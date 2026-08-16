<?php
/**
 * Repair TMDB poster/backdrop assignments for imported movies.
 *
 * Usage:
 *   php repair-movie-images.php <movie_id>
 *   php repair-movie-images.php --all --limit=25 [--offset=0]
 */

/**
 * Repair one movie without altering non-image movie data.
 *
 * Optional callbacks make the behavior deterministic in CLI tests.
 *
 * @param int                  $movie_id Movie ID.
 * @param array<string, mixed> $options  Test/runtime callbacks.
 * @return array<string, mixed>
 */
function streamit_repair_movie_images( $movie_id, array $options = array() ) {
	$movie_id = absint( $movie_id );
	$get_meta = $options['get_meta'] ?? static function ( $id, $key ) {
		return streamit_get_movie_meta( $id, $key, true );
	};
	$update_meta = $options['update_meta'] ?? static function ( $id, $key, $value ) {
		return streamit_update_movie_meta( $id, $key, $value );
	};
	$download = $options['download'] ?? static function ( $url ) {
		return streamit_download_and_attach_movie_image( $url );
	};
	$image_url = $options['image_url'] ?? static function ( $path, $size ) {
		return streamit_get_tmdb_image_url( $path, $size );
	};
	$fetch = $options['fetch'] ?? static function ( $url ) {
		return fetch_tmdb_movie_data( $url );
	};
	$api_key = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
	if ( '' === $api_key ) {
		$settings = @unserialize( get_option( 'streamit_content_import_settings' ) );
		$api_key  = isset( $settings['tmdb']['api_key'] ) ? (string) $settings['tmdb']['api_key'] : '';
	}

	$tmdb_id = $get_meta( $movie_id, '_tmdb_id' );
	if ( ! $movie_id || empty( $tmdb_id ) || '' === $api_key ) {
		return array( 'ok' => false, 'movie_id' => $movie_id, 'reason' => 'missing_tmdb_id_or_api_key' );
	}

	$response = $fetch( "https://api.themoviedb.org/3/movie/{$tmdb_id}?api_key={$api_key}" );
	$data     = ! empty( $response['status'] ) && ! empty( $response['data'] ) ? $response['data'] : array();
	if ( empty( $data ) ) {
		return array( 'ok' => false, 'movie_id' => $movie_id, 'reason' => 'tmdb_fetch_failed' );
	}

	$existing_poster   = absint( $get_meta( $movie_id, '_portrait_thumbmail' ) );
	$existing_backdrop = absint( $get_meta( $movie_id, 'thumbnail_id' ) );
	$poster_id         = $existing_poster;
	$backdrop_id       = $existing_backdrop;
	$messages          = array();
	$changed           = array();

	if ( ! empty( $data['poster_path'] ) ) {
		$result = $download( $image_url( $data['poster_path'], 'w780' ) );
		if ( ! is_wp_error( $result ) ) {
			$poster_id = absint( $result );
			if ( $poster_id && $poster_id !== $existing_poster ) {
				$update_meta( $movie_id, '_portrait_thumbmail', $poster_id );
				$changed[] = '_portrait_thumbmail';
			}
			$messages[] = "poster: OK ({$poster_id})";
		} else {
			$messages[] = 'poster: FAIL; existing poster preserved';
		}
	} else {
		$messages[] = 'poster: SKIP; TMDB has no poster_path';
	}

	if ( ! empty( $data['backdrop_path'] ) ) {
		$backdrop_url = add_query_arg(
			'_streamit_image_role',
			'backdrop',
			$image_url( $data['backdrop_path'], 'original' )
		);
		$result = $download( $backdrop_url );
		if ( ! is_wp_error( $result ) ) {
			$backdrop_id = absint( $result );
			if ( $backdrop_id && $backdrop_id !== $existing_backdrop ) {
				$update_meta( $movie_id, 'thumbnail_id', $backdrop_id );
				$changed[] = 'thumbnail_id';
			}
			$messages[] = "backdrop: OK ({$backdrop_id})";
		} else {
			$messages[] = 'backdrop: FAIL; existing thumbnail_id preserved';
		}
	} elseif ( ! $existing_backdrop && $poster_id ) {
		$backdrop_id = $poster_id;
		$update_meta( $movie_id, 'thumbnail_id', $poster_id );
		$changed[]  = 'thumbnail_id';
		$messages[] = "backdrop: poster fallback ({$poster_id})";
	} else {
		$messages[] = 'backdrop: SKIP; existing thumbnail_id preserved';
	}

	return array(
		'ok'          => true,
		'movie_id'    => $movie_id,
		'tmdb_id'     => (int) $tmdb_id,
		'poster_id'   => $poster_id,
		'backdrop_id' => $backdrop_id,
		'changed'     => $changed,
		'messages'    => $messages,
	);
}

/**
 * CLI entry point.
 *
 * @param string[] $args Arguments excluding script name.
 * @return int
 */
function streamit_repair_movie_images_cli( array $args ) {
	require '/var/www/html/wp-load.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once STREAMIT_PLUGIN_PATH . 'admin/content-import/streamit-tmdb_movie-function.php';

	$movie_ids = array();
	if ( in_array( '--all', $args, true ) ) {
		$limit  = 0;
		$offset = 0;
		foreach ( $args as $arg ) {
			if ( 0 === strpos( $arg, '--limit=' ) ) {
				$limit = absint( substr( $arg, 8 ) );
			}
			if ( 0 === strpos( $arg, '--offset=' ) ) {
				$offset = absint( substr( $arg, 9 ) );
			}
		}
		if ( $limit < 1 || $limit > 100 ) {
			fwrite( STDERR, "--all requires --limit=1..100\n" );
			return 1;
		}

		global $wpdb;
		$table = $wpdb->streamit_moviemeta;
		$movie_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT streamit_movie_id
				FROM {$table}
				WHERE meta_key = %s AND meta_value <> ''
				ORDER BY streamit_movie_id ASC
				LIMIT %d OFFSET %d",
				'_tmdb_id',
				$limit,
				$offset
			)
		);
	} else {
		$movie_id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( $movie_id ) {
			$movie_ids[] = $movie_id;
		}
	}

	if ( empty( $movie_ids ) ) {
		fwrite( STDERR, "Usage: php repair-movie-images.php <movie_id> | --all --limit=25 [--offset=0]\n" );
		return 1;
	}

	$repaired = 0;
	$skipped  = 0;
	foreach ( $movie_ids as $movie_id ) {
		$result = streamit_repair_movie_images( (int) $movie_id );
		if ( empty( $result['ok'] ) ) {
			$skipped++;
			echo "movie {$movie_id}: SKIP ({$result['reason']})\n";
			continue;
		}
		if ( ! empty( $result['changed'] ) ) {
			$repaired++;
		}
		echo "movie {$movie_id}: " . implode( '; ', $result['messages'] ) . "\n";
	}

	echo 'Batch complete: checked=' . count( $movie_ids ) . " repaired={$repaired} skipped={$skipped}\n";
	return 0;
}

if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
	exit( streamit_repair_movie_images_cli( array_slice( $argv, 1 ) ) );
}

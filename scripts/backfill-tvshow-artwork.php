<?php
/**
 * Backfill TMDb poster and backdrop galleries for TV shows imported earlier.
 *
 * Does not replace the primary poster or banner. A list that already has
 * attachment IDs is left alone. Run inside the WordPress container:
 *
 *   php scripts/backfill-tvshow-artwork.php <tvshow_id> [--dry-run]
 *   php scripts/backfill-tvshow-artwork.php --all --limit=5 [--offset=0] [--dry-run]
 */

/**
 * Fill empty gallery lists for one TV show.
 *
 * @param int                  $tvshow_id Streamit TV show ID.
 * @param array<string, mixed> $options   Test hooks: get_meta, fetch_images, apply, dry_run, api_key.
 * @return array<string, mixed>
 */
function movies_wp_backfill_tvshow_artwork( $tvshow_id, array $options = array() ) {
	$tvshow_id = absint( $tvshow_id );
	$get_meta  = $options['get_meta'] ?? static function ( $id, $key ) {
		return streamit_get_tvshow_meta( $id, $key, true );
	};

	$poster_ids   = Movies_WP_Tmdb_Artwork_Importer::existing_ids( $get_meta( $tvshow_id, Movies_WP_Tmdb_Artwork_Importer::POSTER_META ) );
	$backdrop_ids = Movies_WP_Tmdb_Artwork_Importer::existing_ids( $get_meta( $tvshow_id, Movies_WP_Tmdb_Artwork_Importer::BACKDROP_META ) );
	if ( $poster_ids !== array() && $backdrop_ids !== array() ) {
		return array(
			'ok'        => true,
			'tvshow_id' => $tvshow_id,
			'action'    => 'keep_existing',
			'posters'   => count( $poster_ids ),
			'backdrops' => count( $backdrop_ids ),
		);
	}

	$tmdb_id = absint( $get_meta( $tvshow_id, '_tmdb_id' ) );
	if ( $tvshow_id <= 0 || $tmdb_id <= 0 ) {
		return array( 'ok' => false, 'tvshow_id' => $tvshow_id, 'reason' => 'missing_tmdb_id' );
	}

	$fetch = $options['fetch_images'] ?? static function ( $id ) use ( $options ) {
		return movies_wp_backfill_tvshow_fetch_images( $id, $options );
	};
	$body  = $fetch( $tmdb_id );
	if ( is_wp_error( $body ) ) {
		return array(
			'ok'        => false,
			'tvshow_id' => $tvshow_id,
			'reason'    => $body->get_error_code(),
		);
	}
	if ( ! is_array( $body ) ) {
		return array( 'ok' => false, 'tvshow_id' => $tvshow_id, 'reason' => 'tmdb_fetch_failed' );
	}

	$gallery = Movies_WP_Tmdb_Artwork_Selector::select( $body );
	if ( ! empty( $options['dry_run'] ) ) {
		return array(
			'ok'        => true,
			'tvshow_id' => $tvshow_id,
			'action'    => 'dry_run',
			'posters'   => count( $gallery['posters'] ),
			'backdrops' => count( $gallery['backdrops'] ),
		);
	}

	$plan  = array(
		'images'  => array(
			'poster'   => array( 'action' => 'keep_existing' ),
			'backdrop' => array( 'action' => 'keep_existing' ),
		),
		'gallery' => $gallery,
	);
	$apply = $options['apply'] ?? static function ( $id, array $artwork_plan ) {
		return Movies_WP_Streamit_TV_Adapter::apply_images_phase( $id, $artwork_plan );
	};
	$result = $apply( $tvshow_id, $plan );
	$ok     = is_array( $result ) && ! empty( $result['ok'] );

	return array(
		'ok'        => $ok,
		'tvshow_id' => $tvshow_id,
		'action'    => $ok ? 'set' : 'apply_failed',
		'posters'   => count( $gallery['posters'] ),
		'backdrops' => count( $gallery['backdrops'] ),
		'reason'    => $ok ? '' : 'apply_failed',
	);
}

/**
 * @param int                  $tmdb_id TMDb TV id.
 * @param array<string, mixed> $options  api_key override.
 * @return array<string, mixed>|WP_Error
 */
function movies_wp_backfill_tvshow_fetch_images( $tmdb_id, array $options = array() ) {
	$api_key = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
	if ( '' === $api_key && function_exists( 'get_option' ) ) {
		$settings = @unserialize( get_option( 'streamit_content_import_settings' ) );
		$api_key  = isset( $settings['tmdb']['api_key'] ) ? (string) $settings['tmdb']['api_key'] : '';
	}
	if ( '' === $api_key ) {
		return new WP_Error( 'missing_api_key', 'TMDb API key is missing.' );
	}

	$host = defined( 'STREAMIT_TMDB_PROXY_HOST' ) ? STREAMIT_TMDB_PROXY_HOST : 'tmdb.asiastars.ir';
	$url  = sprintf(
		'https://%s/3/tv/%d/images?api_key=%s',
		$host,
		absint( $tmdb_id ),
		rawurlencode( $api_key )
	);
	if ( function_exists( 'streamit_tmdb_server_proxy_url' ) ) {
		$url = streamit_tmdb_server_proxy_url( $url );
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 40,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'tmdb_fetch_failed', 'TMDb images request failed.' );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $status < 200 || $status >= 300 || ! is_array( $body ) ) {
		return new WP_Error( 'tmdb_fetch_failed', 'TMDb images request failed.' );
	}
	return $body;
}

/**
 * @param string[] $args CLI args without the script name.
 * @return int
 */
function movies_wp_backfill_tvshow_artwork_cli( array $args ) {
	require '/var/www/html/wp-load.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$dry_run = in_array( '--dry-run', $args, true );
	$ids     = array();
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
		if ( $limit < 1 || $limit > 10 ) {
			fwrite( STDERR, "--all requires --limit=1..10\n" );
			return 1;
		}

		global $wpdb;
		$table = $wpdb->streamit_tvshowmeta;
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT streamit_tvshow_id
				FROM {$table}
				WHERE meta_key = %s AND meta_value <> ''
				ORDER BY streamit_tvshow_id ASC
				LIMIT %d OFFSET %d",
				'_tmdb_id',
				$limit,
				$offset
			)
		);
	} else {
		$id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( $id ) {
			$ids[] = $id;
		}
	}

	if ( empty( $ids ) ) {
		fwrite( STDERR, "Usage: php scripts/backfill-tvshow-artwork.php <tvshow_id> [--dry-run] | --all --limit=5 [--offset=0] [--dry-run]\n" );
		return 1;
	}

	$filled = 0;
	$kept   = 0;
	$failed = 0;
	foreach ( $ids as $tvshow_id ) {
		$result = movies_wp_backfill_tvshow_artwork(
			(int) $tvshow_id,
			array( 'dry_run' => $dry_run )
		);
		$action = (string) ( $result['action'] ?? $result['reason'] ?? 'failed' );
		echo 'tvshow ' . (int) $tvshow_id . ': ' . $action;
		if ( isset( $result['posters'] ) ) {
			echo ' posters=' . (int) $result['posters'] . ' backdrops=' . (int) $result['backdrops'];
		}
		echo "\n";
		if ( empty( $result['ok'] ) ) {
			++$failed;
		} elseif ( 'keep_existing' === $action ) {
			++$kept;
		} else {
			++$filled;
		}
	}

	echo 'Batch complete: checked=' . count( $ids ) . " filled={$filled} kept={$kept} failed={$failed}\n";
	return $failed > 0 ? 1 : 0;
}

if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( (string) $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
	exit( movies_wp_backfill_tvshow_artwork_cli( array_slice( $argv, 1 ) ) );
}

<?php
/**
 * Read-only dump of one Streamit movie (_source, _subtitles, identity meta).
 *
 * Usage (on the WordPress server or inside the wordpress container):
 *   php tools/inspect-streamit-movie.php --tmdb-id=123456
 *   php tools/inspect-streamit-movie.php --movie-id=42
 *   php tools/inspect-streamit-movie.php --list
 *
 * Never writes. Does not print API keys, HMAC secrets, or signed media tokens.
 *
 * @package movies-wp
 */

if ( php_sapi_name() !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

/**
 * Locate wp-load.php without assuming a single deploy path.
 *
 * @return string
 */
function movies_wp_inspect_find_wp_load() {
	$from_env = getenv( 'WP_LOAD' );
	$candidates = array(
		is_string( $from_env ) && $from_env !== '' ? $from_env : null,
		'/var/www/html/wp-load.php',
		dirname( __DIR__ ) . '/wp-load.php',
		dirname( __DIR__, 2 ) . '/wp-load.php',
	);

	foreach ( $candidates as $path ) {
		if ( is_string( $path ) && is_readable( $path ) ) {
			return $path;
		}
	}

	fwrite( STDERR, "Could not find wp-load.php. Set WP_LOAD or run inside the WordPress container.\n" );
	exit( 1 );
}

require movies_wp_inspect_find_wp_load();

/**
 * Parse --key=value and --key value from argv.
 *
 * @param array<int, string> $argv Argv.
 * @return array{tmdb_id:int,movie_id:int,help:bool,list:bool}
 */
function movies_wp_inspect_parse_args( array $argv ) {
	$out = array(
		'tmdb_id'  => 0,
		'movie_id' => 0,
		'help'     => false,
		'list'     => false,
	);

	$n = count( $argv );
	for ( $i = 1; $i < $n; $i++ ) {
		$arg = (string) $argv[ $i ];
		if ( in_array( $arg, array( '-h', '--help' ), true ) ) {
			$out['help'] = true;
			continue;
		}
		if ( '--list' === $arg ) {
			$out['list'] = true;
			continue;
		}
		if ( preg_match( '/^--tmdb-id=(.+)$/', $arg, $m ) ) {
			$out['tmdb_id'] = absint( $m[1] );
			continue;
		}
		if ( preg_match( '/^--movie-id=(.+)$/', $arg, $m ) ) {
			$out['movie_id'] = absint( $m[1] );
			continue;
		}
		if ( '--tmdb-id' === $arg && isset( $argv[ $i + 1 ] ) ) {
			$out['tmdb_id'] = absint( $argv[ ++$i ] );
			continue;
		}
		if ( '--movie-id' === $arg && isset( $argv[ $i + 1 ] ) ) {
			$out['movie_id'] = absint( $argv[ ++$i ] );
			continue;
		}
		fwrite( STDERR, "Unknown argument: {$arg}\n" );
		exit( 1 );
	}

	return $out;
}

/**
 * Redact secrets and signed media tokens from a string.
 *
 * @param string $value Raw value.
 * @return string
 */
function movies_wp_inspect_redact_string( $value ) {
	$value = (string) $value;
	if ( '' === $value ) {
		return $value;
	}

	$value = preg_replace( '#https?://[^/\s]+/(?:v|d)/[A-Za-z0-9_\-.=]+#', '[redacted-signed-url]', $value );
	$value = preg_replace( '/(api_key|hmac|secret|password|token)=[^&\s]+/i', '$1=[redacted]', $value );

	return is_string( $value ) ? $value : '';
}

/**
 * Recursively redact strings in arrays.
 *
 * @param mixed $data Data.
 * @return mixed
 */
function movies_wp_inspect_redact( $data ) {
	if ( is_string( $data ) ) {
		return movies_wp_inspect_redact_string( $data );
	}
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$out = array();
	foreach ( $data as $key => $value ) {
		$out[ $key ] = movies_wp_inspect_redact( $value );
	}
	return $out;
}

/**
 * Source row keys (sorted) for contract verification.
 *
 * @param mixed $sources _source meta.
 * @return array<int, string>
 */
function movies_wp_inspect_source_keys( $sources ) {
	if ( ! is_array( $sources ) ) {
		return array();
	}
	$keys = array();
	foreach ( $sources as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		foreach ( array_keys( $row ) as $key ) {
			$keys[ (string) $key ] = true;
		}
	}
	$names = array_keys( $keys );
	sort( $names );
	return $names;
}

/**
 * Empty-field counts for live _source convention checks.
 *
 * @param mixed $sources _source meta.
 * @return array<string, int|bool>
 */
function movies_wp_inspect_source_stats( $sources ) {
	$stats = array(
		'empty_name'             => 0,
		'empty_language'         => 0,
		'empty_link'             => 0,
		'empty_download_content' => 0,
		'empty_quality'          => 0,
		'has_file_size_key'      => false,
		'has_unknown_keys'       => false,
	);

	$known = array(
		'name'             => true,
		'link'             => true,
		'is_affiliate'     => true,
		'quality'          => true,
		'language'         => true,
		'date_added'       => true,
		'download_content' => true,
		'player'           => true,
		'file_size'        => true,
	);

	if ( ! is_array( $sources ) ) {
		return $stats;
	}

	foreach ( $sources as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		if ( '' === trim( (string) ( $row['name'] ?? '' ) ) ) {
			++$stats['empty_name'];
		}
		if ( '' === trim( (string) ( $row['language'] ?? '' ) ) ) {
			++$stats['empty_language'];
		}
		if ( '' === trim( (string) ( $row['link'] ?? '' ) ) ) {
			++$stats['empty_link'];
		}
		if ( '' === trim( (string) ( $row['download_content'] ?? '' ) ) ) {
			++$stats['empty_download_content'];
		}
		if ( '' === trim( (string) ( $row['quality'] ?? '' ) ) ) {
			++$stats['empty_quality'];
		}
		if ( array_key_exists( 'file_size', $row ) ) {
			$stats['has_file_size_key'] = true;
		}
		foreach ( array_keys( $row ) as $key ) {
			if ( ! isset( $known[ (string) $key ] ) ) {
				$stats['has_unknown_keys'] = true;
			}
		}
	}

	return $stats;
}

/**
 * Read-only attachment summary for image-field confirmation.
 *
 * @param mixed $attachment_id Attachment ID.
 * @return array<string, mixed>
 */
function movies_wp_inspect_attachment( $attachment_id ) {
	$id = absint( $attachment_id );
	$out = array(
		'id'     => $id,
		'url'    => '',
		'width'  => null,
		'height' => null,
		'mime'   => '',
	);

	if ( $id <= 0 || ! function_exists( 'wp_get_attachment_image_src' ) ) {
		return $out;
	}

	$src = wp_get_attachment_image_src( $id, 'full' );
	if ( is_array( $src ) ) {
		$out['url']    = isset( $src[0] ) ? (string) $src[0] : '';
		$out['width']  = isset( $src[1] ) ? (int) $src[1] : null;
		$out['height'] = isset( $src[2] ) ? (int) $src[2] : null;
	}

	if ( function_exists( 'get_post_mime_type' ) ) {
		$out['mime'] = (string) get_post_mime_type( $id );
	}

	return $out;
}

/**
 * Find movie ID by TMDb meta. Read-only.
 *
 * @param int $tmdb_id TMDb ID.
 * @return array{id:int,total:int,ids:array<int,int>}
 */
function movies_wp_inspect_find_by_tmdb( $tmdb_id ) {
	$tmdb_id = absint( $tmdb_id );
	$ids     = array();

	if ( ! function_exists( 'streamit_get_movies' ) ) {
		fwrite( STDERR, "streamit_get_movies() is not available. Is the Streamit plugin loaded?\n" );
		exit( 1 );
	}

	$result = streamit_get_movies(
		array(
			'per_page'    => 20,
			'paged'       => 1,
			'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			'meta_query'  => array(
				array(
					'key'     => '_tmdb_id',
					'value'   => (string) $tmdb_id,
					'compare' => '=',
				),
			),
		)
	);

	if ( is_object( $result ) && ! empty( $result->results ) && is_array( $result->results ) ) {
		foreach ( $result->results as $movie ) {
			if ( is_object( $movie ) && method_exists( $movie, 'get_id' ) ) {
				$ids[] = (int) $movie->get_id();
			}
		}
	}

	$ids = array_values( array_unique( array_filter( $ids ) ) );
	sort( $ids, SORT_NUMERIC );

	return array(
		'id'    => isset( $ids[0] ) ? $ids[0] : 0,
		'total' => count( $ids ),
		'ids'   => $ids,
	);
}

$args = movies_wp_inspect_parse_args( $argv );

if ( $args['help'] || ( ! $args['list'] && $args['tmdb_id'] <= 0 && $args['movie_id'] <= 0 ) ) {
	fwrite(
		STDOUT,
		"Read-only Streamit movie inspector.\n\n" .
		"Usage:\n" .
		"  php tools/inspect-streamit-movie.php --tmdb-id=123456\n" .
		"  php tools/inspect-streamit-movie.php --movie-id=42\n" .
		"  php tools/inspect-streamit-movie.php --list\n\n" .
		"Does not modify WordPress or Streamit data.\n"
	);
	exit( $args['help'] ? 0 : 1 );
}

if ( ! function_exists( 'streamit_get_movie' ) || ! function_exists( 'streamit_get_movie_meta' ) ) {
	fwrite( STDERR, "Streamit movie functions are not available.\n" );
	exit( 1 );
}

if ( $args['list'] ) {
	if ( ! function_exists( 'streamit_get_movies' ) ) {
		fwrite( STDERR, "streamit_get_movies() is not available.\n" );
		exit( 1 );
	}

	$result = streamit_get_movies(
		array(
			'per_page'    => 25,
			'paged'       => 1,
			'orderby'     => 'ID',
			'order'       => 'DESC',
			'post_status' => array( 'publish', 'draft' ),
		)
	);

	$rows = array();
	if ( is_object( $result ) && ! empty( $result->results ) && is_array( $result->results ) ) {
		foreach ( $result->results as $movie ) {
			if ( ! is_object( $movie ) || ! method_exists( $movie, 'get_id' ) ) {
				continue;
			}
			$id      = (int) $movie->get_id();
			$sources = streamit_get_movie_meta( $id, '_source', true );
			$subs    = streamit_get_movie_meta( $id, '_subtitles', true );
			$rows[]  = array(
				'ID'               => $id,
				'post_title'       => method_exists( $movie, 'get_post_title' ) ? (string) $movie->get_post_title() : '',
				'post_name'        => method_exists( $movie, 'get_post_name' ) ? (string) $movie->get_post_name() : '',
				'post_status'      => method_exists( $movie, 'get_post_status' ) ? (string) $movie->get_post_status() : '',
				'_tmdb_id'         => streamit_get_movie_meta( $id, '_tmdb_id', true ),
				'source_rows'      => is_array( $sources ) ? count( $sources ) : 0,
				'subtitle_rows'    => is_array( $subs ) ? count( $subs ) : 0,
			);
		}
	}

	echo wp_json_encode(
		array(
			'ok'    => true,
			'note'  => 'Read-only list. Pick a row with source_rows > 0, then re-run with --movie-id or --tmdb-id.',
			'total' => is_object( $result ) && isset( $result->total ) ? (int) $result->total : count( $rows ),
			'rows'  => $rows,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	) . "\n";
	exit( 0 );
}

$lookup = array(
	'movie_id'         => $args['movie_id'],
	'tmdb_id_query'    => $args['tmdb_id'] ?: null,
	'match_count'      => null,
	'matching_ids'     => array(),
	'selected_movie_id'=> 0,
);

if ( $args['movie_id'] > 0 ) {
	$lookup['selected_movie_id'] = $args['movie_id'];
} else {
	$found                       = movies_wp_inspect_find_by_tmdb( $args['tmdb_id'] );
	$lookup['match_count']       = $found['total'];
	$lookup['matching_ids']      = $found['ids'];
	$lookup['selected_movie_id'] = $found['id'];
}

$movie_id = (int) $lookup['selected_movie_id'];
if ( $movie_id <= 0 ) {
	fwrite( STDERR, "No Streamit movie found for the given identifier.\n" );
	echo wp_json_encode( array( 'ok' => false, 'lookup' => $lookup ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
	exit( 2 );
}

$movie = streamit_get_movie( $movie_id );
if ( ! $movie || ! is_object( $movie ) ) {
	fwrite( STDERR, "streamit_get_movie({$movie_id}) returned nothing.\n" );
	exit( 2 );
}

$meta_keys = array(
	'_tmdb_id',
	'_imdb_id',
	'thumbnail_id',
	'_portrait_thumbmail',
	'_movie_choice',
	'_movie_url_link',
	'_movie_run_time',
	'_movie_release_date',
	'_language',
	'_country',
	'_source',
	'_subtitles',
	'_media_directory',
	'_cast',
	'_crew',
	'name_custom_imdb_rating',
);

$meta = array();
foreach ( $meta_keys as $key ) {
	$meta[ $key ] = streamit_get_movie_meta( $movie_id, $key, true );
}

$sources = $meta['_source'];
if ( is_string( $sources ) ) {
	$sources = maybe_unserialize( $sources );
}
$subtitles = $meta['_subtitles'];
if ( is_string( $subtitles ) ) {
	$subtitles = maybe_unserialize( $subtitles );
}

$cast = $meta['_cast'];
$crew = $meta['_crew'];

$report = array(
	'ok'     => true,
	'note'   => 'Read-only inspection. No Streamit/WordPress rows were modified.',
	'lookup' => $lookup,
	'movie'  => array(
		'ID'           => method_exists( $movie, 'get_id' ) ? (int) $movie->get_id() : $movie_id,
		'post_title'   => method_exists( $movie, 'get_post_title' ) ? (string) $movie->get_post_title() : '',
		'post_name'    => method_exists( $movie, 'get_post_name' ) ? (string) $movie->get_post_name() : '',
		'post_status'  => method_exists( $movie, 'get_post_status' ) ? (string) $movie->get_post_status() : '',
		'post_excerpt' => method_exists( $movie, 'get_post_excerpt' ) ? (string) $movie->get_post_excerpt() : '',
		'post_type'    => method_exists( $movie, 'get_post_type' ) ? (string) $movie->get_post_type() : '',
		'post_content' => method_exists( $movie, 'get_post_content' ) ? (string) $movie->get_post_content() : '',
	),
	'identity' => array(
		'_tmdb_id'          => $meta['_tmdb_id'],
		'_imdb_id'          => $meta['_imdb_id'],
		'_media_directory'  => $meta['_media_directory'],
	),
	'images' => array(
		'thumbnail_id'        => movies_wp_inspect_attachment( $meta['thumbnail_id'] ),
		'_portrait_thumbmail' => movies_wp_inspect_attachment( $meta['_portrait_thumbmail'] ),
		'same_attachment'     => ( (int) $meta['thumbnail_id'] > 0 && (int) $meta['thumbnail_id'] === (int) $meta['_portrait_thumbmail'] ),
	),
	'playback' => array(
		'_movie_choice'   => $meta['_movie_choice'],
		'_movie_url_link' => movies_wp_inspect_redact( $meta['_movie_url_link'] ),
		'_movie_run_time' => $meta['_movie_run_time'],
		'_movie_release_date' => $meta['_movie_release_date'],
		'_language'       => $meta['_language'],
		'_country'        => $meta['_country'],
		'name_custom_imdb_rating' => $meta['name_custom_imdb_rating'],
	),
	'source_contract' => array(
		'row_count' => is_array( $sources ) ? count( $sources ) : 0,
		'is_array'  => is_array( $sources ),
		'keys_seen' => movies_wp_inspect_source_keys( $sources ),
		'stats'     => movies_wp_inspect_source_stats( $sources ),
		'rows'      => movies_wp_inspect_redact( is_array( $sources ) ? array_values( $sources ) : $sources ),
	),
	'subtitles_contract' => array(
		'row_count' => is_array( $subtitles ) ? count( $subtitles ) : 0,
		'is_array'  => is_array( $subtitles ),
		'rows'      => movies_wp_inspect_redact( is_array( $subtitles ) ? array_values( $subtitles ) : $subtitles ),
	),
	'people' => array(
		'cast_count' => is_array( $cast ) ? count( $cast ) : 0,
		'crew_count' => is_array( $crew ) ? count( $crew ) : 0,
	),
);

$json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $json ) ) {
	fwrite( STDERR, "Failed to encode JSON.\n" );
	exit( 1 );
}

echo $json . "\n";
exit( 0 );

<?php
/**
 * Deterministic CLI tests for the tracked movie image importer patch.
 *
 * Run:
 *   php scripts/tests/movie-image-pipeline-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movie-image-pipeline-test/' );
}
if ( ! defined( 'STREAMIT_TMDB_PROXY_HOST' ) ) {
	define( 'STREAMIT_TMDB_PROXY_HOST', 'tmdb.example.test' );
}

$failures = 0;

function assert_true( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
}

function assert_same( $expected, $actual, string $label ): void {
	assert_true(
		$expected === $actual,
		$label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')'
	);
}

function absint( $value ): int {
	return abs( (int) $value );
}

class Movie_Image_Test_Error {
	private $message;

	public function __construct( string $message ) {
		$this->message = $message;
	}

	public function get_error_message(): string {
		return $this->message;
	}
}

function is_wp_error( $value ): bool {
	return $value instanceof Movie_Image_Test_Error;
}

function add_action( ...$args ): bool {
	unset( $args );
	return true;
}

function add_filter( ...$args ): bool {
	unset( $args );
	return true;
}

function remove_query_arg( $key, $url ) {
	$parts = parse_url( (string) $url );
	if ( false === $parts ) {
		return $url;
	}
	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
		unset( $query[ $key ] );
	}
	$rebuilt = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' ) . ( $parts['path'] ?? '' );
	if ( $query ) {
		$rebuilt .= '?' . http_build_query( $query );
	}
	return $rebuilt;
}

function add_query_arg( $key, $value, $url ) {
	$separator = false === strpos( (string) $url, '?' ) ? '?' : '&';
	return (string) $url . $separator . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
}

$GLOBALS['movie_image_http_requests'] = array();

function streamit_tmdb_server_proxy_url( $url ) {
	return 'https://proxy.example.test/fetch?url=' . rawurlencode( (string) $url );
}

function wp_remote_request( $url, $args ) {
	$GLOBALS['movie_image_http_requests'][] = array( 'url' => $url, 'args' => $args );
	return array( 'url' => $url, 'args' => $args );
}

function streamit_get_tmdb_image_url( $path, $size = 'original' ) {
	return 'https://' . STREAMIT_TMDB_PROXY_HOST . '/t/p/' . $size . '/' . ltrim( (string) $path, '/' );
}

$GLOBALS['movie_image_download_results'] = array();
$GLOBALS['movie_image_meta_writes']      = array();

function streamit_download_and_attach_movie_image( $url ) {
	foreach ( $GLOBALS['movie_image_download_results'] as $needle => $result ) {
		if ( false !== strpos( (string) $url, $needle ) ) {
			return $result;
		}
	}
	return new Movie_Image_Test_Error( 'unexpected URL' );
}

function streamit_add_movie_meta( $movie_id, $key, $value ) {
	$GLOBALS['movie_image_meta_writes'][ $key ] = array( 'movie_id' => $movie_id, 'value' => $value );
	return true;
}

$root = dirname( __DIR__, 2 );
require_once $root . '/scripts/patch-movie-import-images.php';
require_once $root . '/scripts/repair-movie-images.php';
require_once $root . '/wp-content/themes/streamit-child/inc/image-sizes.php';

echo "tracked importer patch\n\n";

$importer = file_get_contents(
	$root . '/wp-content/plugins/streamit/admin/content-import/streamit-tmdb_movie-function.php'
);
$changes  = array();
$patched  = streamit_patch_movie_import_images_content( (string) $importer, $changes );

assert_true( in_array( 'image split', $changes, true ), 'broken importer image block is recognized' );
assert_true( false !== strpos( $patched, "poster_path'], 'w780'" ), 'poster requests w780' );
assert_true( false !== strpos( $patched, "backdrop_path'], 'original'" ), 'backdrop requests original' );
assert_true( false !== strpos( $patched, "'_portrait_thumbmail', \$poster_id" ), 'poster maps only through poster ID' );
assert_true( false !== strpos( $patched, "'thumbnail_id', \$backdrop_id" ), 'backdrop maps to thumbnail_id' );
assert_true(
	false === strpos( $patched, "streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$thumbnail_id)" ),
	'old shared poster assignment removed'
);
assert_true( false !== strpos( $patched, "_streamit_tmdb_source_url" ), 'source metadata is primary identity' );
assert_true( false !== strpos( $patched, '$source_path' ), 'TMDB path deduplicates across source-size changes' );
assert_true( false !== strpos( $patched, 'legacy_attachment_id' ), 'legacy GUID attachment is adopted without duplication' );
assert_true( false === strpos( $patched, 'Update the GUID with the original image URL' ), 'new imports do not rewrite GUID' );

$second_changes = array();
$second_pass = streamit_patch_movie_import_images_content( $patched, $second_changes );
assert_true( $patched === $second_pass, 'deployment patch is idempotent' );
assert_same( array(), $second_changes, 'second patch pass makes no changes' );

// Execute only the transformed image block with deterministic downloader stubs.
$start = strpos( $patched, '    // Portrait poster used by movie cards.' );
$end   = strpos( $patched, '    // Add movie-specific meta from TMDb data', $start );
$block = substr( $patched, $start, $end - $start );

$GLOBALS['movie_image_download_results'] = array( '/w780/' => 101, '/original/' => 202 );
$GLOBALS['movie_image_meta_writes']      = array();
$movie_id                                = 55;
$movie_data                              = array( 'poster_path' => '/poster.jpg', 'backdrop_path' => '/backdrop.jpg' );
eval( $block );

assert_same( 101, $GLOBALS['movie_image_meta_writes']['_portrait_thumbmail']['value'] ?? null, 'poster ID stored in _portrait_thumbmail' );
assert_same( 202, $GLOBALS['movie_image_meta_writes']['thumbnail_id']['value'] ?? null, 'distinct backdrop ID stored in thumbnail_id' );

$GLOBALS['movie_image_download_results'] = array( '/w780/' => 303 );
$GLOBALS['movie_image_meta_writes']      = array();
$movie_data                              = array( 'poster_path' => '/poster.jpg', 'backdrop_path' => '' );
eval( $block );
assert_same( 303, $GLOBALS['movie_image_meta_writes']['thumbnail_id']['value'] ?? null, 'missing backdrop uses poster fallback' );

$GLOBALS['movie_image_download_results'] = array(
	'/w780/'    => 404,
	'/original/' => new Movie_Image_Test_Error( 'backdrop failed' ),
);
$GLOBALS['movie_image_meta_writes'] = array();
$movie_data                         = array( 'poster_path' => '/poster.jpg', 'backdrop_path' => '/backdrop.jpg' );
eval( $block );
assert_same( 404, $GLOBALS['movie_image_meta_writes']['thumbnail_id']['value'] ?? null, 'create fallback uses poster when backdrop download fails' );

echo "\nTMDB source-size filter\n\n";

$GLOBALS['movie_image_http_requests'] = array();
streamit_child_tmdb_import_image_size(
	false,
	array( 'timeout' => 5 ),
	'https://' . STREAMIT_TMDB_PROXY_HOST . '/t/p/original/poster.jpg'
);
$ordinary_request = end( $GLOBALS['movie_image_http_requests'] );
$ordinary_target  = rawurldecode( (string) $ordinary_request['url'] );
assert_true( false !== strpos( $ordinary_target, '/t/p/w1280/poster.jpg' ), 'ordinary original remains capped at w1280' );

$GLOBALS['movie_image_http_requests'] = array();
streamit_child_tmdb_import_image_size(
	false,
	array( 'timeout' => 5 ),
	'https://' . STREAMIT_TMDB_PROXY_HOST . '/t/p/original/backdrop.jpg?_streamit_image_role=backdrop'
);
$backdrop_request = end( $GLOBALS['movie_image_http_requests'] );
$backdrop_target  = rawurldecode( (string) $backdrop_request['url'] );
assert_true( false !== strpos( $backdrop_target, '/t/p/original/backdrop.jpg' ), 'backdrop retains TMDB original' );
assert_true( false === strpos( $backdrop_target, '_streamit_image_role' ), 'internal image-role marker is not sent upstream' );
assert_true( false === strpos( $backdrop_target, '/w1280/' ), 'backdrop is not downgraded to w1280' );

echo "\nrepair behavior\n\n";

$state = array(
	'meta' => array(
		'_tmdb_id'            => 999,
		'_portrait_thumbmail' => 10,
		'thumbnail_id'        => 20,
	),
	'writes' => array(),
);
$options = array(
	'api_key' => 'test-key',
	'get_meta' => static function ( $id, $key ) use ( &$state ) {
		unset( $id );
		return $state['meta'][ $key ] ?? 0;
	},
	'update_meta' => static function ( $id, $key, $value ) use ( &$state ) {
		unset( $id );
		$state['meta'][ $key ] = $value;
		$state['writes'][]     = array( $key, $value );
		return true;
	},
	'fetch' => static function ( $url ) {
		unset( $url );
		return array(
			'status' => true,
			'data'   => array( 'poster_path' => '/poster.jpg', 'backdrop_path' => '/backdrop.jpg' ),
		);
	},
	'image_url' => static function ( $path, $size ) {
		return 'https://tmdb.example.test/t/p/' . $size . $path;
	},
	'download' => static function ( $url ) {
		return false !== strpos( $url, '/w780/' ) ? 101 : 202;
	},
);

$first = streamit_repair_movie_images( 55, $options );
assert_same( array( '_portrait_thumbmail', 'thumbnail_id' ), $first['changed'], 'repair updates poster and backdrop separately' );
assert_same( 101, $state['meta']['_portrait_thumbmail'], 'repair stores portrait attachment' );
assert_same( 202, $state['meta']['thumbnail_id'], 'repair stores landscape attachment' );

$state['writes'] = array();
$second          = streamit_repair_movie_images( 55, $options );
assert_same( array(), $second['changed'], 'second repair is idempotent' );
assert_same( array(), $state['writes'], 'idempotent repair performs no meta writes' );

$failure_state = array(
	'meta' => array( '_tmdb_id' => 999, '_portrait_thumbmail' => 10, 'thumbnail_id' => 777 ),
	'writes' => array(),
);
$failure_options = $options;
$failure_options['get_meta'] = static function ( $id, $key ) use ( &$failure_state ) {
	unset( $id );
	return $failure_state['meta'][ $key ] ?? 0;
};
$failure_options['update_meta'] = static function ( $id, $key, $value ) use ( &$failure_state ) {
	unset( $id );
	$failure_state['meta'][ $key ] = $value;
	$failure_state['writes'][]     = array( $key, $value );
	return true;
};
$failure_options['download'] = static function ( $url ) {
	return false !== strpos( $url, '/w780/' ) ? 101 : new Movie_Image_Test_Error( 'backdrop failed' );
};
$failed = streamit_repair_movie_images( 55, $failure_options );
assert_same( 777, $failed['backdrop_id'], 'failed backdrop preserves valid existing thumbnail_id' );
assert_same( 777, $failure_state['meta']['thumbnail_id'], 'failed backdrop never overwrites existing thumbnail_id' );

echo "\ntemplate and Media Automation contracts\n\n";

$card_template = file_get_contents( $root . '/wp-content/themes/streamit/template-parts/movie/content/movie_thumbnail.php' );
$hero_template = file_get_contents( $root . '/wp-content/themes/streamit/template-parts/movie/content/movie_single_trailer.php' );
$adapter       = file_get_contents(
	$root . '/wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/class-movies-wp-streamit-adapter.php'
);
assert_true( false !== strpos( (string) $card_template, "get_meta('_portrait_thumbmail')" ), 'card template still uses _portrait_thumbmail' );
assert_true( false !== strpos( (string) $hero_template, "get_meta('thumbnail_id')" ), 'detail template still uses thumbnail_id' );
assert_true( false !== strpos( (string) $adapter, 'insert_movie_tmdb_to_streamit' ), 'Media Automation create delegates to native importer' );
assert_true( false === strpos( (string) $adapter, 'backdrop_path' ), 'Media Automation has no duplicate image implementation' );

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}

echo "All movie-image-pipeline assertions passed.\n";

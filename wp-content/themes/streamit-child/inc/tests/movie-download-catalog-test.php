<?php
/**
 * CLI tests for the Movie download section catalog.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/movie-download-catalog-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-movie-download-catalog-test/' );
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {
		unset( $args );
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {
		unset( $args );
		return true;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}

require_once dirname( __DIR__ ) . '/sources-download.php';

if ( ! function_exists( 'movies_wp_user_can_access_media' ) ) {
	function movies_wp_user_can_access_media( $user_id ) {
		unset( $user_id );
		return ! empty( $GLOBALS['movie_download_test_can_download'] );
	}
}

if ( ! function_exists( 'streamit_child_get_subtitles' ) ) {
	function streamit_child_get_subtitles( $st_data ) {
		unset( $st_data );
		return array(
			array(
				'label' => 'فارسی',
				'url'   => 'Movie/Film.fa.srt',
			),
		);
	}
}

if ( ! function_exists( 'streamit_child_resolve_download_href' ) ) {
	function streamit_child_resolve_download_href( $stored, $post_id, $index = 0 ) {
		return 'https://example.test/download?post=' . (int) $post_id . '&index=' . (int) $index . '&path=' . rawurlencode( (string) $stored );
	}
}

if ( ! function_exists( 'streamit_child_resolve_subtitle_url' ) ) {
	function streamit_child_resolve_subtitle_url( $stored, $type = 'v' ) {
		return 'https://example.test/subtitle?type=' . rawurlencode( (string) $type ) . '&path=' . rawurlencode( (string) $stored );
	}
}

require_once dirname( __DIR__ ) . '/movie-download.php';

$failures = 0;

function assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
}

class Movie_Download_Catalog_Test_Object {
	public function get_id() {
		return 42;
	}

	public function get_meta( $key ) {
		return '_source' === $key
			? array(
				array(
					'quality'          => '1080p',
					'name'             => 'YIFY',
					'file_size'        => '2.1 GB',
					'link'             => 'Movie/Film.1080p.mkv',
					'download_content' => 'Movie/Film.1080p.mkv',
				),
				array(
					'quality' => '720p',
					'link'    => 'Movie/Film.720p.mkv',
				),
			)
			: array();
	}
}

$movie = new Movie_Download_Catalog_Test_Object();

$GLOBALS['movie_download_test_can_download'] = true;
$catalog = streamit_child_build_movie_download_catalog( $movie );
assert_true( count( $catalog['sources'] ) === 2, 'both downloadable qualities are included' );
assert_true( str_contains( $catalog['sources'][0]['href'] ?? '', 'post=42&index=0' ), 'movie download URL uses post and source index' );
assert_true( str_contains( $catalog['sources'][1]['href'] ?? '', 'index=1' ), 'link fallback gets its original source index' );
assert_true( ( $catalog['subtitles'][0]['href'] ?? '' ) !== '', 'Persian subtitle URL is resolved when access is granted' );
assert_true( ( $catalog['sources'][0]['encoder'] ?? '' ) === 'YIFY', 'encoder comes from source name' );
assert_true( ( $catalog['sources'][0]['file_size'] ?? '' ) === '2.1 GB', 'file_size is exposed for the row' );
assert_true( ( $catalog['sources'][1]['encoder'] ?? 'x' ) === '', 'missing encoder stays empty' );

$GLOBALS['movie_download_test_can_download'] = false;
$locked = streamit_child_build_movie_download_catalog( $movie );
assert_true( empty( $locked['sources'][0]['href'] ), 'locked catalog does not mint video URLs' );
assert_true( empty( $locked['subtitles'][0]['href'] ), 'locked catalog does not mint subtitle URLs' );
assert_true( count( $locked['sources'] ) === 2 && count( $locked['subtitles'] ) === 1, 'locked catalog still exposes the available download choices' );

if ( $failures > 0 ) {
	exit( 1 );
}

echo "\nAll Movie download catalog tests passed.\n";

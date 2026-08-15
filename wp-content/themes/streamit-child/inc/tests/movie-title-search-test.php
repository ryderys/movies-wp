<?php
/**
 * CLI tests for movie TMDb title display and database-layer search SQL.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/movie-title-search-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-movie-title-search-test/' );
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		unset( $hook );
		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {
		unset( $args );
		return true;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $value ) {
		return (string) $value;
	}
}

if ( ! function_exists( 'streamit_get_permalink' ) ) {
	function streamit_get_permalink( $type, $slug ) {
		return 'https://example.test/' . rawurlencode( (string) $type ) . '/' . rawurlencode( (string) $slug );
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
		unset( $id, $size );
		return 'https://example.test/poster.jpg';
	}
}

if ( ! function_exists( 'streamit_placeholder_image' ) ) {
	function streamit_placeholder_image() {
		return 'https://example.test/placeholder.jpg';
	}
}

class Movie_Title_Search_Wpdb {
	/** @var string */
	public $streamit_moviemeta = 'wp_streamit_moviemeta';

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		foreach ( $args as $value ) {
			if ( false !== strpos( $query, '%d' ) ) {
				$query = preg_replace( '/%d/', (string) (int) $value, $query, 1 );
				continue;
			}
			$quoted = "'" . str_replace( "'", "''", (string) $value ) . "'";
			$query  = preg_replace( '/%s/', $quoted, $query, 1 );
		}
		return $query;
	}
}

class Movie_Title_Test_Object {
	private $post_title;
	private $meta;

	public function __construct( $post_title, array $meta = array() ) {
		$this->post_title = $post_title;
		$this->meta       = $meta;
	}

	public function get_post_title() {
		return $this->post_title;
	}

	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? '';
	}

	public function get_post_type() {
		return 'movie';
	}

	public function get_post_name() {
		return 'decision-to-leave';
	}
}

$GLOBALS['wpdb'] = new Movie_Title_Search_Wpdb();

require_once dirname( __DIR__ ) . '/movie-title-search.php';

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

function assert_eq( $expected, $actual, string $label ): void {
	assert_true(
		$expected === $actual,
		$label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')'
	);
}

function render_movie_title( Movie_Title_Test_Object $movie ): string {
	$st_data = $movie;
	ob_start();
	require dirname( __DIR__, 2 ) . '/template-parts/movie/content/movie_single_title.php';
	return (string) ob_get_clean();
}

echo "movie single title display\n\n";

$html = render_movie_title(
	new Movie_Title_Test_Object(
		'تصمیم به جدایی',
		array(
			'_tmdb_title'          => 'Decision to Leave',
			'_tmdb_original_title' => '헤어질 결심',
		)
	)
);
assert_true( str_contains( $html, '<h1' ) && str_contains( $html, 'تصمیم به جدایی' ), 'Persian post_title remains primary h1' );
assert_true( str_contains( $html, 'streamit-child-tmdb-title' ) && str_contains( $html, 'Decision to Leave' ), '_tmdb_title renders underneath' );
assert_true( ! str_contains( $html, '헤어질 결심' ), '_tmdb_original_title is not rendered' );

$empty_html = render_movie_title( new Movie_Title_Test_Object( 'تصمیم به جدایی' ) );
assert_true( ! str_contains( $empty_html, 'streamit-child-tmdb-title' ), 'empty _tmdb_title renders no secondary title' );

$same_html = render_movie_title(
	new Movie_Title_Test_Object(
		'Decision to Leave',
		array( '_tmdb_title' => 'Decision to Leave' )
	)
);
assert_eq( 1, substr_count( $same_html, 'Decision to Leave' ), 'identical primary and TMDb titles render once' );

echo "\nmovie database search SQL\n\n";

$queries = streamit_child_expand_movie_title_search_query(
	array(
		'paginateQuery' => "SELECT m.* FROM wp_streamit_movie m WHERE m.post_status IN ('publish') AND m.post_title LIKE '%Decision to Leave%' GROUP BY m.ID ORDER BY m.ID DESC LIMIT 6 OFFSET 0",
		'countQuery'    => "SELECT COUNT(DISTINCT m.ID) FROM wp_streamit_movie m WHERE m.post_status IN ('publish') AND m.post_title LIKE '%Decision to Leave%'",
	),
	array( 's' => 'Decision to Leave' )
);

foreach ( array( 'paginateQuery', 'countQuery' ) as $query_key ) {
	$sql = $queries[ $query_key ] ?? '';
	assert_true( str_contains( $sql, "m.post_title LIKE '%Decision to Leave%'" ), "{$query_key}: existing post_title search preserved" );
	assert_true( str_contains( $sql, 'EXISTS (' ), "{$query_key}: metadata search runs in SQL" );
	assert_true( str_contains( $sql, "'_tmdb_title'" ), "{$query_key}: searches _tmdb_title" );
	assert_true( str_contains( $sql, "'_tmdb_original_title'" ), "{$query_key}: searches _tmdb_original_title" );
	assert_true( str_contains( $sql, "search_title_meta.meta_value LIKE '%Decision to Leave%'" ), "{$query_key}: metadata value uses same search term" );
	assert_true( ! str_contains( $sql, 'get_post_meta' ), "{$query_key}: no PHP/N+1 metadata filtering" );
}

$persian_queries = streamit_child_expand_movie_title_search_query(
	array(
		'paginateQuery' => "SELECT m.* FROM wp_streamit_movie m WHERE m.post_title LIKE '%تصمیم به جدایی%' GROUP BY m.ID",
		'countQuery'    => "SELECT COUNT(DISTINCT m.ID) FROM wp_streamit_movie m WHERE m.post_title LIKE '%تصمیم به جدایی%'",
	),
	array( 's' => 'تصمیم به جدایی' )
);
assert_true(
	str_contains( $persian_queries['paginateQuery'], "m.post_title LIKE '%تصمیم به جدایی%'" ),
	'Persian post_title remains searchable'
);

$original_queries = streamit_child_expand_movie_title_search_query(
	array(
		'paginateQuery' => "SELECT m.* FROM wp_streamit_movie m WHERE m.post_title LIKE '%헤어질 결심%' GROUP BY m.ID",
		'countQuery'    => "SELECT COUNT(DISTINCT m.ID) FROM wp_streamit_movie m WHERE m.post_title LIKE '%헤어질 결심%'",
	),
	array( 's' => '헤어질 결심' )
);
assert_true(
	str_contains( $original_queries['paginateQuery'], "search_title_meta.meta_value LIKE '%헤어질 결심%'" ),
	'TMDb original-language title is searchable'
);

echo "\nsearch result display\n\n";

$args = new Movie_Title_Test_Object(
	'تصمیم به جدایی',
	array(
		'_tmdb_title'          => 'Decision to Leave',
		'_tmdb_original_title' => '헤어질 결심',
		'thumbnail_id'         => 1,
	)
);
ob_start();
require dirname( __DIR__, 3 ) . '/streamit/template-parts/common/html-common-list.php';
$result_html = (string) ob_get_clean();
assert_true( str_contains( $result_html, 'تصمیم به جدایی' ), 'search result displays Persian post_title' );
assert_true( ! str_contains( $result_html, 'Decision to Leave' ), 'search result does not replace display title with matched _tmdb_title' );
assert_true( ! str_contains( $result_html, '헤어질 결심' ), 'search result does not display _tmdb_original_title' );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}

echo "All movie-title-search assertions passed.\n";
exit( 0 );

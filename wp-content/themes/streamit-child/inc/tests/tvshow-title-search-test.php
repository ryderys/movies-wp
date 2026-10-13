<?php
/**
 * CLI tests for TV show TMDb title search SQL expansion.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/tvshow-title-search-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-tvshow-title-search-test/' );
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

class Tvshow_Title_Search_Wpdb {
	/** @var string */
	public $streamit_tvshowmeta = 'wp_streamit_tvshowmeta';

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

$GLOBALS['wpdb'] = new Tvshow_Title_Search_Wpdb();

require_once dirname( __DIR__ ) . '/tvshow-title-search.php';

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

echo "tvshow database search SQL\n\n";

$queries = streamit_child_expand_tvshow_title_search_query(
	array(
		'paginateQuery' => "SELECT tv.* FROM wp_streamit_tvshow tv WHERE tv.post_status IN ('publish') AND tv.post_title LIKE '%A Shop for Killers%' GROUP BY tv.ID ORDER BY tv.ID DESC LIMIT 6 OFFSET 0",
		'countQuery'    => "SELECT COUNT(DISTINCT tv.ID) FROM wp_streamit_tvshow tv WHERE tv.post_status IN ('publish') AND tv.post_title LIKE '%A Shop for Killers%'",
	),
	array( 's' => 'A Shop for Killers' )
);

foreach ( array( 'paginateQuery', 'countQuery' ) as $query_key ) {
	$sql = $queries[ $query_key ] ?? '';
	assert_true( str_contains( $sql, "tv.post_title LIKE '%A Shop for Killers%'" ), "{$query_key}: existing post_title search preserved" );
	assert_true( str_contains( $sql, 'EXISTS (' ), "{$query_key}: metadata search runs in SQL" );
	assert_true( str_contains( $sql, "'_tmdb_title'" ), "{$query_key}: searches _tmdb_title" );
	assert_true( str_contains( $sql, "'_tmdb_original_title'" ), "{$query_key}: searches _tmdb_original_title" );
	assert_true( str_contains( $sql, "search_title_meta.meta_value LIKE '%A Shop for Killers%'" ), "{$query_key}: metadata value uses same search term" );
	assert_true( ! str_contains( $sql, 'get_post_meta' ), "{$query_key}: no PHP/N+1 metadata filtering" );
}

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}

echo "All tvshow-title-search assertions passed.\n";
exit( 0 );


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

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( (string) $value );
	}
}

$GLOBALS['tvshow_search_test_hooks'] = array();

/**
 * Minimal hook registry: like WordPress, callbacks receive only the arguments
 * apply_filters() was called with (capped by accepted_args).
 */
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['tvshow_search_test_hooks'][ $hook ][ $priority ][] = array( $callback, (int) $accepted_args );
	return true;
}

function apply_filters( $hook, $value, ...$extra ) {
	$by_priority = $GLOBALS['tvshow_search_test_hooks'][ $hook ] ?? array();
	ksort( $by_priority );
	foreach ( $by_priority as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$args  = array_slice( array_merge( array( $value ), $extra ), 0, $entry[1] );
			$value = call_user_func_array( $entry[0], $args );
		}
	}
	return $value;
}

class Tvshow_Title_Search_Wpdb {
	/** @var string */
	public $streamit_tvshowmeta = 'wp_streamit_tvshowmeta';

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		foreach ( $args as $value ) {
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

/**
 * Same shape as Streamit_Tvshow::prepare_query() output.
 */
function tvshow_queries( string $search = '' ): array {
	$where = "tv.post_status IN ('publish')";
	if ( '' !== $search ) {
		$where .= " AND tv.post_title LIKE '%" . $search . "%'";
	}
	return array(
		'paginateQuery' => "SELECT tv.* FROM wp_streamit_tvshow tv WHERE {$where} GROUP BY tv.ID ORDER BY tv.ID DESC LIMIT 6 OFFSET 0",
		'countQuery'    => "SELECT COUNT(DISTINCT tv.ID) FROM wp_streamit_tvshow tv WHERE {$where}",
	);
}

/**
 * Exactly what Streamit does: one argument, no query args.
 */
function streamit_apply_tvshow_query_filter( array $queries ) {
	return apply_filters( 'streamit_get_tvshows_query', $queries );
}

echo "normal tvshow queries (no search term)\n\n";

$plain = tvshow_queries();
try {
	$result = streamit_apply_tvshow_query_filter( $plain );
	assert_true( true, 'one-argument filter call does not throw' );
	assert_true( $result === $plain, 'normal query is unchanged' );
} catch ( Throwable $e ) {
	assert_true( false, 'one-argument filter call does not throw: ' . $e->getMessage() );
}

apply_filters( 'streamit_tvshows_arguments', array( 'paged' => 1, 'per_page' => 10 ) );
$result = streamit_apply_tvshow_query_filter( $plain );
assert_true( $result === $plain, 'archive args without s leave the query unchanged' );

echo "\nEnglish-name search via staged args (one-argument filter call)\n\n";

apply_filters( 'streamit_tvshows_arguments', array( 's' => 'A Shop for Killers' ) );
$queries = streamit_apply_tvshow_query_filter( tvshow_queries( 'A Shop for Killers' ) );

foreach ( array( 'paginateQuery', 'countQuery' ) as $query_key ) {
	$sql = $queries[ $query_key ] ?? '';
	assert_true( str_contains( $sql, "tv.post_title LIKE '%A Shop for Killers%'" ), "{$query_key}: existing post_title search preserved" );
	assert_true( str_contains( $sql, 'EXISTS (' ), "{$query_key}: metadata search runs in SQL" );
	assert_true( str_contains( $sql, "'_tmdb_title'" ), "{$query_key}: searches _tmdb_title" );
	assert_true( str_contains( $sql, "'_tmdb_original_title'" ), "{$query_key}: searches _tmdb_original_title" );
	assert_true( str_contains( $sql, "search_title_meta.meta_value LIKE '%A Shop for Killers%'" ), "{$query_key}: metadata value uses same search term" );
}

apply_filters( 'streamit_tvshows_arguments', array( 's' => 'فروشگاه قاتلان' ) );
$persian = streamit_apply_tvshow_query_filter( tvshow_queries( 'فروشگاه قاتلان' ) );
assert_true( str_contains( $persian['paginateQuery'], "tv.post_title LIKE '%فروشگاه قاتلان%'" ), 'Persian post_title remains searchable' );

echo "\nstaged term does not leak into later queries\n\n";

apply_filters( 'streamit_tvshows_arguments', array( 'paged' => 2 ) );
$result = streamit_apply_tvshow_query_filter( $plain );
assert_true( $result === $plain, 'a later archive call without s clears the staged term' );

apply_filters( 'streamit_tvshows_arguments', array( 's' => 'Killers' ) );
$result = streamit_apply_tvshow_query_filter( $plain );
assert_true( $result === $plain, 'staged term is a no-op for queries without that search clause' );

echo "\ncallers that pass args directly\n\n";

$GLOBALS['streamit_child_tvshow_search_term'] = '';
$direct = streamit_child_expand_tvshow_title_search_query( tvshow_queries( 'The Husband' ), array( 's' => 'The Husband' ) );
assert_true( str_contains( $direct['paginateQuery'], "search_title_meta.meta_value LIKE '%The Husband%'" ), 'two-argument call still expands the search' );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}

echo "All tvshow-title-search assertions passed.\n";
exit( 0 );

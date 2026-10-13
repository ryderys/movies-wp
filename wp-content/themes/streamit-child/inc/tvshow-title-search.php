<?php
/**
 * Expand Streamit TV show title search to persisted TMDb title metadata.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add `_tmdb_title` and `_tmdb_original_title` matching to Streamit TV show
 * pagination and count queries.
 *
 * This is used by:
 * - header dropdown search (`St_Search_Content_Controller`)
 * - fullscreen search modal (`template-parts/search/ajax_search.php`)
 *
 * @param array<string, string> $queries Streamit paginate/count SQL.
 * @param array<string, mixed>  $args    Streamit tvshow query arguments.
 * @return array<string, string>
 */
function streamit_child_expand_tvshow_title_search_query( $queries, $args ) {
	global $wpdb;

	if ( ! is_array( $queries ) || ! is_array( $args ) || empty( $args['s'] ) ) {
		return $queries;
	}
	if ( ! isset( $wpdb->streamit_tvshowmeta ) || ! is_string( $wpdb->streamit_tvshowmeta ) ) {
		return $queries;
	}

	$search = sanitize_text_field( (string) $args['s'] );
	if ( '' === $search ) {
		return $queries;
	}

	$core_like   = '%' . $search . '%';
	$search_like = '%' . $wpdb->esc_like( $search ) . '%';

	// Streamit TV show queries typically alias the table as `tv`.
	$aliases = array( 'tv', 't' );
	foreach ( $aliases as $alias ) {
		$core_clause = $wpdb->prepare( "{$alias}.post_title LIKE %s", $core_like );
		$expanded    = $wpdb->prepare(
			"({$alias}.post_title LIKE %s OR EXISTS (
				SELECT 1
				FROM {$wpdb->streamit_tvshowmeta} search_title_meta
				WHERE search_title_meta.streamit_tvshow_id = {$alias}.ID
				AND search_title_meta.meta_key IN (%s, %s)
				AND search_title_meta.meta_value LIKE %s
			))",
			$search_like,
			'_tmdb_title',
			'_tmdb_original_title',
			$search_like
		);

		$matched_any = false;
		foreach ( array( 'paginateQuery', 'countQuery' ) as $key ) {
			if ( isset( $queries[ $key ] ) && is_string( $queries[ $key ] ) && false !== strpos( $queries[ $key ], $core_clause ) ) {
				$queries[ $key ] = str_replace( $core_clause, $expanded, $queries[ $key ] );
				$matched_any     = true;
			}
		}

		if ( $matched_any ) {
			return $queries;
		}
	}

	return $queries;
}
add_filter( 'streamit_get_tvshows_query', 'streamit_child_expand_tvshow_title_search_query', 9, 2 );


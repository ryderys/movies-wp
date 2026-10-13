<?php
/**
 * Expand Streamit TV show title search to persisted TMDb title metadata.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stage the TV show search term for the SQL filter.
 *
 * Streamit applies `streamit_get_tvshows_query` with the SQL only (no args),
 * so the term is captured here, like the release-year filter extras.
 *
 * @param array $args Query args.
 * @return array
 */
function streamit_child_stage_tvshow_search_term( $args ) {
	$GLOBALS['streamit_child_tvshow_search_term'] = '';

	if ( is_array( $args ) && ! empty( $args['s'] ) ) {
		$GLOBALS['streamit_child_tvshow_search_term'] = sanitize_text_field( (string) $args['s'] );
	}

	return $args;
}
add_filter( 'streamit_tvshows_arguments', 'streamit_child_stage_tvshow_search_term', 15 );

/**
 * Add `_tmdb_title` and `_tmdb_original_title` matching to Streamit TV show
 * pagination and count queries.
 *
 * @param array<string, string>     $queries Streamit paginate/count SQL.
 * @param array<string, mixed>|null $args    Query args, when the caller passes them.
 * @return array<string, string>
 */
function streamit_child_expand_tvshow_title_search_query( $queries, $args = null ) {
	global $wpdb;

	if ( ! is_array( $queries ) ) {
		return $queries;
	}
	if ( ! isset( $wpdb->streamit_tvshowmeta ) || ! is_string( $wpdb->streamit_tvshowmeta ) ) {
		return $queries;
	}

	if ( is_array( $args ) && ! empty( $args['s'] ) ) {
		$search = sanitize_text_field( (string) $args['s'] );
	} else {
		$search = (string) ( $GLOBALS['streamit_child_tvshow_search_term'] ?? '' );
	}
	if ( '' === $search ) {
		return $queries;
	}

	// Match the core title clause produced by Streamit_Tvshow, then expand it.
	$core_clause = $wpdb->prepare( 'tv.post_title LIKE %s', '%' . $search . '%' );
	$search_like = '%' . $wpdb->esc_like( $search ) . '%';
	$expanded    = $wpdb->prepare(
		"(tv.post_title LIKE %s OR EXISTS (
			SELECT 1
			FROM {$wpdb->streamit_tvshowmeta} search_title_meta
			WHERE search_title_meta.streamit_tvshow_id = tv.ID
			AND search_title_meta.meta_key IN (%s, %s)
			AND search_title_meta.meta_value LIKE %s
		))",
		$search_like,
		'_tmdb_title',
		'_tmdb_original_title',
		$search_like
	);

	foreach ( array( 'paginateQuery', 'countQuery' ) as $key ) {
		if ( isset( $queries[ $key ] ) && is_string( $queries[ $key ] ) ) {
			$queries[ $key ] = str_replace( $core_clause, $expanded, $queries[ $key ] );
		}
	}

	return $queries;
}
add_filter( 'streamit_get_tvshows_query', 'streamit_child_expand_tvshow_title_search_query', 9, 2 );

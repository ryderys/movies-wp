<?php
/**
 * Expand Streamit movie title search to persisted TMDb title metadata.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add `_tmdb_title` and `_tmdb_original_title` matching to both Streamit movie
 * pagination and count queries. Result objects still expose `post_title`.
 *
 * @param array<string, string> $queries Streamit paginate/count SQL.
 * @param array<string, mixed>  $args    Streamit movie query arguments.
 * @return array<string, string>
 */
function streamit_child_expand_movie_title_search_query( $queries, $args ) {
	global $wpdb;

	if ( ! is_array( $queries ) || ! is_array( $args ) || empty( $args['s'] ) ) {
		return $queries;
	}
	if ( ! isset( $wpdb->streamit_moviemeta ) || ! is_string( $wpdb->streamit_moviemeta ) ) {
		return $queries;
	}

	$search = sanitize_text_field( (string) $args['s'] );
	if ( '' === $search ) {
		return $queries;
	}

	// Match the core title clause produced by Streamit_Movie, then expand it.
	$core_like   = '%' . $search . '%';
	$search_like = '%' . $wpdb->esc_like( $search ) . '%';
	$core_clause = $wpdb->prepare( 'm.post_title LIKE %s', $core_like );
	$expanded    = $wpdb->prepare(
		"(m.post_title LIKE %s OR EXISTS (
			SELECT 1
			FROM {$wpdb->streamit_moviemeta} search_title_meta
			WHERE search_title_meta.streamit_movie_id = m.ID
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
add_filter( 'streamit_get_movies_query', 'streamit_child_expand_movie_title_search_query', 10, 2 );

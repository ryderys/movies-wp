<?php
/**
 * Resolve movies/TV shows related to a person (cast/crew history).
 *
 * Streamit stores forward links on movie/tvshow (`_cast` / `_crew`) and reverse
 * links on the person (`_movie_cast`, `_tvshow_cast`, …). Reverse links are
 * sometimes missing after imports, so we fall back to scanning content meta.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalize a list of IDs from person reverse-meta.
 *
 * @param mixed $ids Raw meta value.
 * @return int[]
 */
function streamit_child_normalize_related_ids( $ids ) {
	if ( empty( $ids ) || ! is_array( $ids ) ) {
		return array();
	}

	return array_values(
		array_unique(
			array_filter(
				array_map( 'absint', $ids )
			)
		)
	);
}

/**
 * Build LIKE patterns that match a person id inside serialized `_cast` / `_crew`.
 *
 * @param int $person_id Person ID.
 * @return string[]
 */
function streamit_child_person_cast_like_patterns( $person_id ) {
	$person_id = absint( $person_id );
	$id_str    = (string) $person_id;

	return array(
		'%"id";i:' . $person_id . ';%',
		'%"id";s:' . strlen( $id_str ) . ':"' . $id_str . '";%',
	);
}

/**
 * Find movie/tvshow IDs that reference this person in `_cast` or `_crew`.
 *
 * @param int    $person_id   Person ID.
 * @param string $object_type movie|tvshow.
 * @return int[]
 */
function streamit_child_find_content_ids_for_person( $person_id, $object_type ) {
	$person_id = absint( $person_id );
	if ( $person_id < 1 ) {
		return array();
	}

	$getter = ( 'tvshow' === $object_type ) ? 'streamit_get_tvshows' : 'streamit_get_movies';
	if ( ! function_exists( $getter ) ) {
		return array();
	}

	$patterns   = streamit_child_person_cast_like_patterns( $person_id );
	$meta_query = array( 'relation' => 'OR' );

	foreach ( array( '_cast', '_crew' ) as $meta_key ) {
		foreach ( $patterns as $pattern ) {
			$meta_query[] = array(
				'key'     => $meta_key,
				'value'   => $pattern,
				'compare' => 'LIKE',
			);
		}
	}

	$result = call_user_func(
		$getter,
		array(
			'per_page'   => -1,
			'meta_query' => $meta_query,
		)
	);

	if ( empty( $result->results ) || ! is_array( $result->results ) ) {
		return array();
	}

	$ids = array();
	foreach ( $result->results as $item ) {
		if ( is_object( $item ) && method_exists( $item, 'get_id' ) ) {
			$ids[] = absint( $item->get_id() );
		}
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Collect related movie + TV show IDs for a person (reverse meta + fallback).
 *
 * @param object $person Person object.
 * @return array{movie_ids:int[],tvshow_ids:int[]}
 */
function streamit_child_get_person_history_ids( $person ) {
	$movie_ids  = streamit_child_normalize_related_ids( $person->get_meta( '_movie_cast' ) );
	$movie_ids  = array_merge( $movie_ids, streamit_child_normalize_related_ids( $person->get_meta( '_movie_crew' ) ) );
	$tvshow_ids = streamit_child_normalize_related_ids( $person->get_meta( '_tvshow_cast' ) );
	$tvshow_ids = array_merge( $tvshow_ids, streamit_child_normalize_related_ids( $person->get_meta( '_tvshow_crew' ) ) );

	$movie_ids  = array_values( array_unique( array_filter( $movie_ids ) ) );
	$tvshow_ids = array_values( array_unique( array_filter( $tvshow_ids ) ) );

	$person_id = absint( $person->get_id() );

	// Reverse meta missing (common after some imports) — scan content `_cast`/`_crew`.
	if ( empty( $movie_ids ) ) {
		$found = streamit_child_find_content_ids_for_person( $person_id, 'movie' );
		if ( ! empty( $found ) ) {
			$movie_ids = $found;
			if ( function_exists( 'streamit_add_person_relation' ) ) {
				foreach ( $found as $movie_id ) {
					streamit_add_person_relation( $person_id, '_movie_cast', $movie_id );
				}
			}
		}
	}

	if ( empty( $tvshow_ids ) ) {
		$found = streamit_child_find_content_ids_for_person( $person_id, 'tvshow' );
		if ( ! empty( $found ) ) {
			$tvshow_ids = $found;
			if ( function_exists( 'streamit_add_person_relation' ) ) {
				foreach ( $found as $tvshow_id ) {
					streamit_add_person_relation( $person_id, '_tvshow_cast', $tvshow_id );
				}
			}
		}
	}

	return array(
		'movie_ids'  => $movie_ids,
		'tvshow_ids' => $tvshow_ids,
	);
}

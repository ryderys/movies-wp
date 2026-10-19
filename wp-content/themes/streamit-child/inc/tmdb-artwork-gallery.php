<?php
/**
 * Attachment IDs for the TMDb poster and backdrop galleries.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Positive unique attachment IDs, capped at 10.
 *
 * @param mixed $raw Meta value.
 * @return int[]
 */
function streamit_child_tmdb_artwork_ids( $raw ) {
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$ids  = array();
	$seen = array();
	foreach ( $raw as $id ) {
		$id = (int) $id;
		if ( $id <= 0 || isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = true;
		$ids[]       = $id;
		if ( count( $ids ) >= 10 ) {
			break;
		}
	}

	return $ids;
}

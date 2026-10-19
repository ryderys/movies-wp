<?php
/**
 * Attachment IDs for the TMDb poster and backdrop galleries.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Positive unique attachment IDs for the front-end gallery, capped at 10.
 *
 * Drops the primary portrait/banner when those IDs appear in the gallery list,
 * and collapses attachments that share the same TMDb file basename.
 *
 * @param mixed    $raw           Meta value.
 * @param int[]    $exclude_ids   Primary artwork attachment IDs to skip.
 * @return int[]
 */
function streamit_child_tmdb_artwork_ids( $raw, array $exclude_ids = array() ) {
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$exclude = array();
	foreach ( $exclude_ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$exclude[ $id ] = true;
		}
	}

	$ids      = array();
	$seen_ids = array();
	$seen_key = array();
	foreach ( $raw as $id ) {
		$id = (int) $id;
		if ( $id <= 0 || isset( $seen_ids[ $id ] ) || isset( $exclude[ $id ] ) ) {
			continue;
		}
		$key = streamit_child_tmdb_artwork_dedupe_key( $id );
		if ( null !== $key && isset( $seen_key[ $key ] ) ) {
			continue;
		}
		if ( null !== $key ) {
			$seen_key[ $key ] = true;
		}
		$seen_ids[ $id ] = true;
		$ids[]           = $id;
		if ( count( $ids ) >= 10 ) {
			break;
		}
	}

	return $ids;
}

/**
 * Stable key for one attachment: TMDb basename when recorded, else the ID.
 *
 * @param int $attachment_id Attachment ID.
 * @return string|null
 */
function streamit_child_tmdb_artwork_dedupe_key( $attachment_id ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
		return null;
	}
	$source = (string) get_post_meta( $attachment_id, '_streamit_tmdb_source_url', true );
	if ( '' === $source ) {
		return 'id:' . $attachment_id;
	}
	$path = (string) wp_parse_url( $source, PHP_URL_PATH );
	$base = $path !== '' ? wp_basename( $path ) : '';
	return '' !== $base ? strtolower( $base ) : 'id:' . $attachment_id;
}

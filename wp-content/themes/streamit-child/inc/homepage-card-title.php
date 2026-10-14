<?php
/**
 * Homepage card title: English TMDb title for movie / TV show cards.
 *
 * Display-only. Single pages, search and stored titles keep using the post title.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Title text for a homepage card.
 *
 * On the front page, movie and TV show cards show `_tmdb_title` (English).
 * Everywhere else, for other content types, or when `_tmdb_title` is empty,
 * the existing post title is returned unchanged.
 *
 * @param object $st_data Streamit content object.
 * @return string Unescaped title.
 */
function streamit_child_homepage_card_title( $st_data ) {
	$title = is_object( $st_data ) && method_exists( $st_data, 'get_post_title' )
		? (string) $st_data->get_post_title()
		: '';

	if ( ! is_front_page() || ! is_object( $st_data ) || ! method_exists( $st_data, 'get_meta' ) || ! method_exists( $st_data, 'get_post_type' ) ) {
		return $title;
	}

	if ( ! in_array( $st_data->get_post_type(), array( 'movie', 'tvshow' ), true ) ) {
		return $title;
	}

	$english = trim( (string) $st_data->get_meta( '_tmdb_title' ) );

	return '' !== $english ? $english : $title;
}

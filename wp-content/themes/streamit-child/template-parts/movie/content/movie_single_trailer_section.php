<?php
/**
 * Movie detail: separate trailer section, above the download section.
 *
 * Renders nothing when the movie has no playable trailer.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $st_data ) || ! function_exists( 'streamit_child_render_movie_trailer_section' ) ) {
	return;
}

echo streamit_child_render_movie_trailer_section( $st_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

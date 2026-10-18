<?php
/**
 * TV show detail: separate trailer section, above the episodes slider.
 *
 * Renders nothing when the show has no playable trailer.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $st_data ) || ! function_exists( 'streamit_child_render_tvshow_trailer_section' ) ) {
	return;
}

echo streamit_child_render_tvshow_trailer_section( $st_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

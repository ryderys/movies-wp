<?php
/**
 * Paginated Person Card Elementor widget for the Casts page.
 *
 * Parent widget forces per_page=-1 when persons are selected, which dumps
 * every cast on /casts/. This override respects posts_per_page + ?paged=N.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current page number from the request (works on WP Pages like /casts/).
 *
 * @return int
 */
function streamit_child_get_request_page_number() {
	$paged = absint( get_query_var( 'paged' ) );

	if ( $paged < 1 ) {
		$paged = absint( get_query_var( 'page' ) );
	}

	if ( $paged < 1 && ! empty( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = absint( wp_unslash( $_GET['paged'] ) );
	}

	return max( 1, $paged );
}

/**
 * Persons per page for the Elementor Person Card grid (clamped to 10–20).
 *
 * @param array $settings Widget settings.
 * @return int
 */
function streamit_child_person_card_per_page( $settings ) {
	$per_page = ! empty( $settings['posts_per_page'] ) ? absint( $settings['posts_per_page'] ) : 12;

	if ( $per_page < 10 ) {
		$per_page = 10;
	} elseif ( $per_page > 20 ) {
		$per_page = 20;
	}

	return $per_page;
}

/**
 * Replace Streamit's Person Card widget with a paginated version.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
 */
function streamit_child_register_paginated_person_card( $widgets_manager ) {
	if ( ! class_exists( '\Elementor\ST_Person' ) ) {
		return;
	}

	require_once get_stylesheet_directory() . '/inc/elementor/class-st-person-card-paginated.php';

	$widgets_manager->unregister( 'person-card' );
	$widgets_manager->register( new \Elementor\ST_Person_Card_Paginated() );
}
add_action( 'elementor/widgets/register', 'streamit_child_register_paginated_person_card', 99 );

/**
 * Keep ?paged=N on the Casts page (WP pages otherwise canonical-redirect it away).
 *
 * @param string|false $redirect_url  Canonical redirect URL.
 * @param string       $requested_url Requested URL.
 * @return string|false
 */
function streamit_child_casts_page_allow_paged( $redirect_url, $requested_url ) {
	if ( empty( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $redirect_url;
	}

	if ( is_page( 'casts' ) || is_page( 2143 ) ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'streamit_child_casts_page_allow_paged', 10, 2 );

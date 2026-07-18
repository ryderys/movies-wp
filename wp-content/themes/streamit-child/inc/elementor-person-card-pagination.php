<?php
/**
 * Paginated Person Card Elementor widget for the Casts page.
 *
 * Parent widget forces per_page=-1 when persons are selected, which dumps
 * every cast on /casts/. This override respects posts_per_page + ?cast_page=N.
 *
 * Important: do NOT use WordPress's ?paged= here — ArchiveAppend reads every
 * query arg as a filter, empties .data-listing, and leaves the page blank.
 */

defined( 'ABSPATH' ) || exit;

/** Query arg used for Casts pagination (avoids WP/ArchiveAppend ?paged conflicts). */
const STREAMIT_CHILD_CASTS_PAGE_VAR = 'cast_page';

/**
 * Register cast_page as a public query var.
 *
 * @param array $vars Public query vars.
 * @return array
 */
function streamit_child_casts_query_vars( $vars ) {
	$vars[] = STREAMIT_CHILD_CASTS_PAGE_VAR;
	return $vars;
}
add_filter( 'query_vars', 'streamit_child_casts_query_vars' );

/**
 * Current Casts page number from the request.
 *
 * @return int
 */
function streamit_child_get_request_page_number() {
	$paged = absint( get_query_var( STREAMIT_CHILD_CASTS_PAGE_VAR ) );

	if ( $paged < 1 && ! empty( $_GET[ STREAMIT_CHILD_CASTS_PAGE_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = absint( wp_unslash( $_GET[ STREAMIT_CHILD_CASTS_PAGE_VAR ] ) );
	}

	// Legacy links that still use ?paged=N.
	if ( $paged < 1 ) {
		$paged = absint( get_query_var( 'paged' ) );
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
 * Render numbered pagination using ?cast_page=N (not ?paged=).
 *
 * @param int $total_pages  Total pages.
 * @param int $current_page Current page.
 */
function streamit_child_casts_pagination( $total_pages, $current_page = 1 ) {
	$total_pages  = max( 1, (int) $total_pages );
	$current_page = max( 1, (int) $current_page );

	if ( $total_pages <= 1 ) {
		return;
	}

	$base_url = get_permalink();
	if ( ! is_string( $base_url ) || '' === $base_url ) {
		$base_url = home_url( '/' );
	}

	$paginate_links = paginate_links(
		array(
			'base'      => esc_url_raw( add_query_arg( STREAMIT_CHILD_CASTS_PAGE_VAR, '%#%', $base_url ) ),
			'format'    => '',
			'total'     => $total_pages,
			'current'   => $current_page,
			'show_all'  => false,
			'end_size'  => 1,
			'mid_size'  => 2,
			'prev_next' => true,
			'prev_text' => function_exists( 'st_get_icon' ) ? st_get_icon( 'arrow-prev' ) : '&laquo;',
			'next_text' => function_exists( 'st_get_icon' ) ? st_get_icon( 'arrow-next' ) : '&raquo;',
			'type'      => 'list',
		)
	);

	if ( empty( $paginate_links ) ) {
		return;
	}

	echo '<div class="col-lg-12 col-md-12 col-sm-12">';
	echo '<div class="pagination justify-content-center">';
	echo '<nav aria-label="Page navigation">';
	echo $paginate_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() is escaped.
	echo '</nav></div></div>';
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
 * Keep pagination query args on the Casts page (avoid canonical strip).
 *
 * @param string|false $redirect_url  Canonical redirect URL.
 * @param string       $requested_url Requested URL.
 * @return string|false
 */
function streamit_child_casts_page_allow_paged( $redirect_url, $requested_url ) {
	$has_cast_page = ! empty( $_GET[ STREAMIT_CHILD_CASTS_PAGE_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$has_paged     = ! empty( $_GET['paged'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! $has_cast_page && ! $has_paged ) {
		return $redirect_url;
	}

	if ( is_page( 'casts' ) || is_page( 2143 ) ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'streamit_child_casts_page_allow_paged', 10, 2 );

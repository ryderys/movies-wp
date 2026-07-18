<?php
/**
 * Persons (casts) archive: fixed page size + URL-based pagination.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Persons shown per archive page (grid is 6-across on xl, so multiples of 6 fit well).
 */
const STREAMIT_CHILD_PERSONS_PER_PAGE = 12;

/**
 * Apply per-page and paged args for the persons archive.
 *
 * AJAX/REST load-more already passes its own paged — leave that alone.
 *
 * @param array $args Query args for streamit_get_persons().
 * @return array
 */
function streamit_child_persons_archive_args( $args ) {
	$args['per_page'] = STREAMIT_CHILD_PERSONS_PER_PAGE;

	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
		return $args;
	}

	$paged = 1;

	if ( get_query_var( 'paged' ) ) {
		$paged = absint( get_query_var( 'paged' ) );
	} elseif ( ! empty( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = absint( wp_unslash( $_GET['paged'] ) );
	}

	$args['paged'] = max( 1, $paged );

	return $args;
}
add_filter( 'streamit_persons_arguments', 'streamit_child_persons_archive_args' );

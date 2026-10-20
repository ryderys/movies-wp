<?php
/**
 * Lazy-load TV-show "more like this" slider after first paint.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether lazy morelike is enabled for this request.
 *
 * Sync escape hatch: ?stc_sync_morelike=1 forces the parent template (no-JS / failures).
 *
 * @return bool
 */
function streamit_child_lazy_morelike_enabled() {
	if ( isset( $_GET['stc_sync_morelike'] ) && '1' === (string) wp_unslash( $_GET['stc_sync_morelike'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return false;
	}

	/**
	 * Filter: disable lazy morelike (e.g. tests).
	 *
	 * @param bool $enabled Default true.
	 */
	return (bool) apply_filters( 'streamit_child_lazy_morelike_enabled', true );
}

/**
 * Parent morelike template path (never the child override).
 *
 * @return string
 */
function streamit_child_lazy_morelike_parent_template() {
	return get_template_directory() . '/template-parts/tvshow/content/tvshow_single_morelike_slider.php';
}

/**
 * Render parent morelike markup for a TV-show object.
 *
 * @param object $st_data Streamit TV-show object.
 * @return string HTML (may be empty when recommendations are off / none).
 */
function streamit_child_lazy_morelike_render_html( $st_data ) {
	$parent = streamit_child_lazy_morelike_parent_template();
	if ( ! is_readable( $parent ) ) {
		return '';
	}

	ob_start();
	// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
	include $parent;
	return trim( (string) ob_get_clean() );
}

/**
 * Sync reload URL that forces server-rendered morelike.
 *
 * @param int $post_id TV-show ID (for fragment target).
 * @return string
 */
function streamit_child_lazy_morelike_sync_url( $post_id ) {
	$url = add_query_arg( 'stc_sync_morelike', '1' );
	return $url . '#stc-lazy-morelike-' . (int) $post_id;
}

/**
 * Render placeholder shell for deferred morelike HTML.
 *
 * Includes a visible sync fallback for no-JS and AJAX failures (not an empty permanent section).
 *
 * @param object $st_data Streamit TV-show object.
 */
function streamit_child_render_lazy_morelike_placeholder( $st_data ) {
	if ( ! is_object( $st_data ) || ! method_exists( $st_data, 'get_id' ) ) {
		return;
	}

	$post_id = (int) $st_data->get_id();
	if ( $post_id <= 0 ) {
		return;
	}

	$theme_dir = get_stylesheet_directory();
	$theme_uri = get_stylesheet_directory_uri();
	$js_path   = $theme_dir . '/assets/js/lazy-morelike.js';
	wp_enqueue_script(
		'streamit-child-lazy-morelike',
		$theme_uri . '/assets/js/lazy-morelike.js',
		array( 'jquery', 'st-slick-js' ),
		file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0',
		true
	);
	wp_localize_script(
		'streamit-child-lazy-morelike',
		'streamitChildLazyMorelike',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'action'  => 'streamit_child_lazy_morelike',
		)
	);

	$sync_url = streamit_child_lazy_morelike_sync_url( $post_id );
	$nonce    = wp_create_nonce( 'streamit_child_lazy_morelike_' . $post_id );

	printf(
		'<div class="stc-lazy-morelike" id="stc-lazy-morelike-%1$d" data-post-id="%1$d" data-nonce="%2$s" aria-busy="true">',
		$post_id,
		esc_attr( $nonce )
	);
	printf(
		'<p class="stc-lazy-morelike__fallback"><a href="%1$s">%2$s</a></p>',
		esc_url( $sync_url ),
		esc_html__( 'نمایش پیشنهادهای مشابه', 'streamit' )
	);
	// No-JS: same sync link (avoids paying morelike query cost on every TTFB).
	printf(
		'<noscript><p class="stc-lazy-morelike__noscript"><a href="%1$s">%2$s</a></p></noscript>',
		esc_url( $sync_url ),
		esc_html__( 'نمایش پیشنهادهای مشابه', 'streamit' )
	);
	echo '</div>';
}

/**
 * AJAX: render parent morelike slider markup for a TV-show ID.
 */
function streamit_child_ajax_lazy_morelike() {
	$post_id = isset( $_REQUEST['post_id'] ) ? (int) $_REQUEST['post_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $post_id <= 0 ) {
		wp_send_json_error( array( 'message' => 'invalid_post' ), 400 );
	}

	check_ajax_referer( 'streamit_child_lazy_morelike_' . $post_id, 'nonce' );

	if ( ! function_exists( 'streamit_get_tvshow' ) ) {
		wp_send_json_error( array( 'message' => 'streamit_unavailable' ), 500 );
	}

	$st_data = streamit_get_tvshow( $post_id );
	if ( ! is_object( $st_data ) || ! method_exists( $st_data, 'get_id' ) || (int) $st_data->get_id() !== $post_id ) {
		wp_send_json_error( array( 'message' => 'not_found' ), 404 );
	}

	$html = streamit_child_lazy_morelike_render_html( $st_data );
	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_streamit_child_lazy_morelike', 'streamit_child_ajax_lazy_morelike' );
add_action( 'wp_ajax_nopriv_streamit_child_lazy_morelike', 'streamit_child_ajax_lazy_morelike' );

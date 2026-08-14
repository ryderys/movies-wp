<?php

/**
 * Enqueue admin AJAX payload rewriter after Streamit admin scripts.
 *
 * @param string $hook Current admin page hook.
 */
function streamit_child_enqueue_ajax_payload_fix( $hook ) {
	$screens = array(
		'admin_page_streamit-edit-movie',
		'admin_page_streamit-add-movie',
		'admin_page_streamit-edit-tvshow',
		'admin_page_streamit-add-tvshow',
		'admin_page_streamit-edit-tvshow-episode',
		'admin_page_streamit-add-tvshow-episode',
		'admin_page_streamit-edit-video',
		'admin_page_streamit-add-video',
	);

	if ( ! in_array( $hook, $screens, true ) ) {
		return;
	}

	$js_file = get_stylesheet_directory() . '/assets/js/admin-ajax-payload.js';
	if ( file_exists( $js_file ) ) {
		wp_enqueue_script(
			'streamit-child-admin-ajax-payload',
			get_stylesheet_directory_uri() . '/assets/js/admin-ajax-payload.js',
			array( 'jquery' ),
			(string) filemtime( $js_file ),
			true
		);
	}

	$feedback_js  = get_stylesheet_directory() . '/assets/js/admin-save-feedback.js';
	$feedback_css = get_stylesheet_directory() . '/assets/css/admin-save-feedback.css';
	if ( file_exists( $feedback_js ) ) {
		wp_enqueue_script(
			'streamit-child-admin-save-feedback',
			get_stylesheet_directory_uri() . '/assets/js/admin-save-feedback.js',
			array( 'jquery' ),
			(string) filemtime( $feedback_js ),
			true
		);
	}
	if ( file_exists( $feedback_css ) ) {
		wp_enqueue_style(
			'streamit-child-admin-save-feedback',
			get_stylesheet_directory_uri() . '/assets/css/admin-save-feedback.css',
			array(),
			(string) filemtime( $feedback_css )
		);
	}
}
add_action( 'admin_enqueue_scripts', 'streamit_child_enqueue_ajax_payload_fix', 120 );

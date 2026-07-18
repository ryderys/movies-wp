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
	);

	if ( ! in_array( $hook, $screens, true ) ) {
		return;
	}

	$js_file = get_stylesheet_directory() . '/assets/js/admin-ajax-payload.js';
	if ( ! file_exists( $js_file ) ) {
		return;
	}

	wp_enqueue_script(
		'streamit-child-admin-ajax-payload',
		get_stylesheet_directory_uri() . '/assets/js/admin-ajax-payload.js',
		array( 'jquery' ),
		(string) filemtime( $js_file ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'streamit_child_enqueue_ajax_payload_fix', 120 );

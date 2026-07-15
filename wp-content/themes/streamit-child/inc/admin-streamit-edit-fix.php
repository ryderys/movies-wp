<?php
/**
 * Admin edit UX fixes: Select2 RTL layout + relaxed validation on update.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streamit admin screens that use Select2 / edit forms.
 *
 * @return string[]
 */
function streamit_child_get_streamit_edit_admin_screens() {
	return array(
		'admin_page_streamit-edit-movie',
		'admin_page_streamit-add-movie',
		'admin_page_streamit-edit-tvshow',
		'admin_page_streamit-add-tvshow',
		'admin_page_streamit-edit-tvshow-episode',
		'admin_page_streamit-add-tvshow-episode',
		'tv-shows_page_streamit-add-tvshow-tag',
		'admin_page_streamit-edit-tvshow-tag',
	);
}

/**
 * Enqueue Select2 RTL CSS and edit-form JS fixes.
 *
 * @param string $hook Current admin page hook.
 */
function streamit_child_enqueue_streamit_edit_fixes( $hook ) {
	if ( ! in_array( $hook, streamit_child_get_streamit_edit_admin_screens(), true ) ) {
		return;
	}

	$css_file = get_stylesheet_directory() . '/assets/css/admin-select2-rtl-fix.css';
	if ( file_exists( $css_file ) ) {
		wp_enqueue_style(
			'streamit-child-admin-select2-rtl-fix',
			get_stylesheet_directory_uri() . '/assets/css/admin-select2-rtl-fix.css',
			array(),
			(string) filemtime( $css_file )
		);
	}

	$js_file = get_stylesheet_directory() . '/assets/js/admin-streamit-edit-fix.js';
	if ( file_exists( $js_file ) ) {
		wp_enqueue_script(
			'streamit-child-admin-streamit-edit-fix',
			get_stylesheet_directory_uri() . '/assets/js/admin-streamit-edit-fix.js',
			array( 'jquery', 'wp-hooks' ),
			(string) filemtime( $js_file ),
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'streamit_child_enqueue_streamit_edit_fixes', 110 );

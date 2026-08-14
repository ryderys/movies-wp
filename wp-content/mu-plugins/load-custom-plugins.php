<?php
/**
 * Plugin Name: Load custom-plugins
 * Description: Loads plugin packages from wp-content/plugins/custom-plugins/{slug}/{slug}.php (git-tracked).
 * Version: 1.0.0
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

$movies_wp_custom_plugins = WP_PLUGIN_DIR . '/custom-plugins';
if ( ! is_dir( $movies_wp_custom_plugins ) ) {
	return;
}

foreach ( glob( $movies_wp_custom_plugins . '/*', GLOB_ONLYDIR ) ?: array() as $plugin_dir ) {
	$slug = basename( $plugin_dir );
	$file = $plugin_dir . '/' . $slug . '.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}
}

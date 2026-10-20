<?php
/**
 * Plugin Name: STC Front Bootstrap Trim
 * Description: Front-end bootstrap cuts: skip admin-only plugins site-wide; skip Elementor/Pro on Streamit single URL prefixes; disable Action Scheduler async runner on HTML front requests.
 * Version: 1.1.0
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Request URI path (lowercase, no trailing slash).
 *
 * @return string
 */
function stc_front_bootstrap_request_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	$path = (string) ( function_exists( 'wp_parse_url' ) ? wp_parse_url( $uri, PHP_URL_PATH ) : parse_url( $uri, PHP_URL_PATH ) );
	$path = strtolower( rtrim( (string) $path, '/' ) );
	return ( '' === $path ) ? '/' : $path;
}

/**
 * Admin / CLI / cron / AJAX / REST — never strip plugins or block AS runners.
 *
 * @return bool
 */
function stc_front_bootstrap_is_privileged_request() {
	if ( is_admin() ) {
		return true;
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return true;
	}
	// PHP CLI (wp eval, docker php scripts) must keep AS / full plugin set.
	if ( function_exists( 'php_sapi_name' ) && 'cli' === php_sapi_name() ) {
		return true;
	}
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return true;
	}
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return true;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return true;
	}

	$path = stc_front_bootstrap_request_path();
	if ( 0 === strpos( $path, '/wp-admin' ) || 0 === strpos( $path, '/wp-json' ) || 0 === strpos( $path, '/wp-cron' ) ) {
		return true;
	}

	return false;
}

/**
 * Whether this front request should keep Elementor loaded.
 *
 * @return bool True = keep Elementor.
 */
function stc_front_bootstrap_keep_elementor() {
	if ( stc_front_bootstrap_is_privileged_request() ) {
		return true;
	}

	// Elementor editor / preview must keep both plugins.
	if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor_library'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return true;
	}

	$path = stc_front_bootstrap_request_path();

	// Streamit-style singles / players — theme templates, not Elementor documents.
	$streamit_prefixes = array(
		'/movie/',
		'/tv-show/',
		'/tvshow/',
		'/episode/',
		'/season/',
		'/video/',
		'/person/',
		'/cast/',
		'/genre/',
		'/watch/',
	);

	foreach ( $streamit_prefixes as $prefix ) {
		$needle = rtrim( $prefix, '/' );
		if ( $needle === $path || 0 === strpos( $path . '/', $prefix ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Drop front-unnecessary plugins before they are included.
 *
 * @param string[] $plugins Active plugin basenames.
 * @return string[]
 */
function stc_front_bootstrap_filter_active_plugins( $plugins ) {
	if ( ! is_array( $plugins ) || stc_front_bootstrap_is_privileged_request() ) {
		return $plugins;
	}

	$skip = array(
		// Admin / tooling — not needed for public HTML.
		'vibe-ai/vibe-ai.php',
		'duplicate-page/duplicatepage.php',
		'loco-translate/loco.php',
	);

	if ( ! stc_front_bootstrap_keep_elementor() ) {
		$skip[] = 'elementor/elementor.php';
		$skip[] = 'elementor-pro/elementor-pro.php';
	}

	return array_values(
		array_filter(
			$plugins,
			static function ( $plugin ) use ( $skip ) {
				return ! in_array( $plugin, $skip, true );
			}
		)
	);
}

add_filter( 'option_active_plugins', 'stc_front_bootstrap_filter_active_plugins', 1 );

/**
 * Do not spawn Action Scheduler async loopback on public HTML views.
 * Cron / admin / AJAX still process the queue.
 *
 * @param bool $allow Whether async runner may dispatch.
 * @return bool
 */
function stc_front_bootstrap_disable_as_async( $allow ) {
	if ( stc_front_bootstrap_is_privileged_request() ) {
		return $allow;
	}
	return false;
}

add_filter( 'action_scheduler_allow_async_request_runner', 'stc_front_bootstrap_disable_as_async', 10 );

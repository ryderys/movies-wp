<?php
/**
 * Plugin Name: Movies Security
 * Description: Production hardening controlled by environment variables (WP_ENV, DISABLE_XMLRPC).
 *
 * @package Movies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read a boolean environment variable.
 *
 * @param string $key     Environment variable name.
 * @param bool   $default Default when unset.
 */
function movies_security_env_bool( $key, $default = false ) {
	$value = getenv( $key );
	if ( false === $value || '' === $value ) {
		return $default;
	}
	return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}

$wp_env         = getenv( 'WP_ENV' ) ?: 'development';
$is_production  = ( 'production' === $wp_env );
$disable_xmlrpc = movies_security_env_bool( 'DISABLE_XMLRPC', $is_production );

if ( $disable_xmlrpc ) {
	add_filter( 'xmlrpc_enabled', '__return_false' );

	add_filter(
		'wp_headers',
		static function ( $headers ) {
			unset( $headers['X-Pingback'] );
			return $headers;
		}
	);
}

if ( $is_production ) {
	// Hide WordPress version in HTML and RSS.
	remove_action( 'wp_head', 'wp_generator' );
	add_filter( 'the_generator', '__return_empty_string' );

	// Block author enumeration via ?author=N.
	add_action(
		'template_redirect',
		static function () {
			if ( ! is_admin() && isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( home_url( '/' ), 301 );
				exit;
			}
		}
	);

	// Security headers (Caddy also sets these; harmless if duplicated).
	add_action(
		'send_headers',
		static function () {
			if ( headers_sent() ) {
				return;
			}
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );
			header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
		}
	);

	// Disable REST API user listing for anonymous visitors.
	add_filter(
		'rest_endpoints',
		static function ( $endpoints ) {
			if ( is_user_logged_in() ) {
				return $endpoints;
			}
			if ( isset( $endpoints['/wp/v2/users'] ) ) {
				unset( $endpoints['/wp/v2/users'] );
			}
			if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
				unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
			}
			return $endpoints;
		}
	);
}

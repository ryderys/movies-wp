<?php
/**
 * WordPress configuration for Docker (no secrets committed).
 *
 * Synced to wp-config.php on every container start by docker-entrypoint.sh.
 * Salts are stored in wp-content/.docker-salts.php (generated on first run).
 * Override any value via environment variables.
 *
 * @package WordPress
 */

/** Database settings */
define( 'DB_NAME', getenv( 'DB_NAME' ) ?: 'local_wp' );
define( 'DB_USER', getenv( 'DB_USER' ) ?: 'wp_user' );
define( 'DB_PASSWORD', getenv( 'DB_PASSWORD' ) ?: 'wp_pass' );
define( 'DB_HOST', getenv( 'DB_HOST' ) ?: 'db' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

/** Authentication unique keys and salts */
$salts_file = __DIR__ . '/wp-content/.docker-salts.php';
if ( is_readable( $salts_file ) ) {
	require $salts_file;
} else {
	$salt_env_keys = array(
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	);
	foreach ( $salt_env_keys as $salt_key ) {
		if ( defined( $salt_key ) ) {
			continue;
		}
		$salt_value = getenv( $salt_key );
		if ( $salt_value === false || $salt_value === '' ) {
			die(
				'Docker: missing WordPress salts. ' .
				'Restart the container to generate wp-content/.docker-salts.php, ' .
				'or set AUTH_KEY (and related) environment variables.'
			);
		}
		define( $salt_key, $salt_value );
	}
}

$table_prefix = getenv( 'TABLE_PREFIX' ) ?: 'wp_';

/** Debugging */
$wp_debug         = getenv( 'WP_DEBUG' );
$wp_debug_log     = getenv( 'WP_DEBUG_LOG' );
$wp_debug_display = getenv( 'WP_DEBUG_DISPLAY' );

define( 'WP_DEBUG', $wp_debug !== false ? filter_var( $wp_debug, FILTER_VALIDATE_BOOLEAN ) : false );
define( 'WP_DEBUG_LOG', $wp_debug_log !== false ? filter_var( $wp_debug_log, FILTER_VALIDATE_BOOLEAN ) : false );
define( 'WP_DEBUG_DISPLAY', $wp_debug_display !== false ? filter_var( $wp_debug_display, FILTER_VALIDATE_BOOLEAN ) : false );

/* Add any custom values between this line and the "stop editing" line. */

define( 'WP_CACHE', true ); // WP-Optimize Cache

define( 'WP_REDIS_HOST', getenv( 'REDIS_HOST' ) ?: 'redis' );
define( 'WP_REDIS_PORT', (int) ( getenv( 'REDIS_PORT' ) ?: 6379 ) );
define( 'WP_REDIS_DATABASE', (int) ( getenv( 'REDIS_DATABASE' ) ?: 0 ) );
define( 'WP_REDIS_PREFIX', getenv( 'REDIS_PREFIX' ) ?: 'movies_' );
define( 'WP_REDIS_TIMEOUT', 1 );
define( 'WP_REDIS_READ_TIMEOUT', 1 );
define( 'WP_REDIS_MAXTTL', 86400 );

$redis_password = getenv( 'REDIS_PASSWORD' );
if ( $redis_password !== false && $redis_password !== '' ) {
	define( 'WP_REDIS_PASSWORD', $redis_password );
}

$wp_home = getenv( 'WP_HOME' );
if ( $wp_home ) {
	define( 'WP_HOME', $wp_home );
}

$wp_siteurl = getenv( 'WP_SITEURL' );
if ( $wp_siteurl ) {
	define( 'WP_SITEURL', $wp_siteurl );
}

/* That's all, stop editing! Happy publishing. */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Trust HTTPS when behind Nginx/reverse proxy (Docker prod).
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
	$_SERVER['HTTPS'] = 'on';
}

require_once ABSPATH . 'wp-settings.php';

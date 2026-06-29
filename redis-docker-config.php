<?php
/**
 * Redis Object Cache settings for Docker (loaded from wp-config.php).
 *
 * @package WordPress
 */

if ( defined( 'WP_REDIS_HOST' ) ) {
	return;
}

if ( ! function_exists( 'movies_redis_env' ) ) {
	/**
	 * Read container env in Apache/mod_php where getenv() can be empty.
	 *
	 * @param string $key     Environment variable name.
	 * @param string $default Default when unset.
	 * @return string
	 */
	function movies_redis_env( $key, $default = '' ) {
		$value = getenv( $key );
		if ( $value === false || $value === '' ) {
			$value = $_SERVER[ $key ] ?? $_ENV[ $key ] ?? false;
		}
		if ( $value === false || $value === '' ) {
			return $default;
		}
		return $value;
	}
}

define( 'WP_REDIS_HOST', movies_redis_env( 'REDIS_HOST', 'redis' ) );
define( 'WP_REDIS_PORT', (int) movies_redis_env( 'REDIS_PORT', '6379' ) );
define( 'WP_REDIS_DATABASE', (int) movies_redis_env( 'REDIS_DATABASE', '0' ) );
define( 'WP_REDIS_PREFIX', movies_redis_env( 'REDIS_PREFIX', 'movies_' ) );
define( 'WP_REDIS_SELECTIVE_FLUSH', true );
define( 'WP_REDIS_TIMEOUT', 1 );
define( 'WP_REDIS_READ_TIMEOUT', 1 );
define( 'WP_REDIS_MAXTTL', 86400 );

$redis_password = movies_redis_env( 'REDIS_PASSWORD' );
if ( $redis_password !== '' ) {
	define( 'WP_REDIS_PASSWORD', $redis_password );
}

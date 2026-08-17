<?php
/**
 * WordPress HTTP client for GET /scan/series.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Media_Api_Client {

	const REQUEST_PATH = '/scan/series';
	const DEFAULT_BASE = 'https://media.asiastarx.ir';
	const TIMEOUT      = 20;

	/**
	 * @param string $relative_directory e.g. Series/korea/2024/Marry.My.Husband.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function scan_series_directory( $relative_directory ) {
		$config = self::config();
		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$dir = self::normalize_directory( $relative_directory );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$timestamp = (string) time();
		$encoded   = rawurlencode( $dir );
		$canonical = $timestamp . "\nGET\n" . self::REQUEST_PATH . "\ndir=" . $encoded;
		$signature = hash_hmac( 'sha256', $canonical, $config['hmac_secret'] );
		$url       = $config['base_url'] . self::REQUEST_PATH . '?dir=' . $encoded;

		$started  = microtime( true );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => array(
					'Accept'            => 'application/json',
					'X-Media-Key'       => $config['api_key'],
					'X-Media-Timestamp' => $timestamp,
					'X-Media-Signature' => $signature,
				),
			)
		);
		$duration_ms = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $response ) ) {
			self::debug_log( $dir, 0, $duration_ms );
			return self::transport_error( $response );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );
		self::debug_log( $dir, $status, $duration_ms );

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'series_media_api_invalid_json',
				__( 'The series scan response was not valid JSON.', 'movies-wp' ),
				array( 'status' => $status )
			);
		}

		if ( 401 === $status || 403 === $status ) {
			return self::server_error(
				'series_media_api_authentication_failed',
				__( 'Series scan authentication failed.', 'movies-wp' ),
				$status,
				$decoded
			);
		}

		if ( empty( $decoded['ok'] ) ) {
			$code = $status >= 500 ? 'series_media_api_http_error' : 'series_media_api_scan_error';
			return self::server_error(
				$code,
				__( 'Series scan failed.', 'movies-wp' ),
				$status,
				$decoded
			);
		}

		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error(
				'series_media_api_http_error',
				__( 'The series scan request returned an unexpected HTTP status.', 'movies-wp' ),
				array( 'status' => $status )
			);
		}

		if ( ( $decoded['kind'] ?? '' ) !== 'series' || ! isset( $decoded['files'] ) || ! is_array( $decoded['files'] ) ) {
			return new WP_Error(
				'series_media_api_invalid_response',
				__( 'The series scan response was missing required fields.', 'movies-wp' ),
				array( 'status' => $status )
			);
		}

		return $decoded;
	}

	/**
	 * @return array{base_url: string, api_key: string, hmac_secret: string}|WP_Error
	 */
	public static function config() {
		if ( class_exists( 'Movies_WP_Media_Api_Client' ) ) {
			$config = Movies_WP_Media_Api_Client::config();
			if ( is_wp_error( $config ) ) {
				return $config;
			}
			return $config;
		}

		$key    = self::setting( 'MEDIA_SCAN_API_KEY' );
		$secret = self::setting( 'MEDIA_SCAN_HMAC_SECRET' );
		$base   = self::setting( 'MEDIA_SCAN_API_URL' );
		if ( '' === $base ) {
			$base = self::DEFAULT_BASE;
		}
		if ( '' === $key || '' === $secret ) {
			return new WP_Error(
				'series_media_api_config_error',
				__( 'Media scan API is not configured.', 'movies-wp' ),
				array( 'status' => 0 )
			);
		}
		return array(
			'base_url'    => untrailingslashit( $base ),
			'api_key'     => $key,
			'hmac_secret' => $secret,
		);
	}

	/**
	 * @param mixed $directory
	 * @return string|WP_Error
	 */
	public static function normalize_directory( $directory ) {
		if ( ! is_string( $directory ) ) {
			return new WP_Error( 'series_media_api_invalid_dir', __( 'The directory must be a relative series path.', 'movies-wp' ) );
		}
		if ( '' === trim( $directory ) || str_contains( $directory, "\0" ) ) {
			return new WP_Error( 'series_media_api_invalid_dir', __( 'The directory must be a relative series path.', 'movies-wp' ) );
		}

		$normalized = str_replace( '\\', '/', trim( $directory ) );
		if ( str_starts_with( $normalized, '/' ) || preg_match( '#^[A-Za-z]:/#', $normalized ) || str_starts_with( $normalized, '//' ) ) {
			return new WP_Error( 'series_media_api_invalid_dir', __( 'The directory must be a relative series path.', 'movies-wp' ) );
		}

		$normalized = trim( $normalized, '/' );
		$segments   = explode( '/', $normalized );
		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return new WP_Error( 'series_media_api_invalid_dir', __( 'The directory must be a relative series path.', 'movies-wp' ) );
			}
		}
		if ( 'Series' !== ( $segments[0] ?? '' ) || count( $segments ) < 4 ) {
			return new WP_Error( 'series_media_api_invalid_dir', __( 'The directory must be a relative series path.', 'movies-wp' ) );
		}

		return $normalized;
	}

	private static function setting( $key ) {
		if ( defined( $key ) ) {
			$value = constant( $key );
			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}
		$from_env = getenv( $key );
		return is_string( $from_env ) && '' !== $from_env ? $from_env : '';
	}

	/**
	 * @param WP_Error $error
	 * @return WP_Error
	 */
	private static function transport_error( $error ) {
		$message = $error->get_error_message();
		$is_timeout = false !== stripos( (string) $message, 'timeout' );
		return new WP_Error(
			$is_timeout ? 'series_media_api_timeout' : 'series_media_api_request_error',
			$is_timeout
				? __( 'The series scan request timed out.', 'movies-wp' )
				: __( 'The series scan request failed.', 'movies-wp' ),
			array( 'status' => 0 )
		);
	}

	/**
	 * @param array<string, mixed> $decoded
	 * @return WP_Error
	 */
	private static function server_error( $code, $message, $status, array $decoded ) {
		$data = array( 'status' => (int) $status );
		if ( isset( $decoded['code'] ) && is_string( $decoded['code'] ) && '' !== $decoded['code'] ) {
			$data['server_code'] = $decoded['code'];
		}
		return new WP_Error( $code, $message, $data );
	}

	private static function debug_log( $dir, $status, $duration_ms ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}
		error_log(
			sprintf(
				'movies-wp series-media-scan dir=%s status=%s duration_ms=%d',
				str_replace( array( "\n", "\r" ), '', (string) $dir ),
				(string) $status,
				(int) $duration_ms
			)
		);
	}
}

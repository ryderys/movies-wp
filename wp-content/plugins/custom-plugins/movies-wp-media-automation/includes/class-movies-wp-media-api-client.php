<?php
/**
 * WordPress HTTP client for GET /scan/movie.
 *
 * Server-to-server only. Does not expose credentials to the browser,
 * REST, or admin UI.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Media_Api_Client {

	const REQUEST_PATH = '/scan/movie';
	const DEFAULT_BASE = 'https://media.asiastarx.ir';
	const TIMEOUT      = 20;

	/**
	 * Scan a relative movie directory on the media server.
	 *
	 * @param string $relative_directory e.g. Movie/Korea/2018/Vapor.
	 * @return array<string, mixed>|WP_Error Scan payload or error.
	 */
	public static function scan_movie_directory( $relative_directory ) {
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
					'Accept'             => 'application/json',
					'X-Media-Key'        => $config['api_key'],
					'X-Media-Timestamp'  => $timestamp,
					'X-Media-Signature'  => $signature,
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
				'media_api_invalid_json',
				__( 'The media scan response was not valid JSON.', 'movies-wp' ),
				array( 'status' => $status )
			);
		}

		if ( 401 === $status || 403 === $status ) {
			return self::server_error(
				'media_api_authentication_failed',
				__( 'Media scan authentication failed.', 'movies-wp' ),
				$status,
				$decoded
			);
		}

		if ( empty( $decoded['ok'] ) ) {
			$code = 'media_api_scan_error';
			if ( $status >= 500 ) {
				$code = 'media_api_http_error';
			} elseif ( $status < 400 ) {
				$code = 'media_api_invalid_response';
			}
			return self::server_error(
				$code,
				__( 'Media scan failed.', 'movies-wp' ),
				$status,
				$decoded
			);
		}

		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error(
				'media_api_http_error',
				__( 'The media scan request returned an unexpected HTTP status.', 'movies-wp' ),
				array( 'status' => $status )
			);
		}

		if ( ( $decoded['kind'] ?? '' ) !== 'movie' || ! isset( $decoded['files'] ) || ! is_array( $decoded['files'] ) ) {
			return new WP_Error(
				'media_api_invalid_response',
				__( 'The media scan response was missing required fields.', 'movies-wp' ),
				array( 'status' => $status )
			);
		}

		return $decoded;
	}

	/**
	 * @return array{base_url: string, api_key: string, hmac_secret: string}|WP_Error
	 */
	public static function config() {
		$base = self::setting( 'MEDIA_SCAN_API_URL' );
		if ( '' === $base && function_exists( 'movies_wp_media_base_url' ) ) {
			$base = movies_wp_media_base_url();
		}
		if ( '' === $base ) {
			$base = self::DEFAULT_BASE;
		}

		$key    = self::setting( 'MEDIA_SCAN_API_KEY' );
		$secret = self::setting( 'MEDIA_SCAN_HMAC_SECRET' );

		if ( '' === $key || '' === $secret ) {
			return new WP_Error(
				'media_api_config_error',
				__( 'Media scan API is not configured.', 'movies-wp' ),
				array( 'status' => 0 )
			);
		}

		return array(
			'base_url'     => untrailingslashit( $base ),
			'api_key'      => $key,
			'hmac_secret'  => $secret,
		);
	}

	/**
	 * Lightweight syntax checks only. The media-server resolver is authoritative.
	 *
	 * @param mixed $directory Raw input.
	 * @return string|WP_Error
	 */
	public static function normalize_directory( $directory ) {
		if ( ! is_string( $directory ) ) {
			return new WP_Error( 'media_api_invalid_dir', __( 'The directory must be a relative movie path.', 'movies-wp' ) );
		}

		if ( '' === $directory || '' === trim( $directory ) ) {
			return new WP_Error( 'media_api_invalid_dir', __( 'The directory must be a relative movie path.', 'movies-wp' ) );
		}

		if ( str_contains( $directory, "\0" ) ) {
			return new WP_Error( 'media_api_invalid_dir', __( 'The directory must be a relative movie path.', 'movies-wp' ) );
		}

		$normalized = str_replace( '\\', '/', trim( $directory ) );

		if ( str_starts_with( $normalized, '/' ) || preg_match( '#^[A-Za-z]:/#', $normalized ) || str_starts_with( $normalized, '//' ) ) {
			return new WP_Error( 'media_api_invalid_dir', __( 'The directory must be a relative movie path.', 'movies-wp' ) );
		}

		$normalized = trim( $normalized, '/' );
		if ( '' === $normalized ) {
			return new WP_Error( 'media_api_invalid_dir', __( 'The directory must be a relative movie path.', 'movies-wp' ) );
		}

		$segments = explode( '/', $normalized );
		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return new WP_Error( 'media_api_invalid_dir', __( 'The directory must be a relative movie path.', 'movies-wp' ) );
			}
		}

		if ( 'Movie' !== $segments[0] || count( $segments ) < 2 ) {
			return new WP_Error( 'media_api_invalid_dir', __( 'The directory must be a relative movie path.', 'movies-wp' ) );
		}

		return $normalized;
	}

	/**
	 * Constant, then environment. Never logs the value.
	 */
	private static function setting( $key ) {
		if ( defined( $key ) ) {
			$value = constant( $key );
			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}

		$from_env = getenv( $key );
		if ( is_string( $from_env ) && '' !== $from_env ) {
			return $from_env;
		}

		return '';
	}

	/**
	 * @param WP_Error $error Transport error.
	 * @return WP_Error
	 */
	private static function transport_error( $error ) {
		$message = $error->get_error_message();
		$code    = $error->get_error_code();

		$is_timeout = ( false !== stripos( (string) $message, 'timed out' ) )
			|| ( false !== stripos( (string) $message, 'timeout' ) )
			|| 'http_request_failed' === $code && false !== stripos( (string) $message, 'cURL error 28' );

		if ( $is_timeout ) {
			return new WP_Error(
				'media_api_timeout',
				__( 'The media scan request timed out.', 'movies-wp' ),
				array( 'status' => 0 )
			);
		}

		return new WP_Error(
			'media_api_request_error',
			__( 'The media scan request failed.', 'movies-wp' ),
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
		if ( isset( $decoded['message'] ) && is_string( $decoded['message'] ) && '' !== $decoded['message'] ) {
			$message .= ' ' . sprintf(
				/* translators: %s: technical error returned by the media server */
				__( 'Error details: %s', 'movies-wp' ),
				$decoded['message']
			);
		}

		return new WP_Error( $code, $message, $data );
	}

	private static function debug_log( $dir, $status, $duration_ms ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$dir = str_replace( array( "\n", "\r" ), '', (string) $dir );
		error_log(
			sprintf(
				'movies-wp media-scan dir=%s status=%s duration_ms=%d',
				$dir,
				(string) $status,
				(int) $duration_ms
			)
		);
	}
}

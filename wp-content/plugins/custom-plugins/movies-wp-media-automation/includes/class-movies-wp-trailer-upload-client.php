<?php
/**
 * WordPress HTTP client for POST /upload/trailer.
 *
 * Uses the POST-specific HMAC contract (not GET /scan/movie).
 * Hashes and streams the file from disk — never loads the full video into a PHP string.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Trailer_Upload_Client {

	const REQUEST_PATH     = '/upload/trailer';
	const DEFAULT_TIMEOUT  = 300;
	const DEFAULT_MAX_BYTES = 134217728; // 128 MiB — align with media-server default.
	const ALLOWED_EXTENSIONS = array( 'mp4', 'webm', 'mkv' );

	/**
	 * Build the POST HMAC canonical string (must match media-server).
	 *
	 * @param string $timestamp Unix timestamp string.
	 * @param string $dir       Relative title directory.
	 * @param string $filename  Bare trailer filename.
	 * @param string $sha256    Lowercase hex SHA-256 of file bytes.
	 * @return string
	 */
	public static function canonical_request( $timestamp, $dir, $filename, $sha256 ) {
		$sha256 = strtolower( trim( (string) $sha256 ) );
		$parts  = array(
			'dir=' . rawurlencode( (string) $dir ),
			'filename=' . rawurlencode( (string) $filename ),
			'sha256=' . rawurlencode( $sha256 ),
		);
		sort( $parts, SORT_STRING );

		return (string) $timestamp . "\nPOST\n" . self::REQUEST_PATH . "\n" . implode( '&', $parts );
	}

	/**
	 * @param string $canonical
	 * @param string $secret
	 * @return string Hex HMAC-SHA256.
	 */
	public static function signature( $canonical, $secret ) {
		return hash_hmac( 'sha256', (string) $canonical, (string) $secret );
	}

	/**
	 * Configurable max upload size (bytes).
	 *
	 * @return int
	 */
	public static function max_bytes() {
		$raw = '';
		if ( defined( 'MEDIA_TRAILER_UPLOAD_MAX_BYTES' ) ) {
			$defined = constant( 'MEDIA_TRAILER_UPLOAD_MAX_BYTES' );
			if ( is_numeric( $defined ) ) {
				$raw = (string) $defined;
			}
		}
		if ( '' === $raw ) {
			$env = getenv( 'MEDIA_TRAILER_UPLOAD_MAX_BYTES' );
			if ( is_string( $env ) && '' !== $env ) {
				$raw = $env;
			}
		}
		$max = (int) $raw;
		return $max > 0 ? $max : self::DEFAULT_MAX_BYTES;
	}

	/**
	 * Upload timeout in seconds.
	 *
	 * @return int
	 */
	public static function timeout_seconds() {
		$raw = '';
		if ( defined( 'MEDIA_TRAILER_UPLOAD_TIMEOUT' ) ) {
			$defined = constant( 'MEDIA_TRAILER_UPLOAD_TIMEOUT' );
			if ( is_numeric( $defined ) ) {
				$raw = (string) $defined;
			}
		}
		if ( '' === $raw ) {
			$env = getenv( 'MEDIA_TRAILER_UPLOAD_TIMEOUT' );
			if ( is_string( $env ) && '' !== $env ) {
				$raw = $env;
			}
		}
		$timeout = (int) $raw;
		return $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
	}

	/**
	 * Validate a safe trailer basename (client-side; server is authoritative).
	 *
	 * @param string $filename
	 * @return string|WP_Error Canonical filename with lowercase extension.
	 */
	public static function normalize_filename( $filename ) {
		$filename = trim( (string) $filename );
		if ( '' === $filename || str_contains( $filename, "\0" ) ) {
			return new WP_Error( 'trailer_upload_invalid_filename', __( 'Invalid trailer filename.', 'movies-wp' ) );
		}
		if ( str_contains( $filename, '/' ) || str_contains( $filename, '\\' ) ) {
			return new WP_Error( 'trailer_upload_invalid_filename', __( 'Invalid trailer filename.', 'movies-wp' ) );
		}
		if ( $filename !== basename( $filename ) ) {
			return new WP_Error( 'trailer_upload_invalid_filename', __( 'Invalid trailer filename.', 'movies-wp' ) );
		}
		if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,180}\.(mp4|webm|mkv)$/i', $filename ) ) {
			return new WP_Error( 'trailer_upload_invalid_filename', __( 'Trailer must be an MP4, WebM, or MKV file with a safe name.', 'movies-wp' ) );
		}

		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::ALLOWED_EXTENSIONS, true ) ) {
			return new WP_Error( 'trailer_upload_invalid_type', __( 'Trailer type is not allowed.', 'movies-wp' ) );
		}

		$base = substr( $filename, 0, -strlen( $ext ) );
		return $base . $ext;
	}

	/**
	 * Upload a local file to POST /upload/trailer.
	 *
	 * Streams from disk via cURL + CURLFile. SHA-256 is computed with hash_file().
	 *
	 * @param string $relative_directory Movie `_media_directory` (relative).
	 * @param string $filename           Safe trailer basename.
	 * @param string $absolute_path      Readable local file path.
	 * @param array{
	 *   transport?: callable(array): array{status:int,body:string}|WP_Error,
	 *   curl_exec?: callable(resource|CurlHandle): string|false,
	 *   hash_file?: callable(string,string): string|false
	 * } $options Test hooks. Prefer `transport` in unit tests (avoids curl_getinfo).
	 * @return array{ok:true,path:string}|WP_Error
	 */
	public static function upload_trailer( $relative_directory, $filename, $absolute_path, array $options = array() ) {
		$config = Movies_WP_Media_Api_Client::config();
		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$dir = Movies_WP_Media_Api_Client::normalize_directory( $relative_directory );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$name = self::normalize_filename( $filename );
		if ( is_wp_error( $name ) ) {
			return $name;
		}

		$path = (string) $absolute_path;
		if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'trailer_upload_missing_file', __( 'The trailer file could not be read.', 'movies-wp' ) );
		}

		$size = filesize( $path );
		if ( false === $size || $size < 1 ) {
			return new WP_Error( 'trailer_upload_empty_file', __( 'The trailer file is empty.', 'movies-wp' ) );
		}
		$max = self::max_bytes();
		if ( $size > $max ) {
			return new WP_Error(
				'trailer_upload_too_large',
				__( 'The trailer file exceeds the maximum allowed size.', 'movies-wp' ),
				array( 'status' => 413 )
			);
		}

		$hash_fn = isset( $options['hash_file'] ) && is_callable( $options['hash_file'] )
			? $options['hash_file']
			: 'hash_file';
		$sha256  = call_user_func( $hash_fn, 'sha256', $path );
		if ( ! is_string( $sha256 ) || ! preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
			return new WP_Error( 'trailer_upload_checksum_failed', __( 'Could not checksum the trailer file.', 'movies-wp' ) );
		}

		$timestamp = (string) time();
		$canonical = self::canonical_request( $timestamp, $dir, $name, $sha256 );
		$signature = self::signature( $canonical, $config['hmac_secret'] );
		$url       = $config['base_url'] . self::REQUEST_PATH;

		$started = microtime( true );

		if ( isset( $options['transport'] ) && is_callable( $options['transport'] ) ) {
			$transport = call_user_func(
				$options['transport'],
				array(
					'url'       => $url,
					'dir'       => $dir,
					'filename'  => $name,
					'sha256'    => $sha256,
					'timestamp' => $timestamp,
					'signature' => $signature,
					'path'      => $path,
					'canonical' => $canonical,
				)
			);
			if ( is_wp_error( $transport ) ) {
				return $transport;
			}
			if ( ! is_array( $transport ) ) {
				return new WP_Error( 'trailer_upload_request_error', __( 'The trailer upload request failed.', 'movies-wp' ) );
			}
			$status = isset( $transport['status'] ) ? (int) $transport['status'] : 0;
			$body   = isset( $transport['body'] ) ? (string) $transport['body'] : '';
		} else {
			if ( ! function_exists( 'curl_init' ) || ! class_exists( 'CURLFile', false ) ) {
				return new WP_Error(
					'trailer_upload_transport_unavailable',
					__( 'Streaming trailer upload requires PHP cURL with CURLFile support.', 'movies-wp' )
				);
			}

			$mime = self::mime_for_extension( pathinfo( $name, PATHINFO_EXTENSION ) );
			$file = new CURLFile( $path, $mime, $name );

			$headers = array(
				'Accept: application/json',
				'X-Media-Key: ' . $config['api_key'],
				'X-Media-Timestamp: ' . $timestamp,
				'X-Media-Signature: ' . $signature,
				'X-Media-Content-Sha256: ' . $sha256,
			);

			$ch = curl_init( $url );
			if ( false === $ch ) {
				return new WP_Error( 'trailer_upload_request_error', __( 'The trailer upload request failed.', 'movies-wp' ) );
			}

			$timeout = self::timeout_seconds();
			curl_setopt_array(
				$ch,
				array(
					CURLOPT_POST           => true,
					CURLOPT_POSTFIELDS     => array(
						'dir'      => $dir,
						'filename' => $name,
						'file'     => $file,
					),
					CURLOPT_HTTPHEADER     => $headers,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_HEADER         => false,
					CURLOPT_FOLLOWLOCATION => false,
					CURLOPT_TIMEOUT        => $timeout,
					CURLOPT_CONNECTTIMEOUT => min( 30, $timeout ),
					CURLOPT_SSL_VERIFYPEER => true,
					CURLOPT_SSL_VERIFYHOST => 2,
				)
			);

			$exec   = isset( $options['curl_exec'] ) && is_callable( $options['curl_exec'] )
				? $options['curl_exec']
				: 'curl_exec';
			$body   = call_user_func( $exec, $ch );
			$errno  = curl_errno( $ch );
			$errstr = curl_error( $ch );
			$status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
			curl_close( $ch );

			if ( false === $body || 0 !== $errno ) {
				if ( CURLE_OPERATION_TIMEDOUT === $errno || false !== stripos( $errstr, 'timed out' ) ) {
					return new WP_Error(
						'trailer_upload_timeout',
						__( 'The trailer upload request timed out.', 'movies-wp' ),
						array( 'status' => 0 )
					);
				}
				return new WP_Error(
					'trailer_upload_request_error',
					__( 'The trailer upload request failed.', 'movies-wp' ),
					array( 'status' => 0 )
				);
			}
		}

		$duration_ms = (int) round( ( microtime( true ) - $started ) * 1000 );
		self::debug_log( $dir, $status, $duration_ms );

		$decoded = json_decode( (string) $body, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'trailer_upload_invalid_json',
				__( 'The trailer upload response was not valid JSON.', 'movies-wp' ),
				array( 'status' => $status )
			);
		}

		if ( 401 === $status || 403 === $status ) {
			return self::server_error(
				'trailer_upload_authentication_failed',
				__( 'Trailer upload authentication failed.', 'movies-wp' ),
				$status,
				$decoded
			);
		}

		if ( empty( $decoded['ok'] ) ) {
			return self::server_error(
				$status >= 500 ? 'trailer_upload_http_error' : 'trailer_upload_rejected',
				__( 'Trailer upload failed.', 'movies-wp' ),
				$status,
				$decoded
			);
		}

		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error(
				'trailer_upload_http_error',
				__( 'The trailer upload returned an unexpected HTTP status.', 'movies-wp' ),
				array( 'status' => $status )
			);
		}

		$path_rel = isset( $decoded['path'] ) && is_string( $decoded['path'] ) ? trim( $decoded['path'] ) : '';
		$validated = self::validate_returned_path( $dir, $path_rel );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		return array(
			'ok'   => true,
			'path' => $validated,
		);
	}

	/**
	 * Ensure the server path is under {dir}/Trailer/ and uses a safe basename.
	 *
	 * @param string $dir  Normalized movie directory.
	 * @param string $path Relative path from the media server.
	 * @return string|WP_Error
	 */
	public static function validate_returned_path( $dir, $path ) {
		$path = str_replace( '\\', '/', trim( (string) $path ) );
		$path = trim( $path, '/' );
		$dir  = str_replace( '\\', '/', trim( (string) $dir, '/' ) );

		if ( '' === $path || str_contains( $path, "\0" ) ) {
			return new WP_Error( 'trailer_upload_invalid_path', __( 'The media server returned an invalid trailer path.', 'movies-wp' ) );
		}

		$prefix = $dir . '/Trailer/';
		if ( ! str_starts_with( $path, $prefix ) ) {
			return new WP_Error( 'trailer_upload_invalid_path', __( 'The media server returned an invalid trailer path.', 'movies-wp' ) );
		}

		$rest = substr( $path, strlen( $prefix ) );
		$name = self::normalize_filename( $rest );
		if ( is_wp_error( $name ) ) {
			return new WP_Error( 'trailer_upload_invalid_path', __( 'The media server returned an invalid trailer path.', 'movies-wp' ) );
		}

		return $dir . '/Trailer/' . $name;
	}

	/**
	 * @param string $ext
	 * @return string
	 */
	private static function mime_for_extension( $ext ) {
		$ext = strtolower( (string) $ext );
		$map = array(
			'mp4'  => 'video/mp4',
			'webm' => 'video/webm',
			'mkv'  => 'video/x-matroska',
		);
		return $map[ $ext ] ?? 'application/octet-stream';
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
		// Prefer short safe server message; never append secrets or absolute paths.
		if ( isset( $decoded['message'] ) && is_string( $decoded['message'] ) && '' !== $decoded['message'] ) {
			$safe = $decoded['message'];
			if ( ! str_contains( $safe, '/' ) && ! str_contains( $safe, '\\' ) && strlen( $safe ) < 200 ) {
				$message .= ' ' . sprintf(
					/* translators: %s: short error from media server */
					__( 'Details: %s', 'movies-wp' ),
					$safe
				);
			}
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
				'movies-wp trailer-upload dir=%s status=%s duration_ms=%d',
				$dir,
				(string) $status,
				(int) $duration_ms
			)
		);
	}
}

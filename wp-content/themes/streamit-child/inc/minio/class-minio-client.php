<?php
/**
 * Minimal S3-compatible client for MinIO (AWS SigV4).
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

class Streamit_Child_Minio_Client {

	/**
	 * Retry count for transient network failures.
	 *
	 * Can be overridden via STREAMIT_MINIO_RETRY_MAX in wp-config.php.
	 *
	 * @return int
	 */
	private function retry_max() {
		$max = defined( 'STREAMIT_MINIO_RETRY_MAX' ) ? (int) STREAMIT_MINIO_RETRY_MAX : 3;
		return max( 1, min( 10, $max ) );
	}

	/**
	 * Sleep between retries using exponential backoff (with small jitter).
	 *
	 * @param int $attempt 1..N
	 * @return void
	 */
	private function retry_sleep( $attempt ) {
		$attempt = max( 1, (int) $attempt );
		$base_ms = defined( 'STREAMIT_MINIO_RETRY_BASE_MS' ) ? (int) STREAMIT_MINIO_RETRY_BASE_MS : 250;
		$base_ms = max( 50, min( 5000, $base_ms ) );

		$max_ms = $base_ms * ( 2 ** ( $attempt - 1 ) );
		$jitter = random_int( 0, (int) floor( $base_ms / 2 ) );
		$ms     = min( 10_000, $max_ms + $jitter );

		usleep( $ms * 1000 );
	}

	/**
	 * Whether an HTTP response code should be retried.
	 *
	 * @param int $code
	 * @return bool
	 */
	private function should_retry_http_code( $code ) {
		$code = (int) $code;
		if ( 408 === $code || 429 === $code ) {
			return true;
		}
		return $code >= 500 && $code <= 599;
	}

	/**
	 * Perform a remote request with small retries for transient failures.
	 *
	 * @param string               $url
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>|\WP_Error
	 */
	private function remote_request_retry( $url, array $args ) {
		$max      = $this->retry_max();
		$last_err = null;
		for ( $attempt = 1; $attempt <= $max; $attempt++ ) {
			$response = wp_remote_request( $url, $args );
			if ( is_wp_error( $response ) ) {
				$last_err = $response;
				if ( $attempt < $max ) {
					$this->retry_sleep( $attempt );
					continue;
				}
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $this->should_retry_http_code( $code ) && $attempt < $max ) {
				$this->retry_sleep( $attempt );
				continue;
			}

			return $response;
		}

		return $last_err instanceof WP_Error ? $last_err : new WP_Error( 'minio_request_failed', 'MinIO request failed.' );
	}

	/**
	 * Upload raw bytes to the configured bucket.
	 *
	 * @param string $key          Object key (e.g. smoke/wp-put.txt).
	 * @param string $body         File contents.
	 * @param string $content_type MIME type.
	 * @return true|\WP_Error
	 */
	public function put_object( $key, $body, $content_type = 'application/octet-stream' ) {
		$key = ltrim( (string) $key, '/' );
		if ( '' === $key ) {
			return new WP_Error( 'minio_empty_key', 'MinIO object key is empty.' );
		}

		$endpoint = untrailingslashit( STREAMIT_MINIO_ENDPOINT );
		$bucket   = STREAMIT_MINIO_BUCKET;
		$region   = defined( 'STREAMIT_MINIO_REGION' ) ? STREAMIT_MINIO_REGION : 'us-east-1';
		$access   = STREAMIT_MINIO_KEY;
		$secret   = STREAMIT_MINIO_SECRET;

		$url  = $endpoint . '/' . rawurlencode( $bucket ) . '/' . str_replace( '%2F', '/', rawurlencode( $key ) );
		$host = wp_parse_url( $endpoint, PHP_URL_HOST );
		if ( ! $host ) {
			return new WP_Error( 'minio_bad_endpoint', 'Invalid STREAMIT_MINIO_ENDPOINT.' );
		}

		$now       = time();
		$amz_date  = gmdate( 'Ymd\THis\Z', $now );
		$date_stamp = gmdate( 'Ymd', $now );
		$payload_hash = hash( 'sha256', $body );

		$canonical_uri = '/' . rawurlencode( $bucket ) . '/' . str_replace( '%2F', '/', rawurlencode( $key ) );

		$canonical_headers =
			'host:' . $host . "\n" .
			'x-amz-content-sha256:' . $payload_hash . "\n" .
			'x-amz-date:' . $amz_date . "\n";

		$signed_headers = 'host;x-amz-content-sha256;x-amz-date';

		$canonical_request = implode(
			"\n",
			array(
				'PUT',
				$canonical_uri,
				'', // no query string
				$canonical_headers,
				$signed_headers,
				$payload_hash,
			)
		);

		$credential_scope = $date_stamp . '/' . $region . '/s3/aws4_request';
		$string_to_sign   = implode(
			"\n",
			array(
				'AWS4-HMAC-SHA256',
				$amz_date,
				$credential_scope,
				hash( 'sha256', $canonical_request ),
			)
		);

		$signing_key = $this->signature_key( $secret, $date_stamp, $region, 's3' );
		$signature   = hash_hmac( 'sha256', $string_to_sign, $signing_key );

		$authorization = sprintf(
			'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
			$access,
			$credential_scope,
			$signed_headers,
			$signature
		);

		$response = $this->remote_request_retry(
			$url,
			array(
				'method'  => 'PUT',
				'timeout' => 60,
				'headers' => array(
					'Authorization'        => $authorization,
					'Content-Type'         => $content_type,
					'Host'                 => $host,
					'x-amz-content-sha256' => $payload_hash,
					'x-amz-date'           => $amz_date,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'minio_put_failed',
				sprintf(
					'MinIO PutObject failed HTTP %d: %s',
					$code,
					wp_remote_retrieve_body( $response )
				),
				array( 'status' => $code )
			);
		}

		return true;
	}

	/**
	 * Delete an object from the configured bucket.
	 *
	 * @param string $key Object key.
	 * @return true|\WP_Error
	 */
	public function delete_object( $key ) {
		$key = ltrim( (string) $key, '/' );
		if ( '' === $key ) {
			return new WP_Error( 'minio_empty_key', 'MinIO object key is empty.' );
		}

		$endpoint = untrailingslashit( STREAMIT_MINIO_ENDPOINT );
		$bucket   = STREAMIT_MINIO_BUCKET;
		$region   = defined( 'STREAMIT_MINIO_REGION' ) ? STREAMIT_MINIO_REGION : 'us-east-1';
		$access   = STREAMIT_MINIO_KEY;
		$secret   = STREAMIT_MINIO_SECRET;

		$url  = $endpoint . '/' . rawurlencode( $bucket ) . '/' . str_replace( '%2F', '/', rawurlencode( $key ) );
		$host = wp_parse_url( $endpoint, PHP_URL_HOST );
		if ( ! $host ) {
			return new WP_Error( 'minio_bad_endpoint', 'Invalid STREAMIT_MINIO_ENDPOINT.' );
		}

		$now          = time();
		$amz_date     = gmdate( 'Ymd\THis\Z', $now );
		$date_stamp   = gmdate( 'Ymd', $now );
		$payload_hash = hash( 'sha256', '' );

		$canonical_uri = '/' . rawurlencode( $bucket ) . '/' . str_replace( '%2F', '/', rawurlencode( $key ) );

		$canonical_headers =
			'host:' . $host . "\n" .
			'x-amz-content-sha256:' . $payload_hash . "\n" .
			'x-amz-date:' . $amz_date . "\n";

		$signed_headers = 'host;x-amz-content-sha256;x-amz-date';

		$canonical_request = implode(
			"\n",
			array(
				'DELETE',
				$canonical_uri,
				'',
				$canonical_headers,
				$signed_headers,
				$payload_hash,
			)
		);

		$credential_scope = $date_stamp . '/' . $region . '/s3/aws4_request';
		$string_to_sign   = implode(
			"\n",
			array(
				'AWS4-HMAC-SHA256',
				$amz_date,
				$credential_scope,
				hash( 'sha256', $canonical_request ),
			)
		);

		$signing_key = $this->signature_key( $secret, $date_stamp, $region, 's3' );
		$signature   = hash_hmac( 'sha256', $string_to_sign, $signing_key );

		$authorization = sprintf(
			'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
			$access,
			$credential_scope,
			$signed_headers,
			$signature
		);

		$response = $this->remote_request_retry(
			$url,
			array(
				'method'  => 'DELETE',
				'timeout' => 60,
				'headers' => array(
					'Authorization'        => $authorization,
					'Host'                 => $host,
					'x-amz-content-sha256' => $payload_hash,
					'x-amz-date'           => $amz_date,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		// 204 No Content and 200 OK are success; 404 = already gone.
		if ( ( $code < 200 || $code >= 300 ) && 404 !== $code ) {
			return new WP_Error(
				'minio_delete_failed',
				sprintf(
					'MinIO DeleteObject failed HTTP %d: %s',
					$code,
					wp_remote_retrieve_body( $response )
				),
				array( 'status' => $code )
			);
		}

		return true;
	}

	/**
	 * Public URL for an object key.
	 *
	 * @param string $key Object key.
	 * @return string
	 */
	public function public_url( $key ) {
		return trailingslashit( STREAMIT_MINIO_PUBLIC_BASE ) . ltrim( $key, '/' );
	}

	/**
	 * Derive SigV4 signing key.
	 *
	 * @param string $secret     Secret access key.
	 * @param string $date_stamp YYYYMMDD.
	 * @param string $region     Region.
	 * @param string $service    Service name (s3).
	 * @return string Binary key.
	 */
	private function signature_key( $secret, $date_stamp, $region, $service ) {
		$k_date    = hash_hmac( 'sha256', $date_stamp, 'AWS4' . $secret, true );
		$k_region  = hash_hmac( 'sha256', $region, $k_date, true );
		$k_service = hash_hmac( 'sha256', $service, $k_region, true );

		return hash_hmac( 'sha256', 'aws4_request', $k_service, true );
	}
}

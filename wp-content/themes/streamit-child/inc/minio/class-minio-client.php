<?php
/**
 * Minimal S3-compatible client for MinIO (AWS SigV4).
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

class Streamit_Child_Minio_Client {

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

		$response = wp_remote_request(
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

<?php
/**
 * MinIO object storage offload.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/minio/class-minio-client.php';
require_once __DIR__ . '/minio/class-minio-offload.php';

/**
 * True when MinIO constants are present in wp-config.php.
 *
 * @return bool
 */
function streamit_child_minio_enabled() {
	return defined( 'STREAMIT_MINIO_ENDPOINT' )
		&& defined( 'STREAMIT_MINIO_BUCKET' )
		&& defined( 'STREAMIT_MINIO_KEY' )
		&& defined( 'STREAMIT_MINIO_SECRET' )
		&& defined( 'STREAMIT_MINIO_PUBLIC_BASE' )
		&& STREAMIT_MINIO_ENDPOINT
		&& STREAMIT_MINIO_BUCKET
		&& STREAMIT_MINIO_KEY
		&& STREAMIT_MINIO_SECRET;
}

if ( streamit_child_minio_enabled() ) {
	Streamit_Child_Minio_Offload::init();
}

/**
 * Smoke-test PutObject from WP (dev/ops only).
 *
 * Usage inside container:
 *   php -r 'require "/var/www/html/wp-load.php"; var_export( streamit_child_minio_smoke_put() );'
 *
 * @return string|\WP_Error Public URL on success.
 */
function streamit_child_minio_smoke_put() {
	if ( ! streamit_child_minio_enabled() ) {
		return new WP_Error( 'minio_disabled', 'MinIO is not configured.' );
	}

	$client = new Streamit_Child_Minio_Client();
	$key    = 'smoke/wp-put-' . gmdate( 'Ymd-His' ) . '.txt';
	$body   = 'wp-put-ok ' . gmdate( 'c' );
	$result = $client->put_object( $key, $body, 'text/plain' );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return $client->public_url( $key );
}

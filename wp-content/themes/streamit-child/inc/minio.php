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

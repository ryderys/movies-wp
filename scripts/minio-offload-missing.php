<?php
/**
 * Re-offload attachments that failed MinIO offload previously.
 *
 * Usage (inside prod container):
 *   php scripts/minio-offload-missing.php --limit=200 --offset=0
 *   php scripts/minio-offload-missing.php --dry-run
 *
 * Notes:
 * - Only processes attachments missing `_streamit_minio_offloaded`.
 * - Only targets images by default.
 */
declare(strict_types=1);

if ( php_sapi_name() !== 'cli' ) {
	fwrite( STDERR, "This script must be run from CLI.\n" );
	exit( 1 );
}

$root = realpath( __DIR__ . '/..' );
if ( ! $root ) {
	fwrite( STDERR, "Could not resolve project root.\n" );
	exit( 1 );
}

require_once $root . '/wp-load.php';

// Ensure offload classes are available even if theme is not loaded by CLI.
@require_once $root . '/wp-content/themes/streamit-child/inc/minio.php';

if ( ! function_exists( 'streamit_child_minio_enabled' ) || ! class_exists( 'Streamit_Child_Minio_Offload' ) ) {
	fwrite( STDERR, "MinIO offload code is not available.\n" );
	exit( 1 );
}
if ( ! streamit_child_minio_enabled() ) {
	fwrite( STDERR, "MinIO is not enabled (missing STREAMIT_MINIO_* constants).\n" );
	exit( 1 );
}

// Parse args.
$limit   = 200;
$offset  = 0;
$dry_run = false;
$type    = 'image';

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( '--dry-run' === $arg ) {
		$dry_run = true;
		continue;
	}
	if ( 0 === strpos( $arg, '--limit=' ) ) {
		$limit = (int) substr( $arg, strlen( '--limit=' ) );
		continue;
	}
	if ( 0 === strpos( $arg, '--offset=' ) ) {
		$offset = (int) substr( $arg, strlen( '--offset=' ) );
		continue;
	}
	if ( 0 === strpos( $arg, '--type=' ) ) {
		$type = (string) substr( $arg, strlen( '--type=' ) );
		continue;
	}
}

$limit  = max( 1, min( 5000, (int) $limit ) );
$offset = max( 0, (int) $offset );
$type   = in_array( $type, array( 'image', 'all' ), true ) ? $type : 'image';

global $wpdb;
if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
	fwrite( STDERR, "wpdb not available.\n" );
	exit( 1 );
}

$meta_key = Streamit_Child_Minio_Offload::META_OFFLOADED;
$posts    = $wpdb->posts;
$postmeta = $wpdb->postmeta;

$mime_sql = '';
if ( 'image' === $type ) {
	$mime_sql = "AND p.post_mime_type LIKE 'image/%'";
}

// Find attachments without the offloaded marker (or marker is empty/0).
$sql = $wpdb->prepare(
	"SELECT p.ID
	FROM {$posts} p
	LEFT JOIN {$postmeta} m
		ON (m.post_id = p.ID AND m.meta_key = %s)
	WHERE p.post_type = 'attachment'
	{$mime_sql}
	AND (m.meta_value IS NULL OR m.meta_value = '' OR m.meta_value = '0')
	ORDER BY p.ID ASC
	LIMIT %d OFFSET %d",
	$meta_key,
	$limit,
	$offset
);

$ids = $wpdb->get_col( $sql );
if ( ! is_array( $ids ) ) {
	$ids = array();
}

echo "minio-offload-missing: found " . count( $ids ) . " attachment(s) (limit={$limit} offset={$offset} type={$type})\n";

$ok  = 0;
$bad = 0;

foreach ( $ids as $raw_id ) {
	$id   = (int) $raw_id;
	$file = get_post_meta( $id, '_wp_attached_file', true );
	if ( ! is_string( $file ) || '' === $file ) {
		echo "SKIP {$id} (missing _wp_attached_file)\n";
		++$bad;
		continue;
	}
	$meta = wp_get_attachment_metadata( $id );
	if ( ! is_array( $meta ) ) {
		$meta = array();
	}

	if ( $dry_run ) {
		echo "DRY {$id} {$file}\n";
		continue;
	}

	Streamit_Child_Minio_Offload::offload_attachment( $meta, $id );
	$done = (bool) get_post_meta( $id, $meta_key, true );
	if ( $done ) {
		echo "OK  {$id} {$file}\n";
		++$ok;
	} else {
		echo "ERR {$id} {$file}\n";
		++$bad;
	}
}

echo "done ok={$ok} err={$bad}\n";

<?php
/**
 * Repair attachments missing _wp_attached_file / metadata (broken TMDB imports).
 * Usage: php repair-broken-attachments.php [--all]
 */
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

global $wpdb;

$repair_all = in_array( '--all', $argv ?? [], true );

$sql = "
	SELECT p.ID
	FROM {$wpdb->posts} p
	LEFT JOIN {$wpdb->postmeta} pm
		ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
	WHERE p.post_type = 'attachment'
	  AND (pm.meta_value IS NULL OR pm.meta_value = '')
";

if ( ! $repair_all ) {
	$sql .= ' AND p.ID >= 4600';
}

$ids     = $wpdb->get_col( $sql );
$upload  = wp_upload_dir();
$baseurl = untrailingslashit( $upload['baseurl'] );
$basedir = untrailingslashit( $upload['basedir'] );
$fixed   = 0;
$failed  = 0;

echo 'Found ' . count( $ids ) . " broken attachment(s)\n";

foreach ( $ids as $attachment_id ) {
	$attachment_id = (int) $attachment_id;
	$post          = get_post( $attachment_id );

	if ( ! $post || empty( $post->guid ) ) {
		++$failed;
		continue;
	}

	$relative = '';
	$guid     = $post->guid;

	if ( str_starts_with( $guid, $baseurl . '/' ) ) {
		$relative = ltrim( substr( $guid, strlen( $baseurl ) ), '/' );
	} elseif ( false !== strpos( $guid, '/wp-content/uploads/' ) ) {
		$relative = ltrim( substr( $guid, strpos( $guid, '/wp-content/uploads/' ) + strlen( '/wp-content/uploads/' ) ), '/' );
	} else {
		$filename = basename( parse_url( $guid, PHP_URL_PATH ) );
		$matches  = glob( $basedir . '/**/' . $filename, GLOB_NOSORT );
		if ( ! empty( $matches[0] ) ) {
			$relative = ltrim( str_replace( $basedir . '/', '', $matches[0] ), '/' );
		}
	}

	if ( empty( $relative ) ) {
		echo "  SKIP {$attachment_id}: could not resolve file path\n";
		++$failed;
		continue;
	}

	$absolute = $basedir . '/' . $relative;
	if ( ! file_exists( $absolute ) ) {
		echo "  SKIP {$attachment_id}: file missing ({$relative})\n";
		++$failed;
		continue;
	}

	update_post_meta( $attachment_id, '_wp_attached_file', $relative );

	$metadata = wp_generate_attachment_metadata( $attachment_id, $absolute );
	if ( ! empty( $metadata ) ) {
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	$url = wp_get_attachment_image_url( $attachment_id, 'full' );
	if ( $url ) {
		echo "  OK {$attachment_id}: {$url}\n";
		++$fixed;
	} else {
		echo "  FAIL {$attachment_id}: metadata saved but URL still empty\n";
		++$failed;
	}
}

echo "Fixed: {$fixed}, failed: {$failed}\n";

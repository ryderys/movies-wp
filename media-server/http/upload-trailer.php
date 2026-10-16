<?php
/**
 * Authenticated POST /upload/trailer HTTP adapter.
 *
 * Auth: X-Media-Key + X-Media-Timestamp + X-Media-Signature (POST HMAC).
 * Integrity: X-Media-Content-Sha256 must match signed sha256 and file body.
 * Multipart fields: dir, filename, file.
 *
 * @package movies-wp
 */

declare(strict_types=1);

ini_set( 'display_errors', '0' );
header( 'Content-Type: application/json; charset=UTF-8' );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cache-Control: no-store' );
header_remove( 'X-Powered-By' );

require_once dirname( __DIR__ ) . '/lib/trailer-upload.php';

$started = microtime( true );

/**
 * @param array<string, mixed> $payload
 */
function media_upload_http_send( int $status, array $payload, string $dir, float $started, string $ip ): void {
	$safe_dir = str_replace( array( "\n", "\r" ), '', $dir );
	$ms       = (int) round( ( microtime( true ) - $started ) * 1000 );
	error_log(
		sprintf(
			'media-upload-trailer status=%d ip=%s dir=%s duration_ms=%d',
			$status,
			$ip !== '' ? $ip : '-',
			$safe_dir !== '' ? $safe_dir : '-',
			$ms
		)
	);

	http_response_code( $status );
	$json = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $json ) ) {
		http_response_code( 500 );
		echo '{"ok":false,"code":"upload_failed","message":"Upload is not available."}';
		exit;
	}
	echo $json;
	exit;
}

/**
 * @param array<string, mixed> $extra
 */
function media_upload_http_fail(
	int $status,
	string $code,
	string $message,
	string $dir,
	float $started,
	string $ip,
	array $extra = array()
): void {
	media_upload_http_send(
		$status,
		array_merge(
			array(
				'ok'      => false,
				'code'    => $code,
				'message' => $message,
			),
			$extra
		),
		$dir,
		$started,
		$ip
	);
}

$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
$dir = '';

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
	header( 'Allow: POST' );
	media_upload_http_fail( 405, 'method_not_allowed', 'Only POST is allowed.', $dir, $started, $ip );
}

$config = media_upload_trailer_config();

if ( $config['api_key'] === '' || $config['hmac_secret'] === '' ) {
	media_upload_http_fail( 500, 'upload_failed', 'Upload is not available.', $dir, $started, $ip );
}

if ( ! media_scan_ip_allowed( $ip, $config['allowed_ips'] ) ) {
	media_upload_http_fail( 403, 'forbidden', 'Forbidden.', $dir, $started, $ip );
}

$key       = isset( $_SERVER['HTTP_X_MEDIA_KEY'] ) ? (string) $_SERVER['HTTP_X_MEDIA_KEY'] : '';
$timestamp = isset( $_SERVER['HTTP_X_MEDIA_TIMESTAMP'] ) ? (string) $_SERVER['HTTP_X_MEDIA_TIMESTAMP'] : '';
$signature = isset( $_SERVER['HTTP_X_MEDIA_SIGNATURE'] ) ? (string) $_SERVER['HTTP_X_MEDIA_SIGNATURE'] : '';
$sha256    = isset( $_SERVER['HTTP_X_MEDIA_CONTENT_SHA256'] ) ? (string) $_SERVER['HTTP_X_MEDIA_CONTENT_SHA256'] : '';

if ( $key === '' || $timestamp === '' || $signature === '' || $sha256 === '' ) {
	media_upload_http_fail( 401, 'unauthorized', 'Authentication required.', $dir, $started, $ip );
}

$dir      = isset( $_POST['dir'] ) ? (string) $_POST['dir'] : '';
$filename = isset( $_POST['filename'] ) ? (string) $_POST['filename'] : '';

if ( ! hash_equals( $config['api_key'], $key ) ) {
	media_upload_http_fail( 403, 'forbidden', 'Forbidden.', $dir, $started, $ip );
}

if ( ! media_scan_timestamp_valid( $timestamp, $config['timestamp_skew'] ) ) {
	media_upload_http_fail( 403, 'forbidden', 'Forbidden.', $dir, $started, $ip );
}

$canonical = media_upload_trailer_canonical_request( $timestamp, $dir, $filename, $sha256 );
if ( ! media_scan_signature_valid( $canonical, $config['hmac_secret'], $signature ) ) {
	media_upload_http_fail( 403, 'forbidden', 'Forbidden.', $dir, $started, $ip );
}

if ( ! media_upload_nonce_consume( $canonical, $config['timestamp_skew'], $config['nonce_dir'] ) ) {
	media_upload_http_fail( 403, 'forbidden', 'Forbidden.', $dir, $started, $ip );
}

if ( $dir === '' || $filename === '' ) {
	media_upload_http_fail( 400, 'invalid_request', 'Invalid upload request.', $dir, $started, $ip );
}

if ( ! isset( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
	media_upload_http_fail( 400, 'upload_missing', 'Uploaded file is missing.', $dir, $started, $ip );
}

$file_err = isset( $_FILES['file']['error'] ) ? (int) $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
if ( $file_err === UPLOAD_ERR_INI_SIZE || $file_err === UPLOAD_ERR_FORM_SIZE ) {
	media_upload_http_fail( 413, 'too_large', 'Uploaded file exceeds the maximum allowed size.', $dir, $started, $ip );
}
if ( $file_err !== UPLOAD_ERR_OK ) {
	media_upload_http_fail( 400, 'upload_missing', 'Uploaded file is missing.', $dir, $started, $ip );
}

$tmp = isset( $_FILES['file']['tmp_name'] ) ? (string) $_FILES['file']['tmp_name'] : '';
if ( $tmp === '' || ! is_uploaded_file( $tmp ) ) {
	media_upload_http_fail( 400, 'upload_missing', 'Uploaded file is missing.', $dir, $started, $ip );
}

$result = media_upload_trailer_place(
	$dir,
	$filename,
	$tmp,
	$sha256,
	$config['media_root'],
	$config['movie_root'],
	$config['series_root'],
	array(
		'max_bytes'       => $config['max_bytes'],
		// Fail closed: magic/extension alone is never enough on the HTTP path.
		'require_ffprobe' => true,
	)
);

if ( ( $result['ok'] ?? false ) !== true ) {
	$mapped = media_upload_http_error_for_code( isset( $result['code'] ) ? (string) $result['code'] : '' );
	media_upload_http_fail( $mapped['status'], $mapped['code'], $mapped['message'], $dir, $started, $ip );
}

media_upload_http_send(
	200,
	array(
		'ok'   => true,
		'path' => (string) $result['path'],
	),
	$dir,
	$started,
	$ip
);

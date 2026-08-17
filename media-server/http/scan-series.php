<?php
/**
 * Read-only GET /scan/series HTTP adapter.
 *
 * Auth: X-Media-Key + X-Media-Timestamp + X-Media-Signature (HMAC).
 *
 * @package movies-wp
 */

declare(strict_types=1);

ini_set( 'display_errors', '0' );
header( 'Content-Type: application/json; charset=UTF-8' );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cache-Control: no-store' );
header_remove( 'X-Powered-By' );

require_once dirname( __DIR__ ) . '/lib/series-scan.php';
require_once dirname( __DIR__ ) . '/lib/scan-auth.php';

const MEDIA_SCAN_SERIES_REQUEST_PATH = '/scan/series';

$started = microtime( true );

/**
 * @param array<string, mixed> $payload
 */
function media_series_scan_http_send( int $status, array $payload, string $dir, float $started, string $ip ): void {
	$safe_dir = str_replace( array( "\n", "\r" ), '', $dir );
	$ms       = (int) round( ( microtime( true ) - $started ) * 1000 );
	error_log(
		sprintf(
			'series-scan status=%d ip=%s dir=%s duration_ms=%d',
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
		echo '{"ok":false,"code":"scan_failed","message":"Scan is not available."}';
		exit;
	}
	echo $json;
	exit;
}

/**
 * @param array<string, mixed> $extra
 */
function media_series_scan_http_fail( int $status, string $code, string $message, string $dir, float $started, string $ip, array $extra = array() ): void {
	media_series_scan_http_send(
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

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'GET' ) {
	header( 'Allow: GET' );
	media_series_scan_http_fail( 405, 'method_not_allowed', 'Only GET is allowed.', $dir, $started, $ip );
}

$config = media_scan_http_config();

if ( $config['api_key'] === '' || $config['hmac_secret'] === '' ) {
	media_series_scan_http_fail( 500, 'scan_failed', 'Scan is not available.', $dir, $started, $ip );
}

if ( ! media_scan_ip_allowed( $ip, $config['allowed_ips'] ) ) {
	media_series_scan_http_fail( 403, 'forbidden', 'Forbidden.', $dir, $started, $ip );
}

$key       = isset( $_SERVER['HTTP_X_MEDIA_KEY'] ) ? (string) $_SERVER['HTTP_X_MEDIA_KEY'] : '';
$timestamp = isset( $_SERVER['HTTP_X_MEDIA_TIMESTAMP'] ) ? (string) $_SERVER['HTTP_X_MEDIA_TIMESTAMP'] : '';
$signature = isset( $_SERVER['HTTP_X_MEDIA_SIGNATURE'] ) ? (string) $_SERVER['HTTP_X_MEDIA_SIGNATURE'] : '';

if ( $key === '' || $timestamp === '' || $signature === '' ) {
	media_series_scan_http_fail( 401, 'unauthorized', 'Authentication required.', $dir, $started, $ip );
}

$dir = isset( $_GET['dir'] ) ? (string) $_GET['dir'] : '';

if ( ! hash_equals( $config['api_key'], $key ) ) {
	media_series_scan_http_fail( 403, 'forbidden', 'Forbidden.', $dir, $started, $ip );
}

if ( ! media_scan_timestamp_valid( $timestamp, $config['timestamp_skew'] ) ) {
	media_series_scan_http_fail( 403, 'forbidden', 'Forbidden.', $dir, $started, $ip );
}

$canonical = media_scan_canonical_request_for_path( $timestamp, MEDIA_SCAN_SERIES_REQUEST_PATH, $dir );
if ( ! media_scan_signature_valid( $canonical, $config['hmac_secret'], $signature ) ) {
	media_series_scan_http_fail( 403, 'forbidden', 'Forbidden.', $dir, $started, $ip );
}

foreach ( array_keys( $_GET ) as $qk ) {
	if ( $qk !== 'dir' ) {
		media_series_scan_http_fail( 400, 'invalid_dir', 'Only the dir query parameter is allowed.', $dir, $started, $ip );
	}
}

if ( $dir === '' ) {
	media_series_scan_http_fail( 400, 'invalid_dir', 'Missing or invalid dir.', $dir, $started, $ip );
}

$series_root = isset( $config['series_root'] ) ? (string) $config['series_root'] : '';
$result      = media_scan_series_dir( $dir, $config['media_root'], $series_root );

if ( ( $result['ok'] ?? false ) !== true ) {
	$mapped = media_series_scan_http_error_for_code( isset( $result['code'] ) ? (string) $result['code'] : '' );
	media_series_scan_http_fail( $mapped['status'], $mapped['code'], $mapped['message'], $dir, $started, $ip );
}

media_series_scan_http_send( 200, $result, $dir, $started, $ip );

<?php
/**
 * CLI tests for scan HMAC / timestamp / IP helpers.
 *
 * Run: php media-server/tests/scan-auth-test.php
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/scan-auth.php';

$failures = 0;

function assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
}

echo "canonical request\n";
$dir = 'Movie/Korea/2018/Vapor';
$q   = media_scan_canonical_query( $dir );
assert_true( $q === 'dir=Movie%2FKorea%2F2018%2FVapor', 'rawurlencodes slashes' );

$canon = media_scan_canonical_request( '1700000000', $dir );
assert_true(
	$canon === "1700000000\nGET\n/scan/movie\n" . $q,
	'canonical string shape'
);

echo "\nHMAC\n";
$secret = 'test-secret';
$sig    = media_scan_signature( $canon, $secret );
assert_true( strlen( $sig ) === 64, 'sha256 hex length' );
assert_true( media_scan_signature_valid( $canon, $secret, $sig ), 'valid signature' );
assert_true( media_scan_signature_valid( $canon, $secret, strtoupper( $sig ) ), 'hex case-insensitive' );
assert_true( ! media_scan_signature_valid( $canon, $secret, 'ab' ), 'rejects short sig' );
assert_true( ! media_scan_signature_valid( $canon, $secret, $sig . '00' ), 'rejects mismatch' );

echo "\ntimestamp window\n";
$now = 1_700_000_000;
assert_true( media_scan_timestamp_valid( (string) $now, 300, $now ), 'exact now' );
assert_true( media_scan_timestamp_valid( (string) ( $now + 300 ), 300, $now ), 'at skew edge' );
assert_true( ! media_scan_timestamp_valid( (string) ( $now + 301 ), 300, $now ), 'beyond skew' );
assert_true( ! media_scan_timestamp_valid( 'nope', 300, $now ), 'rejects non-digit' );
assert_true( ! media_scan_timestamp_valid( '-1', 300, $now ), 'rejects signed' );

echo "\nIP allowlist\n";
assert_true( media_scan_ip_allowed( '1.2.3.4', array() ), 'empty list allows' );
assert_true( media_scan_ip_allowed( '1.2.3.4', array( '1.2.3.4' ) ), 'exact match' );
assert_true( ! media_scan_ip_allowed( '1.2.3.5', array( '1.2.3.4' ) ), 'exact miss' );
assert_true( media_scan_ip_allowed( '10.0.0.9', array( '10.0.0.0/24' ) ), 'cidr hit' );
assert_true( ! media_scan_ip_allowed( '10.0.1.9', array( '10.0.0.0/24' ) ), 'cidr miss' );

echo "\nHTTP error mapping\n";
$e = media_scan_http_error_for_code( 'not_found' );
assert_true( $e['status'] === 404 && $e['code'] === 'not_found', '404 not_found' );
$e = media_scan_http_error_for_code( 'invalid_structure' );
assert_true( $e['status'] === 422, '422 contract' );
$e = media_scan_http_error_for_code( 'absolute_path' );
assert_true( $e['status'] === 400, '400 absolute' );
$e = media_scan_http_error_for_code( 'list_failed' );
assert_true( $e['status'] === 500 && ! str_contains( $e['message'], '/' ), '500 has no path' );

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

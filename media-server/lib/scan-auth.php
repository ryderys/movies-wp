<?php
/**
 * Admin-scan authentication helpers (API key + HMAC + timestamp + IP allowlist).
 *
 * Independent of playback signed URLs (verify.php). No secrets in code.
 *
 * @package movies-wp
 */

declare(strict_types=1);

const MEDIA_SCAN_REQUEST_PATH = '/scan/movie';
const MEDIA_SCAN_DEFAULT_SKEW = 300;

/**
 * Load scan HTTP config from environment and optional /etc/asiastarx-media.env.php.
 *
 * @return array{
 *   api_key: string,
 *   hmac_secret: string,
 *   allowed_ips: list<string>,
 *   timestamp_skew: int,
 *   media_root: string,
 *   movie_root: string
 * }
 */
function media_scan_http_config(): array {
	$file = array();
	$config_file = '/etc/asiastarx-media.env.php';
	if ( is_readable( $config_file ) ) {
		$loaded = include $config_file;
		if ( is_array( $loaded ) ) {
			$file = $loaded;
		}
	}

	$api_key = media_scan_config_string( 'MEDIA_SCAN_API_KEY', $file );
	$secret  = media_scan_config_string( 'MEDIA_SCAN_HMAC_SECRET', $file );
	$ips_raw = media_scan_config_string( 'MEDIA_SCAN_ALLOWED_IPS', $file );
	$skew    = media_scan_config_string( 'MEDIA_SCAN_TIMESTAMP_SKEW', $file );

	$allowed = array();
	if ( $ips_raw !== '' ) {
		foreach ( explode( ',', $ips_raw ) as $ip ) {
			$ip = trim( $ip );
			if ( $ip !== '' ) {
				$allowed[] = $ip;
			}
		}
	}

	$skew_int = (int) $skew;
	if ( $skew_int <= 0 ) {
		$skew_int = MEDIA_SCAN_DEFAULT_SKEW;
	}

	$roots = function_exists( 'media_movie_dir_roots' )
		? media_movie_dir_roots()
		: array(
			'media_root' => media_scan_config_string( 'MEDIA_ROOT', $file ) ?: '/data',
			'movie_root' => '',
		);

	if ( $roots['movie_root'] === '' ) {
		$media = $roots['media_root'] !== '' ? $roots['media_root'] : '/data';
		$movie = media_scan_config_string( 'MOVIE_ROOT', $file );
		$roots['movie_root'] = $movie !== '' ? $movie : rtrim( $media, '/' ) . '/Movie';
	}

	return array(
		'api_key'         => $api_key,
		'hmac_secret'     => $secret,
		'allowed_ips'     => $allowed,
		'timestamp_skew'  => $skew_int,
		'media_root'      => $roots['media_root'],
		'movie_root'      => $roots['movie_root'],
	);
}

/**
 * @param array<string, mixed> $file
 */
function media_scan_config_string( string $key, array $file ): string {
	$env = getenv( $key );
	if ( is_string( $env ) && $env !== '' ) {
		return $env;
	}
	if ( isset( $file[ $key ] ) && is_string( $file[ $key ] ) && $file[ $key ] !== '' ) {
		return $file[ $key ];
	}
	return '';
}

/**
 * Canonical query: sorted keys, RFC 3986 encoding. Only `dir` is signed.
 */
function media_scan_canonical_query( string $dir ): string {
	return 'dir=' . rawurlencode( $dir );
}

function media_scan_canonical_request( string $timestamp, string $dir ): string {
	return $timestamp . "\nGET\n" . MEDIA_SCAN_REQUEST_PATH . "\n" . media_scan_canonical_query( $dir );
}

function media_scan_signature( string $canonical, string $secret ): string {
	return hash_hmac( 'sha256', $canonical, $secret );
}

function media_scan_signature_valid( string $canonical, string $secret, string $given ): bool {
	$given = strtolower( trim( $given ) );
	if ( $given === '' || $secret === '' ) {
		return false;
	}
	$expected = media_scan_signature( $canonical, $secret );
	return hash_equals( $expected, $given );
}

function media_scan_timestamp_valid( string $timestamp, int $skew, ?int $now = null ): bool {
	if ( $timestamp === '' || ! ctype_digit( $timestamp ) ) {
		return false;
	}
	$now  = $now ?? time();
	$ts   = (int) $timestamp;
	$skew = max( 1, $skew );
	return abs( $now - $ts ) <= $skew;
}

/**
 * Empty allowlist = not configured (HMAC still required). Exact IP or IPv4 CIDR.
 *
 * @param list<string> $allowed
 */
function media_scan_ip_allowed( string $ip, array $allowed ): bool {
	if ( $allowed === array() ) {
		return true;
	}
	$ip = trim( $ip );
	if ( $ip === '' ) {
		return false;
	}
	foreach ( $allowed as $rule ) {
		if ( $rule === $ip ) {
			return true;
		}
		if ( str_contains( $rule, '/' ) && media_scan_cidr_match( $ip, $rule ) ) {
			return true;
		}
	}
	return false;
}

function media_scan_cidr_match( string $ip, string $cidr ): bool {
	$parts = explode( '/', $cidr, 2 );
	if ( count( $parts ) !== 2 ) {
		return false;
	}
	$network = $parts[0];
	$bits    = (int) $parts[1];
	$ip_bin  = inet_pton( $ip );
	$net_bin = inet_pton( $network );
	if ( $ip_bin === false || $net_bin === false || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
		return false;
	}
	$max = 8 * strlen( $ip_bin );
	if ( $bits < 0 || $bits > $max ) {
		return false;
	}
	$mask = $bits === 0
		? str_repeat( "\0", strlen( $ip_bin ) )
		: pack( 'C*', ...media_scan_cidr_mask_bytes( $bits, strlen( $ip_bin ) ) );

	return ( $ip_bin & $mask ) === ( $net_bin & $mask );
}

/**
 * @return list<int>
 */
function media_scan_cidr_mask_bytes( int $bits, int $length ): array {
	$bytes = array();
	for ( $i = 0; $i < $length; $i++ ) {
		$left = $bits - ( 8 * $i );
		if ( $left >= 8 ) {
			$bytes[] = 255;
		} elseif ( $left <= 0 ) {
			$bytes[] = 0;
		} else {
			$bytes[] = ( 0xFF << ( 8 - $left ) ) & 0xFF;
		}
	}
	return $bytes;
}

/**
 * Map scanner/resolver error codes to HTTP status. Safe public messages only.
 *
 * @return array{status: int, code: string, message: string}
 */
function media_scan_http_error_for_code( string $code ): array {
	$map = array(
		'empty_path'            => array( 400, 'invalid_dir', 'Missing or invalid dir.' ),
		'invalid_path'          => array( 400, 'invalid_dir', 'Missing or invalid dir.' ),
		'absolute_path'         => array( 400, 'invalid_dir', 'dir must be a relative movie directory.' ),
		'invalid_segment'       => array( 400, 'invalid_dir', 'dir contains invalid path segments.' ),
		'not_found'             => array( 404, 'not_found', 'Movie directory not found.' ),
		'invalid_structure'     => array( 422, 'invalid_movie_dir', 'dir is not a valid movie directory.' ),
		'invalid_year'          => array( 422, 'invalid_movie_dir', 'dir is not a valid movie directory.' ),
		'not_directory'         => array( 422, 'invalid_movie_dir', 'dir is not a valid movie directory.' ),
		'outside_media_root'    => array( 422, 'invalid_movie_dir', 'dir is not a valid movie directory.' ),
		'outside_movie_root'    => array( 422, 'invalid_movie_dir', 'dir is not a valid movie directory.' ),
		'root_not_found'        => array( 500, 'scan_failed', 'Scan is not available.' ),
		'invalid_roots'         => array( 500, 'scan_failed', 'Scan is not available.' ),
		'list_failed'           => array( 500, 'scan_failed', 'Scan is not available.' ),
	);

	if ( ! isset( $map[ $code ] ) ) {
		return array(
			'status'  => 400,
			'code'    => 'invalid_dir',
			'message' => 'Missing or invalid dir.',
		);
	}

	return array(
		'status'  => $map[ $code ][0],
		'code'    => $map[ $code ][1],
		'message' => $map[ $code ][2],
	);
}

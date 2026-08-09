<?php
/**
 * Plugin Name: Media Signed URL Mint
 * Description: Mints short-lived signed stream/download URLs for media.asiastarx.ir without exposing /data paths.
 * Version: 1.0.0
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Media CDN / file host base URL (no trailing slash).
 *
 * @return string
 */
function movies_wp_media_base_url() {
	if ( defined( 'MEDIA_BASE_URL' ) && MEDIA_BASE_URL ) {
		return untrailingslashit( (string) MEDIA_BASE_URL );
	}

	$from_env = getenv( 'MEDIA_BASE_URL' );
	if ( is_string( $from_env ) && $from_env !== '' ) {
		return untrailingslashit( $from_env );
	}

	return 'https://media.asiastarx.ir';
}

/**
 * Shared HMAC secret (must match the media server).
 *
 * @return string Empty if not configured.
 */
function movies_wp_media_url_secret() {
	if ( defined( 'MEDIA_URL_SECRET' ) && MEDIA_URL_SECRET ) {
		return (string) MEDIA_URL_SECRET;
	}

	$from_env = getenv( 'MEDIA_URL_SECRET' );
	if ( is_string( $from_env ) && $from_env !== '' ) {
		return $from_env;
	}

	return '';
}

/**
 * Normalize a path relative to /data (no leading slash, no ..).
 *
 * @param string $relative_path e.g. movies/Foo/Foo.mp4
 * @return string|WP_Error
 */
function movies_wp_media_normalize_relative_path( $relative_path ) {
	$path = str_replace( '\\', '/', (string) $relative_path );
	$path = ltrim( $path, '/' );

	if ( '' === $path ) {
		return new WP_Error( 'media_path_empty', 'Media path is empty.' );
	}

	if ( false !== strpos( $path, "\0" ) ) {
		return new WP_Error( 'media_path_invalid', 'Media path contains invalid characters.' );
	}

	$parts = explode( '/', $path );
	foreach ( $parts as $part ) {
		if ( '' === $part || '.' === $part || '..' === $part ) {
			return new WP_Error( 'media_path_traversal', 'Media path must not contain empty or .. segments.' );
		}
	}

	return implode( '/', $parts );
}

/**
 * URL-safe base64 encode.
 *
 * @param string $raw Raw bytes.
 * @return string
 */
function movies_wp_media_b64url_encode( $raw ) {
	return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
}

/**
 * Build a signed token for one file.
 *
 * Payload: { p: relative_path, exp: unix, typ: v|d }
 *
 * @param string $relative_path Path under /data.
 * @param string $type          v = stream, d = download.
 * @param int    $ttl_seconds   Lifetime.
 * @return string|WP_Error token
 */
function movies_wp_media_build_token( $relative_path, $type = 'v', $ttl_seconds = null ) {
	$secret = movies_wp_media_url_secret();
	if ( '' === $secret ) {
		return new WP_Error(
			'media_secret_missing',
			'MEDIA_URL_SECRET is not configured.'
		);
	}

	$path = movies_wp_media_normalize_relative_path( $relative_path );
	if ( is_wp_error( $path ) ) {
		return $path;
	}

	$type = strtolower( (string) $type );
	if ( ! in_array( $type, array( 'v', 'd' ), true ) ) {
		return new WP_Error( 'media_type_invalid', 'Type must be v (stream) or d (download).' );
	}

	if ( null === $ttl_seconds ) {
		$ttl_seconds = ( 'd' === $type ) ? 30 * MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS;
	}
	$ttl_seconds = max( 60, (int) $ttl_seconds );

	$payload = array(
		'p'   => $path,
		'exp' => time() + $ttl_seconds,
		'typ' => $type,
	);

	$payload_json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $payload_json ) ) {
		return new WP_Error( 'media_payload_encode', 'Failed to encode token payload.' );
	}

	$payload_b64 = movies_wp_media_b64url_encode( $payload_json );
	$sig         = hash_hmac( 'sha256', $payload_b64, $secret, true );
	$sig_b64     = movies_wp_media_b64url_encode( $sig );

	return $payload_b64 . '.' . $sig_b64;
}

/**
 * Mint a full signed media URL.
 *
 * @param string   $relative_path Path under /data, e.g. movies/Foo/Foo.mp4
 * @param string   $type          v|d
 * @param int|null $ttl_seconds   Optional TTL.
 * @return string|WP_Error
 */
function movies_wp_media_signed_url( $relative_path, $type = 'v', $ttl_seconds = null ) {
	$token = movies_wp_media_build_token( $relative_path, $type, $ttl_seconds );
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$type = strtolower( (string) $type );
	$base = movies_wp_media_base_url();

	return $base . '/' . $type . '/' . rawurlencode( $token );
}

/**
 * Convenience: stream + download URLs for one file.
 *
 * @param string $relative_path Path under /data.
 * @return array{stream:string,download:string}|WP_Error
 */
function movies_wp_media_signed_urls( $relative_path ) {
	$stream = movies_wp_media_signed_url( $relative_path, 'v' );
	if ( is_wp_error( $stream ) ) {
		return $stream;
	}

	$download = movies_wp_media_signed_url( $relative_path, 'd' );
	if ( is_wp_error( $download ) ) {
		return $download;
	}

	return array(
		'stream'   => $stream,
		'download' => $download,
	);
}

/**
 * Admin notice if secret is missing (WP admins only).
 */
function movies_wp_media_admin_notice_missing_secret() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( movies_wp_media_url_secret() !== '' ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'Media Signed URL Mint: set MEDIA_URL_SECRET (and optionally MEDIA_BASE_URL) so stream/download links can be issued.', 'movies-wp' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'movies_wp_media_admin_notice_missing_secret' );

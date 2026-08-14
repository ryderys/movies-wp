<?php
/**
 * Same-origin WebVTT endpoint for player caption tracks.
 *
 * `_subtitles[].url` points at .srt files on media.asiastarx.ir. A `<track>`
 * cannot consume that directly: browsers parse WebVTT only, and a cross-origin
 * track is dropped unless the `<video>` opts into CORS and the media host
 * answers with CORS headers. Putting `crossorigin` on the `<video>` would also
 * change how the signed video stream is fetched, so instead the player points
 * at this same-origin endpoint, which mints the signed /v/ URL per request,
 * fetches the file and returns it as `text/vtt`.
 *
 * Storage stays relative; nothing signed is ever written back to meta.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Subtitle formats this endpoint will serve.
 *
 * @return string[]
 */
function streamit_child_subtitle_formats() {
	return array( 'srt', 'vtt' );
}

/**
 * Lowercase extension of a stored subtitle path or URL.
 *
 * @param string $stored Stored `_subtitles[].url`.
 * @return string Extension without the dot, or empty.
 */
function streamit_child_subtitle_extension( $stored ) {
	$stored = trim( (string) $stored );
	if ( '' === $stored ) {
		return '';
	}

	if ( preg_match( '#^https?://#i', $stored ) ) {
		$stored = (string) wp_parse_url( $stored, PHP_URL_PATH );
	}

	return strtolower( (string) pathinfo( $stored, PATHINFO_EXTENSION ) );
}

/**
 * Same-origin URL the player should use as `<track src>`.
 *
 * @param int    $post_id   Movie/episode/video ID.
 * @param string $post_type movie|episode|video.
 * @param int    $index     Index in the normalized subtitle list.
 * @return string
 */
function streamit_child_subtitle_track_url( $post_id, $post_type, $index ) {
	return add_query_arg(
		array(
			'streamit_subtitle'      => (int) $post_id,
			'streamit_subtitle_type' => sanitize_key( (string) $post_type ),
			'streamit_subtitle_i'    => (int) $index,
		),
		home_url( '/' )
	);
}

/**
 * Decode subtitle bytes to UTF-8.
 *
 * Persian .srt files are still commonly saved as Windows-1256.
 *
 * @param string $raw Raw file bytes.
 * @return string
 */
function streamit_child_subtitle_to_utf8( $raw ) {
	$text = (string) $raw;

	if ( '' === $text || ! function_exists( 'mb_check_encoding' ) ) {
		return $text;
	}

	if ( mb_check_encoding( $text, 'UTF-8' ) ) {
		return $text;
	}

	$converted = mb_convert_encoding( $text, 'UTF-8', 'Windows-1256' );

	return ( is_string( $converted ) && '' !== $converted ) ? $converted : $text;
}

/**
 * Convert SubRip text to WebVTT.
 *
 * @param string $srt SubRip body.
 * @return string WebVTT body.
 */
function streamit_child_srt_to_vtt( $srt ) {
	$text = (string) $srt;

	if ( str_starts_with( $text, "\xEF\xBB\xBF" ) ) {
		$text = substr( $text, 3 );
	}

	$text = str_replace( array( "\r\n", "\r" ), "\n", $text );

	// SubRip separates milliseconds with a comma, WebVTT with a dot.
	$text = (string) preg_replace( '/(\d{1,3}:[0-5]\d:[0-5]\d),(\d{1,3})/', '$1.$2', $text );

	$text = trim( $text, "\n" );
	if ( '' === $text ) {
		return "WEBVTT\n";
	}

	return "WEBVTT\n\n" . $text . "\n";
}

/**
 * Normalize a fetched subtitle body into a WebVTT payload.
 *
 * @param string $raw Raw file bytes.
 * @param string $ext Source extension (srt|vtt).
 * @return string
 */
function streamit_child_subtitle_body_to_vtt( $raw, $ext ) {
	$text = streamit_child_subtitle_to_utf8( $raw );

	if ( 'vtt' === strtolower( (string) $ext ) ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		if ( str_starts_with( $text, "\xEF\xBB\xBF" ) ) {
			$text = substr( $text, 3 );
		}
		if ( ! str_starts_with( ltrim( $text ), 'WEBVTT' ) ) {
			return streamit_child_srt_to_vtt( $text );
		}
		return $text;
	}

	return streamit_child_srt_to_vtt( $text );
}

/**
 * Fetch a stored subtitle and return it as WebVTT.
 *
 * @param string $stored Stored relative path or absolute URL.
 * @return string WebVTT body, or empty on failure.
 */
function streamit_child_subtitle_fetch_vtt( $stored ) {
	$ext = streamit_child_subtitle_extension( $stored );
	if ( ! in_array( $ext, streamit_child_subtitle_formats(), true ) ) {
		return '';
	}

	$cache_key = 'stc_vtt_' . md5( (string) $stored );
	$cached    = get_transient( $cache_key );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}

	$source = streamit_child_resolve_subtitle_url( $stored, 'v' );
	if ( '' === $source ) {
		return '';
	}

	$response = wp_remote_get(
		$source,
		array(
			'timeout'     => 15,
			'redirection' => 3,
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return '';
	}

	$vtt = streamit_child_subtitle_body_to_vtt( wp_remote_retrieve_body( $response ), $ext );
	if ( '' === trim( str_replace( 'WEBVTT', '', $vtt ) ) ) {
		return '';
	}

	set_transient( $cache_key, $vtt, 12 * HOUR_IN_SECONDS );

	return $vtt;
}

/**
 * Register the endpoint query vars.
 *
 * @param string[] $vars Public query vars.
 * @return string[]
 */
function streamit_child_subtitle_query_vars( $vars ) {
	$vars[] = 'streamit_subtitle';
	$vars[] = 'streamit_subtitle_type';
	$vars[] = 'streamit_subtitle_i';

	return $vars;
}
add_filter( 'query_vars', 'streamit_child_subtitle_query_vars' );

/**
 * Serve one caption track as text/vtt from the site origin.
 */
function streamit_child_serve_subtitle_track() {
	$post_id = (int) get_query_var( 'streamit_subtitle' );
	if ( ! $post_id && isset( $_GET['streamit_subtitle'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = (int) $_GET['streamit_subtitle']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( $post_id <= 0 ) {
		return;
	}

	$post_type = (string) get_query_var( 'streamit_subtitle_type' );
	if ( '' === $post_type && isset( $_GET['streamit_subtitle_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_type = sanitize_key( wp_unslash( $_GET['streamit_subtitle_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	$index = (int) get_query_var( 'streamit_subtitle_i' );
	if ( ! $index && isset( $_GET['streamit_subtitle_i'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$index = (int) $_GET['streamit_subtitle_i']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( $index < 0 ) {
		$index = 0;
	}

	if ( ! is_user_logged_in() ) {
		status_header( 403 );
		exit;
	}

	if ( function_exists( 'movies_wp_user_can_access_media' )
		&& ! movies_wp_user_can_access_media( get_current_user_id() ) ) {
		status_header( 403 );
		exit;
	}

	$subs = streamit_child_get_subtitles_by_id( $post_id, $post_type );
	if ( ! isset( $subs[ $index ]['url'] ) ) {
		status_header( 404 );
		exit;
	}

	$vtt = streamit_child_subtitle_fetch_vtt( $subs[ $index ]['url'] );
	if ( '' === $vtt ) {
		status_header( 404 );
		exit;
	}

	header( 'Content-Type: text/vtt; charset=UTF-8' );
	header( 'Content-Length: ' . strlen( $vtt ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cache-Control: private, max-age=3600' );

	echo $vtt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WebVTT payload, not HTML.
	exit;
}
add_action( 'template_redirect', 'streamit_child_serve_subtitle_track', 0 );

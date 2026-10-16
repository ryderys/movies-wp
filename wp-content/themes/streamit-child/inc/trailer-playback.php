<?php
/**
 * Public trailer playback: external embeds + signed local Trailer/ paths.
 *
 * Local relative paths are signed only when the file sits directly under a
 * Trailer/ or Trailers/ directory. Signed URLs are never written to meta.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Strip a stored trailer value to a relative /data path when possible.
 *
 * @param string $stored Meta value.
 * @return string Relative path without leading slash, or empty.
 */
function streamit_child_normalize_trailer_relative_path( $stored ) {
	$stored = str_replace( '\\', '/', trim( (string) $stored ) );
	if ( '' === $stored ) {
		return '';
	}

	if ( str_starts_with( $stored, '/data/' ) || str_contains( $stored, '/data/' ) ) {
		$stored = preg_replace( '#^.*?/data/#', '', $stored );
		$stored = ltrim( (string) $stored, '/' );
	}

	$stored = ltrim( $stored, '/' );
	if ( '' === $stored || false !== strpos( $stored, "\0" ) ) {
		return '';
	}

	if ( function_exists( 'movies_wp_media_normalize_relative_path' ) ) {
		$normalized = movies_wp_media_normalize_relative_path( $stored );
		if ( is_wp_error( $normalized ) ) {
			return '';
		}
		return (string) $normalized;
	}

	$parts = explode( '/', $stored );
	foreach ( $parts as $part ) {
		if ( '' === $part || '.' === $part || '..' === $part ) {
			return '';
		}
	}

	return implode( '/', $parts );
}

/**
 * Whether a relative path is a direct file under Trailer/ or Trailers/.
 *
 * The penultimate segment must be Trailer or Trailers (case-insensitive).
 * This blocks signing a full feature or any file outside that folder.
 *
 * @param string $relative Normalized relative path.
 * @return bool
 */
function streamit_child_is_allowed_trailer_media_path( $relative ) {
	$relative = streamit_child_normalize_trailer_relative_path( $relative );
	if ( '' === $relative ) {
		return false;
	}

	$parts = explode( '/', $relative );
	$count = count( $parts );
	if ( $count < 2 ) {
		return false;
	}

	$parent = $parts[ $count - 2 ];
	if ( 0 !== strcasecmp( $parent, 'Trailer' ) && 0 !== strcasecmp( $parent, 'Trailers' ) ) {
		return false;
	}

	$basename = $parts[ $count - 1 ];
	if ( '' === $basename || false === strpos( $basename, '.' ) ) {
		return false;
	}

	$ext   = function_exists( 'streamit_child_media_extension' )
		? streamit_child_media_extension( $relative )
		: strtolower( (string) pathinfo( $basename, PATHINFO_EXTENSION ) );
	$types = function_exists( 'streamit_child_media_video_types' )
		? streamit_child_media_video_types()
		: array(
			'mp4'  => 'video/mp4',
			'mkv'  => 'video/x-matroska',
			'webm' => 'video/webm',
		);

	return '' !== $ext && isset( $types[ $ext ] );
}

/**
 * MIME type for a stored trailer path or URL.
 *
 * @param string $stored Path or URL.
 * @return string Empty when unknown.
 */
function streamit_child_trailer_mime_for_stored( $stored ) {
	$ext = function_exists( 'streamit_child_media_extension' )
		? streamit_child_media_extension( $stored )
		: strtolower( (string) pathinfo( (string) wp_parse_url( (string) $stored, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

	$types = function_exists( 'streamit_child_media_video_types' )
		? streamit_child_media_video_types()
		: array(
			'mp4'  => 'video/mp4',
			'mkv'  => 'video/x-matroska',
			'webm' => 'video/webm',
		);

	return ( '' !== $ext && isset( $types[ $ext ] ) ) ? $types[ $ext ] : '';
}

/**
 * Resolve _name_trailer_link for public playback.
 *
 * External http(s) URLs keep streamit_get_trailer_embed() behavior.
 * Local paths are signed only when under Trailer/ or Trailers/.
 * Never persists the signed URL.
 *
 * @param string $stored Raw meta value.
 * @return array{type:string,trailer_link:string,mime:string}
 */
function streamit_child_get_trailer_playback( $stored ) {
	$empty = array(
		'type'         => '',
		'trailer_link' => '',
		'mime'         => '',
	);

	$stored = trim( (string) $stored );
	if ( '' === $stored ) {
		return $empty;
	}

	if ( function_exists( 'streamit_child_is_external_media_link' ) && streamit_child_is_external_media_link( $stored ) ) {
		$embed = function_exists( 'streamit_get_trailer_embed' )
			? streamit_get_trailer_embed( $stored )
			: $empty;

		$type = isset( $embed['type'] ) ? (string) $embed['type'] : '';
		$link = isset( $embed['trailer_link'] ) ? (string) $embed['trailer_link'] : '';
		$mime = ( 'video' === $type ) ? streamit_child_trailer_mime_for_stored( $stored ) : '';

		return array(
			'type'         => $type,
			'trailer_link' => $link,
			'mime'         => $mime,
		);
	}

	$relative = streamit_child_normalize_trailer_relative_path( $stored );
	if ( '' === $relative || ! streamit_child_is_allowed_trailer_media_path( $relative ) ) {
		return $empty;
	}

	if ( ! function_exists( 'movies_wp_media_signed_url' ) ) {
		return $empty;
	}

	$signed = movies_wp_media_signed_url( $relative, 'v' );
	if ( is_wp_error( $signed ) || ! is_string( $signed ) || '' === $signed ) {
		return $empty;
	}

	return array(
		'type'         => 'video',
		'trailer_link' => $signed,
		'mime'         => streamit_child_trailer_mime_for_stored( $relative ),
	);
}

/**
 * Enqueue a small front-end helper so the trailer modal uses the correct MIME.
 */
function streamit_child_enqueue_trailer_modal_mime() {
	$path = get_stylesheet_directory() . '/assets/js/trailer-modal-mime.js';
	if ( ! is_readable( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'streamit-child-trailer-modal-mime',
		get_stylesheet_directory_uri() . '/assets/js/trailer-modal-mime.js',
		array(),
		(string) filemtime( $path ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'streamit_child_enqueue_trailer_modal_mime', 40 );

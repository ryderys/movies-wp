<?php
/**
 * Rewrite local /data paths for player + download (Option A).
 *
 * General "Movie URL" and Sources "Link" may store paths like Movie/Foo/Foo.mp4.
 * Player gets a short-lived signed media.asiastarx.ir URL (user already passed access).
 * Download buttons use stable WP gateway URLs (login checked on click).
 *
 * Signed URLs are /v/{token} with no file extension — video HTML must use the
 * stored path's extension for MIME type (cannot rely on streamit_get_url_video_html alone).
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a stored link is an external http(s) URL.
 *
 * @param string $link Link or path.
 * @return bool
 */
function streamit_child_is_external_media_link( $link ) {
	return (bool) preg_match( '#^https?://#i', trim( (string) $link ) );
}

/**
 * Whether a stored link looks like a local path under /data.
 *
 * @param string $link Link or path.
 * @return bool
 */
function streamit_child_is_local_media_path( $link ) {
	$link = trim( (string) $link );
	if ( '' === $link ) {
		return false;
	}
	if ( streamit_child_is_external_media_link( $link ) ) {
		return false;
	}
	if ( false !== strpos( $link, '://' ) ) {
		return false;
	}
	return true;
}

/**
 * File extension for MIME / HLS detection (from path or URL path).
 *
 * @param string $stored Path or URL.
 * @return string Lowercase extension without dot.
 */
function streamit_child_media_extension( $stored ) {
	$stored = trim( (string) $stored );
	if ( '' === $stored ) {
		return '';
	}
	if ( streamit_child_is_external_media_link( $stored ) ) {
		$path = (string) wp_parse_url( $stored, PHP_URL_PATH );
		return strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	}
	return strtolower( pathinfo( $stored, PATHINFO_EXTENSION ) );
}

/**
 * MIME map matching parent streamit_get_url_video_html.
 *
 * @return array<string, string>
 */
function streamit_child_media_video_types() {
	return array(
		'mp4'  => 'video/mp4',
		'webm' => 'video/webm',
		'ogv'  => 'video/ogg',
		'mov'  => 'video/quicktime',
		'mkv'  => 'video/x-matroska',
		'avi'  => 'video/x-msvideo',
		'flv'  => 'video/x-flv',
		'wmv'  => 'video/x-ms-wmv',
		'm3u8' => 'application/x-mpegURL',
		'mpd'  => 'application/dash+xml',
	);
}

/**
 * Turn a stored General/Sources value into a URL the HTML5 player can load.
 *
 * Local paths → signed media URL (requires mint plugin + secret).
 * External https → unchanged.
 *
 * @param string $stored  Path or URL from meta.
 * @param int    $post_id Content ID (reserved).
 * @param int    $index   Source index (reserved).
 * @return string Playable URL or empty.
 */
function streamit_child_resolve_playable_src( $stored, $post_id = 0, $index = 0 ) {
	unset( $post_id, $index );

	$stored = trim( (string) $stored );
	if ( '' === $stored ) {
		return '';
	}

	if ( streamit_child_is_external_media_link( $stored ) ) {
		return $stored;
	}

	if ( ! streamit_child_is_local_media_path( $stored ) ) {
		return '';
	}

	if ( ! function_exists( 'movies_wp_media_signed_url' ) ) {
		return '';
	}

	$signed = movies_wp_media_signed_url( $stored, 'v' );
	if ( is_wp_error( $signed ) ) {
		return '';
	}

	return $signed;
}

/**
 * Download href for a source row: gateway if local/external (login), else raw.
 *
 * @param string $stored  download_content or link.
 * @param int    $post_id Movie/episode ID.
 * @param int    $index   Source index in _source / _sources.
 * @return string
 */
function streamit_child_resolve_download_href( $stored, $post_id, $index = 0 ) {
	$stored = trim( (string) $stored );
	if ( '' === $stored ) {
		return '';
	}

	if ( function_exists( 'movies_wp_media_download_url' )
		&& ( streamit_child_is_local_media_path( $stored ) || streamit_child_is_external_media_link( $stored ) )
	) {
		return movies_wp_media_download_url( (int) $post_id, (int) $index );
	}

	return $stored;
}

/**
 * Build <video> HTML for a playable URL using extension from the stored path.
 *
 * @param string $playable_url Absolute URL for src=.
 * @param string $stored       Original path/URL (for extension / embed fallback).
 * @return string
 */
function streamit_child_build_video_html( $playable_url, $stored = '' ) {
	$playable_url = trim( (string) $playable_url );
	if ( '' === $playable_url ) {
		return '<p class="no-data-found">' . esc_html__( 'Invalid video URL.', 'streamit' ) . '</p>';
	}

	$ext   = streamit_child_media_extension( $stored !== '' ? $stored : $playable_url );
	$types = streamit_child_media_video_types();

	if ( $ext && isset( $types[ $ext ] ) ) {
		return '<video class="plyr__video-embed" id="streamit_player" playsinline >
            <source src="' . esc_url( $playable_url ) . '" type="' . esc_attr( $types[ $ext ] ) . '" />
        </video>';
	}

	// External embeds (YouTube etc.): prefer parent helper on the original URL.
	if ( streamit_child_is_external_media_link( $stored ) && function_exists( 'streamit_get_url_video_html' ) ) {
		return streamit_get_url_video_html( $stored );
	}

	if ( function_exists( 'streamit_get_url_video_html' ) ) {
		return streamit_get_url_video_html( $playable_url );
	}

	return '<p class="no-data-found">' . esc_html__( 'Unsupported video format or URL.', 'streamit' ) . '</p>';
}

/**
 * Build video HTML for a stored path/URL (player-safe).
 *
 * @param string $stored  Path or URL.
 * @param int    $post_id Post ID.
 * @param int    $index   Source index.
 * @return string
 */
function streamit_child_get_url_video_html_for_stored( $stored, $post_id = 0, $index = 0 ) {
	$playable = streamit_child_resolve_playable_src( $stored, $post_id, $index );
	if ( '' === $playable ) {
		return '<p class="no-data-found">' . esc_html__( 'Invalid video URL.', 'streamit' ) . '</p>';
	}
	return streamit_child_build_video_html( $playable, $stored );
}

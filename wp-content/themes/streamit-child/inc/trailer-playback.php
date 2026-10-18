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
 * Aparat watch URL → embed URL.
 *
 * Accepts only https://www.aparat.com/v/{hash} and https://aparat.com/v/{hash}.
 * The hash is alphanumeric, 5–16 characters. Host is normalized to www.aparat.com.
 *
 * @param string $stored Raw meta value.
 * @return string Embed URL, or empty when the value is not a valid Aparat watch URL.
 */
function streamit_child_aparat_trailer_embed_url( $stored ) {
	$parts = wp_parse_url( trim( (string) $stored ) );
	if ( ! is_array( $parts ) ) {
		return '';
	}

	$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
	$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
	$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '';

	if ( 'https' !== $scheme ) {
		return '';
	}
	if ( 'www.aparat.com' !== $host && 'aparat.com' !== $host ) {
		return '';
	}
	if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
		return '';
	}
	if ( ! empty( $parts['query'] ) || ! empty( $parts['fragment'] ) ) {
		return '';
	}
	if ( ! preg_match( '#^/v/([A-Za-z0-9]{5,16})/?$#', $path, $matches ) ) {
		return '';
	}

	return 'https://www.aparat.com/video/video/embed/videohash/' . $matches[1] . '/vt/frame';
}

/**
 * Resolve _name_trailer_link for public playback.
 *
 * Aparat watch URLs become Aparat embed iframes.
 * Other external http(s) URLs keep streamit_get_trailer_embed() behavior.
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

	$aparat = streamit_child_aparat_trailer_embed_url( $stored );
	if ( '' !== $aparat ) {
		return array(
			'type'         => 'iframe',
			'trailer_link' => $aparat,
			'mime'         => '',
		);
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
 * Attributes for a movie-single "Watch Trailer" button, or null when hidden.
 *
 * @param string $stored Raw `_name_trailer_link` meta.
 * @return array{type:string,trailer_link:string,mime:string}|null
 */
function streamit_child_get_movie_trailer_button( $stored ) {
	$playback = streamit_child_get_trailer_playback( $stored );
	$type     = isset( $playback['type'] ) ? (string) $playback['type'] : '';
	$link     = isset( $playback['trailer_link'] ) ? (string) $playback['trailer_link'] : '';
	$mime     = isset( $playback['mime'] ) ? (string) $playback['mime'] : '';

	if ( '' === $link || ( 'iframe' !== $type && 'video' !== $type ) ) {
		return null;
	}

	return array(
		'type'         => $type,
		'trailer_link' => $link,
		'mime'         => $mime,
	);
}

/**
 * Whether an admin Trailer Link value may be saved.
 *
 * Empty is allowed. A relative value must be a direct file under Trailer/ or
 * Trailers/ (mp4, webm, mkv). An absolute value must be an http(s) URL with a
 * host, which covers YouTube, Vimeo, Aparat, and other existing trailer URLs.
 * This does not change how those values are played.
 *
 * @param string $value Raw field value.
 * @return bool
 */
function streamit_child_is_accepted_trailer_link( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return true;
	}
	if ( preg_match( '/[\s\x00-\x1F\x7F]/', $value ) ) {
		return false;
	}
	if ( function_exists( 'streamit_child_is_external_media_link' ) && streamit_child_is_external_media_link( $value ) ) {
		return streamit_child_is_accepted_trailer_url( $value );
	}
	if ( false !== strpos( $value, '://' ) ) {
		return false;
	}
	return streamit_child_is_allowed_trailer_media_path( $value );
}

/**
 * http(s) URL with a host. Rejects scheme-only and credentialed URLs.
 *
 * @param string $value Trimmed URL.
 * @return bool
 */
function streamit_child_is_accepted_trailer_url( $value ) {
	if ( ! preg_match( '#^https?://#i', $value ) ) {
		return false;
	}

	$parts = wp_parse_url( $value );
	if ( ! is_array( $parts ) ) {
		return false;
	}

	$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
	$host   = isset( $parts['host'] ) ? (string) $parts['host'] : '';
	if ( 'http' !== $scheme && 'https' !== $scheme ) {
		return false;
	}
	if ( '' === $host || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return false;
	}
	if ( ! str_contains( $host, '.' ) && ! filter_var( $host, FILTER_VALIDATE_IP ) ) {
		return false;
	}

	return true;
}

/**
 * Attachment ID stored in `_name_trailer_img`, when the value is an ID or ID array.
 *
 * @param mixed $raw Meta value.
 * @return int
 */
function streamit_child_trailer_image_attachment_id( $raw ) {
	if ( is_numeric( $raw ) ) {
		return absint( $raw );
	}
	if ( ! is_array( $raw ) ) {
		return 0;
	}
	foreach ( array( 'attachment_id', 'id', 'ID' ) as $key ) {
		if ( isset( $raw[ $key ] ) && is_numeric( $raw[ $key ] ) ) {
			return absint( $raw[ $key ] );
		}
	}
	return 0;
}

/**
 * Direct image URL stored in `_name_trailer_img`, when it is not an attachment ID.
 *
 * @param mixed $raw Meta value.
 * @return string
 */
function streamit_child_trailer_image_url( $raw ) {
	if ( is_string( $raw ) && preg_match( '#^https?://#i', trim( $raw ) ) ) {
		return trim( $raw );
	}
	if ( is_array( $raw ) && isset( $raw['url'] ) && is_string( $raw['url'] ) && preg_match( '#^https?://#i', trim( $raw['url'] ) ) ) {
		return trim( $raw['url'] );
	}
	return '';
}

/**
 * Poster for the episodes-slider trailer card.
 *
 * Prefer `_name_trailer_img`. Fall back to the show poster (`thumbnail_id`).
 *
 * @param object $tvshow Streamit TV show.
 * @return string HTML, already escaped by the image helper, or an escaped img tag.
 */
function streamit_child_tvshow_trailer_card_image_html( $tvshow ) {
	$alt   = __( 'تریلر', 'streamit' );
	$class = 'img-fluid object-fit-cover position-relative';
	$raw   = method_exists( $tvshow, 'get_meta' ) ? $tvshow->get_meta( '_name_trailer_img' ) : '';
	$id    = streamit_child_trailer_image_attachment_id( $raw );

	if ( $id <= 0 && method_exists( $tvshow, 'get_meta' ) ) {
		$id = absint( $tvshow->get_meta( 'thumbnail_id' ) );
	}

	if ( $id > 0 && function_exists( 'streamit_render_image' ) ) {
		return (string) streamit_render_image(
			array(
				'attachment_id' => $id,
				'class'         => $class,
				'alt'           => $alt,
				'decoding'      => 'async',
				'size'          => defined( 'STREAMIT_CHILD_SIZE_EPISODE' ) ? STREAMIT_CHILD_SIZE_EPISODE : 'full',
			)
		);
	}

	$url = streamit_child_trailer_image_url( $raw );
	if ( '' === $url && $id > 0 && function_exists( 'wp_get_attachment_image_url' ) ) {
		$url = (string) wp_get_attachment_image_url( $id, 'full' );
	}
	if ( '' === $url && function_exists( 'streamit_placeholder_image' ) ) {
		$url = (string) streamit_placeholder_image();
	}
	if ( '' === $url ) {
		return '';
	}

	return '<img src="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '" alt="' . esc_attr( $alt ) . '" decoding="async" />';
}

/**
 * Data for the TV-show trailer section card, or null when the show has no playable trailer.
 *
 * Uses the same playback resolver as the Watch Trailer button. The card is not an episode.
 *
 * @param object $tvshow Streamit TV show.
 * @return array{label:string,type:string,trailer_link:string,mime:string,image_html:string}|null
 */
function streamit_child_get_tvshow_episode_trailer_card( $tvshow ) {
	if ( ! is_object( $tvshow ) || ! method_exists( $tvshow, 'get_meta' ) ) {
		return null;
	}
	if ( ! function_exists( 'streamit_child_get_movie_trailer_button' ) ) {
		return null;
	}

	$stored = $tvshow->get_meta( '_name_trailer_link' );
	$button = streamit_child_get_movie_trailer_button( null === $stored ? '' : $stored );
	if ( ! is_array( $button ) ) {
		return null;
	}

	return array(
		'label'        => __( 'تریلر', 'streamit' ),
		'type'         => (string) $button['type'],
		'trailer_link' => (string) $button['trailer_link'],
		'mime'         => (string) $button['mime'],
		'image_html'   => streamit_child_tvshow_trailer_card_image_html( $tvshow ),
	);
}

/**
 * One trailer card for the TV-show detail section. Empty when there is no playable trailer.
 *
 * The overlay uses `.st-load-trailer`, so the existing click handler opens the trailer modal.
 *
 * @param object $tvshow Streamit TV show.
 * @return string
 */
function streamit_child_render_tvshow_trailer_card( $tvshow ) {
	$card = streamit_child_get_tvshow_episode_trailer_card( $tvshow );
	if ( ! is_array( $card ) ) {
		return '';
	}

	$attrs = sprintf(
		' type="button" class="episode-overlay st-load-trailer streamit-child-tvshow-trailer-open" data-trailer-type="%1$s" data-trailer-url="%2$s" data-trailer-mime="%3$s" aria-haspopup="dialog" aria-label="%4$s"',
		esc_attr( $card['type'] ),
		esc_url( $card['trailer_link'] ),
		esc_attr( $card['mime'] ),
		esc_attr( $card['label'] )
	);
	$play = function_exists( 'st_get_icon' ) ? st_get_icon( 'play' ) : '';

	return '<div class="episode-card streamit-child-tvshow-trailer-card">'
		. '<button' . $attrs . '></button>'
		. '<div class="episode-image">'
		. $card['image_html']
		. '<div class="episode-detail">'
		. '<div class="flex-grow-1"><h5 class="mt-2 mb-0 line-count-1">' . esc_html( $card['label'] ) . '</h5></div>'
		. '<div class="flex-shrink-0"><span class="btn btn-primary" aria-hidden="true">' . $play . '</span></div>'
		. '</div></div></div>';
}

/**
 * Detail-page trailer section. Empty when the show has no playable trailer.
 *
 * @param object $tvshow Streamit TV show.
 * @return string
 */
function streamit_child_render_tvshow_trailer_section( $tvshow ) {
	$card = streamit_child_render_tvshow_trailer_card( $tvshow );
	if ( '' === $card ) {
		return '';
	}

	return '<div class="section-spacing-top streamit-child-tvshow-trailer-section">'
		. '<div class="container-fluid">'
		. '<div class="d-flex align-items-center justify-content-between mb-md-5 mb-3">'
		. '<h5 class="main-title text-capitalize mb-0">' . esc_html__( 'تریلر', 'streamit' ) . '</h5>'
		. '</div>'
		. $card
		. '</div></div>';
}

/**
 * Enqueue trailer modal helpers (MIME + stop/focus).
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

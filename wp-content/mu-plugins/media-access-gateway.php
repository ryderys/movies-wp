<?php
/**
 * Plugin Name: Media Access Gateway
 * Description: Option A — logged-in users hit stable WP play/download URLs; paths in Sources "Link" become short-lived media tickets. External https links still redirect as-is.
 * Version: 1.0.0
 *
 * Requires: Media Signed URL Mint (movies_wp_media_signed_url).
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query vars for the gateway.
 *
 * @param string[] $vars Vars.
 * @return string[]
 */
function movies_wp_media_gateway_query_vars( $vars ) {
	$vars[] = 'movies_wp_media';
	$vars[] = 'movies_wp_media_post';
	$vars[] = 'movies_wp_media_i';
	return $vars;
}
add_filter( 'query_vars', 'movies_wp_media_gateway_query_vars' );

/**
 * Build a stable play URL for templates / player.
 *
 * @param int $post_id      Movie or episode ID.
 * @param int $source_index Zero-based source index in _source / _sources.
 * @return string
 */
function movies_wp_media_play_url( $post_id, $source_index = 0 ) {
	return add_query_arg(
		array(
			'movies_wp_media'      => 'play',
			'movies_wp_media_post' => (int) $post_id,
			'movies_wp_media_i'    => (int) $source_index,
		),
		home_url( '/' )
	);
}

/**
 * Build a stable download URL.
 *
 * @param int $post_id      Movie or episode ID.
 * @param int $source_index Zero-based source index.
 * @return string
 */
function movies_wp_media_download_url( $post_id, $source_index = 0 ) {
	return add_query_arg(
		array(
			'movies_wp_media'      => 'download',
			'movies_wp_media_post' => (int) $post_id,
			'movies_wp_media_i'    => (int) $source_index,
		),
		home_url( '/' )
	);
}

/**
 * Login URL for redirects.
 *
 * @param string $redirect_to After login.
 * @return string
 */
function movies_wp_media_login_url( $redirect_to = '' ) {
	if ( function_exists( 'streamit_login_page_url' ) ) {
		$url = streamit_login_page_url();
		if ( $redirect_to ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $url );
		}
		return $url;
	}
	return wp_login_url( $redirect_to );
}

/**
 * Whether a Link value is an external URL.
 *
 * @param string $link Source link.
 * @return bool
 */
function movies_wp_media_is_external_link( $link ) {
	$link = trim( (string) $link );
	return (bool) preg_match( '#^https?://#i', $link );
}

/**
 * Load sources array for a movie or episode post.
 *
 * @param int $post_id Post ID.
 * @return array{0:string,1:array<int,array<string,mixed>>}|WP_Error post_type + sources
 */
function movies_wp_media_get_sources_for_post( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return new WP_Error( 'media_bad_post', 'Invalid post ID.' );
	}

	$post_type = get_post_type( $post_id );
	if ( ! $post_type ) {
		// Streamit may use non-WP storage; try movie then episode helpers.
		$post_type = '';
	}

	$sources = null;

	if ( $post_type && in_array( $post_type, array( 'movie', 'video' ), true ) ) {
		$sources = get_post_meta( $post_id, '_source', true );
	} elseif ( 'episode' === $post_type ) {
		$sources = get_post_meta( $post_id, '_sources', true );
	}

	// Streamit object API fallback.
	if ( ( empty( $sources ) || ! is_array( $sources ) ) && function_exists( 'streamit_get_movie' ) ) {
		$obj = streamit_get_movie( $post_id );
		if ( $obj && method_exists( $obj, 'get_meta' ) ) {
			$sources   = $obj->get_meta( '_source' );
			$post_type = $post_type ?: 'movie';
		}
	}
	if ( ( empty( $sources ) || ! is_array( $sources ) ) && function_exists( 'streamit_get_episode' ) ) {
		$obj = streamit_get_episode( $post_id );
		if ( $obj && method_exists( $obj, 'get_meta' ) ) {
			$sources   = $obj->get_meta( '_sources' );
			$post_type = $post_type ?: 'episode';
		}
	}
	if ( ( empty( $sources ) || ! is_array( $sources ) ) && function_exists( 'streamit_get_video' ) ) {
		$obj = streamit_get_video( $post_id );
		if ( $obj && method_exists( $obj, 'get_meta' ) ) {
			$sources   = $obj->get_meta( '_source' );
			$post_type = $post_type ?: 'video';
		}
	}

	if ( empty( $sources ) || ! is_array( $sources ) ) {
		return new WP_Error( 'media_no_sources', 'No sources found for this content.' );
	}

	return array( $post_type ?: 'movie', $sources );
}

/**
 * Resolve link / download target string for one source row.
 *
 * @param array  $source Source row.
 * @param string $action play|download.
 * @return string
 */
function movies_wp_media_resolve_source_target( array $source, $action ) {
	$link     = isset( $source['link'] ) ? trim( (string) $source['link'] ) : '';
	$download = isset( $source['download_content'] ) ? trim( (string) $source['download_content'] ) : '';

	if ( 'download' === $action ) {
		if ( '' !== $download ) {
			return $download;
		}
		return $link;
	}

	return $link;
}

/**
 * Handle gateway requests early.
 */
function movies_wp_media_gateway_handle() {
	$action = get_query_var( 'movies_wp_media' );
	if ( ! $action ) {
		$action = isset( $_GET['movies_wp_media'] ) ? sanitize_key( wp_unslash( $_GET['movies_wp_media'] ) ) : '';
	}
	if ( ! in_array( $action, array( 'play', 'download' ), true ) ) {
		return;
	}

	$post_id = (int) get_query_var( 'movies_wp_media_post' );
	if ( ! $post_id && isset( $_GET['movies_wp_media_post'] ) ) {
		$post_id = (int) $_GET['movies_wp_media_post'];
	}
	// Backward-compatible short names from earlier design notes.
	if ( ! $post_id && isset( $_GET['post'] ) ) {
		$post_id = (int) $_GET['post'];
	}

	$index = (int) get_query_var( 'movies_wp_media_i' );
	if ( ! $index && isset( $_GET['movies_wp_media_i'] ) ) {
		$index = (int) $_GET['movies_wp_media_i'];
	}
	if ( ! $index && isset( $_GET['i'] ) ) {
		$index = (int) $_GET['i'];
	}
	if ( $index < 0 ) {
		$index = 0;
	}

	$current = home_url( add_query_arg( array() ) );

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( movies_wp_media_login_url( $current ) );
		exit;
	}

	// Site-wide free/paid policy (MU-plugin media-site-access.php).
	if ( function_exists( 'movies_wp_user_can_access_media' ) && ! movies_wp_user_can_access_media() ) {
		$subscribe = function_exists( 'movies_wp_media_subscribe_url' )
			? movies_wp_media_subscribe_url()
			: home_url( '/' );
		wp_safe_redirect( $subscribe );
		exit;
	}

	if ( ! function_exists( 'movies_wp_media_signed_url' ) ) {
		wp_die( esc_html__( 'Media mint plugin is not loaded.', 'movies-wp' ), 500 );
	}

	$loaded = movies_wp_media_get_sources_for_post( $post_id );
	if ( is_wp_error( $loaded ) ) {
		wp_die( esc_html( $loaded->get_error_message() ), 404 );
	}

	list( , $sources ) = $loaded;
	// Re-index numerically in case of sparse keys.
	$sources = array_values( $sources );

	if ( ! isset( $sources[ $index ] ) || ! is_array( $sources[ $index ] ) ) {
		wp_die( esc_html__( 'Source not found.', 'movies-wp' ), 404 );
	}

	$target = movies_wp_media_resolve_source_target( $sources[ $index ], $action );
	if ( '' === $target ) {
		wp_die( esc_html__( 'Empty media path for this source.', 'movies-wp' ), 404 );
	}

	// External URL: pass through (still requires login to hit this gateway).
	if ( movies_wp_media_is_external_link( $target ) ) {
		wp_redirect( esc_url_raw( $target ) ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	$type = ( 'download' === $action ) ? 'd' : 'v';
	$url  = movies_wp_media_signed_url( $target, $type );
	if ( is_wp_error( $url ) ) {
		wp_die( esc_html( $url->get_error_message() ), 500 );
	}

	nocache_headers();
	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- intentional off-site media host
	exit;
}
add_action( 'template_redirect', 'movies_wp_media_gateway_handle', 0 );

/**
 * Rewrite a stored source link into a gateway URL when it is a local path.
 * Use from templates / filters when wiring the player (Step 2).
 *
 * @param string $link     Stored link or path.
 * @param int    $post_id  Content ID.
 * @param int    $index    Source index.
 * @param string $action   play|download.
 * @return string
 */
function movies_wp_media_gateway_url_for_link( $link, $post_id, $index = 0, $action = 'play' ) {
	$link = trim( (string) $link );
	if ( '' === $link ) {
		return '';
	}
	if ( movies_wp_media_is_external_link( $link ) ) {
		// Still go through gateway so login is enforced.
		return ( 'download' === $action )
			? movies_wp_media_download_url( $post_id, $index )
			: movies_wp_media_play_url( $post_id, $index );
	}
	return ( 'download' === $action )
		? movies_wp_media_download_url( $post_id, $index )
		: movies_wp_media_play_url( $post_id, $index );
}

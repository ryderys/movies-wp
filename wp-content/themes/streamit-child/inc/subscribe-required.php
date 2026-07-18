<?php
/**
 * Subscribe-required modal helpers (play / download gate UX).
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve content used for access + plan highlighting (episodes → parent TV show).
 *
 * @param object $st_data   Movie, TV show, episode, or video object.
 * @param string $post_type Content type.
 * @return array{0: object|null, 1: string, 2: int} Content object, type, id.
 */
function streamit_child_resolve_access_content( $st_data, $post_type ) {
	$post_type = sanitize_key( (string) $post_type );

	if ( 'episode' === $post_type && $st_data && method_exists( $st_data, 'get_meta' ) ) {
		$tvshow_id = (int) $st_data->get_meta( 'tvshow_id' );
		$tvshow    = ( $tvshow_id && function_exists( 'streamit_get_tvshow' ) ) ? streamit_get_tvshow( $tvshow_id ) : null;

		if ( $tvshow ) {
			return array( $tvshow, 'tvshow', (int) $tvshow->get_id() );
		}

		return array( null, 'tvshow', $tvshow_id );
	}

	$id = ( $st_data && method_exists( $st_data, 'get_id' ) ) ? (int) $st_data->get_id() : 0;

	return array( $st_data, $post_type, $id );
}

/**
 * Subscribe / plans URL, optionally highlighting required levels.
 *
 * @param object|null $st_data   Content object (movie / tvshow / episode).
 * @param string      $post_type Content type.
 * @return string
 */
function streamit_child_get_subscribe_url( $st_data = null, $post_type = 'movie' ) {
	$url = function_exists( 'streamit_subscribe_page_url' ) ? streamit_subscribe_page_url() : home_url( '/' );

	if ( ! $st_data ) {
		return $url;
	}

	list( $content, ) = streamit_child_resolve_access_content( $st_data, $post_type );

	if ( ! $content || ! method_exists( $content, 'get_meta' ) ) {
		return $url;
	}

	$levels = $content->get_meta( '_pmp_level' );
	if ( empty( $levels ) ) {
		return $url;
	}

	if ( ! is_array( $levels ) ) {
		$levels = maybe_unserialize( $levels );
	}

	if ( ! is_array( $levels ) || empty( $levels ) ) {
		return $url;
	}

	return add_query_arg(
		'require_plan',
		implode( ',', array_map( 'intval', $levels ) ),
		$url
	);
}

/**
 * Print the subscribe-required modal once per request.
 *
 * @param object|null $st_data   Content for plan URL highlighting.
 * @param string      $post_type Content type.
 * @param string      $context   'play' or 'download' (copy only; modal is shared).
 */
function streamit_child_render_subscribe_required_modal( $st_data = null, $post_type = 'movie', $context = 'play' ) {
	static $rendered = false;

	if ( $rendered ) {
		return;
	}

	$rendered = true;

	$subscribe_url = streamit_child_get_subscribe_url( $st_data, $post_type );
	$login_url     = function_exists( 'streamit_login_page_url' ) ? streamit_login_page_url() : wp_login_url();

	$template = locate_template( 'template-parts/common/html-subscribe-required-modal.php' );
	if ( ! $template ) {
		$template = get_stylesheet_directory() . '/template-parts/common/html-subscribe-required-modal.php';
	}

	if ( ! file_exists( $template ) ) {
		return;
	}

	include $template;
}

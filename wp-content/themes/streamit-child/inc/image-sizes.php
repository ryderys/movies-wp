<?php
/**
 * Context-aware image sizes for Streamit.
 *
 * Registers display-sized variants (retina-ready) and maps each template/context
 * to the appropriate size so posters, heroes, and thumbnails stay sharp without
 * serving TMDB originals everywhere.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/** Registered size slugs (use these in templates/filters). */
const STREAMIT_CHILD_SIZE_POSTER       = 'streamit-poster';
const STREAMIT_CHILD_SIZE_POSTER_LG    = 'streamit-poster-lg';
const STREAMIT_CHILD_SIZE_HERO         = 'streamit-hero';
const STREAMIT_CHILD_SIZE_PERSON       = 'streamit-person';
const STREAMIT_CHILD_SIZE_EPISODE      = 'streamit-episode';
const STREAMIT_CHILD_SIZE_GENRE        = 'streamit-genre';
const STREAMIT_CHILD_SIZE_THUMB_STRIP  = 'streamit-thumb-strip';

/**
 * Register custom image sizes aligned to Streamit SCSS aspect ratios.
 */
add_action(
	'after_setup_theme',
	function () {
		// 2:2.8 — common_card, movie/tvshow archive cards, top-ten (~280px col @2x).
		add_image_size( STREAMIT_CHILD_SIZE_POSTER, 560, 784, true );

		// 2:2.8 — main-banner thumb nav, larger card slots (~392px @2x).
		add_image_size( STREAMIT_CHILD_SIZE_POSTER_LG, 784, 1098, true );

		// 16:9 — hero backdrops, trailer banners, full-width sliders.
		add_image_size( STREAMIT_CHILD_SIZE_HERO, 1920, 1080, true );

		// 1:1.3 — cast/person headshots.
		add_image_size( STREAMIT_CHILD_SIZE_PERSON, 520, 676, true );

		// 3:2 — episode stills, playlist cards.
		add_image_size( STREAMIT_CHILD_SIZE_EPISODE, 900, 600, true );

		// 5:3 — genre/tag landscape tiles.
		add_image_size( STREAMIT_CHILD_SIZE_GENRE, 800, 480, true );

		// 2:2.8 — vertical-banner thumb strip (~200px @2x).
		add_image_size( STREAMIT_CHILD_SIZE_THUMB_STRIP, 400, 560, true );
	},
	20
);

/**
 * High-quality JPEG output for generated subsizes.
 *
 * @param int $quality Default quality.
 * @return int
 */
add_filter(
	'jpeg_quality',
	function ( $quality ) {
		return 88;
	}
);

add_filter(
	'wp_editor_set_quality',
	function ( $quality ) {
		return 88;
	}
);

/**
 * Template-part path fragment => default image size for that view.
 *
 * @return array<string, string>
 */
function streamit_child_image_template_map() {
	return array(
		'movie/content/movie_thumbnail.php'              => STREAMIT_CHILD_SIZE_POSTER,
		'tvshow/content/tvshow_thumbnail.php'            => STREAMIT_CHILD_SIZE_POSTER,
		'video/content/video_thumbnail.php'              => STREAMIT_CHILD_SIZE_POSTER,
		'person/content/person_thumbnail.php'            => STREAMIT_CHILD_SIZE_PERSON,
		'person/content/person_single_thumbnail.php'     => STREAMIT_CHILD_SIZE_PERSON,
		'person/content/person_single_actor_history.php' => STREAMIT_CHILD_SIZE_PERSON,
		'common/html-common-card.php'                    => STREAMIT_CHILD_SIZE_POSTER,
		'common/html-common-list.php'                    => STREAMIT_CHILD_SIZE_POSTER,
		'top-ten-slider'                                 => STREAMIT_CHILD_SIZE_POSTER,
		'main-card-grid.php'                             => STREAMIT_CHILD_SIZE_POSTER,
		'main-card-slider.php'                           => STREAMIT_CHILD_SIZE_POSTER,
		'main-card-grid-landscape'                       => STREAMIT_CHILD_SIZE_GENRE,
		'main-card-slider-landscape'                     => STREAMIT_CHILD_SIZE_GENRE,
		'person-card'                                    => STREAMIT_CHILD_SIZE_PERSON,
		'episode_single_slider.php'                      => STREAMIT_CHILD_SIZE_EPISODE,
		'episode_single_season_card.php'                 => STREAMIT_CHILD_SIZE_EPISODE,
		'tv-show-season'                                 => STREAMIT_CHILD_SIZE_EPISODE,
		'movie_single_trailer.php'                       => STREAMIT_CHILD_SIZE_HERO,
		'tvshow_single_trailer.php'                      => STREAMIT_CHILD_SIZE_HERO,
		'video_single_trailer.php'                       => STREAMIT_CHILD_SIZE_HERO,
		'html-term-genre'                                => STREAMIT_CHILD_SIZE_GENRE,
		'category-slider'                                => STREAMIT_CHILD_SIZE_GENRE,
		'archive_genre_loop.php'                         => STREAMIT_CHILD_SIZE_GENRE,
		'playlist'                                       => STREAMIT_CHILD_SIZE_POSTER,
		'media-player/single/single.php'                 => STREAMIT_CHILD_SIZE_HERO,
		'episode/single/single.php'                      => STREAMIT_CHILD_SIZE_HERO,
		'simple-banner'                                  => STREAMIT_CHILD_SIZE_HERO,
		'html-tv-show-tab.php'                           => STREAMIT_CHILD_SIZE_HERO,
		'continue-watching'                              => STREAMIT_CHILD_SIZE_POSTER,
		'main-banner/html-main-banner.php'               => STREAMIT_CHILD_SIZE_HERO,
		'comment/single/single.php'                      => STREAMIT_CHILD_SIZE_POSTER,
		'html-upcoming-content.php'                      => STREAMIT_CHILD_SIZE_POSTER,
	);
}

/**
 * CSS class hints from streamit_render_image() => size slug.
 *
 * @return array<string, string>
 */
function streamit_child_image_class_map() {
	return array(
		'vertical-banner-bg-image' => STREAMIT_CHILD_SIZE_HERO,
		'video-banner-image'       => STREAMIT_CHILD_SIZE_HERO,
		'post-img'                 => STREAMIT_CHILD_SIZE_POSTER_LG,
	);
}

/**
 * Active template context stack (set around template-part includes).
 *
 * @var string[]
 */
$GLOBALS['streamit_child_image_context_stack'] = array();

/**
 * Push image size context from a template path.
 *
 * @param string $template Absolute template path.
 */
function streamit_child_push_image_context_from_template( $template ) {
	$template = wp_normalize_path( (string) $template );

	foreach ( streamit_child_image_template_map() as $fragment => $size ) {
		if ( false !== strpos( $template, wp_normalize_path( $fragment ) ) ) {
			$GLOBALS['streamit_child_image_context_stack'][] = $size;
			return;
		}
	}
}

/**
 * Pop the latest template context.
 */
function streamit_child_pop_image_context() {
	array_pop( $GLOBALS['streamit_child_image_context_stack'] );
}

add_action(
	'streamit_theme_before_template_part',
	function ( $template ) {
		streamit_child_push_image_context_from_template( $template );
	},
	10,
	1
);

add_action(
	'streamit_theme_after_template_part',
	function () {
		streamit_child_pop_image_context();
	},
	10,
	0
);

/**
 * Resolve the best registered size for an attachment in the current context.
 *
 * @param int $attachment_id Attachment ID.
 * @return string Size slug or 'full'.
 */
function streamit_child_resolve_contextual_image_size( $attachment_id ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id ) {
		return 'full';
	}

	$size = 'full';

	if ( ! empty( $GLOBALS['streamit_child_image_context_stack'] ) ) {
		$size = end( $GLOBALS['streamit_child_image_context_stack'] );
	} else {
		$size = streamit_child_detect_size_from_backtrace();
	}

	if ( 'full' === $size || ! $size ) {
		$size = streamit_child_detect_size_from_orientation( $attachment_id );
	}

	/**
	 * Filter the resolved contextual image size.
	 *
	 * @param string $size          Size slug.
	 * @param int    $attachment_id Attachment ID.
	 */
	return apply_filters( 'streamit_child_contextual_image_size', $size, $attachment_id );
}

/**
 * Detect size from the calling theme template (fallback when context stack is empty).
 *
 * @return string
 */
function streamit_child_detect_size_from_backtrace() {
	static $cache = array();

	$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 );
	$key   = '';

	foreach ( $trace as $frame ) {
		if ( empty( $frame['file'] ) ) {
			continue;
		}

		$file = wp_normalize_path( $frame['file'] );
		if ( false === strpos( $file, '/themes/streamit' ) && false === strpos( $file, '/themes/streamit-child' ) ) {
			continue;
		}

		$key = $file;
		break;
	}

	if ( '' === $key ) {
		return 'full';
	}

	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	foreach ( streamit_child_image_template_map() as $fragment => $size ) {
		if ( false !== strpos( $key, wp_normalize_path( $fragment ) ) ) {
			$cache[ $key ] = $size;
			return $size;
		}
	}

	$cache[ $key ] = 'full';
	return 'full';
}

/**
 * Pick poster vs hero when no template match exists.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function streamit_child_detect_size_from_orientation( $attachment_id ) {
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
		return STREAMIT_CHILD_SIZE_POSTER;
	}

	if ( $meta['width'] > ( $meta['height'] * 1.15 ) ) {
		return STREAMIT_CHILD_SIZE_HERO;
	}

	return STREAMIT_CHILD_SIZE_POSTER;
}

/**
 * Map streamit_render_image() args to a registered size when still set to full.
 *
 * @param array $args Render args.
 * @return array
 */
function streamit_child_filter_render_image_args( $args ) {
	$size = isset( $args['size'] ) ? (string) $args['size'] : 'full';
	if ( 'full' !== $size ) {
		return $args;
	}

	$class = isset( $args['class'] ) ? (string) $args['class'] : '';
	foreach ( streamit_child_image_class_map() as $needle => $mapped ) {
		if ( false !== strpos( $class, $needle ) ) {
			$args['size'] = $mapped;
			return $args;
		}
	}

	if ( ! empty( $GLOBALS['streamit_child_image_context_stack'] ) ) {
		$args['size'] = end( $GLOBALS['streamit_child_image_context_stack'] );
		return $args;
	}

	if ( ! empty( $args['attachment_id'] ) ) {
		$resolved = streamit_child_resolve_contextual_image_size( (int) $args['attachment_id'] );
		if ( 'full' !== $resolved ) {
			$args['size'] = $resolved;
		}
	}

	return $args;
}

add_filter( 'streamit_render_image_args', 'streamit_child_filter_render_image_args', 10, 1 );

/**
 * Redirect `full` requests to contextual registered sizes on the front end.
 *
 * @param bool|array $downsize Whether to short-circuit. False to continue.
 * @param int        $id       Attachment ID.
 * @param string     $size     Requested size.
 * @return bool|array
 */
function streamit_child_filter_image_downsize( $downsize, $id, $size ) {
	if ( false !== $downsize ) {
		return $downsize;
	}

	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}

	if ( 'full' !== $size ) {
		return false;
	}

	$mapped = streamit_child_resolve_contextual_image_size( $id );
	if ( ! $mapped || 'full' === $mapped ) {
		return false;
	}

	$result = image_downsize( $id, $mapped );
	return $result ? $result : false;
}

add_filter( 'image_downsize', 'streamit_child_filter_image_downsize', 10, 3 );

/**
 * Use w1280 instead of TMDB original during server-side image downloads.
 *
 * w1280 is TMDB's largest standard width before original — sharp enough for
 * 1920px heroes and retina poster cards while avoiding 3000px+ masters.
 *
 * Runs at priority 9, before the TMDB proxy filter at 10 in functions.php.
 *
 * @param false|array|\WP_Error $pre  Preemptive response.
 * @param array                 $args Request args.
 * @param string                $url  Request URL.
 * @return false|array|\WP_Error
 */
function streamit_child_tmdb_import_image_size( $pre, $args, $url ) {
	if ( false !== $pre ) {
		return $pre;
	}

	if ( false === strpos( $url, '/t/p/original/' ) ) {
		return $pre;
	}

	$is_tmdb = ( false !== strpos( $url, 'image.tmdb.org' ) );
	if ( ! $is_tmdb && defined( 'STREAMIT_TMDB_PROXY_HOST' ) ) {
		$is_tmdb = ( false !== strpos( $url, STREAMIT_TMDB_PROXY_HOST ) );
	}

	if ( ! $is_tmdb ) {
		return $pre;
	}

	$url = str_replace( '/t/p/original/', '/t/p/w1280/', $url );

	if ( function_exists( 'streamit_tmdb_server_proxy_url' ) ) {
		$url = streamit_tmdb_server_proxy_url( $url );
	}

	$args['timeout'] = max( (int) ( $args['timeout'] ?? 5 ), 40 );

	return wp_remote_request( $url, $args );
}

add_filter( 'pre_http_request', 'streamit_child_tmdb_import_image_size', 9, 3 );

/**
 * Regenerate custom subsizes for all attachments (run once via WP-CLI or a temporary admin hook).
 *
 * Usage: wp eval 'streamit_child_regenerate_custom_image_sizes( 50 );'
 *
 * @param int $batch Number of attachments per batch (0 = all, use small batches on large sites).
 * @return int Number processed in this run.
 */
function streamit_child_regenerate_custom_image_sizes( $batch = 0 ) {
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$query_args = array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'ASC',
	);

	if ( $batch > 0 ) {
		$query_args['posts_per_page'] = $batch;
	} else {
		$query_args['posts_per_page'] = -1;
	}

	$ids = get_posts( $query_args );
	$count = 0;

	foreach ( $ids as $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			continue;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
		if ( ! is_wp_error( $metadata ) && ! empty( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
			++$count;
		}
	}

	return $count;
}

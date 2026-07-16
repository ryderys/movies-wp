<?php
/**
 * Subtitle support for movies and episodes.
 *
 * Streamit has no native subtitle field. This adds a child-owned `_subtitles`
 * meta (array of { label, srclang, url, default }) with:
 *  - an admin repeater mounted in the Sources tab (movie + episode edit),
 *  - persistence via the plugin's own Update button (JS `*_form_data` filter
 *    injects the payload; the PHP meta-controller filter writes the meta),
 *  - Plyr caption tracks injected into the player,
 *  - a subtitle list in the download modal.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalize raw `_subtitles` meta into a clean list for output.
 *
 * @param mixed $raw Meta value (array or serialized).
 * @return array<int, array{label: string, srclang: string, url: string, default: bool}>
 */
function streamit_child_normalize_subtitles( $raw ) {
	if ( is_string( $raw ) ) {
		$raw = maybe_unserialize( $raw );
	}

	if ( ! is_array( $raw ) ) {
		return array();
	}

	$out         = array();
	$has_default = false;

	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$url = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
		if ( '' === $url ) {
			continue;
		}

		$srclang = isset( $row['srclang'] ) ? strtolower( trim( (string) $row['srclang'] ) ) : '';
		$label   = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';

		if ( '' === $label ) {
			$label = '' !== $srclang ? strtoupper( $srclang ) : __( 'زیرنویس', 'streamit' );
		}

		$is_default = ! empty( $row['default'] ) && 'false' !== $row['default'];
		if ( $is_default && $has_default ) {
			$is_default = false; // Only one default track is allowed.
		}
		if ( $is_default ) {
			$has_default = true;
		}

		$out[] = array(
			'label'   => $label,
			'srclang' => $srclang,
			'url'     => $url,
			'default' => $is_default,
		);
	}

	return $out;
}

/**
 * Sanitize a raw subtitles payload received on save.
 *
 * @param mixed $raw Payload from the save request.
 * @return array<int, array{label: string, srclang: string, url: string, default: int}>
 */
function streamit_child_sanitize_subtitles( $raw ) {
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$out = array();

	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$url = isset( $row['url'] ) ? esc_url_raw( trim( (string) $row['url'] ) ) : '';
		if ( '' === $url ) {
			continue;
		}

		$srclang = isset( $row['srclang'] ) ? preg_replace( '/[^a-zA-Z-]/', '', (string) $row['srclang'] ) : '';
		$srclang = strtolower( substr( (string) $srclang, 0, 10 ) );

		$default = ( ! empty( $row['default'] ) && 'false' !== $row['default'] ) ? 1 : 0;

		$out[] = array(
			'label'   => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
			'srclang' => $srclang,
			'url'     => $url,
			'default' => $default,
		);
	}

	return $out;
}

/**
 * Read normalized subtitles by post ID + type (frontend + admin prefill).
 *
 * @param int    $post_id   Post ID.
 * @param string $post_type movie|episode.
 * @return array
 */
function streamit_child_get_subtitles_by_id( $post_id, $post_type ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		return array();
	}

	$meta_type = ( 'episode' === $post_type ) ? 'streamit_episode' : 'streamit_movie';
	$raw       = get_metadata( $meta_type, $post_id, '_subtitles', true );

	return streamit_child_normalize_subtitles( $raw );
}

/**
 * Read normalized subtitles from a Streamit content object.
 *
 * @param object $st_data Streamit content object.
 * @return array
 */
function streamit_child_get_subtitles( $st_data ) {
	if ( ! is_object( $st_data ) || ! method_exists( $st_data, 'get_meta' ) ) {
		return array();
	}

	return streamit_child_normalize_subtitles( $st_data->get_meta( '_subtitles' ) );
}

/**
 * Inject the `_subtitles` payload into the movie/episode meta write.
 *
 * The plugin's group-meta loop persists any key present in $meta_data, and
 * $data holds every submitted request param (see the controllers'
 * $request->get_params()), so a value added client-side arrives here intact.
 *
 * @param array $meta_data Meta payload about to be saved.
 * @param int   $post_id   Post ID.
 * @param array $data      Full request params.
 * @return array
 */
function streamit_child_add_subtitles_meta( $meta_data, $post_id = 0, $data = array() ) {
	if ( is_array( $data ) && array_key_exists( '_subtitles', $data ) ) {
		$meta_data['_subtitles'] = streamit_child_sanitize_subtitles( $data['_subtitles'] );
	}

	return $meta_data;
}
add_filter( 'streamit_add_movie_meta_controller', 'streamit_child_add_subtitles_meta', 10, 3 );
add_filter( 'streamit_update_movie_meta_controller', 'streamit_child_add_subtitles_meta', 10, 3 );
add_filter( 'streamit_add_episode_meta_controller', 'streamit_child_add_subtitles_meta', 10, 3 );
add_filter( 'streamit_update_episode_meta_controller', 'streamit_child_add_subtitles_meta', 10, 3 );

/**
 * Build the <track> markup for a set of normalized subtitles.
 *
 * @param array $subs Normalized subtitles.
 * @return string
 */
function streamit_child_build_subtitle_tracks( $subs ) {
	$tracks = '';

	foreach ( $subs as $sub ) {
		$tracks .= sprintf(
			'<track kind="captions" src="%s" srclang="%s" label="%s"%s />',
			esc_url( $sub['url'] ),
			esc_attr( '' !== $sub['srclang'] ? $sub['srclang'] : 'und' ),
			esc_attr( $sub['label'] ),
			! empty( $sub['default'] ) ? ' default' : ''
		);
	}

	return $tracks;
}

/**
 * Inject caption <track> elements into the rendered player HTML.
 *
 * Runs on the non-HLS player HTML (self-hosted / direct URL <video>). Embeds
 * (YouTube/Vimeo/iframe) have no <video> element and are left untouched.
 *
 * @param string $html Player HTML.
 * @return string
 */
function streamit_child_inject_player_tracks( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}

	if ( ! is_singular( array( 'movie', 'episode' ) ) ) {
		return $html;
	}

	$pos = strpos( $html, '</video>' );
	if ( false === $pos ) {
		return $html; // Embed player — nothing to caption.
	}

	$obj = get_queried_object();
	if ( ! $obj || empty( $obj->ID ) || empty( $obj->post_type ) ) {
		return $html;
	}

	$subs = streamit_child_get_subtitles_by_id( $obj->ID, $obj->post_type );
	if ( empty( $subs ) ) {
		return $html;
	}

	$tracks = streamit_child_build_subtitle_tracks( $subs );

	return substr( $html, 0, $pos ) . $tracks . substr( $html, $pos );
}
add_filter( 'streamit_media_player_html', 'streamit_child_inject_player_tracks', 20 );
add_filter( 'streamit_episode_player_html', 'streamit_child_inject_player_tracks', 20 );

/**
 * Render the subtitle repeater inside the Sources tab (movie/episode edit).
 *
 * @param string $post_type movie|episode.
 */
function streamit_child_render_subtitles_admin( $post_type ) {
	$post_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$rows    = $post_id ? streamit_child_get_subtitles_by_id( $post_id, $post_type ) : array();
	?>
	<div id="streamit-child-subtitles" class="streamit-child-subtitles" data-post-type="<?php echo esc_attr( $post_type ); ?>">
		<div class="stc-sub-head">
			<h3><?php esc_html_e( 'زیرنویس‌ها', 'streamit' ); ?></h3>
			<p class="stc-sub-intro"><?php esc_html_e( 'فایل‌های زیرنویس (VTT یا SRT) را برای پخش‌کننده و بخش دانلود اضافه کنید. هر زبان یک ردیف.', 'streamit' ); ?></p>
		</div>

		<div class="stc-sub-rows">
			<?php foreach ( $rows as $row ) : ?>
				<div class="stc-sub-row">
					<div class="stc-sub-field">
						<label><?php esc_html_e( 'برچسب (نمایش در پخش‌کننده)', 'streamit' ); ?></label>
						<input type="text" class="stc-sub-label" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'مثلاً فارسی', 'streamit' ); ?>" />
					</div>
					<div class="stc-sub-field stc-sub-lang">
						<label><?php esc_html_e( 'کد زبان', 'streamit' ); ?></label>
						<input type="text" class="stc-sub-srclang" value="<?php echo esc_attr( $row['srclang'] ); ?>" placeholder="fa" maxlength="10" />
					</div>
					<div class="stc-sub-field stc-sub-url-field">
						<label><?php esc_html_e( 'آدرس فایل زیرنویس (VTT/SRT)', 'streamit' ); ?></label>
						<div class="stc-sub-url-wrap">
							<input type="url" class="stc-sub-url" value="<?php echo esc_attr( $row['url'] ); ?>" placeholder="https://example.com/fa.vtt" />
							<button type="button" class="button stc-sub-media"><?php esc_html_e( 'انتخاب فایل', 'streamit' ); ?></button>
						</div>
					</div>
					<div class="stc-sub-field stc-sub-default-field">
						<label class="stc-sub-default-label">
							<input type="checkbox" class="stc-sub-default" <?php checked( ! empty( $row['default'] ) ); ?> />
							<?php esc_html_e( 'پیش‌فرض', 'streamit' ); ?>
						</label>
						<button type="button" class="button-link-delete stc-sub-remove"><?php esc_html_e( 'حذف', 'streamit' ); ?></button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<button type="button" class="button stc-sub-add"><?php esc_html_e( 'افزودن زیرنویس', 'streamit' ); ?></button>

		<template id="stc-sub-row-template">
			<div class="stc-sub-row">
				<div class="stc-sub-field">
					<label><?php esc_html_e( 'برچسب (نمایش در پخش‌کننده)', 'streamit' ); ?></label>
					<input type="text" class="stc-sub-label" placeholder="<?php esc_attr_e( 'مثلاً فارسی', 'streamit' ); ?>" />
				</div>
				<div class="stc-sub-field stc-sub-lang">
					<label><?php esc_html_e( 'کد زبان', 'streamit' ); ?></label>
					<input type="text" class="stc-sub-srclang" placeholder="fa" maxlength="10" />
				</div>
				<div class="stc-sub-field stc-sub-url-field">
					<label><?php esc_html_e( 'آدرس فایل زیرنویس (VTT/SRT)', 'streamit' ); ?></label>
					<div class="stc-sub-url-wrap">
						<input type="url" class="stc-sub-url" placeholder="https://example.com/fa.vtt" />
						<button type="button" class="button stc-sub-media"><?php esc_html_e( 'انتخاب فایل', 'streamit' ); ?></button>
					</div>
				</div>
				<div class="stc-sub-field stc-sub-default-field">
					<label class="stc-sub-default-label">
						<input type="checkbox" class="stc-sub-default" />
						<?php esc_html_e( 'پیش‌فرض', 'streamit' ); ?>
					</label>
					<button type="button" class="button-link-delete stc-sub-remove"><?php esc_html_e( 'حذف', 'streamit' ); ?></button>
				</div>
			</div>
		</template>
	</div>
	<?php
}
add_action(
	'streamit_options_movie_source',
	function () {
		streamit_child_render_subtitles_admin( 'movie' );
	}
);
add_action(
	'streamit_options_episode_source',
	function () {
		streamit_child_render_subtitles_admin( 'episode' );
	}
);

/**
 * Enqueue the subtitle admin UI on movie/episode edit screens.
 *
 * @param string $hook Current admin page hook.
 */
function streamit_child_enqueue_subtitles_admin( $hook ) {
	$screens = array(
		'admin_page_streamit-edit-movie',
		'admin_page_streamit-add-movie',
		'admin_page_streamit-edit-tvshow-episode',
		'admin_page_streamit-add-tvshow-episode',
	);

	if ( ! in_array( $hook, $screens, true ) ) {
		return;
	}

	$post_type = ( false !== strpos( $hook, 'episode' ) ) ? 'episode' : 'movie';

	$css_path = get_stylesheet_directory() . '/assets/css/admin-subtitles.css';
	$js_path  = get_stylesheet_directory() . '/assets/js/admin-subtitles.js';

	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'streamit-child-admin-subtitles',
			get_stylesheet_directory_uri() . '/assets/css/admin-subtitles.css',
			array(),
			(string) filemtime( $css_path )
		);
	}

	wp_enqueue_media();

	wp_enqueue_script(
		'streamit-child-admin-subtitles',
		get_stylesheet_directory_uri() . '/assets/js/admin-subtitles.js',
		array( 'jquery', 'wp-hooks' ),
		file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0',
		true
	);

	wp_localize_script(
		'streamit-child-admin-subtitles',
		'streamitChildSubtitles',
		array(
			'postType'    => $post_type,
			'formFilter'  => ( 'episode' === $post_type ) ? 'episode_form_data' : 'movie_form_data',
			'mediaTitle'  => 'انتخاب فایل زیرنویس',
			'mediaButton' => 'استفاده از این فایل',
		)
	);
}
add_action( 'admin_enqueue_scripts', 'streamit_child_enqueue_subtitles_admin' );

/**
 * Frontend styling for the subtitle section of the download modal.
 */
function streamit_child_enqueue_subtitles_modal_css() {
	if ( ! is_singular( array( 'movie', 'episode' ) ) ) {
		return;
	}

	$css_path = get_stylesheet_directory() . '/assets/css/subtitles-modal.css';
	if ( ! file_exists( $css_path ) ) {
		return;
	}

	wp_enqueue_style(
		'streamit-child-subtitles-modal',
		get_stylesheet_directory_uri() . '/assets/css/subtitles-modal.css',
		array(),
		(string) filemtime( $css_path )
	);
}
add_action( 'wp_enqueue_scripts', 'streamit_child_enqueue_subtitles_modal_css', 100 );

/**
 * Whether the download modal should be available (video sources and/or subtitles).
 *
 * @param object $st_data   Streamit content object.
 * @param string $meta_key  _source (movie) or _sources (episode).
 * @return bool
 */
function streamit_child_has_download_modal_content( $st_data, $meta_key = '_source' ) {
	if ( function_exists( 'streamit_child_has_downloadable_sources' )
		&& streamit_child_has_downloadable_sources( $st_data, $meta_key ) ) {
		return true;
	}

	return ! empty( streamit_child_get_subtitles( $st_data ) );
}

/**
 * Render the subtitle download list for the modal body.
 *
 * @param array $subs Normalized subtitles.
 */
function streamit_child_render_subtitle_download_section( $subs ) {
	if ( empty( $subs ) ) {
		return;
	}
	?>
	<div class="stc-subtitle-section">
		<h6 class="stc-subtitle-title"><?php esc_html_e( 'دانلود زیرنویس', 'streamit' ); ?></h6>
		<ul class="stc-subtitle-list">
			<?php foreach ( $subs as $sub ) : ?>
				<li>
					<div class="stc-subtitle-row">
						<div class="stc-subtitle-meta">
							<span class="stc-subtitle-label"><?php echo esc_html( $sub['label'] ); ?></span>
							<?php if ( ! empty( $sub['srclang'] ) ) : ?>
								<span class="stc-subtitle-lang"><?php echo esc_html( strtoupper( $sub['srclang'] ) ); ?></span>
							<?php endif; ?>
						</div>
						<div class="stc-subtitle-download stc-download-action">
							<span class="stc-download-icon" aria-hidden="true">
								<?php echo st_get_icon( 'download-2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
							<a href="<?php echo esc_url( $sub['url'] ); ?>" class="stc-download-btn link-primary" download>
								<?php esc_html_e( 'دانلود', 'streamit' ); ?>
							</a>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}

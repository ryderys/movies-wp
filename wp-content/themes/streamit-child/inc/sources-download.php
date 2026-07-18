<?php
/**
 * Multi-quality sources + download modal helpers.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalize a sources array for the download modal.
 *
 * Falls back to the playback URL (link) when download_content is empty.
 * Optional UX fields: file_size (حجم), name used as Encoder.
 *
 * @param mixed $sources Raw _source / _sources meta.
 * @return array<int, array{quality: string, language: string, download_content: string, name: string, file_size: string, encoder: string}>
 */
function streamit_child_get_downloadable_sources( $sources ) {
	if ( ! is_array( $sources ) || empty( $sources ) ) {
		return array();
	}

	$normalized = array();

	foreach ( $sources as $source ) {
		if ( ! is_array( $source ) ) {
			continue;
		}

		$quality   = isset( $source['quality'] ) ? trim( (string) $source['quality'] ) : '';
		$language  = isset( $source['language'] ) ? trim( (string) $source['language'] ) : '';
		$download  = isset( $source['download_content'] ) ? trim( (string) $source['download_content'] ) : '';
		$link      = isset( $source['link'] ) ? trim( (string) $source['link'] ) : '';
		$name      = isset( $source['name'] ) ? trim( (string) $source['name'] ) : '';
		$file_size = isset( $source['file_size'] ) ? trim( (string) $source['file_size'] ) : '';

		if ( '' === $download && '' !== $link ) {
			$download = $link;
		}

		if ( '' === $quality || '' === $language || '' === $download ) {
			continue;
		}

		$normalized[] = array(
			'quality'          => $quality,
			'language'         => $language,
			'download_content' => $download,
			'name'             => $name,
			'file_size'        => $file_size,
			'encoder'          => $name, // Admin "Name" field doubles as Encoder.
		);
	}

	return $normalized;
}

/**
 * Render optional download-row meta (size + encoder) for the modal.
 *
 * @param array<string, string> $source Normalized downloadable source.
 */
function streamit_child_render_download_source_meta( $source ) {
	$file_size = isset( $source['file_size'] ) ? trim( (string) $source['file_size'] ) : '';
	$encoder   = isset( $source['encoder'] ) ? trim( (string) $source['encoder'] ) : '';

	if ( '' === $file_size && '' === $encoder ) {
		return;
	}
	?>
	<ul class="stc-download-meta list-unstyled m-0 p-0">
		<?php if ( '' !== $file_size ) : ?>
			<li>
				<span class="stc-download-meta__label"><?php esc_html_e( 'حجم', 'streamit' ); ?></span>
				<span class="stc-download-meta__value"><?php echo esc_html( $file_size ); ?></span>
			</li>
		<?php endif; ?>
		<?php if ( '' !== $encoder ) : ?>
			<li>
				<span class="stc-download-meta__label"><?php esc_html_e( 'Encoder', 'streamit' ); ?></span>
				<span class="stc-download-meta__value"><?php echo esc_html( $encoder ); ?></span>
			</li>
		<?php endif; ?>
	</ul>
	<?php
}

/**
 * @param object $st_data Streamit content object.
 * @param string $meta_key Meta key: _source (movie) or _sources (episode).
 */
function streamit_child_has_downloadable_sources( $st_data, $meta_key = '_source' ) {
	if ( ! $st_data || ! is_object( $st_data ) || ! method_exists( $st_data, 'get_meta' ) ) {
		return false;
	}

	$sources = $st_data->get_meta( $meta_key );

	return ! empty( streamit_child_get_downloadable_sources( $sources ) );
}

/**
 * Whether the current user may download this movie or episode.
 *
 * Movies use their own access meta. Episodes inherit the parent TV show's access
 * (same rule as the episode player).
 *
 * @param object      $st_data  Streamit movie or episode object.
 * @param string|null $post_type Optional. 'movie' or 'episode'. Inferred from $st_data when omitted.
 * @param int|null    $user_id   Optional. Defaults to current user.
 * @return bool
 */
function streamit_child_user_can_download( $st_data, $post_type = null, $user_id = null ) {
	if ( ! $st_data || ! is_object( $st_data ) || ! method_exists( $st_data, 'get_id' ) ) {
		return false;
	}

	if ( ! function_exists( 'streamit_user_has_stream_access' ) ) {
		return true;
	}

	$user_id = null !== $user_id ? (int) $user_id : get_current_user_id();

	if ( null === $post_type && method_exists( $st_data, 'get_post_type' ) ) {
		$post_type = $st_data->get_post_type();
	}

	$post_type = sanitize_key( (string) $post_type );

	if ( 'episode' === $post_type ) {
		$tvshow_id = 0;
		if ( method_exists( $st_data, 'get_meta' ) ) {
			$tvshow_id = (int) $st_data->get_meta( 'tvshow_id' );
		}

		if ( $tvshow_id <= 0 ) {
			return false;
		}

		return (bool) streamit_user_has_stream_access( $tvshow_id, 'tvshow', $user_id );
	}

	$check_type = in_array( $post_type, array( 'movie', 'video' ), true ) ? $post_type : 'movie';

	return (bool) streamit_user_has_stream_access( (int) $st_data->get_id(), $check_type, $user_id );
}

/**
 * On save: autofill download URL + sanitize optional file_size.
 *
 * @param array<string, mixed> $meta_data Meta payload.
 * @return array<string, mixed>
 */
function streamit_child_autofill_source_download_urls( $meta_data ) {
	foreach ( array( '_source', '_sources' ) as $meta_key ) {
		if ( empty( $meta_data[ $meta_key ] ) || ! is_array( $meta_data[ $meta_key ] ) ) {
			continue;
		}

		foreach ( $meta_data[ $meta_key ] as $index => $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}

			$link     = isset( $source['link'] ) ? trim( (string) $source['link'] ) : '';
			$download = isset( $source['download_content'] ) ? trim( (string) $source['download_content'] ) : '';

			if ( '' === $download && '' !== $link ) {
				$meta_data[ $meta_key ][ $index ]['download_content'] = $link;
			}

			$file_size = isset( $source['file_size'] ) ? sanitize_text_field( trim( (string) $source['file_size'] ) ) : '';
			$meta_data[ $meta_key ][ $index ]['file_size'] = $file_size;
		}
	}

	return $meta_data;
}

add_filter( 'streamit_add_movie_meta_controller', 'streamit_child_autofill_source_download_urls', 10, 1 );
add_filter( 'streamit_update_movie_meta_controller', 'streamit_child_autofill_source_download_urls', 10, 1 );
add_filter( 'streamit_add_episode_meta_controller', 'streamit_child_autofill_source_download_urls', 10, 1 );
add_filter( 'streamit_update_episode_meta_controller', 'streamit_child_autofill_source_download_urls', 10, 1 );

/**
 * Admin guide on movie / episode edit screens.
 */
function streamit_child_enqueue_sources_admin_guide( $hook ) {
	$screens = array(
		'admin_page_streamit-edit-movie',
		'admin_page_streamit-add-movie',
		'admin_page_streamit-edit-tvshow-episode',
		'admin_page_streamit-add-tvshow-episode',
	);

	if ( ! in_array( $hook, $screens, true ) ) {
		return;
	}

	$css_path = get_stylesheet_directory() . '/assets/css/admin-sources-guide.css';
	$js_path  = get_stylesheet_directory() . '/assets/js/admin-sources-guide.js';

	// Always enqueue: guide copy is Persian regardless of admin locale (is_rtl).
	wp_enqueue_style(
		'streamit-child-admin-sources-guide',
		get_stylesheet_directory_uri() . '/assets/css/admin-sources-guide.css',
		array(),
		file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0'
	);

	$url_note_css = get_stylesheet_directory() . '/assets/css/admin-sources-url-note.css';
	if ( file_exists( $url_note_css ) ) {
		wp_enqueue_style(
			'streamit-child-sources-url-note',
			get_stylesheet_directory_uri() . '/assets/css/admin-sources-url-note.css',
			array( 'streamit-child-admin-sources-guide' ),
			(string) filemtime( $url_note_css )
		);
	}

	$is_episode = false !== strpos( $hook, 'episode' );

	wp_enqueue_script(
		'streamit-child-admin-sources-guide',
		get_stylesheet_directory_uri() . '/assets/js/admin-sources-guide.js',
		array( 'jquery' ),
		file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0',
		true
	);

	wp_localize_script(
		'streamit-child-admin-sources-guide',
		'streamitChildSourcesGuide',
		array(
			'isEpisode'   => $is_episode,
			'tabSelector' => $is_episode ? '#episode_source_tab' : '#movie_sources_tab',
			'tabsList'    => $is_episode ? '.episode_meta_tabs' : '.movie_meta_tabs',
			'title'       => 'پخش و دانلود چند کیفیت',
			'intro'       => 'برای هر کیفیت (۱۰۸۰p، ۷۲۰p و …) یک ردیف در تب «منابع» اضافه کنید. فیلد آدرس در تب «عمومی» فقط لینک پخش اصلی است.',
			'step1'       => 'تب «منابع» را باز کنید و روی «افزودن منبع» کلیک کنید.',
			'step2'       => 'آدرس ویدیو، کیفیت (مثلاً BluRay 1080p)، زبان، نام/انکودر (مثلاً YIFY) و در صورت نیاز حجم را وارد کنید.',
			'step3'       => 'لینک دانلود اختیاری است — اگر خالی بماند، همان آدرس پخش استفاده می‌شود.',
			'step4'       => $is_episode
				? 'روی «به‌روزرسانی قسمت» کلیک کنید. بازدیدکنندگان منوی کیفیت در پخش‌کننده و مودال دانلود می‌بینند.'
				: 'روی «به‌روزرسانی فیلم» کلیک کنید. بازدیدکنندگان منوی کیفیت در پخش‌کننده و مودال دانلود می‌بینند.',
			'goToTab'     => 'رفتن به تب منابع',
			'dismiss'     => 'بستن',
			'urlNoteShort'  => 'آدرس فیلم = پخش در پخش‌کننده · آدرس دانلود = فایل قابل ذخیره (اختیاری؛ اگر خالی باشد همان آدرس پخش استفاده می‌شود).',
			'urlNoteTitle'  => 'تفاوت دو فیلد آدرس',
			'urlNotePlayback' => 'آدرس فیلم: لینک پخش آن کیفیت (مثلاً m3u8 یا mp4) — در منوی کیفیت پخش‌کننده استفاده می‌شود.',
			'urlNoteDownload' => 'آدرس دانلود: لینک مستقیم فایل برای دکمه دانلود — می‌تواند با لینک پخش متفاوت باشد.',
			'urlNoteOptional' => 'اگر آدرس دانلود خالی بماند، همان آدرس فیلم برای دانلود هم استفاده می‌شود.',
		)
	);

	streamit_child_enqueue_sources_extra_fields( $hook, $is_episode );
}

/**
 * Inject optional «حجم» field into each Sources row and persist via form_data filter.
 *
 * @param string $hook       Admin page hook.
 * @param bool   $is_episode Whether this is an episode screen.
 */
function streamit_child_enqueue_sources_extra_fields( $hook, $is_episode ) {
	$js_path  = get_stylesheet_directory() . '/assets/js/admin-sources-extra.js';
	$css_path = get_stylesheet_directory() . '/assets/css/admin-sources-extra.css';

	if ( ! file_exists( $js_path ) ) {
		return;
	}

	if ( file_exists( $css_path ) && is_rtl() ) {
		wp_enqueue_style(
			'streamit-child-admin-sources-extra',
			get_stylesheet_directory_uri() . '/assets/css/admin-sources-extra.css',
			array(),
			(string) filemtime( $css_path )
		);
	}

	wp_enqueue_script(
		'streamit-child-admin-sources-extra',
		get_stylesheet_directory_uri() . '/assets/js/admin-sources-extra.js',
		array( 'jquery', 'wp-hooks' ),
		(string) filemtime( $js_path ),
		true
	);

	$post_id   = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$file_sizes = array();

	if ( $post_id ) {
		$meta_type = $is_episode ? 'streamit_episode' : 'streamit_movie';
		$meta_key  = $is_episode ? '_sources' : '_source';
		$raw       = get_metadata( $meta_type, $post_id, $meta_key, true );

		if ( is_array( $raw ) ) {
			foreach ( $raw as $source ) {
				$file_sizes[] = ( is_array( $source ) && isset( $source['file_size'] ) )
					? (string) $source['file_size']
					: '';
			}
		}
	}

	wp_localize_script(
		'streamit-child-admin-sources-extra',
		'streamitChildSourcesExtra',
		array(
			'isEpisode'   => $is_episode,
			'formFilter'  => $is_episode ? 'episode_form_data' : 'movie_form_data',
			'sourcesKey'  => $is_episode ? '_sources' : '_source',
			'fileSizes'   => $file_sizes,
			'label'       => 'حجم',
			'placeholder' => 'مثلاً 2.1 GB',
		)
	);
}
add_action( 'admin_enqueue_scripts', 'streamit_child_enqueue_sources_admin_guide' );

/**
 * RTL + layout styling for the download modal.
 *
 * Loaded on all frontend pages (same pattern as search-modal.css). Streamit
 * movie/episode singles are not WP post types, so is_singular() cannot gate this.
 */
function streamit_child_enqueue_download_modal_rtl() {
	if ( is_admin() ) {
		return;
	}

	$css_path = get_stylesheet_directory() . '/assets/css/download-modal-rtl.css';
	if ( ! file_exists( $css_path ) ) {
		return;
	}

	wp_enqueue_style(
		'streamit-child-download-modal-rtl',
		get_stylesheet_directory_uri() . '/assets/css/download-modal-rtl.css',
		array(),
		(string) filemtime( $css_path )
	);
}
add_action( 'wp_enqueue_scripts', 'streamit_child_enqueue_download_modal_rtl', 100 );

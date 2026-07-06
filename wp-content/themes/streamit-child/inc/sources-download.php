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
 *
 * @param mixed $sources Raw _source / _sources meta.
 * @return array<int, array{quality: string, language: string, download_content: string, name: string}>
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

		$quality  = isset( $source['quality'] ) ? trim( (string) $source['quality'] ) : '';
		$language = isset( $source['language'] ) ? trim( (string) $source['language'] ) : '';
		$download = isset( $source['download_content'] ) ? trim( (string) $source['download_content'] ) : '';
		$link     = isset( $source['link'] ) ? trim( (string) $source['link'] ) : '';
		$name     = isset( $source['name'] ) ? trim( (string) $source['name'] ) : '';

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
		);
	}

	return $normalized;
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
 * On save: copy playback link into download_content when download URL is omitted.
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

	wp_enqueue_style(
		'streamit-child-admin-vazirmatn',
		'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600&display=swap',
		array(),
		null
	);

	$css_path = get_stylesheet_directory() . '/assets/css/admin-sources-guide.css';
	$js_path  = get_stylesheet_directory() . '/assets/js/admin-sources-guide.js';

	wp_enqueue_style(
		'streamit-child-admin-sources-guide',
		get_stylesheet_directory_uri() . '/assets/css/admin-sources-guide.css',
		array(),
		file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0'
	);

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
			'step2'       => 'آدرس ویدیو (پخش)، کیفیت (مثلاً ۱۰۸۰p) و زبان (مثلاً فارسی) را وارد کنید.',
			'step3'       => 'لینک دانلود اختیاری است — اگر خالی بماند، همان آدرس پخش استفاده می‌شود.',
			'step4'       => $is_episode
				? 'روی «به‌روزرسانی قسمت» کلیک کنید. بازدیدکنندگان منوی کیفیت در پخش‌کننده و مودال دانلود می‌بینند.'
				: 'روی «به‌روزرسانی فیلم» کلیک کنید. بازدیدکنندگان منوی کیفیت در پخش‌کننده و مودال دانلود می‌بینند.',
			'goToTab'     => 'رفتن به تب منابع',
			'dismiss'     => 'بستن',
		)
	);
}
add_action( 'admin_enqueue_scripts', 'streamit_child_enqueue_sources_admin_guide' );

/**
 * RTL styling for frontend download quality modal (child theme override only).
 */
function streamit_child_enqueue_download_modal_rtl() {
	if ( ! is_singular( array( 'movie', 'episode' ) ) ) {
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

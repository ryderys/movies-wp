<?php
/**
 * Admin guide on Edit TV Show: shortcuts to seasons/episodes workflow.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue TV show episodes guide on edit screen only.
 *
 * @param string $hook Current admin page hook.
 */
function streamit_child_enqueue_tvshow_episodes_guide( $hook ) {
	if ( 'admin_page_streamit-edit-tvshow' !== $hook ) {
		return;
	}

	$tvshow_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	if ( ! $tvshow_id ) {
		return;
	}

	$css_path = get_stylesheet_directory() . '/assets/css/admin-sources-guide.css';
	$js_path  = get_stylesheet_directory() . '/assets/js/admin-tvshow-episodes-guide.js';

	wp_enqueue_style(
		'streamit-child-tvshow-episodes-guide',
		get_stylesheet_directory_uri() . '/assets/css/admin-sources-guide.css',
		array(),
		file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0'
	);

	wp_enqueue_script(
		'streamit-child-tvshow-episodes-guide',
		get_stylesheet_directory_uri() . '/assets/js/admin-tvshow-episodes-guide.js',
		array( 'jquery' ),
		file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0',
		true
	);

	wp_localize_script(
		'streamit-child-tvshow-episodes-guide',
		'streamitChildTvshowGuide',
		array(
			'tvshowId'        => $tvshow_id,
			'tabSelector'     => '#tvshow_seasons_and_episodes_tab',
			'tabsList'        => '.tvshow_meta_tabs',
			'episodesListUrl' => admin_url( 'admin.php?page=streamit-tvshow-episode&tvshow_id=' . $tvshow_id ),
			'addEpisodeUrl'   => admin_url( 'admin.php?page=streamit-add-tvshow-episode' ),
			'title'           => 'مدیریت فصل‌ها و قسمت‌ها',
			'intro'           => 'تب «فصل‌ها و قسمت‌ها» برای ساخت فصل و اتصال قسمت‌های موجود است. برای ویرایش لینک پخش، منابع و تصویر هر قسمت، از لیست قسمت‌های همین سریال استفاده کنید.',
			'step1'           => 'قسمت‌ها را از منوی «سریال‌ها ← قسمت‌ها» بسازید یا وارد کنید.',
			'step2'           => 'در تب «فصل‌ها و قسمت‌ها» فصل اضافه کنید و قسمت‌ها را به هر فصل وصل کنید.',
			'step3'           => 'روی «به‌روزرسانی سریال» کلیک کنید تا اتصال قسمت‌ها ذخیره شود.',
			'step4'           => 'برای لینک پخش و دانلود چندکیفیت، هر قسمت را جداگانه ویرایش کنید (تب «منابع»).',
			'goToSeasonsTab'  => 'رفتن به تب فصل‌ها و قسمت‌ها',
			'manageEpisodes'  => 'مدیریت قسمت‌های این سریال',
			'addEpisode'      => 'افزودن قسمت جدید',
			'dismiss'         => 'بستن',
		)
	);
}
add_action( 'admin_enqueue_scripts', 'streamit_child_enqueue_tvshow_episodes_guide' );

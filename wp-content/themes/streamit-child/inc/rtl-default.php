<?php
/**
 * Force RTL as the site default layout direction.
 *
 * Streamit stores layout mode in Redux (rtl_switcher, default LTR) and only
 * applies RTL SCSS under [dir="rtl"]. Cached pages can also ship the wrong dir=
 * while the browser cookie still says RTL — client sync fixes that mismatch.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the active layout direction for the current request.
 *
 * Honors an explicit LTR choice saved in the theme cookie; otherwise RTL.
 *
 * @return string "rtl" or "ltr"
 */
function streamit_child_get_layout_direction() {
	if ( is_admin() ) {
		return is_rtl() ? 'rtl' : 'ltr';
	}

	if ( isset( $_COOKIE['theme_scheme_direction'] ) ) {
		$cookie = sanitize_text_field( wp_unslash( $_COOKIE['theme_scheme_direction'] ) );
		if ( 'ltr' === $cookie || 'rtl' === $cookie ) {
			return $cookie;
		}
	}

	return 'rtl';
}

/**
 * Keep Streamit's in-memory option aligned with RTL default.
 *
 * @param mixed $options Redux option value.
 * @return array
 */
function streamit_child_default_rtl_options( $options ) {
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$options['rtl_switcher'] = 'rtl';

	return $options;
}
add_filter( 'option_streamit_options', 'streamit_child_default_rtl_options' );

/**
 * Override Streamit language_attributes output with correct direction logic.
 *
 * @param string $output Existing language attributes.
 * @return string
 */
function streamit_child_rtl_language_attributes( $output ) {
	if ( is_admin() ) {
		return $output;
	}

	$dir  = streamit_child_get_layout_direction();
	$lang = get_bloginfo( 'language' );

	return sprintf( 'dir="%s" lang="%s"', esc_attr( $dir ), esc_attr( $lang ) );
}
add_filter( 'language_attributes', 'streamit_child_rtl_language_attributes', 20 );

/**
 * Use Persian locale on the front end so WordPress adds the rtl body class.
 *
 * @param string $locale Active locale.
 * @return string
 */
function streamit_child_frontend_locale( $locale ) {
	if ( is_admin() ) {
		return $locale;
	}

	return 'fa_IR';
}
add_filter( 'determine_locale', 'streamit_child_frontend_locale' );

/**
 * Add RTL body classes used by Streamit / Bootstrap overrides.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function streamit_child_rtl_body_classes( $classes ) {
	if ( is_admin() ) {
		return $classes;
	}

	if ( 'rtl' === streamit_child_get_layout_direction() ) {
		$classes[] = 'rtl';
		$classes[] = 'streamit-child-rtl';
	}

	return $classes;
}
add_filter( 'body_class', 'streamit_child_rtl_body_classes' );

/**
 * Apply dir from the browser cookie before CSS loads (beats page cache).
 *
 * Runs on st_before_head — earlier than wp_head stylesheets.
 */
function streamit_child_rtl_early_sync() {
	if ( is_admin() ) {
		return;
	}
	?>
	<script>
	(function(){var m=document.cookie.match(/(?:^|;\s*)theme_scheme_direction=(rtl|ltr)/);document.documentElement.setAttribute('dir',m?m[1]:'rtl');})();
	</script>
	<?php
}
add_action( 'st_before_head', 'streamit_child_rtl_early_sync', 0 );

/**
 * Footer sync: body classes, switcher UI, and slick re-init after navigation.
 */
function streamit_child_enqueue_rtl_sync_script() {
	if ( is_admin() ) {
		return;
	}

	$js_path = get_stylesheet_directory() . '/assets/js/rtl-sync.js';
	if ( ! file_exists( $js_path ) ) {
		return;
	}

	wp_enqueue_script(
		'streamit-child-rtl-sync',
		get_stylesheet_directory_uri() . '/assets/js/rtl-sync.js',
		array( 'jquery', 'slick-general' ),
		filemtime( $js_path ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'streamit_child_enqueue_rtl_sync_script', 110 );

/**
 * Front-end RTL polish (spacing, alignment, sliders).
 */
function streamit_child_enqueue_frontend_rtl_styles() {
	if ( is_admin() ) {
		return;
	}

	$css_path = get_stylesheet_directory() . '/assets/css/frontend-rtl.css';
	if ( ! file_exists( $css_path ) ) {
		return;
	}

	wp_enqueue_style(
		'streamit-child-frontend-rtl',
		get_stylesheet_directory_uri() . '/assets/css/frontend-rtl.css',
		array( 'streamit-global' ),
		filemtime( $css_path )
	);
}
add_action( 'wp_enqueue_scripts', 'streamit_child_enqueue_frontend_rtl_styles', 101 );

/**
 * Absolute URI for a child-theme font file, or empty if missing.
 *
 * @param string $filename Font filename under assets/fonts/.
 * @return string
 */
function streamit_child_peyda_font_uri( $filename ) {
	$rel = '/assets/fonts/' . ltrim( $filename, '/' );
	if ( ! file_exists( get_stylesheet_directory() . $rel ) ) {
		return '';
	}

	return get_stylesheet_directory_uri() . $rel;
}

/**
 * Build @font-face CSS with absolute URLs (safe under CSS minify/combine).
 *
 * @return string
 */
function streamit_child_peyda_font_face_css() {
	// Static files only (no variable font). 800/900 alias Bold so parent
	// theme rules like .slider-text { font-weight: 800 } load a real face.
	$faces = array(
		array( 'file' => 'PeydaWeb-Light.woff2', 'weight' => 300, 'format' => 'woff2' ),
		array( 'file' => 'PeydaWeb-Regular.woff', 'weight' => 400, 'format' => 'woff' ),
		array( 'file' => 'PeydaWeb-Medium.woff2', 'weight' => 500, 'format' => 'woff2' ),
		array( 'file' => 'PeydaWeb-SemiBold.woff', 'weight' => 600, 'format' => 'woff' ),
		array( 'file' => 'PeydaWeb-Bold.woff2', 'weight' => 700, 'format' => 'woff2' ),
		array( 'file' => 'PeydaWeb-Bold.woff2', 'weight' => 800, 'format' => 'woff2' ),
		array( 'file' => 'PeydaWeb-Bold.woff2', 'weight' => 900, 'format' => 'woff2' ),
	);

	$css = '';
	foreach ( $faces as $face ) {
		$uri = streamit_child_peyda_font_uri( $face['file'] );
		if ( '' === $uri ) {
			continue;
		}
		$css .= '@font-face{font-family:"Peyda";src:url("' . esc_url( $uri ) . '") format("' . $face['format'] . '");font-weight:' . (int) $face['weight'] . ';font-style:normal;font-display:swap;}';
	}

	return $css;
}

/**
 * Shared font-family stack forced onto text UI (not icon fonts).
 *
 * @return string
 */
function streamit_child_peyda_font_family_css() {
	return 'body,button,input,select,textarea,h1,h2,h3,h4,h5,h6,p,a,.elementor-widget,.elementor-heading-title,.elementor-widget-text-editor,.elementor-button-text,.slider-text,.css_prefix-title,.title-tag,.main-title,.menu-title,.css_prefix-menu-item-title,.css_prefix-menu-item-text,.movie-description,.tvshow-description,.readmore-text,.slider-desc,.verticle-title,.streamit-child-card-fa-title,.streamit-child-tmdb-title,.streamit-child-tmdb-status,.movie-tags li a,.tvshow-tags li a,.video-tags li a,.episode-nav-btn .nav-link,.stc-dl,.stc-dl-row__label,.stc-dl-row__quality,.stc-dl-row__value,.stc-dl-btn{font-family:"Peyda",Tahoma,Arial,sans-serif!important;}';
}

/**
 * Critical weight hierarchy — late/high priority so parent font-weight:800
 * and Elementor kit rules cannot flatten Peyda to a synthetic bold.
 *
 * @return string
 */
function streamit_child_peyda_weight_hierarchy_css() {
	return '.slider-text,.slider-text.texture-text{font-weight:700!important;}'
		. 'h1.font-size-37,.detail-page h1{font-weight:700!important;}'
		. '.streamit-card-title .title-tag,.title .title-tag,.main-title,h5.main-title{font-weight:700!important;}'
		. '.css_prefix-title,.card-description .css_prefix-title,.card-description .css_prefix-title a,.vertical-banner-info-desc h2.title,.verticle-title,.css_prefix-verticle-title,.streamit-child-card-fa-title{font-weight:600!important;}'
		. '.movie-description,.movie-description .readmore-text,.tvshow-description,.tvshow-description .readmore-text,.episode-description .readmore-text,.slider-desc,.trending-dec,.season-excerpt,.vertical-banner-info-desc .description{font-weight:500!important;}'
		. '.btn,.hover-card-action-btn,.view-all-btn a,.movie-tags li a,.tvshow-tags li a,.video-tags li a,.episode-nav-btn .nav-link,.episode-nav-btn .css_prefix-change-season{font-weight:600!important;}'
		. '#site-navigation .menu-title,#site-navigation .css_prefix-menu-item-title,#site-navigation .css_prefix-menu-item-text{font-weight:500!important;}'
		. '#site-navigation .sf-menu > li.current-menu-item > a .menu-title,#site-navigation .sf-menu > li.current-menu-item > a .css_prefix-menu-item-title,#site-navigation .sf-menu > li.current_page_item > a .menu-title,#site-navigation .sf-menu > li.current_page_item > a .css_prefix-menu-item-title{font-weight:600!important;}'
		. '.imdb-ratting,.movie-time-text,.movie-time,.movie-language{font-weight:400!important;}'
		. '.streamit-child-tmdb-title,.streamit-child-tmdb-status{font-weight:600!important;}'
		. '.stc-dl-row__label,.stc-dl__subs-label,.stc-dl-series-subs__count{font-weight:500!important;}'
		. '.stc-dl-row__value,.stc-dl-season__toggle,.stc-dl-series-subs__title,.stc-dl-btn,.stc-dl__util-btn{font-weight:600!important;}'
		. '.stc-dl-row__quality{font-weight:700!important;}';
}

/**
 * Self-hosted Peyda webfont for Persian UI.
 */
function streamit_child_enqueue_peyda_font() {
	if ( is_admin() ) {
		return;
	}

	$css_path = get_stylesheet_directory() . '/assets/css/peyda-font.css';
	if ( ! file_exists( $css_path ) ) {
		return;
	}

	$deps = array( 'child-style' );
	if ( wp_style_is( 'streamit-global', 'registered' ) || wp_style_is( 'streamit-global', 'enqueued' ) ) {
		$deps[] = 'streamit-global';
	}

	wp_enqueue_style(
		'streamit-child-peyda-font',
		get_stylesheet_directory_uri() . '/assets/css/peyda-font.css',
		$deps,
		filemtime( $css_path )
	);

	$face_css = streamit_child_peyda_font_face_css();
	if ( '' !== $face_css ) {
		wp_add_inline_style( 'streamit-child-peyda-font', $face_css );
	}
}
add_action( 'wp_enqueue_scripts', 'streamit_child_enqueue_peyda_font', 102 );

/**
 * Preload the primary body weight so Persian text paints sooner.
 */
function streamit_child_preload_peyda_font() {
	if ( is_admin() ) {
		return;
	}

	$uri = streamit_child_peyda_font_uri( 'PeydaWeb-Regular.woff' );
	if ( '' === $uri ) {
		return;
	}

	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff" crossorigin>' . "\n",
		esc_url( $uri )
	);
}
add_action( 'wp_head', 'streamit_child_preload_peyda_font', 1 );

/**
 * Beat Redux dynamic typography (uses font-family !important on body/h1–h6).
 */
function streamit_child_force_peyda_font_family( $css ) {
	return $css . streamit_child_peyda_font_family_css() . streamit_child_peyda_weight_hierarchy_css();
}
add_filter( 'st_inline_css', 'streamit_child_force_peyda_font_family', 100 );

/**
 * Last-resort override after Elementor kit CSS (prints late in wp_head).
 */
function streamit_child_print_peyda_font_late() {
	if ( is_admin() ) {
		return;
	}

	$face = streamit_child_peyda_font_face_css();
	$fam  = streamit_child_peyda_font_family_css();
	$wts  = streamit_child_peyda_weight_hierarchy_css();
	if ( '' === $face && '' === $fam && '' === $wts ) {
		return;
	}

	echo '<style id="streamit-child-peyda-late">' . $face . $fam . $wts . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_url + fixed CSS.
}
add_action( 'wp_head', 'streamit_child_print_peyda_font_late', 99 );

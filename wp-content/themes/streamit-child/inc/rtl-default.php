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

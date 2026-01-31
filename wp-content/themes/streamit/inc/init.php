<?php
/*
* streamit functions and definitions
*
* @link https://developer.wordpress.org/themes/basics/theme-functions/
*
* @package streamit
*/

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

if (file_exists(get_template_directory() . '/vendor/autoload.php')) {
	require get_template_directory() . '/vendor/autoload.php';
} else {
	die('Something went wrong');
}

add_action('init', function () {
	/**
	 * Make theme available for translation.
	 * Translations can be filed in the /languages/ directory.
	 * If you're building a theme based on Word, use a find and replace
	 * to change 'streamit' to the name of your theme in all the template files.
	 */
	load_theme_textdomain('streamit', get_template_directory() . '/languages');
});

require_once get_template_directory() . '/inc/helpers.php';

if (class_exists('Redux'))
	require get_template_directory() . '/admin/Redux_Framework/class-streamit-reduxoptions.php';


if (class_exists('ReduxFramework'))
	require_once get_template_directory() . '/inc/components/Dynamic_Style/class-streamit-dynamic-style.php';

require_once get_template_directory() . '/inc/theme-helper/class-streamit-theme-helper.php';

require_once get_template_directory() . '/inc/components/single/comments.php';

require_once get_template_directory() . '/inc/components/Nav-Menus/class-streamit-dropdown-menu-arrow.php';

require_once get_template_directory() . '/inc/components/class-streamit-breadcrumb.php';

require_once get_template_directory() . '/inc/components/Sidebar/class-streamit-sidebar-handler.php';

require_once get_template_directory() . '/inc/components/Footer/class-streamit-footer.php';


//Elementer widget
require_once get_template_directory() . '/inc/Elementor-widget/class-streamit-elementor.php';

//Ajax route Handler
require_once get_template_directory() . '/static/class-st-routes.php';
require_once get_template_directory() . '/static/class-st-routes-handler.php';


/**
 * Enqueue theme scripts and styles.
 * 
 * This function registers and enqueues various styles and scripts for the theme.
 * It includes Google Fonts, Select2 styles and scripts, Vite-based assets,
 * global styles, custom JavaScript, and comment reply scripts (if applicable).
 *
 * @return void
 */
add_action('wp_enqueue_scripts', function () {

	// Enqueue Google Fonts for the theme.
	/**
	 * Registers and enqueues Google Fonts.
	 *
	 * @link https://fonts.google.com/specimen/Roboto
	 */
	$google_fonts_url = add_query_arg(
		array(
			'family' => 'Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap'
		),
		'//fonts.googleapis.com/css'
	);
	wp_register_style('google-fonts', $google_fonts_url, array(), null);
	wp_enqueue_style('google-fonts');

	// Enqueue jQuery.
	wp_enqueue_script('jquery');

	/**
	 * Registers and enqueues Select2 script and styles.
	 */
	wp_register_script(
		'st-select2-js',
		get_template_directory_uri() . '/static/assets/vendor/js/select2.js',
		array('jquery'),
		'1.0',
		true // Load in the footer
	);

	wp_enqueue_style(
		'st-select2-css',
		get_template_directory_uri() . '/static/assets/vendor/css/select2.css',
		'1.0'
	);

	/**
	 * Enqueues the main JavaScript file compiled with Vite.
	 * 
	 * @param string $handle The unique handle for the asset.
	 * @param array  $args   The array of arguments for the asset, including dependencies.
	 */
	Kucrut\Vite\enqueue_asset(
		get_template_directory() . '/static/dist',
		'static/assets/utilities/main.js',
		array(
			'handle'         => 'streamit-main',
			'dependencies'   => array('jquery', 'st-select2-js'),
			'in-footer'      => true,
		)
	);

	// Register Slick JavaScript
	wp_register_script(
		'st-slick-js',
		get_template_directory_uri() . '/inc/Elementor-widget/assets/slick/slick.min.js',
		array('jquery'),
		'1.8.1',
		true // Load in the footer
	);

	// Register Slick CSS
	wp_enqueue_style(
		'st-slick-css',
		get_template_directory_uri() . '/inc/Elementor-widget/assets/slick/slick.css',
		array(),
		'1.8.1'
	);

	wp_enqueue_style(
		'st-slick-theme-css',
		get_template_directory_uri() . '/inc/Elementor-widget/assets/slick/slick-theme.css',
		array('st-slick-css'),
		'1.8.1'
	);

	// Enqueue slick-general JavaScript asset via Kucrut\Vite\enqueue_asset
	Kucrut\Vite\enqueue_asset(
		get_template_directory() . '/static/dist',
		'inc/Elementor-widget/assets/js/slick-general.js', // Path to your custom JS file
		array(
			'handle'       => 'slick-general',
			'dependencies' => array('jquery', 'st-slick-js'),
			'in-footer'    => true, // Load in the footer
		)
	);


	// Enqueue global stylesheet.
	wp_enqueue_style('streamit-global', get_stylesheet_uri());

	wp_enqueue_script('heartbeat');
	/**
	 * Enqueues the comment reply script if we're viewing a singular post and comments are open.
	 */
	if (is_singular() && comments_open() && get_option('thread_comments')) {
		wp_enqueue_script('comment-reply');
	}


	ob_start();
	streamit_get_template('elementor-widget/main-card-slider/html-main-card-slider-hover-struture.php');
	$main_hover_card = ob_get_clean();

	ob_start();
	streamit_get_template('elementor-widget/main-card-slider/html-main-card-slider-landscape-hover-struture.php');
	$main_landscape_hover_card = ob_get_clean();

	// Localize script to pass AJAX URL, nonce, and hover card templates.
	wp_localize_script(
		'streamit-main',
		'stAjax',
		array(
			'ajaxurl'     => esc_url(admin_url('admin-ajax.php')),
			'_ajax_nonce' => wp_create_nonce('st_ajax_nonce'),

			// Hover card templates for different card styles
			'hoverCards'  => array(
				'main' => $main_hover_card,
				'main_landscap' => $main_landscape_hover_card
			),
			'playlist' => array(
				'placeholder' => esc_html__('Select playlist type', 'streamit'),
				'create_title' => esc_html__('Create Playlist', 'streamit'),
				'create_btn_title' => esc_html__('Create Playlist', 'streamit'),
				'update_title' => esc_html__('Edit Playlist', 'streamit'),
				'update_btn_title' => esc_html__('Update Playlist', 'streamit'),

			)

		)
	);

	// Enqueue and localize the trailer template
	streamit_enqueue_trailer_template();
	// Add inline CSS if defined by filters.
	$inline_css = apply_filters('st_inline_css', '');
	wp_add_inline_style('streamit-global', $inline_css);

	// Add inline JavaScript if defined by filters.
	$inline_js = apply_filters('st_inline_js', '');
	wp_add_inline_script('streamit-main', $inline_js);
});
function streamit_enqueue_trailer_template()
{
	ob_start();
	streamit_get_template('common/html-trailer.php');
	$trailer_template = ob_get_clean();

	if (!empty($trailer_template)) {
		wp_localize_script('streamit-main', 'streamitTrailerVars', array(
			'trailerModalHTML' => $trailer_template,
		));
	}
}
do_action('streamit:customizer:load:before');

global $wp_customize;

if (isset($wp_customize)) {
	require get_template_directory() . '/inc/customizer/class-streamit-customizer.php';
}


if (is_admin()) {
	require get_template_directory() . '/admin/init.php';
}

// Bail if requirements are not met.
if (version_compare($GLOBALS['wp_version'], STREAMIT_MINIMUM_WP_VERSION, '<') || version_compare(phpversion(), STREAMIT_MINIMUM_PHP_VERSION, '<')) {
	require get_template_directory() . '/inc/back-compat.php';
	return;
}

/**
 * Enqueue script tags with async or defer attributes based on handle.
 * 
 * This filter adds 'async' or 'defer' attributes to script tags for improved performance.
 * Scripts are marked with 'async' if their handle matches an array defined in the 'streamit-async-scripts-handles' filter.
 * If the script handle contains 'async' or 'defer', the corresponding attribute is added automatically.
 * 
 * @param string $tag The HTML script tag.
 * @param string $handle The script's unique handle.
 * 
 * @return string Modified script tag with added attributes.
 */
if (!is_admin()) {
	add_filter('script_loader_tag', function ($tag, $handle) {
		// Cache the handles to avoid repetitive calls to apply_filters()
		static $async_handles = null;

		// Fetch the async script handles once, and cache them
		if ($async_handles === null) {
			$async_handles = apply_filters('streamit-async-scripts-handles', []);
		}

		// Check if script should have async or defer attribute
		if (in_array($handle, $async_handles, true)) {
			return st_add_attribute_to_script($tag, 'async');
		}

		// Check if the handle contains 'async' or 'defer' and apply the respective attribute
		if (strpos($handle, 'async') !== false) {
			return st_add_attribute_to_script($tag, 'async');
		} elseif (strpos($handle, 'defer') !== false) {
			return st_add_attribute_to_script($tag, 'defer');
		}

		return $tag;
	}, 10, 2);
}

/**
 * Helper function to add attributes (async or defer) to the script tag.
 * 
 * @param string $tag The script tag.
 * @param string $attribute The attribute to add (either 'async' or 'defer').
 * 
 * @return string The script tag with the added attribute.
 */
function st_add_attribute_to_script($tag, $attribute)
{
	// Ensure the attribute is added properly and return the modified script tag
	return str_replace('<script ', '<script ' . $attribute . ' ', $tag);
}


/**
 * Add <span> in archives count for styling.
 *
 * This function modifies the HTML output of archive links by wrapping the
 * count (e.g. the number of posts in that archive) in a <span> element,
 * which can be targeted with CSS for styling.
 *
 * @param string $link_html The HTML output of the archive link.
 * @return string Modified HTML output with <span> added around the count.
 */
function st_custom_archives_span($link_html)
{
	// Use regular expressions to wrap the count in a <span> element
	$link_html = preg_replace('/<\/a>&nbsp;\((\d+)\)/', '</a> <span class="css_prefix_archive_count">($1)</span>', $link_html);
	return $link_html;
}

add_filter('get_archives_link', 'st_custom_archives_span');


function st_favicon_fallback()
{
	// Get the site icon ID from WordPress settings
	$site_icon_id = get_option('site_icon');
	if ($site_icon_id) {
		// If a site icon is set, get its URL
		$site_icon_url = wp_get_attachment_image_url($site_icon_id, 'full');
	} else {
		// If no site icon is set, use your custom favicon
		$site_icon_url = get_template_directory_uri() . '/admin/assets/images/redux/favicon.ico';
	}

	echo '<link rel="icon" type="image/png" href="' . esc_url($site_icon_url) . '">';
}
add_action('wp_head', 'st_favicon_fallback');

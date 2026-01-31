<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}


/**
 * Class for integrating with the block editor.
 *
 * @link https://wordpress.org/gutenberg/handbook/extensibility/theme-support/
 */

if (!class_exists('st_reduxoptions')) {
	class st_reduxoptions
	{

		protected $opt_name = "streamit_options";

		protected $page_slug = "_streamit_options";
		private $is_customizer;

		/**
		 * Gets the unique identifier for the theme component.
		 *
		 * @return string Component slug.
		 */
		public function get_slug(): string
		{
			return 'redux_framework';
		}

		/**
		 * Adds the action and filter hooks to integrate with WordPress.
		 */
		public function __construct()
		{
			$this->is_customizer = is_customize_preview();
			add_action('after_setup_theme', array($this, 'action_add_redux'));

			add_action('after_setup_theme', array($this, 'action_add_redux_widgets'));

			add_action('after_setup_theme', array($this, 'action_add_reduxmetaboxs'));

			/* redux styles */
			add_action('redux/page/' . $this->opt_name . '/enqueue', array($this, 'st_redux_admin_styles'));
			add_action('wp_ajax_st_save_redux_style_action', [$this, 'st_save_redux_style']);
			add_action('wp_ajax_nopriv_st_save_redux_style_action', [$this, 'st_save_redux_style']);

			/* redux fields overload */
			if (!$this->is_customizer) {
				require dirname(__FILE__) . '/fields/dimensions/class-redux-dimensions.php';
				require dirname(__FILE__) . '/fields/spacing/class-redux-spacing.php';
				require dirname(__FILE__) . '/fields/media/class-redux-media.php';
				require dirname(__FILE__) . '/fields/raw/class-redux-raw.php';
			}
		}

		function st_redux_admin_styles()
		{
			global $is_dark_mode;
			$root = '';

			// remove admin notice for theme redux option page;
			remove_all_actions("admin_notices");

			$get_theme = wp_get_theme();
			$version = $get_theme->get('Version');
			$root_vars = [
				"--redux-sidebar-color:#121623",
				"--redux-top-header:#f5f7ff",
				"--submenu-border-color:#262b3b",
				"--border-color-light:#ededed",
				"--content-backgrand-color:#fff",
				"--sub-fields-back:#fff;",
				"--input-border-color:#d8e1f5",
				"--input-btn-back:#edeffc",
				"--input-back-color:#f5f7ff",
				"--white-color-nochage:#fff",
				"--redux-text-color:#69748c",/* font color */
				"--text-heading-color:#121623",
				"--submenu-hover-color:#fff",
				"--redux-primary-color:#de3a53",
				"--font-weight-medium:500", /* font weight */
				"--notice-yellow-back:#fbf5e2",
				"--notice-yellow-color:#f7a210",
				"--code-editor-active:#e6edff",
				"--notice-green-back:#d1f1be",
				"--redux-sidebar-color:#f5f7ff",
				"--active-tab-color:#f5f0f0",
				"--no-changeborder-color-light:#ededed",
				"--submenu-hover-color:#de3a53",
				"--submenu-active-color:#de3a53",
				"--submenu-border-color:#e5e9e7",
				"--redux-menu-lable:#aeb1b9",
				"--redux-menu-color:#353840",
				"--wp-content-back:#f0f0f1",
			];

			$root .= ':root{' . implode(";", $root_vars) . '}';

			$root .= '.redux-brand.logo { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/logo.webp' . ' ) }';
			$root .= ' @media screen and (max-width: 600px) { .redux-brand.logo { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/mobile-logo-light.png' . ' ) } }';

			$root .= '.redux-image-select .one-column { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/one-column.png' . ' ) }';
			$root .= '.redux-image-select .two-column { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/two-column.png' . ' ) }';
			$root .= '.redux-image-select .three-column { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/three-column.png' . ' ) }';
			$root .= '.redux-image-select .four-column { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/four-column.png' . ' ) }';
			$root .= '.redux-image-select .right-sidebar { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/right-sidebar.png' . ' ) }';
			$root .= '.redux-image-select .left-sidebar { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/left-sidebar.png' . ' ) }';

			$root .= '.redux-image-select .footer-layout-1 { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/footer-1.png' . ' ) }';
			$root .= '.redux-image-select .footer-layout-2 { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/footer-2.png' . ' ) }';
			$root .= '.redux-image-select .footer-layout-3 { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/footer-3.png' . ' ) }';
			$root .= '.redux-image-select .footer-layout-4 { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/footer-4.png' . ' ) }';
			$root .= '.redux-image-select .footer-layout-5 { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/footer-5.png' . ' ) }';

			$root .= '.redux-image-select .title-1 { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/title-1.png' . ' ) }';
			$root .= '.redux-image-select .title-2 { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/title-2.png' . ' ) }';
			$root .= '.redux-image-select .title-3 { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/title-3.png' . ' ) }';
			$root .= '.redux-image-select .title-4 { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/title-4.png' . ' ) }';
			$root .= '.redux-image-select .title-5 { content: url( ' . get_template_directory_uri() . '/admin/assets/images/redux/title-5.png' . ' ) }';

			$is_dark_mode = get_option($this->page_slug . "_is_redux_dark_mode", true);

			if (!$is_dark_mode) {
				wp_add_inline_style("redux-template", $root);
			}

			wp_register_script('custom_redux_options', false);
			wp_localize_script(
				'custom_redux_options',
				'custom_redux_options_params',
				array(
					'ajaxUrl' => admin_url() . 'admin-ajax.php',
					'root' => $root,
					'is_dark_mode' => $is_dark_mode ? true : false
				)
			);

			wp_enqueue_script('custom_redux_options');
		}

		public function st_save_redux_style()
		{
			$is_dark_mode = isset($_GET['is_dark_mode']) && $_GET['is_dark_mode'] == 1 ? 1 : 0;
			update_option($this->page_slug . "_is_redux_dark_mode", $is_dark_mode);
		}

		public function action_add_redux()
		{
			// RDX Framework Barebones Sample Config File
			if (!class_exists('Redux')) {
				return;
			}

			$theme = wp_get_theme(); // For use with some settings. Not necessary.
			$url = get_template_directory_uri();

			$args = array(
				// TYPICAL -> Change these values as you need/desire
				'opt_name' => $this->opt_name,
				// This is where your data is stored in the database and also becomes your global variable name.
				'display_name' => $theme->get('Name'),
				// Name that appears at the top of your panel
				'display_version' => $theme->get('Version'),
				// Version that appears at the top of your panel
				'menu_type' => 'menu',
				//Specify if the admin menu should appear or not. Options: menu or submenu (Under appearance only)
				'allow_sub_menu' => true,
				// Show the sections below the admin menu item or not
				'menu_title' => esc_html__('Streamit Options', 'streamit'),
				'page_title' => esc_html__('Streamit Options', 'streamit'),
				// You will need to generate a Google API key to use this feature.
				// Please visit: https://developers.google.com/fonts/docs/developer_api#Auth
				'google_api_key' => '',
				// Set it you want google fonts to update weekly. A google_api_key value is required.
				'google_update_weekly' => false,
				// Must be defined to add google fonts to the typography module
				'async_typography' => true,
				// Use a asynchronous font on the front end or font string
				//'disable_google_fonts_link' => true,                    // Disable this in case you want to create your own google fonts loader
				'admin_bar' => true,
				// Show the panel pages on the admin bar
				'admin_bar_icon' => 'dashicons-admin-settings',
				// Choose an icon for the admin bar menu
				'admin_bar_priority' => 50,
				// Choose a priority for the admin bar menu
				'global_variable' => 'streamit_options',
				// Set a different name for your global variable other than the opt_name
				'dev_mode' => false,
				// Show the time the page took to load, etc
				'update_notice' => false,
				// If dev_mode is enabled, will notify developer of updated versions available in the GitHub Repo
				'customizer' => true,
				// Enable basic customizer support
				//'open_expanded'     => true,                    // Allow you to start the panel in an expanded way initially.
				//'disable_save_warn' => true,                    // Disable the save warning when a user changes a field
				'class' => 'redux-content',
				// OPTIONAL -> Give you extra features
				'page_priority' => 3,
				// Order where the menu appears in the admin area. If there is any conflict, something will not show. Warning.
				'page_parent' => 'themes.php',
				// For a full list of options, visit: http://codex.wordpress.org/Function_Reference/add_submenu_page#Parameters
				'page_permissions' => 'manage_options',
				// Permissions needed to access the options panel.
				'menu_icon' => $url . '/admin/assets/images/redux/options.png',
				// Specify a custom URL to an icon
				'last_tab' => '',
				// Force your panel to always open to a specific tab (by id)
				'page_icon' => 'icon-themes',
				// Icon displayed in the admin panel next to your menu_title
				'page_slug' => $this->page_slug,
				// Page slug used to denote the panel
				'save_defaults' => true,
				// On load save the defaults to DB before user clicks save or not
				'default_show' => false,
				// If true, shows the default value next to each field that is not the default value.
				'default_mark' => '',
				// What to print by the field's title if the value shown is default. Suggested: *
				'show_import_export' => true,
				// Shows the Import/Export panel when not used as a field.
				'show_options_object' => true,
				'templates_path' => !$this->is_customizer ? dirname(__FILE__) . '/templates/panel/' : '',
				'use_cdn' => true,
				// CAREFUL -> These options are for advanced use only
				'transient_time' => 60 * MINUTE_IN_SECONDS,
				'output' => true,
				// Global shut-off for dynamic CSS output by the framework. Will also disable google fonts output
				'output_tag' => true,
				// Allows dynamic CSS to be generated for customizer and google fonts, but stops the dynamic CSS from going to the head
				// FUTURE -> Not in use yet, but reserved or partially implemented. Use at your own risk.
				'database' => '',
				// possible: options, theme_mods, theme_mods_expanded, transient. Not fully functional, warning!
				'system_info' => false,
				// REMOVE
				'hide_expand' => true,
				// HINTS
				'hints' => array(
					'icon' => 'el el-question-sign',
					'icon_position' => 'right',
					'icon_color' => 'lightgray',
					'icon_size' => 'normal',
					'tip_style' => array(
						'color' => 'light',
						'shadow' => true,
						'rounded' => false,
						'style' => '',
					),
					'tip_position' => array(
						'my' => 'top left',
						'at' => 'bottom right',
					),
					'tip_effect' => array(
						'show' => array(
							'effect' => 'slide',
							'duration' => '500',
							'event' => 'mouseover',
						),
						'hide' => array(
							'effect' => 'slide',
							'duration' => '500',
							'event' => 'click mouseleave',
						),
					),
				),
			);

			Redux::set_args($this->opt_name, $args);
		}

		public function action_add_redux_widgets()
		{
			require get_template_directory() . '/admin/Redux_Framework/Options/Dashboard.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/General.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Logo.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Loader.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Color.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Typography.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Page.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Breadcrumb.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Streamit.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/user.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Layout.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Maintenance.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Blog.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Header.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/Footer.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/FourZeroFour.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/SocialMedia.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/WooCommerce.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/AdditionalCode.php';
			require get_template_directory() . '/admin/Redux_Framework/Options/ImportExport.php';
		}

		public function action_add_reduxmetaboxs()
		{
			require get_template_directory() . '/admin/Redux_Framework/Metabox/Dashboard.php';
		}
	}
}
new st_reduxoptions();

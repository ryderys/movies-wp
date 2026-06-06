<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class to use them setup process
 *
 * @package streamit
 */

class Theme_Setup
{

	/**
	 * Gets the unique identifier for the theme component.
	 *
	 * @return string Component slug.
	 */
	public function get_slug(): string
	{
		return 'theme_setup';
	}

	/**
	 * Adds the action and filter hooks to integrate with WordPress.
	 */
	public function __construct()
	{
		$this->st_setup_wizard_config();
	}

	/**
	 * Define Setup wizard default values
	 *
	 *
	 * This function init merlin class and set default values
	 */
	function st_setup_wizard_config()
	{
		$theme_detail = wp_get_theme();
		$wizard = new Merlin(

			$config = array(
				'directory'            => 'Merlin', // Location / directory where Merlin WP is placed in your theme.
				'merlin_url'           => 'streamit-setup', // The wp-admin page slug where Merlin WP loads.
				'parent_slug'          => 'themes.php', // The wp-admin parent page slug for the admin menu item.
				'capability'           => 'manage_options', // The capability required for this menu to be displayed to the user.
				'child_action_btn_url' => 'https://developer.wordpress.org/themes/advanced-topics/child-themes/', // URL for the 'child-action-link'.
				'dev_mode'             => true, // Enable development mode for testing.
				'license_step'         => false, // EDD license activation step.
				'license_required'     => false, // Require the license activation step.
				'license_help_url'     => '', // URL for the 'license-tooltip'.
				'edd_remote_api_url'   => '', // EDD_Theme_Updater_Admin remote_api_url.
				'edd_item_name'        => '', // EDD_Theme_Updater_Admin item_name.
				'edd_theme_slug'       => '', // EDD_Theme_Updater_Admin item_slug.
				'ready_big_button_url' => home_url('/'), // Link for the big button on the ready step.
			),
			$strings = array(
				'admin-menu'               => esc_html__('Theme Setup', 'streamit'),

				/* translators: 1: Title Tag 2: Theme Name 3: Closing Title Tag */
				'title%s%s%s%s'            => esc_html__('%1$s%2$s Themes &lsaquo; Theme Setup: %3$s%4$s', 'streamit'),
				'return-to-dashboard'      => esc_html__('Return to the dashboard', 'streamit'),
				'ignore'                   => esc_html__('Disable this wizard', 'streamit'),

				'btn-skip'                 => esc_html__('Skip', 'streamit'),
				'btn-next'                 => esc_html__('Next', 'streamit'),
				'btn-start'                => esc_html__('Start', 'streamit'),
				'btn-no'                   => esc_html__('Cancel', 'streamit'),
				'btn-plugins-install'      => esc_html__('Install', 'streamit'),
				'btn-child-install'        => esc_html__('Install', 'streamit'),
				'btn-content-install'      => esc_html__('Install', 'streamit'),
				'btn-import'               => esc_html__('Import', 'streamit'),
				'btn-license-activate'     => esc_html__('Activate', 'streamit'),
				'btn-license-skip'         => esc_html__('Later', 'streamit'),

				/* translators: Theme Name */
				'license-header%s'         => esc_html__('Activate %s', 'streamit'),
				/* translators: Theme Name */
				'license-header-success%s' => esc_html__('%s is Activated', 'streamit'),
				/* translators: Theme Name */
				'license%s'                => esc_html__('Enter your license key to enable remote updates and theme support.', 'streamit'),
				'license-label'            => esc_html__('License key', 'streamit'),
				'license-success%s'        => esc_html__('The theme is already registered, so you can go to the next step!', 'streamit'),
				'license-json-success%s'   => esc_html__('Your theme is activated! Remote updates and theme support are enabled.', 'streamit'),
				'license-tooltip'          => esc_html__('Need help?', 'streamit'),

				/* translators: Theme Name */
				'welcome-header%s'         => esc_html__('Welcome to %s', 'streamit'),
				'welcome-header-success%s' => esc_html__('Welcome to Streamit!', 'streamit'),
				'welcome%s'                => esc_html__('To ensure optimal performance, please review the following PHP settings and verify that they meet the minimum requirements.', 'streamit'),
				'welcome-success%s'        => esc_html__('To ensure optimal performance, please review the following PHP settings and verify that they meet the minimum requirements.', 'streamit'),

				'child-header'             => esc_html__('Install Child Theme', 'streamit'),
				'child-header-success'     => esc_html__('You\'re All Set!', 'streamit'),
				'child'                    => esc_html__('Let\'s build & activate a child theme so you may easily make theme changes.', 'streamit'),
				'child-success%s'          => esc_html__('The child theme has been successfully installed and activated. You\'re ready to proceed with your site customization.', 'streamit'),
				'child-action-link'        => esc_html__('Learn more about child themes', 'streamit'),
				'child-json-success%s'     => esc_html__('Awesome. Your child theme has already been installed and ready to activated.', 'streamit'),
				'child-json-already%s'     => esc_html__('Awesome. Your child theme has been created and ready to activated.', 'streamit'),

				'plugins-header'           => esc_html__('Install Plugins', 'streamit'),
				'plugins-header-success'   => esc_html__('You\'re Ready to Go!', 'streamit'),
				'plugins'                  => esc_html__('Let\'s install essential WordPress plugins to enhance your site.', 'streamit'),
				'plugins-success%s'        => esc_html__('All the required WordPress plugins have been successfully installed and are up to date.To ensure optimal functionality and performance, these plugins are essential for your site. Click "Next" to continue with the setup wizard and complete the next steps in your site configuration.', 'streamit'),
				'plugins-action-link'      => esc_html__('Advanced', 'streamit'),

				'data-import'				=> esc_html__('Import Data' , 'streamit'),
				'data'                   	=> esc_html__('Importing sample movies, videos, and other content offers a realistic demonstration of the theme\'s features and design potential.', 'streamit'),
				
				
				'import-header'            => esc_html__('Import Content', 'streamit'),
				'import'                   => esc_html__('To showcase the theme\'s features and layout, you can import demo content.', 'streamit'),
				'import-action-link'       => esc_html__('Advanced', 'streamit'),

				'ready-header'             => esc_html__('You\'re All Set!', 'streamit'),

				/* translators: Theme Author */
				'ready%s'                  => esc_html__('Your website is ready to go! Get ready to wow the world!' , 'streamit'),
				'ready-action-link'        => esc_html__('Extras', 'streamit'),
				'ready-big-button'         => esc_html__('View your website', 'streamit'),
				'ready-link-1'             => sprintf('<a href="%1$s" class="merlin__button merlin__button--knockout merlin__button--no-chevron merlin__button--external" target="_blank">%2$s</a>', 'https://wordpress.org/support/', esc_html__('Explore WordPress', 'streamit')),
				'ready-link-2'             => sprintf('<a href="%1$s" class="merlin__button merlin__button--knockout merlin__button--no-chevron merlin__button--external" target="_blank">%2$s</a>', 'https://iqonic.desky.support/', esc_html__('Get Theme Support', 'streamit')),
				'ready-link-3'             => sprintf('<a href="%1$s" class="merlin__button merlin__button--knockout merlin__button--no-chevron merlin__button--external">%2$s</a>', admin_url('admin.php?page=_streamit_options&tab=1'), esc_html__('Start Customizing', 'streamit')),
			)
		);
	}
}

// Run after load_theme_textdomain on init (see inc/init.php) to avoid WP 6.7+ notices.
add_action(
	'init',
	static function () {
		new Theme_Setup();
	},
	20
);
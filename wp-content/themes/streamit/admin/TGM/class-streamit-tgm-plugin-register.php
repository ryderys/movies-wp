<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class To Register Plugin in TGM class
 *
 * @package streamit
 */


class streamit_tgm_plugin_register
{

	/**
	 * Gets the unique identifier for the theme component.
	 *
	 * @return string Component slug.
	 */
	public function get_slug(): string
	{
		return 'tgm';
	}

	/**
	 * Adds the action and filter hooks to integrate with WordPress.
	 */
	public function __construct()
	{
		add_action('st_tgmpa_register', array($this, 'st_register_required_plugins'));
	}

	/**
	 * Register the required plugins for this theme.
	 *
	 * The variable passed to tgmpa_register_plugins() should be an array of plugin
	 * arrays.
	 *
	 * This function is hooked into tgmpa_init, which is fired within the
	 * TGM_Plugin_Activation class constructor.
	 */
	function st_register_required_plugins()
	{

		/**
		 * Array of plugin arrays. Required keys are name and slug.
		 * If the source is NOT from the .org repo, then source is also required.
		 */
		$plugins = array(
			array(
				'name'       => esc_html__('Streamit', 'streamit'),
				'slug'       => 'streamit',
				'source'     => esc_url('http://assets.iqonic.design/wp/plugins/streamit_4/streamit.zip'),
				'required'   => true,
			),
			array(
				'name'      => esc_html__('Elementor', 'streamit'),
				'slug'      => 'elementor',
				'required'  => true
			),
			array(
				'name'       => esc_html__('Live Streaming', 'streamit'),
				'slug'       => 'live-streaming',
				'source'     => esc_url('http://assets.iqonic.design/wp/plugins/streamit_4/live-streaming.zip'),
				'required'   => false,
			),
			array(
				'name'      => esc_html__('Paid Memberships Pro', 'streamit'),
				'slug'      => 'paid-memberships-pro',
				'source'     => esc_url('https://assets.iqonic.design/wp/plugins/paid-memberships-pro.zip'),
				'required'  => false,
			),
			array(
				'name'      => esc_html__('WooCommerce', 'streamit'),
				'slug'      => 'woocommerce',
				'required'  => false,
			),
			array(
				'name'      => esc_html__('WPC Smart Quick View for WooCommerce', 'streamit'),
				'slug'      => 'woo-smart-quick-view',
				'required'  => false,
			),
			array(
				'name'      => esc_html__('WOOF - Products Filter for WooCommerce', 'streamit'),
				'slug'      => 'woocommerce-products-filter',
				'required'  => false,
			),
			array(
				'name'      => esc_html__('YITH WooCommerce Wishlist', 'streamit'),
				'slug'      => 'yith-woocommerce-wishlist',
				'required'  => false,
			),
			array(
				'name'      => esc_html__('Social Login, Social Sharing by miniOrange', 'streamit'),
				'slug'      => 'miniorange-login-openid',
				'source'     => esc_url('https://assets.iqonic.design/wp/plugins/miniorange-login-openid.zip'),
				'required'  => false,
			),
			array(
				'name'      => esc_html__('Contact Form 7', 'streamit'),
				'slug'      => 'contact-form-7',
				'required'  => false
			),
			array(
				'name'      => esc_html__('Mailchimp', 'streamit'),
				'slug'      => 'mailchimp-for-wp',
				'required'  => false
			),
		);

		/*
		 * Array of configuration settings. Amend each line as needed.
		 *
		 * TGMPA will start providing localized text strings soon. If you already have translations of our standard
		 * strings available, please help us make TGMPA even better by giving us access to these translations or by
		 * sending in a pull-request with .po file(s) with the translations.
		 *
		 * Only uncomment the strings in the config array if you want to customize the strings.
		 */
		$config = array(
			'id'           => 'tgmpa',                 // Unique ID for hashing notices for multiple instances of TGMPA.
			'default_path' => '',                      // Default absolute path to bundled plugins.
			'menu'         => 'tgmpa-install-plugins', // Menu slug.
			'parent_slug'  => 'themes.php',            // Parent menu slug.
			'capability'   => 'edit_theme_options',    // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
			'has_notices'  => true,                    // Show admin notices or not.
			'dismissable'  => true,                    // If false, a user cannot dismiss the nag message.
			'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
			'is_automatic' => false,                   // Automatically activate plugins after installation or not.
			'message'      => '',                      // Message to output right before the plugins table.
		);

		st_tgmpa($plugins, $config);
	}
}

new streamit_tgm_plugin_register();
<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}


/**
 * streamit\streamit\Redux_Framework\Options\FourZeroFour class
 *
 * @package streamit
 */
Redux::set_section(
	$this->opt_name,
	array(
		'title' => esc_html__('404', 'streamit'),
		'id' => 'fourzerofour',
		'icon' => 'custom-404',
		'desc' => esc_html__('This section contains options for 404.', 'streamit'),
		'fields' => array(

			array(
				'id' => '404_banner_image',
				'type' => 'media',
				'url' => true,
				'title' => esc_html__('Image', 'streamit'),
				'read-only' => false,
				'default' => array('url' => get_template_directory_uri() . '/static/assets/images/404.png'),
				'subtitle' => esc_html__('Upload 404 image for your Website.', 'streamit'),
				'desc' => '<i class="custom-info"></i><span class="media-label">' . esc_html__(' Upload your 404 image', 'streamit') . '</span>',
			),

			array(
				'id' => '404_title',
				'type' => 'text',
				'title' => esc_html__('Title', 'streamit'),
				'default' => esc_html__('Oops! This Page is Not Found.', 'streamit'),
			),

			array(
				'id' => '404_description',
				'type' => 'textarea',
				'title' => esc_html__('Description', 'streamit'),
				'default' => esc_html__('The requested page does not exist.', 'streamit'),
			),

			array(
				'id' => '404_backtohome_title',
				'type' => 'text',
				'title' => esc_html__('Button', 'streamit'),
				'default' => esc_html__('Back to Home', 'streamit'),
			),

			array(
				'id' => 'header_on_404',
				'type' => 'button_set',
				'title' => esc_html__('Header', 'streamit'),
				'subtitle' => esc_html__('Enable / disable header on 404 page', 'streamit'),
				'options' => array(
					'1' => 'Enable',
					'0' => 'Disable'
				),
				'default' => '0',
			),

			array(
				'id' => 'footer_on_404',
				'type' => 'button_set',
				'title' => esc_html__('Footer', 'streamit'),
				'subtitle' => esc_html__('Enable / disable footer on 404 page', 'streamit'),
				'options' => array(
					'1' => 'Enable',
					'0' => 'Disable'
				),
				'default' => '0',
			),
		)
	)
);

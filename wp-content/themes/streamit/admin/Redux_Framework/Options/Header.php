<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * streamit\streamit\Redux_Framework\Options\Header class
 *
 * @package streamit
 */

Redux::set_section($this->opt_name, array(
	'title' 			=> esc_html__('Header', 'streamit'),
	'id' 				=> 'header-editor',
	'has_group_title' 	=> esc_html__("PAGE SETTINGS", "streamit"),
	'icon' 				=> 'custom-header-main',
	'customizer_width' 	=> '500px',
)
);

Redux::set_section($this->opt_name, array(
	'title' 	=> esc_html__('Header', 'streamit'),
	'id' 		=> 'header_variation',
	'subsection'=> true,
	'desc' 		=> esc_html__('This section contains options for header', 'streamit'),
	'icon' 		=> 'custom-Header-layout',
	'fields' 	=> array(
		// --------main header background options start----------//

		array(
			'id' => 'st_header_background_type',
			'type' => 'button_set',
			'title' => esc_html__('Background', 'streamit'),
			'subtitle' => esc_html__('Select the variation for header background', 'streamit'),
			'options' => array(
				'default' => esc_html__('Default', 'streamit'),
				'color' => esc_html__('Color', 'streamit'),
				'image' => esc_html__('Image', 'streamit')
			),
			'default' => 'default',
		),

		array(
			'id' => 'st_header_background_color',
			'type' => 'color',
			'title' => esc_html__('Header Color', 'streamit'),
			'subtitle' => esc_html__('Choose Header Background Color', 'streamit'),
			'required' => array(array('header_layout', '!=', 'custom'), array('st_header_background_type', '=', 'color')),
			"class" => "css_prefix-sub-fields",
			'mode' => 'background',
			'transparent' => false,
		),

		array(
			'id' => 'st_header_background_image',
			'type' => 'media',
			'url' => false,
			'desc' => '<i class="custom-info"></i><span class="media-label">' . esc_html__(' Upload your background image here', 'streamit') . '</span>',
			'required' => array('st_header_background_type', '=', 'image'),
			"class" => "css_prefix-sub-fields",
			'read-only' => false,
			'title' => esc_html__('background image', 'streamit'),
			'subtitle' => esc_html__('Upload background image for header.', 'streamit'),
		),

		// --------main header Background options end----------//

		// --------main header Search Options start----------//

		array(
			'id' 		=> 'header_display_search',
			'type' 		=> 'button_set',
			'title' 	=> esc_html__('Display Search Icon', 'streamit'),
			'subtitle' 	=> esc_html__('Turn on to display the Search in header.', 'streamit'),
			'options' 	=> array(
				'yes' 	=> esc_html__('On', 'streamit'),
				'no' 	=> esc_html__('Off', 'streamit')
			),
			'default' 	=> 'yes',
		),

		array(
			'id' 		=> 'header_search_text',
			'type' 		=> 'text',
			'title' 	=> esc_html__('Enter search label', 'streamit'),
			'subtitle' 	=> esc_html__('This label appears on the header near the search', 'streamit'),
			'required' 	=> array('header_display_search', '=', 'yes'),
			"class" 	=> "css_prefix-sub-fields",
			'validate' 	=> 'text',
			'default' 	=> 'Search',
		),

		// --------main header Search Options end----------//

		// --------main header Icone Options start----------//

		array(
			'id' 		=> 'header_display_cart',
			'type' 		=> 'button_set',
			'title' 	=> esc_html__('Display Cart Icon', 'streamit'),
			'subtitle' 	=> esc_html__('Turn on to display the cart in header.', 'streamit'),
			'options' 	=> array(
				'yes' 	=> esc_html__('On', 'streamit'),
				'no' 	=> esc_html__('Off', 'streamit')
			),
			'default' 	=> 'yes',
		),

		array(
			'id' 		=> 'header_display_user_act',
			'type' 		=> 'button_set',
			'title' 	=> esc_html__('Display User Account Icon', 'streamit'),
			'subtitle' 	=> esc_html__('Turn on to display the user account in header.', 'streamit'),
			'options' 	=> array(
				'yes' 	=> esc_html__('On', 'streamit'),
				'no' 	=> esc_html__('Off', 'streamit')
			),
			'default' => 'yes',
		),

		array(
			'id' 		=> 'streamit_headre_button',
			'type' 		=> 'button_set',
			'title'	 	=> esc_html__('Show Button', 'streamit'),
			'subtitle' 	=> esc_html__('Turn on to display the button in header.', 'streamit'),
			'options' 	=> array(
				'yes' 	=> 'Yes',
				'no' 	=> 'No'
			),
			'default' => 'yes'
		)

		// --------main header Icone Options end----------//
	)
)
);


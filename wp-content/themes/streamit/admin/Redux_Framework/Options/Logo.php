<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


/**
 * streamit\streamit\Redux_Framework\Options\Logo class
 *
 * @package streamit
 */

Redux::set_section( $this->opt_name, array(
	'title' => esc_html__('Branding', 'streamit'),
	'desc' => esc_html__('This section contains options for logo', 'streamit'),
	'id' => 'header-logo',
	'has_group_title' => __("Design System", "streamit"),
	'icon' => 'custom-Branding',
	'fields' => array(

		array(
			'id' 		=> 'header_radio',
			'type' 		=> 'button_set',
			'title' 	=> esc_html__('Select Logo Type', 'streamit'),
			'options' 	=> array(
				'1' 		=> esc_html__('Logo as Text' , 'streamit'),
				'2' 		=> esc_html__('Logo as Image', 'streamit')
			),
			'default' 	=> '2'
		),

		array(
			'id' 		=> 'header_text',
			'type'		=> 'text',
			'title' 	=> esc_html__('Logo text', 'streamit'),
			'subtitle' 	=> esc_html__('Enter the text to be used instead of the logo image', 'streamit'),
			'required'	=> array('header_radio', '=', '1'),
			'msg' 		=> esc_html__('Please enter correct value', 'streamit'),
			'default' 	=> esc_html__('Streamit', 'streamit'),
		),

		array(
			'id' 		=> 'header_color',
			'type' 		=> 'color',
			'title' 	=> esc_html__('Text color', 'streamit'),
			'subtitle' 	=> esc_html__('Choose text color', 'streamit'),
			'required' 	=> array('header_radio', '=', '1'),
			'default' 	=> '',
			'mode' 		=> 'background',
			'transparent' => false
		),

		array(
			'id'	 	=> 'streamit_logo',
			'type' 		=> 'media',
			'url' 		=> false,
			'title' 	=> esc_html__('Logo', 'streamit'),
			'required' 	=> array('header_radio', '=', '2'),
			'read-only' => false,
			'default' 	=> array('url' => get_template_directory_uri() . '/static/assets/images/logo.png'),
			'subtitle' 	=> esc_html__('Upload Logo image for your Website. Otherwise site title will be displayed in place of Logo.', 'streamit'),
			'desc' 		=> '<i class="custom-info"></i><span class="media-label">' . esc_html__(' Upload your logo image', 'streamit') . '</span>',
		),

		array(
			'id' 		=> 'logo-dimensions',
			'type' 		=> 'dimensions',
			'units' 	=> array('em', 'px', '%'),  
			'units_extended' => 'true', 
			'title' 	=> esc_html__('Logo (Width/Height) Option', 'streamit'),
			'subtitle' 	=> esc_html__('Allows you to choose width, height, and/or unit.', 'streamit'),
			'height_label' 	=> 'Height',
			'width_label' 	=> 'Width',
			'units_label' 	=> 'Choose option',
			'required' 	=> array('header_radio', '=', '2'),
		),

	)
) );

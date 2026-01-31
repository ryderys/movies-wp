<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}


/**
 * streamit\streamit\Redux_Framework\Options\General class
 *
 * @package streamit
 */

Redux::set_section(
	$this->opt_name,
	array(
		'title' => esc_html__('Body Layout', 'streamit'),
		'desc' => esc_html__('This section contains body container related options.', 'streamit'),
		'id' => 'general',
		'icon' => 'custom-General',
		'fields' => array(
			array(
				'id' 		=> 'grid_container',
				'type' 		=> 'dimensions',
				'units' 	=> array('em', 'px', '%'),
				'height' 	=> false,
				'width' 	=> true,
				'title' 	=> esc_html__('Container Width', 'streamit'),
				'subtitle' 	=> esc_html__('Adjust Your Site Container Width With Help Of Above Option.', 'streamit'),
				'height_label' 	=> 'Height',
				'width_label' 	=> 'Width',
				'units_label' 	=> 'Choose option',
				'default' 	=> array(
					'width' => '87.5em',
					'units' => 'em'
				),
			),

			array(
				'id' 		=> 'layout_set',
				'type' 		=> 'button_set',
				'title' 	=> esc_html__('Body Background', 'streamit'),
				'subtitle' 	=> esc_html__('Select this option for body background.', 'streamit'),
				'options' 	=> array(
					'1' 		=> 'Color',
					'2' 		=> 'Default',
					'3' 		=> 'Image'
				),
				'default' 	=> '2',
			),

			array(
				'id' 		=> 'streamit_layout_color',
				'type' 		=> 'color',
				'title' 	=> esc_html__('Background color.', 'streamit'),
				"class" 	=> "css_prefix-sub-fields",
				'subtitle' 	=> esc_html__('Choose body background color', 'streamit'),
				'required' 	=> array('layout_set', '=', '1'),
				'default' 	=> '',
				'mode' 		=> 'background',
				'transparent' => false
			),

			array(
				'id' 		=> 'streamit_layout_card_bg_color',
				'type' 		=> 'color',
				'title' 	=> esc_html__('Card Background color.', 'streamit'),
				"class" 	=> "css_prefix-sub-fields",
				'subtitle' 	=> esc_html__('Choose body background color', 'streamit'),
				'required' 	=> array('layout_set', '=', '1'),
				'default' 	=> '',
				'mode' 		=> 'background',
				'transparent' => false
			),

			array(
				'id' 		=> 'streamit_layout_image',
				'type' 		=> 'media',
				'url' 		=> false,
				'read-only' => false,
				'required' 	=> array('layout_set', '=', '3'),
				'title'	 	=> esc_html__('Background image.', 'streamit'),
				'subtitle' 	=> esc_html__('Choose body background image.', 'streamit'),
				'class' 	=> 'css_prefix-sub-fields',
				'desc' 		=> '<i class="custom-info"></i><span class="media-label">' . esc_html__(' Upload your body background image', 'streamit') . '</span>',
			),

			array(
				'id' 		=> 'is_page_spacing',
				'type' 		=> 'button_set',
				'title' 	=> esc_html__('Page Spacing', 'streamit'),
				'subtitle' 	=> esc_html__('Adjust top / bottom spacing of your site pages.', 'streamit'),
				'options' 	=> array(
					'default' 	=> 'Default',
					'custom' 	=> 'Custom',
				),
				'default' => 'default',
			),

			// page top spacing
			array(
				'id' => 'page_spacing',
				'type' => 'spacing',
				'mode' => 'absolute',
				'units' => array('em', 'px', '%'),
				'all' => false,
				"class" => "css_prefix-sub-fields",
				'top' => true,
				'right' => false,
				'bottom' => true,
				'left' => false,
				'default' => array(
					'top' => '3.75',
					'bottom' => '3.75',
					'units' => 'em'
				),
				'top_label' => 'Top',
				'bottom_label' => 'Butoon',
				'right_label' => 'Right',
				'left_label' => 'Left',
				'units_label' => 'Choose option',
				'title' => esc_html__('Top / Bottom Spacing', 'streamit'),
				'subtitle' => esc_html__('Choose Top / Bottom spacing', 'streamit'),
				'required' => array('is_page_spacing', '=', 'custom'),
			),
			array(
				'id' => 'tablet_page_spacing',
				'type' => 'spacing',
				'mode' => 'absolute',
				'units' => array('em', 'px', '%'),
				'all' => false,
				"class" => "css_prefix-sub-fields",
				'top' => true,
				'right' => false,
				'bottom' => true,
				'left' => false,
				'default' => array(
					'top' => '2',
					'bottom' => '2',
					'units' => 'em'
				),
				'top_label' => 'Top',
				'bottom_label' => 'Butoon',
				'right_label' => 'Right',
				'left_label' => 'Left',
				'units_label' => 'Choose option',
				'title' => esc_html__('Top / Bottom Spacing for Tablet', 'streamit'),
				'subtitle' => esc_html__('Choose Top / Bottom spacing', 'streamit'),
				'required' => array('is_page_spacing', '=', 'custom'),
			),
			array(
				'id' => 'mobile_page_spacing',
				'type' => 'spacing',
				'mode' => 'absolute',
				'units' => array('em', 'px', '%'),
				'all' => false,
				"class" => "css_prefix-sub-fields",
				'top' => true,
				'right' => false,
				'bottom' => true,
				'left' => false,
				'default' => array(
					'top' => '3.125',
					'bottom' => '3.125',
					'units' => 'em'
				),
				'top_label' => 'Top',
				'bottom_label' => 'Butoon',
				'right_label' => 'Right',
				'left_label' => 'Left',
				'units_label' => 'Choose option',
				'title' => esc_html__('Top / Bottom Spacing for Mobile', 'streamit'),
				'subtitle' => esc_html__('Choose Top / Bottom spacing', 'streamit'),
				'required' => array('is_page_spacing', '=', 'custom'),
			),

			array(
				'id' 		=> 'back_to_top_btn',
				'type' 		=> 'button_set',
				'title' 	=> esc_html__('Display back to top button', 'streamit'),
				'options' 	=> array(
					'yes' 	=> esc_html__('Yes', 'streamit'),
					'no' 	=> esc_html__('No', 'streamit')
				),
				'default' 	=> 'yes',
			),
		)
	)
);

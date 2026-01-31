<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Standard metabox.
Redux_Metaboxes::set_box(
	$this->opt_name,
	array(
		'id' => 'page_options',
		'title' => esc_html__('Page Options', 'streamit'),
		'post_types' => array('page', 'post'),
		'position' => 'normal', // normal, advanced, side.
		'priority' => 'high',   // high, core, default, low.
		'sections' => array(
			array(
				'title' => esc_html__('Banner Settings', 'streamit'),
				'id' => 'banner_settings',
				'fields' => array(
					array(
						'id' => 'display_banner_meta',
						'type' => 'button_set',
						'title' => esc_html__('Display Banner', 'streamit'),
						'options' => array(
							'default' => esc_html__('Default', 'streamit'),
							'yes' => esc_html__('Yes', 'streamit'),
							'no' => esc_html__('No', 'streamit'),
						),
						'default' => 'default',
					),
				),
			),
			array(
				'title' => esc_html__('Header Settings', 'streamit'),
				'id' => 'header_settings',
				'fields' => array(
					array(
						'id' => 'display_header_meta',
						'type' => 'button_set',
						'title' => esc_html__('Display Header', 'streamit'),
						'options' => array(
							'default' => esc_html__('Default', 'streamit'),
							'yes' => esc_html__('Yes', 'streamit'),
							'no' => esc_html__('No', 'streamit'),
						),
						'default' => 'default',
					),
					
					array(
						'id' => 'select_header_logo_meta',
						'type' => 'media',
						'title' => esc_html__('Header Logo', 'streamit'),
						'default' => 'default',
						'required' => array('display_header_meta', '=', 'yes'),
					),
				),
			),
			array(
				'title' => esc_html__('Footer Settings', 'streamit'),
				'id' => 'footer_settings_meta',
				'fields' => array(
					array(
						'id' => 'display_footer_meta',
						'type' => 'button_set',
						'title' => esc_html__('Display Footer', 'streamit'),
						'options' => array(
							'default' => esc_html__('Default', 'streamit'),
							'yes' => esc_html__('Yes', 'streamit'),
							'no' => esc_html__('No', 'streamit'),
						),
						'default' => 'default',
					),
					array(
						'id' => 'default_footer_meta',
						'type' => 'button_set',
						'title' => esc_html__('Customize Footer', 'streamit'),
						'options' => array(
							'6' => esc_html__('Default', 'streamit'),
							'1' => esc_html__('One Column', 'streamit'),
							'2' => esc_html__('Two Columns', 'streamit'),
							'3' => esc_html__('Three Columns', 'streamit'),
							'4' => esc_html__('Four Columns', 'streamit'),
							'5' => esc_html__('Five Columns', 'streamit'),
						),
						'default' => '6',
						'required' => array('display_footer_meta', '=', 'yes'),
					),
					array(
						'id' => 'select_footer_bg_meta',
						'type' => 'color',
						'title' => esc_html__('Background Color', 'streamit'),
						'default' => 'default',
						'required' => array('display_footer_meta', '=', 'yes'),
					),
					array(
						'id' => 'select_footer_logo_meta',
						'type' => 'media',
						'title' => esc_html__('Footer Logo', 'streamit'),
						'url' => false,
						'subtitle' => esc_html__('Only available with "Streamit Footer Logo" widget', 'streamit'),
						'required' => array('display_footer_meta', '=', 'yes'),
					),
				),
			),
			array(
				'title' => esc_html__('Color Palette', 'streamit'),
				'id' => 'color_pallet_settings_meta',
				'fields' => array(
					array(
						'id' => 'display_color_pallet_meta',
						'type' => 'button_set',
						'title' => esc_html__('Color Palette', 'streamit'),
						'options' => array(
							'default' => esc_html__('Default', 'streamit'),
							'yes' => esc_html__('Yes', 'streamit'),
						),
						'default' => 'default',
					),
					array(
						'id' => 'primary_color_meta',
						'type' => 'color',
						'mode' => 'background',
						'transparent' => false,
						'title' => esc_html__('Primary Color', 'streamit'),
						'default' => 'default',
						'required' => array('display_color_pallet_meta', '=', 'yes'),
					),
					array(
						'id' => 'secondary_color_meta',
						'type' => 'color',
						'title' => esc_html__('Secondary Color', 'streamit'),
						'mode' => 'background',
						'transparent' => false,
						'default' => 'default',
						'required' => array('display_color_pallet_meta', '=', 'yes'),
					),
					array(
						'id' => 'title_color_meta',
						'type' => 'color',
						'title' => esc_html__('Title Color', 'streamit'),
						'mode' => 'background',
						'transparent' => false,
						'default' => 'default',
						'required' => array('display_color_pallet_meta', '=', 'yes'),
					),
					array(
						'id' => 'page_text_color_meta',
						'type' => 'color',
						'title' => esc_html__('Page Text Color', 'streamit'),
						'mode' => 'background',
						'transparent' => false,
						'default' => 'default',
						'required' => array('display_color_pallet_meta', '=', 'yes'),
					),
				),
			),
		),
	)
);
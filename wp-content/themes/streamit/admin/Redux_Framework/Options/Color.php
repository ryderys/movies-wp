<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * streamit\streamit\Redux_Framework\Options\Color class
 *
 * @package streamit
 */
Redux::set_section($this->opt_name, array(
	'title' => esc_html__('Global colors', 'streamit'),
	'id' => 'color',
	'icon' => 'custom-Colors',
	'desc' => esc_html__('Change default colors of the site.', 'streamit'),
	'fields' => array(

		array(
			'id' 		=> 'custom_color_switch',
			'type' 		=> 'button_set',
			'title'	 	=> esc_html__('Set custom colors', 'streamit'),
			'options' 	=> array(
				'yes' 	=> 'Yes',
				'no' 	=> 'No'
			),
			'default' => 'no'
		),

		array(
			'id' 		=> 'primary_color',
			'type' 		=> 'color',
			'title' 	=> esc_html__('Primary color', 'streamit'),
			'subtitle' 	=> esc_html__('Select primary accent color.', 'streamit'),
			'mode' 		=> 'background',
			'transparent' => false,
			'required' 	=> array('custom_color_switch', '=', 'yes')
		),

		array(
			'id' 			=> 'title_color',
			'type' 			=> 'color',
			'title' 		=> esc_html__('Title Color', 'streamit'),
			'subtitle' 		=> esc_html__('Select default Title(Headings) color', 'streamit'),
			'mode' 			=> 'background',
			'transparent' 	=> false,
			'required'	 	=> array('custom_color_switch', '=', 'yes')
		),

		array(
			'id' 			=> 'text_color',
			'type' 			=> 'color',
			'title'	 		=> esc_html__('Body text color', 'streamit'),
			'subtitle' 		=> esc_html__('Select default body text color', 'streamit'),
			'mode' 			=> 'background',
			'transparent' 	=> false,
			'required' 		=> array('custom_color_switch', '=', 'yes')
		),

	)
)
);

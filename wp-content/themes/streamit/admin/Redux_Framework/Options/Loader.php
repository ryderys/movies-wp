<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


/**
 * streamit\streamit\Redux_Framework\Options\Loader class
 *
 * @package streamit
 */

Redux::set_section($this->opt_name, array(
	'title' => esc_html__('Loader', 'streamit'),
	'desc' => esc_html__('This section contains options for loader.', 'streamit'),
	'id' => 'header-loader',
	'icon' => 'custom-loader',
	'fields' => array(
		array(
			'id' 		=> 'streamit_display_loader',
			'type' 		=> 'button_set',
			'title' 	=> esc_html__('Loader', 'streamit'),
			'subtitle' 	=> wp_kses('Turn on to show the icon/images loading animation while your site loads', 'streamit'),
			'options'	=> array(
				'yes' 		=> esc_html__('Yes', 'streamit'),
				'no' 		=> esc_html__('No', 'streamit')
			),
			'default' 	=> 'no'
		),

		array(
			'id' 		=> 'streamit_loader_gif',
			'type' 		=> 'media',
			'url'	 	=> true,
			'title' 	=> esc_html__('Add GIF image for loader', 'streamit'),
			'read-only' => false,
			'required' 	=> array('streamit_display_loader', '=', 'yes'),
			'default' 	=> array('url' => get_template_directory_uri() . '/admin/assets/images/redux/loader.gif'),
			'subtitle'	=> esc_html__('Upload Loader GIF image for your Website.', 'streamit'),
			'desc' 		=> '<i class="custom-info"></i><span class="media-label">' . esc_html__(' Upload your loader', 'streamit') . '</span>',
		),

		array(
			'id' 		=> 'loader-dimensions',
			'type' 		=> 'dimensions',
			'units' 	=> array('em', 'px', '%'),
			'units_extended' => 'true',
			'required' 	=> array('streamit_display_loader', '=', 'yes'),
			'height_label' 	=> 'Height',
			'width_label' 	=> 'Width',
			'units_label' 	=> 'Choose option',
			'height_label' 	=> 'Height',
			'width_label' 	=> 'Width',
			'units_label' 	=> 'Choose option',
			'title' 	=> esc_html__('Loader (Width/Height) Option', 'streamit'),
			'subtitle' 	=> esc_html__('Allows you to choose width, height, and/or unit.', 'streamit'),
			'desc' 		=> esc_html__('You can enable or disable any piece of this field. Width, Height, or Units.', 'streamit'),
		),
	)
)
);

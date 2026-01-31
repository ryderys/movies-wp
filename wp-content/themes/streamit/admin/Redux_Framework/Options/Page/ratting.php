<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


// Streamit Rating Subsection
Redux::set_section($this->opt_name, array(
	'title'       => esc_html__('Rating', 'streamit'),
	'id'          => 'custom-text-options',
	'subsection'  => true,
	'icon'        => 'custom-Rating',
	'fields'      => array(
		array(
			'id'       => 'streamit_imdb_display_logo',
			'type'     => 'media',
			'url'      => false,
			'title'    => esc_html__('Set IMDB Logo', 'streamit'),
			'read-only' => false,
			'default'  => array(
				'url' => get_template_directory_uri() . '/admin/assets/images/redux/IMDB_Logo.svg',
			),
		),
	)
));
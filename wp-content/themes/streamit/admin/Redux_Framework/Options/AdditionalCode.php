<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


/**
 * streamit\streamit\Redux_Framework\Options\Additional Code
 *
 * @package streamit
 */
Redux::set_section( $this->opt_name, array(
	'title' => esc_html__( 'Additional Code', 'streamit' ),
	'id'    => 'additional-Code',
	'icon'  => 'custom-Code',
	'desc'  => esc_html__('This section contains options for header.','streamit'),
	'fields'=> array(

		array(
			'id'       => 'css_code',
			'type'     => 'ace_editor',
			'title'    => esc_html__('CSS Code','streamit'),
			'subtitle'     => esc_html__('Paste your CSS code here.','streamit'),
			'mode'     => 'css'
		),

		array(
			'id'       => 'js_code',
			'type'     => 'ace_editor',
			'title'    => esc_html__('JS Code','streamit'),
			'subtitle'     => esc_html__('Paste custom JS code here.','streamit'),
			'mode'     => 'javascript',
			'theme'   => 'chrome'
		),
	)
));

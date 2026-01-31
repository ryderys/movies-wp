<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Streamit Cast Subsection
Redux::set_section($this->opt_name, array(
	'title'       => esc_html__('Cast & Crew', 'streamit'),
	'id'          => 'single-custom-post-cast-options',
	'subsection'  => true,
	'icon'        => 'custom-cast',
	'fields'      => array(
        array(
            'id'        => 'streamit_display_cast',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Cast', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the Cast ', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),

        array(
            'id'        => 'streamit_cast_title',
            'type'      => 'text',
            'required'  => array('streamit_display_cast', '=', 'yes'),
            'title'     => esc_html__('Cast button title', 'streamit'),
            "class"     => "css_prefix-sub-fields",
            'default'   => 'Starring',
        ),

        array(
            'id'        => 'streamit_display_crew',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Crew', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the Crew ', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),

        array(
            'id'        => 'streamit_crew_title',
            'type'      => 'text',
            'required'  => array('streamit_display_crew', '=', 'yes'),
            'title'     => esc_html__('Crew button title', 'streamit'),
            "class"     => "css_prefix-sub-fields",
            'default'   => 'Crew',
        ),
	)
));


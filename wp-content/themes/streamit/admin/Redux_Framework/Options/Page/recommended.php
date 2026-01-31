<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


Redux::set_section($this->opt_name, array(
    'title'         => esc_html__('Recommeded', 'streamit'),
    'id'            => 'single-custom-post-recommeded-options',
    'icon'          => 'custom-Recommended',
    'subsection'    => true,
    'fields'        => array(
        array(
            'id'        => 'streamit_display_recommended',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Recommended', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the Recommended ', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),
        
        array(
            'id'        => 'streamit_recommended_title',
            'type'      => 'text',
            'required'  => array('streamit_display_recommended', '=', 'yes'),
            'title'     => esc_html__('Recommended button title', 'streamit'),
            'default'   => 'Recommended',
            "class"     => "css_prefix-sub-fields",
        ),
    )
));

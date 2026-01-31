<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Streamit Cast Subsection
Redux::set_section($this->opt_name, array(
    'title'       => esc_html__('Badge', 'streamit'),
    'id'          => 'single-custom-post-badge-options',
    'subsection'  => true,
    'icon'        => 'icon-badge',
    'fields'      => array(
        array(
            'id'        => 'streamit_recommended_enable_premium_badges',
            'type'      => 'button_set',
            'title'     => esc_html__('Enable Premium & Pay Per View Badges', 'streamit'),
            'subtitle'  => esc_html__('Show or hide badges only on single and archive pages.', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('Yes', 'streamit'),
                'no'    => esc_html__('No', 'streamit')
            ),
            'default'   => 'yes',
            "class"     => "css_prefix-sub-fields",
        ),

        array(
            'id'        => 'streamit_recommended_premium_badge_color',
            'type'         => 'color',
            'title'     => esc_html__('Change Color Of Premium Badges', 'streamit'),
            'mode'         => 'background',
            'transparent' => false,
            "class"     => "css_prefix-sub-fields",
            'required'  => array('streamit_recommended_enable_premium_badges', '=', 'yes'),
            'default'       => '#FFD81C'
        ),

        array(
            'id'        => 'streamit_recommended_ppv_badge_color',
            'type'         => 'color',
            'title'     => esc_html__('Change Color Of Pay Per View Badges', 'streamit'),
            'mode'         => 'background',
            'transparent' => false,
            "class"     => "css_prefix-sub-fields",
            'required'  => array('streamit_recommended_enable_premium_badges', '=', 'yes'),
            'default'       => '#1e73be'
        ),

        array(
            'id'        => 'streamit_recommended_ppv_rented_badge_color',
            'type'         => 'color',
            'title'     => esc_html__('Change Color Of Pay Per View Rented Badges', 'streamit'),
            'mode'         => 'background',
            'transparent' => false,
            "class"     => "css_prefix-sub-fields",
            'required'  => array('streamit_recommended_enable_premium_badges', '=', 'yes'),
            'default'       => '#008000'
        ),

        array(
            'id'        => 'streamit_recommended_enable_upcoming_badges',
            'type'      => 'button_set',
            'title'     => esc_html__('Enable Coming Soon Badges', 'streamit'),
            'subtitle'  => esc_html__('Show or hide oming Soon badges only on single and archive pages.', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('Yes', 'streamit'),
                'no'    => esc_html__('No', 'streamit')
            ),
            'default'   => 'yes',
            "class"     => "css_prefix-sub-fields",
        ),

        array(
            'id'        => 'streamit_recommended_upcoming_badge_color',
            'type'         => 'color',
            'title'     => esc_html__('Change Color Of oming Soon Badges', 'streamit'),
            'mode'         => 'background',
            'transparent' => false,
            "class"     => "css_prefix-sub-fields",
            'required'  => array('streamit_recommended_enable_upcoming_badges', '=', 'yes'),
            'default'       => '#e50914'
        ),
    )
));

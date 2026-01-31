<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!is_admin())
    return;
// Streamit Cast Subsection
Redux::set_section($this->opt_name, array(
    'title'       => esc_html__('Subscribe Button', 'streamit'),
    'id'          => 'single-custom-subscribe-options',
    'subsection'  => true,
    'icon'        => 'icon-online',
    'fields'      => array(
        array(
            'id'        => 'streamit_subscribe_button',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Subscribe Button', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display Subscribe Button ', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),

        array(
            'id'        => 'streamit_icon',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Icon', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display icon ', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),

        array(
            'id'        => 'streamit_subscribe_text',
            'type'      => 'text',
            'title'     => esc_html__('Subscribe Text', 'streamit'),
            'default'   => esc_html__('Subscribe', 'streamit'),
            'required'     => array('streamit_subscribe_button', '=', 'yes'),
        ),

        array(
            'id'        => 'streamit_subscribe_text_color',
            'type'         => 'color',
            'title'     => esc_html__('Change subscribe text color', 'streamit'),
            'required'     => array('streamit_subscribe_button', '=', 'yes'),
            'mode'         => 'background',
            'transparent' => false,
        ),

        array(
            'id'        => 'streamit_subscribe_background_color',
            'type'         => 'color',
            'title'     => esc_html__('Change subscribe button color', 'streamit'),
            'required'     => array('streamit_subscribe_button', '=', 'yes'),
            'mode'         => 'background',
            'transparent' => false,
        ),

        array(
            'id'        => 'streamit_subscribe_hover_color',
            'type'         => 'color',
            'title'     => esc_html__('Change subscribe button hover color', 'streamit'),
            'required'     => array('streamit_subscribe_button', '=', 'yes'),
            'mode'         => 'background',
            'transparent' => false,
        ),

        array(
            'id'        => 'streamit_subscribe_page',
            'type'      => 'select',
            'multi'     => false,
            'data'      => 'pages',
            'title'     => esc_html__('Select subscribe Page', 'streamit'),
            'required'     => array('streamit_subscribe_button', '=', 'yes'),
        ),

    )
));

<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


Redux::set_section($this->opt_name, array(
    'title'         => esc_html__('View Counter', 'streamit'),
    'id'            => 'single-page-view-counter',
    'icon'          => 'custom-View',
    'subsection'    => true,
    'fields'        => array(
        array(
            'id'        => 'streamit_show_viewcounter',
            'type'      => 'button_set',
            'title'     => esc_html__('Display View Count On Single Page', 'streamit'),
            'subtitle'  => esc_html__('This option Provide show or Hide View Count In single Page', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),
    )
));

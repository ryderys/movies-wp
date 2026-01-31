<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


Redux::set_section($this->opt_name, array(
    'title'         => esc_html__('Upcoming', 'streamit'),
    'id'            => 'single-custom-post-upcoming-options',
    'icon'          => 'custom-Upcoming',
    'subsection'    => true,
    'fields'        => array(

        array(
            'id'        => 'streamit_display_upcoming',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Upcoming', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the Upcoming ', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),
        array(
            'id'        => 'streamit_upcoming_title',
            'type'      => 'text',
            'required'  => array('streamit_display_upcoming', '=', 'yes'),
            'title'     => esc_html__('Upcoming Product Heading', 'streamit'),
            'default'   => 'Upcoming',
        ),
        array(
            'id'       => 'streamit_upcoming_multi_select',
            'type'     => 'select',
            'multi'    => true,
            'title'    => __('Select Post Type', 'streamit'),
            'subtitle' => __('Select specific Post to display upcoming List', 'streamit'),
            'options'   => array(
                'movie' => 'Movie',
                'video' => 'Video',
                'tv_show' => 'TVshow'
            ),
            'default'  => array('movie', 'video', 'tv_show'),
            'required' => array('streamit_display_upcoming', '=', 'yes'),
        ),
    )
));

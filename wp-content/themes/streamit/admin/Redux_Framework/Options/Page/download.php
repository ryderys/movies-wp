<?php

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

Redux::set_section($this->opt_name, array(
    'title'      => esc_html__('Download Button', 'streamit'),
    'id'         => 'single-page-show-download-btn',
    'icon'       => 'custom-Download',
    'subsection' => true,
    'fields'     => array(
        array(
            'id'       => 'streamit_display_download',
            'type'     => 'button_set',
            'title'    => esc_html__('Allow Download?', 'streamit'),
            'subtitle' => esc_html__('Choose if you want to allow users to see the download button.', 'streamit'),
            'options'  => array(
                'yes' => esc_html__('On', 'streamit'),
                'no'  => esc_html__('Off', 'streamit'),
            ),
            'default'  => 'yes',
        ),
        array(
            'id'       => 'streamit_allow_guest_download',
            'type'     => 'button_set',
            'title'    => esc_html__('Allow Non-Logged-In Users?', 'streamit'),
            'subtitle' => esc_html__('Choose if guests can download without logging in.', 'streamit'),
            'options'  => array(
                'yes' => esc_html__('Yes', 'streamit'),
                'no'  => esc_html__('No', 'streamit'),
            ),
            'default'  => 'no', // Default = only logged-in users can download
            'required' => array('streamit_display_download', '=', 'yes'), // Show only if main download option is ON
        ),
    ),
));

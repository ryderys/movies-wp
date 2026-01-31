<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

Redux::set_section($this->opt_name, array(
    'title'         => esc_html__('Related Videos', 'streamit'),
    'id'            => 'single-custom-post-related-video-options',
    'icon'          => 'custom-Related-video',
    'subsection'    => true,
    'fields'        => array(
        array(
            'id'        => 'streamit_display_related_video',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Related Videos', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the Related Videos', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),

        array(
            'id'        => 'streamit_display_related_video_title',
            'type'      => 'text',
            'required'  => array('streamit_display_related_video', '=', 'yes'),
            'class'     => 'css_prefix-sub-fields',
            'title'     => esc_html__('Related Video title', 'streamit'),
            'default'   => esc_html__('Related Videos' , 'streamit'),

        ),
    )
));

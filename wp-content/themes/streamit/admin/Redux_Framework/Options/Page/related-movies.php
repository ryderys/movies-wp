<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


Redux::set_section($this->opt_name, array(
    'title'         => esc_html__('Related Movies', 'streamit'),
    'id'            => 'single-custom-post-related-movie-options',
    'icon'          => 'custom-Related-Movies',
    'subsection'    => true,
    'fields'        => array(
        array(
            'id'        => 'streamit_display_related_movie',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Related Movies', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the Related Movies', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),
     
        array(
            'id'        => 'streamit_display_related_movie_title',
            'type'      => 'text',
            'required'  => array('streamit_display_related_movie', '=', 'yes'),
            'title'     => esc_html__('Related button title', 'streamit'),
            'class'     => 'css_prefix-sub-fields',
            'default'   => esc_html__('Recommended Movies' , 'streamit'),
        ),
    )
));

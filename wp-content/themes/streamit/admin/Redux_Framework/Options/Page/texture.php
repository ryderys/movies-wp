<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


Redux::set_section($this->opt_name, array(
    'title'         => esc_html__('Big Heading Texture', 'streamit'),
    'id'            => 'custom-text-bigheading-options',
    'icon'          => 'custom-texture',
    'subsection'    => true,
    'fields'        => array(
        array(
            'id'       => 'streamit_big_heading_title_bg_type',
            'type'     => 'button_set',
            'title'    => esc_html__('Set Heading Texture Background Type', 'streamit'),
            'options'  => array(
                '1'    => 'Color',
                '2'    => 'Image',
            ),
            'default'  => '2'
        ),

        array(
            'id'            => 'streamit_big_heading_title_bg_color',
            'type'          => 'color',
            'class'         => 'css_prefix-sub-fields',
            'title'         => esc_html__('Set Heading Texture Color', 'streamit'),
            'required'      => array('streamit_big_heading_title_bg_type', '=', '1'),
            'mode'          => 'background',
            'transparent'   => false,
            'default'       => '#fff'
        ),


        array(
            'id'        => 'streamit_big_heading_title_banner_image',
            'type'      => 'media',
            'url'       => false,
            'title'     => esc_html__('Set Heading Texture Image', 'streamit'),
            'read-only' => false,
            'class'     => 'css_prefix-sub-fields',
            'required'  => array('streamit_big_heading_title_bg_type', '=', '2'),
            'subtitle'  => esc_html__('Upload Image for your heading texture background.', 'streamit'),
            'default'   => array('url' => get_template_directory_uri() . '/admin/assets/images/redux/texture.jpg'),
        ),
    )
));

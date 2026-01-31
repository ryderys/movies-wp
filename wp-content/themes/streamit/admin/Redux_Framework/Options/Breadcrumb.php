<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


/**
 * streamit\streamit\Redux_Framework\Options\Breadcrumb class
 *
 * @package streamit
 */
Redux::set_section($this->opt_name, array(
    'title' => esc_html__('Breadcrumb', 'streamit'),
    'id' => 'breadcrumb',
    'icon' => 'custom-Breadcrumb',
    'desc' => esc_html__('This section contains options for Page Breadcrumb.', 'streamit'),
    'fields' => array(

        array(
            'id' => 'display_breadcrumb',
            'type' => 'button_set',
            'title' => esc_html__('Display breadcrumb', 'streamit'),
            'options' => array(
                'yes' => esc_html__('Yes', 'streamit'),
                'no' => esc_html__('No', 'streamit')
            ),
            'default' => 'yes'
        ),

        array(
            'id' => 'breadcrumb_style',
            'type' => 'image_select',
            'title' => esc_html__('Select breadcrumb Style', 'streamit'),
            'subtitle' => esc_html__('Select the style that best fits your needs.', 'streamit'),
            'options' => array(
                'one' => array(
                    'title' => esc_html__('Center alignment', 'streamit'),
                    'img' => get_template_directory_uri() . '/admin/assets/images/redux/title-dark-1.png',
                    'class' => 'title-1'
                ),
                'two' => array(
                    'title' => esc_html__('Left alignment', 'streamit'),
                    'img' => get_template_directory_uri() . '/admin/assets/images/redux/title-dark-2.png',
                    'class' => 'title-2'
                ),
                'three' => array(
                    'title' => esc_html__('Right alignment', 'streamit'),
                    'img' => get_template_directory_uri() . '/admin/assets/images/redux/title-dark-3.png',
                    'class' => 'title-3'
                ),
            ),
            'required' => array('display_breadcrumb', '=', 'yes'),
            'default' => 'one',
        ),

        array(
            'id' => 'page_default_breadcrumb_image',
            'type' => 'media',
            'url' => true,
            'title' => esc_html__('Breadcrumb Image', 'streamit'),
            'read-only' => false,
            'subtitle' => esc_html__('Upload breadcrumb image for your Website.', 'streamit'),
            'desc' => '<i class="custom-info"></i><span class="media-label">' . esc_html__(' Upload your breadcrumb image', 'streamit') . '</span>',
            'required' => array(
                array(
                    'display_breadcrumb',
                    '=',
                    'yes'
                ),
                array(
                    'breadcrumb_style',
                    '=',
                    array('2', '3')
                )
            ),
        ),

        array(
            'id' => 'breadcrumb_text_color',
            'type' => 'color',
            'title' => esc_html__('Breadcrumb Text color', 'streamit'),
            'subtitle' => esc_html__('Choose breadcrumb text color', 'streamit'),
            "class" => "css_prefix-sub-fields",
            'mode' => 'background',
            'transparent' => false
        ),

        array(
            'id' => 'breadcrumb_back_color',
            'type' => 'color',
            'title' => esc_html__('Background color', 'streamit'),
            'subtitle' => esc_html__('Choose breadcrumb background color', 'streamit'),
            "class" => "css_prefix-sub-fields",
            'mode' => 'background',
            'transparent' => false
        ),
    )
)
);

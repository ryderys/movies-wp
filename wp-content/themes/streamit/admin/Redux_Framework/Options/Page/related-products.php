<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


Redux::set_section($this->opt_name, array(
    'title'         => esc_html__('Related Product', 'streamit'),
    'id'            => 'single-custom-post-related-product-options',
    'icon'          => 'custom-Product',
    'subsection'    => true,
    'fields'        => array(
        array(
            'id'        => 'streamit_display_related_product',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Related Products', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the Related Products', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),
        array(
            'id'        => 'streamit_display_related_product_title',
            'type'      => 'text',
            'required'  => array('streamit_display_related_product', '=', 'yes'),
            'title'     => esc_html__('Related Product Heading', 'streamit'),
            'class'     => 'css_prefix-sub-fields',
            'default'   => 'Related Products',
        ),

        array(
            'id'        => 'streamit_show_related',
            'type'      => 'select',
            'multi'     => true,
            'title'     => __('Select Post Type', 'streamit'),
            'subtitle'  => __('Select specific Post to display related products', 'streamit'),
            'class'     => 'css_prefix-sub-fields',
            'options'   => array(
                'movie'     => 'Movie',
                'video'     => 'Video',
                'tv_show'   => 'Tv Show',
            ),
            'default'   => array('movie', 'video' , 'tv_show'),
            'required'  => array('streamit_display_related_product', '=', 'yes'),
        ),
    )
));

<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


/**
 * streamit\streamit\Redux_Framework\Options\WooCommerce
 *
 * @package streamit
 */
Redux::set_section($this->opt_name, array(
    'title' => esc_html__('WooCommerce', 'streamit'),
    'id'    => 'woocommerce',
    'icon'  => 'custom-Woo-commerce',
    'has_group_title' => __("Settings", "streamit"),
    'customizer_width' => '500px',
));

Redux::set_section($this->opt_name, array(
    'title' => esc_html__('Shop page', 'streamit'),
    'id'    => 'shop-page',
    'subsection' => true,
    'icon' => 'custom-shop',
    'desc'  => esc_html__('This section contains options for blog.', 'streamit'),
    'fields' => array(

        array(
            'id'        => 'woocommerce_shop',
            'type'      => 'image_select',
            'title'     => esc_html__('Shop Page Setting', 'streamit'),
            'subtitle'  => wp_kses(__('Choose among these structures (Product Listing, Product Grid) for your shop section.<br />To filling these column sections you should go to appearance > widget.<br />And put every widget that you want in these sections.', 'streamit'), array('br' => array())),
            'options'   => array(
                '1' => [
                    'title' => esc_html__('Product Listing', 'streamit'),
                    'img' 	=> get_template_directory_uri() . '/admin/assets/images/redux/one-column-dark.png',
                    'class'	=> 'one-column'
                ],
                '2' => [
                    'title' => esc_html__('Product Grid', 'streamit'),
                    'img' 	=> get_template_directory_uri() . '/admin/assets/images/redux/three-column-dark.png',
                    'class'	=> 'three-column'
                ]
            ),
            'default'   => '2',
        ),

        array(
            'id'        => 'product_sidebar_setting',
            'type'      => 'image_select',
            'title'     => esc_html__('Shop Page Sidebar Setting', 'streamit'),
            'subtitle'  => wp_kses(__('Choose among these structures (Right Sidebar, / Sidebar, No sidebar ) for your shop page. To filling these column sections you should go to appearance > widget.<br />And put every widget that you want in these sections.', 'streamit'), array('br' => array())),
            'options'   => array(
                '1' => array(
                    'title' => esc_html__('Right sidebar', 'streamit'), 
                    'img' => get_template_directory_uri() . '/admin/assets/images/redux/right-sidebar-dark.png',
                    'class' => 'right-sidebar'
                ),
                '3' => array(
                    'title' => esc_html__('Left sidebar', 'streamit'), 
                    'img' => get_template_directory_uri() . '/admin/assets/images/redux/left-sidebar-dark.png',
                    'class' => 'left-sidebar'
                ),
                'no-sidebar' => [
                    'title' => esc_html__('No sidebar', 'streamit'),
                    'img' 	=> get_template_directory_uri() . '/admin/assets/images/redux/one-column-dark.png',
                    'class'	=> 'one-column'
                ],
            ),
            'default'   => '1',
        ),

        array(
            'id'        => 'woocommerce_shop_grid',
            'type'      => 'image_select',
            'title'     => esc_html__('Shop Grid page Setting', 'streamit'),
            'options'   => array(
                '3' => [
                    'title' => esc_html__('Two Columns', 'streamit'),
                    'img' 	=> get_template_directory_uri() . '/admin/assets/images/redux/two-column-dark.png',
                    'class'	=> 'two-column'
                ],
                '4' => [
                    'title' => esc_html__('Three Columns', 'streamit'),
                    'img' 	=> get_template_directory_uri() . '/admin/assets/images/redux/three-column-dark.png',
                    'class'	=> 'three-column'
                ],
                '5' => [
                    'title' => esc_html__('Four Columns', 'streamit'),
                    'img' 	=> get_template_directory_uri() . '/admin/assets/images/redux/footer-4-dark.png',
                    'class'	=> 'four-column'
                ],
            ),
            'default'   => '5',
            'required'  => array('woocommerce_shop', '=', '2'),
        ),
        
    )
));

Redux::set_section($this->opt_name, array(
    'title'      => esc_html__('Products Setting', 'streamit'),
    'id'         => 'product-page',
    'subsection' => true,
    'icon' => 'custom-product',
    'fields'     => array(
        array(
            'id'        => 'streamit_display_product_name',
            'type'      => 'button_set',
            'title'     => esc_html__('Display Name', 'streamit'),
            'subtitle' => esc_html__('Here This option provide Name Of The Product', 'streamit'),
            'options'   => array(
                'yes' => esc_html__('Yes', 'streamit'),
                'no' => esc_html__('No', 'streamit')
            ),
            'default'   => 'yes'
        ),
        array(
            'id'        => 'streamit_display_price',
            'type'      => 'button_set',
            'title'     => esc_html__('Display Price', 'streamit'),
            'subtitle' => esc_html__('Here This option Display The Price', 'streamit'),
            'options'   => array(
                'yes' => esc_html__('Yes', 'streamit'),
                'no' => esc_html__('No', 'streamit')
            ),
            'default'   => 'yes'
        ),
        array(
            'id'        => 'streamit_display_product_rating',
            'type'      => 'button_set',
            'title'     => esc_html__('Display Rating', 'streamit'),
            'subtitle' => esc_html__('Display The Ratings', 'streamit'),
            'options'   => array(
                'yes' => esc_html__('Yes', 'streamit'),
                'no' => esc_html__('No', 'streamit')
            ),
            'default'   => 'no'
        ),
        array(
            'id'        => 'streamit_display_product_addtocart_icon',
            'type'      => 'button_set',
            'title'     => esc_html__('Display AddToCart Icon', 'streamit'),
            'subtitle' => esc_html__('Display AddToCart Icon', 'streamit'),
            'options'   => array(
                'yes' => esc_html__('Yes', 'streamit'),
                'no' => esc_html__('No', 'streamit')
            ),
            'default'   => 'yes'
        ),

        array(
            'id'        => 'streamit_display_product_wishlist_icon',
            'type'      => 'button_set',
            'title'     => esc_html__('Display Wishlist Icon', 'streamit'),
            'subtitle' => esc_html__('Display The Wishlist Icon', 'streamit'),
            'options'   => array(
                'yes' => esc_html__('Yes', 'streamit'),
                'no' => esc_html__('No', 'streamit')
            ),
            'default'   => 'yes'
        ),

        array(
            'id'        => 'streamit_display_product_quickview_icon',
            'type'      => 'button_set',
            'title'     => esc_html__('Display QuickView Icon', 'streamit'),
            'subtitle' => esc_html__('Display QuickView Icon', 'streamit'),
            'options'   => array(
                'yes' => esc_html__('Yes', 'streamit'),
                'no' => esc_html__('No', 'streamit')
            ),
            'default'   => 'yes'
        ),

    )
));

<?php

/**
 * Template for displaying recommended products in a slick slider
 *
 * @package streamit
 */

global $streamit_options;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) return;


// Get the related products (selected product IDs)
$related_product = $st_data->get_meta('related_product');

// Exit if there are no related products
if (empty($related_product)) return;

// Prepare the product IDs for the WooCommerce shortcode
$product_ids = implode(',', $related_product);


// Slick slider settings (adjust as needed)
$slick_settings = array(
    "dots" => false,
    "slidesToShow" => 6,
    "slidesToScroll" => 1,
    "arrows" => true,
    "autoplay" => false,
    "autoplaySpeed" => 2000,
    "responsive" => [
        ["breakpoint" => 1025, "settings" => ["slidesToShow" => 4]],
        ["breakpoint" => 600, "settings" => ["slidesToShow" => 3]],
        ["breakpoint" => 450, "settings" => ["slidesToShow" => 2]]
    ]
);


// do_action('woocommerce_before_shop_loop');

add_filter('woocommerce_product_loop_start', '__return_empty_string', 10);

remove_filter('wc_get_template_part', 'streamit_wc_template_part', 99);
add_filter(
    'wc_get_template_part',
    function ($template, $slug, $name) {
        $template = get_template_part('template-parts/wocommerce/entry');
        return $template;
    },
    10,
    3
);

remove_all_actions('woocommerce_shortcode_before_products_loop');

$show_related_product_post = $streamit_options['streamit_show_related'] ?? [];

if (is_array($show_related_product_post) && in_array('video', $show_related_product_post)) :

add_action(
    'woocommerce_shortcode_before_products_loop',
    function () use ($slick_settings) {
        global $streamit_options;
        $related_product_title = isset($streamit_options['streamit_display_related_product_title']) ? $streamit_options['streamit_display_related_product_title'] : esc_html__('Related Products', 'streamit'); ?>
    <div class="st-main-header title d-flex align-items-center justify-content-between">
        <h5 class="title-tag">
            <?php echo esc_html($related_product_title); ?>
        </h5>
    </div>
    <div class='css_prefix-slick-general st-skeleton'
        data-slider_settings='<?php echo esc_attr(wp_json_encode($slick_settings)); ?>'
        data-extra_settings='true'> <?php
                                }
                            );

        add_filter('woocommerce_product_loop_end', '__return_empty_string', 10);
         // do_action('woocommerce_after_shop_loop'); ?>

    <?php if (isset($streamit_options['streamit_display_related_product']) && $streamit_options['streamit_display_related_product'] === 'yes') : ?>
        <div class="single_page_slick">
            <div class="slick-product">
                <?php
                // Display the products using the WooCommerce shortcode
                echo do_shortcode('[products ids="' . esc_attr($product_ids) . '" per_page="-1" order="ASC" paginate="false" class="product-grid-style section-spacing-bottom"]');
                ?>
            </div>
        </div>
    <?php endif; ?>
    </div>
    <?php endif; ?>
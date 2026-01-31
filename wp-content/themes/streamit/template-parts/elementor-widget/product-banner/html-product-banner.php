<?php

/**
 * Streamit product Banner Template
 * 
 * @package Streamit
 */

if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

?>
<div class="css_prefix-main-slider p-0 css_prefix-rtl-direction css_prefix-product-banner-slider">
    <div id="<?php echo esc_attr('product-slider-' . $id_int); ?>" data-rand="<?php echo esc_attr('product-slider-' . $id_int); ?>" class="product-slider css_prefix-product-banner slider st-skeleton" data-extra_settings='<?php echo wp_json_encode(($settings['nav-arrow'] === "true" ? true : false)); ?>' data-slider_settings='<?php echo wp_json_encode($slick_settings); ?>'>

        <?php
        foreach ($args['post_data'] as $index => $item) :
            $href = '';
            if ($item['link_type'] == 'dynamic') {
                if ($item['link_by'] == 'category') {
                    $href = get_term_link($item['dynamic_link_by_cat'], 'product_cat');
                } else {
                    $product = get_page_by_path($item['dynamic_link'], OBJECT, 'product');
                    $href =  get_permalink(isset($product->ID) ? $product->ID : 0);
                }
            } else {
                $href = $item['link']['url'];
            }
        ?>
            <a href="<?php echo esc_url($href) ?>" class="slick-item">
                <div class="elementor-repeater-item-<?php echo  esc_attr($item['_id']) ?> slide slick-bg css_prefix-align-" style="background-image:url('')">
                    <?php echo \Elementor\Group_Control_Image_Size::get_attachment_image_html($item, 'full', 'slider_image'); ?>
                </div>
            </a>
        <?php
        endforeach;
        ?>
    </div>
</div>
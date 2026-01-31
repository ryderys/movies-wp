<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;
?>

<div class="section-spacing-bottom">
    <div class="css_prefix-slick-general st-skeleton css_prefix-product-cat-slick css_prefix-rtl-direction" data-extra_settings='<?php echo wp_json_encode(($args['settings']['nav-arrow'] === "true" ? true : false)); ?>' data-slider_settings='<?php echo esc_attr(wp_json_encode($args['slick_settings'])); ?>'>
        <?php if ($woo_categories) {
            foreach ($woo_categories as $cat) {
                if (empty($cat->term_id)) continue;

                $term_link      = get_term_link($cat);
                $thumbnail_id   = get_term_meta($cat->term_id, 'thumbnail_id', true); ?>

                <div class="css_prefix-categories slick-item">
                    <div class="css_prefix-category-inner">
                        <div class="category_image">

                            <a href="<?php echo esc_url($term_link); ?>">

                                <!-- attachment image using function -->
                                <?php
                                    echo streamit_render_image(
                                        array(
                                            'attachment_id' => $thumbnail_id,
                                            'class' => 'cat-img img-fluid',
                                            'alt' => esc_attr($cat->name),
                                            'decoding' => 'async',
                                        )
                                    );
                                ?>

                            </a>

                            <div class="css_prefix-category-details">
                                <?php if (!empty($cat->name)) { ?>
                                    <<?php echo esc_html($settings['title_tag']); ?> class="css_prefix-title">
                                        <a href="<?php echo esc_url($term_link); ?>">
                                            <?php echo esc_html($cat->name); ?>
                                        </a>
                                    </<?php echo esc_html($settings['title_tag']); ?>>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
        <?php }
        } ?>
    </div>
</div>
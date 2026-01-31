<?php

defined('ABSPATH') || exit;

global $product, $post, $streamit_options;

$product = isset($args['id']) ? wc_get_product($args['id']) : wc_get_product($post->ID ?? 0);

if (!$product || !is_a($product, 'WC_Product')) {
    return '';
}

$display_product_name     = $streamit_options['streamit_display_product_name']            ?? 'yes';
$display_price            = $streamit_options['streamit_display_price']                   ?? 'yes';
$display_rating           = $streamit_options['streamit_display_product_rating']          ?? 'yes';
$display_addtocart_icon   = $streamit_options['streamit_display_product_addtocart_icon']  ?? 'yes';
$display_wishlist_icon    = $streamit_options['streamit_display_product_wishlist_icon']   ?? 'yes';
$display_quickview_icon   = $streamit_options['streamit_display_product_quickview_icon']  ?? 'yes';

?>

<div class="<?php echo esc_attr('col slick-item css_prefix-sub-product ' . implode(' ', wc_get_product_class('', $product))); ?>">

    <div class="css_prefix-inner-box">

        <div class="css_prefix-product-block">

            <?php echo apply_filters('woocommerce_sale_flash', '', $product); ?>

            <div class="css_prefix-image-wrapper">

                <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>" class="css_prefix-product-title-link">
                    <div class="css_prefix-product-image">
                        <?php echo woocommerce_get_product_thumbnail(); ?>
                    </div>
                </a>

                <?php
                // Unified Hover Buttons Area 
                if (($display_quickview_icon == 'yes' && class_exists('WPCleverWoosq')) ||
                    ($display_wishlist_icon  == 'yes' && class_exists('YITH_WCWL'))     ||
                    ($display_addtocart_icon == 'yes')
                ) :
                ?>
                    <div class="css_prefix-woo-buttons-holder">

                        <ul>

                            <?php if ($display_quickview_icon == 'yes' && class_exists('WPCleverWoosq')) : ?>
                                <li class="quickview-icon-li">
                                    <?php echo do_shortcode('[woosq id="' . $product->get_id() . '"]');    ?>
                                </li>
                            <?php endif; ?>

                            <?php if ($display_wishlist_icon == 'yes' && class_exists('YITH_WCWL')) :      ?>
                                <li class="wishlist-icon-li">
                                    <?php echo do_shortcode('[yith_wcwl_add_to_wishlist product_id="' . $product->get_id() . '"]'); ?>
                                </li>
                            <?php endif; ?>

                            <?php if ($display_addtocart_icon == 'yes') : ?>

                                <li class="add-to-cart-icon-li">

                                    <?php
                                    $hover_link     = esc_url($product->get_permalink());
                                    $hover_icon_svg = function_exists('st_get_icon') ? st_get_icon('arrow-next') : '→';
                                    $hover_classes  = ['css_prefix-box-shadow', 'css_prefix-morden-btn'];
                                    $hover_attrs    = [
                                        'data-product_id'   => esc_attr($product->get_id()),
                                        'data-product_sku'  => esc_attr($product->get_sku()),
                                        'title'             => __('View Details', 'streamit'),
                                        'rel'               => 'nofollow',
                                    ];

                                    if ($product->is_type('external')) {
                                        $hover_link            = esc_url($product->add_to_cart_url());
                                        $hover_icon_svg        = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256"><path d="M228,104a12,12,0,0,1-24,0V69l-59.51,59.51a12,12,0,0,1-17-17L187,52H152a12,12,0,0,1,0-24h64a12,12,0,0,1,12,12Zm-44,24a12,12,0,0,0-12,12v64H52V84h64a12,12,0,0,0,0-24H48A20,20,0,0,0,28,80V208a20,20,0,0,0,20,20H176a20,20,0,0,0,20-20V140A12,12,0,0,0,184,128Z"></path></svg>';
                                        $hover_attrs['target'] = '_blank';
                                        $hover_attrs['title']  = $product->add_to_cart_text();
                                    } elseif ($product->is_purchasable() && $product->is_in_stock() && !$product->is_type('variable') && !$product->is_type('grouped')) {
                                        $hover_link            = esc_url($product->add_to_cart_url());
                                        $hover_icon_svg        = function_exists('st_get_icon') ? st_get_icon('bag-cart') : '🛒';
                                        $hover_classes         = array_merge($hover_classes, ['ajax_add_to_cart', 'add_to_cart_button']);
                                        $hover_attrs['data-quantity'] = 1;
                                        $hover_attrs['title']  = $product->single_add_to_cart_text();
                                    }
                                    // For variable/grouped or out of stock, it defaults to permalink and arrow-next icon

                                    echo sprintf(
                                        '<a href="%s" class="%s" %s>%s</a>',
                                        $hover_link,
                                        esc_attr(implode(' ', $hover_classes)),
                                        wc_implode_html_attributes($hover_attrs),
                                        $hover_icon_svg // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    );
                                    ?>

                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="product-caption">

            <?php if ($display_product_name === 'yes') : ?>
                <h4 class="css_prefix-product-title product_title entry-title">
                    <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
                        <?php echo esc_html($product->get_name()); ?>
                    </a>
                </h4>
            <?php endif; ?>

            <?php if ($display_price === 'yes') : ?>
                <div class="price-detail">
                    <span class="price"> <?php echo $product->get_price_html(); ?> </span>
                </div>
            <?php endif; ?>

            <?php if ($display_rating === 'yes' && $product->get_rating_count() > 0) : ?>
                <div class="products">
                    <div class="container-rating">
                        <?php echo wc_get_rating_html($product->get_average_rating(), $product->get_rating_count()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="product-excerpt-container">
                <?php
                // Get the product's short description, or fallback to post_excerpt if unavailable.
                $excerpt_source = !empty($product->get_short_description()) ? $product->get_short_description() : (isset($post) && is_a($post, 'WP_Post') ? $post->post_excerpt : '');

                if (!empty($excerpt_source)) {
                    echo '<div class="woocommerce-product-details__short-description">' . wp_kses_post($excerpt_source) . '</div>';
                }
                ?>
            </div>

            <div class="product-list-actions">
                <ul class="product-action d-flex align-items-center mt-3 mb-0">
                    <li>
                        <?php
                        // Caption Add to Cart Button - Uses WooCommerce standard function
                        $button_args = [
                            'quantity'   => 1,
                            'class'      => 'button btn btn-primary', // theme's classes
                            'attributes' => [
                                'data-product_id'  => $product->get_id(),
                                'data-product_sku' => $product->get_sku(),
                                'aria-label'       => $product->add_to_cart_description(),
                                'rel'              => 'nofollow',
                            ],
                        ];

                        $button_args['class'] .= ' product_type_' . $product->get_type();
                        if ($product->is_purchasable() && $product->is_in_stock()) {
                            $button_args['class'] .= ' add_to_cart_button';
                            if ($product->supports('ajax_add_to_cart')) {
                                $button_args['class'] .= ' ajax_add_to_cart';
                            }
                        }

                        $caption_add_to_cart_html = woocommerce_template_loop_add_to_cart($button_args, $product, []);


                        if (function_exists('st_get_icon') && is_string($caption_add_to_cart_html) && !empty($caption_add_to_cart_html)) {
                            $icon_to_add_caption = '';
                            if ($product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock()) {
                                $icon_to_add_caption = st_get_icon('bag-cart');
                            } elseif ($product->is_type('external') && $product->is_purchasable()) {
                                $icon_to_add_caption = st_get_icon('bag-cart'); // Or your specific external icon

                            } elseif ($product->is_type('variable') || $product->is_type('grouped') || !($product->is_purchasable() && $product->is_in_stock())) {
                                // For variable, grouped, or out of stock simple products (Read more / Select options)
                                $icon_to_add_caption = st_get_icon('arrow-next');
                            }

                            if ($icon_to_add_caption) {
                                if (function_exists('st_get_icon') && is_string($caption_add_to_cart_html) && !empty($caption_add_to_cart_html)) {
                                    if ($icon_to_add_caption) {
                                        // Ensure icon is added right before closing </button> or </a> tag
                                        $caption_add_to_cart_html = preg_replace('/(<\/(button|a)>)/', ' ' . $icon_to_add_caption . '$1', $caption_add_to_cart_html);
                                    }
                                }
                            }
                        }
                        echo $caption_add_to_cart_html;
                        ?>

                    </li>

                    <?php if ($display_wishlist_icon == 'yes' && class_exists('YITH_WCWL')) : ?>
                        <li class="caption-wishlist-li">
                            <?php echo do_shortcode('[yith_wcwl_add_to_wishlist product_id="' . $product->get_id() . '"]'); ?>
                        </li>
                    <?php endif; ?>

                </ul>
            </div>
        </div>
    </div>
</div>
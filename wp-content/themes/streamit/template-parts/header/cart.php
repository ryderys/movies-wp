<?php

/**
 * Template part for displaying the header cart menu
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('WooCommerce')) {
    return;
}

global $streamit_options;
?>
<?php if (isset($streamit_options['header_display_cart']) && $streamit_options['header_display_cart'] == 'yes') {
?>
    <li class="nav-item dropdown dropdown-shopping-wrapper">
        <button class="btn dropdown-toggle" data-bs-toggle="offcanvas" data-bs-target="#offcanvasExample" aria-controls="offcanvasExample" aria-label="<?php esc_attr_e('Open shopping cart', 'streamit'); ?>">
            <?php echo st_get_icon('bag-cart'); ?>

            <span id="mini-cart-count" class="css_prefix-cart-count cart-items-count count mini-cart-count" aria-live="polite">
                <?php echo WC()->cart->get_cart_contents_count(); ?>
            </span>
        </button>
        <div class="offcanvas offcanvas-end shopping-cart-panel" tabindex="-1" id="offcanvasExample">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="offcanvasExampleLabel">
                    <?php esc_html_e('Shopping cart', 'streamit'); ?> ( <span class="streamit-cart-count" aria-live="polite"><?php echo esc_html(WC()->cart->get_cart_contents_count()); ?></span> )
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <div class="widget-shopping-cart-content">
                    <?php if (WC()->cart->is_empty()) : ?>
                        <div class="css_prefix-empty-cart">
                            <div class="empty-wrapper">
                                <img src="<?php echo esc_url(get_template_directory_uri() . '/static/assets/images/defaults/empty-cart-img.png'); ?>" alt="<?php esc_attr_e('Empty cart image', 'streamit'); ?>">
                                <p class="woocommerce-mini-cart__empty-message"><?php esc_html_e('Your bag is empty. add a few items.', 'streamit'); ?></p>
                                <a class="btn btn-primary" href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>"><?php esc_html_e('Shop Now', 'streamit'); ?></a>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="product-list-content">
                            <ul class="list-inline product-list-widget">
                                <?php
                                // Loop through cart items
                                foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                                    $_product = $cart_item['data'];
                                    $product_id = $cart_item['product_id'];
                                    $quantity = $cart_item['quantity'];
                                    $product_price = WC()->cart->get_product_price($_product);
                                    $product_image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'woocommerce_thumbnail');
                                    $product_link = get_permalink($product_id);
                                    $remove_link = wc_get_cart_remove_url($cart_item_key);
                                ?>
                                    <li class="mini-cart-item">
                                        <div class="css_prefix-cart-img">
                                            <a href="<?php echo esc_url($product_link); ?>" aria-label="<?php echo esc_attr($_product->get_name()); ?>">
                                                <img width="300" height="400" src="<?php echo esc_url($product_image[0]); ?>" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" alt="<?php echo esc_attr($_product->get_name()); ?>">
                                            </a>
                                        </div>
                                        <div class="css_prefix-cart-content">
                                            <a href="<?php echo esc_url($remove_link); ?>" class="remove" aria-label="<?php esc_attr_e('Remove item from cart', 'streamit'); ?>">
                                                <?php echo st_get_icon('trash', ['class' => 'remove-icon']); ?>
                                            </a>
                                            <a class="d-block" href="<?php echo esc_url($product_link); ?>" aria-label="<?php echo esc_attr($_product->get_name()); ?>">
                                                <h6 class="css_prefix-product-title"><?php echo esc_html($_product->get_name()); ?></h6>
                                            </a>
                                            <div class="product-price">
                                                <span class="woocommerce-Price-amount amount"><?php echo wp_kses_post($product_price); ?></span>
                                            </div>
                                            <div class="quantity">
                                                <label class="screen-reader-text" for="quantity_<?php echo esc_attr($cart_item_key); ?>">
                                                    <?php esc_html_e('Quantity for', 'streamit'); ?> <?php echo esc_html($_product->get_name()); ?>
                                                </label>
                                                <button type="button" class="btn minus" aria-label="<?php esc_attr_e('Decrease quantity', 'streamit'); ?>">
                                                    <?php echo st_get_icon('minus-2'); ?>
                                                </button>
                                                <input type="number" id="quantity_<?php echo esc_attr($cart_item_key); ?>" class="input-text qty text" min="1" name="cart[<?php echo esc_attr($cart_item_key); ?>][qty]" value="<?php echo esc_attr($quantity); ?>" step="1" inputmode="numeric" autocomplete="off" aria-label="<?php esc_attr_e('Update quantity', 'streamit'); ?>">
                                                <button type="button" class="btn plus" aria-label="<?php esc_attr_e('Increase quantity', 'streamit'); ?>">
                                                    <?php echo st_get_icon('plus'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!WC()->cart->is_empty()) : ?>
                <div class="offcanvas-footer">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <strong><?php esc_html_e('Subtotal:', 'streamit'); ?></strong>
                        <span class="st-woocommerce-Price-amount amount"><?php echo wp_kses_post(WC()->cart->get_cart_subtotal()); ?></span>
                    </div>
                    <div class="mini-cart-buttons d-flex flex-column align-items-center gap-3 mt-4">
                        <a class="btn btn-primary w-100" href="<?php echo esc_url(wc_get_checkout_url()); ?>" aria-label="<?php esc_attr_e('Proceed to checkout', 'streamit'); ?>"><?php esc_html_e('Checkout', 'streamit'); ?></a>
                        <a class="btn btn-secondary w-100" href="<?php echo esc_url(wc_get_cart_url()); ?>" aria-label="<?php esc_attr_e('View cart', 'streamit'); ?>"><?php esc_html_e('View Cart', 'streamit'); ?></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </li>
<?php } ?>
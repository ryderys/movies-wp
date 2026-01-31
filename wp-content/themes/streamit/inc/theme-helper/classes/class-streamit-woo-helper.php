<?php

/**
 * Class streamit_woo_Helper
 *
 * Handles all WooCommerce tasks for the theme or plugin.
 *
 * @package streamit
 */

if (! class_exists('streamit_woo_Helper')) {

    /**
     * streamit_woo_Helper class.
     *
     * This class manages all the hooks and filters related to WooCommerce.
     */
    class streamit_woo_Helper
    {

        /**
         * Constructor function.
         * Initializes the hooks and filters related to WooCommerce.
         */
        public function __construct()
        {
            if (class_exists('WooCommerce')) {

                // Hide product description heading.
                add_filter('woocommerce_product_description_heading', '__return_null');

                // Disable WooCommerce page title.
                add_filter('woocommerce_show_page_title', '__return_false');

                // Remove WooCommerce breadcrumb.
                remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0);

                // Customize the review form fields.
                add_filter('woocommerce_product_review_comment_form_args', [$this, 'st_wc_custom_review_form_fields']);

                // Customize the review form submit button.
                add_filter('woocommerce_product_review_comment_form_args', [$this, 'st_wc_custom_review_form_submit_button']);

                // Change WooCommerce products per page.
                add_filter('loop_shop_per_page', [$this, 'st_custom_products_per_page'], 20);

                add_filter('wc_get_template_part', [$this, 'streamit_wc_template_part'], 10, 3);

                add_filter('body_class', [$this, 'st_woo_body_class'], 10, 3);

                add_filter('woocommerce_get_script_data', [$this, 'st_custom_woocommerce_script_data']);

                add_filter('st_content_container_class', [$this, 'st_content_woo_account_container_class'], 10, 1);

                // Override add to cart params
                add_action('wp_enqueue_scripts', [$this, 'st_override_add_to_cart_params'], 20);

                // woocommerce product layout tab
                add_filter('st_woocommerce_product_layout_tab', [$this, 'st_woocommerce_product_tab'], 10, 1);

                add_filter('custom_shopping_mini_cart_content', [$this, 'render_shopping_mini_cart_content']);

                add_filter('woocommerce_add_to_cart_fragments', [$this, 'st_update_cart_count_fragment']);

                add_filter('woocommerce_add_to_cart_fragments', [$this, 'st_update_mini_cart_fragment']);
                add_filter('st_custom_mini_cart_content', [$this, 'st_render_mini_cart_content']);

                // custom woocommerce review message
                add_action('comment_post', [$this, 'st_custom_woocommerce_review_message'], 10, 2);

                // woocommerce sale filter
                add_filter('woocommerce_sale_flash', [$this, 'streamit_get_product_badge_html'], 10, 2);
            }
        }

        public function st_woo_body_class($classes)
        {

            if (is_shop() || is_cart() || is_checkout() || is_account_page()):
                $classes[] = 'woocommerce-page';
            endif;

            return $classes;
        }


        public function streamit_wc_template_part($st_template)
        {
            if (is_shop() || is_product_category() || is_product_tag()) {
                $st_template_page =  'entry.php';
                $st_template_url = get_stylesheet_directory() . 'template-parts/wocommerce/' . $st_template_page;

                if (file_exists($st_template_url)) {
                    return trailingslashit($st_template_url);
                }
                return trailingslashit(get_template_directory()) . 'template-parts/wocommerce/' . $st_template_page;
            }
            return $st_template;
        }

        // Define a custom function to modify the 'woocommerce_get_script_data' parameters
        public function st_custom_woocommerce_script_data($params)
        {
            if (isset($params['i18n_view_cart'])) {
                $params['i18n_view_cart'] = '<span>' . $params['i18n_view_cart'] . '</span>';
            }
            return $params;
        }


        /**
         * Customize the review form fields.
         *
         * @param array $comment_form The WooCommerce comment form fields.
         * @return array Modified comment form fields.
         */
        public function st_wc_custom_review_form_fields($comment_form)
        {
            if (isset($comment_form['fields']['author'])) {
                $comment_form['fields']['author'] = '<p class="comment-form-author">' .
                    '<label for="author">' . esc_html__('Name', 'streamit') . ' <span class="required">*</span></label>' .
                    '<input id="author" class="form-control" name="author" type="text" value="" size="30" required="required" placeholder="' . esc_attr__('Name', 'streamit') . '" />' .
                    '</p>';
            }

            if (isset($comment_form['fields']['email'])) {
                $comment_form['fields']['email'] = '<p class="comment-form-email">' .
                    '<label for="email">' . esc_html__('Email', 'streamit') . ' <span class="required">*</span></label>' .
                    '<input id="email" class="form-control" name="email" type="email" value="" size="30" required="required" placeholder="' . esc_attr__('Email', 'streamit') . '" />' .
                    '</p>';
            }

            return $comment_form;
        }

        /**
         * Customize the review form submit button.
         *
         * @param array $comment_form The WooCommerce comment form fields.
         * @return array Modified comment form submit button.
         */
        public function st_wc_custom_review_form_submit_button($comment_form)
        {
            $comment_form['submit_button'] = '<input name="submit" type="submit" id="submit" class="btn btn-primary" value="' . esc_attr__('Submit', 'streamit') . '" />';
            return $comment_form;
        }

        /**
         * Set the number of products per page.
         *
         * @param int $cols The current number of products per page.
         * @return int Modified number of products per page.
         */
        public function st_custom_products_per_page($cols)
        {
            return 12;
        }

        /**
         * Adds a custom container class for specific WooCommerce pages.
         *
         * @param string $class The existing container class.
         * @return string The modified container class.
         */
        public function st_content_woo_account_container_class($class)
        {
            global $post;

            // Get the Wishlist page ID from options.
            $wishlist_page_id = get_option('yith_wcwl_wishlist_page_id');

            // List of conditions to apply the 'container' class.
            if (
                (function_exists('is_account_page') && is_account_page()) || // WooCommerce My Account page
                (function_exists('is_checkout') && is_checkout()) ||        // WooCommerce Checkout page
                (is_page() && isset($post->post_name) && $post->post_name === 'order-tracking') || // Order Tracking page
                (get_the_ID() === intval($wishlist_page_id)) // Wishlist page
            ) {
                return 'container';
            }

            // Default return value if conditions are not met.
            return $class;
        }

        // Override add to cart params
        public function st_override_add_to_cart_params()
        {

            // Pass your custom parameters
            wp_localize_script('streamit-main', 'wc_add_to_cart_params', array(
                'ajax_url'                => WC()->ajax_url(),
                'wc_ajax_url'             => WC_AJAX::get_endpoint('%%endpoint%%'),
                'i18n_view_cart'          => esc_attr__('View cart', 'streamit'),
                'cart_url'                => apply_filters('woocommerce_add_to_cart_redirect', wc_get_cart_url(), null),
                'is_cart'                 => is_cart(),
                'cart_redirect_after_add' => get_option('woocommerce_cart_redirect_after_add'),
            ));
        }

        /**
         * Filters the WooCommerce product layout tabs and generates the corresponding HTML markup.
         *
         * This function is hooked into the 'st_woocommerce_product_layout_tab' filter
         * and dynamically generates the layout tabs based on the provided options.
         *
         * @param array $streamit_options Array of options for customizing the WooCommerce product layout.
         *   - 'woocommerce_shop' (string|null): Determines if the shop layout is active. Value '1' sets it as active.
         *   - 'woocommerce_shop_grid' (string|null): Specifies the active grid layout. Accepts '3', '4', or '5'.
         *
         * @return string HTML markup for the product layout tabs.
         *
         * @example
         * // Apply the filter in your theme or plugin
         * echo apply_filters('st_woocommerce_product_layout_tab', $streamit_options);
         *
         * @hook st_woocommerce_product_layout_tab
         */
        public function st_woocommerce_product_tab($streamit_options)
        {

            $preferred_view_cookie = sanitize_text_field($_COOKIE['streamit_preferred_shop_layout'] ?? '');

            // Get theme options, using Redux defaults if not set
            $woocommerce_shop_option      = $streamit_options['woocommerce_shop'] ?? '2';
            $woocommerce_shop_grid_option = $streamit_options['woocommerce_shop_grid'] ?? '5';

            // Define keys for our active states, matching what the HTML output expects
            $state_keys = [
                'list_layout'       => 'st_woo_active_list_layout',
                'grid_two_layout'   => 'st_woo_active_grid_two_layout',
                'grid_three_layout' => 'st_woo_active_grid_three_layout',
                'grid_four_layout'  => 'st_woo_active_grid_four_layout',
            ];

            // Initialize all active states to empty
            foreach ($state_keys as $variable_name) {
                $$variable_name = ''; // Dynamically create and set variables like $st_woo_active_list_layout
            }

            $determined_active_key = null; // This will be 'list_layout', 'grid_two_layout', etc.

            // Priority 1: Check Cookie
            if ($preferred_view_cookie) {
                $cookie_map = [
                    '1' => 'list_layout',
                    '2' => 'grid_two_layout',
                    '3' => 'grid_three_layout',
                    '4' => 'grid_four_layout',
                ];
                $determined_active_key = $cookie_map[$preferred_view_cookie] ?? null;
            }

            // Priority 2: Theme Options (if cookie didn't specify a valid view)
            if (!$determined_active_key) {
                if ($woocommerce_shop_option === '1') {
                    $determined_active_key = 'list_layout';
                } else {
                    $grid_map = [
                        '3' => 'grid_two_layout',
                        '4' => 'grid_three_layout',
                        '5' => 'grid_four_layout',
                    ];
                    $determined_active_key = $grid_map[$woocommerce_shop_grid_option] ?? 'grid_four_layout';
                }
            }

            // If a key was determined, set the corresponding dynamic variable to 'active'
            if ($determined_active_key && isset($state_keys[$determined_active_key])) {
                $active_variable_name  = $state_keys[$determined_active_key];
                $$active_variable_name = 'active';
            } else {
                $st_woo_active_grid_four_layout = 'active';
            }

            ob_start(); // Start output buffering
?>

            <ul class="nav nav-pills woocommerce-product-tab d-flex justify-content-center gap-3 align-items-center">
                <li class="nav-item">
                    <a class="nav-link p-0 <?php echo esc_attr($st_woo_active_list_layout); ?>" id="1" data-bs-toggle="pill" href="#grid-1">
                        <?php echo st_get_icon('list-dashes'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link p-0 <?php echo esc_attr($st_woo_active_grid_two_layout); ?>" id="2" data-bs-toggle="pill" href="#grid-2">
                        <?php echo st_get_icon('grid-2x2'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link p-0 <?php echo esc_attr($st_woo_active_grid_three_layout); ?> " id="3" data-bs-toggle="pill" href="#grid-3">
                        <?php echo st_get_icon('grid-3x3'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link p-0 <?php echo esc_attr($st_woo_active_grid_four_layout); ?>" id="4" data-bs-toggle="pill" href="#grid-4">
                        <?php echo st_get_icon('grid-4x4'); ?>
                    </a>
                </li>
            </ul>

        <?php
            return ob_get_clean(); // Return the buffered content
        }

        public function st_update_cart_count_fragment($fragments)
        {
            ob_start();

            // Conditionally hide the count if the cart is empty
            $style = WC()->cart->get_cart_contents_count() === 0 ? 'style="display:none;"' : '';
        ?>
            <span id="mini-cart-count" class="css_prefix-cart-count cart-items-count count mini-cart-count" <?php echo $style; ?> aria-live="polite">
                <?php echo WC()->cart->get_cart_contents_count(); ?>
            </span>
        <?php

            $fragments['#mini-cart-count'] = ob_get_clean();
            return $fragments;
        }

        public function st_render_mini_cart_content()
        {
            ob_start();
        ?>
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
                                                    <?php echo st_get_icon('trash', ['class' => 'remov-icon']); ?>
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
        <?php
            return ob_get_clean();
        }

        public function st_update_mini_cart_fragment($fragments)
        {
            ob_start();
            echo $this->st_render_mini_cart_content();
            $fragments['.shopping-cart-panel'] = ob_get_clean();
            return $fragments;
        }

        /**
         * Adds a custom success message after a WooCommerce product review is submitted.
         *
         * @param int  $st_comment_id     The ID of the submitted comment.
         * @param bool $st_comment_approved Whether the comment is approved (1 for approved, 0 for pending).
         */
        function st_custom_woocommerce_review_message($st_comment_id, $st_comment_approved)
        {
            // Get the comment object using the provided comment ID.
            $st_comment = get_comment($st_comment_id);

            // Check if the comment belongs to a WooCommerce product.
            if ($st_comment) {
                $st_post_id = $st_comment->comment_post_ID;

                // Ensure the post type is 'product' and the comment is approved.
                if (get_post_type($st_post_id) === 'product' && $st_comment_approved) {
                    // Add a success notice using WooCommerce's notice system.
                    wc_add_notice(
                        esc_html__('Thank you for your review! It has been submitted successfully.', 'streamit'),
                        'success'
                    );
                }
            }
        }

        /**
         * Generate a custom product badge based on product status.
         *
         * This function returns a badge for WooCommerce products based on:
         * - Sale status: displays "Sale!"
         * - Stock status: displays "Sold!"
         * - Recent creation (within 1 week): displays "New!"
         *
         * @param string     $html    The existing HTML for the badge (can be empty).
         * @param WC_Product $product WooCommerce product object.
         * @return string HTML output of the badge, or original HTML if no badge applies.
         */
        public function streamit_get_product_badge_html($html, $product)
        {
            if (! $product instanceof WC_Product) {
                return $html;
            }

            $badge_text = '';

            if ($product->is_on_sale()) {
                $badge_text = __('Sale!', 'streamit');
            } elseif (!$product->is_in_stock()) {
                $badge_text = __('Sold!', 'streamit');
            } elseif ($product->get_date_created() && (time() - strtotime($product->get_date_created())) < WEEK_IN_SECONDS) {
                $badge_text = __('New!', 'streamit');
            }

            if (!$badge_text) {
                return $html;
            }

            ob_start();
        ?>
            <span class="onsale css_prefix-on-sale">
                <span class="custom-badge sale"><?php echo esc_html($badge_text); ?></span>
            </span>
<?php
            return ob_get_clean();
        }
    }

    // Initialize the WooCommerce helper class.
    new streamit_woo_Helper();
}

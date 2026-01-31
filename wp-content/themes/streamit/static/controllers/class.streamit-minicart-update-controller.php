<?php

defined('ABSPATH') || exit;


final class st_Minicart_Controller
{

    public function update_cart(WP_REST_Request $request)
    {
        // Set item key as the hash found in input.qty's name
        $data = $request->get_params();

        $cart_item_key = $data['cart_item_key'];

        // Get the array of values owned by the product we're updating
        $threeball_product_values = WC()->cart->get_cart_item($cart_item_key);

        // Get the quantity of the item in the cart
        $threeball_product_quantity = apply_filters(
            'woocommerce_stock_amount_cart_item',
            apply_filters('woocommerce_stock_amount', $data['new_quantity'], $cart_item_key),
            $cart_item_key
        );

        // Update cart validation
        $passed_validation = apply_filters('woocommerce_update_cart_validation', true, $cart_item_key, $threeball_product_values, $threeball_product_quantity);

        // Update the quantity of the item in the cart
        if ($passed_validation) {
            WC()->cart->set_quantity($cart_item_key, $threeball_product_quantity, true);
            // Return success response with new quantity and subtotal
            return wp_send_json(array(
                'status'  => true,
                'new_quantity' => WC()->cart->get_cart_contents_count(),
                'new_subtotal' => WC()->cart->get_cart_subtotal(), // Send updated subtotal
            ));
        }

        // Return error if validation fails
        return wp_send_json(array('status' => false, 'message' => esc_html__('Failed to update cart.', 'streamit')));
    }
}

<?php

/**
 * The template for displaying video watch list.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

echo do_shortcode('[streamit_watchlist_shortcode post_id="' . esc_attr($st_data->get_id()) . '" post_type="' . esc_attr($st_data->get_post_type()) . '" is_button="false"]');

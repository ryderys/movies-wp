<?php

/**
 * The template for displaying video notify me button
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<div class="notify-me-button-wrapper">
    <?php echo do_shortcode('[streamit_notify_me_shortcode post_id="' . esc_attr($st_data->get_id()) . '" post_type="' . esc_attr($st_data->get_post_type()) . '"]'); ?>
</div>

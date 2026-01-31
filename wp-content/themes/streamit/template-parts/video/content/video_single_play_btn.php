<?php

/**
 * The template for displaying video pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<div class="play-button-wrapper d-flex align-items-center gap-md-4 gap-3 flex-wrap">
    <?php echo do_shortcode('[streamit_play_button post_id="' . esc_attr($st_data->get_id()) . '" post_type="' . esc_attr($st_data->get_post_type()) . '"]');
    ?>
</div>
<?php

/**
 * The template for displaying common list struture
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if(empty($args)){
    return;
}
?>
<div class="css_prefix_common_list d-flex align-items-center gap-3">
    <a href="<?php echo esc_url(streamit_get_permalink($args->get_post_type(), $args->get_post_name())) ?>" class="link-overlay">
        <?php $st_image_url = !empty(wp_get_attachment_image_url($args->get_meta('thumbnail_id'))) ? wp_get_attachment_image_url($args->get_meta('thumbnail_id'), 'full') : streamit_placeholder_image(); ?>
        <img src="<?php echo esc_url($st_image_url) ?>" alt="<?php echo esc_attr($args->get_post_title()) ?>" class="img-fluid object-cover result-image">
    </a>
    <h6 class="text-capitalize line-count-1">
        <a href="<?php echo esc_url(streamit_get_permalink($args->get_post_type(), $args->get_post_name())) ?>" class="color-inherit">
            <?php echo esc_html($args->get_post_title()) ?>
        </a>
    </h6>
</div>
<?php

/**
 * The template for displaying archive thumbnail.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<div class="cast-images position-relative">
    <?php $st_image_url = !empty( wp_get_attachment_image_url( $st_data->get_meta( 'thumbnail_id' ) ) ) ? wp_get_attachment_image_url( $st_data->get_meta( 'thumbnail_id' ) , 'full') : streamit_placeholder_image(); ?>
    <a href="<?php echo esc_url(streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name())) ?>" class="link-overlay">
        <img src="<?php echo esc_url( $st_image_url ) ?>" alt="<?php echo esc_attr($st_data->get_post_title()); ?>" class="img-fluid object-cover w-100 border-0">
    </a>
</div>

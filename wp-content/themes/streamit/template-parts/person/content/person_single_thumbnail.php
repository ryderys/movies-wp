<?php

/**
 * The template for displaying thumbnail.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


$thumbnail_url = !empty( wp_get_attachment_image_url( $st_data->get_meta( 'thumbnail_id' ) ) ) ? wp_get_attachment_image_url( $st_data->get_meta( 'thumbnail_id' ) , 'full') : streamit_placeholder_image(); 
?>


<div class="person-image">
    <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr($st_data->get_post_title()); ?>" class="img-fluid rounded object-cover w-100 border-0">
</div>
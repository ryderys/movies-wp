<?php

/**
 * The template for displaying trailer
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$thumbnail = !empty($st_data->get_meta('thumbnail_id')) ? wp_get_attachment_image_url($st_data->get_meta('thumbnail_id'), 'full') : streamit_placeholder_image();
?>

<div class="video-plus-image">
    <div class="video-banner-image-container">
        <img src="<?php echo esc_url($thumbnail); ?>" class="video-banner-image" alt="video-section-banner-image">
    </div>
</div>
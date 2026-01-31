<?php

/**
 * The template for displaying archive genre pages
 *
 * This template is used to display the list of genres for TV shows in a grid layout.
 * It checks for available genre data and displays the respective genre cards with their images and titles.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

// Ensure the file is being accessed within the WordPress environment, not directly.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

?>

<div class="col">
    <div class="genres-card position-relative">
        <div class="image-box position-relative">
            <!-- Link to the genre page. -->
            <a href="<?php echo esc_url(streamit_get_permalink('tvshow_genre', $st_data->get_term_slug())) ?>" class="color-inherit line-count-1">
                <?php
                // Get the genre image URL or use a placeholder if no image is set.
                $st_image_url = !empty(wp_get_attachment_url($st_data->get_thumbnail())) ? wp_get_attachment_url($st_data->get_thumbnail()) : streamit_placeholder_image();
                ?>
                <?php if (!empty($st_image_url)) : ?>
                    <!-- Display the genre image if available. -->
                    <img src="<?php echo esc_url($st_image_url) ?>" alt="<?php esc_attr($st_data->get_term_name()) ?>">
                <?php endif; ?>
                <?php if (!empty($st_data->get_term_name())) : ?>
                    <!-- Display the genre title. -->
                    <span class="genres-title h6">
                        <?php echo esc_html(wp_unslash($st_data->get_term_name())) ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</div>
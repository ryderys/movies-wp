<?php

/**
 * The template for displaying archive genre page
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Get the thumbnail URL or a placeholder
$st_image_url = !empty($st_data->get_thumbnail()) 
? wp_get_attachment_url($st_data->get_thumbnail()) 
: streamit_placeholder_image();
$term_name = wp_unslash($st_data->get_term_name());
$term_slug = $st_data->get_term_slug();

?>
<div class="col">
    <div class="genres-card position-relative">
        <div class="image-box position-relative">
            <a href="<?php echo esc_url(streamit_get_permalink('movie_genre', $term_slug)); ?>" class="color-inherit line-count-1">
                <img src="<?php echo esc_url($st_image_url); ?>" alt="<?php echo esc_attr($term_name ?: __('image', 'streamit')); ?>" class="img-fluid">
                <?php if (!empty($term_name)) : ?>
                    <span class="genres-title h6">
                        <?php echo esc_html($term_name); ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</div>
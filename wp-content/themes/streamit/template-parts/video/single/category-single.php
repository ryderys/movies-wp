<?php

/**
 * The template for displaying video category single pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wp_query;
$video_category_slug = isset($wp_query->query_vars['video_category']) ? sanitize_title($wp_query->query_vars['video_category']) : '';
$st_data = streamit_get_term((string)$video_category_slug, 'video_category');
$term_id = $st_data->get_term_id();

$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', 'بارگذاری بیشتر');
$loading_text = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__('Loading...', 'streamit'));


?>
<div class="container-fluid">
    <?php
    // Check if content_data and results exist
    if (!empty($content_data) && !empty($content_data->results)) : ?>
        <div class="css_prefix-card-wrapper video_cards grid-view" data-options="yes" data-can-beloaded="1">
            <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 data-listing ">
                <?php foreach ($content_data->results as $video) :
                    // Ensure the template part 'archive_loop.php' exists
                    streamit_get_template('video/archive/archive_loop.php', ['st_data' => $video]);
                endforeach; ?>
            </div>
            <?php
        if (!empty($content_data->results) && ($content_data->maxnumpages > 1))
            echo st_get_load_more_button($content_data->maxnumpages, 'video_single_category', 1, esc_html($load_more_text), esc_html($loading_text), '', '', '', esc_html($term_id));
        ?>
        </div>
    <?php else :
        echo '<p>' . esc_html__('No video category found.', 'streamit') . '</p>';
    endif; ?>
</div>
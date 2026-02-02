<?php

/**
 * The template for displaying tag single pages
 *
 * This template is used to display a single tag page for TV shows.
 * Learn more: https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

global $wp_query;
$tvshow_tag_slug = isset($wp_query->query_vars['tvshow_tag']) ? sanitize_title($wp_query->query_vars['tvshow_tag']) : '';
$st_data = streamit_get_term((string)$tvshow_tag_slug, 'tvshow_tag');
$term_id = $st_data->get_term_id();

$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', 'بارگذاری بیشتر');
$loading_text = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__('Loading...', 'streamit'));


?>

<div class="container-fluid">
    <?php
    // Ensure $content_data exists and is not empty.
    if (!empty($content_data) && !empty($content_data->results)) : ?>
        <div class="css_prefix-card-wrapper tvshow-cards grid-view" data-options="yes" data-can-beloaded="1">
            <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6 data-listing">
                <?php
                // Loop through the results and include the template for each TV show.
                foreach ($content_data->results as $st_data) :
                    streamit_get_template('tvshow/archive/archive_loop.php', ['st_data' => $st_data]);
                endforeach;
                ?>
            </div>
            <?php
        if (!empty($content_data->results) && ($content_data->maxnumpages > 1))
            echo st_get_load_more_button($content_data->maxnumpages, 'tvshow_single_tag', 1, esc_html($load_more_text), esc_html($loading_text), '', '', '', esc_html($term_id));
        ?>
        </div>
    <?php else : ?>
        <!-- Fallback message if no TV shows are found. -->
        <p><?php esc_html_e('No TV shows found for this tag.', 'streamit'); ?></p>
    <?php endif; ?>
</div>
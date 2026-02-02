<?php

/**
 * The template for displaying tag single pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wp_query;
$movie_tag_slug = isset($wp_query->query_vars['movie_tag']) ? sanitize_title($wp_query->query_vars['movie_tag']) : '';
$st_data = streamit_get_term((string)$movie_tag_slug, 'movie_tag');
$term_id = $st_data->get_term_id();

$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', 'بارگذاری بیشتر');
$loading_text = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__('Loading...', 'streamit'));

?>

<div class="container-fluid">
    <?php if (!empty($content_data->results)) : ?>
        <div class="css_prefix-card-wrapper movie_cards grid-view" data-options="yes" data-can-beloaded="1">
            <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6 data-listing">
                <?php foreach ($content_data->results as $st_data) : ?>
                    <?php streamit_get_template('movie/archive/archive_loop.php', ['st_data' => $st_data, 'view_type' => $view_type]);  ?>
                <?php endforeach; ?>
            </div>
            <?php
        if (!empty($content_data->results) && ($content_data->maxnumpages > 1))
            echo st_get_load_more_button($content_data->maxnumpages, 'movie_single_tag', 1, esc_html($load_more_text), esc_html($loading_text), '', '', '', esc_html($term_id));
        ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e('No Movies Found.', 'streamit'); ?></p>
    <?php endif; ?>
</div>
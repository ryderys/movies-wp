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
global $streamit_options;

$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', 'بارگذاری بیشتر');
$loading_text = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__('Loading...', 'streamit'));

$genere_tag_option = isset($streamit_options['streamit_genere_tag_category_item']) ? $streamit_options['streamit_genere_tag_category_item'] : 'load_more';

?>

<div class="container-fluid">
    <?php
    // Check if there is content data available (i.e., TV show genres).
    if (!empty($content_data->results)) : ?>
        <!-- Genre cards container with a grid layout for responsive display. -->
        <div class="css_prefix-card-wrapper tvshow_cards grid-view" data-options="yes" data-can-beloaded="1">
            <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 data-listing">
                <?php foreach ($content_data->results as $st_data) : ?>
                    <!-- Loop through each genre and display it in a grid item. -->
                    <div class="col">
                        <?php streamit_get_template('tvshow/archive/archive_genre_loop.php', ['st_data' => $st_data]); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
            if ($content_data->maxnumpages > 1) {
                if ($genere_tag_option === 'load_more') {
                    echo st_get_load_more_button($content_data->maxnumpages, 'tvshow_genre', 1, esc_html($load_more_text), esc_html($loading_text));
                } elseif ($genere_tag_option === 'infinite_scroll') {
                    echo st_get_loader_wheel_container($content_data->maxnumpages, 'tvshow_genre', 1, '');
                }
            }
            ?>
        </div>

    <?php else : ?>
        <!-- Message if no genres are found. -->
        <p><?php esc_html_e('No TVshow genre found.', 'streamit'); ?></p>
    <?php endif; ?>
</div>
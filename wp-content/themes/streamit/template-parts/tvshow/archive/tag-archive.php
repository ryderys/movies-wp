<?php

/**
 * The template for displaying tag pages
 *
 * This template is used for displaying the tag pages of TV shows.
 * It checks if there are any TV shows related to the current tag and displays them.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

// Ensure the template is accessed within WordPress, not directly.
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
    // Check if there are results in content_data (TV shows in this case).
    if (!empty($content_data->results)) : ?>
        <div class="css_prefix-card-wrapper movie_cards grid-view" data-options="yes" data-can-beloaded="1">
            <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 data-listing">
                <?php
                // Loop through each TV show associated with the tag and display it.
                foreach ($content_data->results as $st_data) : ?>
                    <?php streamit_get_template('tvshow/archive/archive_tag_loop.php', ['st_data' => $st_data]); ?>
                <?php endforeach; ?>
            </div>
            <?php
            if ($content_data->maxnumpages > 1) {
                if ($genere_tag_option === 'load_more') {
                    echo st_get_load_more_button($content_data->maxnumpages, 'tvshow_tag', 1, esc_html($load_more_text), esc_html($loading_text));
                } elseif ($genere_tag_option === 'infinite_scroll') {
                    echo st_get_loader_wheel_container($content_data->maxnumpages, 'tvshow_tag', 1, '');
                }
            }
            ?>
        </div>

    <?php else : ?>
        <!-- Message if no TV shows are found -->
        <p><?php esc_html_e('No Tvhows Tag found.', 'streamit'); ?></p>
    <?php endif; ?>
</div>
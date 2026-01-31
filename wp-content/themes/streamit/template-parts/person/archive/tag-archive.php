<?php

/**
 * The template for displaying tag pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;
$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', esc_html__( 'Load More', 'streamit'));
$loading_text = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__( 'Loading...', 'streamit') );

$tag_category_option = isset($streamit_options['streamit_genere_tag_category_item']) ? $streamit_options['streamit_genere_tag_category_item'] : 'load_more';

?>

<div class="container-fluid">
    <?php
    // Ensure content_data exists and has results
    if (isset($content_data) && !empty($content_data->results)) : ?>
        <div class="css_prefix-card-wrapper person_cards grid-view" data-options="yes" data-can-beloaded="1">
            <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 data-listing">
                <?php foreach ($content_data->results as $st_data) : ?>
                    <?php streamit_get_template('person/archive/archive_tag_loop.php', ['st_data' => $st_data]); ?>
                <?php endforeach; ?>
            </div>
            <?php
            if ($content_data->maxnumpages > 1) {
                if ($tag_category_option === 'load_more') {
                    echo st_get_load_more_button($content_data->maxnumpages, 'person_tag', 1, esc_html($load_more_text), esc_html($loading_text));
                } elseif ($tag_category_option === 'infinite_scroll') {
                    echo st_get_loader_wheel_container($content_data->maxnumpages, 'person_tag', 1, '');
                }
            }
            ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e('No person tag found.', 'streamit'); ?></p>
    <?php endif; ?>
</div>
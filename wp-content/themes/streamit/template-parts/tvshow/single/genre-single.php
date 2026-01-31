<?php

/**
 * The template for displaying TV show genre single pages.
 *
 * This template is responsible for displaying individual TV show genre pages.
 * It checks whether there is any content to display and loops through the TV show data.
 * If no content is found, it displays a message indicating that no TV shows are found.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

// Ensure the file is being accessed within the WordPress environment, not directly.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


global $wp_query;
$tvshow_genre_slug = isset($wp_query->query_vars['tvshow_genre']) ? sanitize_title($wp_query->query_vars['tvshow_genre']) : '';
$st_data = streamit_get_term((string)$tvshow_genre_slug, 'tvshow_genre');
$term_id = $st_data->get_term_id();

$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', esc_html__('Load More', 'streamit'));
$loading_text = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__('Loading...', 'streamit'));


?>

<div class="container-fluid">
    <?php

    // Check if there is any content data available.
    if (!empty($content_data->results)) : ?>

        <div class="css_prefix-card-wrapper tvshow_cards grid-view"
            data-options="yes" data-can-beloaded="1">

            <!-- Grid layout for displaying TV show cards -->
            <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 data-listing">

                <?php
                // Loop through the TV show data and load the template for each TV show
                foreach ($content_data->results as $st_data) :
                    // Call the template for displaying a single TV show in the archive loop
                    streamit_get_template('tvshow/archive/archive_loop.php', ['st_data' => $st_data, 'view_type' => $view_type] );
                endforeach;
                ?>
            </div>
            <?php
        if (!empty($content_data->results) && ($content_data->maxnumpages > 1))
            echo st_get_load_more_button($content_data->maxnumpages, 'tvshow_single_genre', 1, esc_html($load_more_text), esc_html($loading_text), '', '', '', esc_html($term_id));
        ?>
        </div>

    <?php else :
        // Display a message if no TV shows are found
        echo '<p>' . esc_html__('No TV Show Found.', 'streamit') . '</p>';
    endif;
    ?>
</div>
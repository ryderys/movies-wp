<?php

/**
 * The template for displaying archive pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', 'بارگذاری بیشتر');
$loading_text = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__('Loading...', 'streamit'));

?>

<div class="container-fluid">
    <?php if (!empty($content_data->results)) : ?>
        <div class="css_prefix-card-wrapper person_cards grid-view" data-options="yes" data-can-beloaded="1">
            <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6 data-listing">
                <?php foreach ($content_data->results as $st_data) : ?>
                    <?php
                    // Safely render the template for each person entry.
                    streamit_get_template('person/archive/archive_loop.php', ['st_data' => $st_data]);
                    ?>
                <?php endforeach; ?>
            </div>

            <?php echo st_get_load_more_button($content_data->maxnumpages, 'person', 1, esc_html($load_more_text), esc_html($loading_text), 10, '', ''); ?>
        </div>

    <?php else : ?>
        <p class="no_data_found"><?php esc_html_e('No persons found.', 'streamit'); ?></p>
    <?php endif; ?>
</div>
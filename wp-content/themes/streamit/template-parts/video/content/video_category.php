<?php

/**
 * The template for displaying video genre pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<ul class="genres-list p-0 mb-2 d-flex align-items-center">
    <?php
    $terms_ids = streamit_get_term_relationships($st_data->get_id(), 'video_category');
    $terms_data = !empty($terms_ids) ? streamit_get_terms(['include' => $terms_ids])->results : [];
    $limited_terms = array_slice($terms_data, 0, 2);
    foreach ($limited_terms as $st_term) : ?>
        <li>
            <a href="<?php echo esc_url(streamit_get_permalink('video_category', $st_term->get_term_slug())); ?>">
                <?php echo esc_html($st_term->get_term_name()) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
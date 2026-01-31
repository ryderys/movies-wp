<?php

/**
 * The template for displaying tvshow play button
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<ul class="p-0 mb-0 list-inline d-flex flex-wrap align-items-center tvshow-geners">
    <?php
    $terms_ids  = streamit_get_term_relationships($st_data->get_id(), 'tvshow_genre');
    $terms_data = !empty($terms_ids) ? streamit_get_terms(['include' => $terms_ids])->results : [];
    foreach ($terms_data as $st_term) : ?>
        <li class="position-relative">
            <a href="<?php echo esc_url(streamit_get_permalink('tvshow_genre', $st_term->get_term_slug())); ?>"><?php echo esc_html(wp_unslash($st_term->get_term_name())) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
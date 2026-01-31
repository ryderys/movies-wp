<?php

/**
 * The template for displaying movie genre pages
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
    $terms_ids = streamit_get_term_relationships( $st_data->get_id() , 'movie_genre');
    $terms_data = !empty($terms_ids) ? streamit_get_terms(['include' => $terms_ids , 'per_page' => 2])->results : [];
    foreach( $terms_data as $st_term ) : ?>
        <li>
            <a href="<?php echo esc_url(streamit_get_permalink('movie_genre', $st_term->get_term_slug())); ?>">
                <?php echo esc_html($st_term->get_term_name()) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

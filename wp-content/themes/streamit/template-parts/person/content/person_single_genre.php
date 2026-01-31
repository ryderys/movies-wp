<?php

/**
 * The template for displaying person genre
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
$st_person_id = method_exists($st_data, 'get_id') ? $st_data->get_id() : '';
$st_terms = streamit_get_term_by_post($st_person_id, 'person_category');
if(empty($st_terms)) return;
?>
<ul class="person-category d-flex flex-wrap align-items-center gap-5">
    <?php
        foreach($st_terms as $term){
            echo '<li><a href="' . esc_url(streamit_get_permalink($term->get_taxonomy() , $term->get_term_slug())).'">' . esc_html($term->get_term_name()) . '</a></li>';
        }
    ?>
</ul>
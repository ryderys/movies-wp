<?php

/**
 * The template for displaying person genre pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<ul class="d-flex align-items-center justify-content-center gap-2 list-inline p-0 m-0">
    <?php
    $st_person_id = method_exists($st_data , 'get_id') ? $st_data->get_id() : '';
    $st_terms = streamit_get_term_by_post($st_person_id, 'person_category');
    $limited_terms = array_slice( $st_terms, 0, 2 );
    foreach( $limited_terms as $st_term ) : ?>
        <li class="small">
            <a href="<?php echo esc_url(streamit_get_permalink($st_term->get_taxonomy() , $st_term->get_term_slug())); ?>"><?php echo esc_html($st_term->get_term_name()); ?></a>
        </li>
    <?php endforeach; ?>
</ul>
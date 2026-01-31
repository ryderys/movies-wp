<?php

/**
 * The template for displaying movie play button
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
$terms_ids  = streamit_get_term_relationships($st_data->get_id(), 'movie_tag');
if (!empty($terms_ids)) :
    $terms_data = !empty($terms_ids) ? streamit_get_terms(['include' => $terms_ids, 'per_page' => -1])->results : [];
    if (!empty($terms_data)) : ?>
        <ul class="p-0 mb-0 list-inline d-flex flex-wrap align-items-center gap-1 gap-md-3 mt-2 mt-md-3 movie-tags">
            <?php foreach ($terms_data as $st_term) : ?>
                <li class="position-relative">
                    <a href="<?php echo esc_url(streamit_get_permalink('movie_tag', $st_term->get_term_slug())); ?>"><?php echo esc_html(wp_unslash($st_term->get_term_name())) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>
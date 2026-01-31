<?php

/**
 * The template for displaying archive loop pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (method_exists($st_data, 'get_post_excerpt')) {
    $excerpt = trim(wp_strip_all_tags($st_data->get_post_excerpt(), true));

    // Fallback: use content if excerpt is empty
    if (empty($excerpt) && method_exists($st_data, 'get_post_content')) {
        $excerpt = trim(wp_strip_all_tags($st_data->get_post_content(), true));
    }

    if (!empty($excerpt)) :
?>
        <p class="line-count-2 font-size-14">
            <?php echo esc_html($excerpt); ?>
        </p>
<?php
    endif;
}

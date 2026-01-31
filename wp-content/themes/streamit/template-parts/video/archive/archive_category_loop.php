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
?>

<div class="col">
    <a class="tag-card" href="<?php echo esc_url(streamit_get_permalink('video_category', $st_data->get_term_slug())) ?>" target="_self">
        <span class="css_prefix-category "><?php echo esc_html($st_data->get_term_name()) ?></span>
    </a>
</div>
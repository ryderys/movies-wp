<?php

/**
 * The template for displaying tag pages
 *
 * This template is used for displaying the tag pages of TV shows.
 * It checks if there are any TV shows related to the current tag and displays them.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

// Ensure the template is accessed within WordPress, not directly.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<div class="col">
    <a class="tag-card" href="<?php echo esc_url(streamit_get_permalink('tvshow_tag', $st_data->get_term_slug())) ?>" target="_self">
        <!-- Display the term name (TV show tag) -->
        <span class="tag-title line-count-1">
            <?php echo esc_html($st_data->get_term_name()) ?>
        </span>
    </a>
</div>
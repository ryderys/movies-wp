<?php

/**
 * Template for displaying post summaries within the loop.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Display post meta fields.
streamit_get_template('content/entry-meta-fields.php');


// Check if the current view is not a single post.
if (!is_single()) {

    /**
     * Hook: st_before_not_single_title_content
     *
     * Fires before the title content in non-single views.
     *
     * @param string $post_type Current post type.
     */
    do_action('st_before_not_single_title_content', get_post_type());

    // Display post title if available.
    if (!empty(trim(get_the_title()))) {
        echo '<h3 class="entry-title">';
        echo '<a href="' . esc_url(get_permalink()) . '" rel="bookmark">' . esc_html(get_the_title()) . '</a>';
        echo '</h3>';
    }

    /**
     * Hook: st_midle_not_single_title_content
     *
     * Fires in the middle of the title content for non-single views.
     *
     * @param string $post_type Current post type.
     */
    do_action('st_midle_not_single_title_content', get_post_type());

    // Display excerpt if available and valid.
    if (!empty(get_the_excerpt()) && ord(get_the_excerpt()) !== 38) {
        the_excerpt();
    }

    /**
     * Hook: st_after_not_single_title_content
     *
     * Fires after the title content in non-single views.
     *
     * @param string $post_type Current post type.
     */
    do_action('st_after_not_single_title_content', get_post_type());
} else {
    // Single post view.

    /**
     * Hook: st_before_single_content
     *
     * Fires before single post content.
     *
     * @param string $post_type Current post type.
     */
    do_action('st_before_single_content', get_post_type());
?>

    <h1 class="mb-0 font-size-37">
        <?php echo esc_html(get_the_title()); ?>
    </h1>

    <div class="css_prefix-blog-detail-content">
        <?php the_content(); ?>
    </div>

<?php
    /**
     * Hook: st_after_single_content
     *
     * Fires after single post content.
     *
     * @param string $post_type Current post type.
     */
    do_action('st_after_single_content', get_post_type());
}

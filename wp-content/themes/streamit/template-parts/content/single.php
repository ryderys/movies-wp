<?php

/**
 * The sidebar containing the single page content and associated features like comments and pagination
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Display the content of the page
the_content();

// Check if comments are enabled and whether the post type supports them
if (post_type_supports(get_post_type(), 'comments') && (comments_open() || get_comments_number())) {
    // Load the comments template (if comments are enabled and open)
    comments_template();
}

// Handle pagination if the page has multiple parts
wp_link_pages(array(
    'before' => '<div class="page-links">' . esc_html__('Pages:', 'streamit'), // Label before the page links
    'after'  => '</div>', // Label after the page links
));

?>

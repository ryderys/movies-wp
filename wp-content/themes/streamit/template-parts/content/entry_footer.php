<?php
/**
 * The template for displaying post footer within the loop.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
streamit_get_template('content/entry_tags.php');

// Display a "Read More" link.

if (!is_single()) :
    st_get_blog_readmore_link(get_the_permalink(), esc_html__('Read More', 'streamit'));
endif;

<?php
/**
 * The template for displaying post header within the loop.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check if the post has a featured image
streamit_get_template('content/entry-thumbnail.php');


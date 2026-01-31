<?php
/**
 * The main template file.
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when no specific template matches a query.
 * For example, it serves as the homepage template when no home.php file exists.
 *
 * Learn more about the template hierarchy:
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
/**
 * Load the archive template part for the watchlist posts.
 *
 * This function retrieves a template part from the specified folder
 * and displays the archive results for the watchlist.
 * The 'watchlist' argument corresponds to the template part name (e.g., watchlist.php).
 */

streamit_get_template('shortcode/watchlist.php');

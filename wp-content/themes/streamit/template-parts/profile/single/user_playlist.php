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

global $streamit_core_options;

if (isset($streamit_core_options['streamit_display_playlist']) && $streamit_core_options['streamit_display_playlist'] === 'no') {
    return false;
}

/**
 * Load the archive template part for playlist posts.
 *
 * This function retrieves a template part from the specified folder
 * and displays the archive results for playlists.
 * The 'playlist' argument corresponds to the template part name (e.g., playlist.php).
 */

streamit_get_template('playlist/archive/archive.php');

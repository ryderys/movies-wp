<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * streamit\Redux_Framework\Options\Streamit class
 *
 * @package streamit
 */

// Main Content Section
Redux::set_section($this->opt_name, array(
    'title'           => esc_html__('Content', 'streamit'),
    'desc'            => esc_html__('Options for managing content of Movie/TV Show/Video.', 'streamit'),
    'id'              => 'streamit-content',
    'icon'            => 'custom-header-main',
    'has_group_title' => esc_html__('Streamit Content', 'streamit'),
    'fields'          => array(),
));

// Safely include required files
$pages = [
    'Page/subscribe.php',
    'Page/badge.php',
    'Page/upcoming.php',
    'Page/related-products.php',
    'Page/genres.php',
    'Page/texture.php',
    'Page/ratting.php'
];

// Loop through pages and include them securely
foreach ($pages as $page) {
    $file_path = plugin_dir_path(__FILE__) . $page;
    if (file_exists($file_path)) {
        require_once $file_path;
    }
}

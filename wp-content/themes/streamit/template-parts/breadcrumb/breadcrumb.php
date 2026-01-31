<?php

/**
 * Template part for displaying the Breadcrumb 
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;

if (!is_search()):
    if ((isset($streamit_options['display_breadcrumb']) && ($streamit_options['display_breadcrumb'] == 'no')) || is_404() || (isset($streamit_options['display_banner_meta']) && $streamit_options['display_banner_meta'] == 'no')) {
        return;
    }
endif;


$view_type = isset($view_type) ? $view_type : null;
$content_type = isset($content_type) ? $content_type : null;

//Breadcrumb layout
if( !is_front_page() ):
    do_action('st_breadcrumb_layout', $view_type, $content_type);
endif;
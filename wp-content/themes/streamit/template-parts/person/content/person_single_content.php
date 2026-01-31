<?php

/**
 * The template for displaying content.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if(!empty($st_data->get_post_content())) :
    
echo '<p>'. strip_tags($st_data->get_post_content()) .'</p>';


endif;
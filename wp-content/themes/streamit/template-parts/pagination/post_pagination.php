<?php
/**
 * The template for displaying post padination within the loop.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;
global $wp_query;
$numpages = $wp_query->max_num_pages ? $wp_query->max_num_pages : 1;
$post_type = get_post_type();
if($numpages <= 1){
    return;
}

if(isset($streamit_options['streamit_display_pagination'])) :
    if( $streamit_options['streamit_display_pagination'] == 'load_more' ) {
        echo st_get_load_more_button( $numpages , $post_type , 1 , $streamit_options['streamit_display_blog_loadmore_text'] , $streamit_options['streamit_display_blog_loadmore_text_2'] );
        return;
    } elseif( $streamit_options['streamit_display_pagination'] == 'infinite_scroll' ) {
        echo st_get_loader_wheel_container( $numpages, $post_type, 1, '' );
        return;
    }
endif;

if ( isset( $streamit_options['display_pagination'] ) && $streamit_options['display_pagination'] == 'no' )
    return;
    
st_pagination($numpages);
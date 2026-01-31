<?php
/**
 * Theme functions and definitions.
 */
add_action( 'wp_enqueue_scripts', 'streamit_enqueue_styles' ,99);

function streamit_enqueue_styles() {

wp_enqueue_style( 'parent-style', get_stylesheet_directory_uri() . '/style.css'); 
wp_enqueue_style( 'child-style',get_stylesheet_directory_uri() . '/style.css');
}

/**
 * Redirect Streamit TMDb API calls to Cloudflare Worker
 * This bypasses the block in Iran using a Cloudflare Worker proxy.
 */
add_filter( 'http_request_args', function( $args, $url ) {
    
    // Check if the request is going to TMDb API (fallback for any missed URLs)
    if ( strpos( $url, 'api.themoviedb.org' ) !== false ) {
        
        // Replace the blocked domain with Cloudflare Worker
        $url = str_replace( 'api.themoviedb.org', 'tmdb.youssefi-ashkan-ys.workers.dev', $url );
        
        // Update the URL in the request
        $args['url'] = $url;
        
        // Increase timeout to 40 seconds to account for the proxy delay
        $args['timeout'] = 40;
    }
    return $args;
}, 20, 2 );

/**
 * Also redirect Image requests if they are blocked
 */
add_filter( 'pre_http_request', function( $pre, $args, $url ) {
    if ( strpos( $url, 'image.tmdb.org' ) !== false ) {
        // You can add an image mirror here if posters aren't loading
    }
    return $pre;
}, 10, 3 );

/**
 * Set up My Child Theme's textdomain.
*
* Declare textdomain for this child theme.
* Translations can be added to the /languages/ directory.
*/
function streamit_child_theme_setup() {
load_child_theme_textdomain( 'streamit', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'streamit_child_theme_setup' );


function remove_default_post_type() {
    remove_menu_page('edit.php');
}
add_action('admin_menu', 'remove_default_post_type');


function streamit_enqueue_custom_font() {
    // Load the Vazirmatn font from Google Fonts
    wp_enqueue_style( 'vazirmatn-font', 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap', array(), null );
}
add_action( 'wp_enqueue_scripts', 'streamit_enqueue_custom_font' );

function streamit_enqueue_fontawesome() {
    // Loads the free version of Font Awesome 6
    wp_enqueue_style( 'fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css', array(), '6.5.2' );
}
add_action( 'wp_enqueue_scripts', 'streamit_enqueue_fontawesome' );
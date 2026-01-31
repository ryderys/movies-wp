<?php

/**
 * Common Helper Functions.
 *
 * @package streamit
 */

defined('ABSPATH') || exit;

/**
 * Setup theme features and support.
 *
 * This function is hooked into 'after_setup_theme' and adds support for various
 * theme features like title tags, post formats, menu locations, and more.
 */
add_action('after_setup_theme', function () {

    // Add default posts and comments RSS feed links to head
    add_theme_support('automatic-feed-links');

    // Enable responsive embeds (for oEmbed content)
    add_theme_support('responsive-embeds');

    // Enable HTML5 support for specific elements
    add_theme_support('html5', ['script', 'style', 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);

    // Let WordPress manage the document title
    add_theme_support('title-tag');

    // Enable support for custom logo
    add_theme_support('custom-logo');

    // LifterLMS sidebar support
    add_theme_support('lifterlms-sidebars');

    // Boostify header and footer support
    add_theme_support('boostify-header-footer');

    // Theme builder header/footer parts (if applicable)
    add_theme_support('fl-theme-builder-headers');
    add_theme_support('fl-theme-builder-footers');
    add_theme_support('fl-theme-builder-parts');

    // Enable editor styles for the block editor
    add_theme_support('editor-styles');

    // Enable support for post thumbnails (featured images)
    add_theme_support('post-thumbnails');

    // Add excerpt support for pages
    add_post_type_support('page', 'excerpt');

    // Enable support for post formats
    add_theme_support('post-formats', ['aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio', 'chat']);

    // Register support for Gutenberg wide images
    add_theme_support('align-wide');

    // Enable selective refresh for widgets in customizer
    add_theme_support('customize-selective-refresh-widgets');

    // Header/Footer Elementor support
    add_theme_support('header-footer-elementor');

    //woocommerce support
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    // Register navigation menus
    $st_all_menus = [
        'primary'   => esc_html__('Primary Menu', 'streamit'),
        'secondary' => esc_html__('Secondary Menu', 'streamit'),
        'streamit-footer-menu-link' => esc_html__('Mobile Navigation Menu', 'streamit'),
    ];

    // Allow other functions to modify menu locations
    $st_all_menus = apply_filters('st_register_nav_menus', $st_all_menus);

    // Register menus if there are any locations to register
    if (!empty($st_all_menus)) {
        register_nav_menus($st_all_menus);
    }

    add_filter('wpcf7_form_novalidate', '__return_true');

    add_filter('heartbeat_settings', function ($settings) {
        $settings['interval'] = 10; // seconds
        return $settings;
    });
});


//add widget
add_action(
    'widgets_init',
    function () {
        register_sidebar(
            array(
                'name'          => esc_html__('Blog Sidebar', 'streamit'),
                'id'            => 'streamit-blog-sidebar',
                'description'   => esc_html__('Add widgets here.', 'streamit'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h5 class="widget-title"> <span>  ',
                'after_title'   => ' </span></h5>',
            )
        );

        register_sidebar(array(
            'name'          => esc_html__('Product Sidebar', 'streamit'),
            'class'         => 'nav-list',
            'id'            => 'streamit_product_sidebar',
            'before_widget' => '<div class="widget sidebar_widget widget-woof %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h5 class="widget-title"> <span>',
            'after_title'   => '</span></h5>',
        ));
    }
);

/**
 * Determine whether to display the header based on options.
 *
 * @return bool Whether to display the header.
 */
function streamit_display_header()
{
    global $streamit_options;
    if (!apply_filters('streamit_display_header', true)) return false;

    // Default to displaying header if options are not set
    if (!isset($streamit_options)) {
        return true;
    }

    // Check if header meta display is set to 'no'
    if (isset($streamit_options['display_header_meta']) && $streamit_options['display_header_meta'] === 'no') {
        return false;
    }

    // Check if it's a 404 page and header on 404 is set to '0'
    if (is_404() && isset($streamit_options['header_on_404']) && $streamit_options['header_on_404'] === '0') {
        return false;
    }

    // Return the final result after applying all conditions
    return true;
}

/**
 * Verify whether to display the footer.
 *
 * @return bool True if the footer should be displayed, false otherwise.
 */
function streamit_display_footer()
{
    global $streamit_options;

    if (!apply_filters('streamit_display_footer', true)) return false;
    // Default to displaying footer if options are not set
    if (!isset($streamit_options)) {
        return true;
    }


    // Check if footer meta display is set to 'no'
    if (isset($streamit_options['display_footer_meta']) && $streamit_options['display_footer_meta'] === 'no') {
        return false;
    }

    // Check if it's a 404 page and footer on 404 is set to '0'
    if (is_404() && isset($streamit_options['footer_on_404']) && $streamit_options['footer_on_404'] === '0') {
        return false;
    }

    // Return the final result after applying all conditions
    return true;
}

/**
 * Embed Video, Audio, Iframe, etc.
 *
 * @param int $post_id Post ID to fetch the embedded content from.
 * 
 * @return string|null Embed external resource, or null if not found.
 */
function st_get_embed_video($post_id)
{
    // Ensure $post_id is valid and sanitize it
    $post_id = intval($post_id);

    // Return null if post_id is invalid
    if (!$post_id) {
        return null;
    }

    $post = get_post($post_id);

    // Ensure we have post content to work with
    if (!$post || empty($post->post_content)) {
        return null;
    }

    $content = do_shortcode(apply_filters('the_content', $post->post_content));

    // Get all embedded media in the content
    $embeds = get_media_embedded_in_content($content);

    // Allow filtering of the embeds before processing
    $embeds = apply_filters('st_get_embeds', $embeds, $post_id);

    // Check if embeds exist and look for specific media types
    if (!empty($embeds)) {
        foreach ($embeds as $embed) {
            if (
                strpos($embed, 'video') !== false ||
                strpos($embed, 'youtube') !== false ||
                strpos($embed, 'vimeo') !== false ||
                strpos($embed, 'dailymotion') !== false ||
                strpos($embed, 'vine') !== false ||
                strpos($embed, 'wordpress.tv') !== false ||
                strpos($embed, 'embed') !== false ||
                strpos($embed, 'audio') !== false ||
                strpos($embed, 'iframe') !== false ||
                strpos($embed, 'object') !== false
            ) {
                return $embed; // Return the first matching embed
            }
        }
    }

    return null;
}


/**
 * HTML Structure Of Read More Button.
 *
 * @param string $link  URL for the read more link.
 * @param string $label Text for the read more link.
 * @param string $extra_classes Optional additional classes for the button.
 * @param array  $attributes Optional extra attributes for the anchor tag.
 * 
 * @return void
 */
function st_get_blog_readmore_link($link, $label = 'Read More', $extra_classes = '', $attributes = [])
{
    // Sanitize and escape URL and label
    $link = esc_url($link);
    $label = esc_html($label);

    // Prepare additional classes
    $classes = 'btn btn-link ' . esc_attr($extra_classes);

    // Build additional attributes string
    $attribute_string = '';
    if (!empty($attributes)) {
        foreach ($attributes as $key => $value) {
            $attribute_string .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
    }

    // Output the button HTML
    echo '<a class="' . $classes . '" href="' . $link . '" ' . $attribute_string . '><span>' . $label . '</span></a>';
}


/**
 * Generate pagination links for a WordPress site.
 *
 * @param int  $numpages   Total number of pages. Defaults to global $wp_query->max_num_pages.
 * @param int  $pagerange  Number of page links to show around the current page. Default is 2.
 * @param int  $paged      Current page number. Defaults to global $paged.
 * @param bool $echo       Whether to echo the pagination HTML or return it. Default is true.
 * 
 * @return string|null    Pagination HTML if $echo is false, otherwise echoes the pagination.
 */
function st_pagination($numpages = '', $pagerange = 2, $paged = 1, $echo = true)
{
    global $wp_query;

    // Ensure global $paged is set if not passed as an argument
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Determine the total number of pages if not provided
    if (empty($numpages)) {
        $numpages = $wp_query->max_num_pages ? $wp_query->max_num_pages : 1;
    }

    // Construct pagination arguments
    $pagination_args = array(
        'format'        => '?paged=%#%',
        'total'         => $numpages,
        'current'       => $paged,
        'show_all'      => false,
        'end_size'      => 1,
        'mid_size'      => $pagerange,
        'prev_next'     => true,
        'prev_text'     => st_get_icon('arrow-prev'),
        'next_text'     => st_get_icon('arrow-next'),
        'type'          => 'list',
        'add_args'      => false,
        'add_fragment'  => ''
    );

    // Generate pagination links
    $paginate_links = paginate_links($pagination_args);

    // Output or return the pagination links
    if ($paginate_links) {
        $pagination_html = '<div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="pagination justify-content-center">
                                    <nav aria-label="Page navigation">';
        $pagination_html .= sprintf('%s', $paginate_links);
        $pagination_html .= '      </nav>
                                </div>
                            </div>';

        if ($echo) {
            echo $pagination_html;
        } else {
            return $pagination_html;
        }
    }

    return null; // In case no pagination is generated
}

/**
 * Check if the primary navigation menu exists.
 *
 * @return bool True if the primary navigation menu exists, false otherwise.
 */
function st_check_primary_nav_menu()
{
    return has_nav_menu('primary');
}

/**
 * Display the primary navigation menu.
 *
 * @param array $args Optional. An array of arguments to pass to wp_nav_menu(). Default is an empty array.
 */
function display_primary_nav_menu(array $args = [])
{
    // Set default arguments
    $defaults = [
        'container' => 'nav',                // Wrap the menu in a <nav> element for better semantics
        'theme_location' => 'primary',       // The location of the menu
    ];

    // Merge passed arguments with defaults
    $args = array_merge($defaults, $args);

    // Display the menu
    wp_nav_menu($args);
}


/**
 * Adds a div before content based on post type.
 *
 * @param string $post_type The current post type.
 */
add_action('st_before_content', 'st_before_content_action', 10, 1);
function st_before_content_action($post_type)
{
    // Default div opening tag
    $div = '<div class="css_prefix-blog-box">';

    // Allow modification via filter
    $div = apply_filters('st_before_content_action', $div, $post_type);

    // Output the sanitized div
    echo wp_kses_post($div);
}

/**
 * Adds a closing div after content based on post type.
 *
 * @param string $post_type The current post type.
 */
add_action('st_after_content', 'st_after_content_action', 10, 1);
function st_after_content_action($post_type)
{
    // Default div closing tag
    $div = '</div>';

    // Allow modification via filter
    $div = apply_filters('st_after_content_action', $div, $post_type);

    // Output the sanitized div
    echo wp_kses_post($div);
}

/**
 * Formats the runtime from 'H:MM' format to 'Xhr : Ymins' with proper escaping.
 * Allows overriding the formatted runtime via a filter.
 *
 * @param string $run_time Runtime in 'H:MM' format.
 * @return string Formatted and escaped runtime.
 */
function st_format_runtime($run_time)
{
    if (empty($run_time)) return;
    // Split the runtime into hours and minutes
    $time_parts = explode(':', $run_time);
    $hours = isset($time_parts[0]) ? intval($time_parts[0]) : 0; // Get hours
    $minutes = isset($time_parts[1]) ? intval($time_parts[1]) : 0; // Get minutes

    // Create the formatted runtime string
    $formatted_run_time = '';

    // If hours are greater than 0, append hours to the formatted string
    if ($hours > 0) {
        $formatted_run_time .= esc_html($hours) . esc_html__(' h', 'streamit');
    }

    // If minutes are greater than 0, append minutes to the formatted string
    if ($minutes > 0) {
        // Add colon only if hours are present
        if ($hours > 0) {
            $formatted_run_time .= ' : ';
        }
        $formatted_run_time .= esc_html($minutes) . esc_html__(' min', 'streamit');
    }

    // If both hours and minutes are zero, return "0mins"
    if ($hours === 0 && $minutes === 0) {
        return '';
    }

    // Apply the filter to allow overriding the runtime format
    return apply_filters('st_format_runtime', trim($formatted_run_time), $run_time);
}



/**
 * Returns the URL of the default 'search not found' image.
 *
 * This function provides the URL of a default image to display when no search results are found.
 * Developers can modify this URL via the 'streamit_search_not_found_image' filter hook.
 *
 * Example usage of the filter:
 * 
 * // In a plugin or theme, to change the image URL:
 * add_filter('streamit_search_not_found_image', function($image_url) {
 *     return 'https://example.com/path/to/custom-image.png';
 * });
 *
 * @return string The URL of the 'search not found' image.
 */
function streamit_search_not_found_image()
{
    // Get the default search-not-found image URL
    $image_url = get_template_directory_uri() . '/static/assets/images/defaults/search-not-found.png';

    // Allow filtering of the image URL through the 'streamit_search_not_found_image' filter
    return apply_filters('streamit_search_not_found_image', esc_url($image_url));
}


/**
 * Allow additional MIME types for media uploads.
 *
 * This function extends WordPress's allowed MIME types to support
 * various video, image, and other media formats.
 *
 * @param array $mimes Existing allowed MIME types.
 * @return array Modified MIME types array.
 */
function st_allow_all_mime_types($mimes)
{
    // Common Video Formats
    $mimes['mp4']   = 'video/mp4';
    $mimes['webm']  = 'video/webm';
    $mimes['ogv']   = 'video/ogg';
    $mimes['mov']   = 'video/quicktime';
    $mimes['avi']   = 'video/x-msvideo';
    $mimes['mkv']   = 'video/x-matroska';
    $mimes['wmv']   = 'video/x-ms-wmv';
    $mimes['flv']   = 'video/x-flv';
    $mimes['m4v']   = 'video/x-m4v';
    $mimes['ogx']   = 'video/odx';


    // Streaming Formats
    $mimes['m3u8']  = 'application/x-mpegURL';
    $mimes['ts']    = 'video/mp2t';

    // Common Image Formats
    $mimes['jpg']   = 'image/jpeg';
    $mimes['jpeg']  = 'image/jpeg';
    $mimes['png']   = 'image/png';
    $mimes['gif']   = 'image/gif';
    $mimes['bmp']   = 'image/bmp';
    $mimes['webp']  = 'image/webp';
    $mimes['svg']   = 'image/svg+xml';
    $mimes['ico']   = 'image/vnd.microsoft.icon';

    return $mimes;
}
add_filter('upload_mimes', 'st_allow_all_mime_types', 100);


/**
 * Convert a release date to readable WordPress format.
 *
 * @param string|int $release_date MySQL date string or timestamp.
 * @return string Formatted readable date.
 */
function streamit_get_readable_release_date($release_date)
{
    if (empty($release_date)) {
        return '';
    }

    // Convert to timestamp if it's a string date
    if (! is_numeric($release_date)) {
        $release_date = strtotime($release_date);
    }

    return date_i18n(get_option('date_format'), $release_date);
}

<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
/**
 * Include a template part based on the given template name.
 *
 * This function attempts to locate a template file, first in the child theme,
 * then in the parent theme, and finally in the provided default path. It includes
 * the template file if found, and allows for the passing of arguments to the template.
 *
 * @param string $template_name The name of the template part to include.
 * @param array  $args          Optional. Additional arguments to pass to the template part.
 * @param string $default_path  Optional. The default path to look for the template file
 *                              if it is not found in the child or parent theme. Default is
 *                              'template-parts' folder in the theme directory.
 * 
 * @return void|WP_Error Returns an error if the template file does not exist.
 */
function streamit_get_template($template_name, $args = array(), $default_path = '')
{
    // Set default path if not provided, points to the theme's template-parts folder
    if (empty($default_path)) {
        $default_path = get_template_directory() . '/template-parts/'; // Fallback to the parent theme folder
    }

    // Look for the template in the child theme first, then the parent theme
    $template = locate_template(
        array(
            'template-parts/' . $template_name,  // Check in child/parent 'template-parts' folder
            $template_name                       // Check in child/parent theme root directory
        )
    );

    // If not found in the child or parent theme, fallback to the provided default path
    if (empty($template)) {
        $template = $default_path . $template_name;
    }

    // Check if the template file exists in the resolved path
    if (!file_exists($template)) {
        // Return an error if the template doesn't exist
        return new WP_Error('template_error', sprintf(__('%s does not exist.', 'streamit'), '<code>' . esc_html($template) . '</code>'));
    }

    // Trigger action before including the template file, useful for custom behavior
    do_action('streamit_theme_before_template_part', $template, $args, $default_path);

    // If arguments are provided, extract them to variables for use in the template
    if (!empty($args) && is_array($args)) {
        extract($args);
    }
    // Include the template file
    include $template;

    // Trigger action after including the template file, useful for custom behavior
    do_action('streamit_theme_after_template_part', $template, $args, $default_path);
}


/**
 * Retrieve the placeholder image URL.
 *
 * This function returns the URL of the placeholder image used across the site.
 * It can be filtered using the 'streamit_placeholder_image' hook to modify the placeholder image URL.
 *
 * @return string The URL of the placeholder image.
 */
function streamit_placeholder_image(): string
{
    // Define the default placeholder image URL
    $placeholder = get_template_directory_uri() . '/static/assets/images/defaults/placeholder.png';

    /**
     * Filters the placeholder image URL.
     *
     * @param string $placeholder The default placeholder image URL.
     * 
     * @return string The filtered placeholder image URL.
     */
    return apply_filters('streamit_placeholder_image', $placeholder);
}


/**
 * Retrieve the settings for the Slick slider.
 *
 * This function generates the settings for the Slick slider, including default settings for
 * the slider and any user-defined overrides passed through the $args parameter.
 *
 * @param array $args Optional. An array of user-defined settings to override the default ones.
 *                    Default is an empty array.
 * 
 * @return string The JSON-encoded settings for the Slick slider.
 */
function st_get_slick_slider_settings(array $args = []): string
{
    // Default settings for the Slick slider

    $default_settings = [
        'dots'            => false,
        'slidesToShow'    => 6,
        'slidesToScroll'  => 1,
        'arrows'          => true,
        'autoplay'        => false,
        'autoplaySpeed'   => 2000,
        'speed'           => 300,
        'loop'            => true,
        'responsive'      => [
            [
                'breakpoint' => 1024,
                'settings'   => [
                    'slidesToShow' => 4,
                ]
            ],
            [
                'breakpoint' => 600,
                'settings'   => [
                    'slidesToShow' => 3,
                ]
            ]
        ]
    ];

    // Merge the user-defined settings with the default settings
    $settings = array_merge($default_settings, $args);
    // Return the JSON-encoded settings, escaped for safe HTML output
    return esc_attr(wp_json_encode($settings));
}


/**
 * Generate a star rating HTML based on a numerical rating.
 *
 * This function generates the HTML for displaying a star rating system. The rating is scaled
 * from a 1-10 value to a 5-star scale, with full, half, and blank stars. 
 *
 * @param float|int $rating The numerical rating (1-10) to convert into a star rating.
 * If the rating exceeds 10, it will default to 5 stars.
 *
 * @return string The HTML output for the star rating.
 */
function st_star_rating($rating)
{
    // Return early if no rating is provided
    if (empty($rating)) {
        return '';
    }

    // Normalize the rating to a 5-star scale
    if ($rating > 10) {
        $rating = 5;
    } else {
        $rating = $rating / 2;  // Scale from 10 to 5
    }

    // Define star icons for full, half, and empty stars
    $stars = [
        '<i class="icon-star-fill-icon text-warning"></i>', // Full star
        '<i class="icon-star-half-icon text-warning"></i>', // Half star
        '<i class="icon-star-icon text-warning"></i>',      // Empty star
    ];

    // Calculate the number of full, half, and blank stars
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars) >= 0.5 ? 1 : 0;
    $blank_stars = 5 - ($full_stars + $half_star);

    // Generate the HTML for the star rating
    $output = str_repeat($stars[0], $full_stars) .
        str_repeat($stars[1], $half_star) .
        str_repeat($stars[2], $blank_stars);

    // Allow for filterable output
    return apply_filters('st_custom_star_rating', $output, $rating);
}


/**
 * Retrieve the IMDb logo URL.
 *
 * This function retrieves the IMDb logo URL based on the options set in the theme's settings.
 * A filter is applied to allow modifications to the logo URL.
 *
 * @return array The IMDb logo URL wrapped in an array, allowing for easy modification.
 */
function streamit_get_imdb_logo()
{
    global $streamit_options;

    // Default IMDb logo URL
    $default_logo_url = get_template_directory_uri() . '/admin/assets/images/redux/IMDB_Logo.svg';

    // Check if a custom logo URL is set in the theme options
    $logo_url = isset($streamit_options['streamit_imdb_display_logo']) && !empty($streamit_options['streamit_imdb_display_logo']['url'] && isset($streamit_options['streamit_imdb_display_logo']['url']))
        ? $streamit_options['streamit_imdb_display_logo']['url']
        : $default_logo_url;

    // Return the logo URL wrapped in an array for flexibility and future use
    return apply_filters('streamit_imdb_logo_url', ['url' => $logo_url]);
}

/**
 * Displays the comment form for reviews with a rating section.
 *
 * This function displays a review form with a star rating system for logged-in users.
 * If the user is not logged in, they will be prompted to log in.
 *
 * @param int    $post_id   The post ID where the review is being added.
 * @param string $post_type The post type (e.g., 'post', 'movie', etc.).
 */
function st_comment_html_form($post_id, $post_type)
{
    global $streamit_options;

    // Ensure that the user is logged in
    if (is_user_logged_in()) :
        // Get the current logged-in user
        $current_user = wp_get_current_user();
?>

        <form id="st_comment_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">

            <!-- Add Nonce for security -->
            <?php wp_nonce_field('submit_st_review', 'st_ajax_nonce'); ?>
            <input id="cm_id" type="hidden" name="comment_id" value="">

            <!-- Hidden fields for post type and post ID -->
            <input id="cm_post_type" type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>">
            <input id="cm_post_id" type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">

            <!-- User ID for logged-in users -->
            <input id="cm_user_id" type="hidden" name="user_id" value="<?php echo esc_attr(get_current_user_id()); ?>">

            <!-- Display logged-in user's name -->
            <div class="form-group">
                <input id="cm_name" name="cm_name" type="hidden" value="<?php echo esc_attr($current_user->display_name); ?>" />
                <input id="cm_email" name="cm_email" type="hidden" value="<?php echo esc_attr($current_user->user_email); ?>" />
                <p class="mt-0 text-heading"><?php esc_html_e('Logged in as:', 'streamit'); ?> <?php echo esc_html($current_user->display_name); ?></p>
            </div>

            <!-- Rating section -->
            <div class="form-group mb-4">
                <label for="rating" class="form-label"><?php esc_html_e('Your Rating', 'streamit'); ?></label>
                <div class="star-rating">
                    <?php
                    // Loop to display star radio buttons for ratings (1-5 stars)
                    for ($i = 5; $i >= 1; $i--) :
                    ?>
                        <input type="radio" id="star<?php echo esc_attr($i); ?>" name="rating" value="<?php echo esc_attr($i); ?>" />
                        <label class="form-label" for="star<?php echo esc_attr($i); ?>" title="<?php printf(esc_attr__(' %d stars', 'streamit'), $i); ?>">
                            <i class="icon-star-fill-icon icon-fill"></i>
                            <i class="icon-star-icon icon-unfill"></i>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Review text area -->
            <div class="form-group mb-4">
                <label class="form-label" for="cm_details"><?php esc_html_e('Your Review', 'streamit'); ?></label>
                <textarea id="cm_details" class="form-control" name="cm_details" cols="45" rows="8"></textarea>
            </div>

            <!-- Submit button -->
            <button type="submit" class="btn btn-primary " name="submit_review">
                <span class="st-loader d-none"></span><?php esc_attr_e('Submit Review', 'streamit'); ?>
            </button>
        </form>

    <?php else :
        // If the user is not logged in, show the login prompt
        $login_page_link = isset($streamit_options['streamit_signin_link']) && !empty($streamit_options['streamit_signin_link']) ? get_page_link($streamit_options['streamit_signin_link']) : '';
        $login_title = isset($streamit_options['streamit_signin_title']) && !empty($streamit_options['streamit_signin_title']) ? $streamit_options['streamit_signin_title'] : esc_html__('Login', 'streamit');
    ?>
        <p>
            <?php
            echo sprintf(
                esc_html__('You have to %s to share the review', 'streamit'),
                sprintf('<a href="%s">%s</a>', esc_url($login_page_link), esc_html($login_title))
            );
            ?>
        </p>

    <?php endif;
}

/**
 * Displays the details of a comment including the user's avatar, name, date, rating, and comment content.
 *
 * @param object $comment The comment object.
 */
function st_comment_html_details($comment, $action = false)
{
    // Get user avatar URL with a default size of 96.
    $user_avatar = get_avatar_url($comment->get_user_id(), array('size' => 96));
    $user_avatar_new = get_user_meta($comment->get_user_id(), 'user_avatar', true);

    // Ensure all dynamic content is escaped appropriately.
    ?>
    <div class="review-card">
        <div class="review-detail rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <!-- Display user avatar -->
                    <img src="<?php echo !empty($user_avatar_new) ? esc_url($user_avatar_new) : esc_url($user_avatar); ?>" class="img-fluid user-img rounded-circle" alt="<?php echo esc_attr($comment->get_comment_author()); ?>">

                    <div>
                        <!-- Display comment author's name and date -->
                        <h6 class="line-count-1 m-0"><?php echo esc_html($comment->get_comment_author()); ?></h6>
                        <p class="mb-0 mt-1 small"><?php echo esc_html($comment->get_comment_date()); ?></p>
                    </div>
                </div>

                <!-- Display star rating -->
                <?php
                // Assuming you have a 'rating' custom field in the comment.
                $rating = (int) $comment->get_rating();
                ?>
                <div class="star-rating" data-rating="<?php echo esc_attr($rating); ?>">
                    <?php for ($i = 5; $i >= 1; $i--) : ?>
                        <input type="radio" id="star<?php echo $i . '-' . $comment->get_id(); ?>" name="rating-<?php echo $comment->get_id(); ?>" value="<?php echo $i; ?>" <?php checked($i, $rating); ?> disabled />
                        <label class="form-label" for="star<?php echo $i . '-' . $comment->get_id(); ?>" title="<?php echo esc_attr($i . ' ' . __('stars', 'streamit')); ?>">
                            <i class="icon-star-fill-icon icon-fill"></i>
                            <i class="icon-star-icon icon-unfill"></i>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Display comment content -->
            <p class="mb-0 mt-3 pt-3 border-top fw-medium">
                <?php echo esc_html($comment->get_comment_content()); ?>
            </p>

            <!-- actions  -->
            <?php if (($action) && ($comment->get_user_id() == get_current_user_id())) : ?>
                <div class="d-flex align-items-center gap-3 justify-content-end">
                    <a id="openReviewButton" class="btn btn-link" data-cm-id="<?php echo esc_attr($comment->get_id()); ?>" data-cm-rating="<?php echo esc_attr($rating); ?>" data-cm-review="<?php echo esc_html($comment->get_comment_content()); ?>" data-bs-toggle="offcanvas" href="#offcanvasReview" role="button" aria-controls="offcanvasReview">
                        <span><?php echo esc_html__('Edit', 'streamit'); ?></span>
                    </a>
                    <a id="st_delete_comment" data-comment_id="<?php echo esc_attr($comment->get_id()); ?>" data-post_id="<?php echo esc_attr($comment->get_comment_post_ID()); ?>" data-post_type="<?php echo esc_attr($comment->get_post_type()); ?>" type="button" class="btn btn-link p-0 d-flex align-items-center gap-1">
                        <span class="st-loader d-none"></span>
                        <span><?php echo esc_html__('Delete', 'streamit'); ?></span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php
}


/**
 * Generate a conditional notification message with a login link.
 *
 * @param string $st_message Message template. Use %s for the login link placeholder.
 * @param string $st_url URL to insert into the message.
 */
function st_login_user_notification($st_message, $st_url)
{
    // Ensure the message contains a placeholder
    if (strpos($st_message, '%s') === false) {
        $st_message .= ' %s';
    }

    // Build login link
    $login_link = sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_url($st_url),
        esc_html__('Login', 'streamit')
    );

    // Format final message
    $formatted_message = sprintf($st_message, $login_link);

    echo wp_kses_post($formatted_message);
}


/**
 * Get SVG or fallback to img tag.
 *
 * Retrieves inline SVG content if the file is an SVG; otherwise,
 * returns an img tag with the provided class and alt attributes.
 *
 * @param string $file  The file path or URL of the image.
 * @param string $class Optional. The CSS class for the img tag. Default is an empty string.
 * @param string $alt   Optional. The alt text for the img tag. Default is an empty string.
 *
 * @return string The SVG content or an img tag.
 */
function st_get_image_or_svg($file, $class = '', $alt = '')
{
    global $wp_filesystem;

    // Ensure the WP filesystem is loaded.
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();

    // Get the file type.
    $filetype = wp_check_filetype($file);

    // Check if the file is an SVG.
    if (isset($filetype['ext']) && 'svg' === $filetype['ext']) {
        // Safely retrieve the file contents.
        $svg_content = $wp_filesystem->get_contents($file);

        // If SVG content is available, return it.
        if (! empty($svg_content)) {
            return $svg_content;
        }
    }

    // If not an SVG or SVG content isn't available, return a fallback img tag.
    return '<img class="' . esc_attr($class) . '" src="' . esc_url($file) . '" alt="' . esc_attr($alt) . '">';
}

/**
 * Generates a Load More button.
 *
 * @param int    $total_pages     Total number of pages available.
 * @param string $post_type       The post type for the query.
 * @param int    $current_page    The current page number.
 * @param int    $per_page        The per page number.
 * @param int    $settings     Elementor data.
 * @param string $original_text   The original text for the button.
 * @param string $loading_text    The text to display while loading.
 * @param array  $css_classes     Additional CSS classes to apply.
 *
 * @return string HTML for the Load More button.
 */
function st_get_load_more_button($total_pages, $post_type, $current_page, $original_text, $loading_text, $per_page = '', $css_classes = '', $extra_settings = '', $post_id = '')
{
    ob_start(); // Start output buffering
?>
    <div class="text-center mt-4">
        <a id="css_prefix_post_load_more"
            data-total-pages="<?php echo esc_attr($total_pages); ?>"
            data-post-type="<?php echo esc_attr($post_type); ?>"
            data-current-page="<?php echo esc_attr($current_page); ?>"
            data-per-page="<?php echo esc_attr($per_page); ?>"
            tabindex="0"
            data-loading-text="<?php echo esc_attr($loading_text); ?>"
            data-original-text="<?php echo esc_attr($original_text); ?>"
            data-extra-settings="<?php echo esc_attr(json_encode($extra_settings)); ?>"
            data-post-id="<?php echo esc_attr($post_id); ?>"
            class="css_prefix_load_more css_prefix_post_load_more btn btn-primary <?php echo esc_attr($css_classes); ?>">
            <span data-parallax="scroll"><?php echo esc_html($original_text); ?></span>
        </a>
    </div>
<?php
    return ob_get_clean(); // Return the buffered content
}


/**
 * Generates a Loader Wheel Container.
 *
 * @param int    $total_pages     Total number of pages available.
 * @param string $post_type       The post type for the query.
 * @param int    $current_page    The current page number.
 * @param string $css_classes     Additional CSS classes to apply.
 *
 * @return string HTML for the loader wheel container.
 */
function st_get_loader_wheel_container($total_pages, $post_type, $current_page, $post_per_page, $css_classes = '', $extra_settings = [])
{
    ob_start(); // Start output buffering
?>
    <div style="display:none;" id="css_prefix_loader-wheel-container"
        class="loader-wheel-container <?php echo esc_attr($css_classes); ?>"
        data-total-pages="<?php echo esc_attr($total_pages); ?>"
        data-post-type="<?php echo esc_attr($post_type); ?>"
        data-current-page="<?php echo esc_attr($current_page); ?>"
        data-per-page="<?php echo esc_attr($post_per_page); ?>"
        data-extra-settings="<?php echo esc_attr(json_encode($extra_settings)); ?>">
    </div>
<?php
    return ob_get_clean(); // Return the buffered content
}

// for remove wpcf7( Contact form 7 ) <p> 
add_filter('wpcf7_autop_or_not', '__return_false');

/**
 * Filter the language attributes to set the `dir` attribute based on a cookie.
 *
 * @param string $output The existing language attributes.
 * @return string Updated language attributes with the `dir` attribute set.
 */
add_filter('language_attributes', 'st_direction_attributes');
function st_direction_attributes($output)
{
    if (is_admin()) {
        return $output;
    }

    global $streamit_options;

    // Default direction.
    $default_direction = (isset($streamit_options['rtl_switcher'])) ? $streamit_options['rtl_switcher'] : 'ltr';

    // Check if the cookie is set and retrieve its value.
    $cookie_direction = isset($_COOKIE['theme_scheme_direction'])
        ? sanitize_text_field(wp_unslash($_COOKIE['theme_scheme_direction']))
        : $default_direction;

    // Validate the cookie value to ensure it's either 'rtl' or 'ltr'.
    $st_dir = ('rtl' === $cookie_direction) ? 'rtl' : $default_direction;

    // Get the site's language.
    $lang = get_bloginfo('language');

    // Return formatted output.
    return sprintf('dir="%s" lang="%s"', esc_attr($st_dir), esc_attr($lang));
}

function st_get_socialmedia_list($social_text = false)
{
    global $streamit_options;

    if (!isset($streamit_options['social_media_options'])) {
        return;
    }

    $st_media = $streamit_options['social_media_options'];
    if (empty($st_media)) return;
    $text_array = [
        'facebook'      => [esc_html__('Facebook', 'streamit'), esc_html__('fb', 'streamit')],
        'x-twitter'     => [esc_html__('X', 'streamit'), esc_html__('tw', 'streamit')],
        'github'        => [esc_html__('Github', 'streamit'), esc_html__('gh', 'streamit')],
        'instagram'     => [esc_html__('Instagram', 'streamit'), esc_html__('in', 'streamit')],
        'linkedin'      => [esc_html__('LinkedIn', 'streamit'), esc_html__('ln', 'streamit')],
        'tumblr'        => [esc_html__('Tumblr', 'streamit'), esc_html__('tl', 'streamit')],
        'pinterest'     => [esc_html__('Pinterest', 'streamit'), esc_html__('pt', 'streamit')],
        'dribbble'      => [esc_html__('Dribbble', 'streamit'), esc_html__('db', 'streamit')],
        'reddit'        => [esc_html__('Reddit', 'streamit'), esc_html__('rd', 'streamit')],
        'flickr'        => [esc_html__('Flicker', 'streamit'), esc_html__('fc', 'streamit')],
        'skype'         => [esc_html__('skype', 'streamit'), esc_html__('sp', 'streamit')],
        'youtube'       => [esc_html__('Youtube Play', 'streamit'), esc_html__('yt', 'streamit')],
        'vimeo'         => [esc_html__('Vimeo', 'streamit'), esc_html__('vm', 'streamit')],
        'soundcloud'    => [esc_html__('Soundcloud', 'streamit'), esc_html__('sc', 'streamit')],
        'wechat'        => [esc_html__('Wechat', 'streamit'), esc_html__('wc', 'streamit')],
        'renren'        => [esc_html__('Renren', 'streamit'), esc_html__('rr', 'streamit')],
        'weibo'         => [esc_html__('Weibo', 'streamit'), esc_html__('wb', 'streamit')],
        'xing'          => [esc_html__('Xing', 'streamit'), esc_html__('xi', 'streamit')],
        'qq'            => [esc_html__('QQ', 'streamit'), esc_html__('qq', 'streamit')],
        'rss'           => [esc_html__('Rss', 'streamit'), esc_html__('rs', 'streamit')],
        'vk'            => [esc_html__('VK', 'streamit'), esc_html__('vk', 'streamit')],
        'behance'       => [esc_html__('Behance', 'streamit'), esc_html__('bh', 'streamit')],
        'snapchat'      => [esc_html__('Snapchat', 'streamit'), esc_html__('sp', 'streamit')],
    ];
    ob_start(); ?>

    <div class="css_prefix-share">
        <ul class="m-0 d-inline">
            <?php
            foreach ($st_media as $key => $value) {
                if ($value) {
                    if (!$social_text) {
                        echo '<li class="d-inline-block list-inline-item"><a target="_blank" href="' . esc_url($value) . '"><i class="icon-' . esc_attr($key) . '-share"></i></a></li>';
                    } else {
                        echo '<li class="d-inline-block list-inline-item"><a target="_blank" href="' . esc_url($value) . '">' . esc_html($text_array[$key][1]) . '<span>' . esc_html($text_array[$key][0]) . '</span></a></li>';
                    }
                }
            }
            ?>
        </ul>
    </div>
<?php

    $st_media_icone = ob_get_clean();
    return $st_media_icone;
}

// add_action('admin_post_edit_profile', 'handle_edit_profile_form');
function handle_edit_profile_form()
{
    // Verify nonce
    if (!isset($_POST['edit_profile_nonce']) || !wp_verify_nonce($_POST['edit_profile_nonce'], 'edit_profile_action')) {
        wp_die(__('Security check failed.', 'streamit'));
    }

    // Check if the user is logged in
    if (!is_user_logged_in()) {
        wp_die(__('You must be logged in to edit your profile.', 'streamit'));
    }

    $user_id = get_current_user_id();
    $errors = [];

    // Sanitize and update user data
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $user_email = sanitize_email($_POST['user_email']);

    // Check if the email already exists
    if (email_exists($user_email) && $user_email !== get_userdata($user_id)->user_email) {
        $errors[] = __('The email address is already registered with another account.', 'streamit');
    }

    if (empty($errors)) {
        // Update user data
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'user_email' => $user_email,
        ]);
    }

    // Handle avatar upload
    if (!empty($_FILES['user_avatar']['name'])) {
        // Check server upload size
        $upload_size_limit = wp_max_upload_size();
        if ($_FILES['user_avatar']['size'] > $upload_size_limit) {
            $errors[] = __('The uploaded image exceeds the maximum file size allowed by the server.', 'streamit');
        } else {
            // Handle image upload
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $uploaded_file = wp_handle_upload($_FILES['user_avatar'], ['test_form' => false]);

            if (isset($uploaded_file['file'])) {
                // Remove old avatar if new avatar is uploaded successfully
                $old_avatar = get_user_meta($user_id, 'user_avatar', true);
                if ($old_avatar) {
                    // Optionally, delete the old image file from the server
                    $old_avatar_path = get_attached_file(attachment_url_to_postid($old_avatar));
                    if (file_exists($old_avatar_path)) {
                        unlink($old_avatar_path);
                    }
                }

                // Update the user avatar URL
                update_user_meta($user_id, 'user_avatar', $uploaded_file['url']);
            } else {
                $errors[] = __('Error uploading profile picture. Please try again.', 'streamit');
            }
        }
    }

    // Redirect back to the same page with errors or success message
    $redirect_url = add_query_arg([
        'page' => 'profile-edit', // Adjust with your profile edit page's query variable
        'updated' => !empty($errors) ? 'false' : 'true',
        'errors' => !empty($errors) ? urlencode(implode(';', $errors)) : ''
    ], wp_get_referer());

    wp_redirect($redirect_url);
    exit;
}

/**
 * Retrieves the button text based on the specified option key or returns the default text.
 *
 * This function checks if a custom button text is set for the given option key in the global
 * `$streamit_options` array. If a custom text is found, it returns that text; otherwise,
 * it returns the provided default text, ensuring the text is properly escaped for safe HTML output.
 *
 * @param string $option_key   The key used to retrieve the custom button text from the options array.
 * @param string $default_text The default text to return if no custom text is found.
 * @return string The custom button text if available; otherwise, the default text.
 */
function streamit_get_button_text($option_key, $default_text)
{
    global $streamit_options;
    return isset($streamit_options[$option_key])
        ? $streamit_options[$option_key]
        : esc_html__($default_text, 'streamit');
}

/**
 * Outputs the content of a specified sidebar with the current year replacing the {{year}} placeholder.
 *
 * This function captures the content of the specified sidebar, replaces any occurrences of the
 * placeholder {{year}} with the current year, and then outputs the modified content. It ensures
 * that the output is properly escaped for safe inclusion in HTML.
 *
 * @param string $st_sidebar_id The ID of the sidebar to display.
 * @return void
 */
function streamit_replace_text_widget($st_sidebar_id)
{
    ob_start();
    dynamic_sidebar($st_sidebar_id);
    echo wp_kses_post(str_replace('{{year}}', date('Y'), ob_get_clean()));
}


/**
 * Retrieve the login page URL.
 *
 * This function fetches the login page URL from the provided options.
 * If no custom login page URL is set, it defaults to the WordPress admin login page.
 *
 * @since 1.0.0
 *
 * @global array $streamit_options An array containing plugin or theme options.
 *
 * @return string The login page URL.
 */
function streamit_login_page_url()
{
    global $streamit_options;

    $login_url = (isset($streamit_options['streamit_signin_link']) && !empty($streamit_options['streamit_signin_link'])) ? get_page_link($streamit_options['streamit_signin_link']) : wp_login_url();

    return apply_filters('streamit_login_page_url', esc_url_raw($login_url));
}

/**
 * Retrieve the sign-up page URL.
 *
 * This function fetches the sign-up page URL from the provided options.
 * If no custom sign-up page URL is set, it defaults to the WordPress registration page.
 *
 * @since 1.0.0
 *
 * @global array $streamit_options An array containing plugin or theme options.
 *
 * @return string The sign-up page URL.
 */
function streamit_signup_page_url()
{
    global $streamit_options;

    // Retrieve the custom sign-up page link if set, otherwise default to the WordPress registration page.
    $signup_url = (isset($streamit_options['streamit_signup_link']) && !empty($streamit_options['streamit_signup_link']))
        ? get_page_link($streamit_options['streamit_signup_link'])
        : wp_registration_url();

    /**
     * Filter the sign-up page URL.
     *
     * Allows customization of the sign-up page URL.
     *
     * @since 1.0.0
     *
     * @param string $signup_url The sign-up page URL.
     */
    return apply_filters('streamit_signup_page_url', $signup_url);
}

/**
 * Retrieve the forgot password page URL.
 *
 * This function fetches the forgot password page URL from the provided options.
 * If no custom forgot password page URL is set, it defaults to the WordPress forgot password page.
 *
 * @since 1.0.0
 *
 * @global array $streamit_options An array containing plugin or theme options.
 *
 * @return string The forgot password page URL.
 */
function streamit_forgot_page_url()
{
    global $streamit_options;

    // Check if a custom forgot password link is set in options.
    $forgot_url = !empty($streamit_options['streamit_forget_password_link'])
        ? get_page_link($streamit_options['streamit_forget_password_link'])
        : wp_lostpassword_url();

    /**
     * Filter the forgot password page URL.
     *
     * Allows customization of the forgot password page URL.
     *
     * @since 1.0.0
     *
     * @param string $forgot_url The forgot password page URL.
     */
    return apply_filters('streamit_forgot_page_url', $forgot_url);
}

/**
 * Retrieve the subscribe page URL for PMP.
 *
 * This function fetches the subscribe page URL from the provided options.
 * If no custom subscribe page URL is set, it can be customized or defaulted.
 *
 * @since 1.0.0
 *
 * @global array $streamit_options An array containing plugin or theme options.
 *
 * @return string The subscribe page URL.
 */
function streamit_subscribe_page_url()
{
    global $streamit_options;
    // Check if a custom subscribe page link is set in options.
    $subscribe_url = isset($streamit_options['streamit_subscribe_page']) && !empty($streamit_options['streamit_subscribe_page'])
        ? get_page_link($streamit_options['streamit_subscribe_page'])
        : (function_exists('pmpro_url') ? pmpro_url('levels') : '');

    /**
     * Filter the subscribe page URL.
     *
     * Allows customization of the subscribe page URL.
     *
     * @since 1.0.0
     *
     * @param string $subscribe_url The subscribe page URL.
     */
    return apply_filters('streamit_subscribe_page_url', $subscribe_url);
}


/**
 * Generate all available Plyr.io player options.
 *
 * @return array Associative array of Plyr options.
 */
function streamit_media_player_controls()
{
    return apply_filters('streamit_media_player_controls', [
        'controls' => [
            'play-large',   // The large play button in the center
            'restart',      // Restart playback
            'rewind',       // Rewind by the seek time
            'play',         // Play/pause playback
            'fast-forward', // Fast forward by the seek time
            'progress',     // The progress bar and scrubber for playback
            'current-time', // The current time of playback
            'duration',     // The full duration of the media
            'mute',         // Toggle mute
            'volume',       // Volume control
            'captions',     // Toggle captions
            'settings',     // Settings menu
            'pip',          // Picture-in-picture
            'airplay',      // Airplay
            'fullscreen',   // Toggle fullscreen
        ],
        'settings'  => ['captions', 'quality', 'speed', 'loop'],
        'i18n'      => [
            'restart'           => 'Restart',
            'rewind'            => 'Rewind {seektime} secs',
            'play'              => 'Play',
            'pause'             => 'Pause',
            'fastForward'       => 'Forward {seektime} secs',
            'seek'              => 'Seek',
            'played'            => 'Played',
            'buffered'          => 'Buffered',
            'currentTime'       => 'Current time',
            'duration'          => 'Duration',
            'volume'            => 'Volume',
            'mute'              => 'Mute',
            'unmute'            => 'Unmute',
            'enableCaptions'    => 'Enable captions',
            'disableCaptions'   => 'Disable captions',
            'enterFullscreen'   => 'Enter fullscreen',
            'exitFullscreen'    => 'Exit fullscreen',
            'frameTitle'        => 'Player',
            'captions'          => 'Captions',
            'settings'          => 'Settings',
            'speed'             => 'Speed',
            'normal'            => 'Normal',
            'quality'           => 'Quality',
            'loop'              => 'Loop',
            'start'             => 'Start',
            'end'               => 'End',
            'all'               => 'All',
            'reset'             => 'Reset',
            'disabled'          => 'Disabled',
            'advertisement'     => 'Ad',
        ],
        'autoplay'  => false,       // Automatically starts playing the video
        'muted'     => false,       // Start the video muted
        'loop'      => [            // Loop the video
            'active'    => false
        ],
        'speed'     => [            // Playback speed options
            'selected'  => 1,       // Default speed
            'options'   => [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2]  // Options to allow users to select from
        ],
        'captions'  => [            // Caption settings
            'active'    => true,
            'language'  => 'en'     // Default caption language
        ],
        'seekTime'  =>  10,
        'tooltips'  => ['controls' => false, 'seek'  => true],
        'fullscreen'    => [        // Fullscreen options
            'enabled'   => true,    // Enable fullscreen support
            'fallback'  => true,    // Allow fallback to full window mode in browsers without fullscreen API support
            'iosNative' => true,    // Enable native fullscreen support specifically for iOS devices
        ],
        'quality'   => ['default' => 480, 'options' => [4320, 2880, 2160, 1440, 1080, 720, 576, 480, 360, 240]],
        'storage'   =>  ['enabled'  =>  false],
        'hideControls'  => true,
        'vimeo'     =>  [
            'byline'    => false,
            'portrait'  => false,
            'title'     => false,
            'controls'  => false,
        ]
    ]);
}

/**
 * Retrieves the HTML for embedding a video attachment based on the media URL.
 *
 * This function supports various video formats and embeds including:
 * - Local MP4, WebM, OGV, and MOV files.
 * - HLS streams (M3U8).
 * - YouTube and Vimeo embed links.
 *
 * @param string $media_url The URL of the video attachment.
 * @return string HTML markup for embedding the video or a message for unsupported formats.
 */
function streamit_get_attach_video_html($media_url)
{
    if (empty($media_url)) {
        return '<p class="no-data-found">' . esc_html__('Invalid video URL.', 'streamit') . '</p>';
    }

    $file_extension = strtolower(pathinfo($media_url, PATHINFO_EXTENSION));
    $video_html = '';

    if (in_array($file_extension, ['mp4', 'webm', 'ogv', 'mov'], true)) {
        // Adjust MIME type for .mov
        $mime_type = ($file_extension === 'mov') ? 'video/quicktime' : 'video/' . $file_extension;

        $video_html = sprintf(
            '<video id="streamit_player" playsinline controls><source src="%s" type="%s" /></video>',
            esc_url($media_url),
            esc_attr($mime_type)
        );
    } elseif ('m3u8' === $file_extension) {
        $video_html = sprintf(
            '<video id="streamit_player" playsinline controls><source src="%s" type="application/x-mpegURL" /></video>',
            esc_url($media_url)
        );
    } elseif (strpos($media_url, 'youtube.com') !== false || strpos($media_url, 'youtu.be') !== false) {
        preg_match('/(?:v=|\/)([a-zA-Z0-9_-]{11})/', $media_url, $matches);
        if (isset($matches[1])) {
            $video_html = sprintf(
                '<div class="plyr__video-wrapper"><iframe src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen allow="autoplay; encrypted-media"></iframe></div>',
                esc_attr($matches[1])
            );
        }
    } elseif (strpos($media_url, 'vimeo.com') !== false) {
        preg_match('/(?:vimeo\.com\/|video\/)([0-9]+)/', $media_url, $matches);
        if (isset($matches[1])) {
            $video_html = sprintf(
                '<div class="plyr__video-wrapper"><iframe src="https://player.vimeo.com/video/%s" frameborder="0" allowfullscreen allowtransparency></iframe></div>',
                esc_attr($matches[1])
            );
        }
    } elseif (preg_match('/drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?id=)([^\/&?]+)/', $media_url, $matches)) {
        $file_id = esc_attr($matches[1]);
        $video_html = '<iframe src="https://drive.google.com/file/d/' . $file_id . '/preview" width="640" height="360" allow="autoplay" allowfullscreen></iframe>';
    } elseif (strpos($media_url, 'mediadelivery.net') !== false) {
        // Handle Mediadelivery.net embed
        $video_html = sprintf(
            '<div class="plyr__video-wrapper"><iframe src="%s" frameborder="0" allowfullscreen allow="autoplay; encrypted-media"></iframe></div>',
            esc_url($media_url)
        );
    } else {
        $video_html = '<p class="no-data-found">' . esc_html__('Unsupported video format or URL.', 'streamit') . '</p>';
    }

    return $video_html;
}


/**
 * Generate trailer video or iframe structure based on URL.
 * Supports YouTube, Vimeo, Dailymotion, Google Drive, Facebook, Twitch,
 * and direct video links (mp4, mkv, webm).
 *
 * @param string $trailer_link Trailer video URL.
 * @return array {
 *   @type string $type         Type of video embed ('video' or 'iframe').
 *   @type string $trailer_link Embed or direct video link.
 * }
 */
function streamit_get_trailer_embed($trailer_link)
{
    if (empty($trailer_link)) {
        return [
            'type' => '',
            'trailer_link' => ''
        ];
    }

    $st_video_url = esc_url($trailer_link);
    $extension    = strtolower(pathinfo($st_video_url, PATHINFO_EXTENSION));

    // For direct video files
    if ($extension && in_array($extension, ['mp4', 'mkv', 'webm'], true)) {
        return [
            'type' => 'video',
            'trailer_link' => $st_video_url
        ];
    }

    $parsed_url = wp_parse_url($st_video_url);

    if (!empty($parsed_url['host'])) {
        $host      = strtolower($parsed_url['host']);
        $video_id  = '';
        $media_url = '';
        $type      = 'iframe';

        // YouTube short links
        if (strpos($host, 'youtu.be') !== false) {
            $video_id  = trim($parsed_url['path'], '/');
            $media_url = 'https://www.youtube.com/embed/' . esc_attr($video_id) . '?autoplay=1&loop=1&playlist=' . esc_attr($video_id);

            // YouTube standard
        } elseif (strpos($host, 'youtube.com') !== false && !empty($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
            $video_id  = $query_params['v'] ?? '';
            $media_url = 'https://www.youtube.com/embed/' . esc_attr($video_id) . '?autoplay=1&loop=1&playlist=' . esc_attr($video_id);

            // Vimeo
        } elseif (strpos($host, 'vimeo.com') !== false) {
            $video_id  = trim($parsed_url['path'], '/');
            $media_url = 'https://player.vimeo.com/video/' . esc_attr($video_id) . '?autoplay=1&&loop=1';

            // Dailymotion
        } elseif (strpos($host, 'dailymotion.com') !== false) {
            $path_segments = explode('/', $parsed_url['path'] ?? '');
            $video_id      = end($path_segments);
            $media_url     = 'https://www.dailymotion.com/embed/video/' . esc_attr($video_id) . '?autoplay=1&loop=1';

            // Google Drive
        } elseif (strpos($host, 'drive.google.com') !== false && preg_match('/(?:file\/d\/|open\?id=|uc\?id=)([^\/&?]+)/', $st_video_url, $matches)) {
            $file_id  = esc_attr($matches[1]);
            $media_url = 'https://drive.google.com/file/d/' . $file_id . '/preview';

            // Facebook Video
        } elseif (strpos($host, 'facebook.com') !== false) {
            $media_url = 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode($st_video_url) . '&autoplay=1';

            // Twitch Video
        } elseif (strpos($host, 'twitch.tv') !== false) {
            if (preg_match('/videos\/(\d+)/', $st_video_url, $matches)) {
                $video_id  = esc_attr($matches[1]);
                $media_url = 'https://player.twitch.tv/?video=' . $video_id . '&autoplay=true';
            } elseif (preg_match('/twitch.tv\/([^\/]+)/', $st_video_url, $matches)) {
                $channel   = esc_attr($matches[1]);
                $media_url = 'https://player.twitch.tv/?channel=' . $channel . '&parent=' . $_SERVER['HTTP_HOST'];
            }
        }

        $media_url = apply_filters('streamit_trailer_embed', $media_url, $st_video_url, $host);

        return [
            'type' => $type,
            'trailer_link' => $media_url
        ];
    }

    return [
        'type' => '',
        'trailer_link' => ''
    ];
}


/**
 * Renders the video player based on the attachment media URL from post metadata.
 *
 * Retrieves the attachment URL from post meta, determines the type of video, and
 * generates the appropriate HTML markup to display the video player.
 *
 * @param string $post_type   The post type (e.g., 'movie', 'video').
 * @param object $content_data The content data object, used to retrieve post meta.
 * @return string HTML markup for rendering the video player or an empty string if no media is found.
 */
function streamit_render_attach_video_player($post_type, $content_data)
{
    // Retrieve the media URL ID from post meta (casting to integer for safety)
    $media_url_id = (int) $content_data->get_meta('_' . $post_type . '_attachment_id');
    $media_url    = wp_get_attachment_url($media_url_id);
    // Check if media URL exists
    if ($media_url) {
        // Generate video HTML based on media URL
        $video_html = streamit_get_attach_video_html($media_url);

        // Return the video player content with Plyr integration
        return sprintf('<div class="plyr__video-wrapper">%s</div>', $video_html);
    }

    return ''; // Return empty string if no media URL found
}

/**
 * Generate video player HTML with preference for direct playback, fallback to iframe.
 *
 * @param string $media_url The video URL.
 * @return string The HTML output for the video player.
 */
function streamit_get_url_video_html($media_url)
{
    if (empty($media_url) || !filter_var($media_url, FILTER_VALIDATE_URL)) {
        return '<p class="no-data-found">' . esc_html__('Invalid video URL.', 'streamit') . '</p>';
    }

    $file_path     = parse_url($media_url, PHP_URL_PATH);
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $video_html    = '';

    // Direct-playable formats with MIME types
    $video_types = [
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'ogv'  => 'video/ogg',
        'mov'  => 'video/quicktime',
        'mkv'  => 'video/x-matroska',
        'avi'  => 'video/x-msvideo',
        'flv'  => 'video/x-flv',
        'wmv'  => 'video/x-ms-wmv',
        'm3u8' => 'application/x-mpegURL',
        'mpd'  => 'application/dash+xml',
    ];

    // Direct playback using <video> tag
    if (array_key_exists($file_extension, $video_types)) {
        $video_html = '<video class="plyr__video-embed" id="streamit_player" playsinline >
            <source src="' . esc_url($media_url) . '" type="' . esc_attr($video_types[$file_extension]) . '" />
        </video>';
    }

    // Fallback to iframe-based embeds
    if (empty($video_html)) {
        // YouTube
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $media_url, $matches)) {
            $video_html = '<div class="plyr__video-embed" id="streamit_player">
                <iframe src="https://www.youtube.com/embed/' . esc_attr($matches[1]) . '?origin=' . esc_url(home_url()) . '&iv_load_policy=3&modestbranding=1&rel=0&showinfo=0" allowfullscreen allow="autoplay"></iframe>
            </div>';
        }
        // Vimeo
        elseif (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $media_url, $matches)) {
            $video_html = '<div class="plyr__video-embed" id="streamit_player">
                <iframe 
                    src="https://player.vimeo.com/video/' . esc_attr($matches[1]) . '?background=0&byline=0&title=0&portrait=0&controls=0" 
                    frameborder="0"
                    allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen
                ></iframe>
            </div>';
        }
        // Dailymotion
        elseif (preg_match('/dailymotion\.com\/video\/([a-zA-Z0-9_]+)/', $media_url, $matches)) {
            $video_html = '
                <iframe src="https://www.dailymotion.com/embed/video/' . esc_attr($matches[1]) . '" allowfullscreen allow="autoplay"></iframe>
            ';
        }
        // Wistia
        elseif (preg_match('/wistia\.com\/(?:medias|embed)\/([a-zA-Z0-9_-]+)/', $media_url, $matches)) {
            $video_html = '
                <iframe src="https://fast.wistia.net/embed/iframe/' . esc_attr($matches[1]) . '" allowfullscreen allow="autoplay"></iframe>
            ';
        }
        // Google Drive
        elseif (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)\//', $media_url, $matches)) {
            $file_id = $matches[1];
            $video_html = '
                <iframe src="https://drive.google.com/file/d/' . esc_attr($file_id) . '/preview" allow="autoplay; fullscreen" allowfullscreen></iframe>
            ';
        } elseif (strpos($media_url, 'mediadelivery.net') !== false) {
            // Handle Mediadelivery.net embed
            $video_html = sprintf(
                '<div class="plyr__video-wrapper"><iframe src="%s" frameborder="0" allowfullscreen allow="autoplay; encrypted-media"></iframe></div>',
                esc_url($media_url)
            );
        }
        // Unsupported
        else {
            $video_html = '<p class="no-data-found">' . esc_html__('Unsupported video format or URL.', 'streamit') . '</p>';
        }
    }

    return $video_html;
}


/**
 * Render video player for the given post type.
 *
 * This function retrieves the video URL from post meta and renders the appropriate player.
 *
 * @param string $post_type The post type (e.g., movie, video).
 * @param object $content_data The post data object.
 * @return string The HTML output for the video player.
 */
function streamit_render_url_video_player($post_type, $content_data)
{
    // Get media URL from post meta
    $media_url = $content_data->get_meta('_' . $post_type . '_url_link');

    if (!empty($media_url)) {
        return  streamit_get_url_video_html(esc_url($media_url));
    }

    return '<p class="no-data-found">' . esc_html__('No video available.', 'streamit') . '</p>';
}

/**
 * Render an iframe video player with custom options.
 *
 * This function retrieves the iframe code and renders the video player after allowing all necessary HTML.
 *
 * @param string $post_type The post type (e.g., movie, video).
 * @param object $content_data The post data object.
 * @return string The HTML output for the video player.
 */

function streamit_render_video_iframe($post_type, $content_data)
{
    // Get embed code from meta
    $iframe_code = $content_data->get_meta('_' . $post_type . '_embed_content');

    if (empty($iframe_code)) {
        return '<p class="no-data-found">' . esc_html__('No video available.', 'streamit') . '</p>';
    }

    // Check if Voomly embed is present (look for .voomly-embed or voomly script URL)
    $is_voomly_embed = strpos($iframe_code, 'voomly-embed') !== false || strpos($iframe_code, 'embed.voomly.com') !== false;

    // If it's a Voomly embed, enqueue script (once)
    if ($is_voomly_embed && !wp_script_is('voomly-embed', 'enqueued')) {
        wp_enqueue_script('voomly-embed', 'https://embed.voomly.com/embed/embed-build.js', array(), null, true);
    }

    // Remove <script> tags from input for security
    $iframe_code = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $iframe_code);

    // Define allowed HTML
    $allowed_html = array(
        'iframe' => array(
            'src'             => array(),
            'width'           => array(),
            'height'          => array(),
            'frameborder'     => array(),
            'allow'           => array(),
            'allowfullscreen' => array(),
            'title'           => array(),
        ),
        'object' => array(
            'width'           => array(),
            'height'          => array(),
            'data'            => array(),
            'type'            => array(),
        ),
        'embed' => array(
            'src'             => array(),
            'type'            => array(),
            'width'           => array(),
            'height'          => array(),
            'allowfullscreen' => array(),
        ),
        'param' => array(
            'name'            => array(),
            'value'           => array(),
        ),
        'div' => array(
            'class'           => array(),
            'data-id'         => array(),
            'data-ratio'      => array(),
            'data-type'       => array(),
            'data-skin-color' => array(),
            'data-shadow'     => array(),
            'style'           => array(),
        ),
    );

    // Sanitize and return
    $iframe_code = wp_kses($iframe_code, $allowed_html);
    return $iframe_code;
}


/**
 * Displays the restricted content message with subscribe and login links.
 *
 * This function generates the HTML structure for a restricted content message,
 * allowing users to subscribe or log in to access the content. The output can be
 * customized via filters to ensure flexibility.
 *
 * @since 1.0.0
 *
 * @return void
 */
/**
 * Display restricted content message with subscription or login links.
 *
 * @param object $post The post object.
 * @param string $post_type The post type (optional).
 * @return string HTML message for restricted content.
 */
function streamit_display_restricted_content($post, $post_type = '')
{
    $pricing_page = streamit_subscribe_page_url();
    $login_page = streamit_login_page_url();
    $post_id = $post->get_id();

    $access_type = $post->get_meta('_access_type');
    $pmp_levels = $post->get_meta('_pmp_level');

    if (!$access_type && !empty($pmp_levels)) {
        $access_type = 'plan';
    }

    // Respect PMPro activation for showing Subscribe/PPV actions
    $is_membership_enable = is_plugin_active('paid-memberships-pro/paid-memberships-pro.php');

    $action_link = '';
    $or_separator = '';
    if ($is_membership_enable && is_user_logged_in()) {
        // Handle access type logic
        if (!empty($access_type)) {
            if ($access_type === 'anyone') {
                $links = [];
                // Add subscribe link if plans are set
                if (!empty($pmp_levels)) {
                    $links[] = sprintf(
                        '<a class="btn btn-link" href="%s"><span>%s</span></a>',
                        esc_url($pricing_page),
                        esc_html__('Subscribe', 'streamit')
                    );
                }
                // Add PPV link
                $ppv_purchase_page = function_exists('streamit_get_ppv_checkout_url') ? streamit_get_ppv_checkout_url($post_id, $post_type) : '#';
                $links[] = sprintf(
                    '<a class="btn btn-link" href="%s"><span>%s</span></a>',
                    esc_url($ppv_purchase_page),
                    esc_html__('Pay Per View', 'streamit')
                );
                // Join links with 'or'
                $action_link = implode(sprintf('<span> %s </span>', esc_html__('or', 'streamit')), array_filter($links));
            } elseif ($access_type === 'plan' && !empty($pmp_levels)) {
                $action_link = sprintf(
                    '<a class="btn btn-link" href="%s"><span>%s</span></a>',
                    esc_url($pricing_page),
                    esc_html__('Subscribe', 'streamit')
                );
            } elseif ($access_type === 'ppv') {
                $ppv_purchase_page = function_exists('streamit_get_ppv_checkout_url') ? streamit_get_ppv_checkout_url($post_id, $post_type) : '#';
                $action_link = sprintf(
                    '<a class="btn btn-link" href="%s"><span>%s</span></a>',
                    esc_url($ppv_purchase_page),
                    esc_html__('Pay Per View', 'streamit')
                );
            }
        }
    }
    // Add 'or' separator if action link exists and user is not logged in
    if (!is_user_logged_in() && !empty($action_link)) {
        $or_separator = sprintf('<span> %s </span>', esc_html__('or', 'streamit'));
    }

    // Add login link if user is not logged in
    $login_link = '';
    if (!is_user_logged_in()) {
        $login_link = sprintf(
            '<a class="btn btn-link" href="%s"><span>%s</span></a>',
            esc_url($login_page),
            esc_html__('login', 'streamit')
        );
    }

    // Build the final message
    $content = sprintf(
        '<h4>%s</h4><p>%s%s%s %s</p>',
        esc_html__('This Content is restricted', 'streamit'),
        $action_link,
        $or_separator,
        $login_link,
        esc_html__('to read the rest of this content.', 'streamit')
    );

    return apply_filters('streamit_restricted_content_output', $content);
}

/**
 * Retrieve icon markup based on a text identifier.
 *
 * @param string $icon_key The text identifier for the icon (e.g., 'search_normal', 'cross').
 * @param array  $atts     Optional. Additional HTML attributes to add to the icon element.
 * @return string The HTML markup for the icon. Returns an empty string if the key is not found.
 */
function st_get_icon($icon_key, $atts = [])
{
    if (empty($icon_key)) {
        return '';
    }

    // Static cache so array is created only once per request.
    static $icon_classes = null;

    if ($icon_classes === null) {
        $icon_classes = [
            'search_normal' => 'icon-search-normal',
            'cross'         => 'icon-cross',
            'arrow-prev'    => 'icon-arrow-prev',
            'arrow-next'    => 'icon-arrow-next',
            'arrow-left'    => 'icon-arrow-left',
            'arrow-right'   => 'icon-arrow-right',
            'heart'         => 'icon-heart',
            'heart-fill'    => 'icon-heart-fill',
            'play'          => 'icon-play-button',
            'premium'       => 'icon-premium-1',
            'plus'          => 'icon-plus',
            'check-2'       => 'icon-check-2',
            'list-dashes'   => 'icon-list-dashes',
            'grid-2x2'      => 'icon-grid-2x2',
            'grid-3x3'      => 'icon-grid-3x3',
            'grid-4x4'      => 'icon-grid-4x4',
            'trash'         => 'icon-trash-icon',
            'minus-2'       => 'icon-minus-2',
            'clock'         => 'icon-clock',
            'star-fill'     => 'icon-star-fill-icon',
            'star-half'     => 'icon-star-half-icon',
            'star-icon'     => 'icon-star-icon',
            'setting'       => 'icon-setting',
            'user'          => 'icon-user',
            'calendar-2'    => 'icon-calendar-2',
            'translate'     => 'icon-translate',
            'eye-2'         => 'icon-eye-2',
            'share-2'       => 'icon-share-2',
            'playlist'      => 'icon-playlist',
            'download-2'    => 'icon-download-2',
            'facebook'      => 'icon-facebook-icon',
            'twitter'       => 'icon-twitter-icon',
            'instagram'     => 'icon-instagram-icon',
            'whatsapp'      => 'icon-whatsapp-icon',
            'linkedin'      => 'icon-linkedin-share',
            'copy-link'     => 'icon-copy-link',
            'movie'         => 'icon-movie',
            'video'         => 'icon-video',
            'tvshow'        => 'icon-tvshow',
            'subscription'  => 'icon-subscription',
            'bell-1'        => 'icon-bell-1',
            'logout'        => 'icon-logout',
            'bag-cart'      => 'icon-bag-cart',
            'bar-line'      => 'icon-bar-line',
            'three-dots-vertical' => 'icon-three-dots-vertical',
            'edit'          => 'icon-edit-icon',
            'eye-slash'     => 'icon-eye-slash',
            'error'         => 'icon-error',
            'rent'          => 'icon-rent',
            'rented'        => 'icon-rented'
        ];

        // Allow developers to override/extend icons only once per request.
        $icon_classes = apply_filters('st_get_icon_class', $icon_classes, $icon_key);
    }

    if (!isset($icon_classes[$icon_key])) {
        return '';
    }

    $classes = $icon_classes[$icon_key];

    // Merge user-supplied classes
    if (isset($atts['class'])) {
        $classes .= ' ' . $atts['class'];
        unset($atts['class']);
    }

    // Build attributes efficiently
    $attributes = ['class="' . esc_attr($classes) . '"'];
    foreach ($atts as $attr => $value) {
        $attributes[] = sprintf('%s="%s"', esc_attr($attr), esc_attr($value));
    }

    $icon_markup = sprintf('<i %s></i>', implode(' ', $attributes));

    return apply_filters('st_get_icon', $icon_markup, $icon_key);
}

/**
 * Render optimized image with minimal queries and enhanced performance attributes.
 * * @param array $args {
 * @type int    $attachment_id   Attachment ID (required)
 * @type string $class           CSS class(es) (default: 'img-fluid w-100')
 * @type string $alt             Alt text (default: empty)
 * @type string $size            Image size (default: 'full')
 * @type string $loading         Loading strategy: 'lazy', 'eager', or 'none' (default: 'lazy')
 * @type string $fetchpriority   Fetch priority hint: 'high' for LCP, 'low', or 'auto' (default: 'auto')
 * @type array  $attributes      Additional HTML attributes (default: ['decoding' => 'async'])
 * @type bool   $show_fallback   Whether to show placeholder if invalid (default: true)
 * }
 * @return string HTML output
 */
function streamit_render_image($args = [])
{
    // Parse arguments with new defaults
    $args = wp_parse_args($args, [
        'attachment_id'   => 0,
        'class'           => 'img-fluid vertical-banner-bg-image w-100',
        'alt'             => '',
        'size'            => 'full',
        'loading'         => 'lazy', // New default: lazy for most images
        'fetchpriority'   => 'auto', // New default
        'attributes'      => ['decoding' => 'async'],
        'show_fallback'   => true,
    ]);

    /** @var array $args Filtered by streamit-child for contextual image sizes. */
    $args = apply_filters('streamit_render_image_args', $args);

    $attachment_id = absint($args['attachment_id']);

    // --- Performance Logic ---
    $attributes = array_merge(
        [
            'class'           => $args['class'],
            'alt'             => esc_attr($args['alt']),
            'loading'         => $args['loading'],
            'fetchpriority'   => $args['fetchpriority'],
        ],
        $args['attributes']
    );

    // Remove loading="lazy" if specified as 'eager' or 'none'
    // This is crucial for the LCP image.
    if (in_array($args['loading'], ['eager', 'none'])) {
        unset($attributes['loading']);
    }

    // Ensure width and height are included if they are not already.
    // wp_get_attachment_image often includes them, but this adds a fallback check.
    if (empty($attributes['width']) || empty($attributes['height'])) {
        $meta = wp_get_attachment_metadata($attachment_id);
        if (!empty($meta['sizes'][$args['size']])) {
            $attributes['width']  = $meta['sizes'][$args['size']]['width'];
            $attributes['height'] = $meta['sizes'][$args['size']]['height'];
        } elseif (!empty($meta['width']) && $args['size'] === 'full') {
            $attributes['width']  = $meta['width'];
            $attributes['height'] = $meta['height'];
        }
    }
    // --- End Performance Logic ---

    // Try to get the image
    $image = '';
    if ($attachment_id > 0) {
        $image = wp_get_attachment_image(
            $attachment_id,
            $args['size'],
            false,
            $attributes
        );
    }

    // Fallback logic remains the same
    if (!empty($image) || !$args['show_fallback']) {
        return $image;
    }

    $attr_string = '';
    foreach ($attributes as $name => $value) {
        // Strip out 'loading' and 'fetchpriority' from fallback if not standard img attr
        if (!in_array($name, ['loading', 'fetchpriority']) || $attachment_id > 0) {
            $attr_string .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
        }
    }

    return sprintf(
        '<img src="%s"%s>',
        esc_url(streamit_placeholder_image()),
        $attr_string
    );
}

/**
 * Get access badge data (icons and titles) based on post access type.
 *
 * Returns which access badges (Premium or Pay Per View) should be shown
 * for the given post, based on its _access_type or _pmp_levels meta.
 *
 * @param object $post Post object with get_meta().
 * @return array Badge data with icon flags and titles.
 */
function streamit_get_access_badge_for_user($post)
{
    // Respect plugin activation: hide badges when PMPro is deactivated
    $is_membership_enable = is_plugin_active('paid-memberships-pro/paid-memberships-pro.php');
    if (!$is_membership_enable) {
        return [];
    }

    // Determine access type just once
    $access_type = $post->get_meta('_access_type');
    $pmp_levels  = $post->get_meta('_pmp_level') ?? [];

    if (!$access_type && !empty($pmp_levels)) {
        $access_type = 'plan';
    }
    $user_id = get_current_user_id();
    $is_admin = $user_id && user_can($user_id, 'administrator');

    // If the user already has access
    if (function_exists('streamit_user_has_stream_access') && streamit_user_has_stream_access($post->get_id(), $post->get_post_type()) && !empty($user_id)) {
        $has_ppv = function_exists('streamit_user_has_ppv_access') ? streamit_user_has_ppv_access($user_id, $post->get_id()) : false;

        // If it's rented (PPV), show "Rented" badge
        if ($access_type === 'ppv' || $access_type === 'anyone' && $has_ppv) {
            return [
                'is_premium_icon' => false,
                'is_rent_icon'    => false,
                'is_rented_icon'  => true,
                'rent_title'      => esc_attr__('Rented', 'streamit'),
            ];
        }

        // For admins: show badges even if they have access 
        if ($is_admin) {
            return match ($access_type) {
                'plan'   => [
                    'is_premium_icon' => true,
                    'is_rent_icon'    => false,
                    'is_rented_icon'  => false,
                    'premium_title'   => esc_attr__('Premium (Admin Access)', 'streamit'),
                ],
                'ppv'    => [
                    'is_premium_icon' => false,
                    'is_rent_icon'    => true,
                    'is_rented_icon'  => false,
                    'rent_title'      => esc_attr__('Pay Per View (Admin Access)', 'streamit'),
                ],
                'anyone' => [
                    'is_premium_icon' => true,
                    'is_rent_icon'    => true,
                    'is_rented_icon'  => false,
                    'premium_title'   => esc_attr__('Premium (Admin Access)', 'streamit'),
                    'rent_title'      => esc_attr__('Pay Per View (Admin Access)', 'streamit'),
                ],
                default  => [
                    'is_premium_icon' => false,
                    'is_rent_icon'    => false,
                    'is_rented_icon'  => false,
                ],
            };
        }

        return [];
    }

    // Show badge icons based on access type
    return match ($access_type) {
        'plan'   => [
            'is_premium_icon' => true,
            'is_rent_icon'    => false,
            'is_rented_icon'  => false,
            'premium_title'   => esc_attr__('Premium', 'streamit'),
        ],
        'ppv'    => [
            'is_premium_icon' => false,
            'is_rent_icon'    => true,
            'is_rented_icon'  => false,
            'rent_title'      => esc_attr__('Pay Per View', 'streamit'),
        ],
        'anyone' => [
            'is_premium_icon' => true,
            'is_rent_icon'    => true,
            'is_rented_icon'  => false,
            'premium_title'   => esc_attr__('Premium', 'streamit'),
            'rent_title'      => esc_attr__('Pay Per View', 'streamit'),
        ],
        default  => [
            'is_premium_icon' => false,
            'is_rent_icon'    => false,
            'is_rented_icon'  => false,
        ],
    };
}

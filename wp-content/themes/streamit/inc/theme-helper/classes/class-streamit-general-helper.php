<?php

/**
 * Class streamit_theme_general_Helper
 *
 * Handles authentication form customizations and shortcode tasks for the theme or plugin.
 *
 * @package streamit
 */
if (!class_exists('streamit_theme_general_Helper')) {

    /**
     * streamit_theme_general_Helper class.
     *
     * This class manages hooks, filters, and shortcode handling for login forms and related functionalities.
     */
    class streamit_theme_general_Helper
    {
        /**
         * List of filters to apply the terms_per_page method.
         * 
         * @var array
         */
        private $terms_filters = [
            'streamit_movie_genres_arguments',
            'streamit_video_categorys_arguments',
            'streamit_tvshow_genres_arguments',
            'streamit_person_categorys_arguments',
            'streamit_movie_tags_arguments',
            'streamit_video_tags_arguments',
            'streamit_tvshow_tags_arguments',
            'streamit_person_tags_arguments',
        ];

        /**
         * Constructor to initialize filters.
         */
        public function __construct()
        {
            foreach ($this->terms_filters as $filter) {
                add_filter($filter, [$this, 'terms_per_page'], 10, 1);
            }

            // Handle login redirection.
            add_filter('login_redirect', [$this, 'st_redirect_after_login'], 10, 3);

            // Apply frontend page restriction logic for non-logged-in users.
            add_action('template_redirect', [$this, 'st_restrict_pages_for_guests'], 10);

            // wp logout tim remove session entry from database
            add_action('wp_logout', [$this, 'streamit_auto_remove_device_on_logout']);


            add_filter(
                'heartbeat_received',
                [$this, 'st_logoutcheck_heartbeat'],
                10,
                2
            );
            add_filter(
                'heartbeat_nopriv_received',
                [$this, 'st_logoutcheck_heartbeat'],
                10,
                2
            );
        }

        /**
         * Modify the number of terms per page based on theme options.
         *
         * @param array $args Filter arguments.
         * @return array Modified arguments.
         */
        public function terms_per_page($args)
        {
            global $streamit_options;

            $default_per_page = 10; // Default value if the option is not set or empty.
            $option_key = 'streamit_genere_tag_category_post_per_page';

            // Update the 'per_page' argument with the option value or default.
            $args['per_page'] = isset($streamit_options[$option_key]) && !empty($streamit_options[$option_key])
                ? esc_html($streamit_options[$option_key])
                : $default_per_page;

            return $args;
        }

        /**
         * Redirect user to a custom page after login if configured.
         *
         * @param string           $redirect_to Default redirect destination URL.
         * @param string           $request     Requested redirect destination passed as a parameter.
         * @param WP_User|WP_Error $user        Logged in user object or WP_Error on failure.
         * @return string Modified redirect URL.
         */
        public function st_redirect_after_login($redirect_to, $request, $user)
        {
            // Only handle valid login.
            if (is_wp_error($user) || ! $user instanceof WP_User) {
                return esc_url_raw($redirect_to);
            }

            // Allow filtering the logic early by other plugins or developers.
            $redirect_url = apply_filters('st_redirect_after_login', '', $user);

            if (! empty($redirect_url) && filter_var($redirect_url, FILTER_VALIDATE_URL)) {
                return esc_url_raw($redirect_url);
            }

            global $streamit_options;

            $redirect_url = home_url(); // Default fallback.

            if (isset($streamit_options['streamit_after_login_page'])) {
                $page_id = $streamit_options['streamit_after_login_page'];
            } else {
                $page_id = false;
            }
            if (is_numeric($page_id)) {
                $page_url = get_permalink($page_id);
                if ($page_url) {
                    $redirect_url = $page_url;
                }
            } elseif (! empty($request)) {
                $redirect_url = $request;
            } elseif (! empty($redirect_to)) {
                $redirect_url = $redirect_to;
            }
            // Final filter before returning
            return esc_url_raw($redirect_url);
        }
        /**
         * Redirect non-logged-in users if they access restricted pages.
         *
         * This applies only to frontend pages and can be configured via theme options.
         */
        public function st_restrict_pages_for_guests()
        {

            // Skip restriction for logged-in users or admin area.
            if (is_user_logged_in() || is_admin()) {
                return;
            }

            $options = $GLOBALS['streamit_options'] ?? [];

            // Check if page restriction is enabled.
            $restriction_enabled = $options['streamit_enable_page_restriction'] ?? 'yes';
            if ('no' === $restriction_enabled) {
                return;
            }

            /**
             * Allow developers to disable guest page restriction.
             *
             * @param bool $enabled Whether restriction is active.
             */
            if (! apply_filters('ls_enable_guest_page_restriction', true)) {
                return;
            }

            // Collect allowed URLs (login, signup, forgot password, terms).
            $allowed_urls = array_filter([
                get_permalink($options['streamit_signin_link'] ?? 0),
                get_permalink($options['streamit_signup_link'] ?? 0),
                get_permalink($options['streamit_forget_password_link'] ?? 0),
                get_permalink($options['streamit_term_condition'] ?? 0)
            ]);

            // Include excluded page IDs in allowed URLs.
            $excluded_ids = $options['streamit_excluded_pages'] ?? [];

            if (is_array($excluded_ids)) {
                foreach ($excluded_ids as $page_id) {
                    $url = get_permalink($page_id);
                    if ($url) {
                        $allowed_urls[] = $url;
                    }
                }
            }

            if (!empty($options['streamit_redirect_page_for_non_logged_in'])) {
                $allowed_urls[] = get_permalink($options['streamit_redirect_page_for_non_logged_in']);
            }

            // Normalize allowed URLs
            $allowed_urls = array_map(
                static function ($url) {
                    return trailingslashit(esc_url_raw($url));
                },
                $allowed_urls
            );

            /**
             * Allow developers to override the final list of allowed URLs.
             *
             * @param array $allowed_urls List of allowed absolute URLs.
             */
            $allowed_urls = apply_filters('st_restricted_allowed_urls', $allowed_urls);

            // Get the current full URL
            global $wp;
            $current_url = trailingslashit(
                esc_url_raw(
                    home_url(add_query_arg([], $wp->request))
                )
            );

            // Check if current URL is not allowed
            if (! in_array($current_url, $allowed_urls, true)) {

                // Fallback redirect (typically login page)
                $redirect_page_id = !empty($options['streamit_redirect_page_for_non_logged_in']) ? $options['streamit_redirect_page_for_non_logged_in'] : $options['streamit_signin_link'];

                $redirect_url     = !empty($redirect_page_id) ? get_permalink($redirect_page_id) : home_url();

                /**
                 * Allow developers to override the redirect URL.
                 *
                 * @param string $redirect_url The URL to redirect to.
                 * @param string $current_url  The current page URL.
                 */
                $redirect_url = apply_filters('st_guest_redirect_url', $redirect_url, $current_url);

                // Redirect only if it's a different URL to prevent redirect loop
                if ($redirect_url && trailingslashit($redirect_url) !== $current_url) {
                    wp_safe_redirect(esc_url_raw($redirect_url));
                    exit;
                }
            }
        }

        /**
         * Remove the current session to not dispaly that list in device limit.
         *
         */
        public function streamit_auto_remove_device_on_logout() {
            // Only run if user is logged in
            $user_id = get_current_user_id();
            if ( ! $user_id ) {
                return;
            }
        
            // Get current session token
            $current_token = streamit_get_current_session_token();
            $current_token_hash = $current_token ? hash('sha256', $current_token) : '';
        
            if ( $current_token_hash ) {
                // Call your existing function to remove this session
                streamit_remove_device($user_id, $current_token_hash);
            }
        }

        /**
         * Handle WordPress Heartbeat for device management.
         *
         * @param array $response Existing heartbeat response.
         * @param array $data     Data sent from JS.
         * @return array Modified response.
         */
        public function st_logoutcheck_heartbeat($response, $data)
        {
            // Only process if our custom key is present
            if (empty($data['st_heartbeat'])) {
                return $response;
            }
            // Check if user is logged in
            if (!is_user_logged_in()) {
                $response['st_heartbeat_response'] = [
                    'status'                => true,
                    'logged_in'             => false,
                    'has_access'            => true,
                    'device_limit_exceeded' => false,
                    'devices'               => [],
                    'stats'                 => [],
                    'session_key'           => null,
                    'is_paid_content'       => false,
                    'message'               => esc_html__('User not logged in, but access allowed.', 'streamit'),
                ];
                return $response;
            }

            $user_id = get_current_user_id();

            // Get current session key (if available)
            $current_session_key = function_exists('streamit_get_current_session_token')
                ? streamit_get_current_session_token()
                : null;

            // Determine if current content is paid
            $post_id = get_the_ID();
            $post_type = get_post_type($post_id);
            $is_paid_content = false;

            if ($post_id && in_array($post_type, ['movie', 'video', 'tv_show'], true)) {
                if (function_exists('streamit_user_has_stream_access')) {
                    $has_access_without_login = streamit_user_has_stream_access($post_id, $post_type, 0);
                    $is_paid_content = !$has_access_without_login;
                }
            }

            // Device management
            if (!function_exists('streamit_get_user_devices_with_stats')) {
                $response['st_heartbeat_response'] = [
                    'status'                => true,
                    'logged_in'             => true,
                    'has_access'            => true,
                    'device_limit_exceeded' => false,
                    'devices'               => [],
                    'stats'                 => [],
                    'message'               => esc_html__('Device management function not available, but access allowed.', 'streamit'),
                ];
                return $response;
            }

            // Get user device data
            $result  = streamit_get_user_devices_with_stats($user_id);
            $devices = $result['data'] ?? [];
            $stats   = $result['stats'] ?? [];

            // Check limits
            $has_access = true;
            $device_limit_exceeded = false;

            if (!empty($stats)) {
                $total_devices = $stats['total_devices'] ?? 0;
                $total_limit   = $stats['total_limit'] ?? 0;

                if (is_numeric($total_limit) && $total_limit > 0 && $total_devices > $total_limit) {
                    $has_access = false;
                    $device_limit_exceeded = true;
                }
            }

            // Final response
            $response['st_heartbeat_response'] = [
                'status'                => true,
                'logged_in'             => true,
                'has_access'            => $has_access,
                'device_limit_exceeded' => $device_limit_exceeded,
                'devices'               => $devices,
                'stats'                 => $stats,
                'session_key'           => $current_session_key,
                'is_paid_content'       => $is_paid_content,
            ];

            return $response;
        }
    }

    new streamit_theme_general_Helper();
}

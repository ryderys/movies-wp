<?php

/**
 * Class streamit_shortcode_Helper
 *
 * Handles all Shortcode task for the theme or plugin.
 *
 * @package streamit
 */

if (! class_exists('streamit_shortcode_Helper')) {

    /**
     * streamit_shortcode_Helper class.
     *
     * This class manages all the hooks and filters related to movies.
     */
    class streamit_shortcode_Helper
    {
        public function __construct()
        {
            add_shortcode('streamit_watchlist_shortcode',     [$this, 'streamit_watchlist_shortcode_fun']);
            add_shortcode('streamit_play_button',             [$this, 'streamit_play_button_fun']);
            add_shortcode('streamit_like_shortcode',          [$this, 'streamit_like_shortcode_fun']);
            add_shortcode('streamit_user_watchlist',          [$this, 'streamit_user_watchlist'], 10);
            add_shortcode('streamit_playlist_modal',          [$this, 'streamit_playlist_modal'], 10);
            add_shortcode('streamit_login_form',              [$this, 'streamit_login_template'], 10);
            add_shortcode('streamit_registration_form',       [$this, 'streamit_registration_template'], 10);
            add_shortcode('streamit_forgot_password_form',    [$this, 'streamit_forgot_password_template'], 10);
            add_shortcode('streamit_email_subscription_form', [$this, 'streamit_email_subscription_form_shortcode'], 10);
            add_shortcode('streamit_notify_me_shortcode',     [$this, 'streamit_notify_me_shortcode_fun'], 10);
        }

        public function streamit_like_shortcode_fun($atts)
        {

            // Sanitize and escape attributes
            $atts = shortcode_atts(
                array(
                    'post_id'   => 0,
                    'post_type' => '',
                    'class'     => '',
                ),
                $atts,
                'streamit_like_shortcode' // Corrected the shortcode name
            );

            // Ensure proper sanitization
            $post_id = isset($atts['post_id']) ? absint($atts['post_id']) : 0;
            $post_type = sanitize_text_field($atts['post_type']);

            // Check if this is an upcoming movie - if so, don't show like button
            if (!empty($post_id) && !empty($post_type)) {
                $func = 'streamit_get_' . $post_type;
                if (function_exists($func)) {
                    $post = $func($post_id);
                    if ($post && method_exists($post, 'get_meta')) {
                        $upcoming_data = function_exists('streamit_is_upcoming') ? streamit_is_upcoming($post) : [
                            'is_upcoming' => false,
                            'is_future_release' => false,
                            'formatted_date' => ''
                        ];
                        if ($upcoming_data['is_future_release']) {
                            // Don't display like button for upcoming movies
                            return '';
                        }
                    }
                }
            }

            // Check if the post is liked using the custom function
            $is_liked = streamit_is_like($post_id, $post_type, get_current_user_id());

            // Get the like count
            $like_count = 0;
            if (function_exists('streamit_get_like_count')) {
                $like_count = streamit_get_like_count([
                    'post_id' => $post_id,
                    'post_type' => $post_type
                ]);
            }

            // Initialize variables
            $is_liked_class = '';
            $icon_class = 'heart';
            $like_text = esc_html__('Like', 'streamit'); 
            
            // Format the tooltip with like count
            $bs_title = sprintf(
                esc_html__('%d %s', 'streamit'),
                $like_count,
                _n('Like', 'Likes', $like_count, 'streamit')
            );  

            if ($is_liked === true) {
                $is_liked_class = 'is-liked';
                $icon_class = 'heart-fill';
            }

            echo '<button class="action-btn st-like-btn ' . esc_attr($is_liked_class) . ' btn btn-secondary border ' . esc_attr($atts['class']) . '" data-post_type=' . esc_attr($post_type) . ' data-post_id=' . esc_attr($post_id) . ' data-like-count="' . esc_attr($like_count) . '" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' . esc_attr($bs_title) . '">';
            echo st_get_icon($icon_class);
            echo '</button>';
        }


        public function streamit_play_button_fun($atts)
        {
            $atts = shortcode_atts(
                array(
                    'post_id'   => 0,
                    'post_type' => 'tvshow',
                ),
                $atts,
                'streamit_play_button'
            );

            $post_id     = (int) $atts['post_id'];
            $post_type   = $atts['post_type'];
            $func        = 'streamit_get_' . $post_type;
            $post        = $func($post_id);
            $user_id     = get_current_user_id();

            // Get access data
            $access_type  = $post->get_meta('_access_type') ?? '';
            $ppv_price    = $post->get_meta('_ppv_price') ?? 0;
            $discount     = $post->get_meta('_ppv_discount') ?? 0;
            $pmp_levels   = $post->get_meta('_pmp_level') ?? [];

            if (!$access_type && !empty($pmp_levels)) {
                $access_type = 'plan';
            }

            $st_premium_lvl = method_exists($post, 'get_meta') && $post->get_meta('_pmp_level') ? maybe_unserialize($post->get_meta('_pmp_level')) : '';
            $has_access = function_exists('streamit_user_has_stream_access') ? streamit_user_has_stream_access($post_id, $post_type, $user_id) : false;
            $final_price = function_exists('streamit_calculate_final_ppv_price') ? streamit_calculate_final_ppv_price($post_id, $post_type) : $ppv_price;

            $currency_code   = get_option('pmpro_currency', 'USD');
            global $pmpro_currencies;
            $currency_symbol = isset($pmpro_currencies[$currency_code]['symbol']) ? $pmpro_currencies[$currency_code]['symbol'] : '$';
            $original_price   = floatval($ppv_price);
            $discounted_price = floatval($final_price);

            $pricing_page = streamit_subscribe_page_url();
            if (!empty($st_premium_lvl) && is_array($st_premium_lvl)) {
                $pricing_page = add_query_arg(
                    'require_plan',
                    implode(',', array_map('intval', $st_premium_lvl)),
                    $pricing_page
                );
            }

            $purchase_label = sprintf(
                '%s %s <strong>%s%s</strong>',
                esc_html__('Rent For', 'streamit'),
                $discounted_price < $original_price
                    ? '<del class="rent-price">' .
                    esc_html($currency_symbol . number_format($original_price, 2)) .
                    '</del>'
                    : '',
                esc_html($currency_symbol),
                esc_html(number_format($discounted_price, 2))
            );

            $purchase_icon = 'rent';
            $subscribe_icon = 'premium';
            $subscribe_label = esc_html__('Subscribe to Watch', 'streamit');
            $media_player_url = streamit_get_permalink($atts['post_type'], $post->get_post_name() . '/player');

            ob_start();

            if ($has_access || empty($access_type) || $access_type == 'free') {
                echo $this->streamit_generate_button($media_player_url, 'play', esc_html__('Start Watching', 'streamit'));
            } else {
                if ($access_type === 'plan') {
                    echo $this->streamit_generate_button($pricing_page, $subscribe_icon, $subscribe_label);
                } elseif ($access_type === 'ppv') {
                    echo $this->streamit_generate_button('#', $purchase_icon, $purchase_label, 'data-bs-toggle="modal" data-bs-target="#PpvSubscriptionDataModal"', 'btn btn-warning-subtle');
                } elseif ($access_type === 'anyone') {
                    echo $this->streamit_generate_button($pricing_page, $subscribe_icon, $subscribe_label);
                    echo $this->streamit_generate_button(
                        '#',
                        $purchase_icon,
                        $purchase_label,
                        'data-bs-toggle="modal" data-bs-target="#PpvSubscriptionDataModal"',
                        'd-flex align-items-center gap-lg-3 gap-2 flex-md-nowrap flex-wrap btn btn-warning-subtle'
                    );
                }
            }

            return ob_get_clean();
        }

        private function streamit_generate_button($url, $icon, $label, $attributes = '', $custom_class = 'btn btn-primary')
        {
            return sprintf(
                '<a class="%s" href="%s" %s><span class="d-flex align-items-center justify-content-center gap-2"><span>%s</span><span>%s</span></span></a>',
                esc_attr($custom_class),
                esc_url($url),
                $attributes,
                st_get_icon($icon),
                $label
            );
        }


        public function streamit_watchlist_shortcode_fun($atts)
        {
            global $streamit_options;

            // Check if watchlist display is disabled
            if (!empty($streamit_options['streamit_display_watchlist']) && $streamit_options['streamit_display_watchlist'] === 'no') {
                return '';
            }

            $watchlist_title = !empty($streamit_options['streamit_watchlist_title'])
                ? esc_html($streamit_options['streamit_watchlist_title'])
                : esc_html__('Watch List', 'streamit');

            // Shortcode attributes
            $atts = shortcode_atts([
                'post_id'   => 0,
                'post_type' => 'movie',
                'is_button' => 'true',
                'tooltip'   => 'true',
            ], $atts, 'streamit_watchlist_shortcode');

            $post_id   = absint($atts['post_id']);
            $post_type = sanitize_key($atts['post_type']);
            $is_button = filter_var($atts['is_button'], FILTER_VALIDATE_BOOLEAN);
            $tooltip   = filter_var($atts['tooltip'], FILTER_VALIDATE_BOOLEAN);

            // Tooltip attributes
            $tooltip_attr = $tooltip ? ' data-bs-toggle="tooltip" data-bs-placement="top"' : '';

            // Logged-out users: show link to login
            if (!is_user_logged_in()) {
                $login_link = esc_url(streamit_login_page_url());
                $tooltip_text = esc_html__('Add to watchlist', 'streamit');
                $icon = st_get_icon('plus');

                if (!$is_button) {
                    return sprintf(
                        '<a href="%1$s" class="btn btn-secondary border watch-list-btn" >
                    <span class="d-flex align-items-center justify-content-center gap-2">
                        <span>%2$s</span>
                        <span>%3$s</span>
                    </span>
                </a>',
                        $login_link,
                        $icon,
                        $watchlist_title
                    );
                }

                return sprintf(
                    '<a href="%1$s" class="action-btn btn btn-secondary watch-list-btn"  tabindex="0">%2$s</a>',
                    $login_link,
                    $icon
                );
            }

            // Logged-in users: check watchlist status
            $is_watchlist = streamit_is_watchlist(get_current_user_id(), $post_id, $post_type) ? 'remove' : 'add';
            $btn_class    = $is_watchlist === 'add' ? '' : 'in-watchlist';
            $tooltip_text = $is_watchlist === 'add'
                ? esc_html__('Add to watchlist', 'streamit')
                : esc_html__('Remove from watchlist', 'streamit');
            $icon_class   = $is_watchlist === 'add' ? 'plus' : 'check-2';
            $icon         = st_get_icon($icon_class);
            $like_count = 5;
            $bs_title = $is_watchlist === 'Add' ? '' : 'Remove';

            if (!$is_button) {
                return sprintf(
                    '<button class="watch-list-btn btn btn-secondary %1$s border" data-post-id="%2$d" data-post-type="%3$s" data-action="%4$s" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' . esc_attr($bs_title) . '">
                <span class="d-flex align-items-center justify-content-center gap-2">
                    <span>%5$s</span>
                    <span>%6$s</span>
                </span>
            </button>',
                    esc_attr($btn_class),
                    $post_id,
                    esc_attr($post_type),
                    esc_attr($is_watchlist),
                    $icon,
                    $watchlist_title
                );
            }

            return sprintf(
                '<button class="action-btn btn btn-secondary %1$s watch-list-btn" data-post-id="%2$d" data-post-type="%3$s" data-action="%4$s"  tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' . esc_attr($bs_title) . '">%5$s</button>',
                esc_attr($btn_class),
                $post_id,
                esc_attr($post_type),
                esc_attr($is_watchlist),
                $icon
            );
        }


        /**
         * Displays the Streamit registration form.
         *
         * This function checks if the user is logged in. If not, it includes a custom
         * registration template file. If the user is logged in, it shows a message 
         * indicating that the user is already logged in.
         * 
         * @param array $attr Shortcode attributes including terms_required.
         *
         * @return string HTML output for the registration form or login message.
         */
        public function streamit_registration_template($atts): string
        {
            if (!is_user_logged_in()) {

                $atts = shortcode_atts(
                    array(
                        'terms_required' => 'true',
                    ),
                    $atts,
                    'streamit_login_form'
                );
                // Form fields array, similar to iqonic_registration_form_fields
                $form_fields = array(
                    "user_fname" => array(
                        "class"         => "",
                        "required"      => true,
                        "placeholder" => esc_html__('Enter your first name', 'streamit'),
                        "type"          => "text",
                        "parent_class"  => "col-md-6",
                        "text"          => __('First Name', 'streamit'),
                    ),
                    "user_lname" => array(
                        "class"         => "",
                        "required"      => true,
                        "type"          => "text",
                        "placeholder" => esc_html__('Enter your last name', 'streamit'),
                        "parent_class"  => "col-md-6",
                        "text"          => __('Last Name', 'streamit'),
                    ),
                    "user_email" => array(
                        "class"         => "",
                        "required"      => true,
                        "type"          => "email",
                        "placeholder"   => esc_html__('Enter your email', 'streamit'),
                        "text"          => __('Email', 'streamit'),
                        "parent_class"  => "col-md-12",
                    ),
                    "user_username" => array(
                        "class"         => "",
                        "required"      => true,
                        "type"          => "text",
                        "parent_class"  => "col-md-6",
                        "placeholder"   => esc_html__('Enter your username', 'streamit'),
                        "text"          => __('Username *', 'streamit'),
                    ),
                    "user_password"  => array(
                        "class"         => "",
                        "required"      => true,
                        "type"          => "password",
                        "text"          => __('Password', 'streamit'),
                        "placeholder"   => esc_html__('Enter your password', 'streamit'),
                        "parent_class"  => "col-md-6 login-password",
                    ),
                );

                // Apply filters for fields and payment visibility if necessary
                $form_fields = apply_filters("st_add_registration_form_fields", $form_fields);

                ob_start();
                // Use get_template_part to include the registration template
                streamit_get_template('shortcode/registration.php', [
                    'form_fields' => $form_fields,
                    'register_atts' => $atts
                ]);
                return ob_get_clean();  // Return the buffered content
            } else {
                return '<p>' . esc_html__('You are already logged in.', 'streamit') . '</p>';
            }
        }

        /**
         * Displays the Streamit login form.
         * 
         * @param array $attr Shortcode attributes including terms_required.
         *
         * @return string HTML output for the login form or a message for logged-in users.
         */
        public function streamit_login_template($atts): string
        {
            if (!is_user_logged_in()) {
                global $streamit_options;
                $atts = shortcode_atts(
                    array(
                        'terms_required' => 'true'
                    ),
                    $atts,
                    'streamit_login_form'
                );

                // Login form fields
                $form_fields = array(
                    "user_username" => array(
                        "class" => "",
                        "required" => true,
                        "type" => "text",
                        "parent_class" => "col-md-12",
                        "placeholder" => esc_html__('Enter your username or email', 'streamit'),
                        "text" => esc_html__('Username or Email Address', 'streamit'),
                        "value" => isset($streamit_options['streamit_default_accesss']) && ($streamit_options['streamit_default_accesss'] == 'yes') ? esc_html('marvin') : '',
                    ),
                    "user_password" => array(
                        "class" => "",
                        "required" => true,
                        "type" => "password",
                        "parent_class" => "col-md-12",
                        "placeholder" => esc_html__('Enter your password', 'streamit'),
                        "text" => esc_html__('Password', 'streamit'),
                        "value" => isset($streamit_options['streamit_default_accesss']) && ($streamit_options['streamit_default_accesss'] == 'yes') ? esc_html('marvin') : '',
                    ),
                    "remember_me" => array(
                        "class" => "",
                        "required" => false,
                        "type" => "checkbox",
                        "parent_class" => "col-md-12",
                        "placeholder" => "",
                        "text" => esc_html__('Remember Me', 'streamit'),
                        "value" => "1",
                    ),
                );

                ob_start();
                streamit_get_template('template-parts/shortcode/login.php', [
                    'login_form_fields' => $form_fields,
                    'login_atts' => $atts
                ]);
                return ob_get_clean();
            } else {

                $st_redirect_url = streamit_get_permalink('profile');
                if (
                    is_admin() ||
                    wp_doing_ajax() || // Prevent AJAX execution
                    (isset($_GET['elementor-preview'])) || // Prevent Elementor frontend preview execution
                    (isset($_GET['preview-debug'])) || // Prevent Elementor debug preview execution
                    (isset($_GET['action']) && in_array($_GET['action'], ['edit', 'elementor'], true)) ||
                    (defined('REST_REQUEST') && REST_REQUEST)
                ) {
                    return '<p class="data_not_found">' . esc_html__('You are already logged in.', 'streamit') . '</p>';
                } else {
                    wp_redirect($st_redirect_url);
                    exit;
                }
            }
        }


        /**
         * Displays the user watchlist template.
         */
        public function streamit_user_watchlist()
        {
            // Trigger action before displaying the template
            do_action('streamit_before_user_watchlist_template');

            streamit_get_template('shortcode/watchlist.php');

            // Trigger action after displaying the template
            do_action('streamit_after_user_watchlist_template');
        }

        /**
         * Displays the playlist modal for a given post type.
         *
         * @param array $attr Shortcode attributes including post ID and post type.
         */
        public function streamit_playlist_modal($attr)
        {

            // Set default attribute values
            $attr = shortcode_atts(
                array(
                    'post_id'   => 0,
                    'post_type' => '',
                ),
                $attr,
                'streamit_playlist_shortcode'
            );

            // Check if the user is logged in and the post type is not empty
            if (! is_user_logged_in() || empty($attr['post_type']) || ($attr['post_id'] <= 0)) {
                echo sprintf(
                    '<p class="px-4">%s</p>',
                    sprintf(
                        esc_html__('You should %1$slogin%2$s to create a playlist.', 'streamit'),
                        '<a href="' . esc_url(wp_login_url()) . '">',
                        '</a>'
                    )
                );
                return;
            }
            ?>
            <div class="modal-header">
                <h5 class="modal-title m-0"><?php echo esc_html__('Select Playlist', 'streamit'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <?php
            // Default arguments for fetching playlists
            $args = array(
                'paged'    => 1,
                'per_page' => -1,
                'order'    => 'ASC',
                'user_id'  => get_current_user_id(),
            );

            // Build the function name dynamically based on post type
            $function_name = 'streamit_get_' . $attr['post_type'] . '_playlists';

            // Check if the function exists before calling it
            if (function_exists($function_name)) {

                // Call the dynamically constructed function
                $playlists = call_user_func($function_name, $args);

                if (! empty($playlists->results)) {
                    echo '<div class="modal-body py-0">';
                    echo '<div class="playlist-modal-content">';


                    // Check if the post_id is in the $attach_post_ids array
                    foreach ($playlists->results as $playlist) {
                        echo '<div class="form-check">';
                        $attach_post_ids = streamit_get_playlist_item($playlist->get_playlist_id(), $attr['post_type']);

                        // Check if the current post_id exists in the $attach_post_ids array
                        $checked = in_array($attr['post_id'], $attach_post_ids) ? 'checked' : '';

                        // Generate the checkbox with the appropriate checked state
                        echo '<input id="' . $playlist->get_playlist_id() . '" class="st_manage_playlist form-check-input" type="checkbox" data-playlist_id="' . $playlist->get_playlist_id() . '" data-post_id="' . $attr['post_id'] . '" data-post_type="' . $attr['post_type'] . '" ' . $checked . '>';
                        echo '<label for="' . $playlist->get_playlist_id() . '">' . ucwords( strtolower( $playlist->get_playlist_name() ) ) . '</label>';                        

                        echo '</div>';
                    }

                    echo '</div>';
                    echo '</div>';
                } else {
                    echo '<div class="modal-body py-0">';
                    echo '<div class="playlist-modal-content text-center p-4">';
                    echo '<p class="mb-3">' . esc_html__('No playlists found. Create one to get started!', 'streamit') . '</p>';
                    echo '</div>';
                    echo '</div>';
                }
            }
            ?>
            <div class="modal-footer">
                <button type="button" class="action-btn btn btn-secondary border" data-bs-toggle="modal" data-bs-target="#creatplaylistModal">
                    <?php esc_html_e('Create Playlist', 'streamit'); ?>
                </button>
            </div>
        <?php
        }

        /**
         * Displays the forgot password forms.
         */

        public function streamit_forgot_password_template()
        {
            if (!is_user_logged_in()) {
                // Login form fields
                $form_fields = array(
                    "user_email" => array(
                        "class" => "",
                        "required" => true,
                        "type" => "email",
                        "parent_class" => "col-md-12",
                        "placeholder" => esc_html__('Enter your email', 'streamit'),
                        "text" => esc_html__('Email Address', 'streamit'),
                    ),
                );

                // Pass the attributes and form fields to the template
                streamit_get_template('shortcode/forgotpassword.php', ['form_fields' => $form_fields]);
            } else {
                return '<p>' . esc_html__('You are already logged in.', 'streamit') . '</p>';
            }
        }

        /**
         * Shortcode to display an email subscription form.
         *
         * @param array $atts Attributes passed to the shortcode.
         * @return string HTML markup for the email subscription form.
         */
        public function streamit_email_subscription_form_shortcode($atts)
        {
            $atts = shortcode_atts(
                array(
                    'placeholder' => 'Email',
                    'subscribe_text' => 'Subscribe'
                ),
                $atts,
                'iq_email_subscription_form'
            );
            return '<form id="st-subscription-form"><div class="d-flex form-group st-subscription"><input type="email" name="EMAIL" placeholder="' . esc_attr($atts['placeholder']) . '" required class="form-control"><button id="st-subscribe-btn" class="btn btn-primary st-subscription-btn" type="submit">' . esc_html($atts['subscribe_text']) . '</button></div></form>';
        }


        /**
         * Render "Notify Me" button shortcode.
         *
         * @param array $atts Shortcode attributes.
         * @return string HTML of the button.
         */
        public function streamit_notify_me_shortcode_fun($atts)
        {

            // Shortcode defaults
            $atts = shortcode_atts(
                [
                    'post_id'   => 0,
                    'post_type' => '',
                    'class'     => '',
                    'season_id' => null,
                ],
                $atts,
                'streamit_notify_me_shortcode'
            );

            $post_id    = absint($atts['post_id']);
            $post_type  = sanitize_text_field($atts['post_type']);
            $season_id = ($atts['season_id'] !== null) ? absint($atts['season_id']) : null;

            if (empty($post_id) || empty($post_type)) {
                return '';
            }

            // Dynamically get the post object
            $func = 'streamit_get_' . $post_type;
            if (!function_exists($func)) {
                return '';
            }

            $post = $func($post_id);
            if (!$post || !method_exists($post, 'get_meta')) {
                return '';
            }

            // If season, check if season is upcoming
            if ($season_id !== null) {
                $seasons = $post->get_meta('_seasons');
                if (empty($seasons) || !isset($seasons[$season_id])) {
                    return '';
                }

                $season = $seasons[$season_id];
                if (function_exists('streamit_is_season_upcoming')) {
                    $season_upcoming = streamit_is_season_upcoming($season);
                    if (!$season_upcoming['is_future_release']) {
                        return '';
                    }
                }
            }


            // Non-logged-in users: show login button
            if (!is_user_logged_in()) {
                $login_link = streamit_login_page_url();
                return '<a href="' . esc_url($login_link) . '" class="btn btn-purple ' . esc_attr($atts['class']) . '">
                    <span class="d-flex align-items-center justify-content-center gap-2">
                        <span>' . st_get_icon('bell-1') . '</span>
                        <span>' . esc_html__('Remind Me', 'streamit') . '</span>
                    </span>
                </a>';
            }

            $user_id = get_current_user_id();
            $is_in_remind_me = false;

            if ($user_id && function_exists('streamit_is_reminded')) {
                $is_in_remind_me = streamit_is_reminded($user_id, $post_id, $post_type, $season_id ?? null);
            }

            $btn_class = 'btn-purple' . ($is_in_remind_me ? ' in-remind' : '');
            $icon_class = $is_in_remind_me ? 'check-2' : 'bell-1';
            $btn_text = esc_html__('Remind Me', 'streamit');
            $bs_title = $is_in_remind_me ? esc_html__('Remove reminder', 'streamit') : esc_html__('Remind Me', 'streamit');

            ob_start();
        ?>
            <button class="btn <?php echo esc_attr($btn_class); ?> notify-me-btn <?php echo esc_attr($atts['class']); ?>"
                data-post-id="<?php echo esc_attr($post_id); ?>"
                data-post-type="<?php echo esc_attr($post_type); ?>"
                <?php echo $season_id !== null ? 'data-season-id="' . esc_attr($season_id) . '"' : ''; ?>
                data-in-remind="<?php echo $is_in_remind_me ? '1' : '0'; ?>">
                <span class="d-flex align-items-center justify-content-center gap-2">
                    <span><?php echo st_get_icon($icon_class); ?></span>
                    <span><?php echo $btn_text; ?></span>
                </span>
            </button>
            <?php
            return ob_get_clean();
        }

    }

    // Initialize the Shortcode helper class.
    new streamit_shortcode_Helper();
}

<?php

defined('ABSPATH') || exit;


final class st_Authentication_Controller
{

    /**
     * Registration Method for User.
     *
     * @param WP_REST_Request $request The REST API request object.
     */
    public function user_register(WP_REST_Request $request)
    {
        $data = $request->get_params();
        $field_errors = array();

        // Validate Terms and Conditions
        if (!isset($data['st_term_condition']) || $data['st_term_condition'] !== 'accepted') {
            $field_errors['st_term_condition'] = esc_html__('You must accept the Terms and Conditions to proceed.', 'streamit');
        }

        // Validate username
        if (empty($data['user_username'])) {
            $field_errors['user_username'] = esc_html__('Username is required. Please provide a username.', 'streamit');
        }

        if (username_exists($data['user_username'])) {
            $field_errors['user_username'] = sprintf(esc_html__('The username %s is already taken. Please choose another.', 'streamit'), sanitize_user($data['user_username']));
        }

        // Validate email
        if (empty($data['user_email'])) {
            $field_errors['user_email'] = esc_html__('Email is required. Please provide a valid email.', 'streamit');
        } elseif (!is_email($data['user_email'])) {
            $field_errors['user_email'] = esc_html__('Invalid email address. Please provide a valid email.', 'streamit');
        }

        if (email_exists($data['user_email'])) {
            $field_errors['user_email'] = sprintf(esc_html__('The email %s is already registered. Please use a different email.', 'streamit'), sanitize_email($data['user_email']));
        }

        // Validate password
        if (empty($data['user_password'])) {
            $field_errors['user_password'] = esc_html__('Password is required. Please provide a password.', 'streamit');
        }

        // Validate first name
        if (empty($data['user_fname'])) {
            $field_errors['user_fname'] = esc_html__('First name is required.', 'streamit');
        }

        // Validate last name
        if (empty($data['user_lname'])) {
            $field_errors['user_lname'] = esc_html__('Last name is required.', 'streamit');
        }

        // If there are field validation errors, return them
        if (!empty($field_errors)) {
            return wp_send_json([
                'status' => false,
                'field_errors' => $field_errors
            ]);
        }

        // Register the user
        $user_id = wp_create_user(
            sanitize_user($data['user_username']),
            sanitize_text_field($data['user_password']),
            sanitize_email($data['user_email'])
        );

        if (is_wp_error($user_id)) {
            return wp_send_json([
                'status' => false,
                'field_errors' => [
                    'general' => $user_id->get_error_message()
                ]
            ]);
        }

        // Handle first and last name
        if (!empty($data['user_fname'])) {
            update_user_meta($user_id, 'first_name', sanitize_text_field($data['user_fname']));
        }

        if (!empty($data['user_lname'])) {
            update_user_meta($user_id, 'last_name', sanitize_text_field($data['user_lname']));
        }

        // Dynamically save any extra fields as user meta
        foreach ($data as $key => $value) {
            if (in_array($key, ['user_fname', 'user_lname', 'user_email', 'user_username', 'user_password', 'st_term_condition'])) {
                continue;
            }
            update_user_meta($user_id, sanitize_key($key), sanitize_text_field($value));
        }

        // Log the user in after successful registration
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        // Send success response
        return wp_send_json([
            'status' => true,
            'message' => esc_html__('Registration successful. Welcome!', 'streamit'),
            'redirect_url' => apply_filters('streamit_user_register_redirection', home_url())
        ]);
    }



    public function user_login(WP_REST_Request $request)
    {
        $data = $request->get_params();

        //Stop Conflication With PMP Login Functionality
        remove_action('wp_login_failed', 'pmpro_login_failed', 10, 2);
        remove_filter('authenticate', 'pmpro_authenticate_username_password', 30, 3);
        remove_filter('retrieve_password_message', 'pmpro_password_reset_email_filter', 10, 4);
        remove_action('login_form_rp', 'pmpro_do_password_reset');
        remove_action('login_form_resetpass', 'pmpro_do_password_reset');
        remove_action('login_form_rp', 'pmpro_reset_password_redirect');
        remove_action('login_form_resetpass', 'pmpro_reset_password_redirect');
        remove_action('login_form_lostpassword', 'pmpro_lost_password_redirect');
        remove_action('wp', 'pmpro_login_head');
        remove_action('login_init', 'pmpro_login_head');

        $field_errors = array();

        // Validate Terms and Conditions
        if (!isset($data['action']) || $data['action'] !== 'st_ajax_post') {
            return wp_send_json([
                'status' => false,
                'field_errors' => $field_errors
            ]);
        }

        // Validate username/email
        if (empty($data['user_username'])) {
            $field_errors['user_username'] = esc_html__('Username or email is required.', 'streamit');
        }

        // Validate password
        if (empty($data['user_password'])) {
            $field_errors['user_password'] = esc_html__('Password is required. Please provide your password.', 'streamit');
        }


        // Sanitize input
        $username_or_email = sanitize_text_field($data['user_username']);
        $password = $data['user_password'];

        // Check if the input is an email
        if (is_email($username_or_email)) {
            $user = get_user_by('email', $username_or_email);
        } else {
            $user = get_user_by('login', $username_or_email);
        }

        // Check if the user exists
        if (!$user) {
            $field_errors['user_username'] = esc_html__('Invalid username or email. Please try again.', 'streamit');
            return wp_send_json([
                'status' => false,
                'field_errors' => $field_errors
            ]);
        }

        // Authenticate the user
        $authenticated_user = wp_authenticate($user->user_login, $password);

        if (is_wp_error($authenticated_user)) {
            // Check if the error is due to an incorrect password
            if ($authenticated_user->get_error_code() === 'incorrect_password') {
                $field_errors['user_password'] = esc_html__('Incorrect password. Please try again.', 'streamit');
                return wp_send_json([
                    'status' => false,
                    'field_errors' => $field_errors
                ]);
            }

            // Generic error
            $field_errors['user_password'] = esc_html__('Incorrect password. Please try again.', 'streamit');
            return wp_send_json([
                'status' => false,
                'field_errors' => $field_errors
            ]);
        }

        // Check if the user is active
        $account_status = get_user_meta($user->ID, 'account_status', true);
        if (!empty($account_status) && $account_status !== 'active') {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Your account is not active. Please contact support.', 'streamit'),
                'field_errors' => $field_errors
            ]);
        }

        // Check device limits before allowing login
        if (function_exists('streamit_can_add_device')) {
            $can_add_device = streamit_can_add_device($user->ID, 'web');
            
            if ($can_add_device !== true) {
                // Handle different error scenarios
                if (is_array($can_add_device) && isset($can_add_device['error_code'])) {
                    $error_code = $can_add_device['error_code'];
                    
                    if ($error_code === 'LOGIN_NOT_ALLOWED') {
                        return wp_send_json([
                            'status' => false,
                            'message' => esc_html__('Login not allowed on this platform for your membership plan.', 'streamit'),
                            'error_code' => 'LOGIN_NOT_ALLOWED'
                        ]);
                    } elseif ($error_code === 'LOGIN_LIMIT_EXCEEDED') {
                        // Login limit exceeded - trigger device management via AJAX
                        return wp_send_json([
                            'status' => false,
                            'message' => esc_html__('Login limit exceeded. Please remove a device to continue.', 'streamit'),
                            'show_device_management' => true,
                            'username' => $username_or_email,
                            'device_management_endpoint' => 'st-user-get-devices',
                            'error_code' => 'LOGIN_LIMIT_EXCEEDED'
                        ]);
                    }
                }
                
                // Fallback error
                return wp_send_json([
                    'status' => false,
                    'message' => esc_html__('Login not allowed. Please check your membership plan settings.', 'streamit'),
                ]);
            }
        }

        $user_id = $user->ID;
        
        // Get remember me value from request
        $remember_me = isset($data['remember_me']) && $data['remember_me'] === '1';
        
        // Use streamit_store_session for device management
        if (function_exists('streamit_store_session')) {
            $session_created = streamit_store_session($user_id, $remember_me);
            
            if (!$session_created) {
                return wp_send_json([
                    'status' => false,
                    'message' => esc_html__('Something went wrong! Please try again.', 'streamit'),
                ]);
            }

            // Send success response
            return wp_send_json([
                'status' => true,
                'message' => esc_html__('Login Successful.', 'streamit'),
                'redirect_url' => apply_filters('login_redirect', home_url(), '', $user)
            ]);

        } else {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Somthing Want wrong!. Please try again.', 'streamit'),
            ]);
        }
     
    }

    /**
     * Forgot Password Method for User.
     *
     * @param WP_REST_Request $request The REST API request object.
     */
    public function forgot_password(WP_REST_Request $request)
    {
        $data = $request->get_params();
        $field_errors = array();

        // Validate action
        if (!isset($data['action']) || $data['action'] !== 'st_ajax_post') {
            $field_errors['action'] = esc_html__('Invalid action. Please use the correct form.', 'streamit');
            return wp_send_json([
                'status' => false,
                'field_errors' => $field_errors
            ]);
        }

        // Validate email
        if (empty($data['user_email'])) {
            $field_errors['user_email'] = esc_html__('Email is required.', 'streamit');
        } elseif (!is_email($data['user_email'])) {
            $field_errors['user_email'] = esc_html__('Please provide a valid email address.', 'streamit');
        }

        // If there are field validation errors, return them
        if (!empty($field_errors)) {
            return wp_send_json([
                'status' => false,
                'field_errors' => $field_errors
            ]);
        }

        // Check if the email exists in the system
        $user = get_user_by('email', $data['user_email']);

        if (!$user) {
            $field_errors['user_email'] = esc_html__('No account found with that email address.', 'streamit');
            return wp_send_json([
                'status' => false,
                'field_errors' => $field_errors
            ]);
        }

        // Generate password reset link
        $reset_key = get_password_reset_key($user);
        if (is_wp_error($reset_key)) {
            return wp_send_json([
                'status' => false,
                'field_errors' => [
                    'general' => esc_html__('There was an error generating the password reset link. Please try again.', 'streamit')
                ]
            ]);
        }

        $reset_url = network_site_url('wp-login.php', 'login');
        // Send the reset email
        $reset_url = add_query_arg(
            array(
                'action' => 'rp',
                'key'    => $reset_key,
                'login'  => rawurlencode($user->user_login),
            ),
            $reset_url
        );


        $subject = esc_html__('Password Reset Request', 'streamit');
        $message = sprintf(
            esc_html__('To reset your password, click the link below: %s', 'streamit'),
            $reset_url
        );

        $mail_sent = wp_mail($data['user_email'], $subject, $message);

        if (!$mail_sent) {
            return wp_send_json([
                'status' => false,
                'field_errors' => [
                    'general' => esc_html__('There was an error sending the password reset email. Please try again later.', 'streamit')
                ]
            ]);
        }

        // Success response
        return wp_send_json([
            'status' => true,
            'message' => esc_html__('A password reset link has been sent to your email address.', 'streamit'),
            'redirect_url' => apply_filters('streamit_user_reset_password_redirection', home_url())
        ]);
    }

    /**
     * Edit User Profile
     *
     * @param WP_REST_Request $request The REST API request object.
     */
    public function edit_profile(WP_REST_Request $request)
    {
        // Get data from request
        $data = $request->get_params();
        $files = $request->get_file_params();
        $field_errors = array();

        $user_id = get_current_user_id();

        if (!is_user_logged_in()) {
            return wp_send_json([
                'status' => false,
                'field_errors' => [
                    'general' => esc_html__('You are not allowed to edit profile', 'streamit')
                ]
            ]);
        }

        // Validate first name
        if (empty($data['first_name'])) {
            $field_errors['first_name'] = esc_html__('First name is required.', 'streamit');
        }

        // Validate last name
        if (empty($data['last_name'])) {
            $field_errors['last_name'] = esc_html__('Last name is required.', 'streamit');
        }

        // Validate email
        if (empty($data['user_email'])) {
            $field_errors['user_email'] = esc_html__('Email is required.', 'streamit');
        } elseif (!is_email($data['user_email'])) {
            $field_errors['user_email'] = esc_html__('Invalid email address', 'streamit');
        }

        if (email_exists($data['user_email']) && email_exists($data['user_email']) !== $user_id) {
            $field_errors['user_email'] = esc_html__('Email already in use by another user', 'streamit');
        }

        // If there are field validation errors, return them
        if (!empty($field_errors)) {
            return wp_send_json([
                'status' => false,
                'field_errors' => $field_errors
            ]);
        }

        // Update user profile details
        $user_update = wp_update_user([
            'ID' => $user_id,
            'first_name' => sanitize_text_field($data['first_name']),
            'last_name' => sanitize_text_field($data['last_name']),
            'user_email' => sanitize_email($data['user_email'])
        ]);

        if (is_wp_error($user_update)) {
            return wp_send_json([
                'status' => false,
                'field_errors' => [
                    'general' => esc_html__('Failed to update user information', 'streamit')
                ]
            ]);
        }

	//extra meta fileds
		$allowed_meta_keys = apply_filters('st_user_extra_meta_keys', [], $data);

		foreach ($allowed_meta_keys as $meta_key) {
		    if (isset($data[$meta_key])) {
		        update_user_meta($user_id, $meta_key, sanitize_text_field($data[$meta_key]));
		    }
		}

        // Get current avatar
        $current_avatar_url = get_user_meta($user_id, 'user_avatar', true);

        // Handle avatar upload
        if (isset($files['user_avatar']) && $files['user_avatar']['error'] === 0) {
            $avatar = $files['user_avatar'];

            // Get only allowed image MIME types
            $allowed_image_mimes = apply_filters('st_avtar_mime_type', [
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'gif'  => 'image/gif',
                'webp' => 'image/webp'
            ]);

            // Check if uploaded file is a valid image
            $file_info = wp_check_filetype_and_ext($avatar['tmp_name'], $avatar['name']);

            if (!$file_info['ext'] || !isset($allowed_image_mimes[$file_info['ext']])) {
                return wp_send_json([
                    'status' => false,
                    'field_errors' => [
                        'user_avatar' => esc_html__('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.', 'streamit')
                    ]
                ]);
            }

            // Remove previous avatar
            if ($current_avatar_url) {
                $current_avatar_id = attachment_url_to_postid($current_avatar_url);
                if ($current_avatar_id) {
                    wp_delete_attachment($current_avatar_id, true);
                }
            }

            // Upload the file securely
            $upload = wp_handle_upload($avatar, ['test_form' => false]);

            if (!isset($upload['file'])) {
                return wp_send_json([
                    'status' => false,
                    'field_errors' => [
                        'user_avatar' => esc_html__('Error uploading avatar.', 'streamit')
                    ]
                ]);
            }

            // Save to media library
            $attachment = [
                'post_mime_type' => $upload['type'],
                'post_title'     => sanitize_file_name($upload['file']),
                'post_content'   => '',
                'post_status'    => 'inherit'
            ];
            $attachment_id = wp_insert_attachment($attachment, $upload['file']);
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            wp_update_attachment_metadata($attachment_id, $attachment_metadata);

            // Update user meta with new avatar URL
            $avatar_url = wp_get_attachment_url($attachment_id);
            update_user_meta($user_id, 'user_avatar', $avatar_url);

            return wp_send_json(['status' => true, 'message' => esc_html__('Profile updated successfully!!', 'streamit')]);
        }

        // Remove avatar if requested
        $is_remove_avatar = isset($data['is_remove_avtar']) ? $data['is_remove_avtar'] : '';
        if (!empty($is_remove_avatar) && $is_remove_avatar === '1') {
            if ($current_avatar_url) {
                $current_avatar_id = attachment_url_to_postid($current_avatar_url);
                if ($current_avatar_id) {
                    wp_delete_attachment($current_avatar_id, true);
                }
            }
            update_user_meta($user_id, 'user_avatar', '');
        }

        return wp_send_json(['status' => true, 'message' => esc_html__('Profile updated successfully!!', 'streamit')]);
    }

    /**
     * Change Password – SAFE version
     */
    public function change_password(WP_REST_Request $request) {

        $user_id = get_current_user_id();
        $user_data = get_userdata($user_id);
        if ( $user_data && in_array('dumy', (array) $user_data->roles) ) {
            wp_send_json([
                'status'  => false,
                'message' => __( 'This feature isn’t available for demo accounts.', 'streamit' )
            ]);
        }
        $current_password = sanitize_text_field( $request->get_param('current_password') );
        $new_password     = sanitize_text_field( $request->get_param('new_password') );
        $confirm_password = sanitize_text_field( $request->get_param('confirm_password') );

        $response = [ 'status' => false, 'message' => '' ];

        // 1. Validate user
        $user = get_user_by('ID', $user_id);
        if ( ! $user ) {
            $response['message'] = __( 'Invalid user.', 'streamit' );
            wp_send_json( $response );
        }

        // 2. CHECK CURRENT PASSWORD SAFELY
        if ( ! wp_check_password( $current_password, $user->data->user_pass, $user->ID ) ) {
            $response['message'] = __( 'Current password is incorrect.', 'streamit' );
            wp_send_json( $response );
        }

        // 3. New vs Confirm
        if ( $new_password !== $confirm_password ) {
            $response['message'] = __( 'New passwords do not match.', 'streamit' );
            wp_send_json( $response );
        }

        // 4. Strength
        if ( strlen( $new_password ) < 6 ) {
            $response['message'] = __( 'New password must be at least 6 characters.', 'streamit' );
            wp_send_json( $response );
        }

        // 5. Update
        wp_set_password( $new_password, $user_id );

        $response['status']  = true;
        $response['message'] = __( 'Password changed successfully!!', 'streamit' );
        wp_send_json( $response );
    }
}

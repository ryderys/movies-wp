<?php

defined('ABSPATH') || exit;

/**
 * Device Management Controller
 * Handles device-related operations like removing devices
 */
final class st_Device_Management_Controller
{
    /**
     * Remove a specific device for a user
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return void
     */
    public function remove_device(WP_REST_Request $request)
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

        // Validate device_id
        if (empty($data['device_id'])) {
            $field_errors['device_id'] = esc_html__('Device ID is required.', 'streamit');
        }

        // Check if user_id is provided (for profile device management)
        if (!empty($data['user_id'])) {
            // Profile device management - user is already authenticated
            $user_id = intval($data['user_id']);
            
            // Verify user exists and is current user
            $current_user_id = get_current_user_id();
            if ($user_id !== $current_user_id) {
                return wp_send_json([
                    'status' => false,
                    'message' => esc_html__('Unauthorized access.', 'streamit'),
                ]);
            }
        } else {
            // Login device management - requires username/password authentication
            // Validate nonce
            if (!isset($data['st_login_nonce']) || !wp_verify_nonce($data['st_login_nonce'], 'streamit_login')) {
                return wp_send_json([
                    'status' => false,
                    'message' => esc_html__('Security check failed. Please refresh the page and try again.', 'streamit'),
                ]);
            }

            // Validate required fields for login scenario
            if (empty($data['username'])) {
                $field_errors['username'] = esc_html__('Username is required.', 'streamit');
            }

            if (empty($data['password'])) {
                $field_errors['password'] = esc_html__('Password is required.', 'streamit');
            }

            // If there are field validation errors, return them
            if (!empty($field_errors)) {
                return wp_send_json([
                    'status' => false,
                    'field_errors' => $field_errors
                ]);
            }

            // Sanitize input
            $username = sanitize_text_field($data['username']);
            $password = $data['password']; // Don't sanitize password as it may contain special chars

            // Authenticate user credentials
            if (!function_exists('streamit_authenticate_user_credentials')) {
                return wp_send_json([
                    'status' => false,
                    'message' => esc_html__('Authentication function not available.', 'streamit'),
                ]);
            }

            $auth_result = streamit_authenticate_user_credentials($username, $password);

            if (!$auth_result['status']) {
                return wp_send_json([
                    'status' => false,
                    'message' => $auth_result['message'] ?? esc_html__('Authentication failed.', 'streamit'),
                ]);
            }

            $user_id = $auth_result['user_id'];
        }

        // If there are field validation errors, return them
        if (!empty($field_errors)) {
            return wp_send_json([
                'status' => false,
                'field_errors' => $field_errors
            ]);
        }

        // Sanitize device_id
        $device_id = sanitize_text_field($data['device_id']);

        // Check if device removal function exists
        if (!function_exists('streamit_remove_device')) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Device management function not available.', 'streamit'),
            ]);
        }

        // Attempt to remove the device
        $removal_result = streamit_remove_device($user_id, $device_id);

        if ($removal_result) {
            // Device removed successfully
            return wp_send_json([
                'status' => true,
                'message' => esc_html__('Device logged out successfully.', 'streamit'),
            ]);
        } else {
            // Device removal failed
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Failed to remove device. Please try again.', 'streamit'),
            ]);
        }
    }

    /**
     * Remove all devices for a user (except current device)
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return void
     */
    public function remove_all_devices(WP_REST_Request $request)
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

        // Check if user_id is provided (for profile device management)
        if (!empty($data['user_id'])) {
            // Profile device management - user is already authenticated
            $user_id = intval($data['user_id']);
            
            // Verify user exists and is current user
            $current_user_id = get_current_user_id();
            if ($user_id !== $current_user_id) {
                return wp_send_json([
                    'status' => false,
                    'message' => esc_html__('Unauthorized access.', 'streamit'),
                ]);
            }
        } else {
            // Login device management - requires username/password authentication
            // Validate nonce
            if (!isset($data['st_login_nonce']) || !wp_verify_nonce($data['st_login_nonce'], 'streamit_login')) {
                return wp_send_json([
                    'status' => false,
                    'message' => esc_html__('Security check failed. Please refresh the page and try again.', 'streamit'),
                ]);
            }

            // Validate required fields for login scenario
            if (empty($data['username'])) {
                $field_errors['username'] = esc_html__('Username is required.', 'streamit');
            }

            if (empty($data['password'])) {
                $field_errors['password'] = esc_html__('Password is required.', 'streamit');
            }

            // If there are field validation errors, return them
            if (!empty($field_errors)) {
                return wp_send_json([
                    'status' => false,
                    'field_errors' => $field_errors
                ]);
            }

            // Sanitize input
            $username = sanitize_text_field($data['username']);
            $password = $data['password']; // Don't sanitize password as it may contain special chars

            // Authenticate user credentials
            if (!function_exists('streamit_authenticate_user_credentials')) {
                return wp_send_json([
                    'status' => false,
                    'message' => esc_html__('Authentication function not available.', 'streamit'),
                ]);
            }

            $auth_result = streamit_authenticate_user_credentials($username, $password);

            if (!$auth_result['status']) {
                return wp_send_json([
                    'status' => false,
                    'message' => $auth_result['message'] ?? esc_html__('Authentication failed.', 'streamit'),
                ]);
            }

            $user_id = $auth_result['user_id'];
        }

        // If there are field validation errors, return them
        if (!empty($field_errors)) {
            return wp_send_json([
                'status' => false,
                'field_errors' => $field_errors
            ]);
        }

        // Check if device removal function exists
        if (!function_exists('streamit_remove_all_devices')) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Device management function not available.', 'streamit'),
            ]);
        }

        // Attempt to remove all devices
        $removal_result = streamit_remove_all_devices($user_id);

        if ($removal_result) {
            // All devices removed successfully
            return wp_send_json([
                'status' => true,
                'message' => esc_html__('All devices logged out successfully.', 'streamit'),
            ]);
        } else {
            // Device removal failed
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Failed to remove devices. Please try again.', 'streamit'),
            ]);
        }
    }

    /**
     * Get user devices for management
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return void
     */
    public function get_user_devices(WP_REST_Request $request)
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

        // Validate nonce
        if (!isset($data['st_login_nonce']) || !wp_verify_nonce($data['st_login_nonce'], 'streamit_login')) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Security check failed. Please refresh the page and try again.', 'streamit'),
            ]);
        }

        // Validate required fields
        if (empty($data['username'])) {
            $field_errors['username'] = esc_html__('Username is required.', 'streamit');
        }

        if (empty($data['password'])) {
            $field_errors['password'] = esc_html__('Password is required.', 'streamit');
        }

        // If there are field validation errors, return them
        if (!empty($field_errors)) {
            return wp_send_json([
                'status' => false,
                'field_errors' => $field_errors
            ]);
        }

        // Sanitize input
        $username = sanitize_text_field($data['username']);
        $password = $data['password']; // Don't sanitize password as it may contain special chars

        // Authenticate user credentials
        if (!function_exists('streamit_authenticate_user_credentials')) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Authentication function not available.', 'streamit'),
            ]);
        }

        $auth_result = streamit_authenticate_user_credentials($username, $password);

        if (!$auth_result['status']) {
            return wp_send_json([
                'status' => false,
                'message' => $auth_result['message'] ?? esc_html__('Authentication failed.', 'streamit'),
            ]);
        }

        $user_id = $auth_result['user_id'];

        // Check if device function exists
        if (!function_exists('streamit_get_user_devices')) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Device management function not available.', 'streamit'),
            ]);
        }

        // Get user devices
        $devices = streamit_get_user_devices($user_id);

        $stats = null;
        if (function_exists('streamit_get_user_devices_with_stats')) {
            $with_stats = streamit_get_user_devices_with_stats($user_id);
            if (is_array($with_stats) && isset($with_stats['stats'])) {
                $stats = $with_stats['stats'];
            }
        }

        return wp_send_json([
            'status' => true,
            'message' => esc_html__('Devices retrieved successfully.', 'streamit'),
            'devices' => $devices,
            'stats'   => $stats,
        ]);
    }

    /**
     * Get user devices with stats for management
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return void
     */
    public function get_user_devices_with_stats(WP_REST_Request $request)
    {
        $data = $request->get_params();

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('User not logged in.', 'streamit'),
            ]);
        }

        // Validate required fields
        if (empty($data['user_id'])) {
            return wp_send_json([
                'status' => false,
                'error' => esc_html__('User ID is required.', 'streamit')
            ]);
        }

        // Sanitize input
        $user_id = intval($data['user_id']);

        // Verify user exists and is the current user
        $current_user_id = get_current_user_id();
        if ($user_id !== $current_user_id) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Unauthorized access.', 'streamit'),
            ]);
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('User not found.', 'streamit'),
            ]);
        }

        // Check if device function exists
        if (!function_exists('streamit_get_user_devices_with_stats')) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Device management function not available.', 'streamit'),
            ]);
        }

        // Get user devices with stats
        $result = streamit_get_user_devices_with_stats($user_id);

        // Get plan name
        $plan_name = 'No plan';
        if (function_exists('streamit_user_current_pmp_level')) {
            $user_level = streamit_user_current_pmp_level($user_id);
            if (!empty($user_level) && isset($user_level->name)) {
                // Check if membership is still active
                $pmp_end_date = isset($user_level->enddate) ? $user_level->enddate : null;
                if ($pmp_end_date !== null) {
                    $current_timestamp = current_time('timestamp');
                    // Only show plan name if membership is active
                    if ($pmp_end_date > $current_timestamp) {
                        $plan_name = $user_level->name;
                    }
                } else {
                    // No enddate means lifetime membership
                    $plan_name = $user_level->name;
                }
            }
        }

        return wp_send_json([
            'status' => true,
            'message' => esc_html__('Devices and stats retrieved successfully.', 'streamit'),
            'devices' => $result['data'],
            'stats' => $result['stats'],
            'plan_name' => $plan_name,
        ]);
    }


}

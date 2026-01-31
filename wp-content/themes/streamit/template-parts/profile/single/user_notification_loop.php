<?php

/**
 * The template for displaying archive genre page
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $streamit_core_options;

$redirect_url = $image_id = '';
$action = $st_data->get_action();

$notification_content = json_decode($st_data->get_content());
if ($st_data->get_action() === 'new_release') {
    $image_id = absint($notification_content->thumbnail_id);
    $redirect_url = streamit_get_permalink(
        sanitize_text_field($notification_content->post_type),
        sanitize_text_field($notification_content->post_name)
    );
} elseif ($st_data->get_action() === 'pmp_new_plan') {
    if (! class_exists('PMPro_Membership_Level')) {
        return; // or handle the case appropriately if not inside a function
    }
    if (empty($notification_content->plan_id)) {
        return; // or handle the missing plan ID accordingly
    }
    $plan_id = $notification_content->plan_id;
    $image_id = isset($streamit_core_options['membership_new_plan_notification_image']) ? $streamit_core_options['membership_new_plan_notification_image']['id'] : '';
    $redirect_url = pmpro_url("checkout") . "?level=" . $plan_id;
} elseif ($st_data->get_action() === 'pmp_plan_purchase') {
    if (! class_exists('PMPro_Membership_Level')) {
        return; // or handle the case appropriately if not inside a function
    }
    if (empty($notification_content->plan_id)) {
        return; // or handle the missing plan ID accordingly
    }
    $plan_id = $notification_content->plan_id;
    
    $image_id = isset($streamit_core_options['membership_user_purchase_notification_image']) ? $streamit_core_options['membership_user_purchase_notification_image']['id'] : '';
    $redirect_url = pmpro_url("checkout") . "?level=" . $plan_id;
    
} elseif ($st_data->get_action() === 'pmp_ppv_purchase') {

    $post_id = $notification_content->post_id;
    $post_type = $notification_content->post_type;
    $function_name = 'streamit_get_' . $post_type;
    $image_id = absint($notification_content->image_id);

    if (function_exists($function_name)) {
        $post_data = call_user_func($function_name, (int)$post_id);

        if (!is_wp_error($post_data) && !empty($post_data)) {
            $redirect_url = streamit_get_permalink($post_data->get_post_type(), $post_data->get_post_name());

        } else {
            $redirect_url = '#'; // fallback URL or handle error as needed
        }
    }
}

$image_url = isset($image_id) && !empty($image_id) ? wp_get_attachment_image_url($image_id) : streamit_placeholder_image();

// Determine notification type for filtering
$notification_type = 'all';
switch ($action) {
    case 'pmp_plan_purchase':
    case 'pmp_ppv_purchase':
        $notification_type = 'purchases';
        break;
    case 'new_release':
        $notification_type = 'releases';
        break;
    default:
        $notification_type = 'all';
        break;
}
?>
<li class="notification-item" data-notification-type="<?php echo esc_attr($notification_type); ?>">
    <div class="d-flex align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">

            <div class="notification-image flex-shrink-0">
                <a href="<?php echo esc_url($redirect_url) ?>" class="link-overlay" data-user_id="<?php echo esc_attr(get_current_user_id()); ?>" data-notification_id="<?php echo esc_attr($st_data->get_id()); ?>">
                    <img decoding="async" src="<?php echo esc_url($image_url) ?>" alt="<?php esc_attr_e('image', 'streamit') ?>" class="img-fluid object-cover result-image">
                </a>
            </div>
            <div class="notification-message">
                <a href="<?php echo esc_url($redirect_url) ?>" class="link-overlay message" data-user_id="<?php echo esc_attr(get_current_user_id()); ?>" data-notification_id="<?php echo esc_attr($st_data->get_id()); ?>">
                    <?php $formated_details = streamit_format_notification_message($action, $notification_content);
                    echo esc_html($formated_details['message']); ?>
                </a>
                <span class="d-block">
                    <?php
                    $text_data = sanitize_text_field($notification_content->current_date);
                    if (!empty($text_data) && strtotime($text_data) !== false) {
                        echo sprintf(_x('%s ago', 'time difference', 'streamit'), human_time_diff(strtotime($text_data), current_time('timestamp')));
                    } else {
                        // Fallback to notification creation date if current_date is invalid
                        $notification_date = $st_data->get_date();
                        if (!empty($notification_date)) {
                            echo sprintf(_x('%s ago', 'time difference', 'streamit'), human_time_diff(strtotime($notification_date), current_time('timestamp')));
                        } else {
                            echo _x('Recently', 'time difference', 'streamit');
                        }
                    }
                    ?>
                </span>
            </div>
        </div>

        <div class="notification-actions flex-shrink-0">
            <div class="d-flex justify-content-center align-items-center gap-3">
                <button type="button" class="btn btn-secondary btn-circle border notification-action-btn" data-user_id="<?php echo esc_attr(get_current_user_id()); ?>" data-notification_id="<?php echo esc_attr($st_data->get_id()); ?>" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php echo esc_html__('Mark as read', 'streamit') ?>">
                    <?php echo st_get_icon('eye-2'); ?>
                </button>
            </div>
        </div>
    </div>
</li>
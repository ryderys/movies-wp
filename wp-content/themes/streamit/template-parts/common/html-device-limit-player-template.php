<?php

/**
 * Player Device limit Template
 * 
 * @package streamit
 */

defined('ABSPATH') || exit;

// Get current user devices
$current_user_id = get_current_user_id();
$user_devices = [];

// Check if function exists before calling it
if (function_exists('streamit_get_user_devices') && $current_user_id > 0) {
    $user_devices = streamit_get_user_devices($current_user_id);
}

$post_image = $post_image ?? '';
$post_url = $post_url ?? '#';
$page = $page ?? '';
$total_limit = $total_limit ?? 0;
$need_to_logout = $total_devices - $total_limit;
// Set default values if not passed from single.php
$device_limit_message = sprintf(__('You have total %s device limit so logout other device to continue.', 'streamit'), $total_limit);
$device_heading_title = sprintf(__('Log Out %s Device to Continue', 'streamit'), $need_to_logout);
$extra_class = isset($class) && !empty($class) ? $class : '';

?>

<div class="device-restricted-section<?php echo !empty($page) ? ' page-' . esc_attr($page) : ''; ?> flex-column <?php echo esc_attr($extra_class); ?>">
    <?php if (!isset($page) || ($page !== 'episode')) : ?>
        <div class="flex-shrink-0">
            <a class="btn btn-primary btn-sm" href="<?php echo esc_url($post_url); ?>">
                <span class="d-flex align-items-center gap-2">
                    <i class="icon-arrow-prev"></i>
                    <span> <?php esc_html_e('Back', 'streamit'); ?></span>
                </span>
            </a>
        </div>
    <?php endif; ?>
    <div class="container-fluid">
        <div class="row flex-grow-1">
            <div class="col-xl-7 col-md-6 align-self-center d-md-block d-none">
                <div class="card">
                    <div class="card-body">
                        <div class="position-relative">
                            <div class="ratio ratio-16x9">
                                <?php if (!empty($post_image)) : ?>
                                    <img src="<?php echo esc_url($post_image); ?>" alt="<?php esc_attr_e('Content thumbnail', 'streamit'); ?>" class="rounded w-100 object-fit-cover">
                                <?php else : ?>
                                    <img src="https://placehold.co/600x800" alt="<?php esc_attr_e('No image available', 'streamit'); ?>" class="rounded w-100 object-fit-cover">
                                <?php endif; ?>
                            </div>
                            <div class="position-absolute bottom-0 left-0 mb-2">
                                <ul class="list-inline m-0 px-3 d-flex align-items-center gap-2">
                                    <li>
                                        <div class="bg-gray-900 rounded device-icon">
                                            <i class="icon-device-desktop"></i>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="bg-gray-900 rounded device-icon">
                                            <i class="icon-device-mobile"></i>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-5">
                    <h4><?php esc_html_e('Device Limit Reached', 'streamit'); ?></h4>
                    <p class="m-0" id="device-limit-message"><?php echo esc_html($device_limit_message); ?></p>
                </div>
            </div>
            <div class="col-xl-5 col-md-6">
                <h5 class="mb-4"><?php echo esc_html($device_heading_title); ?></h5>

                <!-- Device Logout Processing Loader -->
                <div class="device-logout-loader text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden"><?php esc_html_e('Loading...', 'streamit'); ?></span>
                    </div>
                </div>

                <ul class="list-inline m-0 p-0 login-device-list">
                    <?php if (!empty($user_devices)) : ?>
                        <?php foreach ($user_devices as $device) : ?>
                            <?php
                            // Skip current device
                            if ($device['is_current_device']) {
                                continue;
                            }

                            // Get device icon class based on type
                            $device_icon_class = 'icon-device-desktop'; // default
                            if ($device['type'] === 'mobile' || $device['type'] === 'app') {
                                $device_icon_class = 'icon-device-mobile';
                            } elseif ($device['type'] === 'web') {
                                $device_icon_class = 'icon-device-desktop';
                            }

                            // Format last used time using WordPress timezone
                            $last_used = __('Unknown', 'streamit');
                            if (!empty($device['login_time'])) {
                                try {
                                    $wp_timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(wp_timezone_string());
                                    // Assume stored login_time is in UTC (common for DB timestamps)
                                    $login_dt = new DateTime($device['login_time'], new DateTimeZone('UTC'));
                                    $login_dt->setTimezone($wp_timezone);
                                    $login_time_ts = $login_dt->getTimestamp();

                                    $now_dt = new DateTime('now', $wp_timezone);
                                    $current_time = $now_dt->getTimestamp();
                                } catch (Exception $e) {
                                    // Fallback: convert from GMT to local if possible, else plain strtotime
                                    if (function_exists('get_date_from_gmt')) {
                                        $local_str = get_date_from_gmt($device['login_time'], 'Y-m-d H:i:s');
                                        $login_time_ts = strtotime($local_str);
                                    } else {
                                        $login_time_ts = strtotime($device['login_time']);
                                    }
                                    $current_time = current_time('timestamp');
                                }

                                if ($login_time_ts) {
                                    $time_diff = $current_time - $login_time_ts;

                                    if ($time_diff < 60) {
                                        $last_used = __('Just now', 'streamit');
                                    } elseif ($time_diff < 3600) {
                                        $minutes = floor($time_diff / 60);
                                        $last_used = sprintf(_n('%d minute ago', '%d minutes ago', $minutes, 'streamit'), $minutes);
                                    } elseif ($time_diff < 86400) {
                                        $hours = floor($time_diff / 3600);
                                        $last_used = sprintf(_n('%d hour ago', '%d hours ago', $hours, 'streamit'), $hours);
                                    } else {
                                        $days = floor($time_diff / 86400);
                                        $last_used = sprintf(_n('%d day ago', '%d days ago', $days, 'streamit'), $days);
                                    }
                                }
                            }
                            ?>
                            <li class="mb-3">
                                <div class="login-device-card rounded p-3 d-flex align-items-center justify-content-between gap-2">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-sm-3 gap-2">
                                            <span class="flex-shrink-0">
                                                <i class="<?php echo esc_attr($device_icon_class); ?> fs-4"></i>
                                            </span>
                                            <div>
                                                <h6 class="font-size-14 mb-1 device-name"><?php echo esc_html($device['device_name']); ?></h6>
                                                <span class="login-info">
                                                    <span><?php esc_html_e('Last used', 'streamit'); ?></span>
                                                    <span class="last-used"><?php echo esc_html($last_used); ?></span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <button class="btn btn-secondary px-3 py-2 device-logout-btn"
                                            data-device-id="<?php echo esc_attr($device['device_id']); ?>"
                                            data-user-id="<?php echo esc_attr($current_user_id); ?>"
                                            data-is-device-template-load="true">
                                            <span class="btn-text"><?php esc_html_e('Log out', 'streamit'); ?></span>
                                            <span class="btn-loader" style="display: none;">
                                                <div class="spinner-border spinner-border-sm" role="status">
                                                    <span class="visually-hidden"><?php esc_html_e('Loading...', 'streamit'); ?></span>
                                                </div>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li>
                            <div class="text-center py-4">
                                <p class="text-muted"><?php esc_html_e('No other devices found', 'streamit'); ?></p>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>

                <?php
                // Get the highest priced PMP plan
                $highest_plan = function_exists('streamit_get_highest_priced_plan') ? streamit_get_highest_priced_plan() : null;

                if ($highest_plan) {
                ?>
                    <div class="my-5 d-flex align-items-center gap-3">
                        <span class="border-top flex-grow-1"></span>
                        <h6 class="m-0 flex-shrink-0"><?php esc_html_e('Or', 'streamit'); ?></h6>
                        <span class="border-top flex-grow-1"></span>
                    </div>

                    <div class="mt-5">
                        <h5 class="mb-3"><?php esc_html_e('Upgrade', 'streamit'); ?></h5>
                        <?php
                        // Get plan price and currency
                        $plan_price = max(
                            (float) $highest_plan->initial_payment,
                            (float) $highest_plan->billing_amount
                        );
                        $currency_symbol = get_option('pmpro_currency_symbol', '$');

                        // Get device limit for this plan
                        $device_limit = 0;
                        $login_limit_settings = get_pmpro_membership_level_meta($highest_plan->id, 'streamit_login_limit_settings', true);
                        if (!empty($login_limit_settings) && is_array($login_limit_settings)) {
                            $device_limit = isset($login_limit_settings['total_device_limit']) ? (int) $login_limit_settings['total_device_limit'] : 0;
                        }

                        // Get checkout URL
                        $checkout_url = function_exists('pmpro_url') ? pmpro_url('checkout', '?level=' . $highest_plan->id) : '#';

                        // Format price display
                        $price_display = $currency_symbol . number_format($plan_price, 2);
                        if ($highest_plan->cycle_number > 0 && $highest_plan->cycle_period) {
                            $cycle_text = $highest_plan->cycle_number . ' ' . $highest_plan->cycle_period;
                            if ($highest_plan->cycle_number > 1) {
                                $cycle_text .= 's';
                            }
                            $price_display .= '/' . $cycle_text;
                        }

                        // Device limit text
                        $device_text = $device_limit > 0 ? $device_limit . ' devices' : __('Unlimited devices', 'streamit');

                        // Plan name
                        $plan_name = !empty($highest_plan->name) ? $highest_plan->name : '';

                        // Plan description
                        $plan_description = !empty($highest_plan->description) ? $highest_plan->description : __('Enjoy all the benefits', 'streamit');
                        ?>
                        <a href="<?php echo esc_url($checkout_url); ?>" class="p-3 bg-warning-subtle border border-warning rounded d-block">
                            <span class="d-flex align-items-center justify-content-between gap-4">
                                <span class="flex-grow-1">
                                    <span class="badge text-bg-warning"><?php esc_html_e('RECOMMENDED', 'streamit'); ?></span>
                                    <span class="h6 d-block mt-4"><?php esc_html_e('Upgrade to', 'streamit'); ?> <?php echo esc_html($plan_name); ?> <?php esc_html_e('-', 'streamit'); ?> <?php echo esc_html($device_text); ?> <?php esc_html_e('for', 'streamit'); ?> <?php echo esc_html($price_display); ?></span>
                                    <span class="d-block mt-1 font-size-14 text-body"><?php echo esc_html($plan_description); ?></span>
                                </span>
                                <span class="flex-shrink-0">
                                    <i class="icon-arrow-right text-body"></i>
                                </span>
                            </span>
                        </a>
                    </div>
                <?php
                }
                ?>

            </div>
        </div>
    </div>
</div>
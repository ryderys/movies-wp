<?php
/**
 * The template for displaying user details
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;
$current_user_id = get_current_user_id();
$user_levels = function_exists('streamit_user_current_pmp_level') ? streamit_user_current_pmp_level($current_user_id) : [];
$user_avatar_new = get_user_meta($user_details['id'], 'user_avatar', true);

$pmp_level_name = isset($user_levels->name) ? $user_levels->name : null;
$pmp_end_date   = isset($user_levels->enddate) ? $user_levels->enddate : null;
$show_icon = isset($streamit_options['streamit_icon']) && $streamit_options['streamit_icon'] === 'yes';
$subscribe_button_title = !empty($streamit_options['streamit_subscribe_text']) ? $streamit_options['streamit_subscribe_text'] : esc_html__('Subscribe', 'streamit');

// Check if the user has an active membership
$has_active_membership = false;
if (!empty($user_levels) && $pmp_level_name !== null) {
    if ($pmp_end_date !== null) {
        $current_timestamp = current_time('timestamp');
        $difference_in_seconds = $pmp_end_date - $current_timestamp;
        
        if ($difference_in_seconds > 0) {
            $has_active_membership = true;
            $days = floor($difference_in_seconds / DAY_IN_SECONDS);
            $hours = floor(($difference_in_seconds % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
            $minutes = floor(($difference_in_seconds % HOUR_IN_SECONDS) / 60);
            
            if ($days > 1) {
                $days_left = $days . ' ' . esc_html__('days', 'streamit');
            } elseif ($days == 1) {
                $days_left = $days . ' ' . esc_html__('day', 'streamit');
            } elseif ($hours > 0) {
                $days_left = $hours . 'h' . ($minutes > 0 ? ' ' . $minutes . 'm' : '') . ' ' . esc_html__('left', 'streamit');
            } else {
                $days_left = $minutes . 'm ' . esc_html__('left', 'streamit');
            }
        }
    } else {
        $has_active_membership = true;
    }
}
?>

<div class="bg-gray-900 px-sm-5 px-3 py-4 rounded-3 profile-user-info">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="profile-image flex-shrink-0">
                    <img src="<?php echo esc_url($user_avatar_new ?: esc_url($user_details['user_avatar'])); ?>" alt="<?php echo esc_attr($user_details['display_name']); ?>" class="user-image">
                </div>
                <div class="profile-info">
                    <h5 class="mt-0">
                        <?php echo !empty($user_details['first_name']) || !empty($user_details['last_name'])
                            ? esc_html($user_details['first_name']) . ' ' . esc_html($user_details['last_name'])
                            : esc_html($user_details['display_name']); ?>
                    </h5>
                    <p class="mb-1 mt-0 text-break"><?php echo esc_html($user_details['user_email']); ?></p>
                    <p class="m-0"><?php echo esc_html($user_details['current_user_name']); ?></p>
                </div>
            </div>
        </div>

        <div class="col-sm-6 mt-sm-0 mt-3">
            <div class="d-flex align-items-center justify-content-sm-end gap-3 flex-wrap">

                <?php if (!empty($user_levels) && $pmp_level_name !== null && $has_active_membership) : ?>
                    <div class="text-end">
                        <span class="fw-semibold">
                            <?php esc_html_e('Active Membership:', 'streamit'); ?>
                        </span>
                        <span class="text-primary fw-semibold">
                            <?php echo ' ' . esc_html($pmp_level_name); ?>
                        </span>
                        <?php if (isset($days_left)) : ?>
                            <span class="small text-muted">
                                (<?php echo esc_html($days_left); ?>)
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Upgrade Button -->
                    <a href="<?php echo esc_url(streamit_subscribe_page_url()); ?>" class="subscribe-btn btn btn-warning-subtle py-1 py-md-2 px-2 px-ms-3">
                        <span class="d-flex align-items-center gap-2">
                                <?php if ($show_icon) : ?>
                                    <?php echo st_get_icon('premium'); ?>
                                <?php endif; ?>
                                <?php esc_html_e('Upgrade', 'streamit'); ?>
                        </span>
                    </a>

                <?php else : ?>
                    <!-- Subscribe Button if no active membership -->
                    <a href="<?php echo esc_url(streamit_subscribe_page_url()); ?>" class="subscribe-btn btn btn-warning-subtle py-1 py-md-2 px-2 px-ms-3">
                        <span class="d-flex align-items-center gap-2">
                            <?php if ($show_icon) : ?>
                                <?php echo st_get_icon('premium'); ?>

                            <?php endif; ?>
                            <span><?php echo esc_html($subscribe_button_title); ?></span>
                        </span>
                    </a>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

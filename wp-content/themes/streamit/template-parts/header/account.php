<?php

/**
 * Template part for displaying the header account menu
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $streamit_options;
global $streamit_core_options;

$current_user = wp_get_current_user();
$user_avatar_new = get_user_meta($current_user->ID, 'user_avatar', true);
$user_avatar = get_avatar_url($current_user->ID, array('size' => 96));

if (isset($streamit_options['header_display_user_act']) && $streamit_options['header_display_user_act'] == 'yes') {
?>
    <li class="nav-item flex-shrink-0 dropdown dropdown-user-wrapper">

        <?php if (is_user_logged_in()) : ?>
            <a class="nav-link dropdown-user" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="st-avatar style-1">
                    <img src="<?php echo esc_url(!empty($user_avatar_new) ? $user_avatar_new : $user_avatar); ?>" class="img-fluid dropdown-user-menu-image" alt="<?php echo esc_attr($current_user->display_name); ?>" height="20">
                </span>
            </a>
            <?php else :
            $iqonic_signin_link = home_url();
            if (!empty($streamit_options['streamit_signin_link'])) {
                $iqonic_signin_link = get_page_link($streamit_options['streamit_signin_link']);
            }
            if (isset($streamit_options['streamit_headre_button']) && $streamit_options['streamit_headre_button'] == 'yes'): ?>
                <a class="btn btn-primary py-2 px-3" href="<?php echo esc_url($iqonic_signin_link); ?>">
                    <span class="d-flex align-items-center justify-content-center gap-2">
                        <span><?php echo esc_html__('Login', 'streamit'); ?></span>
                    </span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <div class="dropdown-menu dropdown-menu-end dropdown-user-menu border border-gray-900">
            <div class="user-dropdown-inner">

                <?php if (is_user_logged_in()) : ?>
                    <div class="d-flex align-items-center gap-3 rounded mb-4">
                        <div class="image flex-shrink-0">
                            <img src="<?php echo !empty($user_avatar_new) ? esc_url($user_avatar_new) : esc_url($user_avatar); ?>" class="img-fluid rounded dropdown-user-menu-image" alt="<?php echo esc_attr($current_user->display_name); ?>">
                        </div>
                        <div class="content">
                            <h6 class="mb-1"><?php echo esc_html($current_user->display_name); ?></h6>
                        </div>
                    </div>

                    <ul class="d-flex flex-column gap-3 list-inline m-0 p-0">
                        <!-- My Account -->
                        <?php if (function_exists('streamit_get_permalink')): ?>
                            <li>
                                <?php $account_link = streamit_get_permalink('profile'); ?>
                                <a href="<?php echo esc_url($account_link); ?>" class="link-body-emphasis font-size-14">
                                    <span class="d-flex align-items-center gap-2">
                                        <?php echo st_get_icon('user'); ?>

                                        <span class="fw-medium"><?php echo esc_html__('Profile', 'streamit'); ?></span>
                                    </span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Watchlist -->
                        <?php
                        $watchlist_display = isset($streamit_options['streamit_display_watchlist']) && $streamit_options['streamit_display_watchlist'] !== 'no';
                        if ($watchlist_display) :
                            $watchlist_title = !empty($streamit_options['streamit_watchlist_title']) ? $streamit_options['streamit_watchlist_title'] : esc_html__('Watchlist', 'streamit');
                            $watchlist_link = !empty($streamit_options['streamit_watchlist_link']) ? get_page_link($streamit_options['streamit_watchlist_link']) : '';
                        ?>
                            <li>
                                <a href="<?php echo esc_url($watchlist_link); ?>" class="link-body-emphasis font-size-14">
                                    <span class="d-flex align-items-center gap-2">
                                        <?php echo st_get_icon('plus'); ?>

                                        <span class="fw-medium"><?php echo esc_html($watchlist_title); ?></span>
                                    </span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Playlist -->
                        <?php
                        $is_playlist_display = isset($streamit_core_options['streamit_display_playlist']) && ($streamit_core_options['streamit_display_playlist'] == 'no') ? false : true;
                        if ($is_playlist_display && function_exists('streamit_get_permalink')) :
                            $playlist_link = streamit_get_permalink('archive_playlist');
                        ?>
                            <li>
                                <a href="<?php echo esc_url($playlist_link); ?>" class="link-body-emphasis font-size-14">
                                    <span class="d-flex align-items-center gap-2">
                                        <?php echo st_get_icon('playlist'); ?>

                                        <span class="fw-medium"><?php echo esc_html__('Playlist', 'streamit'); ?></span>
                                    </span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Subscription -->
                        <?php
                        $pricing_plan_link = function_exists('pmpro_getAllLevels') ? streamit_get_permalink('profile', 'pmp') : '';
                        if (!empty($pricing_plan_link)) :
                        ?>
                            <li>
                                <a href="<?php echo esc_url($pricing_plan_link); ?>" class="link-body-emphasis font-size-14">
                                    <span class="d-flex align-items-center gap-2">
                                        <?php echo st_get_icon('subscription'); ?>
                                        <span class="fw-medium"><?php echo esc_html__('Subscription', 'streamit'); ?></span>
                                    </span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (isset($streamit_options['enable_notification_module']) && $streamit_options['enable_notification_module'] === 'yes' && function_exists('streamit_get_permalink')): ?>
                            <li>
                                <?php
                                $notification_label = !empty($streamit_options['notification_label']) ? esc_html($streamit_options['notification_label']) : esc_html__('New Release', 'streamit');  // Default value fallback
                                $account_link = streamit_get_permalink('profile',  'notification'); ?>
                                <a href="<?php echo esc_url($account_link); ?>" class="link-body-emphasis font-size-14">
                                    <span class="d-flex align-items-center gap-2">
                                        <?php echo st_get_icon('bell-1'); ?>

                                        <span class="fw-medium"><?php echo $notification_label; ?></span>
                                    </span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>

            </div>

            <?php $logout_label = esc_attr($streamit_options['streamit_logout_title'] ?? __('Logout', 'streamit')); ?>


            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="btn btn-link p-3 d-block font-size-14 text-center text-decoration-none border-top">
                <span class="d-flex align-items-center justify-content-center gap-2 fw-medium">
                    <?php echo st_get_icon('logout'); ?>

                    <?php echo esc_attr_e($logout_label); ?>
                </span>
            </a>
        </div>

    </li>
<?php } ?>
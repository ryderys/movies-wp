<?php

/**
 * Displays the footer navigation area (mobile view only)
 *
 * @package WordPress
 * @subpackage streamit
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!function_exists('streamit_get_permalink'))
    return;

// Ensure we only display this for mobile users.
global $streamit_options;
if (($streamit_options['manage_footer_mobile_menu'] ?? '') === 'no')
    return;

$current_user = wp_get_current_user();
?>

<div class="streamit-mobile-footer-menu"
    aria-label="<?php esc_attr_e('Mobile Footer Navigation', 'streamit'); ?>">

    <!-- For Scroll Event -->
    <!-- <button class="left flex-shrink-0"><i class="icon-arrow-left"></i></button> -->

    <!-- Scrollable container -->
    <!-- For Scroll Event -->
    <!-- <div class="custom-tab-slider footer-mobile-menu"> -->
        
    <div class="footer-mobile-menu">

        <?php
        if (has_nav_menu('streamit-footer-menu-link')) {
            wp_nav_menu(array(
                'theme_location' => 'streamit-footer-menu-link',
                'container' => false,
                'menu_class' => 'footer-menu list-inline',
                'depth' => 1,
            ));
        } else {
            // Fallback to hardcoded links if no menu is assigned
        ?>
            <ul class="footer-menu list-inline">
                <li class="footer-menu-item">
                    <a href="<?php echo esc_url(streamit_get_permalink('movie')); ?>" class="menu-link">
                        <span class="menu-icon"><?php echo st_get_icon('movie'); ?></span>
                        <span class="menu-label"><?php esc_html_e('فیلم ها', 'streamit'); ?></span>
                    </a>
                </li>
                <li class="footer-menu-item">
                    <a href="<?php echo esc_url(streamit_get_permalink('video')); ?>" class="menu-link">
                        <span class="menu-icon"><?php echo st_get_icon('video'); ?></span>
                        <span class="menu-label"><?php esc_html_e('ویدیو ها', 'streamit'); ?></span>
                    </a>
                </li>
                <li class="footer-menu-item">
                    <a href="<?php echo esc_url(home_url('?s=&ajax_search=true')); ?>" class="menu-link">
                        <span class="menu-icon"><?php echo st_get_icon('search_normal'); ?></span>
                        <span class="menu-label"><?php esc_html_e('جستجو', 'streamit'); ?></span>
                    </a>
                </li>
                <li class="footer-menu-item">
                    <a href="<?php echo esc_url(streamit_get_permalink('tvshow')); ?>" class="menu-link">
                        <span class="menu-icon"><?php echo st_get_icon('tvshow'); ?></span>
                        <span class="menu-label"><?php esc_html_e('سریال ها', 'streamit'); ?></span>
                    </a>
                </li>
                <?php if (is_user_logged_in()): ?>
                    <li class="footer-menu-item">
                        <a href="<?php echo esc_url(streamit_get_permalink('profile')); ?>" class="menu-link">
                            <span class="menu-icon"><?php echo st_get_icon('user'); ?></span>
                            <span class="menu-label"><?php echo esc_html($current_user->display_name); ?></span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="footer-menu-item">
                        <a href="<?php echo esc_url(wp_login_url()); ?>" class="menu-link">
                            <span class="menu-icon"><?php echo st_get_icon('user'); ?></span>
                            <span class="menu-label"><?php esc_html_e('ورود', 'streamit'); ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <?php if (!empty($item['children'])): ?>
                <div class="footer-submenu">
                    <ul>
                        <?php foreach ($item['children'] as $child): ?>
                            <li><a href="<?php echo esc_url($child['url']); ?>"><?php echo esc_html($child['label']); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php } ?>
    </div>

    <!-- Right Arrow -->
    <!-- <button class="right flex-shrink-0" aria-label="<?php esc_attr_e('Scroll right', 'streamit'); ?>"><i class="icon-arrow-right"></i></button> -->
</div>
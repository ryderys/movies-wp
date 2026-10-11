<?php
/**
 * Streamit Child: Profile Tabs Customization.
 *
 * Removes Rental ("دسته بندی اجاره ای ها") and Pay-Per-View ("پرداخت به ازای هر بازدید")
 * sections from user profile tabs, templates, and URL endpoints.
 *
 * @package Streamit_Child
 */

defined('ABSPATH') || exit;

/**
 * Filter available user profile tabs to remove PPV and Rentals (Unlocked Content).
 *
 * @param array $tabs Associative array of [ tab_slug => tab_label ].
 * @return array Modified tabs array without 'unlocked_content' and 'ppv'.
 */
function streamit_child_remove_rental_and_ppv_tabs($tabs) {
    if (is_array($tabs)) {
        unset($tabs['unlocked_content'], $tabs['ppv']);
    }
    return $tabs;
}
add_filter('streamit_user_tabs', 'streamit_child_remove_rental_and_ppv_tabs', PHP_INT_MAX);
add_filter('st_user_profile_tabs', 'streamit_child_remove_rental_and_ppv_tabs', PHP_INT_MAX);

/**
 * Redirect any direct URL requests to the disabled tabs (/profile/ppv/ or /profile/unlocked_content/)
 * back to the user profile base URL.
 */
function streamit_child_redirect_disabled_profile_tabs() {
    $profile_tab = get_query_var('profile_tab', '');
    if (in_array(sanitize_title($profile_tab), ['ppv', 'unlocked_content'], true)) {
        $profile_url = function_exists('streamit_get_permalink') ? streamit_get_permalink('profile') : '';
        if (empty($profile_url)) {
            $profile_url = home_url('/profile/');
        }
        wp_safe_redirect(trailingslashit($profile_url));
        exit;
    }
}
add_action('template_redirect', 'streamit_child_redirect_disabled_profile_tabs', 5);

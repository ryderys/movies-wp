<?php

/**
 * The template for displaying single pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$login_link = esc_url(streamit_login_page_url());
if ( ! is_user_logged_in() ) {
    ?>
    <div style="text-align: center; margin: 50px 0;">
        <p style="font-size: 18px; margin-bottom: 20px;">
            <?php esc_html_e( 'You must be logged in to see your profile.', 'streamit' ); ?>
        </p>
        <a href="<?php echo $login_link; ?>" class="btn btn-primary">
            <?php esc_html_e( 'Login', 'streamit' ); ?>
        </a>
    </div>
    <?php
    return;
}

// Get the requested tab from URL (default to device_limit if available, otherwise playlist)
$default_tab = function_exists('pmpro_getMembershipLevelsForUser') ? 'device_limit' : 'playlist';
$profile_tab = get_query_var('profile_tab', $default_tab);
if ($profile_tab == 'profile') {
    $profile_tab = $default_tab;
}
// Define user details FIRST - before using it anywhere
$user_details = [
    'id'           => $current_user->ID,
    'user'         => $current_user,
    'user_avatar'  => get_avatar_url($current_user->ID, ['size' => 96]),
    'first_name'   => get_user_meta($current_user->ID, 'first_name', true),
    'last_name'    => get_user_meta($current_user->ID, 'last_name', true),
    'display_name' => $current_user->display_name,
    'current_user_name' => $current_user->user_nicename,
    'user_email'   => $current_user->user_email,
    'profile_url'  => streamit_get_permalink('profile'),
    'profile_tab'  => $profile_tab,
];

$user_details = apply_filters('st_profile_user_deatails', $user_details, $profile_tab);
?>

<div class="container-fluid">
    <div class="profile-page">
        <?php streamit_get_template('profile/single/user_details.php', ['user_details' => $user_details]); ?>
        <?php streamit_get_template('profile/single/user_tabs.php', ['user_details' => $user_details]); ?>
    </div>
</div>
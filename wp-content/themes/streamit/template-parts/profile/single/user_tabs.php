<?php

/**
 * Template for displaying Streamit user profile tabs.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Streamit
 */

defined('ABSPATH') || exit;

// Ensure $user_details is defined.
$user_details = wp_parse_args($user_details ?? [], [
    'profile_url' => '',
    'profile_tab' => '',
]);

// Retrieve available tabs from plugin.
$tabs = function_exists('streamit_get_user_tabs') ? streamit_get_user_tabs() : [];

// Fallback check: ensure we have tabs.
if (empty($tabs) || !is_array($tabs)) {
    return;
}

// Determine default tab.
$default_tab = isset($tabs['device_limit']) ? 'device_limit' : array_key_first($tabs);
$active_tab  = empty($user_details['profile_tab']) ? $default_tab : $user_details['profile_tab'];
?>

<div class="row mt-5">
    <div class="col-xl-2 col-lg-3">
        <ul class="profile-page-list list-inline p-0 mx-0">
            <?php foreach ($tabs as $tab_slug => $tab_label) : ?>
                <?php
                $tab_url   = trailingslashit($user_details['profile_url']) . $tab_slug;
                $is_active = ($active_tab === $tab_slug) ? 'active' : '';
                ?>
                <li class="profile-page-list-item">
                    <a href="<?php echo esc_url($tab_url); ?>"
                        class="profile-page-list-link <?php echo esc_attr($is_active); ?>">
                        <?php echo wp_kses_post($tab_label); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="col-xl-10 col-lg-9 mt-5 mt-lg-0">
        <?php
        $template_name = sprintf('profile/single/user_%s.php', sanitize_title($active_tab));
        streamit_get_template($template_name, ['user_details' => $user_details]);
        ?>
    </div>
</div>
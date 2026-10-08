<?php

/**
 * The template for displaying TV show genre pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $streamit_options;

$post = isset($st_data) ? $st_data : null;
$post_type = 'tvshow';
$post_id = $post ? $post->get_id() : get_the_ID();
$user_id = get_current_user_id();

$access_type = $post->get_meta('_access_type') ?? '';
$ppv_price = $post->get_meta('_ppv_price') ?? 0;
$discount = $post->get_meta('_ppv_discount') ?? 0;
$pmp_levels = $post->get_meta('_pmp_level') ?? [];

if (!$access_type && !empty($pmp_levels)) {
    $access_type = 'plan';
}

$has_access = function_exists('streamit_user_has_stream_access') ? streamit_user_has_stream_access($post_id, $post_type, $user_id) : false;

// If access is free or user has access, show play button
if ($access_type === 'free' || $has_access) {
    $first_season = $post->get_meta('_seasons')[0] ?? null;
    $first_episode = $first_season['episodes'][0] ?? null;
    if ($first_episode) {
        $episode = function_exists('streamit_get_episode') ? streamit_get_episode((int) $first_episode) : null;
        if (!empty($episode)) {
            $redirect_link = function_exists('streamit_get_permalink') ? streamit_get_permalink($episode->get_post_type(), $episode->get_post_name()) : '#';
            $icon_class = 'play';
            $button_text = esc_html__('شروع تماشا', 'streamit');
        } else {
            $redirect_link = home_url();
            $icon_class = 'error';
            $button_text = esc_html__('Content Unavailable', 'streamit');
        }
    } else {
        $redirect_link = home_url();
        $icon_class = 'error';
        $button_text = esc_html__('Content Unavailable', 'streamit');
    }
?>
    <div class="play-button-wrapper">
        <a class="btn btn-primary" href="<?php echo esc_url($redirect_link); ?>">
            <span class="d-flex align-items-center justify-content-center gap-2">
                <?php echo st_get_icon($icon_class); ?>
                <span><?php echo esc_html($button_text); ?></span>
            </span>
        </a>
    </div>
<?php
    return;
}

// User is logged in but does not have access, show the subscription option.
$subscribe_label = esc_html__('Subscribe to Watch', 'streamit');
$subscribe_icon = 'premium';
$subscribe_url = function_exists('streamit_subscribe_page_url') ? streamit_subscribe_page_url() : '#';
?>

<div class="play-button-wrapper">
    <a class="btn btn-primary" href="<?php echo esc_url($subscribe_url); ?>">
        <span class="d-flex align-items-center justify-content-center gap-2">
            <span><?php echo st_get_icon($subscribe_icon); ?></span>
            <span><?php echo $subscribe_label; ?></span>
        </span>
    </a>
</div>
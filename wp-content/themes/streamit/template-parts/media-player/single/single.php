<?php

/**
 * The main template file for media player.
 *
 * This is a generic template file in a WordPress theme 
 * used to display the media player.
 *
 * @package streamit
 */

defined('ABSPATH') || exit;

if (empty($content_data)) {
    echo '<p class="no-data-found">' . esc_html__('Post not found.', 'streamit') . '</p>';
    return;
}

$post_type        = $content_data->get_post_type();
$post_id          = $content_data->get_id();
$post_slug        = $content_data->get_post_name();
$post_url         = streamit_get_permalink($post_type, $post_slug);

$thumbnai_image_id  = $content_data->get_meta('thumbnail_id');
$post_image              = !empty($thumbnai_image_id) ? wp_get_attachment_image_url($thumbnai_image_id, "full") : '';

$st_premium_lvl   = maybe_unserialize($content_data->get_meta('_pmp_level'));
$has_access = function_exists('streamit_user_has_stream_access') ? streamit_user_has_stream_access($post_id, $post_type, get_current_user_id()) : false;


$main_class = $has_access ? 'css_prefix-video-player' : 'css_prefix-restricted-content';

$content_choice   = $content_data->get_meta('_' . $post_type . '_choice');
$media_url        = '';

if ($content_choice === $post_type . '_url') {
    $media_url = $content_data->get_meta('_' . $post_type . '_url_link');
} elseif ($content_choice === $post_type . '_file' || empty($content_choice)) {
    $media_url_id = (int) $content_data->get_meta('_' . $post_type . '_attachment_id');
    $media_url    = $media_url_id ? wp_get_attachment_url($media_url_id) : '';
}

$is_hls = (!empty($media_url) && strpos($media_url, '.m3u8') !== false);

$ad_config = [];
$html_ads  = [];

if (class_exists('Live_Streaming') && function_exists('ls_get_ad_configuration')) {
    $ad_config = ls_get_ad_configuration($content_data, false);
    $html_ads  = [
        'preroll'  => $ad_config['pre_roll_ads_list']  ?? [],
        'midroll'  => $ad_config['mid_roll_ads_list']  ?? [],
        'postroll' => $ad_config['post_roll_ads_list'] ?? [],
    ];
}

$device_limit_over = true;

if (function_exists('streamit_get_user_devices_with_stats')) {
    $current_user_id = get_current_user_id();
    $device_stats = streamit_get_user_devices_with_stats($current_user_id);
    $stats = $device_stats['stats'] ?? [];

    // Check if device limit is exceeded
    $remaining_slots = $stats['remaining_slots'] ?? 0;
    $total_limit = $stats['total_limit'] ?? 0;
    $total_devices = $stats['total_devices'] ?? 0;

    // Show device limit template only if:
    // 1. total_limit is not 'unlimited' AND
    // 2. total_devices is greater than total_limit (exceeded the limit)
    if ($remaining_slots !== 'unlimited' && $total_devices > $total_limit) {
        $device_limit_over = false;
    }
}

// Content is Upcoming or not.
$is_upcoming_data = function_exists('streamit_is_upcoming') ? streamit_is_upcoming($content_data, $post_type) : [
    'is_upcoming' => false,
    'is_future_release' => false,
    'formatted_date' => ''
];
$is_upcoming = !$is_upcoming_data['is_future_release'] ?? false;

?>

<div class="<?php echo esc_attr($main_class); ?>" id="media-player-container">

    <!-- Hidden input for current user ID -->
    <input type="hidden" id="current-user-id" value="<?php echo esc_attr($current_user_id); ?>">
    <div class="back-btn">
        <a class="btn btn-link text-white text-decoration-none" href="<?php echo esc_url($post_url); ?>">
            <?php echo st_get_icon('cross'); ?>
        </a>
    </div>
    <?php 
    if ($is_upcoming || current_user_can('administrator')) : ?>
        <?php if ($has_access):
            if ($device_limit_over) : ?>

                <?php if ($is_hls) : ?>

                    <?php
                    do_action('ls_call_plyr_script');
                    ?>

                    <div class="ls-video-container">
                        <video
                            id="ls_video_player"
                            class="plyr-js"
                            playsinline
                            controls
                            data-hls-url="<?php echo esc_url($media_url); ?>"
                            data-is-live="false"
                            <?php if (!empty($ad_config) && $ad_config['ads_enabled']) : ?>
                            data-ads-type="<?php echo esc_attr($ad_config['ads_type']); ?>"
                            data-ad-frequency="<?php echo intval($ad_config['mid_roll_interval']); ?>"
                            data-enabled="<?php echo $ad_config['ads_enabled'] ? 'true' : 'false'; ?>"
                            data-prerollEnabled="<?php echo $ad_config['pre_roll_display']  ? 'true' : 'false'; ?>"
                            data-midrollEnabled="<?php echo $ad_config['mid_roll_display']  ? 'true' : 'false'; ?>"
                            data-postrollEnabled="<?php echo $ad_config['post_roll_display'] ? 'true' : 'false'; ?>"
                            data-html-ads='<?php echo esc_attr(wp_json_encode($html_ads)); ?>'
                            <?php endif; ?>>
                            <source src="<?php echo esc_url($media_url); ?>" type="application/x-mpegURL">
                            <?php echo esc_html__('Your browser does not support HLS video.', 'live-streaming'); ?>
                        </video>
                    </div>

                <?php else : ?>

                    <?php
                    $controllers     = streamit_media_player_controls();
                    $continue_watch  = streamit_get_continue_watching(get_current_user_id(), $post_type);
                    $watched_time    = $continue_watch[$post_id]['watched_time'] ?? '';
                    $sources         = $content_data->get_meta('_source');

                    $content = '';
                    switch ($content_choice) {
                        case $post_type . '_embed':
                            $content = streamit_render_video_iframe($post_type, $content_data);
                            break;
                        case $post_type . '_url':
                            $content = streamit_render_url_video_player($post_type, $content_data);
                            break;
                        default:
                            $content = streamit_render_attach_video_player($post_type, $content_data);
                            break;
                    }
                    $processed_sources = [];

                    if (!empty($sources) && is_array($sources)) {
                        !empty($content) && $processed_sources[] = ['name' => __('Default', 'streamit'), 'content' => $content, 'quality' => '', 'language' => ''];
                        foreach ($sources as $s) {
                            if (!empty($s['name']) && !empty($s['link']))
                                $processed_sources[] = ['name' => $s['name'], 'content' => streamit_get_url_video_html($s['link']), 'quality' => $s['quality'] ?? '', 'language' => $s['language'] ?? ''];
                        }
                    }

                    if (!empty($processed_sources)) {
                        if (!in_array('sources', $controllers['controls'], true)) {
                            $controllers['controls'][] = 'sources';
                            $controllers['i18n']['sources'] = 'Sources';
                            $controllers['i18n']['sourceIcon'] = '<svg id="Layer_1" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" data-name="Layer 1">
                            <path d="m3 8h10a1 1 0 0 0 2 0v-2a1 1 0 0 0 -2 0h-10a1 1 0 0 0 0 2z"/><path d="m17 8h4a1 1 0 0 0 0-2h-4a1 1 0 0 0 0 2z"/>
                            <path d="m21 16h-7a1 1 0 0 0 0 2h7a1 1 0 0 0 0-2z"/><path d="m11 15a1 1 0 0 0 -1 1h-7a1 1 0 0 0 0 2h7a1 1 0 0 0 2 0v-2a1 1 0 0 0 -1-1z"/>
                            <path d="m21 11h-11a1 1 0 0 0 0 2h11a1 1 0 0 0 0-2z"/><path d="m3 13h3a1 1 0 0 0 2 0v-2a1 1 0 0 0 -2 0h-3a1 1 0 0 0 0 2z"/></svg>';
                        }
                    }

                    ob_start(); ?>

                    <div class="streamit-player-ctrl"
                        data-current_time="<?php echo esc_attr($watched_time ?? 0); ?>"
                        data-post_id="<?php echo esc_attr($post_id ?? 0); ?>"
                        data-post_type="<?php echo esc_attr($post_type ?? ''); ?>"
                        data-user_id="<?php echo esc_attr(get_current_user_id() ?? 0); ?>"
                        data-sources="<?php echo esc_attr(wp_json_encode($processed_sources ?? [])); ?>"
                        data-player_controls="<?php echo esc_attr(wp_json_encode($controllers ?? [])); ?>"
                        data-ads-type="<?php echo esc_attr($ad_config['ads_type'] ?? ''); ?>"
                        data-is-live="false"
                        data-vast-url="<?php echo esc_url($ad_config['vast_url'] ?? ''); ?>"
                        data-ad-frequency="<?php echo intval($ad_config['mid_roll_interval'] ?? 0); ?>"
                        data-enabled="<?php echo !empty($ad_config['ads_enabled']) ? 'true' : 'false'; ?>"
                        data-prerollEnabled="<?php echo !empty($ad_config['pre_roll_display']) ? 'true' : 'false'; ?>"
                        data-midrollEnabled="<?php echo !empty($ad_config['mid_roll_display']) ? 'true' : 'false'; ?>"
                        data-postrollEnabled="<?php echo !empty($ad_config['post_roll_display']) ? 'true' : 'false'; ?>"
                        data-html-ads='<?php echo esc_attr(wp_json_encode($html_ads)); ?>'>
                        <?php echo $content ?? ''; ?>
                    </div>

                    <?php
                    $video_player_html = ob_get_clean();
                    echo apply_filters('streamit_media_player_html', $video_player_html);
                    ?>

                <?php endif; ?>

            <?php else:
                // Pass required data to device limit templat
                streamit_get_template('common/html-device-limit-player-template.php', [
                    'post_image' => $post_image,
                    'total_limit' => $total_limit,
                    'total_devices' => $total_devices,
                    'post_url' => $post_url
                ]);
            endif; ?>

        <?php else: ?>
            <div class="restricted-block">
                <?php echo streamit_display_restricted_content($content_data, $post_type); ?>
            </div>
        <?php endif; ?>
    <?php else :
        streamit_get_template('common/html-upcoming-content.php', ['st_data' => $content_data]);
    endif; ?>
</div>

<?php

streamit_get_template('common/html-logout-user.php');

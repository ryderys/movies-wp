<?php

/**
 * The template for displaying a single episode player.
 *
 * This template is modified to conditionally render a player compatible with
 * the live-streaming plugin for HLS (.m3u8) streams, while preserving the
 * theme's essential HTML structure to prevent layout and content issues.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package streamit
 */

defined('ABSPATH') || exit;

$tvshow = streamit_get_tvshow((int)$st_data->get_meta('tvshow_id'));
$tvshow_id = $tvshow ? (int)$tvshow->get_id() : 0;

if ( !empty($tvshow) && function_exists('streamit_user_has_stream_access') && !streamit_user_has_stream_access($tvshow_id, 'tvshow', get_current_user_id())) {
    echo '<div class="restricted-block-episode">' . streamit_display_restricted_content($tvshow, 'tvshow') . '</div>';
    return;
}

$content_type = $st_data->get_meta('_episode_choice');
$media_url = '';

switch ($content_type) {
    case 'episode_url':
        $media_url = $st_data->get_meta('_episode_url_link');
        break;
    case 'episode_file':
    default:
        $media_url_id = (int) $st_data->get_meta('_episode_attachment_id');
        if ($media_url_id) {
            $media_url = wp_get_attachment_url($media_url_id);
        }
        break;
}

$is_hls = (!empty($media_url) && strpos($media_url, '.m3u8') !== false);

$content = '';
if ($is_hls) {
    $content = 'is_hls_placeholder';
} else {
    switch ($content_type) {
        case 'episode_embed':
            $content = streamit_render_video_iframe('episode', $st_data);
            break;
        case 'episode_url':
            $content = streamit_render_url_video_player('episode', $st_data);
            break;
        case 'episode_file':
        default:
            $content = streamit_render_attach_video_player('episode', $st_data);
            break;
    }
}

if ($content) :
    $continue_watch = streamit_get_continue_watching(get_current_user_id(), $st_data->get_post_type());
    $watched_time   = $continue_watch[$st_data->get_id()]['watched_time'] ?? '';
    $post_type      = $st_data->get_post_type();
    $controllers    = streamit_media_player_controls();
    $user_id        = get_current_user_id();
    $post_id        = $st_data->get_id();
    $current_season = $st_data->get_meta('current_season'); // Assuming this is the correct way to fetch the current season.

    // Ad configuration (loaded once, used by both player types)
    $ad_config = [];
    $html_ads  = [];

    if (class_exists('Live_Streaming') && function_exists('ls_get_ad_configuration') && !empty($tvshow)) {
        $ad_config = ls_get_ad_configuration($tvshow, false);
        $html_ads = [
            'preroll'  => $ad_config['pre_roll_ads_list']  ?? [],
            'midroll'  => $ad_config['mid_roll_ads_list']  ?? [],
            'postroll' => $ad_config['post_roll_ads_list'] ?? [],
        ];
    }
    // Add to where you output the player container attributes
    $next_overlay_time = $st_data->get_meta('_next_episode_overlay');

    ob_start();
    ?>

    <div class="streamit-player-ctrl"
        data-current_time="<?php echo esc_attr($watched_time); ?>"
        data-post_id="<?php echo esc_attr($post_id); ?>"
        data-post_type="<?php echo esc_attr($post_type); ?>"
        data-user_id="<?php echo esc_attr($user_id); ?>"
        data-player_controls='<?php echo esc_attr(wp_json_encode($controllers)); ?>'
        data-autoplay="true"
        data-autoplay-countdown="10"
        data-next_overlay_time="<?php echo esc_attr($next_overlay_time); ?>"

        <?php if (!$is_hls && !empty($ad_config) && $ad_config['ads_enabled']) : ?>
        data-ads-type="<?php echo esc_attr($ad_config['ads_type'] ?? ''); ?>"
        data-is-live="false"
        data-vast-url="<?php echo esc_url($ad_config['vast_url'] ?? ''); ?>"
        data-ad-frequency="<?php echo intval($ad_config['mid_roll_interval'] ?? 0); ?>"
        data-enabled="<?php echo !empty($ad_config['ads_enabled']) ? 'true' : 'false'; ?>"
        data-prerollEnabled="<?php echo !empty($ad_config['pre_roll_display']) ? 'true' : 'false'; ?>"
        data-midrollEnabled="<?php echo !empty($ad_config['mid_roll_display']) ? 'true' : 'false'; ?>"
        data-postrollEnabled="<?php echo !empty($ad_config['post_roll_display']) ? 'true' : 'false'; ?>"
        data-html-ads='<?php echo esc_attr(wp_json_encode($html_ads)); ?>'
        <?php endif; ?>>

        <?php if ($is_hls) : ?>

            <?php
            do_action('ls_call_plyr_script');
            ?>

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
            </video>

            <?php else : ?>
                <?php
                echo $content;
                ?>
            <?php endif; ?>

        </div>
        <?php
        $video_player_html = ob_get_clean();
        echo apply_filters('streamit_episode_player_html', $video_player_html);
    endif;
?>
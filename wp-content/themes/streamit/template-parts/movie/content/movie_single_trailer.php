<?php

/**
 * The template for displaying trailer
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$thumbnail = !empty($st_data->get_meta('thumbnail_id')) ? wp_get_attachment_image_url($st_data->get_meta('thumbnail_id'), 'full') : streamit_placeholder_image();
$content = '';
?>

<div class="video-plus-image">
    <div class="video-banner-image-container">
        <img src="<?php echo esc_url($thumbnail); ?>" class="video-banner-image" alt="video-section-banner-image">
    </div>

    <?php
    $post_type = $st_data->get_post_type();
    $controllers = streamit_media_player_controls();
    $controllers['autoplay'] = true;
    $controllers['muted'] = true; // Default muted
    $controllers['fullscreen'] = ['enabled' => false];
    $controllers['tooltips'] = ['controls' => false, 'seek' => false];
    $controllers['loop'] = ['active' => true];
    $st_trailer_details = method_exists($st_data, 'get_meta') && ($st_data->get_meta('_name_trailer_link') !== null) ? $st_data->get_meta('_name_trailer_link') : '';
    $st_trailer_details = streamit_get_trailer_embed($st_trailer_details);
    $is_video = !empty($st_trailer_details['type']) && ($st_trailer_details['type'] == 'video') ? true : false;
    if (!empty($st_trailer_details['trailer_link'])) :
    ?>
        <div id="streamit-trailer-player-ctrl" class="streamit-trailer-player-ctrl" data-player_controls="<?php echo esc_attr(wp_json_encode(apply_filters('streamit_movie_trailer_player_controls', $controllers))); ?>">
            <?php if ($is_video): ?>
                <video class="streamit_trailer_player" autoplay muted playsinline loop preload="auto" 
  webkit-playsinline>
                    <source src="<?php echo esc_url($st_trailer_details['trailer_link']); ?>" type="video/mp4">
                </video>
            <?php endif; ?>
            <?php if (!$is_video): ?>
                <div class="streamit_trailer_player">
                    <iframe id="trailerIframe" src="<?php echo esc_url($st_trailer_details['trailer_link']); ?>" title="Video player" allowfullscreen allow="autoplay" loading="lazy"></iframe>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mute/Unmute Button -->
        <button id="trailer-mute-toggle" class="streamit-trailer-mute-btn" style="display:none; ">
            <i class="icon-volume-slash"></i>
        </button>

        <!-- Fullscreen Toggle Button -->
        <button id="trailer-fullscreen-toggle" class="streamit-trailer-fullscreen-btn" style="display:none;" >
            <i class="icon-fullscreen"></i>
        </button>

    <?php endif; ?>
</div>
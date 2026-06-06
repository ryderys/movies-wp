<?php

/**
 * The template for displaying recommended videos
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $streamit_options;
global $streamit_core_options;

// Check if the option to display related videos is enabled
if (!isset($streamit_core_options['streamit_display_related_video']) || $streamit_core_options['streamit_display_related_video'] !== 'yes') {
    return; // Exit if the feature is disabled
}

$recomended_video_ids = $st_data->get_meta('linked_related_video_ids');

if (empty($recomended_video_ids)) return;

$videos_list = streamit_get_videos(['per_page' => -1, 'include' => $recomended_video_ids]);

if (!isset($videos_list->results) || empty($videos_list->results)) return;

$slick_args = apply_filters('streamit_recomended_video_slider_controls', array(
    'dots'          => false,
    'slidesToShow'  => 6,
    'slidesToScroll' => 6,
    'arrows'        => true,
    'autoplay'      => false,
    'autoplaySpeed' => 2000,
    'speed'         => 300,
    'infinite'      => true,
    'swipeToSlide'  => true,
    'responsive'    => array(
        array(
            'breakpoint'    => 1025,
            'settings'  => array(
                'slidesToShow' => 4,
                'slidesToScroll' => 4,
            )
        ),
        array(
            'breakpoint'    => 600,
            'settings'  => array(
                'slidesToShow' => 3,
                'slidesToScroll' => 3,
            )
        ),
        array(
            'breakpoint'    => 450,
            'settings'  => array(
                'slidesToShow' => 2,
                'slidesToScroll' => 2,
            )
        )
    )
));

$settings = [
    'title_tag'         => 'h5',
    'slider_title'      => esc_html__('Related Videos', 'streamit'),
    'view_all_switch'   => 'no',
    'nav-arrow'         => 'true',
    'enable_premium_badges' => 'yes',
    'play_now_text'     => esc_html__('تماشا', 'streamit'),
];
$title_tag = 'h5';
$slider_title = isset($streamit_core_options['streamit_display_related_video_title']) && !empty($streamit_core_options['streamit_display_related_video_title'])
    ? esc_html($streamit_core_options['streamit_display_related_video_title'])
    : esc_html__('Recommended Videos', 'streamit');

$enable_premium_badges = ($streamit_options['streamit_recommended_enable_premium_badges'] === 'yes') ? 'yes' : 'no';
?>

<div class="more-like-section">
    <div class="single_page_slick">
        <?php
        streamit_get_template(
            'elementor-widget/main-card-slider/html-main-card-slider.php',
            [
                'slick_settings'    => $slick_args,
                'post_data'         => $videos_list->results,
                'settings'          => $settings,
                'title_tag'         => $title_tag,
                'slider_title'      => $slider_title,
                'enable_premium_badges' => $enable_premium_badges
            ]
        );
        ?>
    </div>
</div>
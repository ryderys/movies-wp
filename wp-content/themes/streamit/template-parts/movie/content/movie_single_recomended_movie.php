<?php

/**
 * The template for displaying recomended movies
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
if (!isset($streamit_core_options['streamit_display_related_movie']) || $streamit_core_options['streamit_display_related_movie'] !== 'yes') {
    return; // Exit if the feature is disabled
}

$recomended_movie_ids = $st_data->get_meta('linked_recommended_movie_ids');

if (empty($recomended_movie_ids)) return;

$movies_list = streamit_get_movies(['per_page' => -1, 'include' => $recomended_movie_ids]);

if (!isset($movies_list->results) || empty($movies_list->results)) return;

$slick_args =  apply_filters('streamit_recomended_movie_slider_controls', array(
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
    'view_all_switch'   =>  'no',
    'nav-arrow'         =>  'true',
    'enable_premium_badges' =>  'yes',
    'play_now_text'     =>  esc_html__('Play Now', 'streamit')
];
$title_tag = 'h5';
$slider_title = isset($streamit_core_options['streamit_display_related_movie_title']) && !empty($streamit_core_options['streamit_display_related_movie_title'])
    ? esc_html($streamit_core_options['streamit_display_related_movie_title'])
    : esc_html__('Recommended Movies', 'streamit');

$enable_premium_badges = ($streamit_options['streamit_recommended_enable_premium_badges'] === 'yes') ? 'yes' : 'no';
?>

<div class="more-like-section ">
    <div class="single_page_slick">
        <?php
        streamit_get_template(
            'elementor-widget/main-card-slider/html-main-card-slider.php',
            [
                'slick_settings'    => $slick_args,
                'post_data'         => $movies_list->results,
                'settings'          => $settings,
                'title_tag'         => $title_tag,
                'slider_title'      => $slider_title,
                'enable_premium_badges' => $enable_premium_badges
            ]
        );
        ?>
    </div>
</div>
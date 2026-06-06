<?php

/**
 * The template for displaying morelike slider
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

// Check if the option to display More Like TVshow videos is enabled
if (!isset($streamit_core_options['streamit_display_recommended']) || $streamit_core_options['streamit_display_recommended'] !== 'yes') {
    return; // Exit if the feature is disabled
}

$post_id    = method_exists($st_data, 'get_id') ? $st_data->get_id() : '';
$term_ids       = streamit_get_term_relationships($post_id, 'tvshow_genre');
if (empty($term_ids)) return;

$query          = ['per_page' => 10];
$tax_query = array('relation' => 'OR');
foreach ($term_ids as $term_id) {
    $tax_query[] = array(
        'field'    => 'term_id',
        'terms'    => $term_id,
        'operator' => '=',
    );
}
$query['tax_query']  = $tax_query;
$query['exclude'] = [$st_data->get_id()];
$more_like_tvshow   = streamit_get_tvshows($query)->results;
if (empty($more_like_tvshow)) return;
$slick_args =  apply_filters('streamit_morelike_slider_controls', array(
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
    'nav-arrow' =>  'true',
    'enable_premium_badges' =>  'yes',
    'play_now_text' =>  'تماشا'
];
$title_tag = 'h5';
$slider_title = isset($streamit_core_options['streamit_recommended_title']) && !empty($streamit_core_options['streamit_recommended_title'])
    ? esc_html($streamit_core_options['streamit_recommended_title'])
    : esc_html__('More Like this', 'streamit');

$enable_premium_badges = ($streamit_options['streamit_recommended_enable_premium_badges'] === 'yes') ? 'yes' : 'no';
?>

<div class="more-like-section ">
    <div class="single_page_slick">
        <?php
        streamit_get_template(
            'elementor-widget/main-card-slider/html-main-card-slider.php',
            [
                'slick_settings'    => $slick_args,
                'post_data'         => $more_like_tvshow,
                'settings'          => $settings,
                'title_tag'         => $title_tag,
                'slider_title'      => $slider_title,
                'enable_premium_badges' => $enable_premium_badges
            ]
        );
        ?>
    </div>
</div>
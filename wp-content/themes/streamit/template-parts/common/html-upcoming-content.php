<?php

/**
 * The template for displaying upcoming content (season/episode) with centered design
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
$post_type = $st_data->get_post_type();
$upcoming_data = function_exists('streamit_is_upcoming') ? streamit_is_upcoming($st_data, $post_type) : [
    'is_upcoming' => false,
    'is_future_release' => false,
    'formatted_date' => ''
];
$formatted_js_date = '';
$release_datetime = $st_data->get_meta($post_type . '_upcoming_datetime');
if (!empty($release_datetime)) {
    $timestamp = strtotime($release_datetime);
    $formatted_js_date = date('Y/m/d H:i:s', $timestamp); // <-- JS compatible
}
$thumb_id  = $st_data->get_meta('thumbnail_id');
$thumb_url = $thumb_id ? wp_get_attachment_url($thumb_id) : '';
?>

<div class="upcoming-image-box" style="<?php echo $thumb_url ? 'background-image: url(' . esc_url($thumb_url) . ');' : ''; ?>">
    <div class="container mt-5">
        <div class="text-center upcoming-content-data">
            <?php if (!empty($st_data->get_post_title())) : ?>
                <h3 class="text-uppercase mt-1 mb-2 texture-text fw-bold">
                    <?php echo esc_html($st_data->get_post_title()) ?>
                </h3>
            <?php endif; ?>
            <h3 class="mt-1 mb-2 texture-text fw-bold">
                <?php echo esc_html__('Coming Soon', 'streamit'); ?>
            </h3>
            <?php
            if ($upcoming_data['is_future_release'] && !empty($upcoming_data['formatted_date'])) : ?>
                <div class="release-banner">
                    <div class="release-date"> <?php echo esc_html($upcoming_data['formatted_date']) ?></div>
                </div>
            <?php
            endif;
            ?>

            <div class="upcoming-data-container countdown"
                data-date="<?php echo esc_attr($formatted_js_date); ?>"
                data-labels="true"
                data-format="DD:HH:MM:SS">
                <div class="time-unit">
                    <span class="time-value" id="days"><?php echo esc_html__('00', 'streamit'); ?></span>
                    <span class="time-label"><?php echo esc_html__('Days', 'streamit'); ?></span>
                </div>
                <div class="time-unit">
                    <span class="time-value" id="hours"><?php echo esc_html__('00', 'streamit'); ?></span>
                    <span class="time-label"><?php echo esc_html__('Hours', 'streamit'); ?></span>
                </div>
                <div class="time-unit">
                    <span class="time-value" id="minutes"><?php echo esc_html__('00', 'streamit'); ?></span>
                    <span class="time-label"><?php echo esc_html__('Minutes', 'streamit'); ?></span>
                </div>
                <div class="time-unit">
                    <span class="time-value" id="seconds"><?php echo esc_html__('00', 'streamit'); ?></span>
                    <span class="time-label"><?php echo esc_html__('Seconds', 'streamit'); ?></span>
                </div>
            </div>

            <div class="cta-container">
                <?php echo do_shortcode('[streamit_notify_me_shortcode post_id="' . esc_attr($st_data->get_id()) . '" post_type="' . esc_attr($st_data->get_post_type()) . '" class="remind-me-big-btn"]'); ?>
            </div>
        </div>
    </div>
</div>
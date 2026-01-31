<?php
/**
 * The template for displaying upcoming season info
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check if this season is upcoming
$season_upcoming_data = function_exists('streamit_is_season_upcoming') ? streamit_is_season_upcoming($season) : ['is_future_release' => false];
$is_upcoming_season = $season_upcoming_data['is_future_release'];

if (!$is_upcoming_season) {
    return; // Don't show if not upcoming
}

// Get portrait image from st_data
$portrait_image_id = method_exists($st_data, 'get_meta') ? $st_data->get_meta('_portrait_thumbmail') : '';
$portrait_image_url = '';

if (!empty($portrait_image_id)) {
    $portrait_image_url = wp_get_attachment_image_url($portrait_image_id, 'full');
}
?>

<div class="upcoming-season-info">
    <!-- Season Poster/Image -->
    <div class="season-poster">
        <?php 
        if (!empty($portrait_image_url)) : ?>
            <img src="<?php echo esc_url($portrait_image_url); ?>" alt="<?php echo esc_attr($season['name']); ?>" class="season-poster-image">
        <?php else : ?>
            <div class="season-poster-content">
                <div style="font-size: 24px; margin-bottom: 10px;">🎬</div>
                <div><?php echo esc_html($season['name']); ?></div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Season Content -->
    <div class="season-content">
        <h4><?php echo esc_html($season['name']); ?></h4>
        
        <?php if (!empty($season_upcoming_data['formatted_date'])) : ?>
            <div class="release-date">
                <?php echo st_get_icon('calendar-2'); ?>
                <span><?php 
                    printf(
                        esc_html__('Released: %s', 'streamit'),
                        esc_html($season_upcoming_data['formatted_date'])
                    );
                ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($season['season_description'])) : ?>
            <div class="season-description">
                <p><?php echo esc_html($season['season_description']); ?></p>
            </div>
        <?php else : ?>
            <div class="season-description">
                <p><?php echo esc_html__('The final chapter of this incredible journey brings everything full circle. All mysteries will be revealed, all battles will be fought, and all destinies will be fulfilled in this epic conclusion.', 'streamit'); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="season-actions">
            <?php 
            // Use the passed season_index
            $season_index = isset($season_index) ? $season_index : 0;
            echo do_shortcode('[streamit_notify_me_shortcode post_id="' . esc_attr($st_data->get_id()) . '" post_type="' . esc_attr($st_data->get_post_type()) . '" season_id="' . esc_attr($season_index) . '"]'); 
            ?>
        </div>
    </div>
</div>

<?php

/**
 * The template for displaying after details
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<div class="section-spacing">
    <div class="container-fluid">
        <?php streamit_get_template('video/content/video_single_morelike_slider.php', ['st_data' => $st_data]);  ?>

        <?php streamit_get_template('video/content/video_single_related_products.php', ['st_data' => $st_data]); ?>

        <?php streamit_get_template('video/content/video_single_upcoming_video.php', ['st_data' => $st_data]);  ?>

        <?php 
        // Show comments only if content is available
        $status = function_exists('streamit_is_upcoming') ? streamit_is_upcoming($st_data, 'video') : [
            'is_upcoming' => false,
            'is_future_release' => false,
            'formatted_date' => ''
        ];
        $is_available = empty($status['is_upcoming']) || empty($status['is_future_release']);
        $is_admin = current_user_can('administrator');
        
        if ($is_admin || $is_available) {
            streamit_get_template('video/content/video_single_comments.php', ['st_data' => $st_data]);
        }
        ?>
    </div>
</div>


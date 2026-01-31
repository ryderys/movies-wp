<?php

/**
 * The template for displaying episode season card
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$episode_number = $st_data->get_meta('_episode_number');
$episode_run_time = $st_data->get_meta('_episode_run_time');
$episode_release_date = $st_data->get_meta('_episode_release_date');
$content = $st_data->get_post_excerpt();


$url = !empty(wp_get_attachment_image_url($st_data->get_meta('thumbnail_id'))) ? wp_get_attachment_image_url($st_data->get_meta('thumbnail_id'), 'full') : streamit_placeholder_image();
?>
<div class="episode-card">
    <div class="episode-image">
        <a href="<?php echo esc_url(streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name())); ?>" tabindex="0">
            <img src="<?php echo esc_html($url); ?>" alt="<?php $st_data->get_post_title(); ?>" class="img-fluid object-fit-cover position-relative">
        </a>
        <?php if (!empty($episode_run_time) && $episode_run_time !== '0:00') : ?>
            <span class="episode-time-duration badge d-inline-flex align-items-center gap-1">
                <?php echo st_get_icon('clock'); ?>
                <?php echo esc_html(st_format_runtime($episode_run_time)); ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="episode-detail">
        <h6 class="mt-2 mb-0"> <?php echo esc_html($episode_number); ?> : <?php echo $st_data->get_post_title(); ?></h6>
        <?php
        if (!empty($content)) {
            echo '<p class="mb-0 line-count-2 mt-2 lh-base">';
            $st_remove_tags = array("<p>", "</p>");
            $st_excerpt = str_replace($st_remove_tags, "", $content);
            echo __($st_excerpt);
            echo '</p> ';
        }
        ?>
    </div>
</div>
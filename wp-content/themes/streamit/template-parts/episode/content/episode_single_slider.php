<?php

/**
 * Template for displaying a single episode card in a share model.
 *
 * @package Streamit
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 */

defined('ABSPATH') || exit;

// Early sanity check
if (empty($episode) || !is_object($episode)) {
    return;
}

global $streamit_options;
$enable_upcoming_badges = ($streamit_options['streamit_recommended_enable_upcoming_badges'] === 'yes');

$played_episode_id = !empty($current_episode_id) ? (int) $current_episode_id : 0;
$is_currently_playing = ($played_episode_id && $played_episode_id === (int) $episode->get_id());
// Fetch required episode meta once
$episode_number   = $episode->get_meta('_episode_number');
$episode_run_time = st_format_runtime($episode->get_meta('_episode_run_time'));
$episode_title    = $episode->get_post_title();
$episode_slug     = $episode->get_post_name();
$episode_type     = $episode->get_post_type();

// Thumbnail
$thumbnail_id = $episode->get_meta('thumbnail_id');
$image_html = streamit_render_image([
    'attachment_id' => $thumbnail_id,
    'class'         => 'img-fluid object-fit-cover position-relative',
    'alt'           => esc_attr($episode_title),
    'decoding'      => 'async',
]);


// Upcoming status (cached in one call)
$episode_upcoming_data = function_exists('streamit_is_episode_upcoming')
    ? streamit_is_episode_upcoming($episode)
    : ['is_future_release' => false];
$is_upcoming_episode = !empty($episode_upcoming_data['is_future_release']);

// Episode link
$episode_link = esc_url(streamit_get_permalink($episode_type, $episode_slug));
$episode_release_date = $episode->get_meta('_episode_release_date');
?>

<div class="slick-item">
    <div class="episode-card <?php echo $is_currently_playing ? esc_attr('watching') : ''; ?>">
        <a href="<?php echo $episode_link; ?>" class="episode-overlay" tabindex="0"></a>

        <div class="episode-image">
            <?php echo $image_html; ?>

            <?php if ($is_upcoming_episode && $enable_upcoming_badges) : ?>
                <span class="episode-time-duration badge d-inline-flex align-items-center gap-1 episode-upcoming-badge">
                    <?php echo st_get_icon('clock'); ?>
                    <span><?php esc_html_e('Coming Soon', 'streamit'); ?></span>
                </span>
            <?php endif; ?>

            <div class="episode-detail">
                <div class="flex-grow-1">
                    <?php if (!empty($episode_number)) : ?>
                        <div class="d-flex align-items-center gap-1">
                            <span><?php echo esc_html($episode_number); ?></span>
                        </div>
                    <?php endif; ?>

                    <h5 class="mt-2 mb-0 line-count-1"><?php echo esc_html($episode_title); ?></h5>

                    <?php if (!$is_upcoming_episode) : ?>
                        <ul class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-0 p-0">
                            <?php if (!empty($episode_release_date)) : ?>
                                <li class="font-size-14"><?php echo esc_html(streamit_get_readable_release_date($episode_release_date)); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($episode_run_time) && $episode_run_time !== '0:00') : ?>
                                <li class="font-size-14">
                                    <span class="episode-time-duration badge d-inline-flex align-items-center gap-1">
                                        <?php echo esc_html($episode_run_time); ?>
                                    </span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>

                </div>

                <div class="flex-shrink-0">
                    <a href="<?php echo esc_url($episode_link); ?>"
                        class="btn btn-primary"
                        tabindex="0">
                        <?php esc_html_e('Watch Now', 'streamit'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
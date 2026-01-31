<?php

/**
 * The template for displaying a single video playlist.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $streamit_options;
global $streamit_core_options;
?>
<div class="playlist-detail-page">
    <div class="container-fluid">
        <?php
        if (! empty($content_data)) :
            // Check if the current user is the owner of the playlist
            if (get_current_user_id() == $content_data->get_user_id()) :
                // Fetch the video IDs from the playlist
                $playlist_id = $content_data->get_playlist_id();

                $videosids = streamit_get_playlist_item($playlist_id, 'video');

                if (! empty($videosids)) :
                    $display_video = isset($_GET['videoid']) && !empty($_GET['videoid']) ? $_GET['videoid'] : '';
                    $videos = streamit_get_videos(['per_page' => -1, 'include' => $videosids])->results;
                    $display_video_data = null;

                    // Default to the first video if none is selected
                    if (empty($display_video)) {
                        $display_video = $videos[0]->get_id();
                    }

        ?>
                    <div class="row gy-4 flex-column-reverse flex-lg-row-reverse">
                        <!-- Sidebar for video list -->
                        <div class="col-xxl-3 col-xl-4 col-lg-5">
                            <div class="card">
                                <div class="card-header pb-3 mb-3 border-bottom d-flex align-items-center justify-content-between gap-1">
                                    <h5 class="m-0"><?php echo esc_html($content_data->get_playlist_name()); ?></h5>
                                    <small>
                                        <?php
                                        // Display current video index and total count
                                        $current_video_index = array_search($display_video, array_map(function ($video) {
                                            return $video->get_id();
                                        }, $videos));

                                        if ($current_video_index !== false) {
                                            echo sprintf(esc_html__('%d/%d', 'streamit'), $current_video_index + 1, count($videos));
                                        } else {
                                            echo esc_html__('1/' . count($videos), 'streamit');
                                        }
                                        ?>
                                    </small>
                                </div>
                                <div class="card-body px-0 pt-0 pb-3">
                                    <div class="playlist-data">
                                        <?php foreach ($videos as $video) :
                                            // Get video details
                                            $thumbnail = !empty($video->get_meta('thumbnail_id')) ? wp_get_attachment_image_url($video->get_meta('thumbnail_id'), 'full') : streamit_placeholder_image();
                                            $title = $video->get_post_title();
                                            $view = $video->get_meta('post_views_count');
                                            $release_date = $video->get_meta('_video_release_date');
                                            $release_timestamp = $release_date ? strtotime($release_date) : null;
                                            $current_timestamp = current_time('timestamp');
                                            $time_diff = human_time_diff($release_timestamp, $current_timestamp);

                                            // Generate redirect URL for the video
                                            $encoded_playlist_id = urlencode(base64_encode('playlistid_' . $playlist_id));
                                            $redirect_url = streamit_get_permalink('video_playlist', $encoded_playlist_id);
                                            $redirect_url = add_query_arg('videoid', $video->get_id(), $redirect_url);

                                            // Set active class for the selected video

                                            $active_class = ($display_video == $video->get_id()) ? 'active' : '';
                                            if ($active_class === 'active') {
                                                $display_video_data = $video;
                                            }
                                        ?>
                                            <div class="playlist-data-card <?php echo esc_attr($active_class); ?>">
                                                <div class="playlist-data-card-image">
                                                    <a href="<?php echo esc_url($redirect_url); ?>">
                                                        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($title); ?>" class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                </div>
                                                <div class="playlist-data-card-content">
                                                    <h6 class="mt-0 mb-2 line-count-2 playlist-data-title">
                                                        <a href="<?php echo esc_url($redirect_url); ?>"><?php echo esc_html($title); ?></a>
                                                    </h6>
                                                    <ul class="playlist-category list-inline d-flex flex-wrap align-items-center m-0 p-0 column-gap-3 row-gap-1">
                                                        <?php // Check if the view counter option is enabled
                                                        if (isset($streamit_core_options['streamit_show_viewcounter']) && $streamit_core_options['streamit_show_viewcounter'] === 'yes') : ?>

                                                            <?php echo st_get_icon('eye-2', ['class' => 'me-1']); ?>
                                                            <?php echo esc_html($view) . ' ' . esc_html__('views', 'streamit'); ?></li>
                                                        <?php endif; ?>
                                                        <li><?php echo esc_html($time_diff); ?></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Main video player and actions -->
                        <div class="col-xxl-9 col-xl-8 col-lg-7">
                            <?php
                            // Default to the first video if no display video is set
                            if ($display_video_data) :
                                streamit_manage_views_count($display_video_data);
                                streamit_get_template('video/content/playlist_player.php', ['st_data' => $display_video_data]);

                                $is_like_display = !isset($streamit_options['streamit_display_like']) || $streamit_options['streamit_display_like'] !== 'no';
                                $is_social_share_display = !isset($streamit_options['streamit_display_social_icons']) || $streamit_options['streamit_display_social_icons'] !== 'no';
                            ?>
                                <div id="streamit_player_container" class="d-flex justify-content-between gap-4">
                                    <a href="<?php echo esc_url(streamit_get_permalink($display_video_data->get_post_type(), $display_video_data->get_post_name())); ?>">
                                        <h4 class="my-2 fw-bold"><?php echo esc_html($display_video_data->get_post_title()); ?></h4>
                                    </a>
                                    <ul class="actions-playlist list-inline my-2 p-0 d-flex gap-2 justify-content-md-end">
                                        <?php
                                        // Like Icon
                                        if ($is_like_display && is_user_logged_in()) : ?>
                                            <li>
                                                <?php echo do_shortcode('[streamit_like_shortcode post_id="' . esc_attr($display_video_data->get_id()) . '" post_type="' . esc_attr($display_video_data->get_post_type()) . '"]'); ?>
                                            </li>
                                        <?php endif;

                                        // Social Share Modal
                                        if ($is_social_share_display) : ?>
                                            <li class="position-relative share-button dropend dropdown">
                                                <button type="button" class="action-btn btn btn-secondary border" data-bs-toggle="modal" data-bs-target="#shareModal">
                                                    <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php esc_attr_e('Share', 'streamit'); ?>">
                                                        <?php echo st_get_icon('share-2'); ?>
                                                    </span>
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php streamit_get_template('video/content/video_single_share_model.php', ['st_data' => $display_video_data]); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <p class="no_data_found"><?php echo esc_html__('No Videos found.', 'streamit'); ?></p>
                <?php endif; // End video check
            else : ?>
                <p class="no_data_found"><?php echo esc_html__('You cant access this playlist.', 'streamit'); ?></p>
            <?php endif; // End playlist owner check
        else : ?>
            <p class="no_data_found"><?php echo esc_html__('No Video Playlist found.', 'streamit'); ?></p>
        <?php endif; ?>
    </div>
</div>
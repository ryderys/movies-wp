<?php

/**
 * The template for displaying a single movie playlist.
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

// Get playlist ID
$display_movie = isset($_GET['movieid']) ? absint($_GET['movieid']) : 0;

?>
<div class="playlist-detail-page">
    <div class="container-fluid">
        <?php if (!empty($content_data)) : ?>
            <?php if (get_current_user_id() == $content_data->get_user_id()) : ?>
                <?php
                $playlist_id = $content_data->get_playlist_id();
                // Fetch movies from the playlist
                $moviesids = streamit_get_playlist_item($playlist_id, 'movie');
                if (!empty($moviesids)) :
                    $movies = streamit_get_movies([
                        'per_page' => -1,
                        'include'  => $moviesids,
                        'paged'    => 1,
                    ])->results;
                    $display_movie_data = null;

                    if (empty($display_movie)) {
                        $display_movie = !empty($movies) ? $movies[0]->get_id() : 0;
                    }
                ?>
                    <div class="row gy-4 flex-column-reverse flex-lg-row-reverse">
                        <div class="col-xxl-3 col-xl-4 col-lg-5">
                            <div class="card">
                                <div class="card-header pb-3 mb-3 border-bottom d-flex align-items-center justify-content-between gap-1">
                                    <h5 class="m-0"><?php echo esc_html($content_data->get_playlist_name()); ?></h5>
                                    <small>
                                        <?php
                                        $current_movie_index = array_search(
                                            $display_movie,
                                            array_map(function ($movie) {
                                                return $movie->get_id();
                                            }, $movies)
                                        );
                                        if ($current_movie_index !== false) {
                                            echo sprintf(esc_html__('%d/%d', 'streamit'), $current_movie_index + 1, count($movies));
                                        } else {
                                            echo esc_html__('1/' . count($movies), 'streamit');
                                        }
                                        ?>
                                    </small>
                                </div>

                                <div class="card-body px-0 pt-0 pb-3">
                                    <div class="playlist-data">
                                        <?php foreach ($movies as $movie) :
                                            $thumbnail = !empty($movie->get_meta('thumbnail_id'))
                                                ? wp_get_attachment_image_url($movie->get_meta('thumbnail_id'), 'full')
                                                : streamit_placeholder_image();
                                            $title = $movie->get_post_title();
                                            $view = $movie->get_meta('post_views_count');
                                            $release_date = $movie->get_meta('_movie_release_date');
                                            $time_diff = human_time_diff(strtotime($release_date), current_time('timestamp'));
                                            $encoded_playlist_id = urlencode(base64_encode('playlistid_' . $playlist_id));
                                            $redirect_url = add_query_arg('movieid', $movie->get_id(), streamit_get_permalink('movie_playlist', $encoded_playlist_id));
                                            $active_class = ($display_movie === $movie->get_id()) ? 'active' : '';
                                            if ($active_class === 'active') {
                                                $display_movie_data = $movie;
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
                                                            <li>
                                                                <?php echo st_get_icon('eye-2', ['class' => 'me-1']); ?><?php echo sprintf(esc_html__('%s views', 'streamit'), esc_html((string) $view)); ?>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <?php echo esc_html($time_diff); ?> <?php esc_html_e('ago', 'streamit') ?>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xxl-9 col-xl-8 col-lg-7">
                            <?php if ($display_movie_data) :
                                streamit_manage_views_count($display_movie_data);
                                streamit_get_template('movie/content/playlist_player.php', ['st_data' => $display_movie_data]);

                                $is_like_display = isset($streamit_options['streamit_display_like']) && ($streamit_options['streamit_display_like'] !== 'no');
                                $is_social_share_display = isset($streamit_options['streamit_display_social_icons']) && ($streamit_options['streamit_display_social_icons'] !== 'no');
                            ?>
                                <div id="streamit_player_container" class="d-flex justify-content-between gap-4">
                                    <a href="<?php echo esc_url(streamit_get_permalink($display_movie_data->get_post_type(), $display_movie_data->get_post_name())); ?>">
                                        <h4 class="my-2 fw-bold"><?php echo esc_html($display_movie_data->get_post_title()); ?></h4>
                                    </a>
                                    <ul class="actions-playlist list-inline my-2 p-0 d-flex gap-2 justify-content-md-end">
                                        <?php if ($is_like_display && is_user_logged_in()) : ?>
                                            <li>
                                                <?php echo do_shortcode('[streamit_like_shortcode post_id="' . esc_attr($display_movie_data->get_id()) . '" post_type="' . esc_attr($display_movie_data->get_post_type()) . '"]'); ?>
                                            </li>
                                        <?php endif; ?>
                                        <?php if ($is_social_share_display) : ?>
                                            <li class="position-relative share-button dropend dropdown">
                                                <button type="button" class="action-btn btn btn-secondary border" data-bs-toggle="modal" data-bs-target="#shareModal" aria-label="<?php esc_attr_e('Share', 'streamit'); ?>">
                                                    <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php esc_attr_e('Share', 'streamit'); ?>">
                                                        <?php echo st_get_icon('share-2', ['aria-hidden' => 'true']); ?>
                                                    </span>
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php streamit_get_template('movie/content/movie_single_share_model.php', ['st_data' => $display_movie_data]); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <p class="no_data_found"><?php esc_html_e('No Movies found.', 'streamit'); ?></p>
                <?php endif; ?>
            <?php else : ?>
                <p class="no_data_found"><?php esc_html_e('You cannot access this playlist.', 'streamit'); ?></p>
            <?php endif; ?>
        <?php else : ?>
            <p class="no_data_found"><?php esc_html_e('No Movie Playlist found.', 'streamit'); ?></p>
        <?php endif; ?>
    </div>
</div>
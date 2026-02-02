<?php

/**
 * The template for displaying playlist archives.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$user_id = get_current_user_id();

// Display message if user is not logged in.
if (empty($user_id)) : ?>
    <div class="col-md-12">
        <div class="d-flex align-items-center justify-content-center gap-3 my-5">
            <div class="content">
                <h5 class="mt-0 mb-2"><?php esc_html_e('Sorry, user not found.', 'streamit'); ?></h5>
                <span><?php esc_html_e('You need to log in to view playlists.', 'streamit'); ?></span>
            </div>
        </div>
    </div>
<?php return;
endif;

// Fetch playlists for the logged-in user.
$args = [
    'user_id'  => $user_id,
    'per_page' => 5, // Retrieve all playlists
];

$movie_playlist = function_exists('streamit_get_movie_playlists') ? streamit_get_movie_playlists($args) : [];
$video_playlist  = function_exists('streamit_get_video_playlists') ? streamit_get_video_playlists($args) : [];
$episode_playlist = function_exists('streamit_get_episode_playlists') ? streamit_get_episode_playlists($args) : [];


// Prepare tabs with their corresponding data.
$tabs = [
    'movies'   => $movie_playlist,
    'episodes' => $episode_playlist,
    'videos'   => $video_playlist
];

$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', 'بارگذاری بیشتر');
$loading_text = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__('Loading...', 'streamit'));

?>
<div class="play-lists">
    <div class="container-fluid">
        <div class="row gy-3 column-reverce align-items-center border-bottom mb-5">
            <div class="col-8 col-sm-9 col-lg-10">
                <div id="item-nav">
                    <div class="item-list-tabs no-ajax css_prefix-tab-lists" id="object-nav">
                        <!-- Playlist Tabs -->
                        <ul class="nav nav-underline data-search-tab" id="pills-tab" role="tablist">
                            <?php $firstTab = true; ?>
                            <?php foreach ($tabs as $key => $data) : ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $firstTab ? 'active' : ''; ?>"
                                        id="pills-<?php echo esc_attr($key); ?>-tab"
                                        data-bs-toggle="pill"
                                        data-bs-target="#pills-<?php echo esc_attr($key); ?>"
                                        type="button"
                                        role="tab"
                                        aria-controls="pills-<?php echo esc_attr($key); ?>"
                                        aria-selected="<?php echo $firstTab ? 'true' : 'false'; ?>">
                                        <?php echo ucfirst(esc_html($key)); ?>
                                    </button>
                                </li>
                                <?php $firstTab = false; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-4 col-sm-3 col-lg-2">
                <span class="d-flex justify-content-end">
                    <?php streamit_get_template('playlist/archive/add_playlist_modal.php'); ?>
                </span>
            </div>
        </div>

        <!-- Playlist Tab Content -->
        <div class="tab-content" id="pills-tabContent">
            <div class="css_prefix-card-wrapper tab-pane fade active show" id="pills-movies" role="tabpanel" tabindex="0">
                <div class="row gy-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                    <?php if (!empty($movie_playlist->results)) : ?>
                        <?php foreach ($movie_playlist->results as $st_data) : ?>
                            <?php streamit_get_template('playlist/archive/movie_playlists_loop.php', ['st_data' => $st_data]); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center w-100"><?php esc_html_e('No playlists available.', 'streamit'); ?></p>
                    <?php endif; ?>

                </div>
                <?php
                if (!empty($movie_playlist->results) && !empty($movie_playlist->maxnumpages) && $movie_playlist->maxnumpages > 1) {
                    echo st_get_load_more_button(
                        $movie_playlist->maxnumpages,
                        'movie_playlists',
                        1,
                        esc_html($load_more_text),
                        esc_html($loading_text)
                    );
                }
                ?>
            </div>

            <div class="css_prefix-card-wrapper tab-pane fade" id="pills-episodes" role="tabpanel" tabindex="0">
                <div class="row gy-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                    <?php if (!empty($episode_playlist->results)) : ?>
                        <?php foreach ($episode_playlist->results as $st_data) : ?>
                            <?php streamit_get_template('playlist/archive/episode_playlists_loop.php', ['st_data' => $st_data]); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center w-100"><?php esc_html_e('No playlists available.', 'streamit'); ?></p>
                    <?php endif; ?>
                </div>

                <?php
                if (!empty($episode_playlist->results) && !empty($episode_playlist->maxnumpages) && $episode_playlist->maxnumpages > 1)
                    echo st_get_load_more_button(
                        $episode_playlist->maxnumpages,
                        'episode_playlists',
                        1,
                        esc_html($load_more_text),
                        esc_html($loading_text)
                    );
                ?>
            </div>

            <div class="css_prefix-card-wrapper tab-pane fade" id="pills-videos" role="tabpanel" tabindex="0">
                <div class="row gy-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                    <?php if (!empty($video_playlist->results)) : ?>
                        <?php foreach ($video_playlist->results as $st_data) : ?>
                            <?php streamit_get_template('playlist/archive/video_playlists_loop.php', ['st_data' => $st_data]); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center w-100"><?php esc_html_e('No playlists available.', 'streamit'); ?></p>
                    <?php endif; ?>
                </div>
                <?php
                if (!empty($video_playlist->results) && !empty($video_playlist->maxnumpages) && $video_playlist->maxnumpages > 1)
                    echo st_get_load_more_button(
                        $video_playlist->maxnumpages,
                        'video_playlists',
                        1,
                        esc_html($load_more_text),
                        esc_html($loading_text)
                    );
                ?>
            </div>


        </div>
    </div>
</div>
<?php

/**
 * The template for displaying actor history.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$movies = $tvshows = [];

// Retrieve movie-related meta fields
$movie_cast = $st_data->get_meta('_movie_cast');
$movie_crew = $st_data->get_meta('_movie_crew');
$movie_cast = !empty($movie_cast) && is_array($movie_cast) ? $movie_cast : [];
$movie_crew = !empty($movie_crew) && is_array($movie_crew) ? $movie_crew : [];
$movie_ids = array_unique(array_merge($movie_cast, $movie_crew));

// Retrieve TV show-related meta fields
$tvshow_cast = $st_data->get_meta('_tvshow_cast');
$tvshow_crew = $st_data->get_meta('_tvshow_crew');
$tvshow_cast = !empty($tvshow_cast) && is_array($tvshow_cast) ? $tvshow_cast : [];
$tvshow_crew = !empty($tvshow_crew) && is_array($tvshow_crew) ? $tvshow_crew : [];
$tvshow_ids = array_unique(array_merge($tvshow_cast, $tvshow_crew));

// Fetch movies and TV shows based on IDs
if (!empty($movie_ids)) {
    $movies = streamit_get_movies(['per_page' => -1, 'include' => $movie_ids])->results;
}

if (!empty($tvshow_ids)) {
    $tvshows = streamit_get_tvshows(['per_page' => -1, 'include' => $tvshow_ids])->results;
}
?>

<div class="actor-history">
    <?php if (!empty($st_data->get_meta('cast_awards'))) : ?>
        <h5 class="mt-4 "><?php esc_html_e('Awards :', 'streamit'); ?></h5>
        <p class="text-uppercase mt-3"><?php echo esc_html($st_data->get_meta('cast_awards')); ?></p>
    <?php endif; ?>
    <div class="title">
        <h4 class="title-tag"><?php esc_html_e('Person History', 'streamit'); ?></h4>
    </div>
    <?php
    if (empty($movies) && empty($tvshows)) {
        echo '<div class="no-content">';
        echo '<p>' . esc_html__('No TV shows or Movies found.', 'streamit') . '</p>';
        echo '</div>';
        echo '</div>';
        return;
    }
    ?>

    <ul class="nav nav-underline my-5 list-inline" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pills-personall-tab" data-bs-toggle="pill" data-bs-target="#pills-person-all" type="button" role="tab" aria-controls="pills-person-all" aria-selected="true">
                <?php esc_html_e('All', 'streamit'); ?>
            </button>
        </li>
        <?php if (!empty($movies)) : ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-person-movie-tab" data-bs-toggle="pill" data-bs-target="#pills-person-movie" type="button" role="tab" aria-controls="pills-person-movie" aria-selected="false">
                    <?php esc_html_e('Movies', 'streamit'); ?>
                </button>
            </li>
        <?php endif; ?>
        <?php if (!empty($tvshows)) : ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-person-tvshow-tab" data-bs-toggle="pill" data-bs-target="#pills-person-tvshow" type="button" role="tab" aria-controls="pills-person-tvshow" aria-selected="false">
                    <?php esc_html_e('TV Shows', 'streamit'); ?>
                </button>
            </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        <!-- All Tab -->
        <?php if (!empty($movies) || !empty($tvshows)) : ?>
            <div class="tab-pane fade show active" id="pills-person-all" role="tabpanel">
                <div class="div">
                    <div class="row gy-5">
                        <?php
                        $all_shows = array_merge($movies, $tvshows);
                        ?>
                        <?php foreach ($all_shows as $show) :
                            $release_year = '';
                            if ($show->get_post_type() == 'tvshow') {
                                $season_data = $show->get_meta('_seasons');
                                if (!empty($season_data)) {
                                    $season_data = $season_data[0];
                                    $release_year = $season_data['season_year'];
                                }
                            } else {
                                $release_date = $show->get_meta('_movie_release_date');
                                if (!empty($release_date)) {
                                    $release_year = wp_date('Y', strtotime($release_date));
                                }
                            }
                        ?>
                            <div class="col-xl-4 col-sm-6">
                                <div class="d-flex align-items-center gap-3 bg-gray-900 rounded-3 overflow-hidden">
                                    <div class="image flex-shrink-0">
                                        <?php
                                        $thumbnail_image_id = $show->get_meta('thumbnail_id');
                                        $portrait_image_id  = $show->get_meta('_portrait_thumbmail');

                                        $image          = !empty($thumbnail_image_id) ? wp_get_attachment_image_url($thumbnail_image_id, 'full') : '';
                                        $portrait_image = !empty($portrait_image_id) ? wp_get_attachment_image_url($portrait_image_id, 'full') : '';

                                        // Use portrait image if available, otherwise use thumbnail, else placeholder
                                        $thumbnail_url = !empty($portrait_image) ? $portrait_image : (!empty($image) ? $image : streamit_placeholder_image());
                                        ?>

                                        <a href="<?php echo esc_url(streamit_get_permalink($show->get_post_type(), $show->get_post_name())); ?>">
                                            <img src="<?php echo esc_url($thumbnail_url); ?>"
                                                alt="<?php echo esc_attr($show->get_post_title()); ?>"
                                                class="img-fluid object-fit-cover person-history-thumbnail">
                                        </a>
                                    </div>

                                    <div class="content">
                                        <h5 class="mb-1 line-count-2">
                                            <a href="<?php echo esc_url(streamit_get_permalink($show->get_post_type(), $show->get_post_name())); ?>">
                                                <h6 class="m-0"><?php echo esc_html($show->get_post_title()); ?></h6>
                                            </a>
                                        </h5>
                                        <span><?php echo esc_html($release_year); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <p><?php esc_html_e('No movies or TV shows available in this category.', 'streamit'); ?></p>
        <?php endif; ?>
        <!-- Movies Tab -->
        <?php if (!empty($movies)) : ?>
            <div class="tab-pane fade" id="pills-person-movie" role="tabpanel">
                <div class="div">
                    <div class="row gy-5">
                        <?php foreach ($movies as $movie) :
                            $release_year = '';
                            $release_date = $movie->get_meta('_movie_release_date');
                            if (!empty($release_date)) {
                                $release_year = wp_date('Y', strtotime($release_date));
                            }
                        ?>
                            <div class="col-xl-4 col-sm-6">
                                <div class="d-flex align-items-center gap-3 bg-gray-900 rounded-3 overflow-hidden">
                                    <div class="image flex-shrink-0">
                                        <?php
                                        $thumbnail_image_id = $movie->get_meta('thumbnail_id');
                                        $portrait_image_id  = $movie->get_meta('_portrait_thumbmail');

                                        $image          = !empty($thumbnail_image_id) ? wp_get_attachment_image_url($thumbnail_image_id, 'full') : '';
                                        $portrait_image = !empty($portrait_image_id) ? wp_get_attachment_image_url($portrait_image_id, 'full') : '';

                                        // Use portrait image if available, otherwise use thumbnail, else placeholder
                                        $thumbnail_url = !empty($portrait_image) ? $portrait_image : (!empty($image) ? $image : streamit_placeholder_image());
                                        ?>

                                        <a href="<?php echo esc_url(streamit_get_permalink($movie->get_post_type(), $movie->get_post_name())); ?>">
                                            <img src="<?php echo esc_url($thumbnail_url); ?>"
                                                alt="<?php echo esc_attr($movie->get_post_title()); ?>"
                                                class="img-fluid object-fit-cover person-history-thumbnail">
                                        </a>
                                    </div>

                                    <div class="content">
                                        <h5 class="mb-1 line-count-2">
                                            <a href="<?php echo esc_url(streamit_get_permalink($movie->get_post_type(), $movie->get_post_name())); ?>" class="color-inherit">
                                                <?php echo esc_html($movie->get_post_title()); ?>
                                            </a>
                                        </h5>
                                        <span><?php echo esc_html($release_year); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <!-- TV Shows Tab -->
        <?php if (!empty($tvshows)) : ?>
            <div class="tab-pane fade" id="pills-person-tvshow" role="tabpanel">

            <div class="div">
                <div class="row gy-5">
                    <?php foreach ($tvshows as $tvshow) :
                        $release_year = '';
                        $season_data = $show->get_meta('_seasons');
                        if (!empty($season_data)) {
                            $season_data = $season_data[0];
                            $release_year = $season_data['season_year'];
                        }
                    ?>
                        <div class="col-xl-4 col-sm-6">
                            <div class="d-flex align-items-center gap-3 bg-gray-900 rounded-3 overflow-hidden">
                                <div class="image flex-shrink-0">
                                    <?php
                                    $thumbnail_image_id = $tvshow->get_meta('thumbnail_id');
                                    $portrait_image_id  = $tvshow->get_meta('_portrait_thumbmail');

                                    $image          = !empty($thumbnail_image_id) ? wp_get_attachment_image_url($thumbnail_image_id, 'full') : '';
                                    $portrait_image = !empty($portrait_image_id) ? wp_get_attachment_image_url($portrait_image_id, 'full') : '';

                                    // Use portrait image if available, otherwise use thumbnail, else placeholder
                                    $thumbnail_url = !empty($portrait_image) ? $portrait_image : (!empty($image) ? $image : streamit_placeholder_image());
                                    ?>

                                    <a href="<?php echo esc_url(streamit_get_permalink($tvshow->get_post_type(), $tvshow->get_post_name())); ?>">
                                        <img src="<?php echo esc_url($thumbnail_url); ?>"
                                            alt="<?php echo esc_attr($tvshow->get_post_title()); ?>"
                                            class="img-fluid object-fit-cover person-history-thumbnail">
                                    </a>
                                </div>

                                <div class="content">
                                    <h5 class="mb-1 line-count-2">
                                        <a href="<?php echo esc_url(streamit_get_permalink($tvshow->get_post_type(), $tvshow->get_post_name())); ?>">
                                            <h6 class="m-0"><?php echo esc_html($tvshow->get_post_title()); ?></h6>
                                        </a>
                                    </h5>
                                    <span><?php echo esc_html($release_year); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
    </div>
<?php endif; ?>
</div>
</div>
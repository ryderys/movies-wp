<?php

/**
 * The template for displaying archive loop pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

?>
<div class="col">
    <div class="css_prefix-card card-hover-flip">
        <div class="block-images position-relative w-100">
            <!-- Movie Thumbnail -->
            <?php
            // Ensure that any properties of $st_data used inside movie_thumbnail.php are sanitized
            streamit_get_template('movie/content/movie_thumbnail.php', ['st_data' => $st_data, 'view_type' => isset($view_type) ? $view_type : '']);
            ?>

            <div class="card-description with-transition">
                <div class="position-relative w-100">
                    <!-- Movie Genre -->
                    <?php
                    streamit_get_template('movie/content/movie_genre.php', ['st_data' => $st_data]);
                    ?>

                    <!-- Movie Title -->
                    <?php
                    streamit_get_template('movie/content/movie_title.php', ['st_data' => $st_data]);
                    ?>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- Movie Runtime -->
                        <?php
                        streamit_get_template('movie/content/movie_runtime.php', ['st_data' => $st_data]);
                        ?>

                        <!-- Movie Language (with limit) -->
                        <?php
                        streamit_get_template('movie/content/movie_language.php', ['st_data' => $st_data, 'is_limit' => true]);
                        ?>
                    </div>

                    <!-- Movie Excerpt -->
                    <?php
                    streamit_get_template('movie/content/movie_excerpt.php', ['st_data' => $st_data, 'is_limit' => true]);
                    ?>
                    

                    <div class="css-prefix-play-button d-flex flex-wrap align-items-center gap-2 mt-3">
                        <!-- Movie Watchlist -->
                        <?php
                        streamit_get_template('movie/content/movie_watch_list.php', ['st_data' => $st_data]);
                        ?>

                        <!-- Movie Watch Now -->
                        <?php
                        streamit_get_template('movie/content/movie_watch_now.php', ['st_data' => $st_data]);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
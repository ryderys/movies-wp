<?php

/**
 * The template for displaying metalist
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check if this is an upcoming movie
$upcoming_data =  function_exists('streamit_is_upcoming') ? streamit_is_upcoming($st_data, 'movie') : [
    'is_upcoming' => false,
    'is_future_release' => false,
    'formatted_date' => ''
];
$is_upcoming = $upcoming_data['is_future_release'];

?>
<ul class="list-inline m-0 p-0 d-flex align-items-center flex-wrap gap-3 movie-metalist">

    <!-- Movie Release data  -->
    <?php streamit_get_template('movie/content/movie_single_release_date.php', ['st_data' => $st_data]); ?>

    <!-- Movie Runtime  -->
    <?php streamit_get_template('movie/content/movie_single_runtime.php', ['st_data' => $st_data]); ?>

    <?php if (!$is_upcoming) : ?>
        <!-- Movie Views - Only show for released movies -->
        <?php streamit_get_template('movie/content/movie_single_views.php', ['st_data' => $st_data]); ?>

        <!-- Movie IMDB Rating - Only show for released movies -->
        <?php streamit_get_template('movie/content/movie_single_imdb_rating.php', ['st_data' => $st_data]); ?>

        <!-- Movie Censor Rating - Only show for released movies -->
        <?php streamit_get_template('movie/content/movie_single_censor_rating.php', ['st_data' => $st_data]); ?>
    <?php else : ?>
        <!-- Upcoming Movie Label - Only show for upcoming movies -->
        <?php streamit_get_template('movie/content/movie_single_upcoming_label.php', ['st_data' => $st_data]); ?>
    <?php endif; ?>

</ul>
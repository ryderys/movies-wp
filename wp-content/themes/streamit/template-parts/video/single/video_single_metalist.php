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

// Check if this is an upcoming video
$upcoming_data =  function_exists('streamit_is_upcoming') ? streamit_is_upcoming($st_data, 'video') : [
    'is_upcoming' => false,
    'is_future_release' => false,
    'formatted_date' => ''
];
$is_upcoming = $upcoming_data['is_future_release'];

?>
<ul class="list-inline mt-4 mb-0 mx-0 p-0 d-flex align-items-center flex-wrap gap-3 video-metalist">

    <?php streamit_get_template('video/content/video_single_runtime.php', ['st_data' => $st_data]);  ?>

    <?php streamit_get_template('video/content/video_single_release_date.php', ['st_data' => $st_data]);  ?>

    <?php if (!$is_upcoming) : ?>

        <?php streamit_get_template('video/content/video_single_views.php', ['st_data' => $st_data]);  ?>

        <?php streamit_get_template('video/content/video_single_censor_rating.php', ['st_data' => $st_data]); ?>

    <?php else : ?>
        <?php streamit_get_template('video/content/video_single_upcoming_label.php', ['st_data' => $st_data]); ?>
    <?php endif; ?>

</ul>
<?php

/**
 * The template for displaying actions
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

?>
<div class="d-flex align-items-center flex-wrap gap-3 gap-md-4 mt-5">

    <?php 
    $upcoming_data =  function_exists('streamit_is_upcoming') ? streamit_is_upcoming($st_data) : [
        'is_upcoming' => false,
        'is_future_release' => false,
        'formatted_date' => ''
    ];
    $is_admin = (is_user_logged_in() && current_user_can('administrator'));

    if ($upcoming_data['is_future_release'] && !$is_admin) {
        streamit_get_template('video/content/video_single_notify_me_btn.php', ['st_data' => $st_data]);
    } else {
        streamit_get_template('video/content/video_single_play_btn.php', ['st_data' => $st_data]);
    }
    ?>

    <?php streamit_get_template('video/content/video_single_watchlist_btn.php', ['st_data' => $st_data]);  ?>

    <?php streamit_get_template('video/content/video_single_actions_model.php', ['st_data' => $st_data]);  ?>

</div>
<?php

/**
 * The template for displaying index of current episode
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Get episode data
$st_data = $st_data ?? null;
if (empty($st_data)) {
    return;
}

// Get upcoming status
$upcoming_data = [];
if (function_exists('streamit_get_tvshow_upcoming_status')) {
    $upcoming_data = streamit_get_tvshow_upcoming_status($st_data);
}
$is_upcoming = $upcoming_data['is_upcoming'] ?? false;
$season_id = $upcoming_data['season_id'] ?? '';

// Get episode meta data
$episode_id = $st_data->get_id();
$season_number = 1;
$episode_number = 2;

?>
<div class="episode-index-info">
    <div class="episode-meta-header">
        <?php if (!empty($season_number) && !empty($episode_number)) : ?>
            <span class="season-episode-info">
                <?php echo sprintf(esc_html__('Season %s • Episode %s', 'streamit'), $season_number, $episode_number); ?>
            </span>
        <?php endif; ?>

    </div>
</div>

<?php

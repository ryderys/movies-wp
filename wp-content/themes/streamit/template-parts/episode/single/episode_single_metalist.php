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

$upcoming_status = [];
if (!empty($content_data) && function_exists('streamit_get_tvshow_upcoming_status')) {
    $upcoming_status = streamit_get_tvshow_upcoming_status($content_data);
}
// Extract variables for backward compatibility
$is_upcoming = $upcoming_status['is_upcoming'] ?? false;

?>
<ul class="list-inline mt-4 mb-0 mx-0 p-0 d-flex align-items-center flex-wrap gap-3 episode-metalist">
<?php streamit_get_template('episode/content/episode_number.php', ['st_data' =>  $st_data]); ?>

    <?php streamit_get_template('episode/content/episode_single_release_date.php', ['st_data' =>  $st_data]); ?>

    <?php streamit_get_template('episode/content/episode_single_runtime.php', ['st_data' =>  $st_data]); ?>

    <?php if ($is_upcoming) : ?>
        <?php streamit_get_template('episode/content/episode_single_views.php', ['st_data' =>  $st_data]); ?>
    <?php endif; ?>

</ul>
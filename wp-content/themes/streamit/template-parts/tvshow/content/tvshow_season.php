<?php

/**
 * The template for displaying TV show season pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Retrieve the seasons data
$seasons = $st_data->get_meta('_seasons');

// Check if seasons exist and are in an array format
if (!empty($seasons) && is_array($seasons)) {
    // Count the number of seasons
    $season_count = count($seasons);
    // Prepare the season display text (singular/plural depending on the count)
    $season_display = sprintf(
        _n('%d Season', '%d Seasons', $season_count, 'streamit'),
        $season_count
    );
} else {
    // If no seasons, show "Arriving Soon"
    $season_display = __('Arriving Soon', 'streamit');
}

?>

<!-- Display the season information -->
<div class="font-size-14">
    <?php echo esc_html($season_display); ?>
</div>
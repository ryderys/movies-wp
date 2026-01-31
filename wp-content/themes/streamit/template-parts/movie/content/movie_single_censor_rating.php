<?php

/**
 * The template for displaying censor rating
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!empty($st_data->get_meta('_movie_censor_rating'))) : ?>
    <li>
        <span class="badge bg-secondary py-2 px-3 d-flex align-items-center gap-2">
            <span class="fw-medium">
                <?php echo esc_html($st_data->get_meta('_movie_censor_rating')); ?>
            </span>
        </span>
    </li>
<?php endif; ?>

<?php
$remaining_time = function_exists('streamit_get_remaining_time') ? streamit_get_remaining_time($st_data->get_id()) : '';
if (!empty($remaining_time)) : ?>
    <li>
        <span class="badge d-flex align-items-center gap-2 px-3 py-2 timeline-badge">
            <?php echo st_get_icon('clock'); ?>
            <span class="fw-medium">
                <?php echo esc_html($remaining_time); ?>
            </span>
        </span>
    </li>
<?php endif; ?>
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

if (!empty($st_data->get_meta('_video_censor_rating'))) : ?>
    <li>
        <span class="badge bg-secondary py-2 px-3 d-flex align-items-center gap-2">
            <span class="fw-medium">
                <?php echo esc_html($st_data->get_meta('_video_censor_rating')); ?>
            </span>
        </span>
    </li>
<?php endif; ?>

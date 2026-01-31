<?php

/**
 * The template for displaying release date
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$run_time = $st_data->get_meta('_video_run_time');
if( !empty( $run_time ) && $run_time !== '0:00' ) : ?>
    <li>
        <span class="d-flex align-items-center gap-1">
            <span><?php echo st_get_icon('clock'); ?></span>
            <?php echo esc_html($run_time); ?>
        </span>
    </li>
<?php endif;
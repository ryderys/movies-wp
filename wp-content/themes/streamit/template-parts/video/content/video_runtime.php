<?php

/**
 * The template for displaying archive run time.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
$run_time = $st_data->get_meta('_video_run_time');
if( !empty( $run_time ) && $run_time !== '0:00' ) : ?>
    <div class="video-time d-flex align-items-center gap-1">
        <?php echo st_get_icon('clock'); ?>
        <small class="video-time-text font-normal">
            <?php echo esc_html(st_format_runtime($run_time)); ?>
        </small>
    </div>
<?php endif; 
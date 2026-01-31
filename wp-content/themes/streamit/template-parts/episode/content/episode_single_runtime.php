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

$runtime = st_format_runtime($st_data->get_meta('_episode_run_time'));
if( !empty( $runtime ) && $runtime !== '0:00' ) : ?>
    <li>
        <span class="d-flex align-items-center gap-1">
            <span><?php echo st_get_icon('clock'); ?></span>
            <?php echo esc_html($runtime); ?>
        </span>
    </li>
<?php endif;
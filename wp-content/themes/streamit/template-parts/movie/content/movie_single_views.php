<?php

/**
 * The template for displaying views
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_core_options;
?>

<?php
// Check if the view counter option is enabled
if (isset($streamit_core_options['streamit_show_viewcounter']) && $streamit_core_options['streamit_show_viewcounter'] === 'yes') : ?>
    <li>
        <span class="d-flex align-items-center gap-1">
            <span> </span>
            <?php $total_views = streamit_manage_views_count($st_data);
            echo sprintf(esc_html__('%s views', 'streamit'), esc_html((string) $total_views));
            ?>
        </span>
    </li>
<?php endif; ?>
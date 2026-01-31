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

$st_release_date = $st_data->get_meta('_episode_release_date');
if(!empty($st_release_date)) :
?>
<li>
    <span class="d-flex align-items-center gap-1">
        <span class="fw-medium">
            <?php
            echo wp_date('M Y', strtotime($st_release_date));
            ?>
        </span>
    </span>
</li>
<?php endif; ?>
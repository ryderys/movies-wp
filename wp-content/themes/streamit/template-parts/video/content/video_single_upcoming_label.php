<?php

/**
 * The template for displaying upcoming video label
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check if this is an upcoming video
$upcoming_data =  function_exists('streamit_is_upcoming') ? streamit_is_upcoming($st_data, 'video') : [
    'is_upcoming' => false,
    'is_future_release' => false,
    'formatted_date' => ''
];
if ($upcoming_data['is_future_release'] && !empty($upcoming_data['formatted_date'])) :
    ?>
    <li>
        <span class="badge d-flex align-items-center gap-2 px-3 py-2 timeline-badge">
            <?php echo st_get_icon('calendar-2'); ?>
            <span class="fw-medium">
                <?php 
                printf(
                    esc_html__('Release on %s', 'streamit'),
                    esc_html($upcoming_data['formatted_date'])
                );
                ?>
            </span>
        </span>
    </li>
    <?php
endif;
?>

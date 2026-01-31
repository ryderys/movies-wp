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

// Check if the `get_post_date` method exists and retrieves a valid date.
$post_date = method_exists($st_data, 'get_post_date') ? $st_data->get_post_date() : '';

if (!empty($post_date)) {
    try {
        $date = new DateTime($post_date);
        $formatted_date = $date->format('M Y');
    } catch (Exception $e) {
        $formatted_date = ''; // Handle invalid date gracefully.
    }
}

if (!empty($formatted_date)) :
    ?>
    <li>
        <span class="d-flex align-items-center gap-1">
            <span class="fw-medium">
                <?php echo esc_html($formatted_date); ?>
            </span>
        </span>
    </li>
<?php endif;
$remaining_time = function_exists('streamit_get_remaining_time') ? streamit_get_remaining_time($st_data->get_id()) : '';
if (!empty($remaining_time)) : ?>
    <li>
        <span class="badge d-flex align-items-center gap-2 px-3 py-2  timeline-badge">
            <?php echo st_get_icon('clock'); ?>
            <span class="fw-medium">
                <?php echo esc_html($remaining_time); ?>
            </span>
        </span>
    </li>
<?php endif; ?>

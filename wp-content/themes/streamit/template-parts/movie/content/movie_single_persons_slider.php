<?php

/**
 * The template for displaying persons slider
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $streamit_core_options;

// Early return if cast/crew is disabled
$type = $args['type'] ?? '';
$st_data = $args['st_data'] ?? null;

if (
    ($type === 'cast' && ($streamit_core_options['streamit_display_cast'] ?? '') === 'no') ||
    ($type === 'crew' && ($streamit_core_options['streamit_display_crew'] ?? '') === 'no')
) {
    return false;
}

$person_meta = $st_data->get_meta('_' . $type);

if (empty($person_meta) || !is_array($person_meta)) {
    return;
}

// Slick carousel arguments
$slick_args = apply_filters('streamit_persons_slider_controls', [
    'dots' => false,
    'slidesToShow' => 12,
    'slidesToScroll' => 12,
    'arrows' => true,
    'autoplay' => false,
    'autoplaySpeed' => 2000,
    'speed' => 300,
    'infinite' => true,
    'swipeToSlide' => true,
    'responsive' => [
        ['breakpoint' => 1350, 'settings' => ['slidesToShow' => 8, 'slidesToScroll' => 8]],
        ['breakpoint' => 1200, 'settings' => ['slidesToShow' => 7, 'slidesToScroll' => 7]],
        ['breakpoint' => 1024, 'settings' => ['slidesToShow' => 5, 'slidesToScroll' => 5]],
        ['breakpoint' => 600,  'settings' => ['slidesToShow' => 4, 'slidesToScroll' => 4]],
        ['breakpoint' => 450,  'settings' => ['slidesToShow' => 3, 'slidesToScroll' => 3]],
    ]
]);

// Title
$slider_title = esc_html($type === 'cast' ? ($streamit_core_options['streamit_cast_title'] ?? '') : ($streamit_core_options['streamit_crew_title'] ?? ''));

// Settings
$title_tag = 'h5';
$settings = [
    'view_all_switch'        => 'no',
    'nav-arrow'              => 'true',
    'enable_premium_badges'  => 'yes',
];

// Collect person IDs
$person_ids = array_column($person_meta, 'id');

// Batch fetch all person objects
$persons = streamit_get_persons([
    'include' => array_map('intval', $person_ids),
    'per_page' => -1
]);

// Index persons by ID for faster lookup
$indexed_persons = [];
foreach ($persons->results as $person_obj) {
    $indexed_persons[$person_obj->get_id()] = $person_obj;
}

// Prepare results
$results = [];
foreach ($person_meta as $person) {
    $id = (int) $person['id'];
    if (isset($indexed_persons[$id])) {
        $character = $person['character'] ?? ($person['role'] ?? '');
        $results[] = [
            'data' => $indexed_persons[$id],
            'character' => $character
        ];
    }
}
?>

<div class="cast-section">
    <div class="single_page_slick">
        <?php
        streamit_get_template(
            'elementor-widget/person-card/html-person-card-slider.php',
            [
                'slick_settings'        => [],
                'slingle_slider_settings' => $slick_args,
                'results'               => $results,
                'settings'              => $settings,
                'title_tag'             => $title_tag,
                'slider_title'          => $slider_title
            ]
        );
        ?>
    </div>
</div>
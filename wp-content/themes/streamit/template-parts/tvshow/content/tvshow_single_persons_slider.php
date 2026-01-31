<?php

/**
 * The template for displaying persons slider
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $streamit_core_options;


if ((($args['type'] === 'cast' && ($streamit_core_options['streamit_display_cast'] ?? '') === 'no') || ($args['type'] === 'crew' && ($streamit_core_options['streamit_display_crew'] ?? '') === 'no'))) return false;

$post_id    = method_exists($st_data, 'get_id') ? $st_data->get_id() : '';
$term_ids       = streamit_get_term_relationships($post_id, 'tvshow_genre');
$query          = ['per_page' => 10];
$tax_query = array('relation' => 'OR');
foreach ($term_ids as $term_id) {
    $tax_query[] = array(
        'field'    => 'term_id',
        'terms'    => $term_id,
        'operator' => '=',
    );
}
$query['tax_query']  = $tax_query;
$slick_args =  apply_filters('streamit_persons_slider_controls', array(
    'dots' => false,
    'slidesToShow' => 12,
    'slidesToScroll' => 12,
    'arrows' => true,
    'autoplay' => false,
    'autoplaySpeed' => 2000,
    'speed' => 300,
    'infinite' => true,
    'swipeToSlide' => true,
    'responsive' => array(
        array(
            'breakpoint' => 1350,
            'settings' => array(
                'slidesToShow' => 8,
                'slidesToScroll' => 8
            )
        ),
        array(
            'breakpoint' => 1200,
            'settings' => array(
                'slidesToShow' => 7,
                'slidesToScroll' => 7
            )
        ),
        array(
            'breakpoint' => 1024,
            'settings' => array(
                'slidesToShow' => 5,
                'slidesToScroll' => 5
            )
        ),
        array(
            'breakpoint' => 600,
            'settings' => array(
                'slidesToShow' => 4,
                'slidesToScroll' => 4
            )
        ),
        array(
            'breakpoint' => 450,
            'settings' => array(
                'slidesToShow' => 3,
                'slidesToScroll' => 3
            )
        )
    )
));

$st_casts_title = !empty($streamit_core_options['streamit_cast_title']) ? $streamit_core_options['streamit_cast_title'] : '';
$st_crew_title  = !empty($streamit_core_options['streamit_crew_title']) ? $streamit_core_options['streamit_crew_title'] : '';
$slider_title =  $type === 'cast' ? esc_html($st_casts_title) : esc_html($st_crew_title);

$title_tag = 'h5';

$settings = [
    'title_tag' => 'h5',
    'slider_title'  =>  $slider_title,
    'view_all_switch'   =>  'no',
    'nav-arrow' =>  'true',
    'enable_premium_badges' =>  'yes',
];
?>
<div class="cast-section">
    <div class="single_page_slick">
        <?php
        $person_meta  = $st_data->get_meta('_' . $type);
        if (!empty($person_meta) && is_array($person_meta)) {
            $results = array();
            foreach ($person_meta as $person) {
                $character = isset($person['character']) ? $person['character'] : '';
                $character = empty($character) && isset($person['role']) ? $person['role'] : $character;
                $results[] = ['data' => streamit_get_person((int)$person['id']), 'character' => $character];
            }

            streamit_get_template(
                'elementor-widget/person-card/html-person-card-slider.php',
                [
                    'slick_settings' => [],
                    'slingle_slider_settings' => $slick_args,
                    'results' => $results,
                    'settings' => $settings,
                    'title_tag'  => $title_tag,
                    'slider_title' => $slider_title
                ]
            );
        }
        ?>
    </div>
</div>
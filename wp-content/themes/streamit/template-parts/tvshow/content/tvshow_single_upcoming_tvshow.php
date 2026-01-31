<?php
/**
 * The template for displaying upcoming tvshow
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;

$upcoming_tvshows_meta_query = array(
    array(
        'key'     => 'tvshow_upcoming_status',
        'value'   => '0', 
        'compare' => '!='
    )
);

$upcoming_list = streamit_get_tvshows([
    'per_page'   => 10,
    'meta_query' => $upcoming_tvshows_meta_query,
]);

if (!isset($upcoming_list->results) || empty($upcoming_list->results)) {
    return;
}

$slick_args = apply_filters('streamit_recomended_tvshow_slider_controls', array(
    'dots'          => false,
    'slidesToShow'  => 6,
    'slidesToScroll'=> 6,
    'arrows'        => true,
    'autoplay'      => false,
    'autoplaySpeed' => 2000,
    'speed'         => 300,
    'infinite'      => true,
    'swipeToSlide'  => true,
    'responsive'    => array(
        array(
            'breakpoint'    => 1025,
            'settings'  => array(
                'slidesToShow' => 4,
                'slidesToScroll' => 4,
            )
        ),
        array(
            'breakpoint'    => 600,
            'settings'  => array(
                'slidesToShow' => 3,
                'slidesToScroll' => 3,
            )
        ),
        array(
            'breakpoint'    => 450,
            'settings'  => array(
                'slidesToShow' => 2,
                'slidesToScroll' => 2,
            )
        )
    )
));

$settings = [
    'view_all_switch'       => 'no',
    'nav-arrow'             => 'true',
    'enable_premium_badges' => 'yes',
    'play_now_text'         => esc_html__('Play Now', 'streamit'),
];

$show_upcoming_post = $streamit_options['streamit_upcoming_multi_select'] ?? [];

if (is_array($show_upcoming_post) && in_array('tv_show', $show_upcoming_post)) :

// Get the title from Redux option, or use the default value if not set
$slider_title = isset($streamit_options['streamit_upcoming_title']) && !empty($streamit_options['streamit_upcoming_title']) 
    ? esc_html($streamit_options['streamit_upcoming_title']) 
    : esc_html__('Upcoming TVshow', 'streamit');

$title_tag = 'h5';
$enable_premium_badges = ( $streamit_options['streamit_recommended_enable_premium_badges'] === 'yes' ) ? 'yes': 'no';
?>

<?php if (isset($streamit_options['streamit_display_upcoming']) && $streamit_options['streamit_display_upcoming'] === 'yes') : ?>
<div class="more-like-section">
    <div class="single_page_slick">
        <?php
        streamit_get_template(
            'elementor-widget/main-card-slider/html-main-card-slider.php',
            [
                'slick_settings'    => $slick_args,
                'post_data'         => $upcoming_list->results,
                'settings'          => $settings,
                'title_tag'         => $title_tag,
                'enable_premium_badges' => 'yes',
                'slider_title'      => $slider_title,
                'enable_premium_badges' => $enable_premium_badges
            ]
        );
        ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>


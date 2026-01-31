<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}


class ST_Continue_Watching extends Widget_Base
{
    public function get_name()
    {
        return 'st_continue_watching';
    }

    public function get_title()
    {
        return __('Continue Watching', 'streamit');
    }

    public function get_icon()
    {
        return 'eicon-post-slider';
    }

    public function get_categories()
    {
        return ['streamit'];
    }
    protected function register_controls()
    {
        $this->start_controls_section(
            'section_watching',
            [
                'label' => __('Continue Watching', 'streamit'),
            ]
        );


        $this->add_control(
            'slider_title',
            [
                'label' => __('Title', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Title', 'streamit'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'title_tag',
            [
                'label'      => __('Title Tag', 'streamit'),
                'type'       => Controls_Manager::SELECT,
                'options'    => [
                    'h1'          => __('h1', 'streamit'),
                    'h2'          => __('h2', 'streamit'),
                    'h3'          => __('h3', 'streamit'),
                    'h4'          => __('h4', 'streamit'),
                    'h5'          => __('h5', 'streamit'),
                    'h6'          => __('h6', 'streamit'),
                ],
                'default'    => 'h4',
            ]
        );


        $this->end_controls_section();


        $this->start_controls_section(
            'slick_control_section',
            [
                'label' => __('Slider Control', 'streamit'),
            ]
        );

        require get_template_directory() . '/inc/Elementor-widget/controls/slick-control.php';

        $this->end_controls_section();
    }

    protected function render()
    {

        if (!is_user_logged_in()) return;

        $settings = $this->get_settings_for_display();
        $slick_settings = array(
            "dots"               => false,
            "slidesToShow"       => wp_is_mobile()?2: (!empty($settings['desk_number']) ? intval($settings['desk_number']) : 6),
            "slidesToScroll"     => !empty($settings['desk_number']) ? intval($settings['desk_number']) : 6,
            "arrows"              => !empty($settings['nav-arrow']) && $settings['nav-arrow'] === "true" ? true : false,
            "autoplay"              => !empty($settings['autoplay']) && $settings['autoplay'] === "true" ? true : false,
            "autoplaySpeed"      => !empty($settings['autoplay_speed']) ?  intval($settings['autoplay_speed']) : 2000,
            "speed"              => !empty($settings['speed']) ?  intval($settings['speed']) : 300,
            "infinite"           => !empty($settings['infinite']) && $settings['infinite'] === "true" ? true : false,
            "responsive"         => [
                [
                    "breakpoint"     => 1367,
                    "settings"         => [
                        "slidesToShow"     => !empty($settings['lap_number']) ? intval($settings['lap_number']) : 4,
                        "slidesToScroll" 	=> !empty($settings['lap_number']) ? intval($settings['lap_number']) : 4,
                    ]
                ],
                [
                    "breakpoint"     => 1025,
                    "settings"         => [
                        "slidesToShow"     => !empty($settings['tab_number']) ? intval($settings['tab_number']) : 3,
                        "slidesToScroll" 	=> !empty($settings['tab_number']) ? intval($settings['tab_number']) : 3,
                    ]
                ],
                [
                    "breakpoint"     => 768,
                    "settings"     => [
                        "slidesToShow"     => !empty($settings['mob_number']) ? intval($settings['mob_number']) : 2,
                        "slidesToScroll" 	=> !empty($settings['mob_number']) ? intval($settings['mob_number']) : 2,
                    ]
                    ],
                    [
                        "breakpoint"     => 360,
                        "settings"     => [
                            "slidesToShow"     => !empty($settings['mob_number']) ? intval($settings['mob_number']) : 2,
                            "slidesToScroll" 	=> !empty($settings['mob_number']) ? intval($settings['mob_number']) : 2,
                        ]
                    ]
            ]
        );


        $post_content = array();
        $results = streamit_get_continue_watching(get_current_user_id());

        if (empty($results)) return;

        foreach (['movie', 'video', 'episode'] as $post_type) {

            if (isset($results[$post_type]) && !empty($results[$post_type]) && is_array($results[$post_type])) {

                foreach ($results[$post_type] as $post_id => $data) {
                    $function_name = 'streamit_get_' . $post_type;
                    if (function_exists($function_name)) {
                        $post_data = call_user_func($function_name, (int)$post_id);
                        if (!is_wp_error($post_data) && !empty($post_data)) {
                            $watched_time               = isset($data['watched_time']) ? $data['watched_time'] : 0;
                            $watched_total_time         = isset($data['watched_total_time']) ? $data['watched_total_time'] : 0;
                            $watched_time_percentage    = isset($data['watched_time_percentage']) ? $data['watched_time_percentage'] : 0;

                            // Collect all relevant data.
                            $post_content[] = array(
                                'post_data'                 => $post_data,
                                'watched_time'              => $watched_time,
                                'watched_total_time'        => $watched_total_time,
                                'watched_time_percentage'   => $watched_time_percentage,
                            );
                        }
                    }
                }
            }
        }

        if (empty($post_content)) return;
        $title_tag = isset($settings['title_tag']) ? sanitize_key($settings['title_tag']) : 'h3'; // Default tag if not set
        $slider_title = isset($settings['slider_title']) ? sanitize_text_field($settings['slider_title']) : ''; // Sanitize slider title

        streamit_get_template(
            'elementor-widget/continue-watching/html-continue-watching.php',
            [
                'slick_settings'    => $slick_settings,
                'post_content'      => $post_content,
                'settings'          => $settings,
                'slider_title'     => $slider_title,
                'title_tag'         => $title_tag
            ]
        );
    }
}

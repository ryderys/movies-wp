<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_Terms extends Widget_Base
{


    public function get_name()
    {
        return 'genere-tag-card';
    }

    public function get_title()
    {
        return __('Genre/Tag Card', 'streamit');
    }

    public function get_icon()
    {
        return 'eicon-slider-push';
    }

    public function get_categories()
    {
        return ['streamit'];
    }

    public function get_slider_name(): string
    {
        return 'genere-tag-card';
    }

    protected function register_controls()
    {

        $this->start_controls_section(
            'section_term_template',
            [
                'label' => __('Template Settings', 'streamit'),
            ]
        );


        $this->add_control(
            'slider_title',
            [
                'label'     => __('Title', 'streamit'),
                'type'      => Controls_Manager::TEXT,
                'default'   => __('Genre / Tag Slider', 'streamit'),
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


        $this->add_control(
            'slider_type',
            [
                'label'         => __('Style', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'options'       => [
                    'slider'        => __('Slider', 'streamit'),
                    'grid'          => __('Grid', 'streamit')
                ],
                'default'           => 'slider'
            ]
        );

        $this->end_controls_section();


        $this->start_controls_section(
            'section_data_control',
            [
                'label'         => __('Content Controls', 'streamit'),
            ]
        );

        $this->add_control(
            'st_post_type',
            [
                'label'         => __('Select Post Type', 'streamit'),
                'type'          => Controls_Manager::SELECT2,
                'label_block'   => true,
                'multiple'      => false,
                'options'       => [
                    'movie'        => __('Movie', 'streamit'),
                    'tvshow'       => __('Tv Show', 'streamit'),
                    'video'        => __('Video', 'streamit')
                ],
                'default'        => 'movie',
            ]
        );

        $this->add_control(
            'st_term_type',
            [
                'label'         => __('Select Term Type', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'label_block'   => true,
                'multiple'      => false,
                'options'       => [
                    'genre'        => __('Genre', 'streamit'),
                    'tag'          => __('Tag', 'streamit'),
                ],
                'default'          => 'genre',
            ]
        );


        $this->add_control(
            'st_terms_movie_genre',
            [
                'label'             => __('Select Movie Genre', 'streamit'),
                'type'              => 'st_ajax_select',
                'ajax_action'       => 'streamit_elementor_select_ajax',
                'multiple'          => true,
                'placeholder'       => __('Select items...', 'streamit'),
                'custom_attributes' => array(
                    'data-ajax-params' => wp_json_encode([
                        'callback'         => 'streamit_get_term_list',
                        'argument'         => ['per_page' => 100, 'taxonomy' => ['movie_genre']]
                    ])
                ),
                'condition'     =>  [
                    'st_term_type'      =>  'genre',
                    'st_post_type'      =>  'movie'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        $this->add_control(
            'st_terms_video_genre',
            [
                'label'             => __('Select Video Genre', 'streamit'),
                'type'              => 'st_ajax_select',
                'ajax_action'       => 'streamit_elementor_select_ajax',
                'multiple'          => true,
                'placeholder'       => __('Select items...', 'streamit'),
                'custom_attributes' => array(
                    'data-ajax-params' => wp_json_encode([
                        'callback'         => 'streamit_get_term_list',
                        'argument'         => ['per_page' => 100, 'taxonomy' => ['video_category']]
                    ])
                ),
                'condition'     =>  [
                    'st_term_type'      =>  'genre',
                    'st_post_type'      =>  'video'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        $this->add_control(
            'st_terms_tvshow_genre',
            [
                'label'             => __('Select TV-Show Genre', 'streamit'),
                'type'              => 'st_ajax_select',
                'ajax_action'       => 'streamit_elementor_select_ajax',
                'multiple'          => true,
                'placeholder'       => __('Select items...', 'streamit'),
                'custom_attributes' => array(
                    'data-ajax-params' => wp_json_encode([
                        'callback'         => 'streamit_get_term_list',
                        'argument'         => ['per_page' => 100, 'taxonomy' => ['tvshow_genre']]
                    ])
                ),
                'condition'     =>  [
                    'st_term_type'      =>  'genre',
                    'st_post_type'      =>  'tvshow'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );


        $this->add_control(
            'st_terms_movie_tag',
            [
                'label'             => __('Select Movie Tag', 'streamit'),
                'type'              => 'st_ajax_select',
                'ajax_action'       => 'streamit_elementor_select_ajax',
                'multiple'          => true,
                'placeholder'       => __('Select items...', 'streamit'),
                'custom_attributes' => array(
                    'data-ajax-params' => wp_json_encode([
                        'callback'         => 'streamit_get_term_list',
                        'argument'         => ['per_page' => 100, 'taxonomy' => ['movie_tag']]
                    ])
                ),
                'condition'     =>  [
                    'st_term_type'      =>  'tag',
                    'st_post_type'      =>  'movie'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        $this->add_control(
            'st_terms_video_tag',
            [
                'label'             => __('Select Video Tag', 'streamit'),
                'type'              => 'st_ajax_select',
                'ajax_action'       => 'streamit_elementor_select_ajax',
                'multiple'          => true,
                'placeholder'       => __('Select items...', 'streamit'),
                'custom_attributes' => array(
                    'data-ajax-params' => wp_json_encode([
                        'callback'         => 'streamit_get_term_list',
                        'argument'         => ['per_page' => 100, 'taxonomy' => ['video_tag']]
                    ])
                ),
                'condition'     =>  [
                    'st_term_type'      =>  'tag',
                    'st_post_type'      =>  'video'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        $this->add_control(
            'st_terms_tvshow_tag',
            [
                'label'             => __('Select TV-Show Tag', 'streamit'),
                'type'              => 'st_ajax_select',
                'ajax_action'       => 'streamit_elementor_select_ajax',
                'multiple'          => true,
                'placeholder'       => __('Select items...', 'streamit'),
                'custom_attributes' => array(
                    'data-ajax-params' => wp_json_encode([
                        'callback'         => 'streamit_get_term_list',
                        'argument'         => ['per_page' => 100, 'taxonomy' => ['tvshow_tag']]
                    ])
                ),
                'condition'     =>  [
                    'st_term_type'      =>  'tag',
                    'st_post_type'      =>  'tvshow'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        // Adding a switch control
        $this->add_control(
            'st_viewall_button',
            [
                'label'        => __('View All Button', 'streamit'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'streamit'),
                'label_off'    => __('No', 'streamit'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'st_viewall_text',
            [
                'label'       => __('View All Text', 'streamit'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('View All', 'streamit'),
                'condition'   => [
                    'st_viewall_button' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_slider',
            [
                'label' => __('Slider Controls', 'streamit'),
            ]
        );

        require get_template_directory() . '/inc/Elementor-widget/controls/slick-control.php';

        $this->end_controls_section();
    }


    protected function render()
    {
        $settings = $this->get_settings_for_display();

        $slick_settings = array(
            "dots"               => false,
            "slidesToShow"       => !empty($settings['desk_number']) ? intval($settings['desk_number']) : 6,
            "slidesToScroll"     => !empty($settings['desk_number']) ? intval($settings['desk_number']) : 6,
            "arrows"             => !empty($settings['nav-arrow']) && $settings['nav-arrow'] === "true" ? true : false,
            "autoplay"           => !empty($settings['autoplay']) && $settings['autoplay'] === "true" ? true : false,
            "autoplaySpeed"      => !empty($settings['autoplay_speed']) ?  intval($settings['autoplay_speed']) : 2000,
            "speed"              => !empty($settings['speed']) ?  intval($settings['speed']) : 300,
            "infinite"           => !empty($settings['infinite']) && $settings['infinite'] === "true" ? true : false,
            "responsive"         => [
                [
                    "breakpoint"        => 1367,
                    "settings"          => [
                        "slidesToShow"      => !empty($settings['lap_number']) ? intval($settings['lap_number']) : 4,
                        "slidesToScroll"    => !empty($settings['lap_number']) ? intval($settings['lap_number']) : 4,
                    ]
                ],
                [
                    "breakpoint"        => 1025,
                    "settings"          => [
                        "slidesToShow"      => !empty($settings['tab_number']) ? intval($settings['tab_number']) : 3,
                        "slidesToScroll"    => !empty($settings['tab_number']) ? intval($settings['tab_number']) : 3,
                    ]
                ],
                [
                    "breakpoint"        => 768,
                    "settings"          => [
                        "slidesToShow"      => !empty($settings['mob_number']) ? intval($settings['mob_number']) : 2,
                        "slidesToScroll"    => !empty($settings['mob_number']) ? intval($settings['mob_number']) : 2,
                    ]
                ]
            ]
        );


        $template_type  =   $settings['slider_type'];
        $post_type      =   $settings['st_post_type'];

        // Mapping array for post types
        $post_type_mapping = [
            'movie'     => 'movie_' . $settings['st_term_type'],
            'tvshow'    => 'tvshow_' . $settings['st_term_type'],
            'video'     => $settings['st_term_type'] === 'genre' ? 'video_category' : 'video_tag'
        ];

        $term_args = array();

        //get texonomy
        $current_texonomy = $post_type_mapping[$post_type];

        $settings['view_all_button'] = ($settings['st_viewall_button'] == 'yes') ? streamit_get_permalink($current_texonomy) : '';

        //filters
        $selected_settings = 'st_terms_' . $post_type . '_' . $settings['st_term_type'];
        $includes = !empty($settings[$selected_settings]) ? $settings[$selected_settings] : array();
        $term_args['include'] = $includes;


        $term_args['taxonomy'] = array($current_texonomy);

        // Sanitize the title tag and provide a fallback.
        $title_tag = isset($settings['title_tag']) && !empty($settings['title_tag'])
            ? esc_attr($settings['title_tag'])
            : 'h2';

        // Sanitize the slider title.
        $slider_title = isset($settings['slider_title']) && !empty($settings['slider_title'])
            ? esc_html($settings['slider_title'])
            : '';

        // Sanitize the View All button URL.
        $view_all_url = isset($settings['view_all_button']) && !empty($settings['view_all_button'])
            ? esc_url($settings['view_all_button'])
            : '';

        // Sanitize the View All button text.
        $view_all_text = isset($settings['st_viewall_text']) && !empty($settings['st_viewall_text'])
            ? esc_html($settings['st_viewall_text'])
            : '';

        if (!empty($includes))
            $term_args['per_page'] = -1;
        //get terms
        $term_object = streamit_get_terms($term_args)->results;
        streamit_get_template(
            'elementor-widget/genre-tag-card/html-term-' . $settings['st_term_type'] . '-' . $template_type . '.php',
            [
                'slick_settings'    => $slick_settings,
                'term_object'       => $term_object,
                'settings'          => $settings,
                'title_tag'         => $title_tag,
                'slider_title'      =>  $slider_title,
                'view_all_url'      => $view_all_url,
                'view_all_text'     => $view_all_text
            ]
        );
    }
}

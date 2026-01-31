<?php

namespace Elementor;

use Elementor\Widget_Base;

defined('ABSPATH') || exit;

class ST_Top_Ten extends Widget_Base
{
    public function get_name()
    {
        return 'top-ten-slider';
    }

    public function get_title()
    {
        return __('Top Ten Slider', 'streamit');
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
        return 'top-ten-slider';
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_term_template',
            [
                'label'         =>  __('Template Settings', 'streamit'),
            ]
        );

        $this->add_control(
            'slider_title',
            [
                'label'         =>  __('Title', 'streamit'),
                'type'          =>  Controls_Manager::TEXT,
                'default'       =>  __('Top 10', 'streamit'),
                'label_block'   =>  true,
            ]
        );

        $this->add_control(
            'title_tag',
            [
                'label'         =>  __('Title Tag', 'streamit'),
                'type'          =>  Controls_Manager::SELECT,
                'options'       =>  [
                    'h1'        =>  __('h1', 'streamit'),
                    'h2'        =>  __('h2', 'streamit'),
                    'h3'        =>  __('h3', 'streamit'),
                    'h4'        =>  __('h4', 'streamit'),
                    'h5'        =>  __('h5', 'streamit'),
                    'h6'        =>  __('h6', 'streamit'),
                ],
                'default'       =>  'h4',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_data_control',
            [
                'label'         =>  __('Content Controls', 'streamit'),
            ]
        );

        $this->add_control(
            'st_post_type',
            [
                'label'         =>  __('Select Post Type', 'streamit'),
                'type'          =>  Controls_Manager::SELECT2,
                'label_block'   =>  true,
                'multiple'      =>  false,
                'options'       =>  [
                    'movie'     =>  __('Movie', 'streamit'),
                    'tvshow'    =>  __('Tv Show', 'streamit'),
                    'video'     =>  __('Video', 'streamit')
                ],
                'default'       =>  'movie',
            ]
        );

        $this->add_control(
            'st_select_post_filter',
            [
                'label'         =>  __('Select Post Filter', 'streamit'),
                'type'          =>  Controls_Manager::SELECT,
                'label_block'   =>  true,
                'multiple'      =>  false,
                'options'       =>  [
                    'selected'     =>  'Selected',
                    'genre'        =>  'Genre',
                    'tag'          =>  'Tags',
                    'upcoming'     =>  'Upcoming',
                    'latest'       =>  'Latest',
                    'most_like'    =>  'Most Like',
                    'most_viewed'  =>  'Most Viewed',
                ],
                'default'       =>  'most_viewed'
            ]
        );

        $this->add_control(
            'st_selected_movies',
            [
                'label'         => __('Select Movies', 'streamit'),
                'type'          => 'st_ajax_select',
                'ajax_action'   => 'streamit_elementor_select_ajax',
                'multiple'      => true,
                'placeholder'   => __('Select items...', 'streamit'),
                'custom_attributes'  => array(
                    'data-ajax-params'  => wp_json_encode([
                        'callback'      => 'streamit_get_recommended_movies',
                        'argument'      => ['per_page' => 100 ]
                    ])
                ),
                'condition'     =>  [
                    'st_post_type'           =>  'movie',
                    'st_select_post_filter'  =>  'selected'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        $this->add_control(
            'st_selected_tvshows',
            [
                'label'         => __('Select Tv Shows', 'streamit'),
                'type'          => 'st_ajax_select',
                'ajax_action'   => 'streamit_elementor_select_ajax',
                'multiple'      => true,
                'placeholder'   => __('Select items...', 'streamit'),
                'custom_attributes' => array(
                    'data-ajax-params'  => wp_json_encode([
                        'callback'      => 'streamit_get_recommended_tvshows',
                        'argument'      => ['per_page' => 100]
                    ])
                ),
                'condition'     =>  [
                    'st_post_type'           =>  'tvshow',
                    'st_select_post_filter'  =>  'selected'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        $this->add_control(
            'st_selected_videos',
            [
                'label'         => __('Select Video', 'streamit'),
                'type'          => 'st_ajax_select',
                'ajax_action'   => 'streamit_elementor_select_ajax',
                'multiple'      => true,
                'placeholder'   => __('Select items...', 'streamit'),
                'custom_attributes' => array(
                    'data-ajax-params'  => wp_json_encode([
                        'callback'      => 'streamit_get_recommended_videos',
                        'argument'      => ['per_page' => 100]
                    ])
                ),
                'condition'     =>  [
                    'st_post_type'           =>  'video',
                    'st_select_post_filter'  =>  'selected'
                ],
            ]
        );


        $this->add_control(
            'st_selected_movie_genre',
            [
                'label'             => __('Display Movie From Specific Genre', 'streamit'),
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
                    'st_post_type'           =>  'movie',
                    'st_select_post_filter'  =>  'genre'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );


		$this->add_control(
            'st_selected_tvshow_genre',
            [
                'label'             => __('Display Tv Show From Specific Genre', 'streamit'),
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
                    'st_post_type'           =>  'tvshow',
                    'st_select_post_filter'  =>  'genre'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        $this->add_control(
            'st_selected_video_genre',
            [
                'label'             => __('Display Video From Specific Genre', 'streamit'),
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
                    'st_post_type'           =>  'video',
                    'st_select_post_filter'  =>  'genre'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        $this->add_control(
            'st_selected_movie_tag',
            [
                'label'             => __('Display Movie From Specific Tag', 'streamit'),
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
                    'st_post_type'           =>  'movie',
                    'st_select_post_filter'  =>  'tag'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );


        $this->add_control(
            'st_selected_tvshow_tag',
            [
                'label'             => __('Display Tv Show From Specific Tag', 'streamit'),
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
                    'st_post_type'           =>  'tvshow',
                    'st_select_post_filter'  =>  'tag'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

		$this->add_control(
            'st_selected_video_tag',
            [
                'label'             => __('Display Video From Specific Tag', 'streamit'),
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
                    'st_post_type'           =>  'video',
                    'st_select_post_filter'  =>  'tag'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        $this->add_responsive_control(
            'posts_per_page',
            [
                'label'         =>  __('Posts Per Page', 'streamit'),
                'type'          =>  Controls_Manager::SLIDER,
                'default'       =>  [
                    'unit'      =>  '%',
                    'size'      =>  10,
                ],
                'condition'     =>  [
                    'st_select_post_filter!'  =>  'selected',
                ]
            ]
        );

        $this->add_control(
            'enable_premium_badges',
            [
                'label'         =>  __('Enable Premium & PPV Badges', 'streamit'),
                'type'          =>  Controls_Manager::SWITCHER,
                'yes'           =>  __('Yes', 'streamit'),
                'no'            =>  __('No', 'streamit'),
                'return_value'  =>  'yes',
                'default'       =>  'yes',
            ]
        );

        $this->add_responsive_control(
            'number_size',
            [
                'label'         =>  __('Number Size', 'streamit'),
                'type'          =>  Controls_Manager::SLIDER,
                'size_units'    =>  ['em', 'px', 'rem'],
                'range'         =>  [
                    'em'    =>  [
                        'min'   =>  1,
                        'max'   =>  15,
                        'step'  =>  0.1,
                    ],
                    'px'    =>  [
                        'min'   =>  10,
                        'max'   =>  200,
                        'step'  =>  1,
                    ],
                    'rem'   =>  [
                        'min'   =>  1,
                        'max'   =>  15,
                        'step'  =>  0.1,
                    ],
                ],
                'default'       =>  [
                    'unit'  =>  'em',
                    'size'  =>  7.5,
                ],
                'tablet_default' =>  [
                    'unit'  =>  'em',
                    'size'  =>  6,
                ],
                'mobile_default' =>  [
                    'unit'  =>  'em',
                    'size'  =>  4.5,
                ],
                'selectors'     =>  [
                    '{{WRAPPER}} .top_ten_numbers' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_slider',
            [
                'label'         =>  __('Slider Controls', 'streamit'),
            ]
        );

        require get_template_directory() . '/inc/Elementor-widget/controls/slick-control.php';

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings  =  $this->get_settings_for_display();

        $get   =  fn($key, $default) => !empty($settings[$key]) ? intval($settings[$key]) : $default;
        $true  =  fn($key) => !empty($settings[$key]) && $settings[$key] === 'true';

        $slick_settings  =  [
            "dots"            =>  false,
            "slidesToShow"    =>  $get('desk_number', 6),
            "slidesToScroll"  =>  $get('desk_number', 6),
            "arrows"          =>  $true('nav-arrow'),
            "autoplay"        =>  $true('autoplay'),
            "autoplaySpeed"   =>  $get('autoplay_speed', 2000),
            "speed"           =>  $get('speed', 300),
            "infinite"        =>  $true('infinite'),
            "responsive"      =>  [
                [
                    "breakpoint"  =>  1367,
                    "settings"    =>  [
                        "slidesToShow"    =>  $get('lap_number', 4),
                        "slidesToScroll"  =>  $get('lap_number', 4),
                    ]
                ],
                [
                    "breakpoint" => 1025,
                    "settings"   => [
                        "slidesToShow"    =>  $get('tab_number', 3),
                        "slidesToScroll"  =>  $get('tab_number', 3),
                    ]
                ],
                [
                    "breakpoint"  =>  768,
                    "settings"    =>  [
                        "slidesToShow"    =>  $get('mob_number', 2),
                        "slidesToScroll"  =>  $get('mob_number', 2),
                    ]
                ]
            ]
        ];

        $post_type    =  $settings['st_post_type'];
        $post_filter  =  $settings['st_select_post_filter'];
        $per_page     =  !empty($settings['posts_per_page']['size']) ? $settings['posts_per_page']['size'] : 10;

        $result       =  [];

        $types        =  ['movie', 'tvshow', 'video'];
        $content_map  =  [];

        foreach ($types as $type) {
            $content_map[$type]  =  [
                'selected_items' =>  $settings["st_selected_{$type}s"],
                'get_function'   =>  "streamit_get_{$type}s",
                'tag_key'        =>  $settings["st_selected_{$type}_tag"],
                'genre_key'      =>  $settings["st_selected_{$type}_genre"]
            ];
        }

    	$upcoming_meta_key = "{$post_type}_upcoming_status";


        if (isset($content_map[$post_type])) {

            $content_settings  =  $content_map[$post_type];
            $get_function      =  $content_settings['get_function'];
            $args              =  ['per_page' => $per_page, 'paged' => 1];

            $effective_post_filter = $post_filter;

            if (($post_filter === 'selected' && empty($content_settings['selected_items'])) ||
                (in_array($post_filter, ['genre', 'tag']) && empty($content_settings[$post_filter === 'tag' ? 'tag_key' : 'genre_key']))
            ) {
                $effective_post_filter  =  'most_viewed';
            }

            switch ($effective_post_filter) {

                case 'selected':
                    if (!empty($content_settings['selected_items'])) :
                        $args['include']    =  array_map('intval', $content_settings['selected_items']);
                        $args['per_page']   =  -1;
                        $args['orderby']   =  'post__in';
                    endif;
                    break;

                case 'upcoming':
                   $args['meta_query'] = [
						[
							'key' => $upcoming_meta_key, 
							'value' => '1', 
							'compare' => '='
						]
					];
                    break;

                case 'latest':
                    $args['orderby']    =  'post_date';
                    $args['order']      =  'DESC';
                    $args['meta_query'] = [
						'relation' => 'OR',
						[
							'key'     => $upcoming_meta_key,
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => $upcoming_meta_key,
							'value'   => '0',
							'compare' => '=',
						],
					];
                    break;

                case 'most_like':
                    $args['meta_query'] = [
						'relation' => 'OR',
						[
							'key'     => 'streamit_post_like_count',
							'value'   => 0,
							'compare' => '>='
						]
					];

                    $args['orderby']    =  'meta_value_num';
                    $args['meta_key']   =  'streamit_post_like_count';
                    $args['order']      =  'DESC';
                    break;

                case 'most_viewed':
                    $args['orderby']    =  'meta_value_num';
                    $args['order']      =  'DESC';
                    $args['meta_key']   =  'post_views_count';
                    $args['meta_query'] =  array(
                        'relation' => 'OR',
                        array(
                            'key'       =>  $upcoming_meta_key,
                            'value'     =>  '0',
                            'compare'   =>  '='
                        ),
                        array(
                            'key'       =>  $upcoming_meta_key,
                            'compare'   =>  'NOT EXISTS'
                        )
                    );
                    break;

                default:
                    $selected_terms_ids = $content_settings[$effective_post_filter === 'tag' ? 'tag_key' : 'genre_key'] ?? [];
                    $tax_query          =  array('relation' => 'OR');

                    foreach ($selected_terms_ids as $term_id) {
                        $tax_query[] = array(
                            'field'     =>  'term_id',
                            'terms'     =>  intval($term_id),
                            'operator'  =>  '=',
                        );
                    }

                    $args['tax_query']  =  $tax_query;
                    break;
            }
            $result  =  ($get_function($args))->results ?? [];
        }

        streamit_get_template(
            'elementor-widget/top-ten-slider/html-top-ten-slider.php',
            [
                'slick_settings'  =>  $slick_settings,
                'result'          =>  $result,
                'settings'        =>  $settings,
                'slider_title'    =>  $settings['slider_title']
            ]
        );
    }
}

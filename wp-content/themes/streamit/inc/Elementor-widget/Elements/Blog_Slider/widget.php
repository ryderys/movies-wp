<?php

namespace Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class ST_Blog_Slider extends Widget_Base {

    public function get_name() {
        return 'streamit_blog_slider';
    }

    public function get_title() {
        return esc_html__('Streamit Blog Slider', 'streamit');
    }

    public function get_icon() {
        return 'eicon-slider-push';
    }

    public function get_categories() {
        return ['streamit'];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_slider',
            [
                'label' => __('Slider Settings', 'streamit'),
            ]
        );


        $this->add_control(
            'post_filter',
            [
                'label' => __('Posts Filter', 'streamit'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'selected' => __('Selected', 'streamit'),
                    'default'  => __('Dynamic', 'streamit')
                ],
                'default' => 'default'
            ]
        );

        $this->add_control(
            'selected_posts',
            [
                'label' => __('Select Posts', 'streamit'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $this->get_dynamic_posts('post'),
                'label_block' => true,
                'condition' => ['post_filter' => 'selected'],
            ]
        );

        // Step 3B: If dynamic, show posts per page
        $this->add_control(
            'slides_per_page',
            [
                'label' => __('Slides Per Page', 'streamit'),
                'type' => Controls_Manager::SLIDER,
                'default' => ['size' => 3],
                'condition' => ['post_filter' => 'default'],
            ]
        );

        // Sort Options
        $this->add_control(
            'order_by',
            [
                'label' => __('Order By', 'streamit'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'date'  => __('Date', 'streamit'),
                    'title' => __('Title', 'streamit'),
                    'rand'  => __('Random', 'streamit'),
                ],
                'default' => 'date',
                'condition' => ['post_filter' => 'default'],
            ]
        );

        // Show title toggle
        $this->add_control(
            'show_post_title',
            [
                'label' => __('Show Post Title', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
            ]
        );

        $this->add_control(
			'slider_title',
			[
				'label'			=> __('Title', 'streamit'),
				'type'			=> Controls_Manager::TEXT,
				'default'		=> __('Blog Slider', 'streamit'),
				'label_block'	=> true,
			]
		);

        $this->add_control(
            'title_tag',
            [
                'label' => __('Title Tag', 'streamit'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6',
                ],
                'default' => 'h3',
            ]
        );

        $this->end_controls_section();

        // Slider Settings
        $this->start_controls_section(
            'slider_options',
            [
                'label' => __('Slider Options', 'streamit'),
            ]
        );

        $this->add_control(
            'autoplay',
            [
                'label' => __('Autoplay', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'infinite',
            [
                'label' => __('Infinite Loop', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'true',
            ]
        );

        $this->add_control(
            'speed',
            [
                'label' => __('Speed', 'streamit'),
                'type' => Controls_Manager::NUMBER,
                'default' => 500,
            ]
        );

        $this->add_control(
            'arrows',
            [
                'label' => __('Navigation Arrows', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Responsive Slides
        $this->start_controls_section(
            'slider_responsive',
            [
                'label' => __('Responsive Settings', 'streamit'),
            ]
        );

        $this->add_control(
            'slides_laptop',
            [
                'label' => __('Slides on Laptop (1366px)', 'streamit'),
                'type' => Controls_Manager::NUMBER,
                'default' => 3,
            ]
        );

        $this->add_control(
            'slides_tablet',
            [
                'label' => __('Slides on Tablet (1024px)', 'streamit'),
                'type' => Controls_Manager::NUMBER,
                'default' => 2,
            ]
        );

        $this->add_control(
            'slides_mobile',
            [
                'label' => __('Slides on Mobile (768px)', 'streamit'),
                'type' => Controls_Manager::NUMBER,
                'default' => 1,
            ]
        );

        $this->end_controls_section();
    }

    private function get_available_post_types() {
        $post_types = get_post_types(['public' => true], 'objects');
        $options = [];

        foreach ($post_types as $post_type) {
            $options[$post_type->name] = $post_type->label;
        }

        return $options;
    }

    public function get_dynamic_posts($post_type, $post_ids = []) {
 
        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];

        if (!empty($post_ids)) {
            $args['post__in'] = $post_ids;
            $args['orderby']  = 'post__in';
        }

        $query = new \WP_Query($args);
        $posts = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $posts[get_the_ID()] = get_the_title();
            }
            wp_reset_postdata();
        }

        return $posts;
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $show_post_title = isset($settings['show_post_title']) && $settings['show_post_title'] === 'yes';
        $filter = $settings['post_filter'];
        $default_slides_per_page = !empty($settings['slides_per_page']['size']) ? intval($settings['slides_per_page']['size']) : 2;

        $slides = [];

        $query_args = [
            'post_type'      => 'post',
            'posts_per_page' => 10,
            'orderby'        => $settings['order_by'] ?? 'date',
            'order'          => 'DESC',
        ];

        if ($filter === 'selected' && !empty($settings['selected_posts'])) {
            $post_ids = array_map('absint', $settings['selected_posts']);
            $query_args['post__in'] = $post_ids;
            $query_args['orderby']  = 'post__in';
            $query_args['posts_per_page'] = count($post_ids);
        } elseif ($filter === 'default') {
            $query_args['posts_per_page'] = $settings['slides_per_page']['size'] ?? 3;
        }

        $query = new \WP_Query($query_args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $slides[] = [
                    'post_id' => get_the_ID(),
                    'title'   => get_the_title(),
                    'link'    => get_permalink(),
                    'image'   => has_post_thumbnail() ? get_the_post_thumbnail_url(null, 'full') : streamit_placeholder_image()
                ];
            }
            wp_reset_postdata();
        }
          // Handle slider settings
          $slider_options = [
            'autoplay'       => isset($settings['autoplay']) && $settings['autoplay'] === 'yes',
            'autoplaySpeed'  => !empty($settings['autoplay_speed']) ? intval($settings['autoplay_speed']) : 2000,
            'speed'          => !empty($settings['speed']) ? intval($settings['speed']) : 300,
            'arrows'         => isset($settings['arrows']) && $settings['arrows'] === 'yes',
            'infinite'       => isset($settings['infinite']) && $settings['infinite'] === 'true',
        ];

        $slick_settings = [
            "dots"           => false,
            "slidesToShow"   => !empty($settings['slides_laptop']) ? intval($settings['slides_laptop']) : 6,
            "slidesToScroll" => !empty($settings['slides_laptop']) ? intval($settings['slides_laptop']) : 6,
            "arrows"         => $slider_options['arrows'],
            "autoplay"       => $slider_options['autoplay'],
            "autoplaySpeed"  => $slider_options['autoplaySpeed'],
            "speed"          => $slider_options['speed'],
            "infinite"       => $slider_options['infinite'],
            "responsive"     => [
                [
                    "breakpoint" => 1366,
                    "settings"   => [
                        "slidesToShow"   => !empty($settings['slides_laptop']) ? intval($settings['slides_laptop']) : 6,
                        "slidesToScroll" => !empty($settings['slides_laptop']) ? intval($settings['slides_laptop']) : 6,
                    ]
                ],
                [
                    "breakpoint" => 1024,
                    "settings"   => [
                        "slidesToShow"   => !empty($settings['slides_tablet']) ? intval($settings['slides_tablet']) : 3,
                        "slidesToScroll" => !empty($settings['slides_tablet']) ? intval($settings['slides_tablet']) : 3,
                    ]
                ],
                [
                    "breakpoint" => 768,
                    "settings"   => [
                        "slidesToShow"   => !empty($settings['slides_mobile']) ? intval($settings['slides_mobile']) : 2,
                        "slidesToScroll" => !empty($settings['slides_mobile']) ? intval($settings['slides_mobile']) : 2,
                    ]
                ]
            ]
        ];

        $title_tag = isset($settings['title_tag']) ? sanitize_text_field($settings['title_tag']) : 'h3';
        $slider_title = isset($settings['slider_title']) ? esc_html($settings['slider_title']) : '';
        streamit_get_template(
            'elementor-widget/blog_slider/html-blog-slider.php',
            [
                'slider_options'  => $slider_options,
                'slick_settings'  => $slick_settings,
                'slides'          => $slides,
                'show_post_title' => $show_post_title,
                'title_tag'       => $title_tag,
				'slider_title'    => $slider_title,
            ]
        );
    }
}

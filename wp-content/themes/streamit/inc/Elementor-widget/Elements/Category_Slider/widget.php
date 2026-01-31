<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_Category_Slider extends Widget_Base
{
    public function get_name()
    {
        return 'st_product_category_slider';
    }

    public function get_title()
    {
        return __('Product Category', 'streamit');
    }

    public function get_icon()
    {
        return 'eicon-slider-album';
    }

    public function get_categories()
    {
        return ['streamit'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_blog',
            [
                'label'         => __('Category', 'streamit'),
            ]
        );

        $this->add_control(
            'woo_cat_data',
            [
                'label'         => __('Select Category', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'options'       => [
                    'dynamic'         => __('Dynamic', 'streamit'),
                    'static'          => __('Static', 'streamit'),
                ],
                'default'       => 'dynamic',
            ]
        );

        $repeater = new Repeater();
        $repeater->add_control(
            'category_title_list',
            [
                'label'         => __('Choose Category', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'label_block'   => true,
                'multiple'      => true,
                'options'       => isset($_REQUEST['editor_post_id'])  && function_exists('streamit_get_all_taxonomies') ? streamit_get_all_taxonomies('product_cat') : [],
            ]
        );

        $this->add_control(
            'tabs',
            [
                'label'         => __('Lists Items', 'streamit'),
                'type'          => Controls_Manager::REPEATER,
                'fields'        => $repeater->get_controls(),
                'condition'     => ['woo_cat_data' => 'static'],
            ]
        );

        $this->add_control(
            'woo_order_by',
            [
                'label'         => __('Order By', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'default'       => 'date',
                'options'       => [
                    'menu_order'    => esc_html__('Menu Order', 'streamit'),
                    'title'         => esc_html__('Title', 'streamit'),
                    'date'          => esc_html__('Date', 'streamit'),
                    'rand'          => esc_html__('Rand', 'streamit'),
                    'id'            => esc_html__('Id', 'streamit')
                ],
                'condition'     => ['woo_cat_data!' => 'static'],
            ]
        );

        $this->add_control(
            'woo_order',
            [
                'label'         => __('Order', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'default'       => 'ASC',
                'options'       => [
                    'DESC'          => esc_html__('Descending', 'streamit'),
                    'ASC'           => esc_html__('Ascending', 'streamit')
                ],
                'condition'     => ['woo_cat_data!' => 'static'],
            ]
        );

        $this->add_control(
            'title_tag',
            [
                'label'      => __('Title Tag', 'streamit'),
                'type'       => Controls_Manager::SELECT,
                'default'    => 'h5',
                'options'    => [
                    'h1'          => __('h1', 'streamit'),
                    'h2'          => __('h2', 'streamit'),
                    'h3'          => __('h3', 'streamit'),
                    'h4'          => __('h4', 'streamit'),
                    'h5'          => __('h5', 'streamit'),
                    'h6'          => __('h6', 'streamit'),
                ],
            ]
        );

        $this->add_responsive_control(
            'content_align',
            [
                'label'         => __('Alignment', 'streamit'),
                'type'          => Controls_Manager::CHOOSE,
                'options'       => [
                    'left'          => [
                        'title'             => __('Left', 'streamit'),
                        'icon'              => 'eicon-text-align-left',
                    ],
                    'center'    => [
                        'title'     => __('Center', 'streamit'),
                        'icon'      => 'eicon-text-align-center',
                    ],
                    'right'     => [
                        'title'     => __('Right', 'streamit'),
                        'icon'      => 'eicon-text-align-right',
                    ]
                ],
                'default'       => 'center',
                'selectors'     => [
                    '{{WRAPPER}} .css_prefix-category-details' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();



        $this->start_controls_section(
            'slider_control_section',
            [
                'label' => __('Slider Control', 'streamit'),
            ]
        );


        $this->add_control(
            'desk_number',
            [
                'label' => __('Desktop view', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'label_block' => true,
                'default' => '4',
            ]
        );

        $this->add_control(
            'lap_number',
            [
                'label' => __('Laptop view', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'label_block' => true,
                'default' => '3',
            ]
        );


        $this->add_control(
            'tab_number',
            [
                'label' => __('Tablet view', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'label_block' => true,
                'default' => '3',
            ]
        );

        $this->add_control(
            'mob_number',
            [
                'label' => __('Mobile view', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'label_block' => true,
                'default' => '1',
            ]
        );

        $this->add_control(
            'autoplay',
            [
                'label' => __('Autoplay', 'streamit'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'true' => __('True', 'streamit'),
                    'false' => __('False', 'streamit'),
                ],
                'default' => 'false',
            ]
        );

        $this->add_control(
            'autoplay_speed',
            [
                'label' => __('Autoplay Speed', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'label_block' => false,
                'condition' => ['autoplay' => 'true'],
                'default' => '2000',
            ]
        );

        $this->add_control(
            'infinite',
            [
                'label' => __('Infinite', 'streamit'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'true' => __('True', 'streamit'),
                    'false' => __('False', 'streamit'),
                ],
                'default' => 'false',
            ]
        );

        $this->add_control(
            'speed',
            [
                'label' => __('Speed', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
                'default' => '300',
            ]
        );

        $this->add_control(
            'nav-arrow',
            [
                'label' => __('Arrow', 'streamit'),
                'type' => Controls_Manager::SELECT,
                'default' => 'true',
                'options' => [
                    'true' => __('True', 'streamit'),
                    'false' => __('False', 'streamit'),
                ],
            ]
        );


        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $slick_settings = array(
            "dots"               => false,
            "slidesToShow"       => !empty($settings['desk_number']) ? intval($settings['desk_number']) : 4,
            "slidesToScroll"     => !empty($settings['desk_number']) ? intval($settings['desk_number']) : 4,
            "arrows"              => !empty($settings['nav-arrow']) && $settings['nav-arrow'] === "true" ? true : false,
            "autoplay"              => !empty($settings['autoplay']) && $settings['autoplay'] === "true" ? true : false,
            "autoplaySpeed"      => !empty($settings['autoplay_speed']) ?  intval($settings['autoplay_speed']) : 2000,
            "speed"              => !empty($settings['speed']) ?  intval($settings['speed']) : 300,
            "infinite"           => !empty($settings['infinite']) && $settings['infinite'] === "true" ? true : false,
            "responsive"         => [
                [
                    "breakpoint"     => 1367,
                    "settings"         => [
                        "slidesToShow"     => !empty($settings['lap_number']) ? intval($settings['lap_number']) : 3,
                        "slidesToScroll"     => !empty($settings['lap_number']) ? intval($settings['lap_number']) : 3,
                    ]
                ],
                [
                    "breakpoint"     => 1025,
                    "settings"         => [
                        "slidesToShow"     => !empty($settings['tab_number']) ? intval($settings['tab_number']) : 3,
                        "slidesToScroll"     => !empty($settings['tab_number']) ? intval($settings['tab_number']) : 3,
                    ]
                ],
                [
                    "breakpoint"     => 768,
                    "settings"     => [
                        "slidesToShow"     => !empty($settings['mob_number']) ? intval($settings['mob_number']) : 1,
                        "slidesToScroll"     => !empty($settings['mob_number']) ? intval($settings['mob_number']) : 1,
                    ]
                ]
            ]
        );

        $orderby  = $settings['woo_order_by'];

        $args = array(
            'taxonomy'      => 'product_cat',
            'show_count'    => 0,
            'pad_counts'    => 0,
            'hierarchical'  => 1,
            'title_li'      => '',
            'hide_empty'    => 1,
            'orderby'       => $orderby,
            "order"         => $settings['woo_order'],
        );

        if ($settings['woo_cat_data'] !== 'dynamic') {
            $tabs = $settings['tabs'];
            foreach ($tabs as $index => $item) {
                $cate_list[] = $item['category_title_list'];
            }
            $args['slug']       = $cate_list;
            $args['order']      = 'ASC';
            $args['orderby']    = 'slug__in';
        }
        $woo_categories = get_categories($args);


        streamit_get_template(
            'elementor-widget/category-slider/html-category-slider.php',
            ['slick_settings' => $slick_settings, 'woo_categories' => $woo_categories, 'settings' => $settings]
        );
    }
}

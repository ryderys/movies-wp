<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_Product_Banner extends Widget_Base
{
    public function get_name()
    {
        return 'st_product_banner';
    }

    public function get_title()
    {
        return __('Product Banner', 'streamit');
    }

    public function get_icon()
    {
        return 'eicon-slider-3d';
    }

    public function get_categories()
    {
        return ['streamit'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_zhyghasgahs',
            [
                'label'         => __('Product Banner Slider', 'streamit'),
            ]
        );

        $this->add_control(
            'play_now_text',
            [
                'label'         => __('Shop Now Text', 'streamit'),
                'type'          => Controls_Manager::TEXT,
                'default'       => __('Shop Now', 'streamit'),
                'label_block'   => true,
                'condition'     => ['show_view_all_btn' => ['yes']]
            ]
        );

        $repeater = new Repeater();
        $repeater->add_control(
            'slider_image',
            [
                'label'         => __('Slider Image', 'streamit'),
                'type'          => Controls_Manager::MEDIA,
                'dynamic'       => [
                    'active'        => true,
                ],
                'default'       => [
                    'url'           => streamit_placeholder_image(),
                ],
            ]
        );

        $repeater->add_control(
            'link_type',
            [
                'label'         => __('Link Type', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'default'       => 'custom',
                'options'       => [
                    'dynamic'       => __('Dynamic', 'streamit'),
                    'custom'        => __('Custom', 'streamit'),
                ],
            ]
        );

        $repeater->add_control(
            'link_by',
            [
                'label'         => esc_html__('Select Category Or product', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'default'       => 'solid',
                'options'       => [
                    'category'      => esc_html__('Category', 'streamit'),
                    'product'       => esc_html__('Product', 'streamit'),
                ],
                'condition'     => [
                    'link_type'     => 'dynamic',
                ],
            ]
        );

        $repeater->add_control(
            'dynamic_link',
            [
                'label'         => esc_html__('Product List', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'return_value'  => 'true',
                'multiple'      => true,
                'condition'     => [
                    'link_type'     => 'dynamic',
                    'link_by'       => 'product'
                ],
                'options'       => isset($_REQUEST['editor_post_id']) && function_exists('streamit_custom_post_data') ? streamit_custom_post_data("product", 'slug') : array(),
            ]
        );

        $repeater->add_control(
            'dynamic_link_by_cat',
            [
                'label'         => esc_html__('Category List', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'return_value'  => 'true',
                'multiple'      => true,
                'condition'     => [
                    'link_type'     => 'dynamic',
                    'link_by'       => 'category'
                ],
                'options'       => isset($_REQUEST['editor_post_id']) && function_exists('streamit_get_all_taxonomies') ? streamit_get_all_taxonomies('product_cat') : array(),
            ]
        );

        $repeater->add_control(
            'link',
            [
                'label'         => esc_html__('Link', 'streamit'),
                'type'          => Controls_Manager::URL,
                'dynamic'       => [
                    'active'        => true,
                ],
                'placeholder'   => esc_html__('https://your-link.com', 'streamit'),
                'default'       => [
                    'url'           => '#',
                ],
                'condition'     => ['link_type' => 'custom']
            ]
        );

        $this->add_control(
            'selected_tabs',
            [
                'label'         => __('Item List', 'streamit'),
                'type'          => Controls_Manager::REPEATER,
                'fields'        => $repeater->get_controls(),
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_slick_control',
            [
                'label'         => __('Slider Control', 'streamit'),
            ]
        );

        $this->add_control(
            'autoplay',
            [
                'label'         => __('Autoplay', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'default'       => 'false',
                'options'       => [
                    'true'          => __('True', 'streamit'),
                    'false'         => __('False', 'streamit'),
                ],
            ]
        );

        $this->add_control(
            'autoplay_speed',
            [
                'label'         => __('Autoplay Speed', 'streamit'),
                'type'          => Controls_Manager::TEXT,
                'label_block'   => false,
                'condition'     => ['autoplay' => 'true'],
                'default'       => '5000',
            ]
        );

        $this->add_control(
            'infinite',
            [
                'label'         => __('Infinite', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'default'       => 'true',
                'options'       => [
                    'true'          => __('True', 'streamit'),
                    'false'         => __('False', 'streamit'),
                ],
            ]
        );

        $this->add_control(
            'speed',
            [
                'label'         => __('Speed', 'streamit'),
                'type'          => Controls_Manager::TEXT,
                'label_block'   => true,
                'default'       => '500',
            ]
        );

        $this->add_control(
            'nav-arrow',
            [
                'label'         => __('Arrow', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'default'       => 'true',
                'options'       => [
                    'true'          => __('True', 'streamit'),
                    'false'         => __('False', 'streamit'),
                ],
            ]
        );


        $this->end_controls_section();

        $this->start_controls_section(
            'section_banner_style',
            [
                'label'         => __('Main', 'streamit'),
                'tab'           => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'main_padding',
            [
                'label'         => __('Padding', 'streamit'),
                'type'          => Controls_Manager::DIMENSIONS,
                'size_units'    => ['px', '%', 'em'],
                'selectors'     => [
                    '{{WRAPPER}} .st-main-slider .slider-inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->add_responsive_control(
            'main_margin',
            [
                'label'         => __('Margin', 'streamit'),
                'type'          => Controls_Manager::DIMENSIONS,
                'size_units'    => ['px', '%', 'em'],
                'selectors'     => [
                    '{{WRAPPER}} .st-main-slider .slider-inner' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} ;',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
	{
        $settings = $this->get_settings_for_display();
        $slick_settings = array(
            'dots'          => false,
            'arrows'        => !empty($settings['nav-arrow']) && $settings['nav-arrow'] === 'true' ? true : false,
            'infinite'      => !empty($settings['infinite']) && $settings['infinite'] === 'true' ? true : false,
            'speed'         => !empty($settings['speed']) ? intval($settings['speed']) : 500,
            'autoplay'      => !empty($settings['autoplay'])  && $settings['autoplay'] === 'true' ? true : false,
			"autoplaySpeed" 	=> !empty($settings['autoplay_speed']) ?  intval($settings['autoplay_speed']) : 2000,
            'slidesToShow'  => 1,
            'responsive'    => [[
                    'breakpoint'    => 1601,
                    'settings'      => [
                        'infinite'      => true,
                        'dots'          => false
                    ]
                ],
                [
                    'breakpoint'    => 1366,
                    'settings'      => [
                        'infinite'      => true,
                        'dots'          => false
                    ]
                ],
                [
                    'breakpoint'    => 480,
                    'settings'      => [
                        'arrows'        => false
                    ]
                ],
            ]
        );
        $post_data = $settings['selected_tabs'];
        streamit_get_template(
            'elementor-widget/product-banner/html-product-banner.php',
            [
                'slick_settings' => $slick_settings, 
                'post_data'      => $post_data, 
                'settings'       => $settings,
                'id_int'         => rand(10, 100)
                ]
        );
    }
}

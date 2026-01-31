<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_Blog extends Widget_Base
{
    public function get_name()
    {
        return 'css_prefix-blog';
    }

    public function get_title()
    {
        return __('Streamit Blog', 'streamit');
    }

    public function get_icon()
    {
        return 'eicon-slider-3d';
    }

    public function get_categories()
    {
        return ['streamit'];
    }

    public function get_slider_name(): string
    {
        return 'main-banner';
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_blog',
            [
                'label' => __('Main Blog', 'streamit'),
            ]
        );

        // Dropdown to select the number of columns

        $this->add_control(
            'iq_posts_per_page',
            [
                'label' => esc_html__('Posts Per Page', 'streamit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'step' => 1,
                'default' => 6,
            ]
        );


        $this->add_control(
            'iq_columns',
            [
                'label'       => __('Columns', 'streamit'),
                'type'        => \Elementor\Controls_Manager::SELECT,
                'default'     => '4',
                'options'     => [
                    '1' => __('1 Column', 'streamit'),
                    '2' => __('2 Columns', 'streamit'),
                    '3' => __('3 Columns', 'streamit'),
                    '4' => __('4 Columns', 'streamit'),
                    '5' => __('5 Columns', 'streamit'),
                ],
            ]
        );

        $this->add_control(
            'title_tag',
            [
                'label'      => __('Title Tag', 'streamit'),
                'type'       => Controls_Manager::SELECT,
                'default'    => 'h4',
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

        $this->add_control(
            'hide_content',
            [
                'label'        => __('Content', 'streamit'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __('Hide', 'streamit'),
                'label_off'    => __('Show', 'streamit'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'iq_content_line',
            [
                'label' => esc_html__('Content line', 'streamit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 5,
                'step' => 1,
                'default' => 3,
                'condition' => ['hide_content' => 'yes']
            ]
        );

        $this->add_control(
            'hide_date',
            [
                'label'        => __('Date', 'streamit'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __('Hide', 'streamit'),
                'label_off'    => __('Show', 'streamit'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'hide_image',
            [
                'label'        => __('Image', 'streamit'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __('Hide', 'streamit'),
                'label_off'    => __('Show', 'streamit'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'hide_read_more_button',
            [
                'label'        => __('Read More', 'streamit'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __('Hide', 'streamit'),
                'label_off'    => __('Show', 'streamit'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'read_more_text',
            [
                'label' => esc_html__('Read More Text', 'streamit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Read More', 'streamit'),
            ]
        );

        $this->add_control(
            'iq_pagination',
            [
                'label'   => __('Show Pagintion/Loadmore/Infinite', 'streamit'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'loadmore',
                'label_block' => true,
                'options' => [
                    'yes'        => esc_html__('Pagination', 'streamit'),
                    'loadmore' => esc_html__('Loadmore', 'streamit'),
                    'infinite' => esc_html__('Infinite Scroll', 'streamit')
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $post_per_page = (!empty($settings['iq_posts_per_page'])) ? $settings['iq_posts_per_page'] : 10;
        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

        $post_args = array(
            'post_type'      => 'post',
            'posts_per_page' => $post_per_page,
            'paged'          => $paged,
            'order'          => 'DESC',
        );
        $classes = [
            '1' => 'row-cols-sm-1 row-cols-md-1 row-cols-lg-1',
            '2' => 'row-cols-sm-2 row-cols-md-2 row-cols-lg-2',
            '3' => 'row-cols-sm-2 row-cols-md-3 row-cols-lg-3',
            '4' => 'row-cols-sm-2 row-cols-md-3 row-cols-lg-4',
            '5' => 'row-cols-sm-2 row-cols-md-3 row-cols-lg-5',
        ];
        $parent_class =  $classes[$settings['iq_columns']];
        streamit_get_template(
            'elementor-widget/Blog/html-blog.php',
            [
                'settings'      => $settings,
                'post_args'     => $post_args,
                'post_per_page' => $post_per_page,
                'paged'         => $paged,
                'parent_class'  => $parent_class
            ]
        );
    }
}

<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_TvShow_Season extends Widget_Base
{
    public function get_name()
    {
        return 'st_tvshow_season';
    }

    public function get_title()
    {
        return __('TvShow Season', 'streamit');
    }

    public function get_icon()
    {
        return 'eicon-slider-push';
    }

    public function get_categories()
    {
        return ['streamit'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_Team',
            [
                'label' => __('TvShow Banner', 'streamit'),
            ]
        );

        $this->add_control(
            'post_filter',
            [
                'label'         => __('Posts Filter', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'label_block'   => true,
                'multiple'      => false,
                'options'       => [
                    'selected'         => __('Selected', 'streamit'),
                    'default'         => __('Default', 'streamit')
                ],
                'default'       => 'default'
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'tvshow_id',
            [
                'label'             => __('Select Tv Show', 'streamit'),
                'type'              => 'st_ajax_select',
                'ajax_action'       => 'streamit_elementor_select_ajax',
                'multiple'          => false,
                'placeholder'       => __('Select items...', 'streamit'),
                'custom_attributes' => array(
                    'data-ajax-params' => wp_json_encode([
                        'callback'      => 'streamit_get_recommended_tvshows',
                        'argument'      => ['per_page' => 100]
                    ])
                    ),
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        $this->add_control(
            'selected_tabs',
            [
                'label'         => __('Select Tv Shows Manually', 'streamit'),
                'type'             => Controls_Manager::REPEATER,
                'fields'         => $repeater->get_controls(),
                'condition'     => ['post_filter' => 'selected']
            ]
        );


        $this->add_responsive_control(
            'posts_per_page',
            [
                'label'         => __('Posts Per Page', 'streamit'),
                'type'          => Controls_Manager::SLIDER,
                'default'       => [
                    'unit'          => '%',
                    'size'          => 10,
                ],
                'condition'     => ['post_filter' => 'default']
            ]
        );

        $this->add_control(
            'trending_top_img',
            [
                'label'         => __('Trending Order Image', 'streamit'),
                'type'          => Controls_Manager::MEDIA,
                'default'       => [
                    'url'       =>  streamit_placeholder_image(),
                ],
            ]
        );

        $this->add_control(
            'play_now_text',
            [
                'label'         => __('Play Now Text', 'streamit'),
                'type'          => Controls_Manager::TEXT,
                'default'       => __('Stream Now', 'streamit'),
                'label_block'   => true,
            ]
        );

        $this->add_control(
            'episode_card_text',
            [
                'label'         => __('More Button Text', 'streamit'),
                'type'          => Controls_Manager::TEXT,
                'default'       => __('More', 'streamit'),
                'label_block'   => true,
            ]
        );

        $this->add_control(
            'title_text',
            [
                'label'         => __('Trending', 'streamit'),
                'type'          => Controls_Manager::TEXT,
                'default'       => __('Trending', 'streamit'),
                'label_block'   => true,
                'condition'     => ['show_title' => ['yes']]
            ]
        );


        $this->end_controls_section();

        $this->start_controls_section(
            'slick_control_section',
            [
                'label'         => __('Slider Control', 'streamit'),
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
                'default'       => '300',
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
            'section_button_style',
            [
                'label'         => __('Button', 'streamit'),
                'tab'           => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label'         => __('Padding', 'streamit'),
                'type'          => Controls_Manager::DIMENSIONS,
                'size_units'    => ['px', '%', 'em'],
                'selectors'     => [
                    '{{WRAPPER}} .st-btn-container .st-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_border_radius',
            [
                'label'         => __('Border Radius', 'streamit'),
                'type'          => Controls_Manager::DIMENSIONS,
                'size_units'    => ['px', '%', 'em'],
                'selectors'     => [
                    '{{WRAPPER}} .st-btn-container .st-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $slick_settings = array(
            'dots' => false,
            'arrows' => !empty($settings['nav-arrow']) && $settings['nav-arrow'] === 'true' ? true : false,
            'infinite' => !empty($settings['infinite']) && $settings['infinite'] === 'true' ? true : false,
            'speed' => !empty($settings['speed']) ? intval($settings['speed']) : 500,
            'autoplay' => false,
            'slidesToShow' => 1,
            'responsive' => [
                [
                    'breakpoint' => 768,
                    'settings' => [
                        'arrows' => false,
                        'infinite' => true,
                        'dots' => true
                    ]
                ]
            ]
        );
        $post_data = array();
        $post_filter     = $settings['post_filter'];
        $args = array();
        if ($post_filter == 'selected') {
            $selcted_tabs = $settings['selected_tabs'];
            if (is_array($selcted_tabs) && !empty($selcted_tabs)) {
                $include_ids = array();
                foreach ($selcted_tabs as $tab) {
                    if (isset($tab['tvshow_id']) && !empty($tab['tvshow_id'])) {
                        $include_ids[] = $tab['tvshow_id'];
                    }
                }
                if (!empty($include_ids)) {
                    $args['include']    = $include_ids;
                    $args['per_page']   = -1;
                }
            }
        } elseif ($post_filter == 'default') {
            $args = array(
                'meta_key' => 'post_views_count',
                'orderby'  => 'meta_value_num',
                'order'    => 'DESC',
                'meta_query' => array(
                    array(
                        'key'     => 'post_views_count',
                        'value'   => '0',
                        'compare' => '!='
                    ),
                    array(
                        'key'     => 'post_views_count',
                        'value'   => ' ',
                        'compare' => '!='
                    ),
                )
            );

            $per_page  = isset($settings['posts_per_page']) && !empty($settings['posts_per_page']) ? $settings['posts_per_page']['size'] : 10;
            $per_page = ($per_page > 0) ? $per_page : 10;
            $args['per_page'] = $per_page;
        }
        $post_data = streamit_get_tvshows($args)->results;
        
        streamit_get_template(
            'elementor-widget/tv-show-season/html-tv-show-season.php',
            [
                'slick_settings' => $slick_settings, 
                'post_data' => $post_data, 
                'settings' => $settings
                ]
        );
    }
}

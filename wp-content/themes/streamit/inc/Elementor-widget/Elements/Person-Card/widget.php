<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_Person extends Widget_Base
{


    public function get_name()
    {
        return 'person-card';
    }

    public function get_title()
    {
        return __('Person Card', 'streamit');
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
        return 'person-card';
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
                'default'   => __('Title', 'streamit'),
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
            'st_style',
            [
                'label' => __('Style', 'streamit'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'slider' => __('Slider', 'streamit'),
                    'grid' => __('Grid', 'streamit')
                ],
                'default' => 'slider'
            ]
        );

        $this->add_control(
            'iq_columns',
            [
                'label'       => __('Columns', 'streamit'),
                'type'        => \Elementor\Controls_Manager::SELECT,
                'default'     => '5',
                'options'     => [
                    '1' => __('1 Column', 'streamit'),
                    '2' => __('2 Columns', 'streamit'),
                    '3' => __('3 Columns', 'streamit'),
                    '4' => __('4 Columns', 'streamit'),
                    '5' => __('5 Columns', 'streamit'),
                ],
                'condition' => ['st_style' => 'grid']
            ]
        );

        $this->end_controls_section();


        $this->start_controls_section(
            'section_data_control',
            [
                'label' => __('Persons Controls', 'streamit'),
            ]
        );

        $this->add_control(
            'st_person_filter',
            [
                'label'         => __('Select Person Filter', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'label_block'   => true,
                'multiple'      => false,
                'options'       => [
                    'selected'  =>  'Selected',
                    'category'  =>  'Selected Category',
                ],
                'default'     =>    'selected'
            ]
        );


        $this->add_control(
            'st_selected_person',
            [
                'label'         => __('Select Persons', 'streamit'),
                'type'          => 'st_ajax_select',
                'ajax_action'   => 'streamit_elementor_select_ajax',
                'multiple'      => true,
                'placeholder'   => __('Select items...', 'streamit'),
                'custom_attributes' => array(
                    'data-ajax-params'  => wp_json_encode([
                        'callback'      => 'streamit_get_person_list',
                        'argument'      => ['per_page' => 100]
                    ])
                ),
                'condition'     =>  [
                    'st_person_filter'  =>  'selected'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );


		$this->add_control(
            'st_selected_person_category',
            [
                'label'             => __('Select Category', 'streamit'),
                'type'              => 'st_ajax_select',
                'ajax_action'       => 'streamit_elementor_select_ajax',
                'multiple'          => true,
                'placeholder'       => __('Select items...', 'streamit'),
                'custom_attributes' => array(
                    'data-ajax-params' => wp_json_encode([
                        'callback'         => 'streamit_get_term_list',
                        'argument'         => ['per_page' => 100, 'taxonomy' => ['person_category']]
                    ])
                ),
                'condition'     =>  [
                    'st_person_filter'  =>  'category'
                ],
                'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label' => esc_html__('Posts Per Page', 'streamit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'step' => 1,
                'default' => 10,
            ]
        );

        $this->add_control(
            'use_custom_link_text',
            [
                'label' => __('Custom Link Text', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'default' => __('View All', 'streamit'),
                'label_block' => true,
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_slider',
            [
                'label' => __('Slider Controls', 'streamit'),
                'condition' => ['st_style' => 'slider']
            ]
        );

        require get_template_directory() . '/inc/Elementor-widget/controls/slick-control.php';


        $this->end_controls_section();
    }


    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $slick_settings = array(
            "dots"                 => false,
            "slidesToShow"         => !empty($settings['desk_number']) ? intval($settings['desk_number']) : 11,
            "slidesToScroll"     => !empty($settings['desk_number']) ? intval($settings['desk_number']) : 11,
            "arrows"             => !empty($settings['nav-arrow']) && $settings['nav-arrow'] === "true" ? true : false,
            "autoplay"             => !empty($settings['autoplay']) && $settings['autoplay'] === "true" ? true : false,
            "autoplaySpeed"     => !empty($settings['autoplay_speed']) ?  intval($settings['autoplay_speed']) : 2000,
            "speed"             => !empty($settings['speed']) ?  intval($settings['speed']) : 300,
            "infinite"          => !empty($settings['infinite']) && $settings['infinite'] === "true" ? true : false,
            "responsive"         => [
                [
                    "breakpoint"     => 1367,
                    "settings"         => [
                        "slidesToShow"     => !empty($settings['lap_number']) ? intval($settings['lap_number']) : 7,
                        "slidesToScroll"     => !empty($settings['lap_number']) ? intval($settings['lap_number']) : 7,
                    ]
                ],
                [
                    "breakpoint"     => 1025,
                    "settings"         => [
                        "slidesToShow"     => !empty($settings['tab_number']) ? intval($settings['tab_number']) : 5,
                        "slidesToScroll"   => !empty($settings['tab_number']) ? intval($settings['tab_number']) : 5,
                    ]
                ],
                [
                    "breakpoint"     => 768,
                    "settings"     => [
                        "slidesToShow"     => !empty($settings['mob_number']) ? intval($settings['mob_number']) : 3,
                        "slidesToScroll"   => !empty($settings['mob_number']) ? intval($settings['mob_number']) : 3,
                    ]
                ]
            ]
        );


        $template_type  =   $settings['st_style'];
        $filter_type = $settings['st_person_filter'];
        $per_page    = !empty($settings['posts_per_page']) ? $settings['posts_per_page'] : 10;

        $args = ['per_page' => $per_page];
        if ($filter_type === 'selected' && !empty($settings['st_selected_person'])) {
            $args['include'] = $settings['st_selected_person'];
            $args['per_page'] = -1;
        } elseif (!empty($settings['st_selected_person_category'])) {
            $term_ids = $settings['st_selected_person_category'];
            $tax_query = array('relation' => 'OR');
            foreach ($term_ids as $term_id) {
                $tax_query[] = array(
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                    'operator' => '=',
                );
            }
            $args['tax_query'] = $tax_query;
        }
        $results = [];
        $persons = streamit_get_persons($args)->results;
        foreach ($persons as $person) {
            $results[] = ['data' => $person];
        }

        $title_tag = $settings['title_tag'] ?? 'h3'; // Default tag if not set
        $slider_title = esc_html($settings['slider_title']);

        streamit_get_template(
            'elementor-widget/person-card/html-person-card-' . $template_type . '.php',
            [
                'slick_settings' => $slick_settings,
                'results' => $results,
                'settings' => $settings,
                'slingle_slider_settings' => '',
                'title_tag'  => $title_tag,
                'slider_title' => $slider_title
            ]
        );
    }
}

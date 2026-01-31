<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_SBanner extends Widget_Base
{
    public function get_name()
    {
        return 'simple-banner';
    }

    public function get_title()
    {
        return __('Simple Banner', 'streamit');
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
        return 'simple-banner';
    }

    protected function register_controls()
    {

        $this->start_controls_section(
            'section_sbanner_template',
            [
                'label' => __('Template Settings', 'streamit'),
            ]
        );

		$this->add_control(
			'st_selected_template',
			[
				'label'   => __('Select Banner Template', 'streamit'),
				'type'    => Controls_Manager::SELECT,
				'default' => 'normal',
				'options' => [
					'normal' => esc_html__('Normal', 'streamit'),
					'center' => esc_html__('Center', 'streamit')
				],
			]
		);
		
		$this->add_control(
			'show_view',
			[
				'label' => wp_kses_post(__(' Show Views? <br><span style="color:red; margin-top:10px; display:inline-block;"><strong>Note: This applies to video post only.</strong></span>', 'streamit')),
				'type' 		=> Controls_Manager::SELECT,
				'default' 	=> 'block',
				'options' 	=> [
					'block' => esc_html__('Yes', 'streamit'),
					'none' 	=> esc_html__('No', 'streamit')
				]
			]
		);
		

		$this->add_control(
			'view_all_starring',
			[
				'label' 	=> __('Show Starring?', 'streamit'),
				'type' 		=> Controls_Manager::SELECT,
				'default' 	=> 'block',
				'options' 	=> [
					'block' => esc_html__('Yes', 'streamit'),
					'none' 	=> esc_html__('No', 'streamit')
				],
				'condition'	=>	['st_selected_template'	=>	'normal']

			]
		);

		$this->add_control(
			'view_all_genres',
			[
				'label' 	=> __('Show Genres?', 'streamit'),
				'type' 		=> Controls_Manager::SELECT,
				'default' 	=> 'block',
				'options' 	=> [
					'block' => esc_html__('Yes', 'streamit'),
					'none' 	=> esc_html__('No', 'streamit')
				],
				'condition'	=>	['st_selected_template'	=>	'normal']
			]
		);

		$this->add_control(
			'view_all_tag',
			[
				'label' 	=> __('Show Tags?', 'streamit'),
				'type' 		=> Controls_Manager::SELECT,
				'default' 	=> 'block',
				'options' 	=> [
					'block' => esc_html__('Yes', 'streamit'),
					'none' 	=> esc_html__('No', 'streamit')
				],
				'condition'	=>	['st_selected_template'	=>	'normal']
			]
		);

        $this->add_control(
			'show_view_all_btn',
			[
				'label'   => __('Show Play Now Button', 'streamit'),
				'type'    => Controls_Manager::SELECT,
				'default' => 'yes',
				'options' => [
					'yes' 	=> esc_html__('Yes', 'streamit'),
					'no' 	=> esc_html__('No', 'streamit')
				],
			]
		);

		$this->add_control(
			'play_now_text',
			[
				'label' 	=> __('Play Now Text', 'streamit'),
				'type' 		=> Controls_Manager::TEXT,
				'default' 	=> __('Play Now', 'streamit'),
				'label_block' 	=> true,
				'condition' 	=> ['show_view_all_btn' => ['yes']]
			]
		);

		$this->add_control(
			'show_trailer_btn',
			[
				'label'   => __('Show Trailer Button', 'streamit'),
				'type'    => Controls_Manager::SELECT,
				'default' => 'block',
				'options' => [
					'block' => esc_html__('Yes', 'streamit'),
					'none' 	=> esc_html__('No', 'streamit')
				],
			]
		);

		$this->add_control(
			'trailer_text',
			[
				'label' 		=> __('Trailer Text', 'streamit'),
				'type' 			=> Controls_Manager::TEXT,
				'default' 		=> __('Watch Trailer', 'streamit'),
				'label_block'	=> true,
				'condition' 	=> ['show_trailer_btn' => ['block']]
			]
		);

		
        $this->end_controls_section();

        $this->start_controls_section(
            'st_sbanner_content',
            [
                'label' =>  __('Banner Content','streamit')
            ]
        );

		$repeater = new Repeater();
		
		$repeater->add_control(
			'st_post_type',
			[
				'label' 		=> __('Select Specific Post', 'streamit'),
				'type' 			=> Controls_Manager::SELECT,
				'label_block' 	=> true,
				'multiple' 		=> true,
				'options' 		=> [
					'movie' 	=> __('Movie', 'streamit'),
					'tvshow' 	=> __('Tv Show', 'streamit'),
					'video' 	=> __('Video', 'streamit')
				],
				'default' => 'movie'
			]
		);


		$repeater->add_control(
			'st_tvshow',
			[
				'label' 		=> __('Display Specific Tv-Show', 'streamit'),
				'type' 			=> 'st_ajax_select',
				'ajax_action' 	=> 'streamit_elementor_select_ajax',
				'multiple' 		=> false,
				'placeholder' 	=> __('Select items...', 'streamit'),
				'custom_attributes' 	=> array(
					'data-ajax-params'	 	=> wp_json_encode([
						'callback' 			=> 'streamit_get_recommended_tvshows',
						'argument' 			=> ['per_page' => 100]
					])
				),
				'condition' 	=> ['st_post_type' => ['tvshow']],
				'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
			]
		);

		$repeater->add_control(
			'st_movie',
			[
				'label' 		=> __('Display Specific Movie', 'streamit'),
				'type' 			=> 'st_ajax_select',
				'ajax_action' 	=> 'streamit_elementor_select_ajax',
				'multiple' 		=> false,
				'placeholder' 	=> __('Select items...', 'streamit'),
				'custom_attributes' 	=> array(
					'data-ajax-params' 		=> wp_json_encode([
						'callback' 			=> 'streamit_get_recommended_movies',
						'argument' 			=> ['per_page' => 100]
					])
				),
				'condition' 	=> ['st_post_type' => ['movie']],
				'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
			]
		);


		$repeater->add_control(
			'st_video',
			[
				'label' 		=> __('Display Specific Video', 'streamit'),
				'type' 			=> 'st_ajax_select',
				'ajax_action' 	=> 'streamit_elementor_select_ajax',
				'multiple' 		=> false,
				'placeholder' 	=> __('Select items...', 'streamit'),
				'custom_attributes' 	=> array(
					'data-ajax-params'	 	=> wp_json_encode([
						'callback' 			=> 'streamit_get_recommended_videos',
						'argument' 			=> ['per_page' => 100]
					])
				),
				'condition' 	=> ['st_post_type' => ['video']],
				'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
			]
		);


		$repeater->add_control(
			'st_slider_image',
			[
				'label' => __('Slider Image', 'streamit'),
				'type' => Controls_Manager::MEDIA,
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'st_tabs',
			[
				'label' 	=> __('Item List', 'streamit'),
				'type' 		=> Controls_Manager::REPEATER,
				'fields' 	=> $repeater->get_controls()
			]
		);

        $this->end_controls_section();

        $this->start_controls_section(
            'st_sbanner_slider_controls',
            [
                'label' =>  __('Banner Slider Controls','streamit')
            ]
        );

		$this->add_control(
			'autoplay',
			[
				'label' => __('Autoplay', 'streamit'),
				'type' => Controls_Manager::SELECT,
				'default' => 'false',
				'options' => [
					'true' => __('True', 'streamit'),
					'false' => __('False', 'streamit'),
				],
			]
		);

		$this->add_control(
			'autoplay_speed',
			[
				'label' => __('Autoplay Speed', 'streamit'),
				'type' => Controls_Manager::TEXT,
				'label_block' => false,
				'condition' => ['autoplay' => 'true'],
				'default' => '5000',
			]
		);

		$this->add_control(
			'speed',
			[
				'label' => __('Slide Animation Time', 'streamit'),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
				'default' => '1000',
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
			'cssEase' => 'ease-in-out',
            'lazyLoad' => 'progressive',
            'dots' => false,
            'arrows' => !empty($settings['nav-arrow']) && $settings['nav-arrow'] === "true" ? true : false,
            'infinite' => true,
            'speed' => !empty($settings['speed']) ?  intval($settings['speed']) : 300,
            'autoplay' => !empty($settings['autoplay']) && $settings['autoplay'] === "true" ? true : false,
            'autoplaySpeed' => !empty($settings['autoplay_speed']) ?  intval($settings['autoplay_speed']) : 2000,
            'responsive' => [[
                'breakpoint' => 1025,
                'settings' => [
                    'dots' => true,
                    'arrows' => false,
				]
			]]
		);

		$slick_inner_slider_settings = array(
			'centerMode'	=> true,
			'centerPadding'	=> '200px',
            'cssEase'		=> 'ease-in-out',
            'speed'			=> !empty($settings['speed']) ?  intval($settings['speed']) : 300,
            'slidesToShow'	=> 1,
            'dots'			=> false,
            'arrows'		=> !empty($settings['nav-arrow']) && $settings['nav-arrow'] === "true" ? true : false,
            'infinite'		=> true,
            'slidesToScroll'	=> 1,
            'autoplay'	=> !empty($settings['autoplay']) && $settings['autoplay'] === "true" ? true : false,
            'autoplaySpeed'	=> !empty($settings['autoplay_speed']) ?  intval($settings['autoplay_speed']) : 2000,
            'responsive'	=> [
                [
                    'breakpoint'	=> 1200,
                    'settings'	=> [
                        'centerMode'	=> true,
                        'centerPadding'	=> '50px',
                        'slidesToShow'	=> 1
                    ]
                ],
				[
					'breakpoint' => 1025,
					'settings' => [
						'dots' => true,
						'arrows' => false,
						'centerPadding'	=> '50px',
						'slidesToShow'	=> 1
					]
				],
                [
                    'breakpoint'	=> 991,
                    'settings'	=> [
						'dots' => true,
						'arrows' => false,
                        'centerPadding'	=> '20px',
                        'slidesToShow'	=> 1
                    ]
                ],
                [
                    'breakpoint'	=> 480,
                    'settings'	=> [
						'dots' => true,
						'arrows' => false,
                        'centerPadding'	=> '20px',
                        'slidesToShow'	=> 1
                    ]
                ]
            ]
		);

		$template_type = $settings['st_selected_template'];

		streamit_get_template(
			'elementor-widget/simple-banner/html-simple-banner-' . $template_type . '.php',
			[	
				'slick_settings' 	=> $slick_settings,
				'st_tabs' 			=> $settings['st_tabs'],
				'id_int' 			=> rand(10,100),
				'slick_inner_slider_settings'	=> $slick_inner_slider_settings, 
				'settings' 			=> $settings
				]
		);

    }
}

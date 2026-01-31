<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
	exit;
}

class ST_Main_Banner extends Widget_Base
{
	public function get_name()
	{
		return 'main-banner';
	}

	public function get_title()
	{
		return __('Main Banner', 'streamit');
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
			'section_thumb_slider',
			[
				'label' => __('Main Banner', 'streamit'),
			]
		);

		$this->add_control(
			'play_now_text',
			[
				'label'         => __('Play Now Text', 'streamit'),
				'type'          => Controls_Manager::TEXT,
				'default'       => __('Play Now', 'streamit'),
				'label_block'   => true,
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
					'selected' 	    => __('Selected', 'streamit'),
					'latest' 	    => __('Latest', 'streamit')
				],
				'default'       => 'latest'
			]
		);

		$this->add_control(
			'filter_post_types',
			[
				'label'         => __('Posts Filter', 'streamit'),
				'type'          => Controls_Manager::SELECT2,
				'label_block'   => true,
				'multiple'      => true,
				'options'       => [
					'movie' 	    => __('Movie', 'streamit'),
					'tvshow' 	    => __('Tv Show', 'streamit'),
					'video' 	    => __('Video', 'streamit')
				],
				'default'       => ['movie', 'tvshow', 'video'],
				'condition'     => ['post_filter' => 'latest']
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
				'condition'     => ['post_filter' => 'latest']
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'st_type',
			[
				'label' 		=> __('Post Type', 'streamit'),
				'type' 			=> Controls_Manager::SELECT,
				'label_block' 	=> true,
				'multiple' 		=> true,
				'default' 		=> 'movie',
				'options' 		=> [
					'movie' 	=> __('Movie', 'streamit'),
					'tvshow' 	=> __('Tv Show', 'streamit'),
					'video' 	=> __('Video', 'streamit')
				],
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
				'custom_attributes' => array(
					'data-ajax-params' => wp_json_encode([
						'callback' 		=> 'streamit_get_recommended_tvshows',
						'argument' 		=> ['per_page' => 100]
					])
				),
				'condition' 	=> ['st_type' => ['tvshow']],
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
				'custom_attributes' => array(
					'data-ajax-params' 	=> wp_json_encode([
						'callback' 		=> 'streamit_get_recommended_movies',
						'argument' 		=> ['per_page' => 100]
					])
				),
				'condition' 	=> ['st_type' => ['movie']],
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
				'custom_attributes' => array(
					'data-ajax-params' 	=> wp_json_encode([
						'callback' 		=> 'streamit_get_recommended_videos',
						'argument' 		=> ['per_page' => 100]
					])
				),
				'condition' 	=> ['st_type' => ['video']],
				'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
			]
		);


		$this->add_control(
			'selected_tabs',
			[
				'label' 	    => __('Select Posts To Display', 'streamit'),
				'type' 		    => Controls_Manager::REPEATER,
				'fields' 	    => $repeater->get_controls(),
				'condition'     => ['post_filter' => 'selected']
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_slider',
			[
				'label' => __('Slider Controls', 'streamit'),
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
			'speed',
			[
				'label'         => __('Speed', 'streamit'),
				'type'          => Controls_Manager::TEXT,
				'label_block'   => false,
				'default'       => '500',
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
			'centerMode' => true,
			'centerPadding' => '0',
			'arrows' => false,
			'speed' => !empty($settings['speed']) ?  intval($settings['speed']) : 300,
			'infinite' => true,
			'autoplay' => !empty($settings['autoplay']) && $settings['autoplay'] === "true" ? true : false,
			'autoplaySpeed' => !empty($settings['speed']) ?  intval($settings['speed']) : 300,
			'responsive' => [[
				'breakpoint' => 992,
				'settings' => [
					'dots' => true,
				]
			]]
		);

		$slick_child_settings = array(
			'slidesToShow' => 2,
			'slidesToScroll' => 1,
			'dots' => false,
			'focusOnSelect' => true,
		);

		$post_filter 	= $settings['post_filter'];
		$post_data = array();
		if ($post_filter == 'selected') {
			$selcted_tabs = $settings['selected_tabs'];
			if (is_array($selcted_tabs) && !empty($selcted_tabs)) {
				foreach ($selcted_tabs as $tab) {
					$post_type = $tab['st_type'];
					if (isset($tab['st_' . $post_type]) && !empty($tab['st_' . $post_type])) {
						$function_name = 'streamit_get_' . $post_type;
						if (function_exists($function_name)) {
							$post_id = $tab['st_' . $post_type];
							$data = call_user_func($function_name, (int)$post_id);
							if (!empty($data))
								$post_data[] = $data;
						}
					}
				}
			}
		} elseif ($post_filter == 'latest') {
			$args = array(
				'order'        => 'DESC',
				'orderby'      => 'post_date',
			);
			$post_type = $settings['filter_post_types'];
			$per_page  = isset($settings['posts_per_page']) && !empty($settings['posts_per_page']) ? $settings['posts_per_page']['size'] : 10;

			$args['post_type'] = $post_type;
			$args['per_page']  = $per_page;
			$combine_data = function_exists('streamit_get_data') ? streamit_get_data($args) : [];
			foreach ($combine_data as $data) {
				$post_data[] = $data;
			}
		}

		streamit_get_template(
			'elementor-widget/main-banner/html-main-banner.php',
			[
				'slick_settings' 		=> $slick_settings, 
				'slick_child_settings' 	=> $slick_child_settings, 
				'post_data' 			=> $post_data, 
				'settings' 				=> $settings,
				'id_int' 				=> rand(10, 100),
]
		);
	}
}

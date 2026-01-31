<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
	exit;
}

class ST_VTBenner extends Widget_Base
{


	public function get_name()
	{
		return 'vertical-thumbnail-banner';
	}

	public function get_title()
	{
		return __('Vertical Thumbnail Banner', 'streamit');
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
		return 'vertical-thumbnail-banner';
	}

	protected function register_controls()
	{

		$this->start_controls_section(
			'section_vertical_t_b_template',
			[
				'label' => __('Template Settings', 'streamit'),
			]
		);

		$this->add_control(
			'st_slider_title',
			[
				'label' => __('Title', 'streamit'),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'label_block' => true,
				'default' => __('Title', 'streamit'),
				'condition' => [
					'verticle_banner_style' => '1'
				],
			]
		);

		$this->add_control(
			'st_play_now_text',
			[
				'label' => __('Play Now Text', 'streamit'),
				'type' => Controls_Manager::TEXT,
				'default' => __('Play Now', 'streamit'),
				'label_block' => true,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'st_vertical_t_b_content',
			[
				'label' =>  __('Banner Content', 'streamit')
			]
		);

		$this->add_control(
			'st_post_filter',
			[
				'label' => __('Posts Filter', 'streamit'),
				'type' => Controls_Manager::SELECT,
				'label_block' => true,
				'options' => [
					'selected' 	=> __('Selected', 'streamit'),
					'genre' 	=> __('Genre', 'streamit'),
					'tag'	 	=> __('Tag', 'streamit'),
				],
				'default' => 'genre'
			]
		);

		$repeater = new Repeater();
		$repeater->add_control(
			'st_post_type',
			[
				'label' => __('Select Specific Post', 'streamit'),
				'type' => Controls_Manager::SELECT,
				'label_block' => true,
				'multiple' => true,
				'options' => [
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
				'custom_attributes' => array(
					'data-ajax-params' => wp_json_encode([
						'callback' 	=> 'streamit_get_recommended_tvshows',
						'argument' 	=> ['per_page' => 100]
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
				'custom_attributes' => array(
					'data-ajax-params' => wp_json_encode([
						'callback' => 'streamit_get_recommended_movies',
						'argument' => ['per_page' => 100]
					])
				),
				'condition' => ['st_post_type' => ['movie']],
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
					'data-ajax-params' => wp_json_encode([
						'callback'	 	=> 'streamit_get_recommended_videos',
						'argument' 		=> ['per_page' => 100]
					])
				),
				'condition' 	=> ['st_post_type' => ['video']],
				'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
			]
		);

		$this->add_control(
			'st_tabs',
			[
				'label' 	=> __('Item List', 'streamit'),
				'type' 		=> Controls_Manager::REPEATER,
				'fields' 	=> $repeater->get_controls(),
				'condition' => ['st_post_filter' => 'selected'],
			]
		);

		$this->add_control(
			'st_select_content_type',
			[
				'label' => __('Select Content Type', 'streamit'),
				'type' 	=> Controls_Manager::SELECT,
				'label_block' 	=> true,
				'multiple'	=> false,
				'options' 	=> [
					'movie'		=>	'Movie',
					'tvshow'	=>	'Tv Show',
					'video'		=>	'Video',
				],
				'default'	=>	'movie',
				'condition' => ['st_post_filter!' => 'selected'],

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
					'st_select_content_type' 	=>	'movie',
					'st_post_filter'			=>	'genre'
				],
				'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
			]
		);


		$this->add_control(
			'st_selected_tvshow_genre',
			[
				'label'			=> __('Display Tv Show From Specific Genre', 'streamit'),
				'type'			=> Controls_Manager::SELECT2,
				'label_block'	=> true,
				'multiple' 		=> true,
				'options' 		=> streamit_get_all_genres('tvshow'),
				'default'		=> array(),
				'condition' 	=> [
					'st_select_content_type' 	=>	'tvshow',
					'st_post_filter'			=>	'genre'
				],
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
					'st_select_content_type' 	=>	'tvshow',
					'st_post_filter'			=>	'genre'
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
					'st_select_content_type' 	=>	'video',
					'st_post_filter'			=>	'genre'
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
					'st_select_content_type' 	=>	'movie',
					'st_post_filter'			=>	'tag'
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
					'st_select_content_type' 	=>	'tvshow',
					'st_post_filter'			=>	'tag'
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
					'st_select_content_type' 	=>	'video',
					'st_post_filter'			=>	'tag'
				],
				'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
			]
		);

		$this->add_responsive_control(
			'posts_per_page',
			[
				'label' => __('Posts Per Page', 'streamit'),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'unit' => '%',
					'size' => 10,
				],
				'condition'	=>	[
					'st_post_filter!'	=> 'selected',
				]
			]
		);
		$this->end_controls_section();
	}


	protected function render()
	{
		$settings = $this->get_settings_for_display();

		$filter_value  = $settings['st_post_filter'];
		$per_page      = $settings['posts_per_page']['size'] ?? 10;
		$func_map      = [
			'movie'  => 'streamit_get_movies',
			'tvshow' => 'streamit_get_tvshows',
			'video'  => 'streamit_get_videos'
		];
		$single_func_map = [
			'movie' => 'streamit_get_movie',
			'tvshow' => 'streamit_get_tvshow',
			'video' => 'streamit_get_video'
		];
		$tax_map       = [
			'genre'  => [
				'movie'  => 'st_selected_movie_genre',
				'tvshow' => 'st_selected_tvshow_genre',
				'video'  => 'st_selected_video_genre',
			],
			'tag'    => [
				'movie'  => 'st_selected_movie_tag',
				'tvshow' => 'st_selected_tvshow_tag',
				'video'  => 'st_selected_video_tag',
			]
		];

		$posts_ids = [];
		if ($filter_value === 'selected') {
			if (!empty($settings['st_tabs']) && is_array($settings['st_tabs'])) {
				foreach ($settings['st_tabs'] as $posts) {
					$post_type = $posts['st_post_type'];
					$post_key  = "st_$post_type";
					$posts_ids[$post_type][] = $posts[$post_key];
				}
			}
			$flat_results = [];
			
			$results = [];

		if (!empty($posts_ids) && is_array($posts_ids)) {
				foreach ($posts_ids as $type => $ids) {
					if (!is_array($ids)) {
						$ids = [$ids];
					}
			
					foreach ($ids as $id) {
						$item = $single_func_map[$type]((int)$id);
			
						// ensure only valid objects are added
						if (is_object($item)) {
							$results[] = $item;
						}
					}
				}
			}
		} elseif (in_array($filter_value, ['genre', 'tag'])) {
			$args['per_page'] = $per_page;
			if(isset($settings[$tax_map[$filter_value][$settings['st_select_content_type']]]) && !empty($settings[$tax_map[$filter_value][$settings['st_select_content_type']]])){
			$tax_query = ['relation' => 'OR'];
				foreach ($settings[$tax_map[$filter_value][$settings['st_select_content_type']]] as $term_id) {
					$tax_query[] = [
						'field'    => 'term_id',
						'terms'    => intval($term_id),
						'operator' => '=',
					];
				}
				$args['tax_query'] = $tax_query;
			}
			$results = $func_map[$settings['st_select_content_type']]($args)->results;
		}


		streamit_get_template(
			'elementor-widget/vertical-thumbnail-banner/html-vertical-thumbnail-banner-slider.php',
			[
				'results' => $results,
				'id_int' => rand(10, 100),
				'settings' => $settings
			]
		);
	}
}

<?php

namespace Elementor;

use Elementor\Widget_Base;

defined('ABSPATH') || exit;

class ST_Slider extends Widget_Base
{
	public function get_name()
	{
		return 'main-card-slider';
	}

	public function get_title()
	{
		return __('Main Card Slider', 'streamit');
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
		return 'main-card-slider';
	}

	protected function register_controls()
	{
		$this->start_controls_section(
			'section_slider',
			[
				'label' 		=> __('Card Template', 'streamit'),
			]
		);

		$this->add_control(
			'slider_title',
			[
				'label'			=> __('Title', 'streamit'),
				'type'			=> Controls_Manager::TEXT,
				'default'		=> __('Title', 'streamit'),
				'label_block'	=> true,
			]
		);

		$this->add_control(
			'title_tag',
			[
				'label'      	=> __('Title Tag', 'streamit'),
				'type'       	=> Controls_Manager::SELECT,
				'options'    	=> [
					'h1'        => __('h1', 'streamit'),
					'h2'        => __('h2', 'streamit'),
					'h3'        => __('h3', 'streamit'),
					'h4'        => __('h4', 'streamit'),
					'h5'        => __('h5', 'streamit'),
					'h6'        => __('h6', 'streamit'),
				],
				'default'    	=> 'h4',
			]
		);

		$this->add_control(
			'slider_start',
			[
				'type' => Controls_Manager::DIVIDER,
			]
		);

		$this->add_control(
			'slider_type',
			[
				'label' 		=> __('Style Type', 'streamit'),
				'type' 			=> Controls_Manager::SELECT,
				'label_block' 	=> true,
				'multiple' 		=> true,
				'options' 		=> [
					'slider' 	=> __('Slider', 'streamit'),
					'grid' 		=> __('Grid', 'streamit')
				],
				'default' 		=> 'slider'
			]
		);

		$this->add_control(
			'streamit_grid_style',
			[
				'label'      	=> __('Grid', 'streamit'),
				'type'       	=> Controls_Manager::SELECT,
				'default'    	=> '4',
				'options'    	=> [
					'1'         => __('One', 'streamit'),
					'2'         => __('Two', 'streamit'),
					'3'         => __('Three', 'streamit'),
					'4'         => __('Four', 'streamit'),
					'5'         => __('Five', 'streamit'),
				],
				'condition' 	=> ['slider_type' => 'grid']
			]
		);

		$this->add_control(
			'card_style',
			[
				'label' 		=> __('Card Style', 'streamit'),
				'type' 			=> Controls_Manager::SELECT,
				'label_block' 	=> true,
				'multiple' 		=> true,
				'options' 		=> [
					'potrait' 	=> __('Potrait', 'streamit'),
					'landscape' => __('Landscape', 'streamit')
				],
				'default' 		=> 'potrait'
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_data_control',
			[
				'label' 		=> __('Slider Content', 'streamit'),
			]
		);

		$this->add_control(
			'st_select_content_type',
			[
				'label' 		=> __('Select Content Type', 'streamit'),
				'type' 			=> Controls_Manager::SELECT,
				'label_block' 	=> true,
				'multiple'		=> false,
				'options' 		=> [
					'movie'		=>	'Movie',
					'tvshow'	=>	'Tv Show',
					'video'		=>	'Video',
				],
				'default'		=>	'movie'
			]
		);

		$this->add_control(
			'st_select_post_filter',
			[
				'label' 		=> __('Select Post Filter', 'streamit'),
				'type' 			=> Controls_Manager::SELECT,
				'label_block' 	=> true,
				'multiple'		=> false,
				'options' 		=> [
					'selected'	=>	'Selected',
					'genre'		=>	'Genre',
					'tag'		=>	'Tags',
					'upcoming'	=>	'Upcoming',
					'latest'	=>	'Latest',
					'most_like'=>	'Most Like',
					'rent'      =>  'Rent',
					'plan'      =>  'Paid',
					'anyone'    =>  'Rent or Paid',
				],
				'default'		=>	'selected'
			]
		);


		$this->add_control(
			'st_selected_movies',
			[
				'label' 		=> __('Select Movies', 'streamit'),
				'type' 			=> 'st_ajax_select',
				'ajax_action' 	=> 'streamit_elementor_select_ajax',
				'multiple' 		=> true,
				'placeholder' 	=> __('Select items...', 'streamit'),
				'custom_attributes' => array(
					'data-ajax-params' => wp_json_encode([
						'callback' 		=> 'streamit_get_recommended_movies',
						'argument' 		=> ['per_page' => 100]
					])
				),
				'condition'     =>  [
					'st_select_content_type'   	=>  'movie',
					'st_select_post_filter'  	=>  'selected'
				],
				'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
			]
		);


		$this->add_control(
			'st_selected_tvshows',
			[
				'label' 		=> __('Select Tv Shows', 'streamit'),
				'type' 			=> 'st_ajax_select',
				'ajax_action' 	=> 'streamit_elementor_select_ajax',
				'multiple' 		=> true,
				'placeholder' 	=> __('Select items...', 'streamit'),
				'custom_attributes' => array(
					'data-ajax-params' => wp_json_encode([
						'callback' 		=> 'streamit_get_recommended_tvshows',
						'argument' 	=> ['per_page' => 100]
					])
				),
				'condition'     =>  [
					'st_select_content_type'  	=>  'tvshow',
					'st_select_post_filter'		=>  'selected'
				],
				'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
			]
		);



		$this->add_control(
			'st_selected_videos',
			[
				'label' 		=> __('Select Video', 'streamit'),
				'type' 			=> 'st_ajax_select',
				'ajax_action' 	=> 'streamit_elementor_select_ajax',
				'multiple' 		=> true,
				'placeholder' 	=> __('Select items...', 'streamit'),
				'custom_attributes' => array(
					'data-ajax-params' 	=> wp_json_encode([
						'callback' 		=> 'streamit_get_recommended_videos',
						'argument' 		=> ['per_page' => 100]
					])
				),
				'condition'     =>  [
					'st_select_content_type'  	=>  'video',
					'st_select_post_filter'  	=>  'selected'
				],
				'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
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
					'st_select_post_filter'		=>	'genre'
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
					'st_select_content_type' 	=>	'tvshow',
					'st_select_post_filter'		=>	'genre'
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
					'st_select_post_filter'		=>	'genre'
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
					'st_select_post_filter'		=>	'tag'
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
					'st_select_post_filter'		=>	'tag'
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
					'st_select_post_filter'		=>	'tag'
				],
				'description'   => __('💡 Drag and drop to set proper content.', 'streamit'),
			]
		);

		$this->add_responsive_control(
			'posts_per_page',
			[
				'label' 		=> __('Posts Per Page', 'streamit'),
				'type' 			=> Controls_Manager::SLIDER,
				'default' 		=> [
					'unit' 		=> '%',
					'size' 		=> 10,
				],
				'condition'		=>	[
					'st_select_post_filter!'	=> 'selected',
				]
			]
		);

		$this->add_control(
			'title_settings',
			[
				'type' 			=> Controls_Manager::DIVIDER,
			]
		);

		$this->add_control(
			'show_title',
			[
				'label' 		=> __('Show Title', 'streamit'),
				'type' 			=> Controls_Manager::SWITCHER,
				'yes' 			=> __('Yes', 'streamit'),
				'no' 			=> __('No', 'streamit'),
				'return_value' 	=> 'yes',
				'default' 		=> 'yes',
			]
		);

		$this->add_control(
			'enable_premium_badges',
			[
				'label' 		=> __('Enable Premium & PPV Badges', 'streamit'),
				'type' 			=> Controls_Manager::SWITCHER,
				'yes' 			=> __('Yes', 'streamit'),
				'no' 			=> __('No', 'streamit'),
				'return_value' 	=> 'yes',
				'default' 		=> 'yes',
			]
		);

		$this->add_control(
			'play_now_text',
			[
				'label' 		=> __('Play Now Text', 'streamit'),
				'type' 			=> Controls_Manager::TEXT,
				'default' 		=> __('Play Now', 'streamit'),
				'label_block' 	=> true,
			]
		);

		$this->add_control(
			'view_all_switch',
			[
				'label' 		=> __('Use View All Button ?', 'streamit'),
				'type' 			=> Controls_Manager::SWITCHER,
				'yes' 			=> __('Yes', 'streamit'),
				'no' 			=> __('No', 'streamit'),
				'return_value' 	=> 'yes',
				'default' 		=> 'no',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_Team',
			[
				'label' 		=> __('Slider Controls', 'streamit'),
			]
		);

		require get_template_directory() . '/inc/Elementor-widget/controls/slick-control.php';

		$this->end_controls_section();
	}

	protected function render()
	{
		$settings = $this->get_settings_for_display();

		$slick_settings = array(
			"dots" 				=> false,
			"slidesToShow" 		=> !empty($settings['desk_number']) ? intval($settings['desk_number']) : 6,
			"slidesToScroll" 	=> !empty($settings['desk_number']) ? intval($settings['desk_number']) : 6,
			"arrows" 			=> !empty($settings['nav-arrow']) && $settings['nav-arrow'] === "true" ? true : false,
			"autoplay" 			=> !empty($settings['autoplay']) && $settings['autoplay'] === "true" ? true : false,
			"autoplaySpeed" 	=> !empty($settings['autoplay_speed']) ?  intval($settings['autoplay_speed']) : 2000,
			"speed" 			=> !empty($settings['speed']) ?  intval($settings['speed']) : 300,
			"infinite"          => !empty($settings['infinite']) && $settings['infinite'] === "true" ? true : false,
			"responsive" 		=> [
				[
					"breakpoint" 	=> 1367,
					"settings" 		=> [
						"slidesToShow" 		=> !empty($settings['lap_number']) ? intval($settings['lap_number']) : 4,
						"slidesToScroll" 	=> !empty($settings['lap_number']) ? intval($settings['lap_number']) : 4,
					]
				],
				[
					"breakpoint" 	=> 1025,
					"settings" 		=> [
						"slidesToShow" 		=> !empty($settings['tab_number']) ? intval($settings['tab_number']) : 3,
						"slidesToScroll" 	=> !empty($settings['tab_number']) ? intval($settings['tab_number']) : 3,
					]
				],
				[
					"breakpoint" 	=> 768,
					"settings" 		=> [
						"slidesToShow" 		=> !empty($settings['mob_number']) ? intval($settings['mob_number']) : 2,
						"slidesToScroll" 	=> !empty($settings['mob_number']) ? intval($settings['mob_number']) : 2,
					]
				]
			]
		);

		$template_type 	= $settings['slider_type'];

		$card_style 	= $settings['card_style'];
		if ($card_style == 'landscape')
			$template_type = $template_type . '-landscape';

		$per_page 		= !empty($settings['posts_per_page']['size']) ? $settings['posts_per_page']['size'] : 10;
		$post_filter 	= $settings['st_select_post_filter'];

		// Define mapping for different content types
		$content_map = [
			'movie' => [
				'selected_items' => $settings['st_selected_movies'],
				'get_function'   => 'streamit_get_movies',
				'tag_key'        => $settings['st_selected_movie_tag'],
				'genre_key'      => $settings['st_selected_movie_genre']
			],
			'tvshow' => [
				'selected_items' => $settings['st_selected_tvshows'],
				'get_function'   => 'streamit_get_tvshows',
				'tag_key'        => $settings['st_selected_tvshow_tag'],
				'genre_key'      => $settings['st_selected_tvshow_genre']
			],
			'video' => [
				'selected_items' => $settings['st_selected_videos'],
				'get_function'   => 'streamit_get_videos',
				'tag_key'        => $settings['st_selected_video_tag'],
				'genre_key'      => $settings['st_selected_video_genre']
			]
		];

		$post_type = $settings['st_select_content_type'];
    	$upcoming_meta_key = "{$post_type}_upcoming_status";

		if (isset($content_map[$settings['st_select_content_type']])) {
			$content_settings = $content_map[$settings['st_select_content_type']];
			$get_function = $content_settings['get_function'];
			$args = ['per_page' => $per_page];

			// Handle different post filter cases
			switch ($post_filter) {
				case 'selected':
					$selected_items = $content_settings['selected_items'];
					if (!empty($selected_items)) {
						$args['include'] 	= $selected_items;
						$args['per_page'] 	= -1;
						$args['orderby']   =  'post__in';
					}
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
					$args['orderby'] 	= 'post_date';
					$args['order']   	= 'DESC';
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

					$args['orderby']    = 'meta_value_num';
					$args['meta_key']   = 'streamit_post_like_count';
					$args['order']      = 'DESC';
					break;
				case 'rent':
					$args['per_page'] = $per_page;
					$args['meta_query'] = [
							'relation' => 'AND', // both groups must match
							[
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
							],
							[
								'key'     => '_access_type',
								'value'   => 'ppv',
								'compare' => '=',
							],
						];

					break;
				case 'plan':
					$args['per_page'] = $per_page;
					$args['meta_query'] = [
						'relation' => 'AND',
						[
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
						],
						[
							'relation' => 'OR',
							[
								'key'     => '_access_type',
								'value'   => 'plan',
								'compare' => '='
							],
							[
								'relation' => 'AND',
								[
									'key'     => '_access_type',
									'compare' => 'NOT EXISTS'
								],
								[
									'key'     => '_pmp_level',
									'value'   => '',
									'compare' => '!='
								]
							]
						]
					];	

					break;
				case 'anyone':
					$args['per_page'] = $per_page;
					$args['meta_query'] = [
							'relation' => 'AND',
							[
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
							],
							[
								'key'     => '_access_type',
								'value'   => 'anyone',
								'compare' => '=',
							],
						];
					break;
				default:
					$selected_terms = ($post_filter === 'tag') ? $content_settings['tag_key'] : $content_settings['genre_key'];
					if (!empty($selected_terms)) {
						$tax_query = array('relation' => 'OR');
						foreach ($selected_terms as $term_id) {
							$tax_query[] = array(
								'field'    => 'term_id',
								'terms'    => intval($term_id),
								'operator' => '=',
							);
						}
						$args['tax_query'] = $tax_query;
						$post_data = $get_function($args)->results;
					}
					break;
			}

			// If we haven't already set $post_data, call the function once with the prepared arguments
			if (!isset($post_data)) {
				$post_data = call_user_func($get_function, $args)->results;
			}
		}

		$title_tag    = isset($settings['title_tag']) ? sanitize_text_field($settings['title_tag']) : 'h3';
		$slider_title = isset($settings['slider_title']) ? esc_html($settings['slider_title']) : '';

		$permalink_filter = $post_filter;
		if (isset($content_map[$settings['st_select_content_type']])) {
			$content_settings = $content_map[$settings['st_select_content_type']];
			
			// Handle genre filter
			if ($post_filter === 'genre' && !empty($content_settings['genre_key'])) {
				$permalink_filter = 'genre:' . implode(',', $content_settings['genre_key']);
			}
			// Handle tag filter
			elseif ($post_filter === 'tag' && !empty($content_settings['tag_key'])) {
				$permalink_filter = 'tag:' . implode(',', $content_settings['tag_key']);
			}
		}

		streamit_get_template(
			'elementor-widget/main-card-slider/html-main-card-' . $template_type . '.php',
			[
				'slick_settings' 		=> $slick_settings,
				'post_data' 			=> $post_data,
				'settings' 				=> $settings,
				'title_tag' 			=> $title_tag,
				'slider_title' 			=> $slider_title,
				'enable_premium_badges' => $settings['enable_premium_badges'],
				'post_filter' 			=> $permalink_filter,
			]
		);
	}
}

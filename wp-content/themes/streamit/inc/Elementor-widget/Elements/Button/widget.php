<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

class ST_Buttton extends Widget_Base
{

	public function get_name()
	{
		return 'st-button';
	}

	public function get_title()
	{
		return __('Streamit Button', 'streamit');
	}

	public function get_icon()
	{
		return 'eicon-button';
	}

	public function get_categories()
	{
		return ['streamit'];
	}

	protected function register_controls()
	{
		$this->start_controls_section(
			'section_21eZ2eh1Myn3Vx5qrK29',
			[
				'label' => __('Button', 'streamit'),
			]
		);

		$this->add_control(
			'button_text',
			[
				'label' => __('Text', 'streamit'),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'label_block' => true,
				'default' => __('Read More', 'streamit'),
			]
		);

		$this->add_control(
			'has_icon',
			[
				'label' => __('Use Icon?', 'streamit'),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
				'yes' => __('yes', 'streamit'),
				'no' => __('no', 'streamit'),
			]
		);

		$this->add_control(
			'button_icon',
			[
				'label' => __('Icon', 'streamit'),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'default' => [
					'value' => 'fas fa-play',
					'library' => 'fa-solid ',
				],
				'condition' => ['has_icon' => 'yes'],
			]
		);

		$this->add_responsive_control(
			'icon_position',
			[
				'label' => __('Icon Position', 'streamit'),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'right',
				'options' => [
					'left' => [
						'title' => __('Left', 'streamit'),
						'icon' => 'eicon-text-align-left',
					],
					'right' => [
						'title' => __('Right', 'streamit'),
						'icon' => 'eicon-text-align-right',
					],
				],
				'condition' => ['has_icon' => 'yes'],
			]
		);

		$this->add_control(
			'button_action',
			[
				'label' => __('Action', 'streamit'),
				'type' => Controls_Manager::SELECT,
				'default' => 'none',
				'label_block' => true,
				'options' => [
					'movie_tv'  => __('Open Movie / Tv Show / Video', 'streamit'),
					'product'  => __('Product', 'streamit'),
					'product_cat'  => __('product Category', 'streamit'),
					'page'  => __('Pages', 'streamit'),
					'link'  => __('Open Link', 'streamit'),
					'none'  => __('none', 'streamit'),
				],
			]
		);

		$this->add_control(
			'st_movie_tv_id',
			[
				'label' => __('Select Movie / Tv Show / Video', 'streamit'),
				'type' => Controls_Manager::SELECT2,
				'multiple' => false,
				'label_block' => true,
				'options' => isset($_REQUEST['editor_post_id']) && function_exists('streamit_get_merge_data') ? streamit_get_merge_data(['per_page' => -1 , 'post_type' => ['movie' , 'video' , 'tvshow']]) : [],
				'condition' => ['button_action' => 'movie_tv']
			]
		);


		$this->add_control(
			'st_product_id',
			[
				'label' => __('Select Product', 'streamit'),
				'type' => Controls_Manager::SELECT2,
				'multiple' => false,
				'label_block' => true,
				'options' => isset($_REQUEST['editor_post_id']) && function_exists('streamit_custom_post_data') ? streamit_custom_post_data('product', false) : [],
				'condition' => ['button_action' => 'product']
			]
		);

		$this->add_control(
			'st_product_cat_id',
			[
				'label' => __('Select Product category', 'streamit'),
				'type' => Controls_Manager::SELECT2,
				'multiple' => false,
				'label_block' => true,
				'options' => isset($_REQUEST['editor_post_id']) && function_exists('streamit_get_all_taxonomies') ? streamit_get_all_taxonomies('product_cat') : array(),
				'condition' => ['button_action' => 'product_cat']
			]
		);

		$this->add_control(
			'st_page_id',
			[
				'label' => __('Select Pages', 'streamit'),
				'type' => Controls_Manager::SELECT2,
				'multiple' => false,
				'label_block' => true,
				'options' => isset($_REQUEST['editor_post_id']) && function_exists('streamit_custom_post_data') ? streamit_custom_post_data(array('page'), false, true) : [],
				'condition' => ['button_action' => 'page']
			]
		);

		$this->add_control(
			'link',
			[
				'label' => __('Link', 'streamit'),
				'type' => Controls_Manager::URL,
				'dynamic' => [
					'active' => true,
				],
				'placeholder' => __('https://your-link.com', 'streamit'),
				'default' => [
					'url' => '#',
				],
				'condition' => ['button_action' => 'link']
			]
		);

		$this->end_controls_section();

	}


	protected function render()
	{
		// Render your widget output here
		$html = $link_url = $target = $rel = $modalid = '';

		$settings = $this->get_settings_for_display();

		$button_action = isset($settings['button_action']) && !empty($settings['button_action']) ? $settings['button_action'] : '';

		// Prepare the classes
		$classes = 'btn btn-primary';

		if (isset($settings['btn_has_box_shadow']) && $settings['btn_has_box_shadow'] == 'yes') {
			$classes .= ' st-box-shadow';
		}

		// Sanitize and escape button text
		$html .= esc_html($settings['button_text']);

		if (isset($settings['button_size']) && $settings['button_size'] != 'default') {
			$classes .= ' ' . esc_attr($settings['button_size']);
		}

		if (isset($settings['button_shape']) && $settings['button_shape'] != 'default') {
			$classes .= ' ' . esc_attr($settings['button_shape']);
		}

		if (isset($settings['button_style']) && $settings['button_style'] != 'default') {
			$class = $settings['button_style'] != 'st-btn-link' ? 'btn-primary' : '';
			$classes .= ' ' . esc_attr($settings['button_style']);
		}

		// Prepare the link URL
		if ($button_action == 'link' && isset($settings['link']['url']) && !empty($settings['link']['url'])) {
			$link_url = esc_url($settings['link']['url']);
		}

		if ($button_action == 'movie_tv' && !empty($settings['st_movie_tv_id'])) {
			$parts = explode('_' , $settings['st_movie_tv_id'] , 2);
			$link_url = esc_url(streamit_get_permalink($parts[0] , $parts[1]));
		}

		if ($button_action == 'page' && !empty($settings['st_page_id'])) {
			$link_url = esc_url(get_the_permalink((int) $settings['st_page_id']));
		}

		if ($button_action == 'product' && !empty($settings['st_product_id'])) {
			$link_url = esc_url(get_the_permalink((int) $settings['st_product_id']));
		}

		if ($button_action == 'product_cat' && !empty($settings['st_product_cat_id'])) {
			$link_url = esc_url(get_term_link($settings['st_product_cat_id'], 'product_cat'));
		}

		// Popup modal logic
		if ($button_action == 'popup') {
			$modalid = 'mymodal' . rand(10, 1000);
			$link_url = '#' . $modalid; // Link to the modal
		}

		if (isset($settings['link']['is_external']) && $settings['link']['is_external']) {
			$target = ' target="_blank"';
		}

		if (isset($settings['link']['nofollow']) && $settings['link']['nofollow']) {
			$rel = ' rel="nofollow"';
		}

		streamit_get_template(
			'elementor-widget/button/html-button.php',
			[
				'settings'      => $settings,
				'button_action' => $button_action,
				'classes'       => $classes,
				'link_url'      => $link_url,
				'html'           => $html,
				'target'         => $target,
				'rel'            => $rel,
				'modalid'        => $modalid
			]
		);
	}
}

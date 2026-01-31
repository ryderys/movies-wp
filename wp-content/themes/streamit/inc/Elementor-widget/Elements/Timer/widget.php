<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
	exit;
}

class ST_Timer extends Widget_Base
{
	public function get_name()
	{
		return 'st_timer';
	}

	public function get_title()
	{
		return esc_html__('Streamit Timer', 'streamit');
	}

	public function get_categories()
	{
		return ['streamit'];
	}

	public function get_icon()
	{
		return 'eicon-countdown';
	}

	protected function register_controls()
	{
		$this->start_controls_section(
			'section',
			[
				'label' => esc_html__('Count Down Timer', 'streamit'),
			]
		);

		$this->add_control(
			'timer_title',
			[
				'label' => esc_html__('Title', 'streamit'),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
				'default' => esc_html__('Default title', 'streamit'),
				'placeholder' => esc_html__('Type your title here', 'streamit'),
			]
		);

		$this->add_control(
			'title_tag',
			[
				'label'      => esc_html__('Title Tag', 'streamit'),
				'type'       => Controls_Manager::SELECT,
				'default'    => 'h2',
				'options'    => [
					'h1'          => esc_html__('h1', 'streamit'),
					'h2'          => esc_html__('h2', 'streamit'),
					'h3'          => esc_html__('h3', 'streamit'),
					'h4'          => esc_html__('h4', 'streamit'),
					'h5'          => esc_html__('h5', 'streamit'),
					'h6'          => esc_html__('h6', 'streamit'),
				],
			]
		);

		$this->add_control(
			'future_date',
			[
				'label'     => esc_html__('Select Date', 'streamit'),
				'type'      => Controls_Manager::DATE_TIME,
				'dynamic'   => [
					'active'    => true,
				],
				'label_block'   => true,
				'picker_options' => ['enableTime' => true]
			]
		);

		$this->add_control(
			'timer_format',
			[
				'label'      => esc_html__('Select Format', 'streamit'),
				'type'       => Controls_Manager::SELECT,
				'default'    => 'YODHMS',
				'options'    => [
					'YODHMS' => esc_html__('Year / Month / Day / Hour / Minute / Second', 'streamit'),
					'ODHMS'  => esc_html__('Month / Day/ Hour / Minute / Second', 'streamit'),
					'DHMS'   => esc_html__('Day / Hour / Minute / Second', 'streamit'),
					'HMS'    => esc_html__(' Hour / Minute / Second', 'streamit'),
					'MS'     => esc_html__('Minute / Second', 'streamit'),
					'S'      => esc_html__(' Second', 'streamit'),
				],
			]
		);

		$this->add_responsive_control(
			'align',
			[
				'label' => esc_html__('Alignment', 'streamit'),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => esc_html__('Left', 'streamit'),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__('Center', 'streamit'),
						'icon'  => 'eicon-text-align-center',
					],
					'right' => [
						'title' => esc_html__('Right', 'streamit'),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}} .st-count-down' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_count_down_title',
			[
				'label' => esc_html__('Title', 'streamit'),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'      => 'title_typography',
				'label'     => esc_html__(' Title Typography', 'streamit'),
				'selector'  => '{{WRAPPER}} .css_prefix-title.css_prefix-heading-title',
			]
		);

		$this->add_control(
			'timer_title_color',
			[
				'label' => esc_html__('Title Color', 'streamit'),
				'type'  => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .css_prefix-title.css_prefix-heading-title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'timer_title_hover_color',
			[
				'label' => esc_html__('Title Hover Color', 'streamit'),
				'type'  => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .css_prefix-title.css_prefix-heading-title:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'title_margin',
			[
				'label' => esc_html__('Margin', 'streamit'),
				'type'  => Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%'],
				'selectors' => [
					'{{WRAPPER}} .css_prefix-title.css_prefix-heading-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'title_padding',
			[
				'label' => esc_html__('Padding', 'streamit'),
				'type'  => Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%'],
				'selectors' => [
					'{{WRAPPER}} .css_prefix-title.css_prefix-heading-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_count_down_style',
			[
				'label' => esc_html__('Timer Text', 'streamit'),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'      => 'numbers_typography',
				'label'     => esc_html__(' Number Typography', 'streamit'),
				'selector'  => '{{WRAPPER}} .numberDisplay',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'periods_typography',
				'label' => esc_html__(' Period Typography', 'streamit'),
				'selector' => '{{WRAPPER}} .periodDisplay',
			]
		);

		$this->add_control(
			'title_color',
			[
				'label' => esc_html__('Timer Color', 'streamit'),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .st-count-down ' => 'color: {{VALUE}};',
					'{{WRAPPER}} .numberDisplay' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'label_color',
			[
				'label' => esc_html__('Text Color', 'streamit'),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .periodDisplay' => 'color: {{VALUE}};',

				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_border_style',
			[
				'label' => esc_html__('Border', 'streamit'),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'has_border',
			[
				'label' => esc_html__('Border?', 'streamit'),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'label_off',
				'yes' => esc_html__('yes', 'streamit'),
				'no' => esc_html__('no', 'streamit'),
			]
		);

		$this->add_control(
			'border_style',
			[
				'label' => esc_html__('Border Style', 'streamit'),
				'type' => Controls_Manager::SELECT,
				'default' => 'none',
				'options' => [
					'solid'  => esc_html__('Solid', 'streamit'),
					'dashed' => esc_html__('Dashed', 'streamit'),
					'dotted' => esc_html__('Dotted', 'streamit'),
					'double' => esc_html__('Double', 'streamit'),
					'outset' => esc_html__('outset', 'streamit'),
					'groove' => esc_html__('groove', 'streamit'),
					'ridge' => esc_html__('ridge', 'streamit'),
					'inset' => esc_html__('inset', 'streamit'),
					'hidden' => esc_html__('hidden', 'streamit'),
					'none' => esc_html__('none', 'streamit'),
				],
				'condition' => [
					'has_border' => 'yes',
				],
				'selectors' => [
					'{{WRAPPER}} .st-count-down .numberDisplay' => 'border-style: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'border_color',
			[
				'label' => esc_html__('Color', 'streamit'),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .st-count-down .numberDisplay' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'has_border' => 'yes',
				],
			]
		);

		$this->add_control(
			'border_width',
			[
				'label' => esc_html__('Border Width', 'streamit'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%'],
				'selectors' => [
					'{{WRAPPER}} .st-count-down .numberDisplay' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'has_border' => 'yes',
				],
			]
		);

		$this->add_control(
			'count_down_padding',
			[
				'label' => esc_html__('Padding', 'streamit'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%'],
				'selectors' => [
					'{{WRAPPER}} .st-count-down .numberDisplay' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'has_border' => 'yes',
				],
			]
		);

		$this->add_control(
			'border_radius',
			[
				'label' => esc_html__('Border Radius', 'streamit'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%'],
				'selectors' => [
					'{{WRAPPER}} .st-count-down .numberDisplay' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'has_border' => 'yes',
				],
			]
		);
		$this->end_controls_section();
	}

	protected function render()
	{
		$settings = $this->get_settings_for_display();

		$timer_title = !empty($settings['timer_title']) ? esc_html($settings['timer_title']) : '';

		streamit_get_template(
			'elementor-widget/timer/html-timer.php',
			[
				'settings' 		=> $settings,
				'timer_title' 	=> $timer_title,
				'future_date' 	=> $settings['future_date'],
				'title_tag' 	=> $settings['title_tag'],
				'timer_format' 	=> $settings['timer_format']
				]
		);
	}
}

<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_Section_Title extends Widget_Base
{
    public function get_name()
    {
        return 'section_title';
    }

    public function get_title()
    {
        return esc_html__('Section Title', 'streamit');
    }

    public function get_categories()
    {
        return ['streamit'];
    }

    public function get_icon()
    {
        return 'eicon-site-title';
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section',
            [
                'label' => __('Section Title', 'streamit'),
            ]
        );

        $this->add_control(
            'section_title',
            [
                'label' => __('Section Title', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'label_block' => true,
                'default' => __('Section Title', 'streamit'),
            ]
        );

        $this->add_control(
            'has_sub_title',
            [
                'label' => __('Has Sub Title?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
                'yes' => __('yes', 'streamit'),
                'no' => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'section_sub_title',
            [
                'label' => __('Section Sub Title', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
                'default' => __('Section Sub Title', 'streamit'),
                'condition' => ['has_sub_title' => 'yes'],
            ]
        );

        $this->add_control(
            'has_description',
            [
                'label' => __('Has Description?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
                'yes' => __('yes', 'streamit'),
                'no' => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'description',
            [
                'label' => __('Description', 'streamit'),
                'type' => Controls_Manager::TEXTAREA,
                'dynamic' => [
                    'active' => true,
                ],
                'placeholder' => __('Enter Title Description', 'streamit'),
                'default' => __('Lorem Ipsum is simply dummy text of the printing and typesetting industry.', 'streamit'),
                'condition' => ['has_description' => 'yes']
            ]
        );

        $this->add_control(
            'has_texture',
            [
                'label' => __('Enable Textured Title?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
                'yes' => __('yes', 'streamit'),
                'no' => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'title_texture_image',
            [
                'label' => esc_html__('Choose Title Image', 'streamit'),
                'type' => Controls_Manager::MEDIA,
                'condition' => ['has_texture' => 'yes'],
                'selectors' => [
                    '{{WRAPPER}} .texture-text' => 'background-image: url("{{URL}}");'
                ],
            ]
        );

        $this->add_control(
            'title_image',
            [
                'label' => esc_html__('Choose Title Image', 'streamit'),
                'type' => Controls_Manager::MEDIA,
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

        $this->add_responsive_control(
            'align',
            [
                'label' => __('Alignment', 'streamit'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Left', 'streamit'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'streamit'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __('Right', 'streamit'),
                        'icon' => 'eicon-text-align-right',
                    ]
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .st-title-box' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();


        $this->start_controls_section(
            'section_NIfezt7YM7feDaT9vP8J',
            [
                'label' => __('Title Box', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('titlebox_tabs');
        $this->start_controls_tab(
            'tabs_c8fpaelTGDkf951QeYf2',
            [
                'label' => __('Normal', 'streamit'),
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'titlebox_background',
                'label' => __('Background', 'streamit'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .st-title-box',
            ]
        );

        $this->add_control(
            'titlebox_has_border',
            [
                'label' => __('Set Custom Border?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
                'yes' => __('yes', 'streamit'),
                'no' => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'titlebox_border_style',
            [
                'label' => __('Border Style', 'streamit'),
                'type' => Controls_Manager::SELECT,
                'default' => 'none',
                'condition' => [
                    'titlebox_has_border' => 'yes',
                ],
                'options' => [
                    'solid'  => __('Solid', 'streamit'),
                    'dashed' => __('Dashed', 'streamit'),
                    'dotted' => __('Dotted', 'streamit'),
                    'double' => __('Double', 'streamit'),
                    'outset' => __('outset', 'streamit'),
                    'groove' => __('groove', 'streamit'),
                    'ridge' => __('ridge', 'streamit'),
                    'inset' => __('inset', 'streamit'),
                    'hidden' => __('hidden', 'streamit'),
                    'none' => __('none', 'streamit'),

                ],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box ' => 'border-style: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'titlebox_border_color',
            [
                'label' => __('Border Color', 'streamit'),
                'condition' => [
                    'titlebox_has_border' => 'yes',
                ],
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .st-title-box' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'titlebox_border_width',
            [
                'label' => __('Border Width', 'streamit'),
                'condition' => [
                    'titlebox_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'titlebox_border_radius',
            [
                'label' => __('Border Radius', 'streamit'),
                'condition' => [
                    'titlebox_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tabs_49pcfagYof19beG4w8Ee',
            [
                'label' => __('Hover', 'streamit'),
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'titlebox_hover_background',
                'label' => __('Hover Background', 'streamit'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .st-title-box:hover ',
            ]
        );

        $this->add_control(
            'titlebox_hover_has_border',
            [
                'label' => __('Set Custom Border?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
                'yes' => __('yes', 'streamit'),
                'no' => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'titlebox_hover_border_style',
            [
                'label' => __('Border Style', 'streamit'),
                'condition' => [
                    'titlebox_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::SELECT,
                'default' => 'none',
                'options' => [
                    'solid'  => __('Solid', 'streamit'),
                    'dashed' => __('Dashed', 'streamit'),
                    'dotted' => __('Dotted', 'streamit'),
                    'double' => __('Double', 'streamit'),
                    'outset' => __('outset', 'streamit'),
                    'groove' => __('groove', 'streamit'),
                    'ridge' => __('ridge', 'streamit'),
                    'inset' => __('inset', 'streamit'),
                    'hidden' => __('hidden', 'streamit'),
                    'none' => __('none', 'streamit'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box:hover' => 'border-style: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'titlebox_hover_border_color',
            [
                'label' => __('Border Color', 'streamit'),
                'condition' => [
                    'titlebox_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .st-title-box:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'titlebox_hover_border_width',
            [
                'label' => __('Border Width', 'streamit'),
                'condition' => [
                    'titlebox_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box:hover' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'titlebox_hover_border_radius',
            [
                'label' => __('Border Radius', 'streamit'),
                'condition' => [
                    'titlebox_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control(
            'titlebox_padding',
            [
                'label' => __('Padding', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],

            ]
        );

        $this->add_responsive_control(
            'titlebox_margin',
            [
                'label' => __('Margin', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],

            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_f4aS9uHc50Of5eNP8jbc',
            [
                'label' => __('Title', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'mobile_typography',
                'label' => __('Typography', 'streamit'),
                'selector' => '{{WRAPPER}} .st-title-box .st-title',
            ]
        );

        $this->start_controls_tabs('title_tabs');

        $this->start_controls_tab(
            'title_color_tab_normal',
            [
                'label' => __('normal', 'streamit'),
            ]
        );

        $this->add_control(
            'title_normal_color',
            [
                'label' => __('Color', 'streamit'),
                'type' => Controls_Manager::COLOR,

                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'title_back_color',
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .st-title-box .st-title',
            ]
        );

        $this->add_control(
            'iq_title_has_border',
            [
                'label' => __('Set Custom Border?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
                'yes' => __('yes', 'streamit'),
                'no' => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'iq_title_border_style',
            [
                'label' => __('Border Style', 'streamit'),
                'condition' => [
                    'iq_title_has_border' => 'yes',
                ],
                'type' => Controls_Manager::SELECT,
                'default' => 'none',
                'options' => [
                    'solid'  => __('Solid', 'streamit'),
                    'dashed' => __('Dashed', 'streamit'),
                    'dotted' => __('Dotted', 'streamit'),
                    'double' => __('Double', 'streamit'),
                    'outset' => __('outset', 'streamit'),
                    'groove' => __('groove', 'streamit'),
                    'ridge' => __('ridge', 'streamit'),
                    'inset' => __('inset', 'streamit'),
                    'hidden' => __('hidden', 'streamit'),
                    'none' => __('none', 'streamit'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-title' => 'border-style: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'iq_title_border_color',
            [
                'label' => __('Border Color', 'streamit'),
                'condition' => [
                    'iq_title_has_border' => 'yes',
                ],
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box .st-title' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'iq_title_border_width',
            [
                'label' => __('Border Width', 'streamit'),
                'condition' => [
                    'iq_title_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box .st-title' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'iq_title_border_radius',
            [
                'label' => __('Border Radius', 'streamit'),
                'condition' => [
                    'iq_title_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box .st-title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'title_color_tab_hover',
            [
                'label' => __('Hover', 'streamit'),
            ]
        );

        $this->add_control(
            'title_hover_color',
            [
                'label' => __('Color', 'streamit'),
                'type' => Controls_Manager::COLOR,

                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-title:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'title_hover_back_color',
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .st-title-box:hover .st-title',
            ]
        );

        $this->add_control(
            'iq_title_hover_has_border',
            [
                'label' => __('Set Custom Border?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
                'yes' => __('yes', 'streamit'),
                'no' => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'iq_title_hover_border_style',
            [
                'label' => __('Border Style', 'streamit'),
                'condition' => [
                    'iq_title_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::SELECT,
                'default' => 'none',
                'options' => [
                    'solid'  => __('Solid', 'streamit'),
                    'dashed' => __('Dashed', 'streamit'),
                    'dotted' => __('Dotted', 'streamit'),
                    'double' => __('Double', 'streamit'),
                    'outset' => __('outset', 'streamit'),
                    'groove' => __('groove', 'streamit'),
                    'ridge' => __('ridge', 'streamit'),
                    'inset' => __('inset', 'streamit'),
                    'hidden' => __('hidden', 'streamit'),
                    'none' => __('none', 'streamit'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box:hover .st-title' => 'border-style: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'iq_title_hover_border_color',
            [
                'label' => __('Border Color', 'streamit'),
                'condition' => [
                    'iq_title_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box:hover .st-title' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'iq_title_hover_border_width',
            [
                'label' => __('Border Width', 'streamit'),
                'condition' => [
                    'iq_title_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box:hover .st-title' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'iq_title_hover_border_radius',
            [
                'label' => __('Border Radius', 'streamit'),
                'condition' => [
                    'iq_title_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box:hover .st-title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control(
            'title_margin',
            [
                'label' => __('Margin', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'title_padding',
            [
                'label' => __('Padding', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->end_controls_section();

        $this->start_controls_section(
            'section_sub_title__',
            [
                'label' => __('Sub Title', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => ['has_sub_title' => 'yes']
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'sub_title_typography',
                'label' => __('Typography', 'streamit'),
                'selector' => '{{WRAPPER}} .st-title-box .st-sub-title',
            ]
        );

        $this->start_controls_tabs('sub_title_tabs');

        $this->start_controls_tab(
            'sub_title_color_tab_normal',
            [
                'label' => __('normal', 'streamit'),
            ]
        );

        $this->add_control(
            'sub_title_normal_color',
            [
                'label' => __('Color', 'streamit'),
                'type' => Controls_Manager::COLOR,

                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-sub-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'sub_title_back_color',
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .st-title-box .st-sub-title',
            ]
        );

        $this->add_control(
            'iq_sub_title_has_border',
            [
                'label' => __('Set Custom Border?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
                'yes' => __('yes', 'streamit'),
                'no' => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'iq_sub_title_border_style',
            [
                'label' => __('Border Style', 'streamit'),
                'condition' => [
                    'iq_sub_title_has_border' => 'yes',
                ],
                'type' => Controls_Manager::SELECT,
                'default' => 'none',
                'options' => [
                    'solid'  => __('Solid', 'streamit'),
                    'dashed' => __('Dashed', 'streamit'),
                    'dotted' => __('Dotted', 'streamit'),
                    'double' => __('Double', 'streamit'),
                    'outset' => __('outset', 'streamit'),
                    'groove' => __('groove', 'streamit'),
                    'ridge' => __('ridge', 'streamit'),
                    'inset' => __('inset', 'streamit'),
                    'hidden' => __('hidden', 'streamit'),
                    'none' => __('none', 'streamit'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-sub-title' => 'border-style: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'iq_sub_title_border_color',
            [
                'label' => __('Border Color', 'streamit'),
                'condition' => [
                    'iq_sub_title_has_border' => 'yes',
                ],
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box .st-sub-title' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'iq_sub_title_border_width',
            [
                'label' => __('Border Width', 'streamit'),
                'condition' => [
                    'iq_sub_title_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box .st-sub-title' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'iq_sub_title_border_radius',
            [
                'label' => __('Border Radius', 'streamit'),
                'condition' => [
                    'iq_sub_title_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box .st-sub-title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'sub_title_color_tab_hover',
            [
                'label' => __('Hover', 'streamit'),
            ]
        );

        $this->add_control(
            'sub_title_hover_color',
            [
                'label' => __('Color', 'streamit'),
                'type' => Controls_Manager::COLOR,

                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-sub-title:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'sub_title_hover_back_color',
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .st-title-box:hover .st-sub-title',
            ]
        );

        $this->add_control(
            'iq_sub_title_hover_has_border',
            [
                'label' => __('Set Custom Border?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
                'yes' => __('yes', 'streamit'),
                'no' => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'iq_sub_title_hover_border_style',
            [
                'label' => __('Border Style', 'streamit'),
                'condition' => [
                    'iq_sub_title_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::SELECT,
                'default' => 'none',
                'options' => [
                    'solid'  => __('Solid', 'streamit'),
                    'dashed' => __('Dashed', 'streamit'),
                    'dotted' => __('Dotted', 'streamit'),
                    'double' => __('Double', 'streamit'),
                    'outset' => __('outset', 'streamit'),
                    'groove' => __('groove', 'streamit'),
                    'ridge' => __('ridge', 'streamit'),
                    'inset' => __('inset', 'streamit'),
                    'hidden' => __('hidden', 'streamit'),
                    'none' => __('none', 'streamit'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box:hover .st-sub-title' => 'border-style: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'iq_sub_title_hover_border_color',
            [
                'label' => __('Border Color', 'streamit'),
                'condition' => [
                    'iq_sub_title_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box:hover .st-sub-title' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'iq_sub_title_hover_border_width',
            [
                'label' => __('Border Width', 'streamit'),
                'condition' => [
                    'iq_sub_title_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box:hover .st-sub-title' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'iq_sub_title_hover_border_radius',
            [
                'label' => __('Border Radius', 'streamit'),
                'condition' => [
                    'iq_sub_title_hover_has_border' => 'yes',
                ],
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}}  .st-title-box:hover .st-sub-title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control(
            'sub_title_margin',
            [
                'label' => __('Margin', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-sub-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'sub_title_padding',
            [
                'label' => __('Padding', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-sub-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->end_controls_section();

        $this->start_controls_section(
            'section_ZcASngaa14lc8er55aND',
            [
                'label' => __('Description', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => ['has_description' => 'yes']
            ]
        );

        $this->add_control(
            'description_heading_color',
            [
                'label' => __('Color', 'streamit'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->start_controls_tabs('description_tabs');
        $this->start_controls_tab(
            'description_color_tab_normal',
            [
                'label' => __('normal', 'streamit'),
            ]
        );

        $this->add_control(
            'description_normal_color',
            [
                'label' => __('Color', 'streamit'),
                'type' => Controls_Manager::COLOR,

                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-title-desc' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'description_color_tab_hover',
            [
                'label' => __('Hover', 'streamit'),
            ]
        );

        $this->add_control(
            'description_hover_color',
            [
                'label' => __('Color', 'streamit'),
                'type' => Controls_Manager::COLOR,

                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-title-desc:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'label' => __('Typography', 'streamit'),
                'selector' => '{{WRAPPER}} .st-title-box .st-title-desc',
            ]
        );

        $this->add_responsive_control(
            'desciption_marging',
            [
                'label' => __('Margin', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-title-desc' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'desciption_padding',
            [
                'label' => __('Padding', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-title-box .st-title-desc' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->end_controls_section();
    }


    protected function render()
    {
        $settings = $this->get_settings();

        $title_tag = isset($settings['title_tag']) && !empty($settings['title_tag']) ? $settings['title_tag'] : 'h4';
        $section_title  = isset($settings['section_title']) && !empty($settings['section_title']) ? $settings['section_title'] : '';
        streamit_get_template(
            'elementor-widget/section-title/html-section-title.php',
            [
                'settings'      => $settings,
                'title_tag'     => $title_tag,
                'section_title' => $section_title
            ]
        );
    }
}

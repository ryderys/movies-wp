<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_Accordion extends Widget_Base
{
    public function get_name()
    {
        return 'st_accordion';
    }

    public function get_title()
    {
        return esc_html__('Streamit Accordion', 'streamit');
    }

    public function get_categories()
    {
        return ['streamit'];
    }

    public function get_icon()
    {
        return 'eicon-accordion';
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section',
            [
                'label' => __('Accordion', 'streamit'),
            ]
        );

        $repeater = new Repeater();
        $repeater->add_control(
            'tab_title',
            [
                'label'         => __('Question', 'streamit'),
                'type'          => Controls_Manager::TEXT,
                'default'       => __('What is Lorem Ipsum?', 'streamit'),
                'placeholder'   => __('Tab Title', 'streamit'),
                'label_block'   => true,
            ]
        );

        $repeater->add_control(
            'tab_content',
            [
                'label'         => __('Answer', 'streamit'),
                'default'       => __('It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout.', 'streamit'),
                'placeholder'   => __('Tab Content', 'streamit'),
                'type'          => Controls_Manager::TEXTAREA,
                'show_label'    => false,
            ]
        );

        $this->add_control(
            'tabs',
            [
                'label'         => __('Tabs Items', 'streamit'),
                'type'          => Controls_Manager::REPEATER,
                'fields'        => $repeater->get_controls(),
                'default'       => [
                    [
                        'tab_title'     => __('Tab #1', 'streamit'),
                        'tab_content'   => __('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'streamit'),
                    ]
                ],
                'title_field' => '{{{ tab_title }}}',
            ]
        );

        $this->add_control(
            'has_icon',
            [
                'label'     => __('Use Icon?', 'streamit'),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'yes'       => __('yes', 'streamit'),
                'no'        => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'st_has_box_shadow',
            [
                'label'         => __('Box Shadow?', 'streamit'),
                'type'          => Controls_Manager::SWITCHER,
                'default'       => 'no',
                'yes'           => __('yes', 'streamit'),
                'no'            => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'active_icon',
            [
                'label'             => __('Active Icon', 'streamit'),
                'type'              => Controls_Manager::ICONS,
                'fa4compatibility'  => 'icon',
                'condition' => [
                    'has_icon'  => 'yes',
                ],
                'label_block'   => false,
                'skin'  => 'inline',
            ]
        );

        $this->add_control(
            'inactive_icon',
            [
                'label' => __('Inactive Icon', 'streamit'),
                'type' => Controls_Manager::ICONS,
                'fa4compatibility' => 'icon',
                'condition' => [
                    'has_icon' => 'yes',
                ],
                'label_block' => false,
                'skin' => 'inline',
            ]
        );

        $this->end_controls_section();


        $this->start_controls_section(
            'section_title_style',
            [
                'label' => __('Title', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

         $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'label' => esc_html__('Typography', 'streamit'),
                'selector' => '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-header .accordion-button .accordion-title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Text Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-header .accordion-button.collapsed .accordion-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'title_active_color',
            [
                'label' => __('Text Active Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-header .accordion-button .accordion-title ' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'title_back_color',
            [
                'label' => __('Background Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-header .accordion-button.collapsed' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'title_back_active_color',
            [
                'label' => __('Active Background Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-header .accordion-button' => 'background: {{VALUE}};',
                ],
            ]
        );
        $this->end_controls_section();

        $this->start_controls_section(
            'section_content_style',
            [
                'label' => __('Content', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'content_color',
            [
                'label' => __('Content Text Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-collapse .accordion-body' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'content_back_color',
            [
                'label' => __('Background Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-collapse .accordion-body' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_icon_style',
            [
                'label' => __('Icon', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'icon_active_color',
            [
                'label' => __('Active Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-header .accordion-button .st-icon-right .active_icon i' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'icon_inactive_color',
            [
                'label' => __('Inactive Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-header .accordion-button .st-icon-right .inactive_icon i' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_border_style',
            [
                'label' => __('Border', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'has_border',
            [
                'label' => __('Border?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'label_off',
                'yes' => __('yes', 'streamit'),
                'no' => __('no', 'streamit'),
            ]
        );

        $this->add_control(
            'border_style',
            [
                'label' => __('Border Style', 'streamit'),
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
                'condition' => [
                    'has_border' => 'yes',
                ],
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-header .accordion-button' => 'border-style: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'border_active_color',
            [
                'label' => __('Active Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-header .accordion-button' => 'border-color: {{VALUE}};',
                ],
                'condition' => [
                    'has_border' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'border_inactive_color',
            [
                'label' => __('Inactive Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-header .accordion-button.collapsed' => 'border-color: {{VALUE}};',
                ],
                'condition' => [
                    'has_border' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'border_width',
            [
                'label' => __('Border Width', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .css-prefix-accordion .accordion-item .accordion-header .accordion-button' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
        $tabs = $settings['tabs'];
        $id_int = rand(10, 100);


        // Initialize the accordion class with required classes
        $accordion_classes = 'accordion css-prefix-accordion';
        if ($settings['st_has_box_shadow'] == 'yes') {
            $accordion_classes .= ' st-box-shadow';
        }

        if (
            isset($settings['title_active_color']) && isset($settings['content_back_color']) &&
            $settings['title_active_color'] !== $settings['content_back_color']
        ) {
            $accordion_classes .= ' st-accordion-classic';
        }

        streamit_get_template(
            'elementor-widget/accordion/html-accordion.php',
            [
                'settings'          => $settings,
                'tabs'              => $tabs,
                'accordion_classes' => $accordion_classes,
                'id_int'            => $id_int
            ]
        );
    }
}

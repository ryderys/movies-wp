<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_Pricing_Plan extends Widget_Base
{
    public function get_name()
    {
        return 'st_pmp_pricing';
    }

    public function get_title()
    {
        return esc_html__('PMP Pricing Plan', 'streamit');
    }

    public function get_categories()
    {
        return ['streamit'];
    }

    public function get_icon()
    {
        return 'eicon-price-plan';
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_persons',
            [
                'label' => __('PMP Pricing Plan', 'streamit'),
            ]
        );

        $this->add_control(
            'pmp_pricing_plan_id',
            [
                'label'      => __('Select PMP Pricing Plan To Display', 'streamit'),
                'label_block' => true,
                'type'       => Controls_Manager::SELECT,
                'options'    => isset($_REQUEST['editor_post_id']) && function_exists('streamit_get_pricing_levels') ? streamit_get_pricing_levels() : [],
            ]
        );

        if (is_plugin_active("pmpro-woocommerce/pmpro-woocommerce.php")) {
            $this->add_control(
                'pmp_registration_page',
                [
                    'label'          => __('Select Registration Page For Guest Users', 'streamit'),
                    'label_block'     => true,
                    'type'           => Controls_Manager::SELECT,
                    'options'         => isset($_REQUEST['editor_post_id']) && function_exists('streamit_custom_post_data') ? streamit_custom_post_data('page', false, true) : [],
                ]
            );
        }

        $this->add_control(
            'show_discount_code',
            [
                'label'      => __('Apply Discount Code Directly At Checkout', 'streamit'),
                'type'       => Controls_Manager::SWITCHER,
                'label_on'   => __("Yes", 'streamit'),
                'label_off'  => __("No", 'streamit'),
            ]
        );

        $this->add_control(
            'discount_code',
            [
                'label'         => __('Select Discount Code', 'streamit'),
                'label_block'   => true,
                'type'          => Controls_Manager::SELECT,
                'options'       => isset($_REQUEST['editor_post_id']) && function_exists('streamit_pmp_discount_code_list') ? streamit_pmp_discount_code_list() : [],
                'condition'     => [
                    'show_discount_code' => 'yes'
                ]
            ]
        );

        $this->add_control(
            'show_sale_price',
            [
                'label'      => __('Show Sale Price', 'streamit'),
                'type'       => Controls_Manager::SWITCHER,
                'label_on'   => __("Yes", 'streamit'),
                'label_off'  => __("No", 'streamit'),
            ]
        );

        $this->add_control(
            'sale_price',
            [
                'label'      => __('Enter Sale Price', 'streamit'),
                'type'       => Controls_Manager::TEXT,
                'condition'  => [
                    'show_sale_price' => 'yes'
                ]
            ]
        );

        $this->add_control(
            'show_discount_banner',
            [
                'label'      => __('Show Discount Banner', 'streamit'),
                'type'       => Controls_Manager::SWITCHER,
                'label_on'   => __("Yes", 'streamit'),
                'label_off'  => __("No", 'streamit'),
            ]
        );

        $this->add_control(
            'discount_text',
            [
                'label'      => __('Enter Discount Text', 'streamit'),
                'type'       => Controls_Manager::TEXT,
                'default'    => __("Save 20%", 'streamit'),
                'condition'  => [
                    'show_discount_banner' => 'yes'
                ]
            ]
        );

        $this->add_control(
            'show_description',
            [
                'label'      => __('Show Description', 'streamit'),
                'type'       => Controls_Manager::SWITCHER,
                'label_on'   => __("Yes", 'streamit'),
                'label_off'  => __("No", 'streamit'),
            ]
        );

        $this->add_control(
            'show_expiration',
            [
                'label'      => __('Show Expiration Information (if any)', 'streamit'),
                'type'       => Controls_Manager::SWITCHER,
                'label_on'   => __("Yes", 'streamit'),
                'label_off'  => __("No", 'streamit'),
            ]
        );

        $this->add_control(
            'account_exists',
            [
                'label'         => __('Text to display when account already exists with the selected plan', 'streamit'),
                'type'          => Controls_Manager::TEXT,
                'label_block'   => true,
                'default'       => __("My Account", 'streamit'),
            ]
        );

        $this->add_control(
            'image',
            [
                'label'     => __('Choose Image', 'streamit'),
                'type'      => Controls_Manager::MEDIA,
                'dynamic'   => [
                    'active' => true,
                ],
            ]
        );

        $this->add_control(
            'enable_dynamic_features',
            [
                'label'      => __('Enable Membership Features', 'streamit'),
                'type'       => Controls_Manager::SWITCHER,
                'label_on'   => __("Yes", 'streamit'),
                'label_off'  => __("No", 'streamit'),
                'default'    => 'no',
                'description' => __('When enabled, features will be loaded from PMP Membership Plan Features. When disabled, use the static features below.', 'streamit'),
            ]
        );

        $this->add_control(
            'enable_custom_icons',
            [
                'label'      => __('Enable Custom Icons', 'streamit'),
                'type'       => Controls_Manager::SWITCHER,
                'label_on'   => __("Yes", 'streamit'),
                'label_off'  => __("No", 'streamit'),
                'default'    => 'no',
                'description' => __('When enabled, you can set custom icons for include and not include features.', 'streamit'),
                'condition'  => [
                    'enable_dynamic_features' => 'yes'
                ]
            ]
        );

        $this->add_control(
            'custom_include_icon',
            [
                'label'     => __('Custom Include Icon', 'streamit'),
                'type'      => Controls_Manager::ICONS,
                'default'   => [
                    'value'     => 'fas fa-check',
                    'library'   => 'fa-solid',
                ],
                'condition' => [
                    'enable_dynamic_features' => 'yes',
                    'enable_custom_icons' => 'yes'
                ]
            ]
        );

        $this->add_control(
            'custom_not_include_icon',
            [
                'label'     => __('Custom Not Include Icon', 'streamit'),
                'type'      => Controls_Manager::ICONS,
                'default'   => [
                    'value'     => 'fas fa-times',
                    'library'   => 'fa-solid',
                ],
                'condition' => [
                    'enable_dynamic_features' => 'yes',
                    'enable_custom_icons' => 'yes'
                ]
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'has_active',
            [
                'label' => __('Is not available?', 'streamit'),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'no',
                'yes'       => __('yes', 'streamit'),
                'no'        => __('no', 'streamit'),
            ]
        );

        $repeater->add_control(
            'tab_icon',
            [
                'label'     => __('Select Icon', 'streamit'),
                'type'      => Controls_Manager::ICONS,
                // 'fa4compatibility' => 'icon',
                'default'   => [
                    'value'     => 'fas fa-check',
                    'library'   => 'fa-solid',
                ],
            ]
        );

        $repeater->add_control(
            'plan_description',
            [
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
                        'plan_description' => __('Lorem ipsum', 'streamit'),
                    ]
                ],
                'title_field' => '{{{ plan_description }}}',
                'condition'     => [
                    'enable_dynamic_features!' => 'yes'
                ]
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {

        $settings = $this->get_settings();
        streamit_get_template(
            'elementor-widget/pricing-plan/html-pricing-plan.php',
            [
                'settings' => $settings
            ]
        );
    }
}

<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_Category_Box extends Widget_Base
{
    public function get_name()
    {
        return 'st_product_category_box';
    }

    public function get_title()
    {
        return __('Product Category Box', 'streamit');
    }

    public function get_icon()
    {
        return 'eicon-image-box';
    }

    public function get_categories()
    {
        return ['streamit'];
    }


    protected function register_controls()
    {
        $this->start_controls_section(
            'section_blog',
            [
                'label' => __('Product Category Box', 'streamit'),
            ]
        );

        $this->add_control(
            'category_title_list',
            [
                'label'         => __('Choose Category', 'streamit'),
                'type'          => Controls_Manager::SELECT,
                'label_block'   => true,
                'multiple'      => true,
                'options'       => isset($_REQUEST['editor_post_id']) && function_exists('streamit_get_all_taxonomies') ? streamit_get_all_taxonomies('product_cat') : [],
            ]
        );

        $this->add_control(
            'add_custom_image',
            [
                'label'         => esc_html__('Add Custom Image', 'streamit'),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__('yes', 'streamit'),
                'label_off'     => esc_html__('No', 'streamit'),
                'return_value'  => 'yes',
                'default'       => 'no',
            ]
        );

        $this->add_control(
            'cat_image',
            [
                'label'         => esc_html__('Choose Image', 'streamit'),
                'type'          => Controls_Manager::MEDIA,
                'default'       => [
                    'url'           => streamit_placeholder_image(),
                ],
                'condition'     => ['add_custom_image' => 'yes'],
            ]
        );


        $this->add_responsive_control(
            'box_height',
            [
                'label'         => esc_html__('Height', 'streamit'),
                'type'          => Controls_Manager::SLIDER,
                'size_units'    => ['px', '%', 'em'],
                'range'         => [
                    'px'            => [
                        'min'           => 0,
                        'max'           => 1000,
                        'step'          => 5,
                    ],
                    'em'            => [
                        'min'           => 0,
                        'max'           => 100,
                    ],
                ],
                'default'           => [
                    'unit'              => 'px',
                    'size'              => 500,
                ],
                'selectors'     => [
                    '{{WRAPPER}} .css_prefix-product-box' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_button',
            [
                'label'     => __('Button', 'streamit'),
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label'     => esc_html__('Text', 'streamit'),
                'type'      => Controls_Manager::TEXT,
                'dynamic'   => [
                    'active'     => true,
                ],
                'label_block'   => true,
                'default'       => esc_html__('Shop now', 'streamit'),
            ]
        );

        $this->add_control(
            'has_icon',
            [
                'label'     => esc_html__('Use Icon?', 'streamit'),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'no',
                'yes'       => esc_html__('yes', 'streamit'),
                'no'        => esc_html__('no', 'streamit'),
            ]
        );

        $this->add_control(
            'button_icon',
            [
                'label'     => esc_html__('Icon', 'streamit'),
                'type'      => Controls_Manager::ICONS,
                'fa4compatibility' => 'icon',
                'default'   => [
                    'value'     => 'fas fa-star'
                ],
                'condition'    => [
                    'has_icon'  => 'yes',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'icon_position',
            [
                'label'     => esc_html__('Icon Position', 'streamit'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'right',
                'options'   => [
                    'left'      => [
                        'title'         => esc_html__('Left', 'streamit'),
                        'icon'          => 'eicon-text-align-left',
                    ],
                    'right'     => [
                        'title'         => esc_html__('Right', 'streamit'),
                        'icon'          => 'eicon-text-align-right',
                    ],
                ],
                'condition'     => [
                    'has_icon'          => 'yes',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {

        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        $settings = $this->get_settings_for_display();

        $image_url  = '';
        $parent_id  = is_numeric($settings['category_title_list'])  ?  get_term($settings['category_title_list']) : get_term_by('slug', $settings['category_title_list'], 'product_cat');

        if ($settings['add_custom_image'] == 'yes') {
            $image_url = $settings['cat_image']['url'];
        } else {
            if (!empty($parent_id) && $parent_id->term_id != 0) {
                $thumbnail_id = get_term_meta($parent_id->term_id, 'thumbnail_id', true);
            } else {
                $thumbnail_id = get_term_meta($parent_id, 'thumbnail_id', true);
            }
            $image_url = wp_get_attachment_url($thumbnail_id);
        }

        $term = get_term($parent_id->parent != 0 ? $parent_id->parent : $parent_id);

        streamit_get_template(
            'elementor-widget/category-box/html-category-box.php',
            [
                'settings'      => $settings,
                'image_url'     => $image_url,
                'parent_id'     => $parent_id,
                'term'          => $term
            ]
        );
    }
}

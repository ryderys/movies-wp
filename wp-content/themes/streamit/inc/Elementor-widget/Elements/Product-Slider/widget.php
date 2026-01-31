<?php

namespace Elementor;

use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}

class ST_Products_Slider extends Widget_Base
{
    public $product_query;
    public $max_page;

    public function get_name()
    {
        return 'st_products_slider';
    }

    public function get_title()
    {
        return __('WooCommerce Products Slider', 'streamit');
    }

    public function get_categories()
    {
        return ['streamit'];
    }

    public function get_icon()
    {
        return 'eicon-product-images';
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_blog',
            [
                'label' => __('Products Slider', 'streamit'),
            ]
        );

        $this->add_control(
            'product_slider_title',
            [
                'label' => __('Title', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Title', 'streamit'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'product_type',
            [
                'label'      => __('Select Product', 'streamit'),
                'type'       => Controls_Manager::SELECT,
                'default'    => 'products',
                'options'    => [

                    'featured_products'     => __('Feature Product', 'streamit'),
                    'recent_products'         => __('Recent Product', 'streamit'),
                    'sale_products'           => __('Sale Product', 'streamit'),
                    'best_selling_products' => __('Best Selling Product', 'streamit'),
                    'top_rated_products'    => __('Top Rated Product', 'streamit'),
                    'products'              => __('All Products', 'streamit'),
                ],
            ]
        );

        $this->add_control(
            'woo_order',
            [
                'label'   => __('Order By', 'streamit'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => [
                    'DESC' => esc_html__('Descending', 'streamit'),
                    'ASC' => esc_html__('Ascending', 'streamit')
                ],
            ]
        );

        $this->add_control(
            'woo_per_page',
            [
                'label' => __('Product per Slide', 'streamit'),
                'type' => Controls_Manager::NUMBER,
                'min' => -1,
                'step' => 1,
                'default' => 10,
            ]
        );

        $this->add_control(
            'view_all_switch',
            [
                'label' => __('Use View All Button ?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'yes' => __('Yes', 'streamit'),
                'no' => __('No', 'streamit'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'use_custom_link_viewall',
            [
                'label' => __('Use View All custom link ?', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'yes' => __('Yes', 'streamit'),
                'no' => __('No', 'streamit'),
                'return_value' => 'yes',
                'default' => 'no',
                'condition' => ['view_all_switch' => 'yes'],
            ]
        );

        $this->add_control(
            'use_custom_link_text',
            [
                'label' => __('Custom Link Text', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Link Text', 'streamit'),
                'label_block' => true,
                'condition' => [
                    'use_custom_link_viewall' => 'yes',
                    'view_all_switch' => 'yes'
                ],
            ]
        );

        $this->add_control(
            'view_all_custom_link',
            [
                'label' => __('Custom link', 'streamit'),
                'type' => Controls_Manager::URL,
                'placeholder' => __('https://your-link.com', 'streamit'),
                'show_external' => true,
                'default' => [
                    'url' => '',
                    'is_external' => true,
                    'nofollow' => true,
                ],
                'condition' => [
                    'use_custom_link_viewall' => 'yes',
                    'view_all_switch' => 'yes'
                ],
            ]
        );

        $this->add_control(
            'show_catalog',
            [
                'label' => __('Show Catalog ordering', 'streamit'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'streamit'),
                'label_off' => __('Hide', 'streamit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => ['show_pagination' => 'yes'],
            ]
        );

        $this->add_control(
            'woo_category',
            [
                'label' => __('Display Product From Specific Category', 'streamit'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => true,
                'options' => isset($_REQUEST['editor_post_id']) && function_exists('streamit_get_all_taxonomies') ? streamit_get_all_taxonomies("product_cat") : [],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'slick_control_section',
            [
                'label' => __('Slider Control', 'streamit'),
            ]
        );

        $this->add_control(
            'desk_number',
            [
                'label' => __('Desktop view', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'label_block' => true,
                'default' => '5',
            ]
        );

        $this->add_control(
            'lap_number',
            [
                'label' => __('Laptop view', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'label_block' => true,
                'default' => '4',
            ]
        );


        $this->add_control(
            'tab_number',
            [
                'label' => __('Tablet view', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'label_block' => true,
                'default' => '3',
            ]
        );

        $this->add_control(
            'mob_number',
            [
                'label' => __('Mobile view', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'label_block' => true,
                'default' => '2',
            ]
        );

        $this->add_control(
            'autoplay',
            [
                'label' => __('Autoplay', 'streamit'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'true' => __('True', 'streamit'),
                    'false' => __('False', 'streamit'),
                ],
                'default' => 'false',
            ]
        );

        $this->add_control(
            'autoplay_speed',
            [
                'label' => __('Autoplay Speed', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'label_block' => false,
                'condition' => ['autoplay' => 'true'],
                'default' => '2000',
            ]
        );

        $this->add_control(
            'infinite',
            [
                'label' => __('Infinite', 'streamit'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'true' => __('True', 'streamit'),
                    'false' => __('False', 'streamit'),
                ],
                'default' => 'false',
            ]
        );

        $this->add_control(
            'speed',
            [
                'label' => __('Speed', 'streamit'),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
                'default' => '300',
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

        $this->start_controls_section(
            'section_title_style',
            [
                'label' => esc_html__('Slider Heading ', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'label' => esc_html__('Typography', 'streamit'),
                'selector' => '{{WRAPPER}} .st-woocommerce-product-slider .st-main-header  .main-title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => esc_html__('Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}  .st-woocommerce-product-slider .st-main-header  .main-title' => 'color:{{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'title_hover_color',
            [
                'label' => esc_html__('Hover Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}  .st-woocommerce-product-slider .st-main-header  .main-title:hover' => 'color:{{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'title_margin',
            [
                'label' => esc_html__('Margin', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-woocommerce-product-slider .st-main-header' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'title_padding',
            [
                'label' => esc_html__('Padding', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-woocommerce-product-slider .st-main-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_product_style',
            [
                'label' => esc_html__('Product ', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'product_margin',
            [
                'label' => esc_html__('Margin', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-woocommerce-product-slider .css_prefix-sub-product' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],

            ]
        );

        $this->add_responsive_control(
            'product_padding',
            [
                'label' => esc_html__('Padding', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-woocommerce-product-slider .css_prefix-sub-product' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_product_content_style',
            [
                'label' => esc_html__('Product Content Box', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'product_content_padding',
            [
                'label' => esc_html__('Padding', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-woocommerce-product-slider .css_prefix-sub-product .product-caption' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_product_title_style',
            [
                'label' => esc_html__('Product Content', 'streamit'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'product_title_typography',
                'label' => esc_html__('Title Typography', 'streamit'),
                'selector' => '{{WRAPPER}} .st-woocommerce-product-slider .css_prefix-sub-product .product-caption .woocommerce-loop-product__title',
            ]
        );

        $this->add_control(
            'product_title_color',
            [
                'label' => esc_html__('Title Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}  .st-woocommerce-product-slider .css_prefix-sub-product .product-caption .woocommerce-loop-product__title > a' => 'color:{{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'product_hover_title_color',
            [
                'label' => esc_html__(' Title Hover Color', 'streamit'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}  .st-woocommerce-product-slider .css_prefix-sub-product .product-caption .woocommerce-loop-product__title:hover > a' => 'color:{{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'product_title_margin',
            [
                'label' => esc_html__('Product Title Margin', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-woocommerce-product-slider .css_prefix-sub-product .product-caption .woocommerce-loop-product__title ' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'product_price_margin',
            [
                'label' => esc_html__('Product Price Margin', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-woocommerce-product-slider .css_prefix-sub-product .product-caption .price-detail' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'product_rating_margin',
            [
                'label' => esc_html__('Product Rating Margin', 'streamit'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .st-woocommerce-product-slider .css_prefix-sub-product .product-caption .container-rating' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    public function render()
    {

        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        $settings = $this->get_settings_for_display();
        $slick_settings = array(
            "dots"                 => false,
            "slidesToShow"         => !empty($settings['desk_number']) ? intval($settings['desk_number']) : 6,
            "slidesToScroll"     => !empty($settings['desk_number']) ? intval($settings['desk_number']) : 6,
            "arrows"             => !empty($settings['nav-arrow']) && $settings['nav-arrow'] === "true" ? true : false,
            "autoplay"             => !empty($settings['autoplay']) && $settings['autoplay'] === "true" ? true : false,
            "autoplaySpeed"     => !empty($settings['autoplay_speed']) ?  intval($settings['autoplay_speed']) : 2000,
            "speed"             => !empty($settings['speed']) ?  intval($settings['speed']) : 300,
            "infinite"          => !empty($settings['infinite']) && $settings['infinite'] === "true" ? true : false,
            "responsive"         => [
                [
                    "breakpoint"     => 1367,
                    "settings"         => [
                        "slidesToShow"     => !empty($settings['lap_number']) ? intval($settings['lap_number']) : 4,
                        "slidesToScroll"     => !empty($settings['lap_number']) ? intval($settings['lap_number']) : 4,
                    ]
                ],
                [
                    "breakpoint"     => 1025,
                    "settings"         => [
                        "slidesToShow"     => !empty($settings['tab_number']) ? intval($settings['tab_number']) : 3,
                        "slidesToScroll"     => !empty($settings['tab_number']) ? intval($settings['tab_number']) : 3,
                    ]
                ],
                [
                    "breakpoint"     => 768,
                    "settings"     => [
                        "slidesToShow"     => !empty($settings['mob_number']) ? intval($settings['mob_number']) : 2,
                        "slidesToScroll"     => !empty($settings['mob_number']) ? intval($settings['mob_number']) : 2,
                    ]
                ]
            ]
        );

        $category = '';
        if (!empty($settings['woo_category'])) {
            foreach ($settings['woo_category'] as $element) {
                $category .= $element . ",";
            }
            $category = "category=" . '"' . rtrim($category, ",") . '"';
        }

        do_action('woocommerce_before_shop_loop');

        add_filter('woocommerce_product_loop_start', '__return_empty_string', 10);

        try {
            add_filter(
                'wc_get_template_part',
                function ($template, $slug, $name) {
                    // Check for specific slugs or names if needed
                    if ($slug === 'content' && $name === 'product') {
                        $template = locate_template('template-parts/wocommerce/entry.php');
                    }
                    return $template;
                },
                10,
                3
            );

            // Widget rendering logic here
        } finally {
            remove_filter('wc_get_template_part', function () {}, 10); // Match the closure
        }

        remove_all_actions('woocommerce_shortcode_before_' . $settings['product_type'] . '_loop');

        if ($this->get_settings()['view_all_switch'] === 'yes' || !empty($this->get_settings()['product_slider_title'])) {
            add_action(
                'woocommerce_shortcode_before_' . $settings['product_type'] . '_loop',
                function () use ($slick_settings) {
                    // Initialize view all link
                    $view_all = '';

                    // Check if 'view_all_switch' is enabled
                    if ($this->get_settings()['view_all_switch'] === 'yes') {
                        $this->add_render_attribute('st_class', 'class', 'st-view-all');

                        // Check for custom link settings
                        if ($this->get_settings()['use_custom_link_viewall'] == 'yes') {
                            if ($this->get_settings()['view_all_custom_link']['url']) {
                                $url = $this->get_settings()['view_all_custom_link']['url'];
                                $this->add_render_attribute('st_class', 'href', esc_url($url));

                                if ($this->get_settings()['view_all_custom_link']['is_external']) {
                                    $this->add_render_attribute('st_class', 'target', '_blank');
                                }

                                if ($this->get_settings()['view_all_custom_link']['nofollow']) {
                                    $this->add_render_attribute('st_class', 'rel', 'nofollow');
                                }
                            }
                            $view_all_text = esc_html($this->get_settings()['use_custom_link_text']);
                            $view_all = '<a ' . $this->get_render_attribute_string('st_class') . '>' . $view_all_text . '</a>';
                        } else {
                            $view_all_text = get_option('streamit_options')['streamit_viewall_text'];
                            $cat = '';
                            if (!empty($this->get_settings()['woo_category'])) {
                                $cat = '&cat=' . implode(',', $this->get_settings()['woo_category']);
                            }
                            $view_all = '<a class="st-view-all" href="' . esc_url(get_page_link(get_option('streamit_options')['streamit_viewall_link'])) . '?type=product&title=' . $this->get_settings()['product_slider_title'] . '&p_type=' . esc_attr($this->get_settings()['product_type'] . $cat) . '">' . esc_html__('Want More?', 'streamit') . '</a>';
                        }
                    }

                    // Start the main header
?>
                <div class="st-main-header d-flex align-items-center justify-content-between">
                    <h4 class="main-title">
                        <?php echo $this->get_settings()['product_slider_title']; ?>
                    </h4>
                    <?php echo isset($view_all) ? wp_kses_post($view_all) : ''; ?>
                </div>

                <!-- Pass settings as data attributes directly in the div -->
                 <div class="section-spacing-bottom">
                <div class='css_prefix-slick-general st-skeleton'
                    data-slider_settings='<?php echo esc_attr(wp_json_encode($slick_settings)); ?>'
                    data-extra_settings='<?php echo esc_attr(wp_json_encode(($this->get_settings()['nav-arrow'] === "true" ? true : false))); ?>'>

            <?php
                },
                999
            );
        }

        add_filter('woocommerce_product_loop_end', '__return_empty_string', 10);

        do_action('woocommerce_after_shop_loop');

        // Add the corresponding after action

        $details = array(
            'category' => $category,
        );

        streamit_get_template(
            'elementor-widget/products-slider/html-products-slider.php',
            [
                'slick_settings'    => $slick_settings,
                'details'           => $details,
                'settings'          => $settings
            ]
        ); ?>
                </div>
                </div>
        <?php }
}

<?php

/**
 * Footer Widget Registration and Layout Configuration.
 *
 * This class handles the functions for registering footer widgets and setting up their layouts.
 * It includes dynamic handling of widget areas, their layout, and customization through options.
 *
 * @package streamit
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class for Footer Widget Registration and Layout.
 */
class streamit_footer
{

    public function __construct()
    {
        add_action('widgets_init', [$this, 'register_footer_widget'], 10);
        add_action('widgets_init', [$this, 'register_footer_copyright_widget'], 10);
    }

    /**
     * Registers Footer Widget Areas.
     *
     * Registers multiple footer widget areas based on the options stored in the 'streamit_options'.
     * Each widget area can have different text alignment classes based on user settings.
     *
     * @since 1.0.0
     */
    public function register_footer_widget()
    {
        $streamit_options =  get_option('streamit_options');
        $sidebar_title_tag = apply_filters('widgets_title_wrapper', 'h3');

        $number_of_sidebars = 4;
        if (class_exists('ReduxFramework')) {
            $number_of_sidebars = 5;
        }

        $default = [
            '1' => esc_html__('text-start', 'streamit'),
            '2' => esc_html__('text-end', 'streamit'),
            '3' => esc_html__('text-center', 'streamit'),
        ];

        for ($i = 1; $i <= $number_of_sidebars; $i++) {

            register_sidebar(
                [
                    'id' => 'ct-footer-sidebar-' . esc_attr($i),
                    'name' => esc_html__('Footer Widget Area ', 'streamit') . esc_html($i),
                    'before_widget' => '<div class="widget %2$s" id="%1$s">',
                    'after_widget' => '</div>',
                    'before_title' => '<' . esc_attr($sidebar_title_tag) . ' class="widget-title">',
                    'after_title' => '</' . esc_attr($sidebar_title_tag) . '>',
                ]
            );
        }

    }

    /**
     * Registers Footer Copyright Widget Areas.
     *
     * Registers one or two footer copyright widget areas based on the Redux Framework plugin status.
     * These areas allow customization of the footer copyright content.
     *
     * @since 1.0.0
     */
    public function register_footer_copyright_widget()
    {
        $streamit_options =  get_option('streamit_options');
        $sidebar_title_tag = apply_filters('widgets_title_wrapper', 'h3');

        $number_of_sidebars = 1;
        if (class_exists('ReduxFramework')) {
            $number_of_sidebars = 2;
        }

        for ($i = 1; $i <= $number_of_sidebars; $i++) {
            register_sidebar(
                [
                    'id' => 'ct-footer-copyright-sidebar-' . esc_attr($i),
                    'name' => esc_html__('Footer Copyright Area ', 'streamit') . esc_html($i),
                    'before_widget' => '<div class="widget %2$s" id="%1$s">',
                    'after_widget' => '</div>',
                    'before_title' => '<' . esc_attr($sidebar_title_tag) . ' class="widget-title">',
                    'after_title' => '</' . esc_attr($sidebar_title_tag) . '>',
                ]
            );
        }
    }

    /**
     * Retrieves the Footer Layout Options.
     *
     * Determines the layout for the footer widgets based on the options set in the theme settings.
     * The layout is responsive and adjusts based on the selected option.
     *
     * @return array Footer layout columns classes.
     * @since 1.0.0
     */
    public function get_footer_option(): array
    {
        $data = [];
        if (
            is_active_sidebar('ct-footer-sidebar-1') || is_active_sidebar('ct-footer-sidebar-2') ||
            is_active_sidebar('ct-footer-sidebar-3') || is_active_sidebar('ct-footer-sidebar-4')
        ) {
            global $streamit_options;

            $options = !empty($streamit_options['streamit_footer_column_layout']) ? $streamit_options['streamit_footer_column_layout'] : '';
            $options = (isset($streamit_options['display_footer_meta']) && ($streamit_options['display_footer_meta'] == 'yes') && $streamit_options['default_footer_meta'] != '6') ? $streamit_options['default_footer_meta'] : $options;
            switch ($options) {
                case 1:
                    $data['value'] = ['col-12'];
                    break;
                case 2:
                    $data['value'] = ['col-lg-6 col-sm-6', 'col-lg-6 col-sm-6'];
                    break;
                case 3:
                    $data['value'] = ['col-lg-4 col-sm-6', 'col-lg-4 col-sm-6', 'col-lg-4 col-sm-6'];
                    break;
                case 4:
                    $data['value'] = ['col-lg-4 col-sm-6', 'col-lg-2  col-sm-6', 'col-lg-3 col-sm-6', 'col-lg-3 col-sm-6'];
                    break;
                case 5:
                    $data['value'] = ['col-lg-3 col-sm-6', 'col-lg-2 col-sm-6', 'col-lg-2 col-sm-6', 'col-lg-2 col-sm-6', 'col-lg-3 col-sm-6'];
                    break;
                default:
                    $data['value'] = ['col-lg-4 col-sm-6', 'col-lg-4  col-sm-6', 'col-lg-4 col-sm-6'];
            }
        }
        return $data;
    }
}

new streamit_footer();

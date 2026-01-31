<?php
/**
 * Class streamit_customizer
 *
 * Handles theme customization via the WordPress Customizer.
 *
 * @package streamit
 */
class streamit_customizer {

    /**
     * Gets the unique identifier for the theme component.
     *
     * @return string Component slug.
     */
    public function get_slug() : string {
        return 'customizer';
    }

    /**
     * Adds the action and filter hooks to integrate with WordPress.
     */
    public function __construct() {
        add_action( 'customize_register', array( $this, 'action_customize_register' ) );
        add_action( 'customize_preview_init', array( $this, 'action_enqueue_customize_preview_js' ) );
    }

    /**
     * Adds postMessage support for site title and description, plus a custom Theme Options section.
     *
     * @param WP_Customize_Manager $wp_customize Customizer manager instance.
     */
    public function action_customize_register( WP_Customize_Manager $wp_customize ) {
        // Enable live preview for site title, description, and header text color
        $wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
        $wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
        $wp_customize->get_setting( 'header_textcolor' )->transport = 'postMessage';

        // Selective refresh for site title and description
        if ( isset( $wp_customize->selective_refresh ) ) {
            $wp_customize->selective_refresh->add_partial(
                'blogname',
                array(
                    'selector'        => '.site-title a',
                    'render_callback' => function() {
                        bloginfo( 'name' );
                    },
                )
            );
            $wp_customize->selective_refresh->add_partial(
                'blogdescription',
                array(
                    'selector'        => '.site-description',
                    'render_callback' => function() {
                        bloginfo( 'description' );
                    },
                )
            );
        }

        /**
         * Theme options.
         */
        $wp_customize->add_section(
            'theme_options',
            array(
                'title'    => esc_html__( 'Theme Options', 'streamit' ),
                'priority' => 130, // Before Additional CSS.
            )
        );

        // Example setting and control
        $wp_customize->add_setting(
            'example_setting',
            array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'transport'         => 'postMessage',
            )
        );

        $wp_customize->add_control(
            'example_setting',
            array(
                'label'       => esc_html__( 'Example Setting', 'streamit' ),
                'section'     => 'theme_options',
                'settings'    => 'example_setting',
                'type'        => 'text',
            )
        );
    }

    /**
     * Enqueues JavaScript to make Customizer preview reload changes asynchronously.
     */
    public function action_enqueue_customize_preview_js() {
        wp_enqueue_script(
            'streamit-customizer',
            get_theme_file_uri( '/assets/js/custom.min.js' ),
            array( 'jquery' ),
            '1.0.0',
            true
        );
    }
}

// Initialize the customizer class
new streamit_customizer();

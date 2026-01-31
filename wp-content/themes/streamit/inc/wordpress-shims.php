<?php
/**
 * Shims for recent WordPress functions
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Adds backwards compatibility for wp_body_open() introduced with WordPress 5.2
 */
if ( ! function_exists( 'wp_body_open' ) ) {
    /**
     * Fire the wp_body_open action.
     *
     * This function provides backward compatibility for versions of WordPress prior to 5.2.
     * It allows themes and plugins to hook into the wp_body_open action, which is triggered
     * immediately after the opening <body> tag in a theme.
     *
     * @return void
     */
    function wp_body_open() {
        /**
         * Trigger the wp_body_open action.
         *
         * @since 5.2.0
         */
        do_action( 'wp_body_open' );
    }
}


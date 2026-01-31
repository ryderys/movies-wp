<?php

/**
 * Displays the post header
 *
 * @package WordPress
 * @subpackage streamit
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
$site_name = get_bloginfo( 'name' );
global $streamit_options;

// Check if the header logo option is set
if ( ! isset( $streamit_options['header_radio'] ) ) {
    // Default logo URL
    $logo_url = get_template_directory_uri() . '/static/assets/images/logo.png';
    echo '<a href="' . esc_url( home_url( '/' ) ) . '">';
    echo '<img class="img-fluid logo" src="' . esc_url( $logo_url ) . '" alt="' . esc_html( $site_name ) . '">';
    echo '</a>';
    return; // Exit the function after displaying the default logo
}

// Check if the custom header text is enabled
if ( '1' === $streamit_options['header_radio'] ) {
    // Use custom header text or fallback to 'streamit'
    $header_text = ! empty( $streamit_options['header_text'] ) ? esc_html( $streamit_options['header_text'] ) : esc_html__( 'streamit', 'streamit' ); ?>
    <span class="logo-text"><?php echo esc_html( $header_text ); ?></span> <?php
    return; // Exit after displaying the header text
}

// Get the custom logo URL
$logo_url = ! empty( $streamit_options['streamit_logo']['url'] ) ? esc_url( $streamit_options['streamit_logo']['url'] ) : get_template_directory_uri() . '/static/assets/images/logo.png';

// Check if header meta logo is enabled
if ( isset( $streamit_options['display_header_meta'] ) && 'yes' === $streamit_options['display_header_meta'] ) {
    $logo_url = ! empty( $streamit_options['select_header_logo_meta']['url'] ) ? esc_url( $streamit_options['select_header_logo_meta']['url'] ) : $logo_url;
}

echo '<a href="' . esc_url( home_url( '/' ) ) . '">';
echo '<img class="img-fluid logo" src="' . esc_url( $logo_url ) . '" alt="' . esc_html( $site_name ) . '">';
echo '</a>';


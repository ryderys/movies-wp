<?php

/**
 * Displays the header premium button
 *
 * @package WordPress
 * @subpackage streamit
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<?php
global $streamit_options;

if (!isset($streamit_options['header_radio'])) {
    $logo_url = get_template_directory_uri() . '/static/assets/images/logo.png';
    echo '<a href="' . esc_url(home_url('/')) . '">';
    echo '<img class="img-fluid logo" width="150" height="150" src="' . esc_url($logo_url) . '" alt="streamit">';
    echo '</a>';
    return;
}

if ($streamit_options['header_radio'] === '1' ) {
    $header_text = !empty( $streamit_options['header_text'] ) ? esc_html( $streamit_options['header_text'] ) : esc_html__( 'Streamit', 'streamit' );
    echo esc_html($header_text);
    return;
}

$logo_url = !empty( $streamit_options['streamit_logo']['url'] ) ? esc_url( $streamit_options['streamit_logo']['url']) : esc_url(get_template_directory_uri() . '/static/assets/images/logo.png');
$logo_url = ($streamit_options['display_header_meta'] == 'yes') ? esc_url( $streamit_options['select_header_logo_meta']['url']) : $logo_url ;
echo '<a href="' . esc_url(home_url('/')) . '">';
echo '<img class="img-fluid logo" width="150" height="150" src="' . esc_url($logo_url) . '" alt="'. esc_html("streamit" , 'streamit').'">';
echo '</a>';
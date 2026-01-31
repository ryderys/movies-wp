<?php

/**
 * Displays the default header
 *
 * @package WordPress
 * @subpackage streamit
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

global $streamit_options;

// Check if loader display is enabled and the loader GIF URL is set
$display_loader = isset( $streamit_options['streamit_display_loader'] ) && 'yes' === $streamit_options['streamit_display_loader'];
$loader_gif_url  = isset( $streamit_options['streamit_loader_gif']['url'] ) ? esc_url( $streamit_options['streamit_loader_gif']['url'] ) : '';

?>
<!-- Start loader -->
<?php if ( $display_loader && $loader_gif_url ) : ?>
    <div id="loading">
        <div id="loading-center">
            <img src="<?php echo esc_url($loader_gif_url); ?>" alt="<?php esc_attr_e( 'Loading...', 'streamit' ); ?>">
        </div>
    </div>
<?php endif; ?>
<!-- End loader -->

<!-- Different header or default header -->
<div id="page" class="site css_prefix <?php echo esc_attr( apply_filters( 'streamit_header_main_class', '' ) ); ?>">
    <?php
    // Display the header only if the condition is met
    if ( ! streamit_display_header() ) {
        return;
    }

    // Load the header template part
    streamit_get_template('header/header_default.php');



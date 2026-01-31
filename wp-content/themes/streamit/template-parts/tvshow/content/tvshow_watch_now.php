<?php

/**
 * The template for displaying tvshow watch now.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

?>

<div class="flex-grow-1">
    <a href="<?php echo esc_url( streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name() ) ) ?>" class="btn btn-primary w-100">
        <?php esc_html_e( 'Watch now', 'streamit' ) ?>
    </a>
</div>
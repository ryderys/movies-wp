<?php

/**
 * The template for displaying archive title.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

?>

<h6 class="person-title">
    <a href="<?php echo esc_url(streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name())) ?>" class="color-inherit">
        <?php echo esc_html($st_data->get_post_title()) ?>
    </a>
</h6>
<?php

/**
 * The template for displaying single title.
 *
 * @package Streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<?php if (!empty($st_data->get_post_title())) : ?>
    <h1 class="text-uppercase mt-1 mb-2 texture-text fw-bold font-size-37">
        <?php echo esc_html($st_data->get_post_title()) ?>
    </h1>
<?php endif; ?>
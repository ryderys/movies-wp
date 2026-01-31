<?php

/**
 * The template for displaying person title
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!empty($st_data->get_post_title())) :
?>

    <h1 class="mt-0 font-size-28"><?php echo esc_html($st_data->get_post_title()); ?></h1>

<?php endif; ?>
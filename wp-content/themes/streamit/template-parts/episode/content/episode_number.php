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
<?php echo !empty($st_data->get_meta('_episode_number')) ? esc_html($st_data->get_meta('_episode_number')) : '' ?>
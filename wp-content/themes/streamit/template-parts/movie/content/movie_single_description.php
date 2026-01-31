<?php

/**
 * The template for displaying description.
 *
 * @package Streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!empty($st_data->get_post_content())) :
?>
    <p class="movie-description readmore-wrapper" id="readmore-wrapper">
        <span class="readmore-text line-count-3">
            <?php echo strip_tags($st_data->get_post_content()); ?>

        </span>
        <button class="btn btn-sm btn-secondary cursor-pointer" data-bs-toggle="modal" data-bs-target="#viewMoreDataModal">
            <?php echo esc_html__('Read More', 'streamit'); ?>
        </button>
    </p>
<?php
endif;


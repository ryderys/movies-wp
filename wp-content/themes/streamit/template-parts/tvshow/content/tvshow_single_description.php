<?php

/**
 * The template for displaying description.
 *
 * @package Streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<p class="tvshow-description readmore-wrapper">
    <span class="readmore-text line-count-3">
        <?php echo method_exists($st_data, 'get_post_content') ? nl2br(strip_tags($st_data->get_post_content())) : '' ?>
    </span>
    <button class="btn btn-sm btn-secondary cursor-pointer" data-bs-toggle="modal" data-bs-target="#viewMoreDataModal">
            <?php esc_html_e('Read More', 'streamit'); ?>
    </button>
</p>
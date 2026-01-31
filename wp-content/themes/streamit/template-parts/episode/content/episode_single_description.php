<?php

/**
 * The template for displaying discroption model
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}
?>
<p class="episode-description readmore-wrapper">
  <span class="readmore-text line-count-3">
    <?php echo method_exists($st_data, 'get_post_content') ? strip_tags($st_data->get_post_content()) : '' ?>
  </span>
  <button class="btn btn-sm btn-secondary cursor-pointer" data-bs-toggle="modal" data-bs-target="#viewMoreDataModal">
    <?php echo esc_html__('Read More', 'streamit'); ?>
  </button>
</p>
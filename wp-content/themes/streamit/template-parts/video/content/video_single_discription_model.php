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
<div class="modal view-more-data-modal fade" id="viewMoreDataModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header pb-0">
        <?php streamit_get_template('video/content/video_single_title.php', ['st_data' => $st_data]);  ?>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-1">
        <?php streamit_get_template('video/single/video_single_metalist.php', ['st_data' => $st_data]);  ?>

        <?php
        $category_ids = streamit_get_term_relationships($st_data->get_id(), 'video_category');
        if (!empty($category_ids)) :
        ?>
          <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-3">
            <div class="viewmore-data-title">
              <h6 class="m-0"><?php echo esc_html_e('Category :', 'streamit') ?></h6>
            </div>
            <?php streamit_get_template('video/content/video_single_genre.php', ['st_data' => $st_data]);  ?>

          </div>
        <?php endif; ?>
        <?php
        $tag_ids = streamit_get_term_relationships($st_data->get_id(), 'video_tag');
        if (!empty($tag_ids)) :
        ?>
          <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-md-1">
            <div class="viewmore-data-title">
              <h6 class="m-0"><?php esc_html_e('Tags:', 'streamit') ?></h6>
            </div>
            <?php streamit_get_template('video/content/video_tag.php', ['st_data' => $st_data]);  ?>

          </div>
        <?php endif; ?>
        <div class="mt-4">
          <?php streamit_get_template('video/content/video_language.php', ['st_data' => $st_data, 'is_limit' => false]);  ?>

        </div>

        <p class="mt-4 mb-0">
          <?php echo method_exists($st_data, 'get_post_content') ? wp_kses_post($st_data->get_post_content()) : '' ?>
        </p>
      </div>
    </div>
  </div>
</div>
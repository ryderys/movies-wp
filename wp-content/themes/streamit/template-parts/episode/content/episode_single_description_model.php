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
                <?php streamit_get_template('episode/content/episode_title.php', ['st_data' =>  $st_data]); ?>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-1">
                <?php streamit_get_template('episode/single/episode_single_metalist.php', ['st_data' =>  $st_data]); ?>
                
                <p class="mt-4 mb-0">
                    <?php echo method_exists($st_data, 'get_post_content') ? wp_kses_post($st_data->get_post_content()) : '' ?>
                </p>
            </div>
        </div>
    </div>
</div>
<?php

/**
 * The template for displaying add playlist modal.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

?>
<button type="button" class="manage_playlist action-btn btn btn-link">
    <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php esc_attr_e('Playlist', 'streamit'); ?>">
        <?php echo esc_html__('Add Playlist', 'streamit'); ?>
    </span>
</button>

<div class="modal fade" id="creatplaylistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered creat-playlist-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title m-0" id="st_playlist_modal_title"> <?php esc_html_e('Create Playlist', 'streamit'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <form id="st_creat_playlist" action="#" method="post">
                    <input id="st_playlist_id" type="hidden" value="">
                    <div class="form-group mb-4">
                        <label class="form-label"><?php esc_html_e('Playlist Title', 'streamit'); ?></label>
                        <span class="text-danger">*</span>
                        <input class="form-control" type="text" id="st_playlist_title" value="" placeholder="<?php echo esc_html__('Enter playlist title', 'streamit'); ?>">
                    </div>
                    <div class="form-group mb-4">
                        <label class="form-label"><?php esc_html_e('Select Playlist Type', 'streamit'); ?></label>
                        <span class="text-danger">*</span>
                        <select class="form-control" id="st_playlist_post_type" name="playlist_type">
                            <option value="movie"><?php esc_html_e('Movie', 'streamit'); ?></option>
                            <option value="video"><?php esc_html_e('video', 'streamit'); ?></option>
                            <option value="episode"><?php esc_html_e('Episode', 'streamit'); ?></option>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit" id="st_playlist_submit_btn">
                        <span class="st-loader d-none"></span>
                        <?php echo esc_html__('Create Playlist', 'streamit'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
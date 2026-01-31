<?php

/**
 * The template for displaying playlist model
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_core_options;
if (isset($streamit_core_options['streamit_display_playlist']) && ($streamit_core_options['streamit_display_playlist'] == 'no')) {
    return;
}
$upcoming_data =  function_exists('streamit_is_upcoming') ? streamit_is_upcoming($st_data, 'episode') : [
    'is_upcoming' => false,
    'is_future_release' => false,
    'formatted_date' => ''
];

if ($upcoming_data['is_future_release'] && !empty($upcoming_data['formatted_date'])) {
    return;
}
?>

<div class="modal playlistModal fade" id="playlistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered playlist-modal modal-dialog-scrollable">
        <div class="modal-content">
            <?php echo do_shortcode('[streamit_playlist_modal post_type="episode" post_id="' . esc_attr($st_data->get_id()) . '"]'); ?>
        </div>
    </div>
</div>

<div class="modal fade" id="creatplaylistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered creat-playlist-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title m-0"> <?php esc_html_e('Create Playlist', 'streamit'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <form id="st_creat_playlist" action="#" method="post">
                    <input type="hidden" id="st_playlist_post_type" value="<?php echo esc_html($st_data->get_post_type()); ?>">
                    <div class="form-group mb-4">
                        <label class="form-label"><?php esc_html_e('Playlist Title', 'streamit'); ?></label>
                        <span class="text-danger">*</span>
                        <input class="form-control" type="text" id="st_playlist_title" value="" placeholder="<?php echo esc_html__('Enter playlist title' , 'streamit'); ?>">
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <span class="loader d-none"></span>
                        <?php echo esc_html__('Create Playlist', 'streamit'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
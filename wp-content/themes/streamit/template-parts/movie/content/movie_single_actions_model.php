<?php

/**
 * The template for displaying actions
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;
global $streamit_core_options;
$is_like_display = isset($streamit_options['streamit_display_like']) && ($streamit_options['streamit_display_like'] == 'no') ? false : true;
$is_social_share_display = isset($streamit_options['streamit_display_social_icons']) && ($streamit_options['streamit_display_social_icons'] == 'no') ? false : true;
$is_trailler_avaiable = empty($st_data->get_meta('_name_trailer_link')) ? false : true;
$is_playlist_display = isset($streamit_core_options['streamit_display_playlist']) && ($streamit_core_options['streamit_display_playlist'] == 'no') ? false : true;
$allow_download = function_exists('streamit_user_can_download') ? streamit_user_can_download($st_data) : false;
$upcoming_data =  function_exists('streamit_is_upcoming') ? streamit_is_upcoming($st_data, 'movie') : [
    'is_upcoming' => false,
    'is_future_release' => false,
    'formatted_date' => ''
];
?>
<ul class="actions-list list-inline m-0 p-0 d-flex align-items-center flex-wrap gap-3">
    <?php

    // Like Icone 
    if ($is_like_display && is_user_logged_in()) : ?>
        <li>
            <?php echo do_shortcode('[streamit_like_shortcode post_id="' . esc_attr($st_data->get_id()) . '" post_type="' . esc_attr($st_data->get_post_type()) . '"]'); ?>
        </li>
    <?php endif; ?>
    <?php if ($is_social_share_display) : ?>
        <li class="position-relative share-button dropend dropdown">
            <button type="button" class="action-btn btn btn-secondary border" data-bs-toggle="modal" data-bs-target="#shareModal">
                <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php esc_attr_e('Share', 'streamit'); ?>">
                    <?php echo st_get_icon('share-2'); ?>
                </span>
            </button>
        </li>
    <?php endif; ?>

    <!-- Playlist Modal  -->
    <?php if ($is_playlist_display && (!$upcoming_data['is_future_release'] || empty($upcoming_data['formatted_date']))) : ?>
        <li class="position-relative playlist-button dropend dropdown">
            <button type="button" class="action-btn btn btn-secondary border" data-bs-toggle="modal" data-bs-target="#playlistModal">
                <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php esc_attr_e('Playlist', 'streamit'); ?>">
                    <?php echo st_get_icon('playlist'); ?>
                </span>
            </button>
        </li>
    <?php endif; ?>

    <?php if ($allow_download &&(!$upcoming_data['is_future_release'] || empty($upcoming_data['formatted_date']))) :
        streamit_get_template('movie/content/movie_single_download_btn.php', ['st_data' => $st_data]); 
    endif; ?>
</ul>
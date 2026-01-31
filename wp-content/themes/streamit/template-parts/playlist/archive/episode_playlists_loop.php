<?php

/**
 * The template for displaying archive loop pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$playlist_id   = $st_data->get_playlist_id();
$playlist_name = $st_data->get_playlist_name();
$playlist_slug = $st_data->get_playlist_slug();
$playlist_relation = streamit_get_playlist_item($playlist_id, 'episode');
$data_count = is_array($playlist_relation) ? count($playlist_relation) : 0;
$first_data = !empty($playlist_relation[0]) ? $playlist_relation[0] : '';
$encoded_playlist_id = base64_encode('playlistid_' . $playlist_id);
$encoded_playlist_id = urlencode($encoded_playlist_id);
$playlist_url = streamit_get_permalink('episode_playlist', $encoded_playlist_id);

$image_url = streamit_placeholder_image();
if (!empty($first_data)) {
    $episode_data = streamit_get_episode((int) $first_data);
    if (!empty($episode_data)) {
        $image_url = !empty($episode_data->get_meta('thumbnail_id')) ? wp_get_attachment_image_url($episode_data->get_meta('thumbnail_id'), 'full') : streamit_placeholder_image();
    }
}
?>
<div class="col">
    <div class="playlist-card">
        <!-- Playlist Image -->
        <div class="image-box">
            <a href="<?php echo esc_url($playlist_url); ?>">
                <img src="<?php echo esc_url($image_url); ?>"
                    alt="<?php echo esc_attr($playlist_name); ?>"
                    class="img-fluid object-cover w-100 border-0">
            </a>
            <?php if ($data_count > 0) : ?>
                <a href="<?php echo esc_url($playlist_url); ?>" class="play-icon">
                    <?php echo st_get_icon('play'); ?>
                </a>
            <?php endif; ?>

        </div>
        <!-- Playlist Content -->
        <div class="content-part">
            <div class="d-flex justify-content-between gap-2 mb-1">
                <h5 class="my-0 title"><?php echo esc_html($playlist_name); ?></h5>
                <div class="dropdown">
                    <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo st_get_icon('three-dots-vertical'); ?>
                    </button>
                    <ul class="dropdown-menu border">
                        <li><a data-playlist-name="<?php echo esc_attr($playlist_name); ?>" data-playlist-id="<?php echo esc_attr($playlist_id); ?>" data-post-type="<?php echo esc_attr('episode'); ?>" class="manage_playlist dropdown-item update_user_playlist"><?php echo esc_html__('Update', 'streamit'); ?></a></li>
                        <li><a data-playlist_id="<?php echo esc_attr($playlist_id); ?>" data-post-type="<?php echo esc_attr('episode'); ?>" class="dropdown-item delete_user_playlist"><?php echo esc_html__('Delete', 'streamit'); ?></a></li>
                    </ul>
                </div>
            </div>
            <div class="small mb-1">
                <?php echo sprintf(
                    esc_html(_n('%s Episode', '%s Episodes', $data_count === 0 ? 1 : $data_count, 'streamit')),
                    number_format_i18n($data_count)
                ); ?>
            </div>
            <?php if ($data_count > 0) : ?>
                <a href="<?php echo esc_url($playlist_url); ?>" class="btn btn-link btn-playlist"><?php echo esc_html__('View playlist', 'streamit'); ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>
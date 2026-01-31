<?php

/**
 * The template for displaying episode details
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
// Get upcoming status using the common function
$upcoming_data = [];
if (!empty($content_data) && function_exists('streamit_get_tvshow_upcoming_status')) {
    $upcoming_data = streamit_get_tvshow_upcoming_status($content_data);
}
// Extract variables for backward compatibility
$is_upcoming = $upcoming_data['is_upcoming'] ?? true;
$release_date = $upcoming_data['release_date'] ?? '';

$tvshow    = streamit_get_tvshow((int)$content_data->get_meta('tvshow_id'));

$tvshow_upcoming_data = function_exists('streamit_is_upcoming') ? streamit_is_upcoming($tvshow, 'tvshow') : [
    'is_upcoming' => false,
    'is_future_release' => false,
    'formatted_date' => ''
];
$is_upcoming_tvshow = !$tvshow_upcoming_data['is_future_release'];

if (!empty($content_data)) :
?>
    <div class="detail-page">
        <div class="player">
            <?php
            if ($is_upcoming && !current_user_can('administrator')) { ?>
                <div class="episode-upcoming-player">
                    <?php streamit_get_template('common/html-upcoming-content.php', ['st_data' => $content_data]); ?>
                </div>
                <?php
            } else {
                // Device limit gating moved here from episode_single_player.php
                $device_limit_over = true;
                $total_limit = 0;
                $total_devices = 0;

                if (function_exists('streamit_get_user_devices_with_stats')) {
                    $current_user_id = get_current_user_id();
                    $device_stats = streamit_get_user_devices_with_stats($current_user_id);
                    $stats = $device_stats['stats'] ?? [];

                    $remaining_slots = $stats['remaining_slots'] ?? 0;
                    $total_limit = $stats['total_limit'] ?? 0;
                    $total_devices = $stats['total_devices'] ?? 0;

                    if ($remaining_slots !== 'unlimited' && $total_devices > $total_limit) {
                        $device_limit_over = false;
                    }
                }

                // Prepare episode image and tvshow URL for the restriction template
                $episode_id = $content_data->get_id();
                $post_image = '';
                $post_url = '';

                if (!empty($episode_id)) {
                    // Get thumbnail from meta field 
                    $thumb_id = $content_data->get_meta('thumbnail_id');
                    if (!empty($thumb_id)) {
                        $post_image = wp_get_attachment_image_url($thumb_id, 'full');
                    }

                    $tvshow = function_exists('streamit_get_tvshow') ? streamit_get_tvshow((int)$content_data->get_meta('tvshow_id')) : null;
                    if (!empty($tvshow)) {
                        $post_url = streamit_get_permalink('tvshow', $tvshow->get_post_name());
                    } else {
                        $post_url = get_permalink($episode_id);
                    }
                }

                if ($device_limit_over) {
                    streamit_get_template('episode/content/episode_single_player.php', ['st_data' => $content_data]);
                } else { ?>
                    <div class="episode-limit-login-player">
                        <?php streamit_get_template('common/html-device-limit-player-template.php', [
                            'post_image' => $post_image,
                            'total_limit' => $total_limit,
                            'total_devices' => $total_devices,
                            'post_url' => $post_url,
                            'page' => 'episode'
                        ]);
                        ?>
                    </div>
            <?php
                }
            }
            ?>
        </div>

        <div class="container-fluid">
            <div class="detail-part mt-5">

                <?php streamit_get_template('episode/content/episode_single_index.php', ['st_data' => $content_data]); ?>
                <?php streamit_get_template('episode/content/episode_title.php', ['st_data' => $content_data]); ?>
                <?php streamit_get_template('episode/content/episode_single_description.php', ['st_data' => $content_data]); ?>
                <?php streamit_get_template('episode/single/episode_single_metalist.php', ['st_data' => $content_data]); ?>

                <!-- Potentially remove redundancy here for the download model -->
                <?php streamit_get_template('episode/content/episode_single_download_model.php', ['st_data' => $content_data]); ?>

            </div>

            <?php streamit_get_template('episode/single/episode_single_actions.php', ['st_data' => $content_data]); ?>
        </div>
        <?php if ($is_upcoming_tvshow): ?>
            <div class="episodes">
                <?php streamit_get_template('episode/content/episode_single_get_seasons.php', ['st_data' => $content_data]); ?>
            </div>
        <?php endif; ?>
        <div class="section-spacing-top">
            <div class="container-fluid">
                <?php streamit_get_template('episode/content/episode_single_morelike_slider.php', ['st_data' => $content_data]); ?>
            </div>
        </div>

    </div>

    <!-- It might be worth checking if this needs to be called multiple times -->
    <?php streamit_get_template('episode/content/episode_single_share_model.php', ['st_data' => $content_data]); ?>
    <?php streamit_get_template('episode/content/episode_single_description_model.php', ['st_data' => $content_data]); ?>
    <?php streamit_get_template('episode/content/episode_single_playlist_model.php', ['st_data' => $content_data]); ?>

<?php

else : ?>
    <div class="container no-data-here">
        <p class="no_data_found"><?php echo esc_html__('No Episode found.', 'streamit'); ?></p>
    </div>
<?php endif; ?>
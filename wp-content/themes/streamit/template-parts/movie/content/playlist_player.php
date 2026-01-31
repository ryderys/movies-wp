<?php

/**
 * The template for displaying a single movie playlist player.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$content_type = $st_data->get_meta('_movie_choice');
$st_premium_lvl = $st_data->get_meta('_pmp_level');
$post_id = $st_data->get_id();
$post_slug = $st_data->get_post_name();
$post_url  = streamit_get_permalink('movie', $post_slug);
$has_access = function_exists('streamit_user_has_stream_access') ? streamit_user_has_stream_access($post_id, 'movie', get_current_user_id()) : false;
$thumbnai_image_id  = $st_data->get_meta('thumbnail_id');
$post_image = !empty($thumbnai_image_id) ? wp_get_attachment_image_url($thumbnai_image_id, "full") : '';

$continue_watch_results = streamit_get_continue_watching(get_current_user_id(), $st_data->get_post_type());
$watched_time = '';
if (!empty($continue_watch_results) && is_array($continue_watch_results)) {
    foreach ($continue_watch_results as $id => $results) {
        if ($id === $st_data->get_id()) {
            $watched_time   =   $results['watched_time'];
        }
    }
}
$controllers = streamit_media_player_controls();


$is_upcoming_data = function_exists( 'streamit_is_upcoming' ) ? streamit_is_upcoming( $st_data, 'movie' ) : [
    'is_upcoming' => false,
    'is_future_release' => false,
    'formatted_date' => ''
];
$is_upcoming = !$is_upcoming_data['is_future_release'] ?? false;
$device_limit_over = ! streamit_is_device_limit_exceeded();

$content = '';
switch ($content_type) {
    case 'movie_embed':
        $content = streamit_render_video_iframe('movie', $st_data);
        break;

    case 'movie_url':
        $content = streamit_render_url_video_player('movie', $st_data);
        break;

    default:
        $content = streamit_render_attach_video_player('movie', $st_data);
        break;
}
if ( ! empty( $content ) ) : ?>

    <?php if ( $is_upcoming || current_user_can( 'administrator' ) ) : ?>

        <?php if ( $has_access ) : ?>

            <?php if ( $device_limit_over ) : ?>
                <div class="streamit-player-ctrl"
                     data-current_time="<?php echo esc_attr( $watched_time ); ?>"
                     data-post_id="<?php echo esc_attr( $st_data->get_id() ); ?>"
                     data-post_type="<?php echo esc_attr( $st_data->get_post_type() ); ?>"
                     data-user_id="<?php echo esc_attr( get_current_user_id() ); ?>"
                     data-player_controls="<?php echo esc_attr( wp_json_encode( $controllers ) ); ?>">
                    <?php echo $content; ?>
                </div>
            <?php else : ?>
                <?php
                    $device_stats    = function_exists( 'streamit_get_user_devices_with_stats' ) ? streamit_get_user_devices_with_stats( get_current_user_id() ) : [ 'stats' => [] ];
                    $stats           = $device_stats['stats'] ?? [];
                    $total_limit     = $stats['total_limit'] ?? 0;
                    $total_devices   = $stats['total_devices'] ?? 0;

                streamit_get_template(
                    'common/html-device-limit-player-template.php',
                    [
                        'post_image'    => $post_image,
                        'total_limit'   => $total_limit,
                        'total_devices' => $total_devices,
                        'post_url'      => $post_url,
                        'class'         => 'playlist-login-limit-restriction'
                    ]
                );
                ?>
            <?php endif; ?>

        <?php else : ?>
            <div class="restricted-block">
                <?php echo streamit_display_restricted_content( $st_data, 'movie' ); ?>
            </div>
        <?php endif; ?>

    <?php else : ?>
        <?php streamit_get_template( 'common/html-upcoming-content.php', [ 'st_data' => $st_data ] ); ?>
    <?php endif; ?>

<?php else : ?>
    <div class="no_data_found">
        <?php echo esc_html__( 'No Data Found', 'streamit' ); ?>
    </div>
<?php endif; ?>
<?php

/**
 * The template for displaying single pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!empty($content_data)) :
    // Retrieve the video thumbnail
    $thumbnail = !empty($content_data->get_meta('thumbnail_id')) ? wp_get_attachment_image_url($content_data->get_meta('thumbnail_id'), 'full') : streamit_placeholder_image();
    ?>

    <div class="detail-page">
        <div class="video-section">

            <!-- Single Video Trailer -->
            <?php streamit_get_template('video/content/video_single_trailer.php', ['st_data' => $content_data]); ?>

            <div class="detail-part mt-md-0 mt-5">
                <!-- Video Genre -->
                <?php streamit_get_template('video/content/video_single_genre.php', ['st_data' => $content_data]); ?>

                <!-- Video Title -->
                <?php streamit_get_template('video/content/video_single_title.php', ['st_data' => $content_data]); ?>

                <!-- Video Description -->
                <?php streamit_get_template('video/content/video_single_description.php', ['st_data' => $content_data]); ?>

                <!-- Meta List -->
                <?php streamit_get_template('video/single/video_single_metalist.php', ['st_data' => $content_data]); ?>

                <!-- Video Language -->
                <?php streamit_get_template('video/content/video_language.php', ['st_data' => $content_data, 'is_limit' => false]); ?>

                <!-- Action Buttons -->
                <?php streamit_get_template('video/single/video_single_actions.php', ['st_data' => $content_data]); ?>
                
            </div>

        </div>

        <!-- After Detail Templates -->
        <?php streamit_get_template('video/single/video_single_after_details.php', ['st_data' => $content_data]); ?>

    </div>

    <!-- Share Model -->
    <?php streamit_get_template('video/content/video_single_share_model.php', ['st_data' => $content_data]); ?>

    <!-- Playlist Model -->
    <?php streamit_get_template('video/content/video_single_playlist_model.php', ['st_data' => $content_data]); ?>

    <!-- Description Model -->
    <?php streamit_get_template('video/content/video_single_discription_model.php', ['st_data' => $content_data]); ?>

    <!-- PPV-Subscription Model -->
    <?php streamit_get_template('common/html-ppv-subscription-details-model.php', ['st_data' => $content_data]); ?>

<?php
else : ?>
    <div class="container no-data-here">
        <p class="no_data_found"> <?php echo esc_html__('No video found.', 'streamit'); ?> </p>
   </div>
<?php endif; ?>

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

// Check if movie data is available
if (!empty($content_data)) : ?>

    <div class="detail-page">
        <div class="video-section">
            <!-- Include movie trailer template -->
            <?php 
            streamit_get_template('movie/content/movie_single_trailer.php', [
                'st_data' => $content_data
            ]); 
            ?>

            <div class="detail-part mt-md-0 mt-5">
                <!-- Include movie genre -->
                <?php 
                streamit_get_template('movie/content/movie_single_genre.php', [
                    'st_data' => $content_data
                ]); 
                ?>

                <!-- Include movie title -->
                <?php 
                streamit_get_template('movie/content/movie_single_title.php', [
                    'st_data' => $content_data
                ]); 
                ?>

                <!-- Include movie description -->
                <?php 
                streamit_get_template('movie/content/movie_single_description.php', [
                    'st_data' => $content_data
                ]); 
                ?>

                <!-- Include movie metadata list -->
                <?php 
                streamit_get_template('movie/single/movie_single_metalist.php', [
                    'st_data' => $content_data
                ]); 
                ?>

                <!-- Include movie languages -->
                <?php 
                streamit_get_template('movie/content/movie_language.php', [
                    'st_data' => $content_data, 
                    'is_limit' => false
                ]); 
                ?>

                <!-- Include movie actions -->
                <?php 
                streamit_get_template('movie/single/movie_single_actions.php', [
                    'st_data' => $content_data
                ]); 
                ?>

            </div>
        </div>

        <!-- Include movie after details section -->
        <?php 
        streamit_get_template('movie/single/movie_single_after_details.php', [
            'st_data'   => $content_data,
            'view_type' => $view_type
        ]); 
        ?>
    </div>

    <!-- Include share, playlist, description, and download modals -->
    <?php
    streamit_get_template('movie/content/movie_single_share_model.php', [
        'st_data' => $content_data
    ]);
    streamit_get_template('movie/content/movie_single_playlist_model.php', [
        'st_data' => $content_data
    ]);
    streamit_get_template('movie/content/movie_single_discription_model.php', [
        'st_data' => $content_data
    ]);
    streamit_get_template('movie/content/movie_single_download_model.php', [
        'st_data' => $content_data
    ]);
    streamit_get_template('common/html-ppv-subscription-details-model.php', [
        'st_data' => $content_data
    ]);
    ?>

<?php else : ?>
    <div class="container no-data-here">
        <p class="no_data_found"><?php echo esc_html__('No movie found.', 'streamit'); ?></p>
    </div>
<?php endif; ?>

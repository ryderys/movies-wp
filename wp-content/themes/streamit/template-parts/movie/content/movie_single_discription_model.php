<?php

/**
 * The template for displaying the description modal.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
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
                <!-- Include movie title -->
                <?php
                streamit_get_template('movie/content/movie_single_title.php', [
                    'st_data' => $st_data,
                ]);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-1">
                <!-- Include movie metadata list -->
                <?php
                streamit_get_template('movie/single/movie_single_metalist.php', [
                    'st_data' => $st_data,
                ]);
                ?>

                <?php
                $genres_ids = streamit_get_term_relationships($st_data->get_id(), 'movie_genre');
                if (!empty($genres_ids)) : ?>
                    <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-3">
                        <div class="viewmore-data-title">
                            <h6 class="m-0"><?php echo esc_html__('Genres:', 'streamit'); ?></h6>
                        </div>
                        <!-- Include movie genres -->
                        <?php
                        streamit_get_template('movie/content/movie_single_genre.php', [
                            'st_data' => $st_data,
                        ]);
                        ?>
                    </div>
                <?php endif; ?>

                <?php
                $tag_ids = streamit_get_term_relationships($st_data->get_id(), 'movie_tag');
                if (!empty($tag_ids)) : ?>
                    <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-md-1 mt-2">
                        <div class="viewmore-data-title">
                            <h6 class="m-0"><?php echo esc_html__('Tags:', 'streamit'); ?></h6>
                        </div>
                        <!-- Include movie tags -->
                        <?php
                        streamit_get_template('movie/content/movie_tag.php', [
                            'st_data' => $st_data,
                        ]);
                        ?>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <!-- Include movie language -->
                    <?php
                    streamit_get_template('movie/content/movie_language.php', [
                        'st_data' => $st_data,
                    ]);
                    ?>
                </div>

                <p class="mt-4 mb-0">
                    <?php echo method_exists($st_data, 'get_post_content') ? wp_kses_post($st_data->get_post_content()) : ''; ?>

                </p>

                <?php
                $cast_meta = $st_data->get_meta('_cast');

                if (!empty($cast_meta) && is_array($cast_meta)) :
                    $cast_ids = array_column($cast_meta, 'id');

                    // Get all person objects in a single query
                    $cast_people = streamit_get_persons([
                        'include'   => array_map('intval', $cast_ids),
                        'per_page'  => -1,
                    ]);
                    if (!empty($cast_people->results)) : ?>
                        <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-4">
                            <div class="viewmore-data-title">
                                <h6 class="m-0"><?php echo esc_html__('Cast:', 'streamit'); ?></h6>
                            </div>
                            <ul class="list-inline m-0 p-0 d-flex align-items-center flex-wrap row-gap-1 column-gap-2 cast-crew-list">
                                <?php foreach ($cast_people->results as $person) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(streamit_get_permalink($person->get_post_type(), $person->get_post_name())); ?>" tabindex="0" class="color-inherit">
                                            <?php echo esc_html($person->get_post_title()); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                <?php endif;
                endif;
                ?>

                <?php
                $crew_meta = $st_data->get_meta('_crew');

                if (!empty($crew_meta) && is_array($crew_meta)) {
                    $crew_ids = array_column($crew_meta, 'id');

                    // Fetch all crew members in one query
                    $crew_people = streamit_get_persons([
                        'include'  => array_map('intval', $crew_ids),
                        'per_page' => -1,
                    ]);

                    if (!empty($crew_people->results)) : ?>
                        <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-4">
                            <div class="viewmore-data-title">
                                <h6 class="m-0"><?php echo esc_html__('Crew:', 'streamit'); ?></h6>
                            </div>
                            <ul class="list-inline m-0 p-0 d-flex align-items-center flex-wrap row-gap-1 column-gap-2 cast-crew-list">
                                <?php foreach ($crew_people->results as $person) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(streamit_get_permalink($person->get_post_type(), $person->get_post_name())); ?>" tabindex="0" class="color-inherit">
                                            <?php echo esc_html($person->get_post_title()); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                <?php endif;
                }
                ?>

            </div>
        </div>
    </div>
</div>
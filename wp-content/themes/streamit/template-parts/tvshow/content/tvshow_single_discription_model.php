<?php

/**
 * The template for displaying description modal
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
                <?php streamit_get_template('tvshow/content/tvshow_single_title.php', ['st_data' => $st_data]); ?>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-1">
                <?php streamit_get_template('tvshow/single/tvshow_single_metalist.php', ['st_data' => $st_data]); ?>

                <?php
                // Genres Section
                $genres_ids = streamit_get_term_relationships($st_data->get_id(), 'tvshow_genre');
                if (!empty($genres_ids)) : ?>
                    <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-3">
                        <div class="viewmore-data-title">
                            <h6 class="m-0"><?php esc_html_e('Genres:', 'streamit'); ?></h6>
                        </div>
                        <?php streamit_get_template('tvshow/content/tvshow_single_genre.php', ['st_data' => $st_data]); ?>
                    </div>
                <?php endif; ?>

                <?php
                // Tags Section
                $tag_ids = streamit_get_term_relationships($st_data->get_id(), 'tvshow_tag');
                if (!empty($tag_ids)) : ?>
                    <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-md-1 mt-2">
                        <div class="viewmore-data-title">
                            <h6 class="m-0"><?php esc_html_e('Tags:', 'streamit'); ?></h6>
                        </div>
                        <?php streamit_get_template('tvshow/content/tvshow_tag.php', ['st_data' => $st_data]); ?>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <?php streamit_get_template('tvshow/content/tvshow_language.php', ['st_data' => $st_data, 'is_limit' => false]); ?>
                </div>

                <p class="mt-4 mb-0">
                    <?php echo method_exists($st_data, 'get_post_content') ? nl2br(wp_kses_post($st_data->get_post_content())) : ''; ?>
                </p>

                <?php
                // Cast Section
                $cast_meta = $st_data->get_meta('_cast');
                if (!empty($cast_meta) && is_array($cast_meta)) : ?>
                    <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-4">
                        <div class="viewmore-data-title">
                            <h6 class="m-0"><?php esc_html_e('Cast:', 'streamit'); ?></h6>
                        </div>
                        <ul class="list-inline m-0 p-0 d-flex align-items-center flex-wrap row-gap-1 column-gap-2 cast-crew-list">
                            <?php foreach ($cast_meta as $person) :
                                $person_data = streamit_get_person((int) $person['id']); ?>
                                <li>
                                    <a href="<?php echo esc_url(streamit_get_permalink($person_data->get_post_type(), $person_data->get_post_name())); ?>" class="color-inherit">
                                        <?php echo esc_html($person_data->get_post_title()); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php
                // Crew Section
                $crew_meta = $st_data->get_meta('_crew');
                if (!empty($crew_meta) && is_array($crew_meta)) : ?>
                    <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-4">
                        <div class="viewmore-data-title">
                            <h6 class="m-0"><?php esc_html_e('Crew:', 'streamit'); ?></h6>
                        </div>
                        <ul class="list-inline m-0 p-0 d-flex align-items-center flex-wrap row-gap-1 column-gap-2 cast-crew-list">
                            <?php foreach ($crew_meta as $person) :
                                $person_data = streamit_get_person((int) $person['id']); ?>
                                <li>
                                    <a href="<?php echo esc_url(streamit_get_permalink($person_data->get_post_type(), $person_data->get_post_name())); ?>" class="color-inherit">
                                        <?php echo esc_html($person_data->get_post_title()); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

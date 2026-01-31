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

?>
<div class="col">
    <div class="css_prefix-card card-hover card-hover-flip">

        <div class="block-images position-relative w-100">

            <?php streamit_get_template('tvshow/content/tvshow_thumbnail.php', ['st_data' => $st_data]); ?>


            <div class="card-description with-transition">
                <div class="position-relative w-100">
                    <?php streamit_get_template('tvshow/content/tvshow_genre.php', ['st_data' => $st_data]); ?>

                    <?php streamit_get_template('tvshow/content/tvshow_title.php', ['st_data' => $st_data]); ?>

                    <div class="d-flex flex-wrap align-items-center gap-2">

                        <?php streamit_get_template('tvshow/content/tvshow_season.php', ['st_data' => $st_data]); ?>

                        <?php streamit_get_template('tvshow/content/tvshow_language.php', ['st_data' => $st_data, 'is_limit' => true]); ?>

                    </div>

                    <?php streamit_get_template('tvshow/content/tvshow_excerpt.php', ['st_data' => $st_data, 'is_limit' => true]); ?>

                    <div class="css-prefix-play-button d-flex flex-wrap align-items-center gap-2 mt-3">

                        <?php streamit_get_template('tvshow/content/tvshow_watch_list.php', ['st_data' => $st_data]); ?>

                        <?php streamit_get_template('tvshow/content/tvshow_watch_now.php', ['st_data' => $st_data]); ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
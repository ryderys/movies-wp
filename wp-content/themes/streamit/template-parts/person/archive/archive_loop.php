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
    <div class="person-card position-relative">
        <div class="block-images position-relative w-100">
            <?php streamit_get_template('person/content/person_thumbnail.php', ['st_data' => $st_data]); ?>

            <div class="person-detail">
                <div class="position-relative w-100">                    
                    <?php streamit_get_template('person/content/person_title.php', ['st_data' => $st_data]); ?>
                    <?php streamit_get_template('person/content/person_genre.php', ['st_data' => $st_data]); ?>
                </div>
            </div>
        </div>
    </div>
</div>
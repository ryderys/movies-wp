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
?>

<div class="person-detail">
    <div class="container-fluid">
        <?php if (!empty($content_data)) : // Check if content_data is not empty ?>

            <div class="row">
                <div class="col-md-3">
                    <div class="person-info">
                        <!-- Pass content_data to the templates -->
                        <?php streamit_get_template('person/content/person_single_thumbnail.php', ['st_data' => $content_data]); ?>
                        <?php streamit_get_template('person/content/person_single_information.php', ['st_data' => $content_data]); ?>
                    </div>
                </div>
                <div class="col-md-9 mt-md-0 mt-5">
                    <div class="person-info-details">
                        <!-- Pass content_data or st_data to the details template -->
                        <?php streamit_get_template('person/content/person_single_details.php', ['st_data' => $content_data]); ?>
                    </div>
                </div>
            </div>

        <?php else : // Display message if content_data is empty ?>
            <p class="no_data_found"><?php echo esc_html__('No person found.', 'streamit'); ?></p>
        <?php endif; ?>

    </div>
</div>

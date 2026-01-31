<?php

/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package streamit
 */

get_header();
/**
 * st_before_primary_content_area hook.
 *
 * @since 2.0
 */
do_action('st_before_primary_content_area', '404'); ?>

<div class="site-content-contain">
    <div id="content" class="site-content">
        <div id="primary" class="content-area">
            <main id="main" class="site-main">
                <?php 
                // Load the 404 template
                streamit_get_template('no-result/404.php');
                ?>
            </main>
        </div>
    </div>
</div>

<?php
/**
 * st_after_primary_content_area hook.
 *
 * @since 2.0
 */
do_action('st_after_primary_content_area', '404');

get_footer();

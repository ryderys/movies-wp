<?php

/**
 * The template for displaying offline pages
 *
 * @link https://github.com/xwp/pwa-wp#offline--500-error-handling
 *
 * @package streamit
 */

get_header(); ?>

<div class="page-content">
    <div id="content" class="site-content">
        <div id="primary" class="content-area">
            <main id="main" class="site-main">

                <?php
                /**
                 * st_before_primary_content_area hook.
                 *
                 * @since 2.0
                 */
                do_action('st_before_primary_content_area', 'offline');
                ?>

                <!-- Offline Page Content -->
                <section class="offline-page">
                    <?php
                    // Include the offline page template 
                    streamit_get_template('content/offline.php');
                    ?>
                </section>

                <?php
                /**
                 * st_after_primary_content_area hook.
                 *
                 * @since 2.0
                 */
                do_action('st_after_primary_content_area', 'offline');
                ?>

            </main>
        </div>
    </div>
</div>

<?php
get_footer();

<?php

/**
 * The main template file.
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

get_header();

?>
<div class="page-content">
    <?php
    /**
     * Hook: st_before_primary_content_area
     *
     * Fires before the primary content area.
     *
     * @param string $post_type The current post type.
     */
    do_action('st_before_primary_content_area', get_post_type());
    ?>

    <div class="site-content-contain">
        <div id="content" class="site-content">
            <?php streamit_get_template("breadcrumb/breadcrumb.php"); ?>
            <div id="primary" class="content-area">
                <main id="main" class="site-main">
                    <div class="<?php echo esc_attr(apply_filters('content_container_class', 'container')); ?>">
                        <?php $post_type = get_post_type() ?? ''; ?>
                        <div class="row css_prefix-card-wrapper <?php echo esc_attr(apply_filters('st_sidebar_direction', $post_type)); ?>">
                            <?php
                            /**
                             * Hook: st_before_main_sidebar_class
                             *
                             * Fires before the main content loop starts.
                             */
                            do_action('st_before_main_sidebar_class', $post_type);

                            if (have_posts()) :
                                /**
                                 * Hook: st_before_loop
                                 *
                                 * Fires before the main posts loop.
                                 *
                                 * @param string $context Context identifier for the loop (e.g., 'index').
                                 */
                                do_action('st_before_loop', 'index');

                                while (have_posts()) :
                                    the_post();

                                    // Load the post entry template part.
                                    streamit_get_template('content/entry.php');

                                endwhile;

                                /**
                                 * Hook: st_after_loop
                                 *
                                 * Fires after the main posts loop.
                                 *
                                 * @param string $context Context identifier for the loop (e.g., 'index').
                                 */
                                do_action('st_after_loop', 'index');



                                wp_reset_postdata();

                            else :

                                // Load no-results template part.
                                streamit_get_template('no-result/error.php');

                            endif;

                            /**
                             * Hook: st_after_main_sidebar_class
                             *
                             * Fires after the main content loop ends.
                             */
                            do_action('st_after_main_sidebar_class', $post_type);

                            // Load the sidebar.
                            get_sidebar('', array('title' => $post_type));

                            // Load pagination template part.
                            streamit_get_template('pagination/post_pagination.php');
                            ?>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <?php
    /**
     * Hook: st_after_primary_content_area
     *
     * Fires after the primary content area.
     *
     * @param string $post_type The current post type.
     */
    do_action('st_after_primary_content_area', $post_type);
    ?>
</div>
<?php

get_footer();

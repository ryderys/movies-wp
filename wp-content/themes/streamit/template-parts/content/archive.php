<?php

/**
 * The template for displaying archive pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

?>
<?php streamit_get_template("breadcrumb/breadcrumb.php"); ?>
<main id="main" class="site-main">
    <div class="<?php echo apply_filters('content_container_class', 'container'); ?>">
        <?php
        // Get the post type and display the archive title
        $post_type = get_post_type() ?? '';

        ?>

        <div class="row">
            <?php
            /**
             * st_before_main_content hook.
             * Runs before the main content.
             *
             * @since 0.1
             */
            do_action('st_before_main_content');

            /**
             * st_before_main_sidebar hook.
             * Runs before the sidebar.
             *
             * @since 0.1
             */
            do_action('st_before_main_sidebar_class', $post_type);

            if (have_posts()) :
                /**
                 * st_before_loop hook.
                 * Runs before starting the loop.
                 *
                 * @since 3.1.0
                 */
                do_action('st_before_loop', 'index');
                
                while (have_posts()) :
                    the_post();
                    streamit_get_template('content/entry.php'); // Custom entry template
                endwhile;

                /**
                 * st_after_loop hook.
                 * Runs after the loop has finished.
                 *
                 * @since 2.3
                 */
                do_action('st_after_loop', 'index');
                
                streamit_get_template('pagination/post_pagination.php'); // Custom pagination
                wp_reset_postdata();

            else :
                streamit_get_template('no-result/error.php'); // Template for no results
            endif;

            /**
             * st_after_main_sidebar hook.
             * Runs after the sidebar.
             *
             * @since 0.1
             */
            do_action('st_after_main_sidebar_class', $post_type);

            // Sidebar, if needed
            get_sidebar('', array('title' => $post_type));

            /**
             * st_after_main_content hook.
             * Runs after the main content.
             *
             * @since 0.1
             */
            do_action('st_after_main_content');
            ?>
        </div>
    </div>
</main>
<?php

<?php

/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

get_header();
?>

<div class="page-content">
    <?php

    global $streamit_options;
    $is_ajax_search = isset($_GET['ajax_search']) && 'true' === $_GET['ajax_search'];
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    ?>
    <div id="content" class="site-content">
        <div id="primary" class="content-area">
            <main id="main" class="site-main">
                <div class="<?php echo esc_attr(apply_filters('content_container_class', 'container')); ?>">
                    <?php if ($is_ajax_search) : ?>
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <form id="st_search_page_form" action="<?php echo esc_url(home_url('/')); ?>" method="get">
                                    <div class="form-group input-group search-not-found">
                                        <button type="submit" class="input-group-text btn btn-link p-0" id="movie-search">
                                            <?php echo st_get_icon('search_normal'); ?>
                                        </button>
                                        
                                        <input type="text" name="s" class="form-control" id="search-input" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_html_e('Search....', 'streamit'); ?>">
                                        <input type="hidden" name="ajax_search" value="true">

                                        <button type="button" class="btn btn-link remove-search" id="remove_search_text" style="<?php echo empty($search) ? 'display: none;' : 'display: block;'; ?>">
                                            <?php echo st_get_icon('cross'); ?>
                                        </button>
                                    </div>
                                </form>

                            </div>

                            <?php
                            /**
                             * st_before_ajax_search_results hook.
                             *
                             * @since 0.1
                             */
                            do_action('st_before_ajax_search_results');

                            streamit_get_template("search/ajax_search.php", ['s' => $search]);
                            /**
                             * st_after_ajax_search_results hook.
                             *
                             * @since 0.1
                             */
                            do_action('st_after_ajax_search_results'); ?>
                        </div>
                    <?php else : ?>
                        <div class="row <?php echo apply_filters('st_sidebar_direction', 'search'); ?>">
                            <?php
                            /**
                             * st_before_main_sidebar_class hook.
                             *
                             * @since 0.1
                             */
                            do_action('st_before_main_sidebar_class', 'search');

                            if (strlen($search) > 3 && isset($_GET['s'])) :

                                if (have_posts()) :

                                    /**
                                     * st_before_loop hook.
                                     *
                                     * @since 3.1.0
                                     */
                                    do_action('st_before_loop', 'index');

                                    while (have_posts()) :
                                        the_post();
                                        streamit_get_template("content/entry.php");

                                    endwhile;

                                    /**
                                     * st_after_loop hook.
                                     *
                                     * @since 2.3
                                     */
                                    do_action('st_after_loop', 'index');



                                else :

                                    streamit_get_template("no-result/error.php");


                                endif;

                                // Reset the main query modifications after use
                                remove_action('pre_get_posts', 'custom_search_filter');

                            else :
                                streamit_get_template("no-result/error.php");

                            endif;

                            /**
                             * st_after_main_sidebar hook.
                             *
                             * @since 0.1
                             */
                            do_action('st_after_main_sidebar_class', 'search');

                            get_sidebar('', ['title' => 'search']);

                            streamit_get_template("pagination/post_pagination.php");
                            ?>

                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</div>

<?php get_footer(); ?>
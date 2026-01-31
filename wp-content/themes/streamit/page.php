<?php

/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage streamit
 * @since 1.0
 * @version 1.0
 */

get_header();
$class = apply_filters('st_content_container_class', '');
?>
<div class="page-content">
    <?php streamit_get_template("breadcrumb/breadcrumb.php"); ?>
    <div id="content" class="site-content">
        <div id="primary" class="content-area">
            <main id="main" class="site-main">
                <?php echo (!empty($class)) ? '<div class="' . esc_attr($class) . '">' : ''; ?>

                <?php streamit_get_template('content/single.php'); ?>

                <?php echo (!empty($class)) ? '</div>' : ''; ?>
            </main>
        </div>
    </div>
</div>
<?php get_footer();

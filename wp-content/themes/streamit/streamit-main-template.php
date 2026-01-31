<?php

/**
 * The main template file.
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

// Prevent direct access to the file for security reasons.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include the header template part (this will load the header from the theme).
get_header();

?>
<div class="page-content">
    <!-- Main content area of the page -->
    <div id="content" class="site-content">
        <!-- Primary content area -->
        <div id="primary" class="content-area">
            <!-- Main site content -->
            <?php if ($sub_template !== 'single') echo streamit_get_template("breadcrumb/breadcrumb.php", ["view_type" => $view_type, "content_type" => $content_type]); ?>

            <main id="main" class="site-main">
                <?php

                /**
                 * 'st_before_primary_content_area' hook.
                 *
                 * This hook allows custom code to run before the primary content area.
                 * It could be used to add additional HTML or perform actions before
                 * rendering the main content. The current content type and view type
                 * are passed as arguments.
                 *
                 * @since 2.02
                 */
                do_action('st_before_primary_content_area', $content_type . '_' . $view_type);

                // Construct the path for the template based on the content type and view type.
                $template_path = "{$content_type}/{$sub_template}/{$view_type}.php";
                // Include the appropriate template file based on the content type and view type.
                // The content data is passed as a variable to be used within the included template.
                streamit_get_template($template_path, ["content_data" => $content_data,  "view_type" => $view_type]);

                /**
                 * 'st_after_primary_content_area' hook.
                 *
                 * This hook allows custom code to run after the primary content area.
                 * It could be used for actions like adding extra content after the main 
                 * content section or performing clean-up tasks.
                 *
                 * @since 2.0
                 */
                do_action('st_after_primary_content_area', $content_type . '_' . $view_type);

                ?>
            </main> <!-- End of the main content area -->
        </div> <!-- End of the primary content area -->
    </div> <!-- End of site content -->
</div> <!-- End of page content -->

<?php

// Include the footer template part (this will load the footer from the theme).
get_footer();

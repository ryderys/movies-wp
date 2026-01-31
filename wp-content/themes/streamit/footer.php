<?php
/**
 * The template for displaying the footer.
 *
 * @package Streamit
 */

global $streamit_options;
?>

<?php
/**
 * st_before_footer hook.
 *
 * @since 0.1
 */
do_action('st_before_footer');
?>

<?php
/**
 * st_before_footer_content hook.
 *
 * @since 0.1
 */
do_action('st_before_footer_content');

// Load the footer template
streamit_get_template('footer/footer.php');

/**
 * st_after_footer_content hook.
 *
 * @since 0.1
 */
do_action('st_after_footer_content');
?>

<!-- Back To Top Button -->
<?php 
// Check if the back-to-top button option is set to 'yes', or not set at all (defaults to 'yes')
$back_to_top_enabled = isset($streamit_options['back_to_top_btn']) && $streamit_options['back_to_top_btn'] == 'yes';

if ($back_to_top_enabled) : ?>
    <div id="back-to-top" class="css-prefix-top">
        <a class="top" id="top" href="#top">
            <span class="icon-arrow-up"></span>
        </a>
    </div>
<?php endif; ?>

<?php
/**
 * st_after_footer hook.
 *
 * @since 0.1
 */
do_action('st_after_footer');
?>

<?php wp_footer(); ?>
</body>
</html>

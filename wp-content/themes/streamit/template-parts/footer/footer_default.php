<?php
/**
 * Displays the footer default area
 *
 * @package WordPress
 * @subpackage streamit
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;
?>

<footer class="footer css_prefix-footer">
    <?php
    streamit_get_template('footer/widget.php');

    ?>
    <?php
    if (!isset($streamit_options['display_copyright']) || ($streamit_options['display_copyright'] == 'yes'))
    streamit_get_template('footer/copyright.php');

    ?>
</footer>

<?php
// Footer Menu For Responsive Device
streamit_get_template('footer/footer-mobile-menu.php');


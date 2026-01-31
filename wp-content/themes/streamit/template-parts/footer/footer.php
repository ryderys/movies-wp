<?php

/**
 * Displays the post footer
 *
 * @package    WordPress
 * @subpackage streamit
 * @since      1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

?>

<!-- diffrent footer or default footer -->

<?php
if (!streamit_display_footer()) {
    return;
}

streamit_get_template('footer/footer_default.php');

?>

<!-- do not remove this close div tag -->
</div>
<?php

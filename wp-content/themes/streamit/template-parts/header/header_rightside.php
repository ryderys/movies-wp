<?php

/**
 * Displays the post header
 *
 * @package WordPress
 * @subpackage streamit
 * @since 1.0.0
 */
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<div class="css_prefix-header-right d-flex align-items-center gap-2">
    <ul class="list-inline d-flex align-items-center gap-3 gap-md-4 mb-0 ps-0 justify-content-md-end justify-content-between">

        <!-- Search Panel: Only display if not on the search results page -->
        <?php if (! is_search()) : ?>
            <?php streamit_get_template('header/search_icone.php'); ?>
        <?php endif; ?>

        <!-- User Mini Cart -->
        <?php streamit_get_template('header/cart.php'); ?>

        <!-- User Account -->
        <?php streamit_get_template('header/account.php'); ?>

    </ul>
    <!-- Mobile toggle button for the offcanvas menu -->
    <?php if (st_check_primary_nav_menu()) : ?>
        <button class="menu-toggle-button btn btn-link d-block d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#staticBackdrop" aria-controls="staticBackdrop">
            <?php echo st_get_icon('bar-line'); ?>

        </button>
    <?php endif; ?>
</div>
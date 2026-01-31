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

global $streamit_options;
$current_user_id = get_current_user_id();
?>
<header class="header-default" id="default-header">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2 gap-md-3">

                <!-- Branding section -->
                <?php streamit_get_template('header/header_branding.php'); ?>

                <?php
                $user_levels = function_exists('streamit_user_current_pmp_level') ? streamit_user_current_pmp_level($current_user_id) : [];
                $subscribe_button = isset($streamit_options['streamit_subscribe_button']) && $streamit_options['streamit_subscribe_button'] === 'yes';
                $subscribe_button_title = !empty($streamit_options['streamit_subscribe_text']) ? $streamit_options['streamit_subscribe_text'] : esc_html__('Subscribe', 'streamit');
                $show_icon = isset($streamit_options['streamit_icon']) && $streamit_options['streamit_icon'] === 'yes';
                if($subscribe_button) : 
                    if(empty($user_levels)) : ?>
                        <!-- Subscribe button -->
                            <a href="<?php echo esc_url(streamit_subscribe_page_url()); ?>" class="subscribe-btn btn btn-warning-subtle py-1 py-md-2 px-2 px-ms-3">
                                <span class="d-flex align-items-center gap-2">
                                    <?php if ($show_icon) : ?>
                                        <?php echo st_get_icon('premium'); ?>

                                    <?php endif; ?>
                                    <span><?php echo esc_html($subscribe_button_title); ?></span>
                                </span>
                            </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url(streamit_subscribe_page_url()); ?>" class="subscribe-btn btn btn-warning-subtle py-1 py-md-2 px-2 px-ms-3">
                            <span class="d-flex align-items-center gap-2">
                                    <?php if ($show_icon) : ?>
                                        <?php echo st_get_icon('premium'); ?>
                                    <?php endif; ?>
                                    <?php esc_html_e('Upgrade', 'streamit'); ?>
                            </span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Site navigation -->
            <nav id="site-navigation" class="navbar deafult-header navbar-expand-xl navbar-light p-0" aria-label="<?php esc_attr_e('Main menu', 'streamit'); ?>">
                <div class="offcanvas offcanvas-start css_prefix-offcanvas-menu" data-bs-backdrop="static" tabindex="-1" id="staticBackdrop">
                    <div class="offcanvas-header">
                        <?php streamit_get_template('header/header_branding.php'); ?>

                        <!-- Close button for the offcanvas menu -->
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    </div>

                    <div class="offcanvas-body">
                        <div id="navbarSupportedContent" class="navbar-collapse justify-content-center">
                            <?php if (st_check_primary_nav_menu()) : ?>
                                <div id="css_prefix-menu-container" class="menu-all-pages-container">
                                    <?php
                                    // Display primary navigation menu
                                    display_primary_nav_menu(
                                        array(
                                            'menu_class'   => 'sf-menu navbar-nav top-menu navbar-nav-toggle ms-auto',
                                            'menu_id'      => 'menu-main-menu',
                                            'item_spacing' => 'discard',
                                            'depth'        => 10, // Allow multi-level depth
                                            'link_before'  => '<span class="menu-title">',
                                            'link_after'   => '</span>',
                                            'fallback_cb'  => 'wp_page_menu', // Fallback menu if no primary menu is set
                                        )
                                    );
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Right-side content (header-right) -->
            <?php streamit_get_template('header/header_rightside.php'); ?>
        </div>
    </div>
</header>

<!-- Layout component -->
<?php streamit_get_template('component/layout.php'); ?>
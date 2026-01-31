<?php
/*
 * streamit functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

add_action('admin_enqueue_scripts', function ($hook) {

    // Enqueue JavaScript file
    Kucrut\Vite\enqueue_asset(
        get_template_directory() . '/static/dist',
        'admin/assets/utilities/main.js',
        array(
            'handle'             => 'streamit-adminmain',
            'css-dependencies'   => array(),
            'css-media'          => 'all',
            'css-only'           => false,
            'in-footer'          => false,
            'dependencies'       => array('jquery'),
        )
    );

    wp_localize_script(
        'streamit-adminmain',
        'stAdminAjax',
        array(
            'ajaxurl'           => esc_url(admin_url('admin-ajax.php')),
            '_ajax_nonce'       => wp_create_nonce('st_ajax_nonce'),
            'st_migrationUrl'   => admin_url('admin.php?page=streamit-migration')
        )
    );

    if ($hook === 'nav-menus.php') {
        wp_enqueue_media();
    }
});


//TGM plugin activation 
require get_template_directory() . '/admin/TGM/class-streamit-tgm-plugin-register.php';

require get_template_directory() . '/admin/class-tgm-plugin-activation.php';
require_once get_template_directory() . '/admin/class-st-admin-routes.php';
require_once get_template_directory() . '/admin/class-st-admin-routes-handler.php';
//Merlin Theme Setup
require_once get_template_directory() . '/admin/Merlin/vendor/autoload.php';
require_once get_template_directory() . '/admin/Merlin/class-merlin.php';
require_once get_template_directory() . '/admin/Theme-Setup/class-streamit-theme-setup.php';
require_once get_template_directory() . '/admin/import/class-streamit-import-data.php';

//Admin Notices
require_once get_template_directory() . '/admin/class-st-admin-notice-handler.php';

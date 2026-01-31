<!DOCTYPE html>
<html data-bs-theme="dark" <?php language_attributes(); ?>>

<head>
    <?php do_action('st_before_head'); ?>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover <?php echo (wp_is_mobile()) ? ', user-scalable=no': '';?>">

    <meta name="theme-color" content="<?php echo esc_attr($streamit_options['theme_color'] ?? '#000000'); ?>">
    <link rel="profile" href="<?php echo esc_url(is_ssl() ? 'https:' : 'http:') . '//gmpg.org/xfn/11'; ?>">
    <?php wp_head(); ?>
    <?php do_action('st_after_head'); ?>
</head>

<body <?php body_class(); ?>>
    <?php
    /**
     * wp_body_open hook.
     *
     * @since 2.3
     */
    do_action('wp_body_open');

    /**
     * st_before_header hook.
     *
     *  hooks for adding navigation or other elements.
     */
    do_action('st_before_header');

    // Load the header template
    streamit_get_template('header/header.php');


    /**
     * st_after_header hook.
     */
    do_action('st_after_header');
    
    //Toggle Message Html Struture
    streamit_get_template('header/toggle.php');

<?php

defined('ABSPATH') || exit;

if (!is_admin())
    return;
/**
 * streamit\streamit\Redux_Framework\Options\User
 *
 * @package streamit
 */
Redux::set_section($this->opt_name, array(
    'title'             => esc_html__('User Account', 'streamit'),
    'id'                => 'user_account',
    'icon'              => 'el el-user',
    'icon'              => 'custom-user',
    'customizer_width'  => '500px',
));

Redux::set_section($this->opt_name, array(
    'title'       => esc_html__('User Account Settings', 'streamit'),
    'id'          => 'user-options',
    'icon'        => 'custom-accounts',
    'subsection'  => true,
    'fields'      => array(
        array(
            'id'        => 'streamit_signup_section',
            'type'      => 'section',
            'title'     =>  esc_html__('Sign Up', 'streamit'),
            'indent'    => true,
        ),
        array(
            'id'        => 'streamit_signup_title',
            'type'      => 'text',
            'title'     => esc_html__('SignUp button title', 'streamit'),
            'default'   => esc_html__('Sign Up', 'streamit'),
        ),
        array(
            'id'        => 'streamit_signup_link',
            'type'      => 'select',
            'multi'     => false,
            'data'      => 'pages',
            'title'     => esc_html__('Select Page For SignUp', 'streamit'),
            'desc'      =>  esc_html__('Use [streamit_registration_form] Shortcode on a page which you sleceted', 'streamit'),
        ),
        array(
            'id'        => 'streamit_signin_section',
            'type'      => 'section',
            'title'     => esc_html__('Sign In', 'streamit'),
            'indent'    => true,
        ),
        array(
            'id'        => 'streamit_signin_title',
            'type'      => 'text',
            'title'     => esc_html__('SignIn button title', 'streamit'),
            'default'   => esc_html__('Sign In', 'streamit'),
        ),
        array(
            'id'        => 'streamit_signin_link',
            'type'      => 'select',
            'multi'     => false,
            'data'      => 'pages',
            'title'     => esc_html__('Select Page For SignIn', 'streamit'),
            'desc'      =>  esc_html__('Use [streamit_login_form] Shortcode on a page which you selected', 'streamit'),
        ),
        array(
            'id'         => 'streamit_default_accesss',
            'type'         => 'button_set',
            'title'         => esc_html__('Set default access', 'streamit'),
            'options'     => array(
                'yes'     => 'Yes',
                'no'     => 'No'
            ),
            'default'   => 'no'
        ),
        array(
            'id'        => 'streamit_forget_password_section',
            'type'      => 'section',
            'title'     => esc_html__('Forget Password', 'streamit'),
            'indent'    => true,
        ),
        array(
            'id'        => 'streamit_forget_password_title',
            'type'      => 'text',
            'title'     => esc_html__('Forget Password button title', 'streamit'),
            'default'   => esc_html__('Forget Password', 'streamit'),
        ),
        array(
            'id'        => 'streamit_forget_password_link',
            'type'      => 'select',
            'multi'     => false,
            'data'      => 'pages',
            'title'     => esc_html__('Select Page For Forget Password', 'streamit'),
            'desc'      =>  esc_html__('Use [streamit_forgot_password_form] Shortcode on a page which you selected', 'streamit'),
        ),
        array(
            'id'        => 'streamit_logout_section',
            'type'      => 'section',
            'title'     => esc_html__('Logout', 'streamit'),
            'indent'    => true,
        ),
        array(
            'id'        => 'streamit_logout_title',
            'type'      => 'text',
            'title'     => esc_html__('Logout Button title', 'streamit'),
            'default'   =>  esc_html__('Logout', 'streamit')
        ),
        array(
            'id'        => 'streamit_term_section',
            'type'      => 'section',
            'title'     => esc_html__('Term and condition', 'streamit'),
            'indent'    => true,
        ),
        array(
            'id'        => 'streamit_term_condition',
            'type'      => 'select',
            'multi'     => false,
            'data'      => 'pages',
            'title'     => esc_html__('Select Page For Term and Condition.', 'streamit'),
        )
    )
));

Redux::set_section($this->opt_name, array(
    'title'      => esc_html__('SideIcons Settings', 'streamit'),
    'id'         => 'watch-sideicon-options',
    'icon'       => 'icon-sideicons-setting',
    'subsection' => true,
    'fields'     => array(
        array(
            'id'        => 'streamit_display_social_icons',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Social Icons', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the Social Icons For Sharing', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),
        array(
            'id'        => 'streamit_display_like',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Like Icon and List', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the Like Icon and Liked Content List', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),
        array(
            'id'        => 'streamit_liked_content_title',
            'type'      => 'text',
            'required'  => array('streamit_display_like', '=', 'yes'),
            'title'     => esc_html__('Liked Content button title', 'streamit'),
            'default'   => esc_html__('Liked Content', 'streamit'),
            'class'     => 'css_prefix-sub-fields',
        ),
        array(
            'id'        => 'streamit_display_watchlist',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Watchlist', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the Watchlist ', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),
        array(
            'id'        => 'streamit_watchlist_title',
            'type'      => 'text',
            'required'  => array('streamit_display_watchlist', '=', 'yes'),
            'title'     => esc_html__('Watchlist button title', 'streamit'),
            'default'   => esc_html__('Watch List', 'streamit'),
            'class'     => 'css_prefix-sub-fields',
        ),
        array(
            'id'        =>  'streamit_watchlist_link',
            'type'      =>  'select',
            'multi'     =>  false,
            'data'      =>  'pages',
            'required'  => array('streamit_display_watchlist', '=', 'yes'),
            'title'     =>  esc_html__('Select Page For Watchlist', 'streamit'),
            'desc'      =>  esc_html__('Use [streamit_user_watchlist] Shortcode on a page which you selected', 'streamit'),
            'class'     => 'css_prefix-sub-fields',
        ),
        array(
            'id'        => 'enable_notification_module',
            'type'      => 'button_set',
            'title'     => esc_html__('Enable Notifications', 'streamit'),
            'subtitle'  => esc_html__('Enable to recieve notification, whenever a new movie/tv show/video is published.', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('Enable', 'streamit'),
                'no'    => esc_html__('Disable', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),
        array(
            'id'        => 'notification_label',
            'type'      => 'text',
            'title'     => esc_html__('Notification Label', 'streamit'),
            'default'   => esc_html__('Notification', 'streamit'),
            'required'  => array('enable_notification_module', '=', 'yes'),
            'class'     => 'css_prefix-sub-fields',
        ),
    )
));

Redux::set_section($this->opt_name, array(
    'title'      => esc_html__('User Access Control', 'streamit'),
    'id'         => 'user-login-redirection',
    'icon'       => 'icon-sideicons-setting',
    'subsection' => true,
    'fields'     => array(
        array(
            'id'          => 'streamit_after_login_page',
            'type'        => 'select',
            'multi'       => false,
            'title'       => esc_html__('Redirect After Login', 'streamit'),
            'desc'        => esc_html__('Type to search for content (movies, videos, TV shows, etc.) to redirect to after login.', 'streamit'),
            'placeholder' => esc_html__('Search and select content', 'streamit'),
            'ajax'        => true,
            'data'        => 'pages',
            'class'       => 'redux-ajax-select',
        ),
        array(
            'id'          => 'streamit_enable_page_restriction',
            'type'        => 'button_set',
            'title'       => esc_html__('Enable Page Restriction', 'streamit'),
            'desc'        => esc_html__('Enable page restriction if you want to restrict your visitors to access specific pages.', 'streamit'),
            'options'     => array(
                'yes'     => esc_html__('Enable', 'streamit'),
                'no'      => esc_html__('Disable', 'streamit'),
            ),
            'default'     => 'no',
        ),
        array(
            'id'          => 'streamit_redirect_page_for_non_logged_in',
            'type'        => 'select',
            'multi'       => false,
            'title'       => esc_html__('Select Page For Non Login Users', 'streamit'),
            'desc'        => esc_html__('Select pages on which you want to redirect non login user.', 'streamit'),
            'ajax'        => false,
            'data'        => 'pages',
            'required'    => array('streamit_enable_page_restriction', '=', 'yes'),
        ),
        array(
            'id'          => 'streamit_excluded_pages',
            'type'        => 'select',
            'multi'       => true,
            'title'       => esc_html__('Select Excludes Pages', 'streamit'),
            'desc'        => esc_html__('Select pages which you want to exlude form restriction.', 'streamit'),
            'ajax'        => true,
            'data'        => 'pages',
            'required'    => array('streamit_enable_page_restriction', '=', 'yes'),
        ),
    ),
));

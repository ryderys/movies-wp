<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * streamit\streamit\Redux_Framework\Options\Dashbord class
 *
 * @package streamit
 */
Redux::set_section(
    $this->opt_name,
    array(
        'title' => esc_html__('Dashboard', 'streamit'),
        'id' => 'redux-dashboard',
        'has_group_title' => __('Get Started', 'streamit'),
        'icon' => 'custom-Dashboard',
        'fields' => array(

            array(
                'id' => 'dashboard-raw',
                'type' => 'raw',
                'full_width' => true,
                'content_path' => dirname(__FILE__) . '/raw_html.php'
            )
        ),
    )
);

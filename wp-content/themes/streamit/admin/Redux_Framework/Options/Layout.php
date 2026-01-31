<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


/**
 * streamit\streamit\Redux_Framework\Options\Layout
 *
 * @package streamit
 */

Redux::set_section($this->opt_name, array(
    'title' => esc_html__('Layout', 'streamit'),
    'id' => 'editer-page',
    'icon'      => 'icon-layout',
    'fields' => array(

        array(
            'id'        => 'streamit_enable_switcher',
            'type'      => 'switch',
            'title'     => __('Show Style Switcher', 'streamit'),
            'subtitle'     => __('The style switcher is only for preview on front-end', 'streamit'),
            'default'   => true,
        ),

        array(
            'id'        => 'rtl_switcher',
            'type'      => 'select',
            'title'     => __('Select Layout Mode', 'streamit'),
            'subtitle'      => __('Select a Mode to quickly apply pre-defined', 'streamit'),
            'customizer_only'   => false,
            'options'     => array(
                'ltr'     => esc_html__('LTR', 'streamit'),
                'rtl'     => esc_html__('RTL', 'streamit')
            ),
            'default'     => 'ltr',
        ),
    )
));

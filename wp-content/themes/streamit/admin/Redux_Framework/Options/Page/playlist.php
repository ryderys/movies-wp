<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


Redux::set_section($this->opt_name, array(
    'title'         => esc_html__('Playlist', 'streamit'),
    'icon'          => 'custom-playlist',
    'id'            => 'playlist',
    'subsection'    => true,
    'fields'        => array(
        array(
            'id'        => 'streamit_display_playlist',
            'type'      => 'button_set',
            'title'     => esc_html__('Show Playlist', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the playlist ', 'streamit'),
            'options'   => array(
                'yes'   => esc_html__('On', 'streamit'),
                'no'    => esc_html__('Off', 'streamit')
            ),
            'default'   => esc_html__('yes', 'streamit')
        ),
    )
));

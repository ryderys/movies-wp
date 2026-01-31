<?php

/**
 * Manages custom tabs for the user profile page.
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Streamit_Profile_Tabs_Manager
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_filter(
            'streamit_user_tabs',
            [$this, 'add_custom_user_tabs'],
            10,
            1
        );
    }

    /**
     * Customize Streamit user tabs using theme options.
     *
     * @param array $tabs         Default tabs from plugin.
     * @return array Modified tabs.
     */
    public function add_custom_user_tabs($tabs)
    {
        global $streamit_options, $streamit_core_options;
    
        $is_admin = is_admin();
    
        // Helper: returns label with or without icon
        $wrap = function($icon, $label) use ($is_admin) {
            return $is_admin ? $label : (st_get_icon($icon) . ' ' . $label);
        };
    
        // === Playlist Tab ===
        if (isset($streamit_core_options['streamit_display_playlist']) && 'yes' === $streamit_core_options['streamit_display_playlist']) {
            $playlist_label = ! empty($streamit_core_options['streamit_playlist_title'])
                ? esc_html($streamit_core_options['streamit_playlist_title'])
                : esc_html__('Playlist', 'streamit');
    
            $tabs['playlist'] = $wrap('playlist', $playlist_label);
    
        } else {
            unset($tabs['playlist']);
        }
    
        // === Watchlist Tab ===
        if (isset($streamit_options['streamit_display_watchlist']) && 'yes' === $streamit_options['streamit_display_watchlist']) {
            $watchlist_label = ! empty($streamit_options['streamit_watchlist_title'])
                ? esc_html($streamit_options['streamit_watchlist_title'])
                : esc_html__('Watchlist', 'streamit');
    
            $tabs['watchlist'] = $wrap('movie', $watchlist_label);
    
        } else {
            unset($tabs['watchlist']);
        }

        // === Liked Content Tab ===
        if (isset($streamit_options['streamit_display_like']) && 'yes' === $streamit_options['streamit_display_like']) {
            $liked_content_label = ! empty($streamit_options['streamit_liked_content_title'])
                ? esc_html($streamit_options['streamit_liked_content_title'])
                : esc_html__('Liked Content', 'streamit');
    
            $tabs['liked_content'] = $wrap('heart', $liked_content_label);
    
        } else {
            unset($tabs['liked_content']);
        }

        // === Notification Tab ===
        if (isset($streamit_options['enable_notification_module']) && 'yes' === $streamit_options['enable_notification_module']) {
            $notification_label = ! empty($streamit_options['notification_label'])
                ? esc_html($streamit_options['notification_label'])
                : esc_html__('Notification', 'streamit');
    
            $tabs['notification'] = $wrap('bell-1', $notification_label);
    
        } else {
            unset($tabs['notification']);
        }

        /**
         * Filter: Allow further customization of user tabs.
         *
         * @since 1.0.0
         */
        return apply_filters('st_user_profile_tabs', $tabs);
    }
}    


// Initialize the class.
new Streamit_Profile_Tabs_Manager();

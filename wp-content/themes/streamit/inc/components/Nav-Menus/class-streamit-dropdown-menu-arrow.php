<?php

/**
 * Dropdown Menu Arrow Class.
 *
 * Adds a dropdown arrow to menu items that have child elements in the navigation menu.
 * This class modifies the nav menu items by appending a dropdown arrow to items that have submenus (children),
 * and enables the arrow to toggle the submenu visibility with JavaScript.
 *
 * @package streamit
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class streamit_dropdown_menu_arrow
{

    /**
     * Constructor to initialize the class and hook into WordPress.
     */
    public function __construct()
    {
        add_filter('nav_menu_item_title', [$this, 'add_dropdown_arrow'], 10, 4);
        add_filter('nav_menu_link_attributes', [$this, 'filter_nav_menu_empty_href'], 10, 3);

        //Mobile Footer Menu
        // Save custom icon when menu is saved
        add_action('wp_update_nav_menu_item', [$this, 'save_menu_item_icon'], 10, 3);

        // Add icon to menu item in backend (menu editor preview)
        add_filter('wp_setup_nav_menu_item', [$this, 'add_icon_to_menu_item']);

        // Add icon to menu items on frontend
        add_filter('wp_nav_menu_objects', [$this, 'st_add_icon_to_menu_item_on_frontend'], 10, 2);

        //Set limit of 5 in footer mobile menu
        add_filter('wp_nav_menu_objects', [$this, 'limit_footer_menu_items'], 10, 2);


        add_action('wp_nav_menu_item_custom_fields', [$this, 'streamit_add_menu_item_icon_field'], 10, 4);
    }

    /**
     * Adds a dropdown symbol to nav menu items with children.
     *
     * This method adds a dropdown arrow after the menu link element, which is used to indicate that the menu item
     * has submenus. The arrow is toggled with JavaScript to show or hide the child menu items.
     *
     * @param string  $title  The menu item's starting HTML output.
     * @param WP_Post $item   The menu item data object.
     * @param int     $depth  Depth of the menu item. Used for padding.
     * @param object  $args   An object of wp_nav_menu() arguments.
     * 
     * @return string Modified HTML output of the menu item.
     */
    public function add_dropdown_arrow($title, $item, $args, $depth)
    {

        $icon = get_post_meta($item->ID, '_menu_item_icon', true);

        if (isset($args->theme_location) && $args->theme_location === 'streamit-footer-menu-link' && !empty($icon)) {

            // Add a class only for this item's link
            add_filter('nav_menu_link_attributes', function ($atts, $item_filter) use ($item) {
                if ($item_filter->ID === $item->ID) {
                    $atts['class'] = ($atts['class'] ?? '') . ' streamit-menu-icon-link';
                }
                return $atts;
            }, 10, 2);
                $icon_url = esc_url($icon);
           $icon_html = '';
            if ( $icon_url && 'svg' === pathinfo($icon_url, PATHINFO_EXTENSION) ) {
    // Get SVG content from file
                $icon_html = file_get_contents( $icon_url );
                if ( $icon_html ) {
                    $icon_html = sprintf('<span class="streamit-menu-icon">%s</span>', $icon_html);
                }
            }
            $title     = '<span class="css_prefix-menu-item-text has-icon">' . esc_html($title) . '</span>';

            return $icon_html . $title;
        }

        $title = '<span class="css_prefix-menu-item-title">' . esc_html($title) . '</span>';

        // Add dropdown arrow only if menu supports toggle + has children
        if (!empty($args->menu_class) && str_contains($args->menu_class, needle: 'navbar-nav-toggle') && in_array('menu-item-has-children', $item->classes, true)) {
            $title .= '<span class="toggledrop icon-arrow-down"></span>';
        }

        return $title;
    }

    /**
     * Filters the navigation menu link attributes to prevent empty href values.
     *
     * @param array   $atts  The HTML attributes applied to the menu item's `<a>` element.
     * @param WP_Post $item  The current menu item.
     * @param stdClass $args An object containing wp_nav_menu() arguments.
     *
     * @return array Modified attributes with a valid href.
     */
    public function filter_nav_menu_empty_href(array $atts, $item, $args): array
    {
        if (isset($atts['href']) && empty($atts['href'])) {
            $atts['href'] = 'javascript:void(0);'; // Prevents empty links but keeps functionality
        }
        return $atts;
    }


    /**
     * Save custom menu item icon (in menu editor).
     */
    public function save_menu_item_icon($menu_id, $menu_item_db_id, $args)
    {
        if (isset($_POST['menu-item-icon'][$menu_item_db_id])) {
            update_post_meta($menu_item_db_id, '_menu_item_icon', sanitize_text_field($_POST['menu-item-icon'][$menu_item_db_id]));
        }
    }

    /**
     * Add icon data to menu item (backend use).
     */
    public function add_icon_to_menu_item($menu_item)
    {
        $menu_item->icon = get_post_meta($menu_item->ID, '_menu_item_icon', true);
        return $menu_item;
    }

    /**
     * Add icon data to menu item (frontend).
     */
    public function st_add_icon_to_menu_item_on_frontend($items, $args)
    {
        foreach ($items as &$item) {
            $icon_url = get_post_meta($item->ID, '_menu_item_icon', true);
            if ($icon_url) {
                // Store icon URL as a custom property
                $item->icon_url = $icon_url;
            }
        }
        return $items;
    }
    public function streamit_add_menu_item_icon_field($item_id, $item, $depth, $args)
    {
        $icon_url = get_post_meta($item_id, '_menu_item_icon', true);
?>
        <div class="streamit-menu-icon-preview">
            <?php if ($icon_url) : ?>
                <img src="<?php echo esc_url($icon_url); ?>" alt="icon" style="width: 24px; height: 24px; object-fit: cover;" />
            <?php endif; ?>
        </div>
        <p class="description description-wide streamit-menu-icon-field">
            <label for="edit-menu-item-icon-<?php echo esc_attr($item_id); ?>">
                <?php esc_html_e('Menu Icon URL', 'streamit-core'); ?><br />
                <input type="text" id="edit-menu-item-icon-<?php echo esc_attr($item_id); ?>" class="widefat edit-menu-item-icon" name="menu-item-icon[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr($icon_url); ?>" />
                <input type="button" class="button upload-streamit-menu-icon" value="<?php esc_attr_e('Upload Icon', 'streamit-core'); ?>" />
            </label>
        </p>
<?php
    }

    /**
     * Limit the number of items in the footer menu.
     *
     * @param array    $items Menu items.
     * @param stdClass $args  Menu arguments.
     * @return array Modified menu items.
     */
    public function limit_footer_menu_items($items, $args)
    {
        // Only apply to the specific footer menu
        if (isset($args->theme_location) && $args->theme_location === 'streamit-footer-menu-link') {

            /**
             * Filter: streamit_footer_menu_item_limit
             *
             * Allow changing the max number of footer menu items.
             *
             * @param int $limit Max number of items (default 5).
             */
            $limit = apply_filters('streamit_footer_menu_item_limit', 5);

            return array_slice($items, 0, (int) $limit);
        }

        return $items;
    }
}

// Instantiate the class
new streamit_dropdown_menu_arrow();

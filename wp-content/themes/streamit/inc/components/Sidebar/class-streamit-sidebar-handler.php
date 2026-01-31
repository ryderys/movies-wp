<?php

/**
 * Class streamit_sidebar_handler
 *
 * Handles sidebar display logic in the theme.
 * Manages checking the sidebar settings, rendering the appropriate sidebar layout, 
 * and outputting the sidebar based on post type and page conditions.
 *
 * @package streamit
 */
class streamit_sidebar_handler
{

    /**
     * Constructor to hook necessary actions and filters.
     */
    public function __construct()
    {
        add_action('st_before_main_sidebar_class',  [$this, 'before_main_sidebar_class'], 20, 1);
        add_action('st_after_main_sidebar_class',   [$this, 'after_main_sidebar_class'], 20);
        add_action('st_get_sidebar',                [$this, 'get_sidebar'], 20, 1);
        add_filter('st_sidebar_direction',          [$this, 'sidebar_layout_direction'], 20, 1);
    }


    /**
     * Adds the appropriate class to the main sidebar container.
     *
     * @param string $post_type The post type of the current page or post.
     */
    public function before_main_sidebar_class($post_type)
    {
        // Retrieve the content class based on the post type
        $row_class = $this->st_main_content_class($post_type);

        // Check if the sidebar should be displayed for the current post type
        if (!$this->should_display_sidebar($post_type)) {
            echo '<div class="col-lg-12 css_prefix-blog-main-list "><div class="'. $row_class .'">';
            return;
        }

        echo '<div class="col-xl-8 css_prefix-blog-main-list"><div class="'. $row_class .'">';
    }

    /**
     * Closes the main sidebar container after content is rendered.
     */
    public function after_main_sidebar_class()
    {
        echo '</div></div>';
    }

    /**
     * Checks whether the sidebar should be displayed for a given post type.
     *
     * @param string $post_type The post type of the current page or post.
     * @return bool True if sidebar should be displayed, false otherwise.
     */
    public function should_display_sidebar($post_type)
    {
        global $streamit_options;

        $sidebar_name = $this->get_sidebar_name($post_type);

        // Ensure the sidebar is registered and active
        if (!is_registered_sidebar($sidebar_name) || !is_active_sidebar($sidebar_name)) {
            return false;
        }

        // Determine if sidebar settings allow display
        $post_type_key = is_single() ? $post_type . '_single' : $post_type;
        $sidebar_setting = $streamit_options[$post_type_key . '_sidebar_setting'] ?? null;
        if (empty($sidebar_setting)) return true;
        // Check if the sidebar setting is either '2' or '3'
        return in_array($sidebar_setting, ['2', '3'], true);
    }

    /**
     * Renders the appropriate sidebar based on the post type.
     *
     * @param string $post_type The post type of the current page or post.
     */
    public function get_sidebar($post_type)
    {

        if (!$this->should_display_sidebar($post_type)) {
            return;
        }

        ob_start();

        $sidebar_class = $this->get_sidebar_class($post_type);
        $sidebar_name = $this->get_sidebar_name($post_type);

?>
        <div class="<?php echo esc_attr($sidebar_class); ?>">
            <aside id="secondary" class="primary-sidebar widget-area">
                <h2 class="screen-reader-text"><?php esc_html_e('Asides', 'streamit'); ?></h2>
                <?php dynamic_sidebar($sidebar_name); ?>
            </aside>
        </div>
<?php

        $sidebar_content = ob_get_clean();
        echo $sidebar_content;
    }

    /**
     * Determines the direction of the sidebar layout based on settings.
     *
     * @param string $post_type The post type of the current page or post.
     */
    public function sidebar_layout_direction($post_type)
    {
        global $streamit_options;

        if (!$this->should_display_sidebar($post_type)) {
            return;
        }

        $post_type_key = is_single() ? $post_type . '_single' : $post_type;
        $sidebar_setting = $streamit_options[$post_type_key . '_sidebar_setting'] ?? null;

        if ($sidebar_setting === '3') {
            echo 'flex-row-reverse';
        }
    }

    /**
     * Retrieves the sidebar name based on the post type.
     *
     * @param string $post_type The post type of the current page or post.
     * @return string The sidebar name.
     */
    private function get_sidebar_name($post_type)
    {
        $sidebars = apply_filters('streamit_sidebar_name', [
            'post'    => 'streamit-blog-sidebar',
            'product' => 'streamit_product_sidebar',
        ]);

        return $sidebars[$post_type] ?? 'streamit-blog-sidebar';
    }

    /**
     * Retrieves the CSS class for the sidebar container based on the post type.
     *
     * @param string $post_type The post type of the current page or post.
     * @return string The sidebar CSS class.
     */
    private function get_sidebar_class($post_type)
    {
        $sidebar_classes = apply_filters('streamit_render_sidebar_class', [
            'post'    => 'col-lg-4 col-sm-12 mt-5 mt-xl-0 sidebar-service-right',
            'product' => 'col-xl-3 col-sm-12 mt-xl-0 sidebar-service-right css_prefix-woo-sidebar',
        ]);

        return $sidebar_classes[$post_type] ?? 'col-lg-4 col-sm-12 mt-5 mt-xl-0 sidebar-service-right';
    }

    /**
     * Retrieves the content column class based on the post type and custom options.
     *
     * @param string $post_type The post type of the current page or post.
     * @return string The appropriate content class.
     */
    public function st_main_content_class($post_type)
    {
        global $streamit_options;
        // Generate the option key for the post type's content column setting
        $content_col = $post_type . '_conent_col';

        if(is_single()) return 'row gy-4 row-cols-1';
        // Return default class if the option is not set
        if (!isset($streamit_options[$content_col])) {
            return 'data-listing row gy-4 row-cols-1'; // Default class if not configured
        }

        // Get the column count setting
        $col_count = $streamit_options[$content_col];

        // Define the content column classes based on the setting
        $content_class = [
            '1' => 'data-listing row gy-4 row-cols-1',
            '2' => 'data-listing row gy-4 row-cols-2 row-cols-sm-2',
            '3' => 'data-listing row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3',
        ];

        // Return the appropriate class or default if invalid value
        return isset($content_class[$col_count]) ? $content_class[$col_count] : 'row-cols-2 row-cols-sm-2';
    }
}

// Instantiate the Sidebar Handler class
new streamit_sidebar_handler();

<?php

/**
 * Breadcrumb Class.
 *
 * @package streamit
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class streamit_breadcrumb
{

    /**
     * Constructor to initialize the breadcrumb functionality.
     */
    public function __construct()
    {
        add_filter('body_class', array($this, 'add_custom_body_class'));
        add_action('st_breadcrumb_layout', array($this, 'breadcrumb_layout'), 20, 2);
    }

    /**
     * Add custom body class based on the display banner meta option.
     *
     * @param array $classes Array of body classes.
     * @return array Modified body classes.
     */
    public function add_custom_body_class($classes)
    {
        global $streamit_options;

        $class = (isset($streamit_options['display_banner_meta']) && $streamit_options['display_banner_meta'] === 'no') ? 'home' : 'not-home';
        $classes[] = $class;

        return $classes;
    }

    /**
     * Change the layout of the breadcrumb.
     *
     * @return void
     */
    public function breadcrumb_layout($view_type = '', $content_type = '')
    {
        global $streamit_options;

        $breadcrumb_style = isset($streamit_options['breadcrumb_style']) ? $streamit_options['breadcrumb_style'] : '1';
?>
        <div class="css_prefix-breadcrumb css_prefix-breadcrumb-style-<?php echo esc_attr($breadcrumb_style); ?>">
            <div class="container">
                <?php
                switch ($breadcrumb_style) {
                    case 'two':
                        $this->breadcrumb_layout_style_two();
                        break;
                    case 'three':
                        $this->breadcrumb_layout_style_three();
                        break;
                    default:
                        $this->breadcrumb_layout_style_default($view_type, $content_type);
                }
                ?>
            </div>
        </div>
    <?php
    }

    /**
     * Breadcrumb layout for style two.
     *
     * @return void
     */
    public function breadcrumb_layout_style_two()
    {
    ?>
        <div class="row align-items-center">
            <div class="col-lg-8 col-md-8 text-start">
                <nav aria-label="breadcrumb" class="text-start css_prefix-breadcrumb-nav">
                    <?php $this->breadcrumb_nav('breadcrumb main-bg justify-content-start'); ?>
                </nav>
            </div>
        </div>
    <?php
    }

    /**
     * Breadcrumb layout for style three.
     *
     * @return void
     */
    public function breadcrumb_layout_style_three()
    {
    ?>
        <div class="row align-items-center">
            <div class="col">
                <nav aria-label="breadcrumb" class="text-end css_prefix-breadcrumb-nav">
                    <?php $this->breadcrumb_nav('breadcrumb main-bg justify-content-end'); ?>
                </nav>
            </div>
        </div>
    <?php
    }

    /**
     * Default breadcrumb layout.
     *
     * @return void
     */
    public function breadcrumb_layout_style_default($view_type = '', $content_type = '')
    {
    ?>
        <div class="row align-items-center justify-content-center text-center">
            <div class="col-sm-12">
                <nav aria-label="breadcrumb" class="css_prefix-breadcrumb-nav">
                    <?php $this->breadcrumb_nav('breadcrumb main-bg justify-content-center', $view_type, $content_type); ?>
                </nav>
            </div>
        </div>
<?php
    }

    /**
     * Display breadcrumb navigation.
     *
     * @param string $class       Custom classes for the breadcrumb.
     * @param string $view_type   Type of view (archive, category-archive, etc.).
     * @param string $content_type Content type for the breadcrumb.
     * @return void
     */
    public function breadcrumb_nav($class = '', $view_type = '', $content_type = '')
    {
        global $streamit_options, $post;

        // Early exits for conditions where breadcrumb should not display
        if (
            (isset($streamit_options['display_banner_meta']) && $streamit_options['display_banner_meta'] === 'yes') ||
            is_front_page()
        ) {
            return;
        }

        $home_label = esc_html__('Home', 'streamit');
        $home_link  = esc_url(home_url());
        $show_current = true;

        echo '<ol class="' . esc_attr($class) . '">';
        echo '<li class="breadcrumb-item"><a href="' . $home_link . '">' . $home_label . '</a></li>';

        // Homepage blog listing
        if (is_home()) {
            $labels = [
                'archive'          => $content_type,
                'category-archive' => $content_type . ' ' . esc_html__('categories', 'streamit'),
                'genre-archive'    => $content_type . ' ' . esc_html__('genres', 'streamit'),
                'tag-archive'      => $content_type . ' ' . esc_html__('tags', 'streamit'),
            ];
            echo '<li class="breadcrumb-item active">' . esc_html($labels[$view_type] ?? esc_html__('Blogs', 'streamit')) . '</li>';

            // Category archive
        } elseif (is_category()) {
            $this_cat = get_category(get_query_var('cat'));
            if ($this_cat->parent) {
                echo '<li class="breadcrumb-item">' . get_category_parents($this_cat->parent, true, ' ') . '</li>';
            }
            echo '<li class="breadcrumb-item active">' . esc_html__('Archive by category : ', 'streamit') . '"' . single_cat_title('', false) . '"</li>';

            // Search results
        } elseif (is_search()) {
            echo '<li class="breadcrumb-item active">' . esc_html__('Search results for : ', 'streamit') . '"' . get_search_query() . '"</li>';

            // Date archives
        } elseif (is_day()) {
            echo '<li class="breadcrumb-item active">' . get_the_date('F d, Y') . '</li>';
        } elseif (is_month()) {
            echo '<li class="breadcrumb-item active">' . get_the_date('F Y') . '</li>';
        } elseif (is_year()) {
            echo '<li class="breadcrumb-item active">' . get_the_date('Y') . '</li>';

            // Single posts
        } elseif (is_single() && !is_attachment()) {
            if (get_post_type() !== 'post') {
                $post_type = get_post_type_object(get_post_type());
                if (get_post_type() === 'product') {
                    echo '<li class="breadcrumb-item"><a href="' . esc_url(wc_get_page_permalink('shop')) . '">' . esc_html__('Product', 'streamit') . '</a></li>';
                } elseif (!empty($post_type->rewrite)) {
                    echo '<li class="breadcrumb-item"><a href="' . $home_link . '/' . $post_type->rewrite['slug'] . '/">' . esc_html($post_type->labels->singular_name) . '</a></li>';
                }
                if ($show_current) {
                    echo '<li class="breadcrumb-item">' . get_the_title() . '</li>';
                }
            } else {
                $cat = get_the_category();
                if (!empty($cat)) {
                    echo '<li class="breadcrumb-item">' . get_category_parents($cat[0], true, '') . '</li>';
                    echo '<li class="breadcrumb-item active">' . get_the_title() . '</li>';
                }
            }

            // Pages
        } elseif (is_page()) {
            if ($show_current) {
                echo '<li class="breadcrumb-item active">' . get_the_title() . '</li>';
            }

            // Custom post types
        } elseif (!is_single() && !is_page() && get_post_type() !== 'post' && !is_404()) {
            $post_type = get_post_type_object(get_post_type());
            if ($post_type) {
                echo '<li class="breadcrumb-item active">' . esc_html($post_type->labels->singular_name) . '</li>';
            }

            // Tags
        } elseif (is_tag()) {
            echo '<li class="breadcrumb-item active">' . esc_html__('Archive by Tag : ', 'streamit') . '"' . single_tag_title('', false) . '"</li>';

            // Authors
        } elseif (is_author()) {
            $userdata = get_userdata(get_query_var('author'));
            echo '<li class="breadcrumb-item active">' . esc_html__('Articles posted by : ', 'streamit') . ' ' . esc_html($userdata->display_name) . '</li>';

            // 404
        } elseif (is_404()) {
            echo '<li class="breadcrumb-item active">' . esc_html__('Error 404', 'streamit') . '</li>';
        }

        // Pagination
        if (get_query_var('paged')) {
            echo '<li class="breadcrumb-item active">' . esc_html__('Page', 'streamit') . ' ' . get_query_var('paged') . '</li>';
        }

        echo '</ol>';
    }
}

// Initialize the breadcrumb class.
new streamit_breadcrumb();

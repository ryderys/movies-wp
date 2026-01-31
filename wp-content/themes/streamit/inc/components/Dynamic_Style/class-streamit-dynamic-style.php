<?php

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
/**
 * Class streamit_dynamic_style
 *
 * Handles Dynamic Style Site Layout.
 *
 * This class loads and applies dynamic styles for different components of the theme,
 * ensuring that all necessary styles are included for the layout, including Header, Footer,
 * Typography, and other sections.
 *
 * @package streamit
 */
class streamit_dynamic_style
{

    /**
     * Constructor: Registers the action hook to load dynamic styles after the theme setup.
     *
     * This method hooks into WordPress' `after_setup_theme` action to ensure that styles are added
     * once the theme is initialized.
     */
    public function __construct()
    {
        add_filter('body_class', array($this, 'st_add_body_classes'));
        add_filter('st_inline_css', array($this, 'st_inline_css'));
        add_filter('st_inline_js', array($this, 'st_inline_scripts'));
        add_action('template_redirect', array($this, 'st_maintenance_mode_redirect'));
    }


    public function st_add_body_classes($classes)
    {
        global $streamit_options;
        if (isset($streamit_options['header_postion']) && $streamit_options['header_postion'] == 'over') {
            $classes[] = 'header-absolute';
        }
        return $classes;
    }


    /**
     * Main method to generate dynamic CSS.
     *
     * @param string $st_css_code Existing inline CSS code.
     * @return string Updated CSS code with dynamic styles.
     */
    public function st_inline_css($st_css_code)
    {
        //Additional code
        $st_css_code .= $this->st_inline_style();

        //Breadcrumb style
        $st_css_code .= $this->st_breadcrumb_dynamic_style();

        //Breadcrumb style
        $st_css_code .= $this->st_image_logo_design();

        // Color Value
        $st_css_code .= $this->st_color_options();

        // Texture Button 
        $st_css_code .= $this->st_big_heading_texture_button();

        // Footer Style 
        $st_css_code .= $this->st_footer_dynamic_style();

        // General Style
        $st_css_code .= $this->st_create_general_style();

        // Hide Featured 
        $st_css_code .= $this->st_featured_hide();

        // Header Style 
        $st_css_code .= $this->st_header_background_style();

        // Loader Style 
        $st_css_code .= $this->st_loader_options();

        // Logo style
        $st_css_code .= $this->st_header_logo_options();

        //Typography
        $st_css_code .= $this->st_fontstyle_dynamic_style();

        //Badge
        $st_css_code .= $this->st_badge_dynamic_style();

        //subscribe
        $st_css_code .= $this->st_subscribe_dynamic_style();
        return $st_css_code;
    }

    public function st_inline_style()
    {
        global $streamit_options;
        $st_css_code = '';
        // Check if custom CSS code exists and append it.
        if (! empty($streamit_options['css_code'])) {
            $st_css_code = $streamit_options['css_code'];
        }

        return $st_css_code;
    }

    public function st_breadcrumb_dynamic_style()
    {
        global $streamit_options;

        if (!isset($streamit_options)) return '';

        // Check if breadcrumbs are enabled and if it's not a 404 page.
        if (isset($streamit_options['display_breadcrumb']) && 'no' === $streamit_options['display_breadcrumb'] || is_404()) {
            return '';
        }
        $dynamic_css = '';
        // Background color for breadcrumb.
        if (isset($streamit_options['breadcrumb_text_color']) && ! empty($streamit_options['breadcrumb_text_color'])) {
            $dynamic_css .= '.css_prefix-breadcrumb .breadcrumb-item a { color: ' . esc_attr($streamit_options['breadcrumb_text_color']) . ' !important; }';
        }

        // Text color for breadcrumb.
        if (isset($streamit_options['breadcrumb_back_color']) && ! empty($streamit_options['breadcrumb_back_color'])) {
            $dynamic_css .= '.css_prefix-breadcrumb { background: ' . esc_attr($streamit_options['breadcrumb_back_color']) . ' !important; }';
        }

        return $dynamic_css;
    }

    /**
     * Generates dynamic CSS for site colors based on theme options.
     *
     * This function creates CSS variables for primary color, title color, and page text color,
     * and appends them to the existing CSS. The colors are fetched from theme options, 
     * either using custom meta values or default settings.
     *
     * @param string $color_attrs Existing inline CSS code.
     * 
     * @return string Modified inline CSS code with dynamic color variables.
     */
    public function st_color_options()
    {
        global $streamit_options;

        // Initialize the color variables string.
        $color_var = '';

        // Check if the custom color switch is disabled and if the default color palette is set.
        if ('no' === $streamit_options['custom_color_switch'] && 'default' === $streamit_options['display_color_pallet_meta']) {
            return '';
        }

        // Set primary color using meta value if available, otherwise fallback to default.
        $primary_color = ('yes' === $streamit_options['display_color_pallet_meta'] && ! empty($streamit_options['primary_color_meta']))
            ? $streamit_options['primary_color_meta']
            : $streamit_options['primary_color'];

        if (! empty($primary_color)) {
            // Base primary color.
            $color_var .= '--bs-primary: ' . esc_attr($primary_color) . ' !important;';

            // Generate RGB value (e.g. "52, 152, 219").
            $rgb = $this->hexToRgb($primary_color);
            $rgb_string = $rgb['r'] . ', ' . $rgb['g'] . ', ' . $rgb['b'];
            $color_var .= '--bs-primary-rgb: ' . esc_attr($rgb_string) . ' !important;';

            // Generate tinted version for subtle backgrounds (80% tint).
            $tinted = $this->tintColor($primary_color, 80);
            $color_var .= '--bs-primary-bg-subtle: ' . esc_attr($tinted) . ' !important;';
            $color_var .= '--bs-primary-border-subtle: var(--bs-primary-bg-subtle) !important;';

            // Set link color RGB to primary RGB.
            $color_var .= '--bs-link-color-rgb: var(--bs-primary-rgb) !important;';

            // Generate shades for hover and active states.
            $hover_bg = $this->shadeColor($primary_color, 20);  // 20% darker for hover background.
            $hover_border = $this->shadeColor($primary_color, 30); // 30% darker for hover border.
            $color_var .= '--bs-primary-hover-bg: ' . esc_attr($hover_bg) . ' !important;';
            $color_var .= '--bs-primary-hover-border: ' . esc_attr($hover_border) . ' !important;';
            $color_var .= '--bs-primary-active-bg: ' . esc_attr($hover_bg) . ' !important;';
            $color_var .= '--bs-primary-active-border: ' . esc_attr($hover_border) . ' !important;';
        }

        // Set title color using meta value if available, otherwise fallback to default.
        $title_color = ('yes' === $streamit_options['display_color_pallet_meta'] && ! empty($streamit_options['title_color_meta']))
            ? $streamit_options['title_color_meta']
            : '';
        $title_color = empty($title_color) && isset($streamit_options['title_color']) ? $streamit_options['title_color'] : '';
        if (! empty($title_color)) {
            $color_var .= ' --bs-heading-color: ' . esc_attr($title_color) . ' !important;';
        }

        // Set page text color using meta value if available, otherwise fallback to default.
        $page_text_color = ('yes' === $streamit_options['display_color_pallet_meta'] && ! empty($streamit_options['page_text_color_meta']))
            ? $streamit_options['page_text_color_meta']
            : $streamit_options['text_color'];

        if (! empty($page_text_color)) {
            $color_var .= '--bs-body-color: ' . esc_attr($page_text_color) . ' !important;';
        }

        // Append the generated color styles to the existing CSS.
        return ':root { ' . $color_var . ' }';
    }

    /**
     * Adds dynamic styles for the big heading texture button.
     *
     * This function checks the options for the big heading background type (color or image) 
     * and generates the appropriate CSS. It also applies a specific texture effect to 
     * the text in the big heading and top-ten numbers sections.
     *
     * @param string $heading_var Existing inline CSS code.
     * 
     * @return string Modified inline CSS code with dynamic background styles.
     */
    public function st_big_heading_texture_button()
    {
        global $streamit_options;
        $heading_var = '';

        // Get the background type for the big heading.
        $big_heading_bg_type = isset($streamit_options['streamit_big_heading_title_bg_type']) ? $streamit_options['streamit_big_heading_title_bg_type'] : '';

        // If background type is color (type 1), apply color styles.
        if ('1' === $big_heading_bg_type) {
            $big_heading_color = isset($streamit_options['streamit_big_heading_title_bg_color']) ? $streamit_options['streamit_big_heading_title_bg_color'] : '';

            if (! empty($big_heading_color)) {
                $heading_var = "
                .texture-text {
                    color: " . esc_attr($big_heading_color) . " !important;
                    -webkit-text-fill-color: unset !important;
                    -moz-text-fill-color: unset !important;
                    text-fill-color: unset !important;
                }";
            }
        } else if ('2' === $big_heading_bg_type) {
            // If background type is image, apply background image styles.
            $big_heading_image = !empty($streamit_options['streamit_big_heading_title_banner_image']['url']) ? $streamit_options['streamit_big_heading_title_banner_image']['url'] : get_template_directory_uri() . '/admin/assets/images/redux/texture.jpg';


            $heading_var = "
                .texture-text, .top-ten .top_ten_numbers {
                    background: url(" . esc_url($big_heading_image) . ");
                    -webkit-background-clip: text;
                    -moz-background-clip: text;
                    background-clip: text;
                }";
        }

        return $heading_var;
    }


    public function st_footer_dynamic_style()
    {
        global $streamit_options;
        $footer_css = '';
        if ($streamit_options['change_footer_background'] == 'color' && !empty($streamit_options['footer_bg_color'])) {
            $footer_bg_color = !empty($streamit_options['footer_bg_color']) ? $streamit_options['footer_bg_color'] : '';
        }

        if ($streamit_options['display_footer_meta'] == 'yes') {
            $footer_bg_color = !empty($streamit_options['select_footer_bg_meta']) ? $streamit_options['select_footer_bg_meta'] : '';
        }

        if (!empty($footer_bg_color)) {
            $footer_css .= "footer.footer.css_prefix-footer { background-color: $footer_bg_color !important; }";
        }

        if ($streamit_options['change_footer_background'] == 'image' && !empty($streamit_options['footer_bg_image']['url'])) {
            $footer_bg_image = $streamit_options['footer_bg_image'];
            $footer_css .= ".footer {
										background: url(" . $footer_bg_image['url'] . ") no-repeat !important;
										backgrouns-size: cover !important ;
									}";
        }

        return $footer_css;
    }

    public function st_create_general_style()
    {
        $st_css_code = '';
        $st_css_code .= $this->st_body_color();

        $st_css_code .= $this->st_general_body_size();

        $st_css_code .= $this->st_set_page_specing();

        return $st_css_code;
    }

    public function st_general_body_size()
    {
        global $streamit_options;
        $general_var = '';
        if (!empty($streamit_options['grid_container']['width'])) {
            $general_var = '--content-width: ' . $streamit_options['grid_container']['width'] . ' !important;';
            $general_var = ":root{" . $general_var . "}";
        }

        return $general_var;
    }

    public function st_body_color()
    {
        global $streamit_options;

        // If layout_set is 2, do nothing and return the original CSS.
        if (isset($streamit_options['layout_set']) && $streamit_options['layout_set'] == 2) {
            return '';
        }

        // For layout_set 1, we build CSS custom properties.
        if (isset($streamit_options['layout_set']) && $streamit_options['layout_set'] == 1) {
            $custom_props = '';

            // Set body background color if provided.
            if (isset($streamit_options['streamit_layout_color']) && ! empty($streamit_options['streamit_layout_color'])) {
                $general = $streamit_options['streamit_layout_color'];
                $custom_props .= '--bs-body-bg: ' . esc_attr($general) . ' !important;';
            }

            // Set card background color using the dedicated key.
            if (isset($streamit_options['streamit_layout_card_bg_color']) && ! empty($streamit_options['streamit_layout_card_bg_color'])) {
                $card_bg_color = $streamit_options['streamit_layout_card_bg_color'];
                $custom_props .= '--bs-gray-900: ' . esc_attr($card_bg_color) . ' !important;';
            }

            // Wrap the custom properties inside the :root selector.
            return ':root { ' . $custom_props . ' }';
        }

        // For layout_set 3, set a background image for the body.
        if (isset($streamit_options['layout_set']) && $streamit_options['layout_set'] == 3) {
            if (isset($streamit_options['streamit_layout_image']['url']) && ! empty($streamit_options['streamit_layout_image']['url'])) {
                $general = $streamit_options['streamit_layout_image']['url'];
                $general_var = 'body { background-image: url(' . esc_url($general) . ') !important; }';
                return $general_var;
            }
        }
        return '';
    }


    public function st_set_page_specing()
    {
        global $streamit_options;
        if ($streamit_options['is_page_spacing'] == 'default') {
            return '';
        }
        $general_var = '';
        $spacings = [
            'page_spacing' => ['--global-page-top-spacing', '--global-page-bottom-spacing'],
            'tablet_page_spacing' => ['--global-page-top-spacing-tablet', '--global-page-bottom-spacing-tablet'],
            'mobile_page_spacing' => ['--global-page-top-spacing-mobile', ' --global-page-bottom-spacing-mobile']
        ];

        foreach ($spacings as $options_value => $vars) {
            if (isset($streamit_options[$options_value]) && !empty($streamit_options[$options_value])) {
                $general_var .= !empty($streamit_options[$options_value]["top"]) ? $vars[0] . ":" . $streamit_options[$options_value]["top"] . " !important;" : "";
                $general_var .= !empty($streamit_options[$options_value]["bottom"]) ? $vars[1] . ":" . $streamit_options[$options_value]["bottom"] . " !important;" : "";
            }
        }
        return $general_var ? ":root{{$general_var}}" : '';
    }

    public function st_header_background_style()
    {
        global $streamit_options;
        if ($streamit_options['st_header_background_type'] == 'default') {
            return '';
        }
        $dynamic_css = '';
        $type = $streamit_options['st_header_background_type'];
        if ($type == 'color') {
            if (!empty($streamit_options['streamit_header_background_color'])) {
                $dynamic_css .= 'header#default-header{
                                background : ' . $streamit_options['streamit_header_background_color'] . '!important;
                            }';
            }
        } elseif ($type == 'image') {
            if (!empty($streamit_options['streamit_header_background_image']['url'])) {
                $dynamic_css .= 'header#default-header{
                                background : url(' . $streamit_options['streamit_header_background_image']['url'] . ') !important;
                            }';
            }
        } elseif ($type == 'transparent') {
            $dynamic_css .= 'header#default-header{
                            background : transparent !important;
                        }';
        }
        return  $dynamic_css;
    }

    public function st_loader_options()
    {
        global $streamit_options;

        if ($streamit_options['streamit_display_loader'] == 'no') {
            return '';
        }
        $loader_var = '';
        if (!empty($streamit_options['loader_bg_color'])) {
            $loader_bg_color = $streamit_options['loader_bg_color'];
            $loader_var .= "
                        #loading {
                            background : $loader_bg_color !important;
                        }";
        }

        if (!empty($streamit_options["loader-dimensions"]["width"]) && $streamit_options["loader-dimensions"]["width"] != "px") {
            $loader_width = $streamit_options["loader-dimensions"]["width"];
            $loader_var .= '#loading img { width: ' . $loader_width . ' !important; }';
        }

        if (!empty($streamit_options["loader-dimensions"]["height"]) && $streamit_options["loader-dimensions"]["height"] != "px") {
            $loader_height = $streamit_options["loader-dimensions"]["height"];
            $loader_var .= '#loading img { height: ' . $loader_height . ' !important; }';
        }

        return  $loader_var;
    }

    public function st_featured_hide()
    {
        global $streamit_options;
        $post_format = "";

        if (isset($streamit_options['posts_select'])) {
            $posts_format = $streamit_options['posts_select'];
            $post_format = get_post_format();

            if (!empty($posts_format) && in_array(get_post_format(), $posts_format)) {
                $general_var = '.css_prefix-blog-main-list .format-' . $post_format . ' .css_prefix-blog-box .css_prefix-blog-media img { display: none !important; }';
                return $general_var;
            }
        }
    }


    public function st_header_logo_options()
    {
        global $streamit_options;
        $logo_var = '';
        if (isset($streamit_options['header_color']) && !empty($streamit_options['header_color'])) {
            $general_logo = $streamit_options['header_color'];
            $logo_var .= '.logo-text { color : ' . $general_logo . ' !important; }';
        }
        return $logo_var;
    }


    public function st_text_logo_design()
    {
        global $streamit_options;
        $logo_var = '';
        if (!empty($streamit_options['header_color'])) {
            $logo = $streamit_options['header_color'];
            $logo_var = ".navbar-light .navbar-brand {
                color : $logo !important;
            }";
        }
        return $logo_var;
    }

    public function st_image_logo_design()
    {
        global $streamit_options;
        $logo_var = '';
        if (!empty($streamit_options["logo-dimensions"]["width"]) && $streamit_options["logo-dimensions"]["width"] != "px") {
            $logo_width = $streamit_options["logo-dimensions"]["width"];
            $logo_var .= '.header-default .logo { width: ' . $logo_width . ' !important; }';
        }

        if (!empty($streamit_options["logo-dimensions"]["height"]) && $streamit_options["logo-dimensions"]["height"] != "px") {
            $logo_height = $streamit_options["logo-dimensions"]["height"];
            $logo_var .= '.header-default .logo { height: ' . $logo_height . ' !important; }';
        }

        return $logo_var;
    }


    public function st_fontstyle_dynamic_style()
    {
        global $streamit_options;

        // If font change is disabled, return the CSS as is
        if ($streamit_options['streamit_change_font'] == 0) {
            return '';
        }
        $font_dynamic_css = '';

        // Body font styles
        if (! empty($streamit_options["body_font"]["font-family"])) {
            $body_family = esc_attr($streamit_options["body_font"]["font-family"]);
            $body_size = esc_attr($streamit_options["body_font"]["font-size"]);
            $body_weight = esc_attr($streamit_options["body_font"]["font-weight"]);
            $font_dynamic_css .= $this->st_generate_font_css('body', $body_family, $body_size, $body_weight);
        }

        // Heading font styles (h1 - h6)
        $headings = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        foreach ($headings as $heading) {
            if (! empty($streamit_options["{$heading}_font"]["font-family"])) {
                $font_family = esc_attr($streamit_options["{$heading}_font"]["font-family"]);
                $font_size = esc_attr($streamit_options["{$heading}_font"]["font-size"]);
                $font_weight = esc_attr($streamit_options["{$heading}_font"]["font-weight"]);
                $font_dynamic_css .= $this->st_generate_font_css($heading, $font_family, $font_size, $font_weight);
            }
        }

        return $font_dynamic_css;
    }

    public function st_badge_dynamic_style()
    {
        global $streamit_options;
        $output = '';

        if (isset($streamit_options['streamit_recommended_premium_badge_color']) && !empty($streamit_options['streamit_recommended_premium_badge_color'])) {
            $premium_color = $streamit_options['streamit_recommended_premium_badge_color'];
            $output .= '.product-premium { background: ' . $premium_color . ' !important; }';
        }

        if (isset($streamit_options['streamit_recommended_ppv_badge_color']) && !empty($streamit_options['streamit_recommended_ppv_badge_color'])) {
            $ppv_color = $streamit_options['streamit_recommended_ppv_badge_color'];
            $output .= '.product-ppv { background: ' . $ppv_color . ' !important; }';
        }

        if (isset($streamit_options['streamit_recommended_ppv_rented_badge_color']) && !empty($streamit_options['streamit_recommended_ppv_rented_badge_color'])) {
            $ppv_rented_color = $streamit_options['streamit_recommended_ppv_rented_badge_color'];
            $output .= '.product-ppv-rented { background: ' . $ppv_rented_color . ' !important; }';
        }

        if (isset($streamit_options['streamit_recommended_upcoming_badge_color']) && !empty($streamit_options['streamit_recommended_upcoming_badge_color'])) {
            $upcoming_color = $streamit_options['streamit_recommended_upcoming_badge_color'];
            $output .= '.product-upcoming { background: ' . $upcoming_color . ' !important; }';
            $output .= '.upcoming-badge { background: ' . $upcoming_color . ' !important; }';
            $output .= '.episode-upcoming-badge { background: ' . $upcoming_color . ' !important; }';
        }

        return $output;
    }



    public function st_subscribe_dynamic_style()
    {
        global $streamit_options;
        $general_subscribe_var = '';
        // Check and apply text color
        if (!empty($streamit_options['streamit_subscribe_text_color'])) {
            $text_color = esc_attr($streamit_options['streamit_subscribe_text_color']);
            $general_subscribe_var .= '.subscribe-btn { color: ' . $text_color . ' !important; }';
        }

        // Check and apply background color
        if (!empty($streamit_options['streamit_subscribe_background_color'])) {
            $background_color = esc_attr($streamit_options['streamit_subscribe_background_color']);
           $general_subscribe_var .= 'header .subscribe-btn.btn.btn-warning-subtle::after { border: ' . $background_color . ' !important; }';
            $general_subscribe_var .= 'header .subscribe-btn.btn.btn-warning-subtle::after { background: ' . $background_color . ' !important; }';
        }

         // Check and apply hover color 
         if (!empty($streamit_options['streamit_subscribe_hover_color'])) {
            $hover_color = esc_attr($streamit_options['streamit_subscribe_hover_color']);
            // $general_subscribe_var .= '.subscribe-btn:hover { color: ' . $hover_color . ' !important; }';
            $general_subscribe_var .= 'header .subscribe-btn.btn-warning-subtle:hover
 { background: ' . $hover_color . ' !important; }';
        }

        return $general_subscribe_var;
    }

    public function st_generate_font_css($element, $font_family, $font_size, $font_weight)
    {
        $css = '';

        if (! empty($font_family)) {
            $css .= "$element { font-family: $font_family !important; }";
        }

        if (! empty($font_size)) {
            $css .= "$element { font-size: $font_size !important; }";
        }

        if (! empty($font_weight)) {
            $css .= "$element { font-weight: $font_weight !important; }";
        }

        return $css;
    }



    public function st_inline_scripts($st_js_code)
    {

        global $streamit_options;

        // Check if custom JS code exists and append it.
        if (! empty($streamit_options['js_code'])) {
            $st_js_code .= $streamit_options['js_code'];
        }

        return $st_js_code;
    }
    public function st_maintenance_mode_redirect()
    {
        global $streamit_options;
        if (!isset($streamit_options['mainte_mode']) || 'yes' !== esc_attr($streamit_options['mainte_mode'])) {
            return;
        }
        

        // Skip for admin/dashboard and privileged/logged-in users
        if (is_admin() || is_user_logged_in() || current_user_can('manage_options')) {
            return;
        }

        // Skip the login/register pages
        global $pagenow;
        if (isset($pagenow) && $pagenow === 'wp-login.php') {
            return;
        }
        $maintenance_template = get_template_directory() . '/template-parts/maintenance/maintenance.php';
        if (file_exists($maintenance_template)) {
            include $maintenance_template;
            exit;
        }
    }

    /**
     * Converts a HEX color to its RGB components.
     *
     * @param string $hex Hex color code (e.g. "#3498db" or "3498db").
     * @return array Associative array with keys 'r', 'g', and 'b'.
     */
    private function hexToRgb($hex)
    {
        // Remove '#' if present.
        $hex = ltrim($hex, '#');

        // Support shorthand notation (e.g. "fff").
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] .
                $hex[1] . $hex[1] .
                $hex[2] . $hex[2];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }

    /**
     * Tints a HEX color by mixing it with white.
     *
     * @param string $hex HEX color code.
     * @param int $percent Percentage of white to mix (0-100).
     * @return string Tinted HEX color.
     */
    private function tintColor($hex, $percent)
    {
        $rgb = $this->hexToRgb($hex);
        $r = round($rgb['r'] + ((255 - $rgb['r']) * ($percent / 100)));
        $g = round($rgb['g'] + ((255 - $rgb['g']) * ($percent / 100)));
        $b = round($rgb['b'] + ((255 - $rgb['b']) * ($percent / 100)));
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }

    /**
     * Shades a HEX color by mixing it with black.
     *
     * @param string $hex HEX color code.
     * @param int $percent Percentage of black to mix (0-100).
     * @return string Shaded HEX color.
     */
    private function shadeColor($hex, $percent)
    {
        $rgb = $this->hexToRgb($hex);
        $r = round($rgb['r'] * ((100 - $percent) / 100));
        $g = round($rgb['g'] * ((100 - $percent) / 100));
        $b = round($rgb['b'] * ((100 - $percent) / 100));
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}

// Initialize the streamit_dynamic_style class to ensure dynamic styles are added.
new streamit_dynamic_style();

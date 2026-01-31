<?php

namespace Elementor;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class streamit_elementor
{
    public function __construct()
    {
        // Require streamit plugin to active
        if (class_exists('Streamit')) {
           

            add_action('elementor/controls/register', [$this, 'register_controls']);

            add_action('elementor/init', [$this, 'init_elementor']);
        }
    }

    /**
     * Registers the Custom_Ajax_Select control.
     *
     * @param Controls_Manager $controls_manager
     */
    public function register_controls($controls_manager)
    {
        // Include the control class file (if separated, but here it's inline)

        // Register the control instance
        $controls_manager->register(new \CustomControls\Control_Ajax_Select());
    }


    /**
     * Initialize Elementor and register widgets and scripts.
     */
    public function init_elementor()
    {
         require_once(__DIR__ . '/Controllers/class.streamit-ajax-select.php');
        add_action('elementor/widgets/register', [$this, 'register_new_widgets']);

        // Include widget files.
        $this->include_widgets();
        add_action('elementor/elements/categories_registered', [$this, 'register_streamit_elementor_category']);
    }

    public function register_streamit_elementor_category($elements_manager)
    {
        $elements_manager->add_category(
            'streamit',
            [
                'title' => __('Streamit Widgets', 'streamit'),
                'icon' => 'fa fa-plug',
            ]
        );
    }
    public function include_widgets()
    {
        // Include your widget files.
        require_once(__DIR__ . '/Elements/Button/widget.php');
        require_once(__DIR__ . '/Elements/Main-Card-Slider/widget.php');
        require_once(__DIR__ . '/Elements/Genre-Tag-Card/widget.php');
        require_once(__DIR__ . '/Elements/Person-Card/widget.php');
        require_once(__DIR__ . '/Elements/Top-Ten-Slider/widget.php');
        require_once(__DIR__ . '/Elements/Vertical-Thumbnail-Banner/widget.php');
        require_once(__DIR__ . '/Elements/Main-Banner/widget.php');
        require_once(__DIR__ . '/Elements/Simple-Banner/widget.php');
        require_once(__DIR__ . '/Elements/Tv-Show-Season/widget.php');
        require_once(__DIR__ . '/Elements/Tv-Show-Tab/widget.php');
        require_once(__DIR__ . '/Elements/Product-Banner/widget.php');
        require_once(__DIR__ . '/Elements/Category_Box/widget.php');
        require_once(__DIR__ . '/Elements/Category_Slider/widget.php');
        require_once(__DIR__ . '/Elements/Product-Slider/widget.php');
        require_once(__DIR__ . '/Elements/Continue-Watch/widget.php');
        require_once(__DIR__ . '/Elements/Pricing-Plan/widget.php');
        require_once(__DIR__ . '/Elements/Timer/widget.php');
        require_once(__DIR__ . '/Elements/Accordion/widget.php');
        require_once(__DIR__ . '/Elements/Section-Title/widget.php');
        require_once(__DIR__ . '/Elements/Blog/widget.php');
        require_once(__DIR__ . '/Elements/Blog_Slider/widget.php');

    }

    /**
     * Register  widgets.
     *
     * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
     */
    public function register_new_widgets($widgets_manager)
    {
        // Register Button widget.
        $widgets_manager->register(new ST_Buttton());

        // Register Slider widget.
        $widgets_manager->register(new ST_Slider());

        // Register Terms widget.
        $widgets_manager->register(new ST_Terms());

        // Register Person widget.
        $widgets_manager->register(new ST_Person());

        // Top ten Widet.
        $widgets_manager->register(new ST_Top_Ten());

        //Vertical Thubnail Banner
        $widgets_manager->register(new ST_VTBenner());

        //Main Banner
        $widgets_manager->register(new ST_Main_Banner());

        //Simple Banner
        $widgets_manager->register(new ST_SBanner());

        //TvShow Season
        $widgets_manager->register(new ST_TvShow_Season());

        //TvShow Tab
        $widgets_manager->register(new ST_TvShow_Tab());

        //Product Banner
        $widgets_manager->register(new ST_Product_Banner());

        //category Box
        $widgets_manager->register(new ST_Category_Box());

        //category slider
        $widgets_manager->register(new ST_Category_Slider());

        // Products Slider
        $widgets_manager->register(new ST_Products_Slider());

        //User Watchlist
        $widgets_manager->register(new ST_Continue_Watching());

        //Pricing Plan
        $widgets_manager->register(new ST_Pricing_Plan());

        //Timer
        $widgets_manager->register(new ST_Timer());

        //Accordion
        $widgets_manager->register(new ST_Accordion());

        //Title
        $widgets_manager->register(new ST_Section_Title());

        // Blog
        $widgets_manager->register(new ST_Blog());

        // Blog Slider
        $widgets_manager->register(new ST_Blog_Slider());

        
    }

}

// Instantiate the class.
new streamit_elementor();

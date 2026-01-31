<?php
/**
 * Product Loop Start
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/loop-start.php.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     3.3.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Streamit Shop Grid Configuration
 * Determines the product layout and grid structure based on user preferences or theme defaults
 */

// Get global theme options
global $streamit_options;

// Step 1: Determine the view layout (list or grid)
// --------------------------------------------

// Check for user preference cookie first
$preferred_view = isset($_COOKIE['streamit_preferred_shop_layout']) 
    ? sanitize_text_field($_COOKIE['streamit_preferred_shop_layout']) 
    : '';

// Initialize default values
$is_list_view = false;
$grid_columns = '4'; // Default column count

// User has selected a view from the UI (stored in cookie)
if (!empty($preferred_view)) {
    if ($preferred_view === '1') {
        // List view (1 column)
        $is_list_view = true;
        $grid_columns = '1';
    } elseif (in_array($preferred_view, ['2', '3', '4'], true)) {
        // Grid view with specific column count
        $grid_columns = $preferred_view;
    }
} else {
    // No user preference, fall back to theme options
    $theme_view_preference = $streamit_options['woocommerce_shop'] ?? '2';
    
    if ($theme_view_preference === '1') {
        // Theme prefers list view
        $is_list_view = true;
        $grid_columns = '1';
    } else {
        // Theme prefers grid view, determine column count from theme options
        $theme_grid_option = $streamit_options['woocommerce_shop_grid'] ?? '5';
        
        // Map theme grid options to actual column counts
        $grid_map = [
            '3' => '2', // 2 columns
            '4' => '3', // 3 columns
            '5' => '4'  // 4 columns
        ];
        
        $grid_columns = $grid_map[$theme_grid_option] ?? '4';
    }
}

// Step 2: Build CSS classes based on view configuration
// --------------------------------------------

// Base classes that apply to all product listings
$wrapper_classes = [
    'row',                  // Bootstrap grid container
    'row-col-data',         // Custom class for JS interaction
    'gy-5',                 // Bootstrap gutter
    'products',             // WooCommerce standard class
    'columns-' . $grid_columns, // WooCommerce columns indicator
    'woocommerce',          // Theme class
    'product-slick',        // Theme class
    $is_list_view ? 'view-list' : 'view-grid' // View type indicator
];

// Responsive grid classes based on view type
if ($is_list_view) {
    // List view - consistent single column across all breakpoints
    $wrapper_classes = array_merge($wrapper_classes, [
        'row-cols-1',       // Extra small devices (<576px)
        'row-cols-sm-1',    // Small devices (≥576px)
        'row-cols-md-1',    // Medium devices (≥768px)
        'row-cols-lg-1',    // Large devices (≥992px)
        'row-cols-xl-1',    // Extra large devices (≥1200px)
        'product-list'      // Additional list-specific styling
    ]);
} else {
    // Grid view - responsive column counts
    $wrapper_classes = array_merge($wrapper_classes, [
        'row-cols-2',                // 2 columns on extra small devices
        'row-cols-sm-2',             // 2 columns on small devices
        'row-cols-md-' . $grid_columns, // Selected columns on medium devices
        'row-cols-lg-' . $grid_columns, // Selected columns on large devices
        'row-cols-xl-' . $grid_columns  // Selected columns on extra large devices
    ]);
}

// Clean up classes (remove duplicates and empty values)
$wrapper_classes = array_unique(array_filter($wrapper_classes));

// Get grid ID for tab pane
$grid_id = $is_list_view ? '1' : $grid_columns;
?>

<div class="tab-content">
    <div class="tab-pane active" id="grid-<?php echo esc_attr($grid_id); ?>">
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">
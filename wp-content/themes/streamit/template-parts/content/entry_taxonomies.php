<?php
/**
 * The template for displaying post taxonomies (categories) within the loop.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Get the post categories.
$st_postcat = get_the_category();
if ($st_postcat) {
    $total_categories = count($st_postcat);

    $categories = array_map(function ($cat, $index) use ($total_categories) {
        $category_link = esc_url(get_category_link($cat->cat_ID)); 
        $category_name = esc_html($cat->name);
        $comma = ($index < $total_categories - 1) ? ',' : ''; // Add a comma if not the last item

        return sprintf(
            '<li class="css_prefix-blog-category"><a href="%s" title="%s">%s%s</a></li>', 
            $category_link, 
            $category_name, 
            $category_name,
            $comma
        );
    }, $st_postcat, array_keys($st_postcat));

    echo implode('', $categories); // Output the list items.
}

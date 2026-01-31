<?php

/**
 * Template part for displaying the header search menu
 *
 * @package streamit
 */
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;
if (($streamit_options['header_display_search'] ?? '') === 'no') return;

?>
<li class="nav-item dropdown dropdown-search-wrapper">
    <div class="search-box position-relative">
        <a href="#" class="nav-link p-0" id="st-search-drop" aria-label="<?php esc_attr_e('Search', 'streamit'); ?>">
            <span class="btn-icon btn-sm rounded-pill btn-action">
                <span class="btn-inner">
                    <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></circle>
                        <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </span>
            </span>
        </a>

        <!-- Dropdown menu for search input -->
        <ul id="header_search_input" class="dropdown-menu p-0 dropdown-search m-0 css_prefix-search-bar">
            <li class="p-0">
                <div class="form-group input-group mb-0">
                    <!-- Search button -->
                    <button type="submit" id="search-button" class="search-submit input-group-text border-0" aria-label="<?php esc_attr_e('Submit search', 'streamit'); ?>">
                        <svg class="icon-15" width="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></circle>
                            <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </button>

                    <?php $search_label = isset($streamit_options['header_search_text']) && !empty($streamit_options['header_search_text']) ? esc_attr($streamit_options['header_search_text']) : esc_attr__('Search...', 'streamit'); ?>

                    <!-- Search input field -->
                    <?php if (class_exists('Streamit')): ?>
                        <input type="text" value="" id="search-query" class="form-control border-0" placeholder="<?php echo esc_attr_e($search_label); ?>">
                    <?php else: ?>
                        <form id="searchForm" method="GET">
                            <input type="text" id="searchQuery" class="form-control border-0" name="s" placeholder="<?php echo esc_attr_e($search_label); ?>" required />
                        </form>
                    <?php endif; ?>
                </div>
            </li>
        </ul>

        <!-- Section for search results -->
        <div class="search_result_section"></div>
    </div>
</li>
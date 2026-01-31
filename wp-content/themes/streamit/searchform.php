<?php

/**
 * The template for displaying all pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

$unique_id = esc_html(uniqid('search-form-'));
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : ''; ?>
<form method="get" class="search-form search__form" action="<?php echo esc_url(home_url('/')); ?>">
    <div class="form-search input-group">
        <input 
            type="text" 
            class="form-control border-end-0" 
            id="search-input-<?php echo esc_attr($unique_id); ?>" 
            placeholder="<?php esc_attr_e('Search', 'streamit'); ?>" 
            aria-label="<?php esc_attr_e('Search', 'streamit'); ?>"
            value="<?php echo esc_attr($search); ?>" 
            name="s">

        <button class="btn search-submit" type="submit" id="button-addon2">
            <?php echo st_get_icon('search_normal' , [ 'aria-hidden' => 'true']); ?>
            <span class="screen-reader-text">
                <?php echo esc_html_x('Search', 'submit button', 'streamit'); ?>
            </span>
        </button>
        
    </div>
</form>

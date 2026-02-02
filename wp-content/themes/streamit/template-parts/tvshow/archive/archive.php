<?php

/**
 * The template for displaying archive pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;

$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', 'بارگذاری بیشتر');
$loading_text = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__('Loading...', 'streamit'));

$display_loader = isset($streamit_options['streamit_display_loader']) && 'yes' === $streamit_options['streamit_display_loader'];
$loader_gif_url  = isset($streamit_options['streamit_loader_gif']['url']) ? esc_url($streamit_options['streamit_loader_gif']['url']) : '';

?>

<div class="container-fluid">
    <div class="archive-page-container">

        <div class="archive-page-item-1">
            <!-- filter off canvas -->
            <div class="offcanvas offcanvas-start filter-offcanvas" tabindex="-1" id="filteroffcanvas" aria-labelledby="filteroffcanvasLabel">
                <div class="offcanvas-header gap-3">
                    <h5 class="m-0 filter-offcanvas-title">
                        <span class="d-flex align-items-center gap-1">
                            <?php echo esc_html__('filters', 'streamit'); ?>
                            <span class="count filter-count"><?php echo esc_html__('0', 'streamit'); ?></span>
                        </span>
                    </h5>
                    <div class="flex-shrink-0 d-lg-none">
                        <button type="button" class="btn-close p-0 m-0 align-middle" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    </div>
                </div>
                <div class="offcanvas-body">
                    <form class="m-0">
                        <?php if ( function_exists('streamit_get_terms') ) : ?>
                            <div class="filter-widget filter-widget-genres">
                                <h5 class="filter-wiget-title"><?php echo esc_html__('Genres', 'streamit'); ?></h5>
                                <?php
                                    $genre_taxonomy = 'tvshow_genre';
                                    $per_page_genre = 6;
                                    $initial = streamit_get_terms([
                                    'paged'    => 1,
                                    'per_page' => $per_page_genre,
                                    'taxonomy' => [ $genre_taxonomy ],
                                    'orderby'  => 'term_id',
                                    'order'    => 'ASC',
                                    ]);
                                    $initial_items = (!is_wp_error($initial) && !empty($initial->results)) ? $initial->results : [];
                                ?>
                                <div class="filter-widget-inner">
                                    <div id="genre-list-container"
                                        class="genre-scrollbox"
                                        data-page="2"
                                        data-taxonomy="<?php echo esc_attr($genre_taxonomy); ?>"
                                        data-per-page="<?php echo esc_attr($per_page_genre); ?>">
                                    <div class="filter-list" id="genre-list">
                                        <?php foreach ($initial_items as $term) :
                                        $id_obj   = 0;
                                        if ( method_exists($term, 'get_term_id') ) {
                                            $id_obj = (int) $term->get_term_id();
                                        } elseif ( isset($term->term_id) ) {
                                            $id_obj = (int) $term->term_id;
                                        }
                                        $id   = $id_obj;
                                        $name = method_exists($term,'get_term_name') ? $term->get_term_name() : (string)($term->name ?? '');
                                        $slug = method_exists($term,'get_term_slug') ? $term->get_term_slug() : sanitize_title($name);
                                        ?>
                                        <div class="form-check">
                                          <input class="form-check-input streamit-filter" type="checkbox"
                                                 value="<?php echo esc_attr($slug); ?>" id="genre-<?php echo esc_attr($id); ?>"
                                                 name="genres[]">
                                          <label class="form-check-label" for="genre-<?php echo esc_attr($id); ?>">
                                            <span class="filter-text"><?php echo esc_html($name); ?></span>
                                          </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="genre-loader" style="display:none;" class="text-primary mt-1">
                                        <span><?php echo esc_html__('Loading…', 'streamit'); ?></span>
                                    </div>

                                    <div id="genre-sentinel"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="filter-widget filter-widget-access">
                            <h5 class="filter-wiget-title"><?php echo esc_html__('By Access', 'streamit'); ?></h5>
                            <div class="filter-widget-inner">
                                <div class="filter-list">
                                    <div class="form-check">
                                        <input class="form-check-input streamit-filter" type="radio" name="access_type" id="free" value="free">
                                        <label class="form-check-label" for="free">
                                            <span class="d-flex align-items-center gap-2">
                                                <span><?php echo esc_html__('Free', 'streamit'); ?></span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="form-check premium-filter">
                                        <input class="form-check-input streamit-filter" type="radio" name="access_type" id="premium" value="plan">
                                        <label class="form-check-label premium-filter-label" for="premium">
                                            <span class="d-flex align-items-center gap-2">
                                                <i class="icon-premium"></i>
                                                <span><?php echo esc_html__('Premium', 'streamit'); ?></span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input streamit-filter" type="radio" name="access_type" id="rent" value="ppv">
                                        <label class="form-check-label" for="rent">
                                            <span class="d-flex align-items-center gap-2">
                                                <span><?php echo esc_html__('Rent', 'streamit'); ?></span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input streamit-filter" type="radio" name="access_type" id="premium_or_rent" value="anyone">
                                        <label class="form-check-label" for="premium_or_rent">
                                            <span class="d-flex align-items-center gap-2">
                                                <span><?php echo esc_html__('Premium or Rent', 'streamit'); ?></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if (function_exists('streamit_get_terms')) : ?>
                            <div class="filter-widget filter-widget-tags">
                                <h5 class="filter-wiget-title"><?php echo esc_html__('Popular Tags', 'streamit'); ?></h5>
                                <?php
                                $taxonomy = 'tvshow_tag';
                                // Load first 6 tags using streamit_get_terms
                                $initial_tags = streamit_get_terms([
                                    'paged' => 1,
                                    'per_page' => 6,
                                    'taxonomy' => [ $taxonomy ],
                                    'orderby' => 'term_id',
                                    'order' => 'ASC'
                                ]);
                                
                                $has_more_tags = false;
                                if (!is_wp_error($initial_tags) && !empty($initial_tags->total)) {
                                    $has_more_tags = $initial_tags->total > 6;
                                }
                                ?>
                                <div class="filter-widget-inner data-loaded" data-current-page="1">
                                    <div class="filter-list" id="movie-tags-container">
                                        <?php if (!is_wp_error($initial_tags) && !empty($initial_tags->results)) : ?>
                                            <?php foreach ($initial_tags->results as $term) : ?>
                                                <?php $term_slug = sanitize_title($term->get_term_name()); ?>
                                                <div class="form-check">
                                                    <input class="form-check-input streamit-filter" type="checkbox" value="<?php echo esc_attr($term_slug); ?>" id="tag-<?php echo esc_attr($term->get_term_id()); ?>" name="tags[]">
                                                    <label class="form-check-label" for="tag-<?php echo esc_attr($term->get_term_id()); ?>">
                                                        <span class="filter-text"><?php echo esc_html($term->get_term_name()); ?></span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($has_more_tags) : ?>
                                        <div class="mt-2" id="tags-load-more-container">
                                            <button type="button" 
                                                    class="more-btn border-0 bg-transparent text-primary p-0 font-size-14 streamit-load-more-tags"
                                                    data-page="2"
                                                    data-taxonomy="<?php echo esc_attr($taxonomy); ?>"
                                                    data-loading-text="<?php echo esc_attr__('Loading...', 'streamit'); ?>">
                                                <span class="d-flex align-items-center">
                                                    <i class="icon-plus"></i>
                                                    <span><?php echo esc_html__('More', 'streamit'); ?></span>
                                                </span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="archive-page-item-2">
            <div class="archive-page-filters-wrapper mb-4">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <div class="d-flex justify-content-sm-start justify-content-center">
                            <?php
                            $total_results = !empty($content_data->total) ? $content_data->total : 0;
                            ?>
                            <span class="fw-medium results-count streamit-results-count"
                                data-total-results="<?php echo esc_attr($content_data->total ?? 0); ?>"
                                data-per-page="10"
                                data-current-page="1">
                                <!-- JavaScript will populate this text dynamically -->
                            </span>
                        </div>
                    </div>
                    <div class="col-sm-6 mt-sm-0 mt-3">
                        <div class="archive-page-filters-action d-flex align-items-center gap-3 justify-content-sm-end justify-content-center">
                            <div class="select-wrapper">
                                <select class="form-select streamit-filter" name="sort_by" id="filter-sort-select" data-is-fullwidth="true">
                                    <option value=""><?php echo esc_html__('Sort by', 'streamit'); ?></option>
                                    <option value="title_asc"><?php echo esc_html__('A to Z', 'streamit'); ?></option>
                                    <option value="title_desc"><?php echo esc_html__('Z to A', 'streamit'); ?></option>
                                    <option value="latest"><?php echo esc_html__('Latest', 'streamit'); ?></option>
                                    <option value="date_asc"><?php echo esc_html__('Oldest', 'streamit'); ?></option>
                                    <option value="upcoming"><?php echo esc_html__('Upcoming', 'streamit'); ?></option>
                                    <option value="most_viewed"><?php echo esc_html__('Most Viewed', 'streamit'); ?></option>
                                    <option value="most_like"><?php echo esc_html__('Most Liked', 'streamit'); ?></option>
                                </select>
                            </div>
                            <button class="filter-btn d-lg-none" data-bs-toggle="offcanvas" href="#filteroffcanvas" role="button" aria-controls="filteroffcanvas">
                                <?php echo esc_html__('Filters', 'streamit'); ?>
                                <span class="count filter-count"><?php echo esc_html__('0', 'streamit'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="applied-filters-list mt-3" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <div class="flex-shrink-0">
                            <button id="streamit-clear-filters" class="filter-clearall-btn bg-transparent font-size-14 border-0 text-primary p-0"><?php echo esc_html__('Clear All', 'streamit'); ?></button>
                        </div>
                        <div class="item-list-tabs flex-grow-1">
                            <div class="left" onclick="slide('left',event)" style="display: none;">
                                <?php echo st_get_icon('arrow-left'); ?>
                            </div>
                            <ul class="list-inline m-0 p-0 custom-tab-slider gap-1 applied-filters-container">
                                <!-- Applied filters will be dynamically added here -->
                            </ul>
                            <div class="right" onclick="slide('right',event)" style="display: none;">
                                <?php echo st_get_icon('arrow-right'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            if (!empty($content_data->results)): ?>
                <div class="css_prefix-card-wrapper tvshow_cards grid-view" data-options="yes" data-can-beloaded="1">
                    <!-- Start loader overlay -->
                    <?php if ($display_loader && $loader_gif_url) : ?>
                        <div id="archive-loader" class="archive-loader-overlay">
                            <div id="archive-loading-center">
                                <img src="<?php echo esc_url($loader_gif_url); ?>" alt="<?php esc_attr_e('Loading...', 'streamit'); ?>" style="max-width: 80px; max-height: 80px;">
                            </div>
                        </div>
                    <?php endif; ?>
                    <!-- End loader overlay -->
                    <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                        <?php foreach ($content_data->results as $st_data) : ?>
                            <?php streamit_get_template('tvshow/archive/archive_loop.php', ['st_data' => $st_data, 'view_type' => $view_type]); ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="text-center mt-4">
                        <?php if ($content_data->maxnumpages > 1) : ?>
                            <button id="css_prefix_post_load_more"
                                class="btn btn-primary load-more-btn"
                                data-current-page="1"
                                data-total-pages="<?php echo esc_attr($content_data->maxnumpages); ?>"
                                data-post-type="tvshow"
                                data-per-page="10"
                                data-post-id=""
                                data-extra-settings=""
                                data-original-text="<?php echo esc_attr($load_more_text); ?>"
                                data-loading-text="<?php echo esc_attr($loading_text); ?>">
                                <?php echo esc_html($load_more_text); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>


            <?php else : ?>
                <p><?php esc_html_e('No TV shows found.', 'streamit'); ?> </p>
            <?php endif; ?>
        </div>
<?php

/**
 * Streamit Main Card Grid Template - Optimized
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit;
}
if (is_wp_error($post_data) || empty($post_data)) {
    return;
}
global $streamit_options;

// Column count
$col_count = !empty($settings['streamit_grid_style']) ? $settings['streamit_grid_style'] : '4';

// Badge settings
$enable_premium_badges = ($streamit_options['streamit_recommended_enable_premium_badges'] === 'yes');
$enable_upcoming_badges = ($streamit_options['streamit_recommended_enable_upcoming_badges'] === 'yes');

// View All URL (computed once)
$view_all_url = !empty($settings['st_select_content_type']) ? streamit_get_permalink($settings['st_select_content_type'], '', $post_filter) : '';
?>

<div class="streamit-card-title">
    <div class="title d-flex align-items-center justify-content-between">
        <?php if (!empty($slider_title)) : ?>
            <<?php echo esc_attr($title_tag); ?> class="title-tag">
                <?php echo esc_html($slider_title); ?>
            </<?php echo esc_attr($title_tag); ?>>
        <?php endif; ?>

        <?php if (!empty($settings['view_all_switch']) && $settings['view_all_switch'] === 'yes' && $view_all_url) : ?>
            <div class="view-all-btn">
                <a href="<?php echo esc_url($view_all_url); ?>">
                    <?php esc_html_e('View All', 'streamit'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="css_prefix-card-wrapper movie_cards grid-view" data-options="yes" data-can-beloaded="1">
    <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-<?php echo esc_attr($col_count); ?> data-listing">
        <?php if (!empty($post_data) && is_array($post_data)) : ?>
            <?php foreach ($post_data as $post) :

                if (!is_object($post) || !method_exists($post, 'get_meta') || !method_exists($post, 'get_post_type')) {
                    continue;
                }

                // Core Post Data
                $post_type  = sanitize_text_field($post->get_post_type());
                $post_id    = (int) $post->get_id();
                $post_name  = sanitize_title($post->get_post_name());
                $post_title = esc_html($post->get_post_title());
                $permalink  = esc_url(streamit_get_permalink($post_type, $post_name));
                $image_id   = $post->get_meta('thumbnail_id');
                $post_slug = $post->get_post_type();
                // Taxonomy Map
                $taxonomy_map = [
                    'movie'   => 'movie_genre',
                    'tvshow'  => 'tvshow_genre',
                    'tv_show' => 'tvshow_genre',
                    'video'   => 'video_category',
                ];
                $taxonomy   = $taxonomy_map[$post_type] ?? 'video_category';

                // Get Terms (max 2)
                $term_ids   = streamit_get_term_relationships($post_id, $taxonomy);
                $terms_data = [];
                if (!empty($term_ids)) {
                    $terms_result = streamit_get_terms(['include' => $term_ids, 'per_page' => 2]);
                    $terms_data   = $terms_result && !is_wp_error($terms_result) ? $terms_result->results : [];
                }

                // Upcoming Check (Status + Release Date)
                $upcoming_result =  function_exists('streamit_is_upcoming') ? streamit_is_upcoming($post, $post_type) : [
                    'is_upcoming' => false,
                    'is_future_release' => false,
                    'formatted_date' => ''
                ];
                $is_upcoming = !empty($upcoming_result['is_upcoming']) && !empty($upcoming_result['is_future_release']);

                // Badges
                $badge = streamit_get_access_badge_for_user($post);
                $show_badges = isset($streamit_options['streamit_recommended_enable_premium_badges']) && $streamit_options['streamit_recommended_enable_premium_badges'] === 'yes';

                // Extra Metadata
                $post_run_time = st_format_runtime($post->get_meta("_{$post_type}_run_time"));
                $post_language = $post->get_meta('_language');
                $release_date  = $post->get_meta("_{$post_type}_release_date");

                // Convert Release Date to Readable Format
                $readable_release_date = '';
                if (!empty($release_date)) {
                    $timestamp = is_numeric($release_date) ? $release_date : strtotime($release_date);
                    $readable_release_date = date_i18n(get_option('date_format'), $timestamp);
                }

                // Determine Play Link
                $is_premium = !empty($badge['is_premium_icon']);
                $is_rent    = !empty($badge['is_rent_icon']);
                $is_rented  = !empty($badge['is_rented_icon']);

                $play_link = $permalink;
                if (!$is_upcoming && ($post_slug == 'tvshow')) {
                    if ($is_rented || (!$is_premium && !$is_rent)) {
                        $play_link = esc_url(streamit_get_permalink($post_type, trailingslashit($post_name) . 'player'));
                    }
                }

            ?>
                <div class="col">
                    <div class="css_prefix-card card-hover landscape-card">
                        <div class="block-images position-relative w-100">

                            <div class="image-box w-100">
                                <a href="<?php echo $permalink; ?>" class="color-inherit image-box w-100 z-1">
                                    <?php
                                    echo streamit_render_image([
                                        'attachment_id' => $image_id,
                                        'class'         => 'img-fluid object-cover w-100 border-0',
                                        'alt'           => esc_attr($post_title),
                                        'decoding'      => 'async',
                                    ]);
                                    ?>
                                </a>

                                <?php if ($is_upcoming && $enable_upcoming_badges && function_exists('st_get_icon')) : ?>
                                    <span class="product-upcoming border-0 left-icon">
                                        <?php echo esc_html__('Coming Soon', 'streamit'); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($show_badges && !empty($badge)) :
                                    $badge_icons = [
                                        'is_premium_icon' => ['class' => 'product-premium border-0 right-icon',  'icon' => 'premium', 'title' => $badge['premium_title'] ?? ''],
                                        'is_rent_icon'    => ['class' => 'product-ppv border-0 left-icon',       'icon' => 'rent',    'title' => $badge['rent_title'] ?? ''],
                                        'is_rented_icon'  => ['class' => 'product-ppv-rented border-0 right-icon', 'icon' => 'rented',  'title' => $badge['rent_title'] ?? ''],
                                    ];
                                    foreach ($badge_icons as $key => $data) :
                                        if (!empty($badge[$key]) && function_exists('st_get_icon')) : ?>
                                            <span class="<?php echo esc_attr($data['class']); ?>"
                                                data-bs-toggle="tooltip"
                                                title="<?php echo esc_attr($data['title']); ?>">
                                                <?php echo st_get_icon($data['icon']); ?>
                                            </span>
                                <?php endif;
                                    endforeach;
                                endif; ?>
                            </div>

                            <div class="card-description with-transition">
                                <div class="position-relative w-100">

                                    <?php if (!empty($terms_data)) : ?>
                                        <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap">
                                            <?php foreach ($terms_data as $term) : ?>
                                                <li>
                                                    <a href="<?php echo esc_url(streamit_get_permalink($taxonomy, $term->get_term_slug())); ?>">
                                                        <?php echo esc_html(wp_unslash($term->get_term_name())); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <?php if (!empty($settings['show_title']) && $settings['show_title'] === 'yes') : ?>
                                        <h5 class="css_prefix-title text-capitalize line-count-1">
                                            <a href="<?php echo $permalink; ?>" class="color-inherit">
                                                <?php echo esc_html($post_title); ?>
                                            </a>
                                        </h5>
                                    <?php endif; ?>

                                    <?php if (!empty($readable_release_date) && $is_upcoming) : ?>
                                        <p class="release-date mb-2">
                                            <?php echo esc_html__('Releases on:', 'streamit') . ' ' . esc_html($readable_release_date); ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="d-flex flex-wrap align-items-center gap-3">
                                        <?php if (!empty($post_run_time)) : ?>
                                            <div class="movie-time d-flex align-items-center gap-1 flex-shrink-0">
                                                <small><?php echo esc_html($post_run_time); ?></small>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($post_language['labels']) && is_array($post_language['labels'])) :
                                            $languages_to_show = array_slice($post_language['labels'], 0, 2);
                                            $remaining = count($post_language['labels']) - 2; ?>
                                            <div class="movie-language d-flex align-items-center gap-1">
                                                <?php echo st_get_icon('translate'); ?>
                                                <small>
                                                    <?php echo esc_html(implode(', ', $languages_to_show)); ?>
                                                    <?php if ($remaining > 0) echo ' +' . intval($remaining); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                                        <?php echo do_shortcode('[streamit_watchlist_shortcode post_id="' . esc_attr($post_id) . '" post_type="' . esc_attr($post_type) . '" is_button="false"]'); ?>

                                        <div class="">
                                            <a href="<?php echo esc_url($play_link); ?>" class="btn btn-primary w-100">
                                                <?php echo isset($settings['play_now_text']) && !empty($settings['play_now_text']) ? $settings['play_now_text'] :  esc_html__('تماشا', 'streamit'); ?>
                                            </a>
                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else : ?>
            <p class="no_data_found"><?php esc_html_e('No Data Found', 'streamit'); ?></p>
        <?php endif; ?>
    </div>
</div>
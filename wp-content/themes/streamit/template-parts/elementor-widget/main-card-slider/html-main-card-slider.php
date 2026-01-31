<?php

/**
 * Streamit Main Card Slider Template (Optimized)
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
$enable_premium_badges = ($streamit_options['streamit_recommended_enable_premium_badges'] === 'yes');
$enable_upcoming_badges = ($streamit_options['streamit_recommended_enable_upcoming_badges'] === 'yes');

?>



<div class="section-spacing-bottom">
    <div class="streamit-card-title">
        <div class="title d-flex align-items-center justify-content-between">
            <?php if (!empty($slider_title)) : ?>
                <<?php echo esc_attr($title_tag); ?> class="title-tag">
                    <?php echo esc_html($slider_title); ?>
                </<?php echo esc_attr($title_tag); ?>>
            <?php endif; ?>

            <?php if (!empty($settings['view_all_switch']) && $settings['view_all_switch'] === 'yes') : ?>
                <div class="view-all-btn">
                    <a href="<?php echo esc_url(streamit_get_permalink($settings['st_select_content_type'], '', $post_filter)); ?>">
                        <?php esc_html_e('View All', 'streamit'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="css_prefx-main-card position-relative">
        <div class="css_prefix-slick-general st-skeleton"
            data-extra_settings='<?php echo wp_json_encode(!empty($settings['nav-arrow']) && $settings['nav-arrow'] === "true"); ?>'
            data-slider_settings='<?php echo wp_json_encode($slick_settings); ?>'>

            <?php if (!empty($post_data) && is_array($post_data)) : ?>

                <?php foreach ($post_data as $post) :

                    // Validate post object and methods
                    if (!is_object($post) || !method_exists($post, 'get_meta') || !method_exists($post, 'get_post_type')) {
                        continue;
                    }

                    // Core post details
                    $post_type  = sanitize_text_field($post->get_post_type());
                    $post_id    = (int) $post->get_id();
                    $post_title = esc_html($post->get_post_title());
                    $post_slug  = sanitize_title($post->get_post_name());
                    $base_permalink = esc_url(streamit_get_permalink($post_type, $post_slug));

                    // Thumbnail
                    $image_id   = $post->get_meta('_portrait_thumbmail');
                    $image_html = streamit_render_image([
                        'attachment_id' => $image_id,
                        'class'         => 'img-fluid object-cover w-100 border-0',
                        'alt'           => esc_attr($post_title),
                        'decoding'      => 'async',
                    ]);

                    // Taxonomy
                    static $taxonomy_map = [
                        'movie'   => 'movie_genre',
                        'tvshow'  => 'tvshow_genre',
                        'tv_show' => 'tvshow_genre',
                        'video'   => 'video_category',
                    ];
                    $taxonomy = $taxonomy_map[$post_type] ?? 'video_category';

                    // Terms
                    $terms_data = [];
                    $terms_ids = streamit_get_term_relationships($post_id, $taxonomy);
                    if (!empty($terms_ids)) {
                        $terms = streamit_get_terms(['include' => $terms_ids, 'per_page' => 2]);
                        if (!is_wp_error($terms)) {
                            $terms_data = $terms->results ?? [];
                        }
                    }

                    // Runtime
                    $runtime = st_format_runtime($post->get_meta("_{$post_type}_run_time"));
                    $runtime_html = !empty($runtime) ? '<small>' . esc_html($runtime) . '</small>' : '';

                    // Language
                    $language_meta = $post->get_meta('_language');
                    $language_html = '';
                    if (!empty($language_meta['labels'])) {
                        $languages = array_slice($language_meta['labels'], 0, 2);
                        $extra = count($language_meta['labels']) - 2;
                        $lang_text = esc_html(implode(', ', $languages));
                        if ($extra > 0) {
                            $lang_text .= ' +' . esc_html($extra);
                        }
                        $language_html = st_get_icon('translate') . '<small>' . $lang_text . '</small>';
                    }

                    // Upcoming & badges
                    $upcoming =  function_exists('streamit_is_upcoming') ? streamit_is_upcoming($post, $post_type) : [
                        'is_upcoming' => false,
                        'is_future_release' => false,
                        'formatted_date' => ''
                    ];
                    $is_upcoming = !empty($upcoming['is_upcoming']) && !empty($upcoming['is_future_release']);
                    $badge = streamit_get_access_badge_for_user($post);

                    // Determine badge flags
                    $is_premium = !empty($badge['is_premium_icon']);
                    $is_rent    = !empty($badge['is_rent_icon']);
                    $is_rented  = !empty($badge['is_rented_icon']);

                    // Base permalink (details page)
                    $base_permalink = esc_url(streamit_get_permalink($post_type, $post_slug));

                    // Default play link → details page
                    $play_link = $base_permalink;

                    /**
                     * Determine final Play Now link:
                     * - If upcoming → always normal permalink
                     * - If premium or rent (locked content) → normal permalink (user cannot access directly)
                     * - If rented or free (accessible) → redirect to /player
                     */
                    if (!$is_upcoming && ($post_slug == 'tvshow')) {
                        if ($is_rented || (!$is_premium && !$is_rent)) {
                            // Free content or rented content → redirect to player
                            $play_link = esc_url(streamit_get_permalink($post_type, trailingslashit($post_slug) . 'player'));
                        }
                    }

                    // Badge Renderer
                    $render_badges = static function ($badge, $is_upcoming = false) use ($enable_upcoming_badges) {
                        if ($is_upcoming && !empty($enable_upcoming_badges)) {
                            echo '<span class="product-upcoming border-0 left-icon">'
                                . esc_html__('Coming Soon', 'streamit') . '</span>';
                        }

                        if (empty($badge)) return;

                        if (!empty($badge['is_premium_icon'])) {
                            echo '<span class="product-premium border-0 right-icon" data-bs-toggle="tooltip" title="' . esc_attr($badge['premium_title']) . '">' . st_get_icon('premium') . '</span>';
                        }
                        if (!empty($badge['is_rent_icon'])) {
                            echo '<span class="product-ppv border-0 left-icon" data-bs-toggle="tooltip" title="' . esc_attr($badge['rent_title']) . '">' . st_get_icon('rent') . '</span>';
                        }
                        if (!empty($badge['is_rented_icon'])) {
                            echo '<span class="product-ppv-rented border-0 right-icon" data-bs-toggle="tooltip" title="' . esc_attr($badge['rent_title']) . '">' . st_get_icon('rented') . '</span>';
                        }
                    };
                ?>

                    <!-- Slick Item -->
                    <div class="slick-item">
                        <div class="css_prefix-card">

                            <!-- Image -->
                            <div class="block-images position-relative w-100">
                                <a href="<?php echo $base_permalink; ?>" class="color-inherit image-box w-100">
                                    <?php echo $image_html; ?>
                                    <?php if (!empty($enable_premium_badges)) $render_badges($badge, $is_upcoming); ?>
                                </a>
                            </div>

                            <!-- Description -->
                            <div class="card-description with-transition">
                                <div class="position-relative w-100">

                                    <!-- Terms -->
                                    <?php if (!empty($terms_data)) : ?>
                                        <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap">
                                            <?php foreach ($terms_data as $term) :
                                                $term_url = esc_url(streamit_get_permalink($taxonomy, $term->get_term_slug()));
                                                $term_name = esc_html(wp_unslash($term->get_term_name()));
                                            ?>
                                                <li><a href="<?php echo $term_url; ?>"><?php echo $term_name; ?></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <!-- Title -->
                                    <h5 class="css_prefix-title text-capitalize line-count-1">
                                        <a href="<?php echo $base_permalink; ?>" class="color-inherit"><?php echo $post_title; ?></a>
                                    </h5>

                                    <!-- Meta Info -->
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <?php if ($runtime_html) : ?>
                                            <div class="movie-time d-flex align-items-center gap-1 flex-shrink-0"><?php echo $runtime_html; ?></div>
                                        <?php endif; ?>

                                        <?php if ($language_html) : ?>
                                            <div class="movie-language d-flex align-items-center gap-1"><?php echo $language_html; ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Watchlist + Button -->
                                    <div class="css-prefix-play-button d-flex flex-wrap align-items-center gap-2 mt-2">
                                        <?php echo do_shortcode('[streamit_watchlist_shortcode post_id="' . esc_attr($post_id) . '" post_type="' . esc_attr($post_type) . '" is_button="false"]'); ?>

                                        <div class="">
                                            <a href="<?php echo esc_url($play_link); ?>" class="hover-card-action-btn btn btn-primary w-100">
                                                <?php echo isset($settings['play_now_text']) && !empty($settings['play_now_text']) ? $settings['play_now_text'] :  esc_html__('Play Now', 'streamit'); ?>
                                            </a>
                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>

                <?php endforeach; ?>


            <?php else : ?>

                <p class="no_data_found"><?php esc_html_e('No Data Found.', 'streamit'); ?></p>

            <?php endif; ?>

        </div>
    </div>
</div>
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

                    // Ensure valid object
                    if (!is_object($post) || !method_exists($post, 'get_meta')) {
                        continue;
                    }

                    /**
                     * Basic post information
                     */
                    $post_id    = $post->get_id();
                    $post_type  = sanitize_text_field($post->get_post_type());
                    $post_name  = esc_attr($post->get_post_name());
                    $post_slug  = sanitize_title($post->get_post_name());
                    $post_title = esc_html($post->get_post_title());
                    $permalink  = esc_url(streamit_get_permalink($post_type, $post_name));
                    $image_id   = $post->get_meta('thumbnail_id');

                    /**
                     * Determine taxonomy type and terms
                     */
                    $taxonomy_map = [
                        'movie'   => 'movie_genre',
                        'tvshow'  => 'tvshow_genre',
                        'tv_show' => 'tvshow_genre',
                        'video'   => 'video_category',
                    ];

                    $taxonomy   = $taxonomy_map[$post_type] ?? 'video_category';
                    $terms_ids  = streamit_get_term_relationships($post_id, $taxonomy);
                    $terms_data = !empty($terms_ids)
                        ? streamit_get_terms(['include' => $terms_ids, 'per_page' => 2])->results
                        : [];

                    /**
                     * Meta data
                     */
                    $post_run_time = st_format_runtime($post->get_meta("_{$post_type}_run_time"));
                    $runtime_html  = !empty($post_run_time)
                        ? '<small>' . esc_html($post_run_time) . '</small>'
                        : '';

                    $post_language = $post->get_meta('_language');
                    $language_html = '';

                    if (!empty($post_language['labels'])) {
                        $languages = array_slice($post_language['labels'], 0, 2);
                        $extra     = count($post_language['labels']) - 2;
                        $lang_text = esc_html(implode(', ', $languages));

                        if ($extra > 0) {
                            $lang_text .= ' +' . esc_html($extra);
                        }

                        $language_html = st_get_icon('translate') . '<small>' . $lang_text . '</small>';
                    }

                    /**
                     * Upcoming status and access badge
                     */
                    $upcoming     =  function_exists('streamit_is_upcoming') ? streamit_is_upcoming($post, $post_type) : [
                        'is_upcoming' => false,
                        'is_future_release' => false,
                        'formatted_date' => ''
                    ];
                    $is_upcoming  = $upcoming['is_upcoming'] && $upcoming['is_future_release'];
                    $badge        = streamit_get_access_badge_for_user($post);

                    /**
                     * Determine play link based on user access and content type
                     */
                    $is_premium = !empty($badge['is_premium_icon']);
                    $is_rent    = !empty($badge['is_rent_icon']);
                    $is_rented  = !empty($badge['is_rented_icon']);

                    // Default permalink (details page)
                    $play_link = esc_url(streamit_get_permalink($post_type, $post_name));

                    // Final Play Now logic
                    if (!$is_upcoming && ($post_slug == 'tvshow')) {
                        if ($is_rented || (!$is_premium && !$is_rent && empty($badge))) {
                            // Free or rented content → direct to player page
                            $play_link = esc_url(streamit_get_permalink($post_type, trailingslashit($post_name) . 'player'));
                        }
                    }

                    /**
                     * Render image
                     */
                    $image_html = streamit_render_image([
                        'attachment_id' => $image_id,
                        'class'         => 'img-fluid object-cover w-100 border-0',
                        'alt'           => $post_title,
                        'decoding'      => 'async',
                    ]);

                    /**
                     * Build badges
                     */
                    $badge_html = '';

                    if ($is_upcoming && $enable_upcoming_badges) {
                        $badge_html .= sprintf(
                            '<span class="product-upcoming border-0 left-icon">%s</span>',
                            esc_html__('Coming Soon', 'streamit')
                        );
                    }

                    if (!empty($badge) && $enable_premium_badges) {
                        if (!empty($badge['is_premium_icon'])) {
                            $badge_html .= sprintf(
                                '<span class="product-premium border-0 right-icon" title="%s">%s</span>',
                                esc_attr($badge['premium_title']),
                                st_get_icon('premium')
                            );
                        }

                        if (!empty($badge['is_rent_icon'])) {
                            $badge_html .= sprintf(
                                '<span class="product-ppv border-0 left-icon" title="%s">%s</span>',
                                esc_attr($badge['rent_title']),
                                st_get_icon('rent')
                            );
                        }

                        if (!empty($badge['is_rented_icon'])) {
                            $badge_html .= sprintf(
                                '<span class="product-ppv-rented border-0 right-icon" title="%s">%s</span>',
                                esc_attr($badge['rent_title']),
                                st_get_icon('rented')
                            );
                        }
                    }

                    /**
                     * Watchlist button
                     */
                    $watchlist_html = do_shortcode(sprintf(
                        '[streamit_watchlist_shortcode post_id="%s" post_type="%s" is_button="false"]',
                        esc_attr($post_id),
                        esc_attr($post_type)
                    ));

                    /**
                     * Play button label
                     */
                    $user_id   = get_current_user_id();
                    $is_admin  = $user_id && user_can($user_id, 'administrator');
                    $play_text = ($is_upcoming && !$is_admin)
                        ? esc_html__('Remind Me', 'streamit')
                        : esc_html($settings['play_now_text']);
                ?>

                    <div class="slick-item">
                        <div class="css_prefix-card landscape-card">
                            <div class="block-images position-relative w-100 cursor-pointer">
                                <a href="<?php echo $permalink; ?>" class="color-inherit image-box w-100">
                                    <?php echo $image_html; ?>
                                    <?php echo $badge_html; ?>
                                </a>
                            </div>

                            <div class="card-description with-transition">
                                <div class="position-relative w-100">

                                    <?php if (!empty($terms_data)) : ?>
                                        <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap">
                                            <?php foreach ($terms_data as $term) :
                                                $term_url  = esc_url(streamit_get_permalink($taxonomy, $term->get_term_slug()));
                                                $term_name = esc_html(wp_unslash($term->get_term_name()));
                                            ?>
                                                <li><a href="<?php echo $term_url; ?>"><?php echo $term_name; ?></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <h5 class="css_prefix-title text-capitalize line-count-1">
                                        <a href="<?php echo $permalink; ?>" class="color-inherit"><?php echo $post_title; ?></a>
                                    </h5>

                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <?php if (!empty($runtime_html)) : ?>
                                            <div class="movie-time d-flex align-items-center gap-1 flex-shrink-0">
                                                <?php echo $runtime_html; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($language_html)) : ?>
                                            <div class="movie-language d-flex align-items-center gap-1">
                                                <?php echo $language_html; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="css-prefix-play-button d-flex flex-wrap align-items-center gap-2 mt-2">
                                        <?php echo $watchlist_html; ?>

                                        <div class="">
                                            <a href="<?php echo $play_link; ?>"
                                                class="hover-card-action-btn btn btn-primary w-100">
                                                <?php echo $play_text; ?>
                                            </a>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</div>
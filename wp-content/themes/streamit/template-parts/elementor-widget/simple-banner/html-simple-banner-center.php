<?php

/**
 * Streamit Simple Banner Center Template
 * 
 * @package Streamit
 */

if (!defined('ABSPATH')) exit;

?>
<div class="css_prefix-main-slider p-0 css_prefix-rtl-direction css_prefix-banner-slider-center">
    <div id="<?php echo esc_attr('home-slider-' . $id_int); ?>"
        data-rand="<?php echo esc_attr('home-slider-' . $id_int); ?>"
        class="home-slider css_prefix-simple-banner home-slider-center st-skeleton"
        data-extra_settings='<?php echo wp_json_encode(($settings['nav-arrow'] === "true" ? true : false)); ?>'
        data-slider_settings='<?php echo wp_json_encode($slick_inner_slider_settings); ?>'>

        <?php
        $dispaly_views  = $settings['show_view']    === "block";
        foreach ($st_tabs as $post) {
            $censor_rating = '';

            $post_type = $post['st_post_type'];
            $post_key  = "st_$post_type";
            $post_id   = $post[$post_key];

            $func_map = [
                'movie'  => 'streamit_get_movie',
                'tvshow' => 'streamit_get_tvshow',
                'video'  => 'streamit_get_video'
            ];

            if (isset($func_map[$post_type])) {
                $result = $func_map[$post_type]((int)$post_id);
            }

            if (empty($result)) {
                continue;
            }

            if ($post_type === 'movie' || $post_type === 'video') {
                $run_time = esc_html($result->get_meta('_' . $post_type . '_run_time'));
                if ($post_type === 'movie') {
                    $censor_rating = $result->get_meta('_movie_censor_rating');
                }
            } elseif ($post_type === 'tvshow') {
                $seasons = $result->get_meta("_seasons");
                if (!empty($seasons) && is_array($seasons)) {
                    $season_count = count($seasons);
                    $run_time = $season_count . ' ' . _n("Season", "Seasons", $season_count, 'streamit');
                } else {
                    $run_time = esc_html__('Arriving Soon', 'streamit');
                }
            }

            $bg_image = !empty($post['st_slider_image']['url'])
                ? $post['st_slider_image']['url']
                : wp_get_attachment_image_url($result->get_meta('thumbnail_id'), 'full');
            $bg_image = empty($bg_image) ? streamit_placeholder_image() : $bg_image;

            $release_date = date('F Y', strtotime($result->get_post_date()));
            $trailer_link = $result->get_meta('_name_trailer_link');
        ?>

            <div class="slick-item banner-bg <?php echo esc_attr($post_type); ?>" style="background-image:url(<?php echo esc_url($bg_image); ?>)">
                <div class="css_prefix-simple-banner-content slider-content-full-height h-100">
                    <div class="shows-content h-100 slider-content-full-height">
                        <div class="row align-items-center h-100 slider-content-full-height">
                            <div class="col-lg-7 col-md-12">
                                <h2 class="slider-text texture-text RightAnimate-three" data-animation-in="fadeInLeft" data-delay-in="0.1">
                                    <?php echo esc_html($result->get_post_title()); ?>
                                </h2>
                                <?php if ($post_type === 'video' && !empty($post['st_video'])) { ?>
                                    <div class="d-flex align-items-center gap-3" data-animation-in="fadeInUp" data-delay-in="0.3">
                                        <span class="badge bg-secondary py-2 px-3 video_view">
                                            <?php if ($dispaly_views) : ?>
                                                <?php echo st_get_icon('eye-2'); ?>

                                                <?php echo esc_html($result->get_meta('post_views_count')); ?>
                                            <?php endif; ?>
                                        </span>

                                        <?php if (!empty($run_time) && $run_time !== '0:00') { ?>
                                            <span>
                                                <?php
                                                if ($post_type !== 'tvshow') {
                                                    if (!empty($run_time)) : ?>
                                                        <?php echo st_get_icon('clock'); ?><?php echo esc_html(st_format_runtime($run_time)); ?>
                                                    <?php endif; ?>
                                                <?php } else {
                                                    echo esc_html($run_time);
                                                } ?>
                                            </span>
                                        <?php } ?>

                                        <span class="trending-year"><?php echo esc_html($release_date); ?></span>
                                    </div>
                                <?php } else { ?>
                                    <div class="d-flex flex-wrap align-items-center gap-3 RightAnimate-three">
                                        <?php if (!empty($result->get_meta('name_custom_imdb_rating'))) : ?>
                                            <div class="slider-ratting d-flex align-items-center gap-1">
                                                <?php echo st_star_rating($result->get_meta('name_custom_imdb_rating')); ?>
                                            </div>
                                            <div class="imdb-ratting d-flex align-items-center gap-1">
                                                <div class="rating-text">
                                                    <?php echo esc_html($result->get_meta('name_custom_imdb_rating')); ?>
                                                </div>
                                                <img alt="<?php echo esc_html($result->get_post_title()); ?>" src="<?php echo streamit_get_imdb_logo()['url']; ?>" loading="lazy" decoding="async">
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($censor_rating)) { ?>
                                            <span class="badge bg-secondary py-2 px-3"><?php echo esc_html($censor_rating); ?></span>
                                        <?php } ?>

                                        <?php if (!empty($run_time) && $run_time !== '0:00') { ?>
                                            <span>
                                                <?php
                                                if ($post_type !== 'tvshow') { ?>
                                                    <?php if (!empty($run_time)) : ?>
                                                        <?php echo st_get_icon('clock'); ?>
                                                        <?php echo esc_html(st_format_runtime($run_time)); ?>
                                                    <?php endif; ?>
                                                <?php } else {
                                                    echo esc_html($run_time);
                                                } ?>
                                            </span>
                                        <?php } ?>

                                        <div><?php echo st_get_icon('calendar-2'); ?> <?php echo esc_html($release_date); ?></div>
                                    </div>
                                <?php } ?>

                                <p data-animation-in="fadeInUp" data-delay-in="0.5" class="line-count-3 my-3 RightAnimate-three">
                                    <?php
                                    $st_excerpt = $result->get_post_excerpt();
                                    if (!empty($st_excerpt)) {
                                        echo esc_html(strip_tags($st_excerpt));
                                    }
                                    ?>
                                </p>

                                <?php if (!empty($settings['show_view_all_btn']) && $settings['show_view_all_btn'] === 'yes') { ?>
                                    <a href="<?php echo esc_url(streamit_get_permalink($post_type, $result->get_post_name())); ?>" class="btn btn-primary RightAnimate-three">
                                        <span class="d-flex align-items-center gap-2">
                                            <span>
                                                <?php echo esc_html($settings['play_now_text']); ?>
                                            </span>
                                            <?php echo st_get_icon('play', ['aria-hidden' => 'true']); ?>
                                        </span>
                                    </a>
                                <?php } ?>
                            </div>

                            <?php if (($post_type !== 'video') &&  $settings['show_trailer_btn'] === 'block' && !empty($trailer_link)) : ?>
                                <?php $trailer_details = streamit_get_trailer_embed($trailer_link); ?>
                                <div class="col-lg-5 col-md-12 trailor-video css_prefix-slider d-none d-lg-block">
                                    <!-- Button trigger modal -->
                                    <a href="#"
                                        class="st-load-trailer video-open playbtn" 
                                        data-trailer-type="<?php echo esc_attr($trailer_details['type']); ?>"
                                        data-trailer-url="<?php echo esc_url($trailer_details['trailer_link']); ?>"> <svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="80px" height="80px" viewBox="0 0 213.7 213.7">
                                            <polygon class="triangle" fill="none" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" points="73.5,62.5 148.5,105.8 73.5,149.1" />
                                            <circle class="circle" fill="none" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" cx="106.8" cy="106.8" r="103.3" />
                                        </svg>
                                        <?php if (!empty($args['settings']['trailer_text'])) : ?>
                                            <span class="w-trailor"><?php echo esc_html($args['settings']['trailer_text']); ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
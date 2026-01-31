<?php


/**
 * Streamit Simple Banner Normal Template
 * 
 * @package Streamit
 */

if (!defined('ABSPATH')) exit;

global $streamit_options;
$starring_title = isset($streamit_options['streamit_starring_title']) ? $streamit_options['streamit_starring_title'] : esc_html__('Starring', 'streamit');
$genres_title = isset($streamit_options['streamit_genres_title']) ? $streamit_options['streamit_genres_title'] : esc_html__('Genres', 'streamit');
$tag_title = isset($streamit_options['streamit_tag_title']) ? $streamit_options['streamit_tag_title'] : esc_html__('Tag', 'streamit');
$id_int = rand(10, 100);
$content = '';

?>
<div class="css_prefix-main-slider p-0 css_prefix-rtl-direction css_prefix-banner-slider">
    <div id="<?php echo esc_attr('home-slider-' . $id_int); ?>"
        data-rand="<?php echo esc_attr('home-slider-' . $id_int); ?>"
        class="home-slider css_prefix-simple-banner slider st-skeleton"
        data-extra_settings='<?php echo wp_json_encode($args['settings']['nav-arrow'] === "true"); ?>'
        data-slider_settings='<?php echo esc_attr(wp_json_encode($args['slick_settings'])); ?>'>

        <?php
        $display_cast   = $args['settings']['view_all_starring']  === "block";
        $dispaly_views  = $args['settings']['show_view']          === "block";
        $display_genres = $args['settings']['view_all_genres']    === "block";
        $display_tag    = $args['settings']['view_all_tag']       === "block";

        foreach ($st_tabs as $post) {
            $post_type = $post['st_post_type'];
            $post_key = "st_$post_type";
            $post_id = $post[$post_key];
            $func_map = [
                'movie'  => 'streamit_get_movie',
                'tvshow' => 'streamit_get_tvshow',
                'video'  => 'streamit_get_video'
            ];
            $result = $func_map[$post_type]((int)$post_id);
            if (empty($result)) {
                continue;
            }
            $censor_rating = '';
            if ($post_type === 'movie' || $post_type === 'video') {
                $run_time = esc_html($result->get_meta('_' . $post_type . '_run_time'));
                $censor_rating = ($post_type === 'movie') ? $result->get_meta('_movie_censor_rating') : '';
            } else if ($post_type === 'tvshow') {
                $seasons = $result->get_meta('_seasons');
                $season_count = $seasons ? count($seasons) : 0;
                $run_time = $season_count
                    ? $season_count . _n(" Season", " Seasons", $season_count, 'streamit')
                    : esc_html__('Arriving Soon', 'streamit');
            }

            $bg_image = !empty($post['st_slider_image']['url'])
                ? $post['st_slider_image']['url']
                : wp_get_attachment_image_url($result->get_meta('thumbnail_id'), 'full');
            $bg_image = empty($bg_image) ? streamit_placeholder_image() : $bg_image;


            // Casts
            $cast = '';
            if (!empty($result->get_meta('_cast'))):
                $casts = array_slice($result->get_meta('_cast'), 0, 3);
                if (!empty($casts) && is_array($casts)) {
                    foreach ($casts as $data) {
                        $cast_obj = streamit_get_person((int)$data['id']);
                        if (!empty($cast_obj) && !is_wp_error($cast_obj)) {
                            $cast .= '<a href="' . streamit_get_permalink($cast_obj->get_post_type(), $cast_obj->get_post_name()) . '">
                                <span class="text-body">' . $cast_obj->get_post_title() . '</span></a>, ';
                        }
                    }
                    $cast = rtrim($cast, ", ");
                }
            endif;

            // Genres
            $genres_list_display = '';
            if ($post_type !== 'video' && !empty(streamit_get_term_by_post($post_id, $post_type . '_genre'))) {
                $genre_list = array_slice(streamit_get_term_by_post($post_id, $post_type . '_genre'), 0, 3);
                foreach ($genre_list as $genre) {
                    $genres_list_display .= '<a href="' . streamit_get_permalink($genre->get_taxonomy(), $genre->get_term_slug()) . '">
                                                <span class="text-body">' . esc_html(wp_unslash($genre->get_term_name())) . '</span></a>, ';
                }
                $genres_list_display = rtrim($genres_list_display, ", ");
            }

            // Tags
            $tags_list_display = '';

            if (!empty(streamit_get_term_by_post($post_id, $post_type . '_tag'))) :
                $tag_list = array_slice(streamit_get_term_by_post($post_id, $post_type . '_tag'), 0, 3);
                foreach ($tag_list as $tag) {
                    $tags_list_display .= '<a href="' . streamit_get_permalink($tag->get_taxonomy(), $tag->get_term_slug()) . '">
                        <span class="text-body">' . esc_html(wp_unslash($tag->get_term_name())) . '</span></a>, ';
                }
                $tags_list_display = rtrim($tags_list_display, ", ");
            endif;

            $trailer_link = $result->get_meta('_name_trailer_link');
        ?>

            <div class="slick-item slide banner-bg <?php echo esc_attr($post_type); ?>" style="background-image:url('<?php echo esc_url($bg_image); ?>')">
                <div class="css_prefix-simple-banner-content slider-content-full-height h-100">
                    <div class="container-fluid position-relative h-100 slider-content-full-height">
                        <div class="slider-inner h-100 slider-content-full-height">
                            <div class="row align-items-center h-100 slider-content-full-height">
                                <div class="col-xl-5 col-lg-6 col-md-12">
                                    <h2 class="slider-text texture-text RightAnimate-three">
                                        <?php echo $result->get_post_title(); ?>
                                    </h2>
                                    <div class="d-flex flex-wrap align-items-center gap-3 RightAnimate-three <?php echo esc_attr($post_type) . "-meta"; ?>">
                                        <?php if (!empty($result->get_meta('name_custom_imdb_rating'))) : ?>
                                            <div class="slider-ratting d-flex align-items-center gap-1">
                                                <?php echo st_star_rating($result->get_meta('name_custom_imdb_rating')); ?>
                                            </div>
                                            <div class="imdb-ratting d-flex align-items-center gap-1">
                                                <div class="rating-text">
                                                    <?php echo esc_html($result->get_meta('name_custom_imdb_rating')); ?>
                                                </div>
                                                <img src="<?php echo streamit_get_imdb_logo()['url']; ?>" loading="lazy" decoding="async" alt="<?php esc_attr_e('imdb logo', 'streamit') ?>">
                                            </div>
                                        <?php endif; ?>
                                        <div class="d-flex align-items-center gap-3">

                                            <?php
                                            if ($post_type === 'video') {
                                                if ($dispaly_views) {
                                                    echo '<span class="badge bg-secondary py-2 px-3"> ' . st_get_icon('eye-2') . ' ' . $result->get_meta('post_views_count') . '</span>';
                                                }
                                            } else {
                                                echo '<span class="badge bg-secondary py-2 px-3">' . esc_html($censor_rating) . '</span>';
                                            }
                                            ?>

                                            <?php if (!empty($run_time) && $run_time !== '0:00') { ?>
                                                <span>
                                                    <?php
                                                    if ($post_type !== 'tvshow') { ?>
                                                        <?php echo st_get_icon('clock'); ?> <?php echo esc_html(st_format_runtime($run_time)); ?>

                                                    <?php } else {
                                                        echo esc_html($run_time);
                                                    } ?>
                                                </span>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <p class="line-count-3 RightAnimate-three">
                                        <?php
                                        $st_excerpt = $result->get_post_excerpt();
                                        if (!empty($st_excerpt)) {
                                            $st_excerpt = str_replace(["<p>", "</p>"], "", $st_excerpt);
                                            echo esc_html($st_excerpt);
                                        }
                                        ?>
                                    </p>

                                    <div class="trending-list RightAnimate-three">
                                        <?php if ($display_cast && $post_type !== 'video' && !empty($cast)) : ?>
                                            <div class="text-primary title starring">
                                                <?php echo esc_html($starring_title . ':'); ?>
                                                <span class="text-body"><?php echo wp_kses_post($cast); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($display_genres && $post_type !== 'video' && !empty($genre_list)) : ?>
                                            <div class="text-primary genres">
                                                <?php echo esc_html($genres_title . ':'); ?>
                                                <span class="text-body">
                                                    <?php echo wp_kses_post($genres_list_display); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($display_tag && !empty($tag_list)) : ?>
                                            <div class="text-primary tag">
                                                <?php echo esc_html($tag_title . ':'); ?>
                                                <span class="text-body"><?php echo wp_kses_post($tags_list_display); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($args['settings']['show_view_all_btn']) && $args['settings']['show_view_all_btn'] === 'yes') : ?>
                                        <div class="d-flex align-items-center RightAnimate-three">
                                            <a href="<?php echo esc_url(streamit_get_permalink($post_type, $result->get_post_name())); ?>" class="btn btn-primary">
                                                <span class="d-flex align-items-center gap-2">
                                                    <span>
                                                        <?php echo !empty($args['settings']['play_now_text']) ? esc_html($args['settings']['play_now_text']) : esc_html__('Play Now', 'streamit'); ?>
                                                    </span>
                                                    <?php echo st_get_icon('play', ['aria-hidden' => 'true']); ?>
                                                </span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (($post_type !== 'video') && !empty($trailer_link) && $args['settings']['show_trailer_btn'] === 'block') :

                                    $trailer_details = streamit_get_trailer_embed($trailer_link);
                                ?>
                                    <div class="col-xl-7 col-lg-6 col-md-12 trailor-video css_prefix-slider d-none d-lg-block">
                                        <!-- Button trigger modal -->
                                        <a href="#"
                                            class="st-load-trailer video-open playbtn"
                                            
                                            data-trailer-type="<?php echo esc_attr($trailer_details['type']); ?>"
                                            data-trailer-url="<?php echo esc_url($trailer_details['trailer_link']); ?>">
                                            <svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="80px" height="80px" viewBox="0 0 213.7 213.7">
                                                <polygon class='triangle' fill="none" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" points="73.5,62.5 148.5,105.8 73.5,149.1" />
                                                <circle class='circle' fill="none" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" cx="106.8" cy="106.8" r="103.3" />
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
            </div>
        <?php } ?>
    </div>
</div>
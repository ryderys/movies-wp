<?php
if (!defined('ABSPATH')) exit;

global $streamit_options;

$starring_title = isset($streamit_options['streamit_starring_title']) ? $streamit_options['streamit_starring_title'] : esc_html__('Starring', 'streamit');
$genres_title   = isset($streamit_options['streamit_genres_title']) ? $streamit_options['streamit_genres_title'] : esc_html__('Genres', 'streamit');
$tag_title      = isset($streamit_options['streamit_tag_title']) ? $streamit_options['streamit_tag_title'] : esc_html__('Tag', 'streamit');
$slider         = '';
?>
<div class="css_prefix-rtl-direction css_prefix-banner-thumb-slider st-main-slider inner-content">
    <div id="<?php echo esc_attr('home-slider-' . $id_int); ?>"
        data-rand="<?php echo esc_attr('home-slider-' . $id_int); ?>"
        class="home-slider css_prefix-main-banner st-skeleton"
        data-slider_settings='<?php echo wp_json_encode($slick_settings); ?>'
        data-slick_child_settings='<?php echo wp_json_encode($slick_child_settings); ?>'>

        <?php if (!empty($post_data) && is_array($post_data)) :
            foreach ($post_data as $slide_index => $post) {
                $run_time = $url_link = $censor_rating = $imdb_rating = $cast = $tag_list = $genre_list = $run_minutes = $genre_texonomy = '';
                $post_id  = $post->get_id();
                $post_type = $post->get_post_type();
                $is_first_slide = ( 0 === (int) $slide_index );
                $hero_loading   = $is_first_slide ? 'eager' : 'lazy';
                $hero_priority  = $is_first_slide ? 'high' : 'auto';

                $backdrop_id = (int) $post->get_meta('thumbnail_id');
                $portrait_id = (int) $post->get_meta('_portrait_thumbmail');

                // Hero backdrop (landscape) — high-res registered size, not raw original.
                $full_image = streamit_render_image([
                    'attachment_id' => $backdrop_id,
                    'class'         => 'post-img',
                    'alt'           => $post->get_post_name(),
                    'size'          => STREAMIT_CHILD_SIZE_HERO,
                    'loading'       => $hero_loading,
                    'fetchpriority' => $hero_priority,
                ]);

                // Portrait poster for thumb nav strip.
                $potrait_image = streamit_render_image([
                    'attachment_id' => $portrait_id,
                    'class'         => 'post-img',
                    'alt'           => $post->get_post_name(),
                    'size'          => STREAMIT_CHILD_SIZE_POSTER_LG,
                ]);

                // CSS background — use hero size URL instead of full original.
                $full_image_url = $backdrop_id
                    ? ( wp_get_attachment_image_url( $backdrop_id, STREAMIT_CHILD_SIZE_HERO ) ?: streamit_placeholder_image() )
                    : esc_url( streamit_placeholder_image() );

                $srcset = $full_image;

                if ($post_type == 'movie') {
                    $censor_rating = $post->get_meta('_movie_censor_rating');
                    $run_time      = $post->get_meta('_movie_run_time');
                    $run_minutes   = $post->get_meta('_movie_run_minutes');
                    $genre_texonomy = 'movie_genre';
                } elseif ($post_type == 'tvshow') {
                    $seasons      = $post->get_meta('_seasons');
                    $season_count = $seasons ? count($seasons) : 0;
                    $censor_rating = $season_count
                        ? $season_count . _n(" Season", " Seasons", $season_count, 'streamit')
                        : esc_html__('Arriving Soon', 'streamit');
                    $run_time       = date('F Y', strtotime($post->get_post_date()));
                    $genre_texonomy = 'tvshow_genre';
                } elseif ($post_type == 'video') {
                    $run_time      = $post->get_meta('_video_run_time');
                    $run_minutes   = $post->get_meta('_video_run_minutes');
                    $genre_texonomy = 'video_category';
                }

                if ($post_type !== 'video' && !empty($post->get_meta('_cast'))) {
                    $cast = '';
                    foreach (array_slice($post->get_meta('_cast') ?? [], 0, 3) as $data) {
                        if ($cast_obj = streamit_get_person((int)$data['id'])) {
                            $cast .= '<a href="' . streamit_get_permalink($cast_obj->get_post_type(), $cast_obj->get_post_name()) . '">
                                    <span class="text-body">' . $cast_obj->get_post_title() . '</span></a>, ';
                        }
                    }
                    $cast = rtrim($cast, ", ");
                }

                if (!empty(streamit_get_term_by_post($post_id, $post_type . '_tag'))) :
                    $tag_list = array_slice(streamit_get_term_by_post($post_id, $post_type . '_tag'), 0, 3) ?: [];
                    $tag_list_display = implode(', ', array_map(fn($tag) =>
                    '<a href="' . streamit_get_permalink($tag->get_taxonomy(), $tag->get_term_slug()) . '">
                            <span class="text-body">' . $tag->get_term_name() . '</span></a>', $tag_list));
                endif;

                if (!empty(streamit_get_term_by_post($post_id, $genre_texonomy))) :
                    $genre_list = array_slice(streamit_get_term_by_post($post_id, $genre_texonomy), 0, 3);
                    $genres_list_display = implode(', ', array_map(function ($genre) {
                        return '<a href="' . streamit_get_permalink($genre->get_taxonomy(), $genre->get_term_slug()) . '">
                                    <span class="text-body">' . $genre->get_term_name() . '</span></a>';
                    }, $genre_list));
                endif;

                $imdb_logo = streamit_get_imdb_logo(); ?>

                <div class="slick-item">
                    <div class="slide banner-bg <?php echo esc_attr($post_type); ?> iqonic-lazy" style="background-image:url('<?php echo esc_attr($full_image_url) ?>')">
                        <div class="container-fluid position-relative h-100 slider-content-full-height">
                            <div class="shows-content h-100 slider-content-full-height">
                                <div class="row align-items-center h-100 slider-content-full-height">
                                    <div class="col-xl-5 col-lg-6 col-md-12">
                                        <div class="css-prefix-main-banner-content">
                                            <h2 class="slider-text texture-text line-count-2 RightAnimate-two">
                                                <?php echo esc_html($post->get_post_title()); ?>
                                            </h2>

                                            <?php if ($post_type !== 'video' || $run_time !== '0:00') : ?>
                                                <div class="d-flex flex-wrap align-items-center gap-3 horizontal-thumb-description py-2 RightAnimate-three">
                                                    <?php if (!empty($censor_rating)) { ?>
                                                        <span class="badge bg-secondary slider-badge">
                                                            <?php echo esc_html($censor_rating); ?>
                                                        </span>
                                                    <?php }
                                                    if (!empty($post->get_meta('name_custom_imdb_rating'))) { ?>
                                                        <div class="slider-ratting d-flex align-items-center gap-1">
                                                            <?php echo st_star_rating($post->get_meta('name_custom_imdb_rating')); ?>
                                                        </div>
                                                        <?php if (is_array($imdb_logo) && !empty($imdb_logo['url'])) { ?>
                                                            <div class="imdb-ratting d-flex align-items-center gap-1">
                                                                <img src="<?php echo esc_url($imdb_logo['url']); ?>" loading="lazy" decoding="async" alt="<?php esc_attr_e('imdb logo', 'streamit') ?>">
                                                            </div>
                                                    <?php }
                                                    } ?>
                                                    <?php if (!empty($run_time) && $run_time !== '0:00') {  ?>
                                                        <div class="font-normal">
                                                            <?php
                                                            if ($post_type !== 'tvshow') {
                                                                if (!empty($run_time)) :
                                                                    echo st_get_icon('clock');
                                                                    echo esc_html(st_format_runtime($run_time));
                                                                endif;
                                                            } else {
                                                                echo esc_html($run_time);
                                                            } ?>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            <?php endif; ?>

                                            <p class="line-count-3 my-3 RightAnimate-two">
                                                <?php $st_excerpt = $post->get_post_excerpt();
                                                if (!empty($st_excerpt)) {
                                                    echo str_replace(['<p>', '</p>'], '', $st_excerpt);
                                                } ?>
                                            </p>

                                            <div class="gener-tag trending-list RightAnimate-three">
                                                <?php if (!empty($tag_list)) : ?>
                                                    <div class="text-primary title tag">
                                                        <span class="mx-0"> <?php echo esc_html($tag_title . ':'); ?></span>
                                                        <span class="text-body">
                                                            <?php echo wp_kses_post($tag_list_display) ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($genre_list)): ?>
                                                    <div class="text-primary title genres">
                                                        <span class="mx-0"><?php echo esc_html($genres_title . ':'); ?></span>
                                                        <span class="text-body">
                                                            <?php echo wp_kses_post($genres_list_display) ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($cast)) : ?>
                                                    <div class="text-primary title starring">
                                                        <span class="mx-0"><?php echo esc_html($starring_title . ':'); ?></span>
                                                        <span class="text-body">
                                                            <?php echo wp_kses_post($cast); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="RightAnimate-four mt-lg-0 mt-4">
                                                <a href="<?php echo streamit_get_permalink($post_type, $post->get_post_name()) ?>" class="btn btn-primary" tabindex="0">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span>
                                                            <?php echo sprintf(esc_html_x("%1s", "Vertical slider button for style one", 'streamit'), $settings['play_now_text']); ?>
                                                        </span>
                                                        <?php echo st_get_icon('play', ['class' => 'ms-2', 'aria-hidden' => 'true']); ?>
                                                    </span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php ob_start(); ?>
                <div class="slick-item">
                    <div class="block-images position-relative <?php echo esc_attr($post_type); ?>">
                        <?php echo $potrait_image; ?>
                        <div class="block-description">
                            <h6 class="css_prefix-verticle-title line-count-1"><?php echo esc_html($post->get_post_title()) ?></h6>
                            <div class="movie-time d-flex align-items-center mt-1 ">
                                <small class="movie-time-text font-normal d-flex align-items-center gap-1">
                                    <?php if (!empty($run_time) && $run_time !== '0:00') {
                                        if ($post_type !== 'tvshow') {
                                            if (!empty($run_time)) :
                                                echo st_get_icon('clock');
                                                echo esc_html(st_format_runtime($run_time));
                                            endif;
                                        } else {
                                            echo esc_html($run_time);
                                        }
                                    } ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
        <?php $slider .= ob_get_clean();
            }
        endif; ?>
    </div>
    <div class="horizontal_thumb_slider">
        <div id="<?php echo esc_attr('banner-thumb-slider-nav-' . $id_int); ?>" data-rand="<?php echo esc_attr('banner-thumb-slider-nav-' . $id_int); ?>" class="banner-thumb-slider-nav d-flex align-items-center st-skeleton">
            <?php echo $slider; ?>
        </div>
    </div>
</div>

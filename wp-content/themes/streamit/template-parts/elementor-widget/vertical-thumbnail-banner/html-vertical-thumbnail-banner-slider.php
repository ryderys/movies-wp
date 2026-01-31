<?php
if (!defined('ABSPATH')) exit;
?>
<div class="css_prefix-vertical-thumbnail-banner">
    <?php
    $nav_slider = $slider = '';

    foreach ($results as $post) {
        $post_id    = $post->get_id();
        $post_type  = $post->get_post_type();
        $post_slug  = $post->get_post_name();
        $post_title = $post->get_post_title();
        $image_id   = $post->get_meta('thumbnail_id');

        // Determine run time / season info
        if ($post_type === 'movie' || $post_type === 'video') {
            $run_time = esc_html($post->get_meta('_' . $post_type . '_run_time'));
        } elseif ($post_type === 'tvshow') {
            $seasons = $post->get_meta('_seasons');
            $run_time = (!empty($seasons) && is_array($seasons))
                ? count($seasons) . _n(' Season', ' Seasons', count($seasons), 'streamit')
                : esc_html__('Arriving Soon', 'streamit');
        } else {
            $run_time = '';
        }

        // Taxonomy mapping
        $taxonomy = [
            'movie'   => 'movie_genre',
            'tvshow'  => 'tvshow_genre',
            'tv_show' => 'tvshow_genre',
            'video'   => 'video_category'
        ][$post_type] ?? 'video_category';

        // Get terms
        $terms_ids  = streamit_get_term_relationships($post_id, $taxonomy);
        $terms_data = !empty($terms_ids) ? streamit_get_terms(['include' => $terms_ids])->results : [];

        $imdb_rating = $post->get_meta('name_custom_imdb_rating');
        $imdb_logo   = streamit_get_imdb_logo();

        // --- Main banner slide ---
        ob_start(); ?>
        <div class="slick-item slick-bg <?php echo esc_attr($post_type); ?>">
            <div class="vertical-banner-info">
                <a href="<?php echo esc_url(streamit_get_permalink($post_type, $post_slug)); ?>">
                    <?php echo streamit_render_image([
                        'attachment_id' => $image_id,
                        'class'         => 'img-fluid vertical-banner-bg-image w-100',
                        'alt'           => esc_attr($post_title),
                        'decoding'      => 'async',
                    ]); ?>
                </a>

                <div class="vertical-banner-info-desc">
                    <?php if (!empty($terms_data)) : ?>
                        <div class="genres slider-fade-in-right RightAnimate">
                            <ul class="p-0 mb-2 list-inline d-flex flex-wrap align-items-center justify-content-lg-start justify-content-center genres-list movie-space-action">
                                <?php foreach ($terms_data as $term) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(streamit_get_permalink($taxonomy, $term->get_term_slug())); ?>" class="fw-semibold">
                                             <?php echo esc_html(wp_unslash($term->get_term_name())); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <h2 class="m-0 title RightAnimate-two line-count-2"><?php echo esc_html($post_title); ?></h2>

                    <div class="d-flex flex-wrap align-items-center justify-content-lg-start justify-content-center gap-3 horizontal-thumb-description py-2 RightAnimate-three">
                        <?php if (!empty($imdb_rating)) : ?>
                            <div class="slider-ratting d-flex align-items-center gap-1">
                                <?php echo st_star_rating($imdb_rating); ?>
                            </div>
                            <div class="imdb-ratting d-flex align-items-center gap-1">
                                <span class="rating-text"><?php echo esc_html($imdb_rating); ?></span>
                                <?php if (!empty($imdb_logo['url'])) : ?>
                                    <div class="imdb-logo">
                                        <img src="<?php echo esc_url($imdb_logo['url']); ?>" loading="lazy" decoding="async" alt="<?php esc_attr_e('imdb logo', 'streamit'); ?>">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($run_time) && $run_time !== '0:00' && $run_time !== 'Arriving Soon') : ?>
                            <div class="font-normal">
                                <?php echo st_get_icon('clock') . esc_html(st_format_runtime($run_time)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="description mt-2 mb-0 RightAnimate-three line-count-3">
                        <?php echo wp_kses_post($post->get_post_content()); ?>
                    </div>

                    <div class="btn-radius mt-3 RightAnimate-four">
                        <a href="<?php echo esc_url(streamit_get_permalink($post_type, $post_slug)); ?>" class="btn btn-primary">
                            <span class="d-flex align-items-center justify-content-center gap-2">
                                <span><?php echo esc_html($args['settings']['st_play_now_text']); ?></span>
                                <span><?php echo st_get_icon('play'); ?></span>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $nav_slider .= ob_get_clean();

        // --- Thumbnail slide ---
        ob_start();
        $thumb_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : streamit_placeholder_image();
        ?>
        <div class="slick-item">
            <div class="vertical-thumb-card position-relative">
                <a href="<?php echo esc_url(streamit_get_permalink($post_type, $post_slug)); ?>">
                    <img src="<?php echo esc_url($thumb_url); ?>" class="img-fluid w-100" alt="<?php echo esc_attr($post_title); ?>">
                </a>
                <div class="vertical-thumb-card-desc">
                    <h5 class="verticle-title line-count-2"><?php echo esc_html($post_title); ?></h5>
                    <?php if (!empty($run_time) && $run_time !== '0:00' && $run_time !== 'Arriving Soon') : ?>
                        <div class="movie-time my-2">
                            <span class="movie-time-text small font-normal d-flex align-items-center gap-1">
                                <?php echo st_get_icon('clock') . esc_html(st_format_runtime($run_time)); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php
        $slider .= ob_get_clean();
    }
    ?>
    <div class="position-relative vertical-thumbnail-banner">
        <div class="vertical-thumbnail-banner-inner">
            <div id="<?php echo esc_attr('vertical-banner-content-' . $id_int); ?>" data-rand="<?php echo esc_attr('vertical-banner-content-' . $id_int); ?>" class="vertical-banner-content st-skeleton">
                <?php echo $nav_slider; ?>
            </div>
            <div class="vertical-banner-thumb-wrapper">
                <div id="<?php echo esc_attr('vertical-banner-thumb-' . $id_int); ?>" data-rand="<?php echo esc_attr('vertical-banner-thumb-' . $id_int); ?>" class="vertical-banner-thumb st-skeleton">
                    <?php echo $slider; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (!defined('ABSPATH')) exit; ?>

<div class="css_prefix-tvshow-season-parent css_prefix-rtl-direction">

    <div class="css_prefix-tvshow-season st-skeleton"
        data-slider_settings='<?php echo esc_attr(wp_json_encode($slick_settings)); ?>'
        data-extra_settings='<?php echo esc_attr(wp_json_encode($settings['nav-arrow'] === "true")); ?>'>

        <?php
        $rank       = 1;
        $all_posts  = $post_data;
        foreach ($post_data as $post) :
            $post_id       = $post->get_id();
            $post_type     = $post->get_post_type();
            $post_name     = $post->get_post_name();
            $post_title    = $post->get_post_title();
            $post_excerpt  = $post->get_post_excerpt();
            $year          = date_i18n('F Y', strtotime($post->get_post_date()));
            $thumbnail_id  = $post->get_meta('thumbnail_id');
            $full_image    = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'full') : streamit_placeholder_image();

            // Seasons
            $seasons         = (array) $post->get_meta("_seasons");
            $season_display  = !empty($seasons)
                ? sprintf('%d %s', count($seasons), _n("Season", "Seasons", count($seasons), 'streamit'))
                : esc_html__('Arriving Soon', 'streamit');
        ?>
            <div class="slick-item" data-post="<?php echo esc_attr($post_id); ?>">
                <div class="css_prefix-thumb-items css_prefix-tvshow home-slider tvshow-<?php echo esc_attr($post_id); ?>" style="background-image:url('<?php echo esc_url($full_image); ?>')">
                    <div class="thumbnail-list-wrapper">
                        <div class="css_prefix-tvshow-details">

                            <?php if (!empty($args['settings']['trending_top_img']['url'])) :
                                $alt = !empty($args['settings']['trending_top_img']['alt']) ? esc_attr($args['settings']['trending_top_img']['alt']) : 'Image';
                            ?>
                                <div class="series d-flex align-items-center gap-2 mb-3 RightAnimate">
                                    <?php
                                    echo wp_get_attachment_image(
                                        attachment_url_to_postid($args['settings']['trending_top_img']['url']),
                                        'full',
                                        false,
                                        ['class' => 'img-fluid', 'alt' => $alt, 'decoding' => 'async']
                                    );
                                    ?>
                                    <span class="text-gold"><?php printf(esc_html__('#%d in Series Today', 'streamit'), $rank); ?></span>
                                </div>
                            <?php endif; ?>

                            <h2 class="slider-text texture-text line-count-2 RightAnimate-two"><?php echo esc_html($post_title); ?></h2>

                            <p class="season-excerpt line-count-3 mb-3 RightAnimate-three"><?php echo esc_html($post_excerpt); ?></p>

                            <div class="css_prefix-tvshow-detail-meta text-detail d-flex align-items-center gap-5 RightAnimate-four">
                                <span class="trending-year"><?php echo esc_html($year); ?></span>
                                <span class="season_date"><?php echo esc_html($season_display); ?></span>
                            </div>

                            <div class="RightAnimate-four mt-5">
                                <a class="btn btn-primary" href="<?php echo esc_url(streamit_get_permalink($post_type, $post_name)); ?>">
                                    <span class="d-flex align-items-center gap-2">
                                        <span><?php echo esc_html($args['settings']['play_now_text']); ?></span>
                                        <i aria-hidden="true" class="icon-play-button ms-2"></i>
                                    </span>
                                </a>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        <?php
            $rank++;
        endforeach;
        ?>
    </div>

    <div class="css_prefix-wrap-details">
        <?php if (empty($all_posts)) return;

        $first_tvshow = $all_posts[0];
        $first_showseasons = (array) $first_tvshow->get_meta('_seasons');
        ?>

        <div class="episodes-tab">
            <div class="trending-info align-items-center w-100 animated fadeIn">
                <div class="all-episodes">
                    <h5 class="trending-text mt-0 mb-2"><?php esc_html_e("All Episodes", 'streamit'); ?></h5>
                </div>

                <div class="nav nav-tabs">
                    <div class="nav-tabs-inner" data-first_tvshow_id="<?php echo esc_attr($first_tvshow->get_id()); ?>">
                        <?php foreach ($first_showseasons as $index => $val) : ?>
                            <a class="nav-item nav-link css_prefix-episodes-meta tvshow-<?php echo esc_attr($first_tvshow->get_id()); ?> <?php echo $index === 0 ? 'active' : ''; ?>"
                                data-tvshow-id="<?php echo esc_attr($first_tvshow->get_id()); ?>"
                                data-season="<?php echo esc_attr($index); ?>"
                                data-ajax_loaded="0">
                                <?php echo isset($val['name']) ? esc_html($val['name']) : ''; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="season-content">
                    <?php
                    if (!empty($first_showseasons)) :
                        foreach ($first_showseasons as $season_index => $season) :
                            if (empty($season['episodes'])) continue;

                            $episodes = isset($season['episodes']) && !empty($season['episodes']) ? streamit_get_episodes([
                                'orderby' => 'post__in',
                                'include'  => $season['episodes'],
                                'paged'    => -1,
                                'per_page' => -1
                            ])->results : [];
                    ?>
                            <div class="season-episodes-details">
                                <?php
                                foreach (array_slice($episodes, 0, 5) as $index => $episode) :
                                    $image_id = $episode->get_meta('thumbnail_id');
                                    $episode_run_time = $episode->get_meta('_episode_run_time');
                                ?>
                                    <div class="episodes-info episode-<?php echo esc_attr($episode->get_id()); ?> tvshow-<?php echo esc_attr($first_tvshow->get_id()); ?>-season-<?php echo esc_attr($season_index + 1); ?>">
                                        <div class="episode-img">
                                            <a href="<?php echo esc_url(streamit_get_permalink($episode->get_post_type(), $episode->get_post_name())); ?>" class="color-inherit">
                                                <?php
                                                echo streamit_render_image([
                                                    'attachment_id' => $image_id,
                                                    'class'         => 'post-img',
                                                    'alt'           => esc_attr($episode->get_post_title()),
                                                    'decoding'      => 'async',
                                                ]);
                                                ?>
                                            </a>
                                        </div>

                                        <div class="episodes-meta">
                                            <div class="episode-name">
                                                <a href="<?php echo esc_url(streamit_get_permalink($episode->get_post_type(), $episode->get_post_name())); ?>" class="color-inherit">
                                                    <?php echo esc_html($episode->get_post_title()); ?>
                                                </a>
                                            </div>

                                            <?php if (!empty($episode_run_time) && $episode_run_time !== '0:00') : ?>
                                                <div class="episode-time mt-2">
                                                    <?php echo wp_kses_post(st_get_icon('clock')) . ' ' . esc_html(st_format_runtime($episode_run_time)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (count($episodes) > 5) : ?>
                                    <div class="view-all-btn-parent">
                                        <div class="view-all-btn tvshow-<?php echo esc_attr($first_tvshow->get_id()); ?>-season-<?php echo esc_attr($season['name']); ?>">
                                            <div class="d-flex align-items-center justify-content-center" data-episode_card_text="<?php echo esc_attr($args['settings']['episode_card_text']); ?>">
                                                <a href="<?php echo esc_url(streamit_get_permalink($first_tvshow->get_post_type(), $first_tvshow->get_post_name())); ?>" class="season-btn btn btn-sm btn-primary">
                                                    <?php echo esc_html($args['settings']['episode_card_text']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php break; // only display first season's episodes 
                            ?>
                    <?php endforeach;
                    else :
                        echo '<div class="season-episodes-details">' . esc_html__('No Season Found', 'streamit') . '</div>';
                    endif;
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
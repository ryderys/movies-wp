<?php

/**
 * The template for displaying single pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

defined('ABSPATH') || exit;

global $streamit_options;

$enable_premium_badges = ($streamit_options['streamit_recommended_enable_premium_badges'] === 'yes') ? 'yes' : 'no';
$badge = $enable_premium_badges ? streamit_get_access_badge_for_user($content_data) : null;
?>
<div class="rate-review-details">
    <div class="container-fluid">
        <div class="row gy-4">
            <?php if (!empty($content_data)) : ?>
                <div class="col-md-4 col-xl-3">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="css_prefix-card">
                                <div class="block-images position-relative w-100">
                                    <div class="image-box w-100">
                                        <?php $st_image_url = !empty(wp_get_attachment_image_url($content_data->get_meta('thumbnail_id'))) ? wp_get_attachment_image_url($content_data->get_meta('thumbnail_id'), 'full') : streamit_placeholder_image(); ?>
                                        <a href="<?php echo esc_url(streamit_get_permalink($content_data->get_post_type(), $content_data->get_post_name())); ?>">
                                            <img src="<?php echo esc_url($st_image_url); ?>" alt="<?php echo esc_attr($content_data->get_post_title()); ?>" class="img-fluid object-cover w-100 d-block border-0"> </a>

                                        <?php
                                        if (!empty($badge) && $enable_premium_badges === 'yes') : ?>
                                            <?php if ($badge['is_premium_icon'] && function_exists('st_get_icon')) : ?>
                                                <span class="product-premium border-0 right-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo esc_attr($badge['premium_title']); ?>">
                                                    <?php echo st_get_icon('premium'); ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if ($badge['is_rent_icon'] && function_exists('st_get_icon')) : ?>
                                                <span class="product-ppv border-0 left-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo esc_attr($badge['rent_title']); ?>">
                                                    <?php echo st_get_icon('rent'); ?>
                                                </span>
                                            <?php endif; ?>

                                        <?php endif; ?>

                                    </div>
                                    <div class="card-description with-transition">
                                        <?php
                                        $genre_texonmy = ['movie' => 'movie_genre', 'video' => 'video_category', 'tvshow' => 'tvshow_genre'];
                                        $terms_ids     = streamit_get_term_relationships($content_data->get_id(), $genre_texonmy[$content_data->get_post_type()]);
                                        if (!empty($terms_ids)) : ?>
                                            <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap">
                                                <?php
                                                $terms_data = !empty($terms_ids) ? streamit_get_terms(['include' => $terms_ids])->results : [];
                                                foreach ($terms_data as $st_term) : ?>
                                                    <li>
                                                        <a href="<?php echo esc_url(streamit_get_permalink($st_term->get_taxonomy(), $st_term->get_term_slug())); ?>"><?php echo esc_html($st_term->get_term_name()) ?></a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <?php if (!empty($content_data->get_post_name())) : ?>
                                            <h5 class="css_prefix-title text-capitalize line-count-1">
                                                <a href="<?php echo esc_url(streamit_get_permalink($content_data->get_post_type(), $content_data->get_post_name())); ?>" class="color-inherit" tabindex="0"><?php echo esc_html($content_data->get_post_title()); ?> </a>
                                            </h5>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="ratting-card">
                                <?php
                                $average_rating = (float) $content_data->get_meta('comment_average_rating');
                                $formate_rating = number_format($average_rating, 2);
                                $max_stars = 5;
                                ?>

                                <div class="d-flex flex-wrap align-items-center gap-4 mb-4">
                                    <h2 class="m-0"><?php echo esc_html($formate_rating); ?></h2>
                                    <div class="data">
                                        <ul class="list-inline m-0 p-0 d-flex align-items-center gap-1">
                                            <?php
                                            for ($i = 1; $i <= $max_stars; $i++):
                                                if ($i <= $average_rating):
                                                    echo '<li class="text-warning"> ' . st_get_icon('star-fill') . '</li>';
                                                elseif ($i - 1 < $average_rating && $average_rating < $i):
                                                    echo '<li class="text-warning"> ' . st_get_icon('star-half') . '</li>';
                                                else:
                                                    echo '<li class="text-warning">' . st_get_icon('star-icon') . '</li>';
                                                endif;
                                            endfor;
                                            ?>
                                        </ul>
                                        <p class="m-0"><?php esc_html_e('Based on individual rating', 'streamit'); ?></p>
                                    </div>
                                </div>
                                <?php
                                // Fetch the rating data
                                $rating_average = streamit_get_post_rating_progress($content_data->get_id(), $content_data->get_post_type());
                                // Initialize an array to store rating data for each level
                                $ratings_data = [
                                    5 => ['rating' => 5, 'review_count' => 0, 'percentage' => 0],
                                    4 => ['rating' => 4, 'review_count' => 0, 'percentage' => 0],
                                    3 => ['rating' => 3, 'review_count' => 0, 'percentage' => 0],
                                    2 => ['rating' => 2, 'review_count' => 0, 'percentage' => 0],
                                    1 => ['rating' => 1, 'review_count' => 0, 'percentage' => 0],
                                ];

                                // Override default values with actual data if available
                                if (!empty($rating_average)) {
                                    foreach ($rating_average as $rating) {
                                        $star = (int) $rating['rating'];
                                        $ratings_data[$star]['review_count'] = (int) $rating['review_count'];
                                        $ratings_data[$star]['percentage']   = (float) $rating['percentage'];
                                    }
                                }

                                // Loop through each rating level from 5 to 1 and display
                                foreach ($ratings_data as $data) {
                                    $star_rating  = $data['rating'];
                                    $review_count = $data['review_count'];
                                    $percentage   = $data['percentage'];
                                ?>

                                    <div class="row align-items-center g-3">
                                        <div class="col-xl-2 col-lg-2 col-md-3 col-sm-1 col-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="h6 mb-0"><?php echo esc_html($star_rating); ?></span>
                                                <?php st_get_icon('star-fill', ['class' => 'text-warning']); ?>
                                            </div>
                                        </div>
                                        <div class="col-xl-7 col-lg-8 col-md-7 col-sm-9 col-7">
                                            <div class="progress w-100 progress-ratings" role="progressbar" aria-label="Rating <?php echo esc_attr($star_rating); ?>" aria-valuenow="<?php echo esc_attr($percentage); ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar bg-success" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-lg-2 col-md-2 col-sm-2 col-2 flex-shrink-0">
                                            <span class="text-body"><?php echo esc_html($review_count); ?></span>
                                        </div>
                                    </div>

                                <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8 col-xl-9 ">
                    <div class="mb-2 d-flex align-items-center justify-content-between">
                        <h5><?php echo sprintf(esc_html__('%s Reviews for %s', 'streamit'), esc_html($content_data->get_meta('streamit_comment_count')), esc_html($content_data->get_post_title())); ?></h5>
                    </div>
                    <div class="review-list-inner comments-section data-listing">
                        <?php $comments = streamit_get_comments(['comment_post_ID' => $content_data->get_id(), 'post_type' => [$content_data->get_post_type()], 'per_page' => 5]);
                        if (!empty($comments->results)) :
                            foreach ($comments->results as $comment) :
                                st_comment_html_details($comment);
                            endforeach;
                            if ($comments->maxnumpages > 1):
                                echo st_get_load_more_button($comments->maxnumpages, esc_html('comment'), 1, esc_html__('Load More', 'streamit'), esc_html__('Loding', 'streamit'), '', '', ['post_type' => $content_data->get_post_type(), 'comment_post_ID' => $content_data->get_id()]);
                            endif;
                        else: ?>
                            <?php esc_html_e('Not Rated Yet', 'streamit'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else : ?>
                <?php esc_html_e('No Post Found', 'streamit'); ?>
            <?php endif; ?>

        </div>
    </div>
</div>
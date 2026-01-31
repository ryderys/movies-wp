<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

?>
<div class="section-spacing-bottom">
    <div class="streamit-genres-slider-title">
        <div class="title d-flex align-items-center justify-content-between">
            <?php if (!empty($slider_title)) : ?>
                <<?php echo esc_html($title_tag); ?> class="title-tag">
                    <?php echo esc_html($slider_title); ?>
                </<?php echo esc_html($title_tag); ?>>
            <?php endif; ?>
        </div>
    </div>

    <div class="css_prefix-slick-general st-skeleton css_prefix-product-cat-slick css_prefix-rtl-direction"
        data-extra_settings="<?php echo esc_attr(json_encode($settings['nav-arrow'] === "true" ? true : false)); ?>"
        data-slider_settings="<?php echo esc_attr(json_encode($slick_settings)); ?>">

        <?php if (!empty($args['post_content'])) : ?>
            <?php foreach ($args['post_content'] as $post) :
                $post_data              = $post['post_data'];
                $watched_time           = $post['watched_time'];
                $watched_total_time     = $post['watched_total_time'];
                $watched_time_percentage = $post['watched_time_percentage'];
                $thumbnail_id           = $post_data->get_meta('thumbnail_id');
            ?>
                <div class="slick-item">
                    <div class="position-relative continue-watching-card">
                        <!-- Button to remove from list -->
                        <button
                            class=" continue_watch_empty trash-continue-watching"
                            data-post-type="<?php echo esc_attr($post_data->get_post_type()); ?>"
                            data-id="<?php echo esc_attr($post_data->get_id()); ?>"
                            data-bs-toggle="tooltip"
                            data-bs-title="<?php esc_attr_e('Remove from list', 'streamit'); ?>"
                            data-bs-placement="left" tabindex="0">
                            <?php echo st_get_icon('cross'); ?>
                        </button>

                        <!-- Image Box -->
                        <div class="img-box">
                            <a href="<?php echo esc_url(streamit_get_permalink($post_data->get_post_type(), $post_data->get_post_name())); ?>" tabindex="0">
                                <?php
                                if (!empty($thumbnail_id)) {
                                    echo wp_get_attachment_image($thumbnail_id, "full", false, array(
                                        "class" => "img-fluid",
                                        "alt" => esc_attr($post_data->get_post_title()),
                                        "decoding" => "async"
                                    ));
                                } else {
                                    echo '<img src="' . esc_url(streamit_placeholder_image()) .'" class="img-fluid" alt="' . esc_attr($post_data->get_post_title()) . '" decoding="async">';
                                }
                                ?>
                            </a>
                        </div>

                        <!-- Continue Watching Details -->
                        <div class="continue-watching-details">
                            <span class="left-time">
                                <?php
                                $remaining_time = round(($watched_total_time - $watched_time) / 60, 2);
                                echo esc_html($remaining_time) . ' ' . esc_html__('m Left', 'streamit');
                                ?>
                            </span>
                            <div class="d-flex align-items-center justify-content-between continue-watching-desc">
                                <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3">
                                    <li><span class="line-count-1"><?php echo esc_html($post_data->get_post_title()); ?></span></li>
                                    <li class="flex-shrink-0"><?php echo esc_html(date_i18n('M, Y', strtotime($post_data->get_post_date()))); ?></li>
                                </ul>
                                <a href="<?php echo esc_url(streamit_get_permalink($post_data->get_post_type(), $post_data->get_post_name())); ?>">
                                    <?php echo st_get_icon('play'); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <span class="css_prefix-progress"
                            style="--progress-percentage:<?php echo esc_attr($watched_time_percentage); ?>%; --right-offset: 0px;"
                            data-left-time="<?php echo esc_attr(round($watched_time / 60, 2)); ?> of <?php echo esc_attr(round($watched_total_time / 60, 2)); ?> m">
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="no_data_found"><?php esc_html_e('No Data Found', 'streamit'); ?></p>
        <?php endif; ?>
    </div>
</div>
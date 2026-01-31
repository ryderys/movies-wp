<?php
if (!defined('ABSPATH')) exit;

?>
<div class="section-spacing-bottom">

    <div class="streamit-card-title">
        <div class="title d-flex align-items-center justify-content-between">
            <?php
            if (!empty($slider_title)) : ?>
                <<?php echo esc_attr($title_tag); ?> class="title-tag">
                    <?php echo esc_html($slider_title); ?>
                </<?php echo esc_attr($title_tag); ?>>
            <?php endif;

            // Check if the "View All" button is enabled
            if (!empty($settings['view_all_switch']) && $settings['view_all_switch'] === 'yes') : ?>
                <div class="view-all-btn">
                    <a href="<?php echo esc_url(streamit_get_permalink($settings['st_select_content_type'])); ?>">
                        <?php esc_html_e('View All', 'streamit'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="css_prefix-main-slider css_prefix-blog-slider position-relative">
        <div class="css_prefix-slick-general st-skeletons"
            data-extra_settings='<?php echo wp_json_encode(!empty($settings['arrows']) && $settings['arrows'] === "yes") ? 'true' : 'false'; ?>'
            data-slider_settings='<?php echo wp_json_encode($slick_settings); ?>'>


            <?php if (!empty($slides)) : ?>
                <?php foreach ($slides as $item) : ?>
                    <div class="slick-item">
                        <a href="<?php echo esc_url($item['link']); ?>">
                            <img class="blog-img" src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['title']); ?>">

                            <?php if (!empty($show_post_title)) : ?>
                                <div class="slider-caption">
                                    <h4 class="slider-caption-title line-count-2"><?php echo esc_html($item['title']); ?></h4>
                                </div>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p><?php esc_html_e('No slider items found.', 'streamit'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
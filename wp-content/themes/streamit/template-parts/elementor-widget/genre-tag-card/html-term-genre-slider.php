<?php
if (!defined('ABSPATH')) exit;
?>
<div class="section-spacing-bottom">
    <div class="streamit-genres-slider-title">
        <div class="title d-flex align-items-center justify-content-between">

            <<?php echo $title_tag; ?> class="title-tag">
                <?php echo $slider_title; ?>
            </<?php echo $title_tag; ?>>

            <?php if (!empty($view_all_url) && !empty($view_all_text)) : ?>
                <a class="view_all" href="<?php echo $view_all_url; ?>">
                    <?php echo $view_all_text; ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="css_prefix-slick-general genres-slider st-skeleton" data-extra_settings='<?php echo wp_json_encode(($args['settings']['nav-arrow'] === "true" ? true : false)); ?>' data-slider_settings='<?php echo esc_attr(wp_json_encode($args['slick_settings'])); ?>'>
        <?php
        if (! empty($args['term_object']) && is_array($args['term_object'])) {
            foreach ($args['term_object'] as $term) {
                $image_id = $term->get_thumbnail();
        ?>
                <div class="slick-item">
                    <div class="genres-card position-relative">
                        <div class="image-box position-relative">
                            <a href="<?php echo esc_url(streamit_get_permalink($term->get_taxonomy(), $term->get_term_slug())); ?>" class="color-inherit line-count-1">
                                <?php
                                echo streamit_render_image(
                                    array(
                                        'attachment_id' => $image_id,
                                        'class' => '',
                                        'alt' => esc_attr($term->get_term_name()),
                                        'decoding' => 'async',
                                    )
                                );
                                ?>
                                <h6 class="genres-title">
                                     <?php echo esc_html(wp_unslash($term->get_term_name())); ?>
                                </h6>
                            </a>
                        </div>
                    </div>
                </div>
            <?php
            }
        } else {
            ?>
            <p class='no_data_found'> <?php esc_html_e('No Data Found', 'streamit'); ?></p>
        <?php
        }
        ?>
    </div>
</div>
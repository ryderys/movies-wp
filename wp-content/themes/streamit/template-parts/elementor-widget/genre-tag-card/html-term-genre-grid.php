<?php
if (!defined('ABSPATH')) exit;
?>
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


<div class="genres-grid gy-4 row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">
    <?php
    if (!empty($term_object) && is_array($term_object)) {
        foreach ($term_object as $term) {
            $image_id = $term->get_thumbnail(); ?>
            <div class="col">
                <div class="genres-card position-relative">
                    <div class="image-box">
                        <a href="<?php echo esc_url(streamit_get_permalink($term->get_taxonomy(), $term->get_term_slug())); ?>" class="color-inherit line-count-1">
                            <?php
                            echo streamit_render_image(
                                array(
                                    'attachment_id' => $image_id,
                                    'class' => 'post-img',
                                    'alt' => esc_attr($term->get_term_name()),
                                    'decoding' => 'async',
                                )
                            );
                            ?>
                            <span class="genres-title h6">
                                <?php echo esc_html(wp_unslash($term->get_term_name())); ?>
                            </span>
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
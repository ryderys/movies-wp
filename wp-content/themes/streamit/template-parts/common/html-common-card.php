<?php

/**
 * The template for displaying common card struture
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

defined('ABSPATH') || exit;

if (empty($st_data)) return;

$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', esc_html__('Load More', 'streamit'));
$loading_text   = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__('Loading...', 'streamit'));

// Determine if this card is being rendered within the liked content section
$is_liked_content = !empty($is_liked_content);

$portrait_image_id = 0;

if (is_object($st_data) && method_exists($st_data, 'get_meta')) {

    if($st_data->get_post_type() == 'person'){
	    $portrait_image_id = (int) $st_data->get_meta('thumbnail_id');
	}else{
	    $portrait_image_id = (int) $st_data->get_meta('_portrait_thumbmail');
	}

}

$rendered_image_html = streamit_render_image([
    'attachment_id' => $portrait_image_id,
    'class'         => 'img-fluid',
    'alt'           => esc_attr($st_data->get_post_title()),
    'size'          => 'full',
    'show_fallback' => true,
]);

?>

<div class="col">
    <div class="common_card">

        <div class="image-box w-100">
            <a href="<?php echo esc_url(streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name())) ?>" class="d-block">
                <?php echo $rendered_image_html; ?>
            </a>
        </div>

        <div class="css_prefix-detail-part">

            <?php if (!empty($st_data->get_post_title())) : ?>
                <h6 class="text-capitalize line-count-1 mb-0">
                    <a href="<?php echo esc_url(streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name())) ?>" class="color-inherit">
                        <?php echo esc_html($st_data->get_post_title()) ?>
                    </a>
                </h6>
            <?php endif; ?>

            <?php
            if ($is_liked_content) {
                echo do_shortcode('[streamit_like_shortcode class="btn-sm" post_id="' . esc_attr($st_data->get_id()) . '" post_type="' . esc_attr($st_data->get_post_type()) . '"]');
            } elseif (empty($hide_watchlist)) {
                echo do_shortcode('[streamit_watchlist_shortcode post_id="' . esc_attr($st_data->get_id()) . '" post_type="' . esc_attr($st_data->get_post_type()) . '" is_button="true"]');
            }
            ?>

        </div>
    </div>
</div>

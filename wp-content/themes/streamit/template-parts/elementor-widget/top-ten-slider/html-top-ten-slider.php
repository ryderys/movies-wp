<?php

/**
 * Streamit Top Ten Template
 * 
 * @package Streamit
 */
defined('ABSPATH') || exit;
if (is_wp_error($result) || empty($result)) {
    return;
}
global $streamit_options;

$enable_premium_badges = ($streamit_options['streamit_recommended_enable_premium_badges'] === 'yes');
?>
<div class="section-spacing-bottom">
    <div class="streamit-genres-slider-title">
        <div class="title d-flex align-items-center justify-content-between">
            <<?php echo esc_attr($settings['title_tag']); ?> class="title-tag">
                <?php echo esc_html($slider_title); ?>
            </<?php echo esc_attr($settings['title_tag']); ?>>
        </div>
    </div>

    <div class="css_prefix-slick-general st-skeleton"
        data-extra_settings='<?php echo wp_json_encode($settings['nav-arrow'] === "true"); ?>'
        data-slider_settings='<?php echo wp_json_encode($slick_settings); ?>'>

        <?php if (!empty($result) && is_array($result)) :
            $count = 0;
            foreach ($result as $data) :
                if (empty($data)) continue;
                $count++;
                $image_id = $data->get_meta('_portrait_thumbmail');
                $badge    = streamit_get_access_badge_for_user($data);
        ?>

                <div class="slick-item">
                    <div class="top-ten-card position-relative">

                        <?php if (!empty($badge) && $enable_premium_badges && function_exists('st_get_icon')) :
                            $badge_map = [
                                'is_premium_icon' => ['class' => 'product-premium border-0 right-icon', 'title_key' => 'premium_title', 'icon' => 'premium'],
                                'is_rent_icon'    => ['class' => 'product-ppv border-0 left-icon',   'title_key' => 'rent_title',    'icon' => 'rent'],
                                'is_rented_icon'  => ['class' => 'product-ppv-rented border-0 right-icon', 'title_key' => 'rent_title', 'icon' => 'rented'],
                            ];
                            foreach ($badge_map as $key => $config) :
                                if (!empty($badge[$key])) : ?>
                                    <span class="<?php echo esc_attr($config['class']); ?>"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="<?php echo esc_attr($badge[$config['title_key']]); ?>">
                                        <?php echo st_get_icon($config['icon']); ?>
                                    </span>
                        <?php endif;
                            endforeach;
                        endif; ?>

                        <div class="top-ten-iner">
                            <a class="overly-images" href="<?php echo esc_url(streamit_get_permalink($data->get_post_type(), $data->get_post_name())); ?>">
                                <?php echo streamit_render_image([
                                    'attachment_id' => $image_id,
                                    'class'         => 'img-fluid',
                                    'alt'           => esc_attr($data->get_post_title()),
                                    'decoding'      => 'async',
                                ]); ?>
                            </a>
                            <span class="top_ten_numbers texture-text"><?php echo esc_html($count); ?></span>
                        </div>
                    </div>
                </div>

            <?php endforeach;
        else : ?>
            <p class="no_data_found"><?php esc_html_e('No Data Found', 'streamit'); ?></p>
        <?php endif; ?>
    </div>
</div>
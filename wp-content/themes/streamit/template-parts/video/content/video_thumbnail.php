<?php

/**
 * The template for displaying archive thumbnail.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;
$enable_premium_badges = ($streamit_options['streamit_recommended_enable_premium_badges'] === 'yes');
$enable_upcoming_badges = ($streamit_options['streamit_recommended_enable_upcoming_badges'] === 'yes');
$upcoming_result =  function_exists('streamit_is_upcoming') ? streamit_is_upcoming($st_data, 'video') : [
    'is_upcoming' => false,
    'is_future_release' => false,
    'formatted_date' => ''
];
$is_upcoming = $upcoming_result['is_upcoming'] && $upcoming_result['is_future_release'];
$badge = streamit_get_access_badge_for_user($st_data);

// Badge renderer
$render_badges = function ($badge, $is_upcoming = false) use ($enable_upcoming_badges) {
    if ($is_upcoming && $enable_upcoming_badges) {
        echo '<span class="product-upcoming border-0 left-icon">'
        . esc_html__('Coming Soon', 'streamit') .
        '</span>';
    }

    if (empty($badge)) return;
    if (!empty($badge['is_premium_icon'])) {
        echo '<span class="product-premium border-0 right-icon" data-bs-toggle="tooltip" title="' . esc_attr($badge['premium_title']) . '">' . st_get_icon('premium') . '</span>';
    }
    if (!empty($badge['is_rent_icon'])) {
        echo '<span class="product-ppv border-0 left-icon" data-bs-toggle="tooltip" title="' . esc_attr($badge['rent_title']) . '">' . st_get_icon('rent') . '</span>';
    }
    if (!empty($badge['is_rented_icon'])) {
        echo '<span class="product-ppv-rented border-0 right-icon" data-bs-toggle="tooltip" title="' . esc_attr($badge['rent_title']) . '">' . st_get_icon('rented') . '</span>';
    }
};
?>

<div class="image-box w-100">
    <?php
    $st_image_id = $st_data->get_meta('_portrait_thumbmail');
    ?>

    <a href="<?php echo esc_url(streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name())) ?>" class="link-overlay">
        <?php
        echo streamit_render_image(
            array(
                'attachment_id' => $st_image_id,
                'class' => 'img-fluid object-cover w-100 border-0',
                'alt' => $st_data->get_post_title(),
            )
        );
        if ($enable_premium_badges) $render_badges($badge, $is_upcoming);

        ?>
    </a>

</div>
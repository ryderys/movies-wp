<?php

/**
 * The template for displaying actions
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;

$is_like_display = isset($streamit_options['streamit_display_like']) && ($streamit_options['streamit_display_like'] == 'no') ? false : true;
$is_social_share_display = isset($streamit_options['streamit_display_social_icons']) && ($streamit_options['streamit_display_social_icons'] == 'no') ? false : true;

?>
<ul class="actions-list list-inline m-0 p-0 d-flex align-items-center flex-wrap gap-3">

    <?php if ($is_like_display && is_user_logged_in()) : ?>
        <li>
            <?php echo do_shortcode('[streamit_like_shortcode post_id="' . esc_attr($st_data->get_id()) . '" post_type="' . esc_attr($st_data->get_post_type()) . '"]'); ?>
        </li>
    <?php endif;
    if ($is_social_share_display) : ?>
        <li class="position-relative share-button dropend dropdown">
            <button type="button" class="action-btn btn btn-secondary border" data-bs-toggle="modal" data-bs-target="#shareModal">
                <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php esc_attr_e('Share', 'streamit'); ?>">

                    <?php echo st_get_icon('share-2'); ?>

                </span>
            </button>
        </li>
    <?php endif; ?>
</ul>
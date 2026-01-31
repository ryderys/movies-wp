<?php

/**
 * The template for displaying share model
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

?>

<div class="modal fade" id="shareModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered share-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"><?php echo esc_html__('Share', 'streamit'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="share-media-box">
                    <div class="media-box">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_url(streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name())) ?>" target="_blank">
                            <span class="image-icon">
                                <?php echo st_get_icon('facebook'); ?>
                            </span>
                            <span class="title"><?php echo esc_html('Facebook', 'streamit'); ?></span>
                        </a>
                    </div>
                    <div class="media-box">
                        <a href="https://twitter.com/intent/tweet?url=<?php echo esc_url(streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name())) ?>" target="_blank">
                            <span class="image-icon">
                                <?php echo st_get_icon('twitter'); ?>

                            </span>
                            <span class="title"><?php echo esc_html('Twitter', 'streamit'); ?></span>
                        </a>
                    </div>
                    <div class="media-box">
                        <a href="#" target="_blank">
                            <span class="image-icon">
                                <?php echo st_get_icon('instagram'); ?>
                            </span>
                            <span class="title"><?php echo esc_html('Instagram', 'streamit'); ?></span>
                        </a>
                    </div>
                    <div class="media-box">
                        <a href="https://api.whatsapp.com/send?text=<?php echo esc_url(streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name())) ?>" target="_blank">
                            <span class="image-icon">
                                <?php echo st_get_icon('whatsapp'); ?>
                            </span>
                            <span class="title"><?php echo esc_html('Whatsapp', 'streamit'); ?></span>
                        </a>
                    </div>
                </div>
                <div class="copy-link">
                    <h6><?php echo esc_html('Copy Link ', 'streamit'); ?></h6>
                    <div class="input-group mb-0">
                        <input type="text" class="form-control copy-post-url" placeholder="Username" value="<?php echo esc_url(streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name())); ?>" aria-label="Username" aria-describedby="basic-addon1" readonly>
                        <button class="input-group-text copy-url-btn" id="basic-addon1"><?php echo st_get_icon('copy-link'); ?> </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
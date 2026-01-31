<?php 

/**
 * The template for displaying notofication list structure
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if(empty($args)) return;

if(empty($args['notification_id'])) return;

$redirect_url = isset($args['redirect_url']) && !empty($args['redirect_url']) ? $args['redirect_url'] : '';
$image_url = isset($args['image_id']) && !empty($args['image_id']) ? wp_get_attachment_image_url($args['image_id']) : streamit_placeholder_image();
$message = isset($args['message']) && !empty($args['message']) ? $args['message'] : '';
$meta_message = isset($args['meta_message']) && !empty($args['meta_message']) ? $args['meta_message'] : '';
?>

<div data-notification-id="<?php echo esc_attr($args['notification_id']); ?>" class="css_prefix_common_list d-flex align-items-center gap-3 mb-3">
    <a href="<?php echo esc_url($redirect_url) ?>" class="link-overlay">
        <img src="<?php echo esc_url($image_url) ?>" alt="<?php esc_attr_e('image', 'streamit') ?>" class="img-fluid object-cover result-image">
    </a>
    <div class="">
        <h6 class="text-capitalize line-count-2 m-0">
            <a href="<?php echo esc_url($redirect_url) ?>"  class="color-inherit notification-action-btn" data-user_id="<?php echo esc_attr(get_current_user_id()); ?>" data-notification_id="<?php echo  esc_attr($args['notification_id']);?>">
                <?php echo esc_html($message) ?>
            </a>        
        </h6>
        <small><?php echo esc_html($meta_message); ?></small>
    </div>    
</div>
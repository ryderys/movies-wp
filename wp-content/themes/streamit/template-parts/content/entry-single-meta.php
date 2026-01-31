<?php

/**
 * The template for displaying single posts meta fields.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;

?>

<div class="blog-navigation">
    <?php
    // Display the previous post link if available
    $prev_post = get_adjacent_post(false, '', false);
    if (!empty($prev_post)) : ?>
        <div class="previous-post">
            <a href="<?php echo esc_url(get_permalink($prev_post->ID)); ?>" title="<?php echo esc_attr($prev_post->post_title); ?>">
                <span class="blog-title"><?php echo esc_html($prev_post->post_title); ?></span>
                <div class="blog-arrow">
                    <?php echo st_get_icon('arrow-prev'); ?>
                    <span class="previous"><?php echo esc_html_e('Previous Post', 'streamit'); ?></span>
                </div>
            </a>
        </div>
    <?php endif; ?>

    <?php
    // Display the next post link if available
    $next_post = get_adjacent_post(false, '', true);
    if (!empty($next_post)) : ?>
        <div class="next-post">
            <a href="<?php echo esc_url(get_permalink($next_post->ID)); ?>" title="<?php echo esc_attr($next_post->post_title); ?>">
                <span class="blog-title"><?php echo esc_html($next_post->post_title); ?></span>
                <div class="blog-arrow">
                    <span class="next"><?php echo esc_html_e('Next Post', 'streamit'); ?></span>
                    <?php echo st_get_icon('arrow-next'); ?>
                </div>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
// Display comments section if enabled in the options and the post supports comments
if (!isset($streamit_options['display_comment']) || $streamit_options['display_comment'] === 'yes') {
    if (post_type_supports(get_post_type(), 'comments')) {
        comments_template();
    }
}
?>

<?php
/**
 * Template for displaying post tags within the loop.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Get post tags.
$post_tags = get_the_tags();
if ($post_tags) { ?>
    <ul class="css_prefix-blogtag">
        <?php 
        foreach ($post_tags as $post_tag) {
            // Sanitize and output each tag with a link.
            $tag_link = esc_url(get_tag_link($post_tag)); 
            $tag_name = esc_html($post_tag->name);
            ?>
            <li><a href="<?php echo $tag_link; ?>" title="<?php echo $tag_name; ?>"><?php echo $tag_name; ?></a></li>
        <?php } ?>
    </ul>
<?php } ?>

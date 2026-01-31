<?php

/**
 * The template for displaying post header within the loop.
 *
 * @package Streamit
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

global $streamit_options;
$display_image = isset($streamit_options['streamit_display_image']) ? $streamit_options['streamit_display_image'] : 'yes';

if (is_singular(get_post_type())) {
    (has_post_thumbnail()) ? the_post_thumbnail('', array('class' => 'skip-lazy')) : '';
} else {
    $post_format = get_post_format();
    if ('video' === $post_format || 'audio' === $post_format) {
        echo st_get_embed_video(get_the_ID());
    } elseif ('gallery' === $post_format) {
        echo get_post_gallery();
    } else if (has_post_thumbnail()) {
        if ($display_image === 'yes') { ?>
            <a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true">
                <?php
                $thumbnail_src = get_the_post_thumbnail_url(get_the_ID(), 'full');
                // Output the post thumbnail with lazy loading
                echo '<img src="' . esc_url($thumbnail_src) . '" alt="' . esc_attr(get_the_title()) . '" loading="lazy" />';

                ?>
            </a>
<?php }
    }
}

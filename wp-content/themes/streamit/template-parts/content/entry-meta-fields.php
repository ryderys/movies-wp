<?php

/**
 * The template for displaying post meta fields within the loop.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Get the post's creation date and author name
$post_date = get_the_date();
$author_name = get_the_author();
?>

<div class="css_prefix-blog-meta">
    <ul class="list-inline">
        <?php if (!empty($author_name)) : ?>
            <li class="posted-by">
                <span class="post-author">
                    <?php echo st_get_icon('user', ['class' => 'me-1', 'aria-hidden' => 'true']); ?>
                    <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>"
                        title="<?php esc_attr_e('View all posts by', 'streamit'); ?> <?php echo esc_attr($author_name); ?>">
                        <?php echo esc_html($author_name); ?>
                    </a>
                </span>
            </li>
        <?php endif; ?>

        <?php if (!empty($post_date)) :
            // Generate a link to the specific date archive page (optional)
            $timestamp = strtotime($post_date);
            $year = date('Y', $timestamp);
            $month = date('m', $timestamp);
            $day = date('d', $timestamp);
            $date_link = get_day_link($year, $month, $day);
        ?>
            <li class="posted-on">
                <span class="post-date">
                    <?php echo st_get_icon('calendar-2', ['class' => 'me-1', 'aria-hidden' => 'true']); ?>
                    <a href="<?php echo esc_url($date_link); ?>"
                        title="<?php esc_attr_e('View posts from', 'streamit'); ?> <?php echo esc_attr($post_date); ?>">
                        <?php echo esc_html($post_date); ?>
                    </a>
                </span>
            </li>
        <?php endif;
        // Include the template for post taxonomies.
        streamit_get_template('content/entry_taxonomies.php');
        ?>

    </ul>
</div>
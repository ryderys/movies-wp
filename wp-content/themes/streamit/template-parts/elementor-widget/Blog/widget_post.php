<?php if (!defined('ABSPATH')) exit;
$post_date = get_the_date(); ?>

<div class="col">
    <div class="css_prefix-blog-box blog-widget">

        <!-- Blog Image -->
        <?php if ($settings['hide_image'] === 'yes') : ?>
            <div class="css_prefix-blog-media">
                <?php if (has_post_thumbnail()) : ?>
                    <?php
                    // Disable the default lazy-loading behavior for this image
                    echo wp_get_attachment_image(get_post_thumbnail_id(get_the_ID()), 'full', false, ['class' => 'post-img', 'alt' => get_the_title(), 'decoding' => 'async']);
                    ?>

                <?php endif; ?>
            </div>
        <?php endif ?>

        <!-- Blog Details -->
        <div class="css_prefix-blog-detail">
            <div class="css_prefix-blog-meta">
                <ul class="list-inline">
                    <li class="posted-by">
                        <span class="post-author">
                            <?php echo st_get_icon('user',  ['aria-hidden' => 'true']); ?>
                            <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))) ?>">
                                <?php the_author() ?>
                            </a>
                        </span>
                    </li>
                    <!-- Post Date -->
                    <?php if ($settings['hide_date'] === 'yes') :
                        // Generate a link to the specific date archive page (optional)
                        $timestamp = strtotime($post_date);
                        $year = date('Y', $timestamp);
                        $month = date('m', $timestamp);
                        $day = date('d', $timestamp);
                        $date_link = get_day_link($year, $month, $day); ?>
                        <li class="posted-on">
                            <span class="post-date">
                                <?php echo st_get_icon('calendar-2',  ['aria-hidden' => 'true']); ?>
                                <a href="<?php echo esc_url($date_link); ?>" rel="bookmark">
                                    <?php echo get_the_date(); ?>
                                </a>
                            </span>
                        </li>
                    <?php endif ?>
                </ul>
            </div>
            <!-- Blog Title -->
            <div class="blog-title">
                <<?php echo esc_attr($settings['title_tag']) ?> class="entry-title">
                    <a href="<?php the_permalink(); ?>">
                        <?php the_title(); ?>
                    </a>
                </<?php echo esc_attr($settings['title_tag']) ?>>
            </div>

            <!-- Blog Excerpt -->
            <?php if ($settings['hide_content'] === 'yes') : ?>
                <p class="line-count-<?php echo esc_attr($settings['iq_content_line']) ?>"><?php echo get_the_excerpt(); ?></p>
            <?php endif ?>

            <!-- Read More Button -->
            <?php if ($settings['hide_read_more_button'] === 'yes') : ?>
                <a class="btn btn-link" href="<?php the_permalink(); ?>">
                    <span class="d-flex align-items-center justify-content-center gap-1">
                        <span><?php echo esc_html($settings['read_more_text']); ?></span>
                        <span><?php echo st_get_icon('arrow-right',  ['aria-hidden' => 'true']); ?></span>
                    </span>
                </a>
            <?php endif ?>
        </div>
    </div>
</div>
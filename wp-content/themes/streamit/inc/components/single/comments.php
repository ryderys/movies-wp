<?php

/**
 * Displays the list of comments for the current post.
 *
 * Internally this method calls `wp_list_comments()`. However, in addition to that it will render the wrapping
 * element for the list, so that must not be added manually. The method will also take care of generating the
 * necessary markup if amp-live-list should be used for comments.
 *
 * @param array $args Optional. Array of arguments. See `wp_list_comments()` documentation for a list of supported
 *                    arguments.
 */
function st_comments(array $args = array())
{ 
    $args = array_merge(
        $args,
        array(
            'style' => 'ul',
            'short_ping' => true,
        )
    );

    $amp_live_list = using_amp_live_list_comments();

    if ($amp_live_list) {
        $comment_order = get_option('comment_order');
        $comments_per_page = get_option('page_comments') ? (int) get_option('comments_per_page') : 10000;
        $poll_inverval = MINUTE_IN_SECONDS * 1000;

        ?>
        <amp-live-list id="amp-live-comments-list-<?php the_ID(); ?>" <?php echo ('asc' === $comment_order) ? ' sort="ascending" ' : ''; ?> data-poll-interval="<?php echo esc_attr($poll_inverval); ?>"
            data-max-items-per-page="<?php echo esc_attr($comments_per_page); ?>">
            <?php
    }

    ?>
        <ul class="commentlist" <?php echo esc_html($amp_live_list) ? ' items' : ''; ?>>
            <?php wp_list_comments(
                array(
                    'walker' => new Component_Walker_Comment,
                    'style' => 'ul',
                    'avatar_size' => 80,
                )
            );
            ?>
        </ul><!-- .comment-list -->
        <?php

        the_comments_navigation();

        if ($amp_live_list) {
            ?>
            <div>
                <button class="button"
                    on="tap:amp-live-comments-list-<?php the_ID(); ?>.update"><?php esc_html_e('New comment(s)', 'streamit'); ?></button>
            </div>
        </amp-live-list>
        <?php
        }
}
function using_amp_live_list_comments() : bool {
    if ( ! is_amp() ) {
        return false;
    }

    $amp_theme_support = get_theme_support( 'amp' );

    return ! empty( $amp_theme_support[0]['comments_live_list'] );
}
function is_amp() : bool {
    return function_exists( '\is_amp_endpoint' ) && \is_amp_endpoint();
}

function st_get_comment_btn()
	{
		return '<button name="submit" type="submit" id="submit" class="submit btn btn-primary" value="' . esc_attr__('Post Comment' , 'streamit') . '" >
					<span class="css_prefix-main-btn">
					<span class="text-btn">' . esc_html__('Post Comment', 'streamit') . '</span></span>
				</button>';
	}

if (!class_exists('Component_Walker_Comment')) {
    /**
     * Custom comment walker
     *
     * @users Walker_Comment
     */
    class Component_Walker_Comment extends \Walker_Comment
    {
        public function start_lvl(&$output, $depth = 0, $args = array())
        {
            $GLOBALS['comment_depth'] = $depth + 1;

            switch ($args['style']) {
                case 'div':
                    break;
                case 'ol':
                    $output .= '<li><ol class="children">' . "\n";
                    break;
                case 'ul':
                default:
                    $output .= '<li><ul class="children">' . "\n";
                    break;
            }
        }
        
        function end_el(&$output, $comment, $depth = 0, $args = array())
        {
            if (!empty($args['end-callback'])) {
                ob_start();
                call_user_func($args['end-callback'], $comment, $args, $depth);
                $output .= ob_get_clean();
                return;
            }
            if ('div' === $args['style']) {
                $output .= "</div><!-- #comment-## -->\n";
            }
        }
        public function html5_comment($comment, $depth, $args)
        {
            switch ($comment->comment_type):
                case 'pingback':
                case 'trackback':
                    if (isset($args['style']) && 'div' == $args['style']) {
                        $tag = 'div';
                        $add_below = 'comment';
                    } else {
                        $tag = 'li';
                        $add_below = 'div-comment';
                    }
        ?>
                <li <?php comment_class('css_prefix-comments-item'); ?> id="comment-<?php comment_ID(); ?>">
                    <h5 class="mt-0 mb-0">
                        <span class="css_prefix-type-date">
                        <span class="me-2"><?php echo esc_html__(comment_type() . ':', 'streamit'); ?></span>
                            <?php comment_author_link(); ?>
                        </span>
                        <?php edit_comment_link(esc_html__('(Edit)', 'streamit'), '<span class="edit-link">', '</span>'); ?>
                    </h5>
                </li>
            <?php
                    break;
                default:
                    global $post;
            ?>
                <li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
                    <article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
                        <div class="css_prefix-comments-media">
                            <div class="css_prefix-comment-wrap">
                                <div class="css_prefix-comments-photo">
                                    <?php if (0 != $args['avatar_size']) echo get_avatar($comment, $args['avatar_size']); ?>
                                </div>
                                <div class="css_prefix-comments-info">
                                    <div class="css_prefix-comment-metadata">
                                        <a href="<?php echo esc_url(get_comment_link($comment->comment_ID)); ?>">
                                            <time datetime="<?php comment_time('c'); ?>">
                                                <?php printf(wp_kses('%1$s', '1: date'), get_comment_date()); ?>
                                            </time>
                                        </a>
                                        <?php edit_comment_link(esc_html__('Edit', 'streamit'), '<span class="edit-link">', '</span>'); ?>
                                    </div>
                                    <!-- .comment-metadata -->
                                    <h4 class="title">
                                        <?php printf(wp_kses('%s ', 'streamit'), sprintf('%s', get_comment_author_link())); ?>
                                    </h4>

                                    <?php if ('0' == $comment->comment_approved) : ?>
                                        <p class="comment-awaiting-moderation"><?php echo esc_html__('Your comment is awaiting moderation.', 'streamit'); ?></p>
                                    <?php endif; ?>
                                    <div class="comment-content">
                                        <?php comment_text(); ?>
                                    </div><!-- .comment-content -->
                                    <div class="reply css_prefix-reply">
                                        <?php
                                        $args["reply_text"] = esc_html__('Reply', 'streamit');
                                        comment_reply_link(array_merge($args, array('add_below' => 'div-comment', 'depth' => $depth, 'max_depth' => $args['max_depth'])));
                                        ?>
                                    </div>
                                    <!-- .reply -->
                                </div><!-- .comment-author -->

                            </div>
                        </div>
                    </article><!-- .comment-body -->
                </li>
<?php
                    break;
            endswitch;
        }
    }
    // end of WPSE_Walker_Comment
}
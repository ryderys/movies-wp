<?php

/**
 * The template for displaying Comments
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $streamit_core_options;

// Check if the "Show TVshow Comments" option is enabled
$show_comments = isset($streamit_core_options['streamit_tvshow_display_comment']) && $streamit_core_options['streamit_tvshow_display_comment'] === 'yes';

$user_comment_id = '';
$user_comment = null;
if (is_user_logged_in()) :
    $user_comment = streamit_user_current_post_comment($st_data->get_id(), $st_data->get_post_type(), get_current_user_id());
    if ($user_comment !== null)
        $user_comment_id = $user_comment->get_id();
endif;
$comments = streamit_get_comments(['comment_post_ID' => $st_data->get_id(), 'post_type' => [$st_data->get_post_type()], 'per_page' => 3, 'exclude' => array($user_comment_id)]);
$data_id = $st_data !== null ? $st_data->get_id() : '';

if ($show_comments) : ?>
    <div class="rate-review-details">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <h5 class="main-title text-capitalize m-0"><?php echo esc_html__('Review', 'streamit'); ?></h5>
            <div class="d-flex align-items-center gap-3">
                <?php if ($user_comment === null && function_exists('streamit_user_has_stream_access') && streamit_user_has_stream_access($data_id, $st_data->get_post_type(), get_current_user_id())) : ?>
                    <div class="d-flex align-items-center gap-3">
                        <a id="openReviewButton" class="btn btn-link" data-bs-toggle="offcanvas" href="#offcanvasReview" role="button" aria-controls="offcanvasReview">
                            افزودن نظر
                        </a>
                    </div>
                <?php endif; ?>
                <?php
                if (($user_comment !== null) || !empty($comments->results)) :
                    $permalink = streamit_get_permalink($st_data->get_post_type(), $st_data->get_post_name() . '/comment');
                    echo sprintf('<a class="return-to-dashboard btn btn-link" href="%s">%s</a>', esc_url($permalink), esc_html__('View More', 'streamit'));
                endif;
                ?>
            </div>
        </div>
        <?php
        if ($user_comment !== null) :
            $user_comment_id = $user_comment->get_id();
            st_comment_html_details($user_comment, true);
        endif;
        ?>
        <div class="comments-section">
            <?php
            if (!empty($comments->results)) :
                foreach ($comments->results as $comment) :
                    st_comment_html_details($comment);
                endforeach;
            endif;
            if (($user_comment == null) && empty($comments->results)) :
                echo '<div class="card">';
                echo '<div class="card-body">';
                echo '<h5 class="m-0 text-center"> هنوز امتیازی ثبت نشده </h5>';
                echo '</div></div>';
            endif;
            ?>
        </div>
    </div>
<?php endif; ?>

<div class="offcanvas offcanvas-review offcanvas-end" tabindex="-1" id="offcanvasReview">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="offcanvasReviewLabel">
            <?php echo $user_comment !== null ? 'ویرایش نظر' : 'افزودن نظر'; ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <?php st_comment_html_form($st_data->get_id(), $st_data->get_post_type()); ?>
    </div>
</div>
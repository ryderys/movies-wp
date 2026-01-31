<?php

/**
 * The template for displaying all comments
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
$comments = streamit_get_comments(['comment_post_ID' => $st_data->get_id(), 'post_type' => [$st_data->get_post_type()], 'per_page' => 3])->results;
if (empty($comments) || !array($comments)) {
    return;
}
?>
<div class="rate-review-details">
    <div class="d-flex align-items-center gap-3 my-2 justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <h5 class="main-title text-capitalize m-0">
                <?php esc_html_e('Review', 'streamit'); ?>
            </h5>
            <a class="btn btn-link" data-bs-toggle="offcanvas" href="#offcanvasReview" role="button" aria-controls="offcanvasReview">
                <?php esc_html_e( 'Add Review', 'streamit' ) ?>
            </a>            
        </div>
        <div class="d-flex align-items-center gap-3 my-2 justify-content-between">
            <button class="btn btn-link p-0 d-flex align-items-center gap-1">
                <span><?php esc_html_e( 'Edit', 'streamit' ) ?></span>
            </button>
            <button type="button" class="btn btn-link p-0 d-flex align-items-center gap-1">
                <span><?php esc_html_e( 'Delete', 'streamit' ) ?></span>
            </button>
        </div>
    </div>
    <div class="comments-section">

    </div>
        <?php foreach ($comments as $comment):
        ?>
            <div class="review-detail rounded">                
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center justify-content-center gap-3">
                        <img src="<?php esc_url(get_avatar($comment->get_user_id())); ?>" alt="user" class="img-fluid user-img rounded-circle">
                        <div>
                            <h6 class="line-count-1 m-0"><?php echo esc_html($comment->get_comment_author()); ?></h6>
                            <p class="mb-0 mt-1 small"><?php echo esc_html(date('d M Y', strtotime($comment->get_comment_date()))); ?></p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <?php
                        echo st_star_rating(($comment->get_rating()) * 2);
                        ?>
                    </div>
                </div>
                <p class="mb-0 mt-3 pt-3 fw-medium border-top"><?php echo esc_html($comment->get_comment_content()); ?></p>
            </div>
        <?php endforeach; ?>
</div>

>


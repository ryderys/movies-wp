<?php

/**
 * The template for displaying comments
 *
 * This is the template that displays the area of the page that contains both the current comments
 * and the comment form.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (post_password_required()) {
	return;
} ?>
	<div id="comments" class="comments-area">

		<h3 class="comments-title">
			<?php
			$comment_count = get_comments_number();
			echo '<span>' . sprintf(
				esc_html(
					_n('%d Comment', '%d Comments', $comment_count, 'streamit')
				),
				esc_html($comment_count)
			) . '</span>';
			?>
		</h3>

		<?php the_comments_navigation();

		//get comments
		st_comments();

		if (!comments_open()) {
		?>
			<p class="no-comments"><?php esc_html_e('Comments are closed.', 'streamit'); ?></p>
		<?php
		}


		$args = array(
			'label_submit' => esc_html__('Post Comment', 'streamit'),
			'comment_notes_before' => esc_html__('Your email address will not be published. Required fields are marked *', 'streamit'),
			'comment_field' => '<div class="comment-form-comment">
								<textarea id="comment" class="form-control" rows="5" name="comment" placeholder="' . esc_attr__('Comment*', 'streamit') . '" required="required"></textarea>
							</div>',
			'format' => 'xhtml',
			'fields' => array(
				'author' => '<div class="row">
							 <div class="col-lg-4 mb-4">
								 <div class="comment-form-author">
									 <input id="author" class="form-control" name="author" aria-required="true" required="required" placeholder="' . esc_attr__('Name*', 'streamit') . '" />
								 </div>
							 </div>',
				'email' => '<div class="col-lg-4 mb-4">
							 <div class="comment-form-email">
								 <input id="email" class="form-control" name="email" required="required" placeholder="' . esc_attr__('Email*', 'streamit') . '" />
							 </div>
						 </div>',
				'url' => '<div class="col-lg-4 mb-4">
							 <div class="comment-form-url">
								 <input id="url" class="form-control" name="url"  placeholder="' . esc_attr__('Website', 'streamit') . '" />
							 </div>
						 </div>',
				'cookies' => '<div class="css_prefix-check mb-4">
								<label>
									<input type="checkbox" required="required" /> <span class="checkmark"></span><span>' . esc_html__("Save my name, email, and website in this browser for the next time I comment.", "streamit") . '</span>
								</label>
							</div>
							</div>',
			),
			'submit_button' => st_get_comment_btn(),
		);

		comment_form($args);

		?>
	</div>
<?php

<?php

/**
 * The template part for displaying a message that posts cannot be found.
 *
 * @package Streamit
 */

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
?>
<div class="no-results not-found">
	<div class="inside-article">
		<?php
		/**
		 * streamit_before_content hook.
		 *
		 * @since 0.1
		 *
		 * @hooked streamit_featured_page_header_inside_single - 10
		 */
		do_action('st_before_content');
		?>

		<header>
			<h3 class="entry-title mb-1"><?php _e('Nothing Found', 'streamit'); ?></h3>
		</header>

		<?php
		/**
		 * streamit_after_entry_header hook.
		 *
		 * @since 0.1
		 *
		 * @hooked streamit_post_image - 10
		 */
		?>

		<div class="entry-content">

			<?php if (is_home() && current_user_can('publish_posts')) : ?>
				<p>
					<?php
					printf(
						/* translators: 1: Admin URL */
						__('Ready to publish your first post? <a href="%1$s">Get started here</a>.', 'streamit'),
						esc_url(admin_url('post-new.php'))
					);
					?>
				</p>

			<?php elseif (is_search()) : ?>

				<p><?php _e('Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'streamit'); ?></p>
				<?php get_search_form(); ?>

			<?php else : ?>

				<p><?php _e('It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.', 'streamit'); ?></p>
				<?php get_search_form(); ?>

			<?php endif; ?>

			<div class="d-block mt-4">
				<a class="btn btn-primary" href = "<?php echo esc_url(home_url()); ?>">
					<?php esc_html_e('Back to Home' , 'streamit'); ?>
				</a>
			</div>
		</div>

		<?php
		/**
		 * streamit_after_content hook.
		 *
		 * @since 0.1
		 */
		do_action('st_after_content');
		?>
	</div>
</div>
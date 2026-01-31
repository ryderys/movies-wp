<?php

/**
 * The template for displaying posts within the loop.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

?>
<div class="col">
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<?php
		/**
		 * streamit_before_content hook.
		 *
		 * @since 0.1
		 *
		 * @hooked streamit_featured_page_header_inside_single - 10
		 */
		do_action('st_before_content', get_post_type());


		/**
		 * streamit_before_entry_header hook.
		 *
		 * @since 0.1
		 *
		 * @hooked streamit_post_image
		 */
		do_action('st_before_entry_header');
		?>
		<div class="css_prefix-blog-media">
			<?php
			/**
			 * streamit_before_entry_title hook.
			 *
			 * @since 0.1
			 */
			do_action('st_before_entry_title');

			streamit_get_template('content/entry-header.php');


			/**
			 * streamit_after_entry_title hook.
			 *
			 * @since 0.1
			 *
			 * @hooked streamit_post_meta 
			 */
			do_action('st_after_entry_title');
			?>
		</div>
		<?php

		/**
		 * streamit_after_entry_header hook.
		 *
		 * @since 0.1
		 *
		 * @hooked streamit_post_image
		 */
		do_action('st_after_entry_header');
		?>
		<div class="css_prefix-blog-detail">
			<?php
			/**
			 * streamit_after_entry_header hook.
			 *
			 * @since 0.1
			 *
			 * @hooked streamit_post_image
			 */
			do_action('st_before_entry_summary');

			//get post summery
			streamit_get_template('content/entry_summary.php');


			/**
			 * streamit_after_entry_header hook.
			 *
			 * @since 0.1
			 *
			 * @hooked streamit_post_image
			 */
			do_action('st_after_entry_summary');

			wp_link_pages(
				array(
					'before' => '<div class="page-links">' . esc_html__('Pages:', 'streamit'),
					'after' => '</div>',
				)
			);

			/**
			 * streamit_after_entry_header hook.
			 *
			 * @since 0.1
			 *
			 * @hooked streamit_post_image
			 */
			do_action('st_before_entry_footer');

			//get post footer 
			streamit_get_template('content/entry_footer.php');


			/**
			 * streamit_after_entry_header hook.
			 *
			 * @since 0.1
			 *
			 * @hooked streamit_post_image
			 */
			do_action('st_after_entry_footer');
			?>
		</div>
		<?php

		/**
		 * streamit_after_entry_content hook.
		 *
		 * @since 0.1
		 *
		 * @hooked streamit_footer_meta - 10
		 */
		do_action('st_after_entry_content');

		/**
		 * 	streamit_after_content hook.
		 *
		 * @since 0.1
		 */
		do_action('st_after_content', get_post_type());
		?>
	</article>
</div>
<?php
if (is_single()) {

	/**
	 * streamit_before_entry_single_meta hook.
	 *
	 * @since 0.1
	 *
	 * @hooked streamit_entry_meta - 10
	 */
	do_action('st_before_entry_single_meta');

	streamit_get_template('content/entry-single-meta.php');


	/**
	 * streamit_after_entry_single_meta hook.
	 *
	 * @since 0.1
	 *
	 * @hooked streamit_entry_meta - 10
	 */
	do_action('st_after_entry_single_meta');
}

<?php

/**
 * The Template for displaying product archives, including the main shop page which is a post type archive
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/archive-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.6.0
 */

defined('ABSPATH') || exit;

get_header('shop');
global $streamit_options;
$post_type = get_post_type() ?? '';

$is_sidebar = false;
if (isset($streamit_options['woocommerce_shop'])) :
	if ($streamit_options['product_sidebar_setting'] == '1' || $streamit_options['product_sidebar_setting'] == '3') :
		$is_sidebar = (bool)is_active_sidebar('streamit_product_sidebar');
	endif;
endif;

?>

<div class="page-content">
	<div id="content" class="site-content"> <?php
		streamit_get_template("breadcrumb/breadcrumb.php");
		/**
		 * Hook: woocommerce_before_main_content.
		 *
		 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
		 * @hooked woocommerce_breadcrumb - 20
		 * @hooked WC_Structured_Data::generate_website_data() - 30
		 */
		do_action('woocommerce_before_main_content'); ?>

		<div class="container-fluid">
			<div class="row <?php echo apply_filters('st_sidebar_direction', $post_type); ?>">

				<?php if ($is_sidebar) {  ?>
					<div class="col-xl-9 col-sm-12">
				<?php } else { ?>
					<div class="col-xl-12 col-sm-12">
				<?php } ?> <?php
						/**
						 * Hook: woocommerce_shop_loop_header.
						 *
						 * @since 8.6.0
						 *
						 * @hooked woocommerce_product_taxonomy_archive_header - 10
						 */
						do_action('woocommerce_shop_loop_header');

						if (woocommerce_product_loop()) {

							/**
							 * Hook: woocommerce_before_shop_loop.
							 *
							 * @hooked woocommerce_output_all_notices - 10
							 * @hooked woocommerce_result_count - 20
							 * @hooked woocommerce_catalog_ordering - 30
							 */
							do_action('woocommerce_before_shop_loop');

							woocommerce_product_loop_start();

							if (wc_get_loop_prop('total')) {
								while (have_posts()) {
									the_post();

									/**
									 * Hook: woocommerce_shop_loop.
									 */
									do_action('woocommerce_shop_loop');

									// wc_get_template_part('content', 'product');
									get_template_part('template-parts/wocommerce/entry');
								}
							}

							woocommerce_product_loop_end();

							/**
							 * Hook: woocommerce_after_shop_loop.
							 *
							 * @hooked woocommerce_pagination - 10
							 */
							do_action('woocommerce_after_shop_loop');
						} else {
							/**
							 * Hook: woocommerce_no_products_found.
							 *
							 * @hooked wc_no_products_found - 10
							 */
							do_action('woocommerce_no_products_found');
						}  ?>
					</div>

					<?php if ($is_sidebar) {  ?>
						<div class="col-xl-3 col-sm-12 css_prefix-woo-sidebar">
							<?php dynamic_sidebar('streamit_product_sidebar'); ?>
						</div>
					<?php } ?>
				</div>
			</div> <?php

			/**
			 * Hook: woocommerce_after_main_content.
			 *
			 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
			 */
			do_action('woocommerce_after_main_content'); ?>
		</div>
	</div>
	<?php

	get_footer('shop');

<?php
/**
 * Child override of Streamit main template.
 *
 * Adds opt-in TV-show single timings around header/footer and bootstrap-to-template.
 * Markup and hooks match the parent template when timings are disabled.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stc_tvshow_timing = function_exists( 'streamit_child_tvshow_timing_is_tvshow_single' )
	&& streamit_child_tvshow_timing_is_tvshow_single(
		isset( $content_type ) ? $content_type : '',
		isset( $view_type ) ? $view_type : ''
	);

if ( $stc_tvshow_timing && function_exists( 'streamit_child_tvshow_time_begin_from_request' ) ) {
	streamit_child_tvshow_time_begin_from_request( 'bootstrap_to_template' );
	if ( function_exists( 'streamit_child_tvshow_time_end' ) ) {
		streamit_child_tvshow_time_end( 'bootstrap_to_template' );
	}
}

if ( $stc_tvshow_timing && function_exists( 'streamit_child_tvshow_time_begin' ) ) {
	streamit_child_tvshow_time_begin( 'header' );
}
try {
	get_header();
} finally {
	if ( $stc_tvshow_timing && function_exists( 'streamit_child_tvshow_time_end' ) ) {
		streamit_child_tvshow_time_end( 'header' );
	}
}

?>
<div class="page-content">
	<!-- Main content area of the page -->
	<div id="content" class="site-content">
		<!-- Primary content area -->
		<div id="primary" class="content-area">
			<!-- Main site content -->
			<?php if ( $sub_template !== 'single' ) echo streamit_get_template( 'breadcrumb/breadcrumb.php', array( 'view_type' => $view_type, 'content_type' => $content_type ) ); ?>

			<main id="main" class="site-main">
				<?php

				/**
				 * 'st_before_primary_content_area' hook.
				 *
				 * @since 2.02
				 */
				do_action( 'st_before_primary_content_area', $content_type . '_' . $view_type );

				$template_path = "{$content_type}/{$sub_template}/{$view_type}.php";
				streamit_get_template( $template_path, array( 'content_data' => $content_data, 'view_type' => $view_type ) );

				/**
				 * 'st_after_primary_content_area' hook.
				 *
				 * @since 2.0
				 */
				do_action( 'st_after_primary_content_area', $content_type . '_' . $view_type );

				?>
			</main> <!-- End of the main content area -->
		</div> <!-- End of the primary content area -->
	</div> <!-- End of site content -->
</div> <!-- End of page content -->

<?php

if ( $stc_tvshow_timing && function_exists( 'streamit_child_tvshow_time_begin' ) ) {
	streamit_child_tvshow_time_begin( 'footer' );
}
try {
	get_footer();
} finally {
	if ( $stc_tvshow_timing && function_exists( 'streamit_child_tvshow_time_end' ) ) {
		streamit_child_tvshow_time_end( 'footer' );
	}
}

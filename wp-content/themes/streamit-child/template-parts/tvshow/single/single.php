<?php

// Ensure that the file is being accessed within WordPress, not directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// Check if content data is available before displaying the TV show details.
if ( ! empty( (array) $content_data ) ) :
	?>

	<div class="detail-page">
		<!-- Video Section: Display the trailer or video content -->
		<div class="video-section">
			<?php
			// Include the TV show trailer template part
			streamit_get_template( 'tvshow/content/tvshow_single_trailer.php', array( 'st_data' => $content_data ) );
			?>

			<!-- Detail Part: Display additional information about the TV show -->
			<div class="detail-part mt-md-0 mt-5">
				<?php
				// Include the genre template part
				streamit_get_template( 'tvshow/content/tvshow_single_genre.php', array( 'st_data' => $content_data ) );

				// Include the title template part
				streamit_get_template( 'tvshow/content/tvshow_single_title.php', array( 'st_data' => $content_data ) );

				// Include the description template part
				streamit_get_template( 'tvshow/content/tvshow_single_description.php', array( 'st_data' => $content_data ) );

				// Include the metalist (cast/crew) template part
				if ( function_exists( 'streamit_child_tvshow_time_begin' ) ) {
					streamit_child_tvshow_time_begin( 'detail_metalist' );
				}
				try {
					streamit_get_template( 'tvshow/single/tvshow_single_metalist.php', array( 'st_data' => $content_data ) );
				} finally {
					if ( function_exists( 'streamit_child_tvshow_time_end' ) ) {
						streamit_child_tvshow_time_end( 'detail_metalist' );
					}
				}

				// Include the language information template part
				streamit_get_template( 'tvshow/content/tvshow_language.php', array( 'st_data' => $content_data, 'is_limit' => false ) );

				// Include the actions (e.g., watch, follow) template part
				streamit_get_template( 'tvshow/single/tvshow_single_actions.php', array( 'st_data' => $content_data ) );

				?>
			</div>
		</div>

		<!-- Trailer section: omitted entirely when the show has no playable trailer. -->
		<?php
		streamit_get_template( 'tvshow/content/tvshow_single_trailer_section.php', array( 'st_data' => $content_data ) );
		?>

		<!-- Season + download: optional nested phase timings live inside these templates.
		     template_render includes those nested phases — do not sum them together. -->
		<?php
		if ( function_exists( 'streamit_child_tvshow_time_begin' ) ) {
			streamit_child_tvshow_time_begin( 'template_render' );
		}
		try {
			streamit_get_template( 'tvshow/content/tvshow_single_get_season.php', array( 'st_data' => $content_data ) );
			streamit_get_template( 'tvshow/content/tvshow_single_download_section.php', array( 'st_data' => $content_data ) );
		} finally {
			if ( function_exists( 'streamit_child_tvshow_time_end' ) ) {
				streamit_child_tvshow_time_end( 'template_render' );
			}
		}
		?>

		<!-- After Details Section: Additional content or related information -->
		<?php
		streamit_get_template( 'tvshow/single/tvshow_single_after_details.php', array( 'st_data' => $content_data ) );
		?>

	</div>

	<!-- Share Model Section: Display the share options for the TV show -->
	<?php
	streamit_get_template( 'tvshow/content/tvshow_single_share_model.php', array( 'st_data' => $content_data ) );
	?>

	<!-- Description Model Section: Display more detailed information in a modal -->
	<?php
	streamit_get_template( 'tvshow/content/tvshow_single_discription_model.php', array( 'st_data' => $content_data ) );
	?>

	<!-- PPV-Subscription Model Section: Display more detailed information in a modal -->
	<?php
	streamit_get_template( 'common/html-ppv-subscription-details-model.php', array( 'st_data' => $content_data ) );
	?>

<?php else : ?>
	<div class="container no-data-here">
		<!-- If no content data is found, display a fallback message -->
		<p class="no_data_found"><?php echo esc_html__( 'No tvshow found.', 'streamit' ); ?></p>
	</div>
	<?php
endif;

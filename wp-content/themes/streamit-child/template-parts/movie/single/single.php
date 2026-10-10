<?php
/**
 * Movie single template with an in-page download section.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

if ( ! empty( $content_data ) ) :
	?>
	<div class="detail-page">
		<div class="video-section">
			<?php
			streamit_get_template(
				'movie/content/movie_single_trailer.php',
				array( 'st_data' => $content_data )
			);
			?>

			<div class="detail-part mt-md-0 mt-5">
				<?php
				streamit_get_template( 'movie/content/movie_single_genre.php', array( 'st_data' => $content_data ) );
				streamit_get_template( 'movie/content/movie_single_title.php', array( 'st_data' => $content_data ) );
				streamit_get_template( 'movie/content/movie_single_description.php', array( 'st_data' => $content_data ) );
				streamit_get_template( 'movie/single/movie_single_metalist.php', array( 'st_data' => $content_data ) );
				streamit_get_template(
					'movie/content/movie_language.php',
					array(
						'st_data'  => $content_data,
						'is_limit' => false,
					)
				);
				streamit_get_template( 'movie/single/movie_single_actions.php', array( 'st_data' => $content_data ) );
				?>
			</div>
		</div>

		<?php
		streamit_get_template( 'movie/content/movie_single_download_section.php', array( 'st_data' => $content_data ) );
		streamit_get_template(
			'movie/single/movie_single_after_details.php',
			array(
				'st_data'   => $content_data,
				'view_type' => $view_type,
			)
		);
		?>
	</div>

	<?php
	streamit_get_template( 'movie/content/movie_single_share_model.php', array( 'st_data' => $content_data ) );
	streamit_get_template( 'movie/content/movie_single_playlist_model.php', array( 'st_data' => $content_data ) );
	streamit_get_template( 'movie/content/movie_single_discription_model.php', array( 'st_data' => $content_data ) );
	?>
<?php else : ?>
	<div class="container no-data-here">
		<p class="no_data_found"><?php echo esc_html__( 'No movie found.', 'streamit' ); ?></p>
	</div>
<?php endif; ?>

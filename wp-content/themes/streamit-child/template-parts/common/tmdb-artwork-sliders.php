<?php
/**
 * Poster and backdrop galleries under the download box.
 *
 * Uses the same slick-item / css_prefix-card shell as the recommended slider
 * so spacing and radius match. Each image links to the full attachment URL.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $st_data ) || ! is_object( $st_data ) || ! method_exists( $st_data, 'get_meta' ) ) {
	return;
}

$exclude_ids = array_filter(
	array(
		absint( $st_data->get_meta( '_portrait_thumbmail' ) ),
		absint( $st_data->get_meta( 'thumbnail_id' ) ),
	)
);

$sections = array(
	array(
		'ids'   => streamit_child_tmdb_artwork_ids( $st_data->get_meta( '_tmdb_poster_ids' ), $exclude_ids ),
		'title' => __( 'پوسترها', 'streamit' ),
		'class' => 'stc-artwork__poster',
		'size'  => defined( 'STREAMIT_CHILD_SIZE_POSTER' ) ? STREAMIT_CHILD_SIZE_POSTER : 'medium',
		'slick' => array(
			'dots'           => false,
			'slidesToShow'   => 6,
			'slidesToScroll' => 6,
			'arrows'         => true,
			'autoplay'       => false,
			'speed'          => 300,
			'infinite'       => true,
			'swipeToSlide'   => true,
			'responsive'     => array(
				array(
					'breakpoint' => 1025,
					'settings'   => array(
						'slidesToShow'   => 4,
						'slidesToScroll' => 4,
					),
				),
				array(
					'breakpoint' => 600,
					'settings'   => array(
						'slidesToShow'   => 3,
						'slidesToScroll' => 3,
					),
				),
				array(
					'breakpoint' => 450,
					'settings'   => array(
						'slidesToShow'   => 2,
						'slidesToScroll' => 2,
					),
				),
			),
		),
	),
	array(
		'ids'   => streamit_child_tmdb_artwork_ids( $st_data->get_meta( '_tmdb_backdrop_ids' ), $exclude_ids ),
		'title' => __( 'تصاویر پس‌زمینه', 'streamit' ),
		'class' => 'stc-artwork__backdrop',
		'size'  => defined( 'STREAMIT_CHILD_SIZE_HERO' ) ? STREAMIT_CHILD_SIZE_HERO : 'large',
		'slick' => array(
			'dots'           => false,
			'slidesToShow'   => 3,
			'slidesToScroll' => 3,
			'arrows'         => true,
			'autoplay'       => false,
			'speed'          => 300,
			'infinite'       => true,
			'swipeToSlide'   => true,
			'responsive'     => array(
				array(
					'breakpoint' => 1025,
					'settings'   => array(
						'slidesToShow'   => 2,
						'slidesToScroll' => 2,
					),
				),
				array(
					'breakpoint' => 600,
					'settings'   => array(
						'slidesToShow'   => 1,
						'slidesToScroll' => 1,
					),
				),
			),
		),
	),
);

$sections = array_values(
	array_filter(
		$sections,
		static function ( $section ) {
			return $section['ids'] !== array();
		}
	)
);

if ( $sections === array() ) {
	return;
}

$css_path = get_stylesheet_directory() . '/assets/css/tmdb-artwork-gallery.css';
if ( file_exists( $css_path ) ) {
	wp_enqueue_style(
		'streamit-child-tmdb-artwork-gallery',
		get_stylesheet_directory_uri() . '/assets/css/tmdb-artwork-gallery.css',
		array(),
		(string) filemtime( $css_path )
	);
}

foreach ( $sections as $section ) :
	?>
	<div class="stc-artwork-section <?php echo esc_attr( $section['class'] ); ?>">
		<div class="single_page_slick">
			<div class="section-spacing-bottom">
				<div class="streamit-card-title">
					<div class="title d-flex align-items-center justify-content-between">
						<h5 class="title-tag"><?php echo esc_html( $section['title'] ); ?></h5>
					</div>
				</div>
				<div class="css_prefx-main-card position-relative">
					<div class="css_prefix-slick-general st-skeleton"
						data-extra_settings="true"
						data-slider_settings='<?php echo wp_json_encode( $section['slick'] ); ?>'>
						<?php foreach ( $section['ids'] as $attachment_id ) : ?>
							<?php
							$full_url = function_exists( 'wp_get_attachment_image_url' )
								? wp_get_attachment_image_url( (int) $attachment_id, 'full' )
								: '';
							if ( ! is_string( $full_url ) || '' === $full_url ) {
								continue;
							}
							$file_name = basename( (string) wp_parse_url( $full_url, PHP_URL_PATH ) );
							if ( '' === $file_name ) {
								$file_name = 'tmdb-image.jpg';
							}
							?>
							<div class="slick-item">
								<div class="css_prefix-card">
									<div class="block-images position-relative w-100">
										<a href="<?php echo esc_url( $full_url ); ?>"
											class="color-inherit image-box w-100"
											target="_blank"
											rel="noopener noreferrer"
											download="<?php echo esc_attr( $file_name ); ?>">
											<?php
											if ( function_exists( 'streamit_render_image' ) ) {
												echo streamit_render_image(
													array(
														'attachment_id' => $attachment_id,
														'size'          => $section['size'],
														'class'         => 'img-fluid object-cover w-100 border-0',
														'alt'           => $section['title'],
														'show_fallback' => false,
													)
												); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											}
											?>
										</a>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
endforeach;

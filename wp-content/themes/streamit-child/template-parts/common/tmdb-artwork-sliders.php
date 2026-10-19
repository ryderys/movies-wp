<?php
/**
 * Poster and backdrop galleries under the download box.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $st_data ) || ! is_object( $st_data ) || ! method_exists( $st_data, 'get_meta' ) ) {
	return;
}

$sections = array(
	array(
		'ids'   => streamit_child_tmdb_artwork_ids( $st_data->get_meta( '_tmdb_poster_ids' ) ),
		'title' => __( 'پوسترها', 'streamit' ),
		'class' => 'stc-artwork__poster',
		'size'  => defined( 'STREAMIT_CHILD_SIZE_POSTER' ) ? STREAMIT_CHILD_SIZE_POSTER : 'medium',
		'slick' => array(
			'slidesToShow'   => 6,
			'slidesToScroll' => 1,
			'responsive'     => array(
				array(
					'breakpoint' => 1025,
					'settings'   => array(
						'slidesToShow' => 4,
					),
				),
				array(
					'breakpoint' => 600,
					'settings'   => array(
						'slidesToShow' => 3,
					),
				),
				array(
					'breakpoint' => 450,
					'settings'   => array(
						'slidesToShow' => 2,
					),
				),
			),
		),
	),
	array(
		'ids'   => streamit_child_tmdb_artwork_ids( $st_data->get_meta( '_tmdb_backdrop_ids' ) ),
		'title' => __( 'تصاویر پس‌زمینه', 'streamit' ),
		'class' => 'stc-artwork__backdrop',
		'size'  => defined( 'STREAMIT_CHILD_SIZE_HERO' ) ? STREAMIT_CHILD_SIZE_HERO : 'large',
		'slick' => array(
			'slidesToShow'   => 3,
			'slidesToScroll' => 1,
			'responsive'     => array(
				array(
					'breakpoint' => 1025,
					'settings'   => array(
						'slidesToShow' => 2,
					),
				),
				array(
					'breakpoint' => 600,
					'settings'   => array(
						'slidesToShow' => 1,
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
?>
<div class="stc-artwork section-spacing-bottom">
	<div class="container-fluid">
		<?php foreach ( $sections as $section ) : ?>
			<?php
			$slick = array_merge(
				array(
					'dots'         => false,
					'arrows'       => true,
					'infinite'     => false,
					'swipeToSlide' => true,
					'speed'        => 300,
				),
				$section['slick']
			);
			?>
			<div class="stc-artwork__section <?php echo esc_attr( $section['class'] ); ?>">
				<div class="streamit-card-title">
					<div class="title d-flex align-items-center justify-content-between">
						<h5 class="title-tag"><?php echo esc_html( $section['title'] ); ?></h5>
					</div>
				</div>
				<div class="css_prefix-slick-general st-skeleton"
					data-extra_settings="true"
					data-slider_settings='<?php echo wp_json_encode( $slick ); ?>'>
					<?php foreach ( $section['ids'] as $attachment_id ) : ?>
						<div class="stc-artwork__slide">
							<?php
							if ( function_exists( 'streamit_render_image' ) ) {
								echo streamit_render_image(
									array(
										'attachment_id' => $attachment_id,
										'size'          => $section['size'],
										'class'         => 'img-fluid',
										'alt'           => $section['title'],
										'show_fallback' => false,
									)
								); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>

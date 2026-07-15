<?php
/**
 * Country card for movie country archive.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$st_image_url = streamit_placeholder_image();
$term_name    = wp_unslash( $st_data->get_term_name() );
$term_slug    = $st_data->get_term_slug();
$permalink    = streamit_child_get_country_permalink( 'movie_country', $term_slug );
?>
<div class="col">
	<div class="genres-card position-relative">
		<div class="image-box position-relative">
			<a href="<?php echo esc_url( $permalink ); ?>" class="color-inherit line-count-1">
				<img src="<?php echo esc_url( $st_image_url ); ?>" alt="<?php echo esc_attr( $term_name ?: __( 'image', 'streamit' ) ); ?>" class="img-fluid">
				<?php if ( ! empty( $term_name ) ) : ?>
					<span class="genres-title h6">
						<?php echo esc_html( $term_name ); ?>
					</span>
				<?php endif; ?>
			</a>
		</div>
	</div>
</div>

<?php
/**
 * Country filter widget for movie / TV show archives.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_type = isset( $post_type ) ? sanitize_key( (string) $post_type ) : 'movie';
if ( ! in_array( $post_type, array( 'movie', 'tvshow' ), true ) ) {
	return;
}

$countries = function_exists( 'streamit_child_get_catalog_countries' )
	? streamit_child_get_catalog_countries( $post_type )
	: array();

if ( empty( $countries ) ) {
	return;
}
?>
<div class="filter-widget filter-widget-countries">
	<h5 class="filter-wiget-title"><?php echo esc_html__( 'بر اساس کشور', 'streamit' ); ?></h5>
	<div class="filter-widget-inner">
		<div class="filter-list country-scrollbox" id="country-list-<?php echo esc_attr( $post_type ); ?>">
			<?php foreach ( $countries as $code => $label ) : ?>
				<?php
				$input_id = 'country-' . esc_attr( $post_type ) . '-' . esc_attr( $code );
				?>
				<div class="form-check">
					<input
						class="form-check-input streamit-filter"
						type="checkbox"
						value="<?php echo esc_attr( $code ); ?>"
						id="<?php echo esc_attr( $input_id ); ?>"
						name="countries[]"
					>
					<label class="form-check-label" for="<?php echo esc_attr( $input_id ); ?>">
						<span class="filter-text"><?php echo esc_html( $label ); ?></span>
					</label>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

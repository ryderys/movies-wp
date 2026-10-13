<?php
/**
 * The template for displaying TV show season count on cards (child-theme override).
 *
 * Uses available seasons to ensure count matches actual media availability.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seasons = function_exists( 'streamit_child_get_available_seasons' )
	? streamit_child_get_available_seasons( $st_data )
	: ( isset( $st_data ) && method_exists( $st_data, 'get_meta' ) ? $st_data->get_meta( '_seasons' ) : array() );

$season_count = ( ! empty( $seasons ) && is_array( $seasons ) ) ? count( $seasons ) : 0;

if ( $season_count > 0 ) {
	$season_display = sprintf(
		_n( '%d Season', '%d Seasons', $season_count, 'streamit' ),
		$season_count
	);
} else {
	$season_display = __( 'Arriving Soon', 'streamit' );
}
?>
<div class="font-size-14">
	<?php echo esc_html( $season_display ); ?>
</div>

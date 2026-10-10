<?php
/**
 * TV show single title with an optional TMDb-localized secondary title
 * and TMDb series status.
 *
 * Matches the Movie single title behavior: Persian post_title as primary, and
 * `_tmdb_title` (English/original) shown underneath when present.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

$primary_title = is_object( $st_data ) && method_exists( $st_data, 'get_post_title' )
	? trim( (string) $st_data->get_post_title() )
	: '';
$tmdb_title    = is_object( $st_data ) && method_exists( $st_data, 'get_meta' )
	? trim( (string) $st_data->get_meta( '_tmdb_title' ) )
	: '';
$tmdb_status   = is_object( $st_data ) && method_exists( $st_data, 'get_meta' )
	? trim( (string) $st_data->get_meta( '_tmdb_status' ) )
	: '';

$status_labels = array(
	'Returning Series' => __( 'Returning Series', 'streamit' ),
	'Ended'            => __( 'Ended', 'streamit' ),
	'Canceled'         => __( 'Canceled', 'streamit' ),
	'In Production'    => __( 'In Production', 'streamit' ),
	'Planned'          => __( 'Planned', 'streamit' ),
	'Pilot'            => __( 'Pilot', 'streamit' ),
);
$status_label = '' !== $tmdb_status
	? ( $status_labels[ $tmdb_status ] ?? $tmdb_status )
	: '';
?>
<?php if ( '' !== $primary_title ) : ?>
	<h1 class="text-uppercase mt-1 mb-2 texture-text fw-bold font-size-37">
		<?php echo esc_html( $primary_title ); ?>
	</h1>
<?php endif; ?>
<?php if ( '' !== $tmdb_title && $tmdb_title !== $primary_title ) : ?>
	<p class="streamit-child-tmdb-title mt-0 <?php echo '' !== $status_label ? 'mb-2' : 'mb-3'; ?>" dir="auto">
		<?php echo esc_html( $tmdb_title ); ?>
	</p>
<?php endif; ?>
<?php if ( '' !== $status_label ) : ?>
	<p class="streamit-child-tmdb-status mt-0 mb-3" dir="auto">
		<?php echo esc_html( __( 'Status:', 'streamit' ) . ' ' . $status_label ); ?>
	</p>
<?php endif; ?>

<?php
/**
 * TV show single title: English (_tmdb_title) primary when present,
 * Persian post_title as secondary, plus TMDb series status.
 * Falls back to Persian when English is empty.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

$persian_title = is_object( $st_data ) && method_exists( $st_data, 'get_post_title' )
	? trim( (string) $st_data->get_post_title() )
	: '';
$english_title = is_object( $st_data ) && method_exists( $st_data, 'get_meta' )
	? trim( (string) $st_data->get_meta( '_tmdb_title' ) )
	: '';
$tmdb_status   = is_object( $st_data ) && method_exists( $st_data, 'get_meta' )
	? trim( (string) $st_data->get_meta( '_tmdb_status' ) )
	: '';

$primary_title   = '' !== $english_title ? $english_title : $persian_title;
$secondary_title = (
	'' !== $english_title
	&& '' !== $persian_title
	&& $persian_title !== $english_title
) ? $persian_title : '';

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
	<h1 class="text-uppercase mt-1 mb-2 text-white fw-bold font-size-37">
		<?php echo esc_html( $primary_title ); ?>
	</h1>
<?php endif; ?>
<?php if ( '' !== $secondary_title ) : ?>
	<p class="streamit-child-tmdb-title text-white mt-0 <?php echo '' !== $status_label ? 'mb-2' : 'mb-3'; ?>" dir="auto">
		<?php echo esc_html( $secondary_title ); ?>
	</p>
<?php endif; ?>
<?php if ( '' !== $status_label ) : ?>
	<p class="streamit-child-tmdb-status mt-0 mb-3" dir="auto">
		<?php echo esc_html( __( 'Status:', 'streamit' ) . ' ' . $status_label ); ?>
	</p>
<?php endif; ?>

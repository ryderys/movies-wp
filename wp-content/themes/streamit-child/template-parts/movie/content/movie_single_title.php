<?php
/**
 * Movie single title: English (_tmdb_title) primary when present,
 * Persian post_title as secondary. Falls back to Persian when English is empty.
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

$primary_title   = '' !== $english_title ? $english_title : $persian_title;
$secondary_title = (
	'' !== $english_title
	&& '' !== $persian_title
	&& $persian_title !== $english_title
) ? $persian_title : '';
?>
<?php if ( '' !== $primary_title ) : ?>
	<h1 class="text-uppercase mt-1 mb-2 texture-text fw-bold font-size-37">
		<?php echo esc_html( $primary_title ); ?>
	</h1>
<?php endif; ?>
<?php if ( '' !== $secondary_title ) : ?>
	<p class="streamit-child-tmdb-title mt-0 mb-3" dir="auto">
		<?php echo esc_html( $secondary_title ); ?>
	</p>
<?php endif; ?>

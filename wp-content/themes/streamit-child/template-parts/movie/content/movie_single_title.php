<?php
/**
 * Movie single title with an optional TMDb-localized secondary title.
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
?>
<?php if ( '' !== $primary_title ) : ?>
	<h1 class="text-uppercase mt-1 mb-2 texture-text fw-bold font-size-37">
		<?php echo esc_html( $primary_title ); ?>
	</h1>
<?php endif; ?>
<?php if ( '' !== $tmdb_title && $tmdb_title !== $primary_title ) : ?>
	<p class="streamit-child-tmdb-title mt-0 mb-3" dir="auto">
		<?php echo esc_html( $tmdb_title ); ?>
	</p>
<?php endif; ?>

<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$labels = function_exists( 'streamit_child_get_country_labels' )
	? streamit_child_get_country_labels( $st_data )
	: array();

if ( empty( $labels ) ) {
	return;
}

$is_limit       = isset( $is_limit ) ? (bool) $is_limit : false;
$total_countries = count( $labels );
$loop_limit     = $is_limit ? 2 : $total_countries;
?>
<div class="movie-country d-flex align-items-center gap-1 mt-2">
	<span class="text-body-secondary"><small><?php esc_html_e( 'Country', 'streamit' ); ?></small></span>
	<ul class="list-inline m-0 p-0 d-inline-flex align-items-center gap-2 flex-wrap">
		<?php foreach ( array_slice( $labels, 0, $loop_limit ) as $country_label ) : ?>
			<li>
				<small><?php echo esc_html( $country_label ); ?></small>
			</li>
		<?php endforeach; ?>
		<?php if ( $is_limit && $total_countries > 2 ) : ?>
			<li>
				<small>+<?php echo esc_html( $total_countries - 2 ); ?></small>
			</li>
		<?php endif; ?>
	</ul>
</div>

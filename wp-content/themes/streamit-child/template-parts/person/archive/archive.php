<?php

/**
 * Persons (casts) archive — numbered pagination instead of load-more.
 *
 * @package streamit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_page = 1;
if ( get_query_var( 'paged' ) ) {
	$current_page = absint( get_query_var( 'paged' ) );
} elseif ( ! empty( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current_page = absint( wp_unslash( $_GET['paged'] ) );
}
$current_page = max( 1, $current_page );

// st_pagination() reads get_query_var( 'paged' ); keep it in sync for ?paged=N.
set_query_var( 'paged', $current_page );

?>

<div class="container-fluid">
	<?php if ( ! empty( $content_data->results ) ) : ?>
		<div class="css_prefix-card-wrapper person_cards grid-view" data-options="yes" data-can-beloaded="0">
			<div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6 data-listing">
				<?php foreach ( $content_data->results as $st_data ) : ?>
					<?php streamit_get_template( 'person/archive/archive_loop.php', array( 'st_data' => $st_data ) ); ?>
				<?php endforeach; ?>
			</div>

			<?php if ( ! empty( $content_data->maxnumpages ) && (int) $content_data->maxnumpages > 1 ) : ?>
				<div class="row mt-4">
					<?php st_pagination( (int) $content_data->maxnumpages, 2, $current_page ); ?>
				</div>
			<?php endif; ?>
		</div>

	<?php else : ?>
		<p class="no_data_found"><?php esc_html_e( 'No persons found.', 'streamit' ); ?></p>
	<?php endif; ?>
</div>

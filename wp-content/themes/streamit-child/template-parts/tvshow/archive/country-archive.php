<?php
/**
 * TV show countries archive (grid of countries).
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $streamit_options;

$load_more_text    = streamit_get_button_text( 'streamit_genere_tag_category_display_loadmore_text', 'بارگذاری بیشتر' );
$loading_text      = streamit_get_button_text( 'streamit_genere_tag_category_loadmore_text_2', esc_html__( 'Loading...', 'streamit' ) );
$genere_tag_option = isset( $streamit_options['streamit_genere_tag_category_item'] ) ? $streamit_options['streamit_genere_tag_category_item'] : 'load_more';
$per_page          = streamit_child_countries_per_page();
?>
<div class="container-fluid">
	<?php if ( ! empty( $content_data->results ) ) : ?>
		<div class="css_prefix-card-wrapper tvshow_cards grid-view" data-options="yes" data-can-beloaded="1">
			<div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 data-listing">
				<?php foreach ( $content_data->results as $st_data ) : ?>
					<?php streamit_get_template( 'tvshow/archive/archive_country_loop.php', array( 'st_data' => $st_data ) ); ?>
				<?php endforeach; ?>
			</div>
			<?php
			if ( $content_data->maxnumpages > 1 ) {
				if ( 'load_more' === $genere_tag_option ) {
					echo st_get_load_more_button( $content_data->maxnumpages, 'tvshow_countries', 1, esc_html( $load_more_text ), esc_html( $loading_text ), $per_page );
				} elseif ( 'infinite_scroll' === $genere_tag_option ) {
					echo st_get_loader_wheel_container( $content_data->maxnumpages, 'tvshow_countries', 1, $per_page );
				}
			}
			?>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'No countries found.', 'streamit' ); ?></p>
	<?php endif; ?>
</div>

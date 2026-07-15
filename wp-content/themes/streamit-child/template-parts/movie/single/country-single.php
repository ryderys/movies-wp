<?php
/**
 * Movies filtered by country.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_query;

$country_code = isset( $wp_query->query_vars['movie_country'] )
	? strtoupper( sanitize_text_field( (string) $wp_query->query_vars['movie_country'] ) )
	: '';

$load_more_text = streamit_get_button_text( 'streamit_genere_tag_category_display_loadmore_text', 'بارگذاری بیشتر' );
$loading_text   = streamit_get_button_text( 'streamit_genere_tag_category_loadmore_text_2', esc_html__( 'Loading...', 'streamit' ) );
?>
<div class="container-fluid">
	<?php if ( ! empty( $content_data->results ) ) : ?>
		<div class="css_prefix-card-wrapper movie_cards grid-view" data-options="yes" data-can-beloaded="1">
			<div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 data-listing">
				<?php
				foreach ( $content_data->results as $st_data ) :
					streamit_get_template( 'movie/archive/archive_loop.php', array( 'st_data' => $st_data, 'view_type' => $view_type ) );
				endforeach;
				?>
			</div>
			<?php
			if ( $content_data->maxnumpages > 1 ) {
				echo st_get_load_more_button(
					$content_data->maxnumpages,
					'movie_single_country',
					1,
					esc_html( $load_more_text ),
					esc_html( $loading_text ),
					12,
					'',
					'',
					esc_attr( $country_code )
				);
			}
			?>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'No Movies Found.', 'streamit' ); ?></p>
	<?php endif; ?>
</div>

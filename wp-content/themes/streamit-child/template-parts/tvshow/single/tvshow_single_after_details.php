<?php
/**
 * TV show single: artwork galleries, then the parent after-details sliders.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="section-spacing">
	<div class="container-fluid">
		<div class="overflow-hidden">
			<?php streamit_get_template( 'common/tmdb-artwork-sliders.php', array( 'st_data' => $st_data ) ); ?>
			<?php streamit_get_template( 'tvshow/content/tvshow_single_persons_slider.php', array( 'st_data' => $st_data, 'type' => 'cast' ) ); ?>
			<?php streamit_get_template( 'tvshow/content/tvshow_single_persons_slider.php', array( 'st_data' => $st_data, 'type' => 'crew' ) ); ?>
			<?php
			if ( function_exists( 'streamit_child_tvshow_time_begin' ) ) {
				streamit_child_tvshow_time_begin( 'after_morelike' );
			}
			try {
				streamit_get_template( 'tvshow/content/tvshow_single_morelike_slider.php', array( 'st_data' => $st_data ) );
			} finally {
				if ( function_exists( 'streamit_child_tvshow_time_end' ) ) {
					streamit_child_tvshow_time_end( 'after_morelike' );
				}
			}
			?>
			<?php streamit_get_template( 'tvshow/content/tvshow_single_upcoming_tvshow.php', array( 'st_data' => $st_data ) ); ?>
			<?php streamit_get_template( 'tvshow/content/tvshow_single_related_products.php', array( 'st_data' => $st_data ) ); ?>
			<?php
			$status       = function_exists( 'streamit_is_upcoming' ) ? streamit_is_upcoming( $st_data, 'tvshow' ) : array(
				'is_upcoming'       => false,
				'is_future_release' => false,
				'formatted_date'    => '',
			);
			$is_available = empty( $status['is_upcoming'] ) || empty( $status['is_future_release'] );
			$is_admin     = current_user_can( 'administrator' );
			if ( $is_admin || $is_available ) {
				streamit_get_template( 'tvshow/content/tvshow_single_comments.php', array( 'st_data' => $st_data ) );
			}
			?>
		</div>
	</div>
</div>

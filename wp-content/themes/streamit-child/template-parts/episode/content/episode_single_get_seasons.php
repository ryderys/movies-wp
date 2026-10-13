<?php
/**
 * Episode single: seasons and episode slider tab section (child-theme override).
 *
 * Filters seasons and episodes to those with available media on the media host.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $streamit_options;
$enable_upcoming_badges = ( isset( $streamit_options['streamit_recommended_enable_upcoming_badges'] ) && 'yes' === $streamit_options['streamit_recommended_enable_upcoming_badges'] );

$current_episode_id = (int) $st_data->get_id();
$current_tab        = 0;
$slider_position    = 0;
$total_slides       = 0;

$tvshow_id = (int) $st_data->get_meta( 'tvshow_id' );
$tvshow    = function_exists( 'streamit_get_tvshow' ) ? streamit_get_tvshow( $tvshow_id ) : null;
if ( empty( $tvshow ) ) {
	return;
}

$seasons = function_exists( 'streamit_child_get_available_seasons' )
	? streamit_child_get_available_seasons( $tvshow )
	: ( method_exists( $tvshow, 'get_meta' ) ? $tvshow->get_meta( '_seasons' ) : array() );

if ( empty( $seasons ) || ! is_array( $seasons ) ) {
	return;
}

foreach ( $seasons as $index => $val ) {
	if ( isset( $val['episodes'] ) && is_array( $val['episodes'] ) ) {
		if ( in_array( $current_episode_id, $val['episodes'], true ) ) {
			$current_tab     = $index;
			$slider_position = (int) array_search( $current_episode_id, $val['episodes'], true );
			$total_slides    = count( $val['episodes'] );
		}
	}
}

$slides_to_show = 5;
if ( $total_slides > 0 && $slider_position > $total_slides - $slides_to_show ) {
	$slider_position = max( 0, $total_slides - $slides_to_show );
}

$slick_args = array(
	'slidesToShow'   => 5,
	'slidesToScroll' => 1,
	'infinite'       => false,
	'arrows'         => true,
	'dots'           => false,
	'initialSlide'   => $slider_position,
	'draggable'      => true,
	'responsive'     => array(
		array( 'breakpoint' => 1368, 'settings' => array( 'slidesToShow' => 3 ) ),
		array( 'breakpoint' => 1025, 'settings' => array( 'slidesToShow' => 3 ) ),
		array( 'breakpoint' => 993,  'settings' => array( 'slidesToShow' => 2 ) ),
		array( 'breakpoint' => 577,  'settings' => array( 'slidesToShow' => 2 ) ),
	),
);
?>
<div class="section-spacing-top">
	<div class="container-fluid">
		<div class="episode-section">
			<div class="single_page_slick">
				<div class="d-flex align-items-center justify-content-between mb-md-5 mb-3">
					<h5 class="main-title text-capitalize mb-0"><?php echo esc_html__( 'Episodes', 'streamit' ); ?></h5>
				</div>

				<div class="episode-season">
					<div id="item-nav">
						<div class="item-list-tabs no-ajax css_prefix-tab-lists" id="object-nav">
							<div class="left" onclick="slide('left',event)" style="display: none;">
								<?php echo st_get_icon( 'arrow-left' ); ?>
							</div>
							<ul class="nav nav-pills custom-tab-slider episode-nav-btn gap-3" id="nav-tab">
								<?php foreach ( $seasons as $index => $val ) :
									$season_name_id = trim( preg_replace( '/[^\p{L}\p{N}]+/u', '-', mb_strtolower( (string) $val['name'] ) ), '-' );

									$season_upcoming_data = function_exists( 'streamit_is_season_upcoming' ) ? streamit_is_season_upcoming( $val ) : array( 'is_future_release' => false );
									$is_upcoming_season   = ! empty( $season_upcoming_data['is_future_release'] );
								?>
									<li class="nav-item" role="presentation">
										<a class="nav-link init-episode-slider <?php echo $index === $current_tab ? 'active' : ''; ?>" data-display='<?php echo esc_attr( (string) $index ); ?>' data-bs-toggle="pill" data-season_no="<?php echo esc_attr( (string) $index ); ?>" href="#<?php echo esc_attr( $season_name_id ); ?>">
											<?php echo esc_html( $val['name'] ); ?>
											<?php if ( $is_upcoming_season && $enable_upcoming_badges ) : ?>
												<span class="badge ms-2 upcoming-badge">
													<span><?php echo esc_html__( 'Coming Soon', 'streamit' ); ?></span>
												</span>
											<?php endif; ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
							<div class="right" onclick="slide('right',event)" style="display: none;">
								<?php echo st_get_icon( 'arrow-right' ); ?>
							</div>
						</div>
					</div>
					<div class="tab-content mt-5" id="nav-tabContent">
						<?php foreach ( $seasons as $index1 => $season ) :
							$season_name_id = trim( preg_replace( '/[^\p{L}\p{N}]+/u', '-', mb_strtolower( (string) $season['name'] ) ), '-' );

							$season_upcoming_data = function_exists( 'streamit_is_season_upcoming' ) ? streamit_is_season_upcoming( $season ) : array( 'is_future_release' => false );
							$is_upcoming_season   = ! empty( $season_upcoming_data['is_future_release'] );
						?>
							<div class="tab-pane fade <?php echo $current_tab === $index1 ? ' active show' : ''; ?>" id="<?php echo esc_attr( $season_name_id ); ?>" role="tabpanel">
								<div class="single_page_slick">
									<div class="css_prefix-episodes-content">
										<?php
										$is_admin = current_user_can( 'administrator' );

										if ( $is_upcoming_season && ! $is_admin ) :
											streamit_get_template( 'tvshow/content/tvshow_single_upcoming_season.php', array(
												'season'               => $season,
												'season_upcoming_data' => $season_upcoming_data,
												'st_data'              => $tvshow,
												'season_index'         => $index1,
											) );
										elseif ( ! empty( $season['episodes'] ) && is_array( $season['episodes'] ) ) :
										?>
											<div class="css_prefix-slick-general st-skeleton" data-display='<?php echo esc_attr( (string) $index1 ); ?>' data-extra_settings="true" data-slider_settings="<?php echo st_get_slick_slider_settings( $slick_args ); ?>">
												<?php
												$episode_posts = function_exists( 'streamit_get_episodes' )
													? streamit_get_episodes( array(
														'orderby'  => 'post__in',
														'include'  => $season['episodes'],
														'per_page' => -1,
													) )->results
													: array();

												foreach ( $episode_posts as $index => $episode ) :
													streamit_get_template( 'episode/content/episode_single_slider.php', array(
														'episode'            => $episode,
														'current_episode_id' => $current_episode_id,
													) );
												endforeach;
												?>
											</div>
										<?php else : ?>
											<p class="no_data_found"><?php echo esc_html__( 'No Episode Available', 'streamit' ); ?></p>
										<?php endif; ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

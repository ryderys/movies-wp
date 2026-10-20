<?php
/**
 * TV show single: seasons and episode slider tab section (child-theme override).
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

if ( function_exists( 'streamit_is_upcoming' ) ) {
	$upcoming_data = streamit_is_upcoming( $st_data, 'tvshow' );
	if ( ! empty( $upcoming_data['is_future_release'] ) ) {
		$is_admin = current_user_can( 'administrator' );
		if ( ! $is_admin ) {
			return;
		}
	}
}

$seasons = array();
if ( function_exists( 'streamit_child_tvshow_time_begin' ) ) {
	streamit_child_tvshow_time_begin( 'get_available_seasons' );
}
try {
	$seasons = function_exists( 'streamit_child_get_available_seasons' )
		? streamit_child_get_available_seasons( $st_data )
		: ( isset( $st_data ) && method_exists( $st_data, 'get_meta' ) ? $st_data->get_meta( '_seasons' ) : array() );
} finally {
	if ( function_exists( 'streamit_child_tvshow_time_end' ) ) {
		streamit_child_tvshow_time_end( 'get_available_seasons' );
	}
}

if ( empty( $seasons ) || ! is_array( $seasons ) ) {
	return;
}

$season_switch_state = get_stylesheet_directory() . '/assets/js/tvshow-season-switch-state.js';
$season_switch       = get_stylesheet_directory() . '/assets/js/tvshow-season-switch.js';
wp_enqueue_script(
	'streamit-child-season-switch-state',
	get_stylesheet_directory_uri() . '/assets/js/tvshow-season-switch-state.js',
	array(),
	file_exists( $season_switch_state ) ? (string) filemtime( $season_switch_state ) : '1.0',
	true
);
wp_enqueue_script(
	'streamit-child-season-switch',
	get_stylesheet_directory_uri() . '/assets/js/tvshow-season-switch.js',
	array( 'jquery', 'streamit-child-season-switch-state' ),
	file_exists( $season_switch ) ? (string) filemtime( $season_switch ) : '1.0',
	true
);

$slick_args = array(
	'slidesToShow'   => 5,
	'slidesToScroll' => 1,
	'infinite'       => true,
	'arrows'         => true,
	'dots'           => false,
	'draggable'      => true,
	'responsive'     => array(
		array( 'breakpoint' => 1368, 'settings' => array( 'slidesToShow' => 3 ) ),
		array( 'breakpoint' => 1025, 'settings' => array( 'slidesToShow' => 3 ) ),
		array( 'breakpoint' => 993,  'settings' => array( 'slidesToShow' => 2 ) ),
		array( 'breakpoint' => 577,  'settings' => array( 'slidesToShow' => 1 ) ),
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
										<a class="nav-link css_prefix-change-season <?php echo 0 === $index ? 'active' : ''; ?>" data-display=0 data-episodes="<?php echo esc_attr( wp_json_encode( $seasons ) ); ?>" data-bs-toggle="pill" data-season_no="<?php echo esc_attr( (string) $index ); ?>" href="#<?php echo esc_attr( $season_name_id ); ?>">
											<?php echo esc_html( $val['name'] ); ?>
											<?php if ( $is_upcoming_season && $enable_upcoming_badges ) : ?>
												<span class="badge upcoming-badge">
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
						<?php
						// Full episode rows only for the first pane that renders cards.
						// Later seasons stay empty skeletons for the existing season-switch AJAX.
						$slider_episodes_loaded = false;
						foreach ( $seasons as $index1 => $season ) :
							$season_name_id = trim( preg_replace( '/[^\p{L}\p{N}]+/u', '-', mb_strtolower( (string) $season['name'] ) ), '-' );
						?>
							<div class="tab-pane fade <?php echo 0 === $index1 ? esc_attr( 'active show' ) : ''; ?>" id="<?php echo esc_attr( $season_name_id ); ?>" role="tabpanel">
								<div class="single_page_slick">
									<div class="css_prefix-episodes-content">
										<?php
										$season_upcoming_data = function_exists( 'streamit_is_season_upcoming' ) ? streamit_is_season_upcoming( $season ) : array( 'is_future_release' => false );
										$is_upcoming_season   = ! empty( $season_upcoming_data['is_future_release'] );

										$is_admin = current_user_can( 'administrator' );

										if ( $is_upcoming_season && ! $is_admin ) :
											streamit_get_template( 'tvshow/content/tvshow_single_upcoming_season.php', array(
												'season'               => $season,
												'season_upcoming_data' => $season_upcoming_data,
												'st_data'              => $st_data,
												'season_index'         => $index1,
											) );
										elseif ( ! empty( $season['episodes'] ) && is_array( $season['episodes'] ) ) :
										?>
											<div class="css_prefix-slick-general st-skeleton" data-display=0 data-season_no="<?php echo esc_attr( (string) $index1 ); ?>" data-extra_settings="true" data-slider_settings="<?php echo st_get_slick_slider_settings( $slick_args ); ?>">
												<?php
												if ( ! $slider_episodes_loaded ) {
													if ( function_exists( 'streamit_child_tvshow_time_begin' ) ) {
														streamit_child_tvshow_time_begin( 'episode_objects' );
													}
													try {
														$episode_posts = function_exists( 'streamit_child_get_series_episode_objects' )
															? streamit_child_get_series_episode_objects( $season['episodes'] )
															: ( function_exists( 'streamit_get_episodes' )
																? streamit_get_episodes(
																	array(
																		'orderby'  => 'post__in',
																		'include'  => $season['episodes'],
																		'paged'    => 1,
																		'per_page' => -1,
																	)
																)->results
																: array() );
													} finally {
														if ( function_exists( 'streamit_child_tvshow_time_end' ) ) {
															streamit_child_tvshow_time_end( 'episode_objects' );
														}
													}

													foreach ( $episode_posts as $episode ) {
														streamit_get_template( 'episode/content/episode_single_slider.php', array( 'episode' => $episode ) );
													}
													$slider_episodes_loaded = true;
												}
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

<?php
/**
 * Series single: season accordion → quality rows → episode link grid.
 *
 * Each season embeds its quality groups as JSON; the episode grid of a quality
 * row is built on first expand so large seasons are not pre-rendered for every
 * quality.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $st_data ) || ! is_object( $st_data ) ) {
	return;
}

if ( ! function_exists( 'streamit_child_build_series_download_catalog' ) ) {
	return;
}

$catalog = array(
	'seasons'      => array(),
	'ost'          => array(),
	'can_download' => false,
);
if ( function_exists( 'streamit_child_tvshow_time_begin' ) ) {
	streamit_child_tvshow_time_begin( 'download_catalog' );
}
try {
	$catalog = streamit_child_build_series_download_catalog( $st_data );
} finally {
	if ( function_exists( 'streamit_child_tvshow_time_end' ) ) {
		streamit_child_tvshow_time_end( 'download_catalog' );
	}
}
if ( empty( $catalog['seasons'] ) && empty( $catalog['ost'] ) ) {
	return;
}

$can_download = ! empty( $catalog['can_download'] );
streamit_child_enqueue_series_download_assets();

if ( ! $can_download ) {
	streamit_child_render_subscribe_required_modal( $st_data, 'tvshow', 'download' );
}

global $streamit_options;
$show_share = ! ( isset( $streamit_options['streamit_display_social_icons'] ) && 'no' === $streamit_options['streamit_display_social_icons'] );

$ui_i18n = array(
	/* translators: %s: zero-padded episode number */
	'episode'    => __( 'قسمت %s', 'streamit' ),
	'download'   => __( 'دانلود', 'streamit' ),
	'noMedia'    => __( 'لینکی موجود نیست', 'streamit' ),
	/* translators: %d: number of copied links */
	'copied'     => __( '%d لینک کپی شد.', 'streamit' ),
	'copyFailed' => __( 'کپی لینک‌ها انجام نشد. مرورگر شما اجازه دسترسی به کلیپ‌بورد را نداد.', 'streamit' ),
	'noLinks'    => __( 'لینک دانلودی برای کپی وجود ندارد.', 'streamit' ),
);
$json_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
?>
<section
	class="section-spacing-top stc-dl stc-dl--series"
	data-stc-series-download
	data-can-download="<?php echo $can_download ? '1' : '0'; ?>"
	aria-labelledby="series-download-title"
>
	<div class="container-fluid">
		<div class="stc-dl__box">
			<div class="stc-dl__head">
				<h5 class="main-title text-capitalize mb-0" id="series-download-title">
					<?php esc_html_e( 'لینک‌های دانلود', 'streamit' ); ?>
				</h5>

				<?php if ( $show_share ) : ?>
					<div class="stc-dl__utils">
						<button type="button" class="btn btn-sm btn-secondary border stc-dl__util-btn" data-bs-toggle="modal" data-bs-target="#shareModal">
							<span class="stc-dl__icon" aria-hidden="true"><?php echo st_get_icon( 'share-2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span><?php esc_html_e( 'اشتراک‌گذاری', 'streamit' ); ?></span>
						</button>
					</div>
				<?php endif; ?>
			</div>

			<script type="application/json" class="stc-dl-i18n">
				<?php echo wp_json_encode( $ui_i18n, $json_flags ); ?>
			</script>

			<div class="stc-dl__seasons">
				<?php $rendered_seasons = 0; ?>
				<?php foreach ( $catalog['seasons'] as $season ) : ?>
					<?php
					$all_groups = streamit_child_series_download_quality_groups( $season['episodes'] );
					$groups     = array();
					foreach ( $all_groups as $g ) {
						if ( '' !== ( $g['quality'] ?? '' ) ) {
							$groups[] = $g;
						}
					}

					$sub_groups = function_exists( 'streamit_child_series_download_subtitle_groups' )
						? streamit_child_series_download_subtitle_groups( $season['episodes'] )
						: array();

					if ( empty( $groups ) && empty( $sub_groups ) ) {
						continue;
					}

					$panel_id = 'stc-dl-season-' . (int) $season['index'];
					$is_open  = ( 0 === $rendered_seasons++ );
					$count    = (int) $season['downloadable_episode_count'];

					$season_title = trim( (string) $season['name'] );
					if ( '' === $season_title && '' !== (string) $season['season_number'] ) {
						/* translators: %s: season number */
						$season_title = sprintf( __( 'فصل %s', 'streamit' ), $season['season_number'] );
					}

					$groups_json = array();
					foreach ( $groups as $group ) {
						$groups_json[] = $group['episodes'];
					}
					?>
					<div class="stc-dl-season<?php echo $is_open ? ' is-open' : ''; ?>" data-stc-season>
						<button
							type="button"
							class="stc-dl-season__toggle"
							data-stc-season-toggle
							aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
							aria-controls="<?php echo esc_attr( $panel_id ); ?>"
						>
							<span class="stc-dl-season__meta">
								<span class="stc-dl-season__title-row">
									<span class="stc-dl-season__title"><?php echo esc_html( $season_title ); ?></span>
									<?php if ( ! empty( $season['is_upcoming'] ) ) : ?>
										<span class="stc-dl-season__badge"><?php esc_html_e( 'به‌زودی', 'streamit' ); ?></span>
									<?php endif; ?>
								</span>
								<span class="stc-dl-season__count">
									<?php
									/* translators: %d: number of episodes with downloads */
									echo esc_html( sprintf( _n( '%d قسمت قابل دانلود', '%d قسمت قابل دانلود', $count, 'streamit' ), $count ) );
									?>
								</span>
							</span>
							<span class="stc-dl-chevron" aria-hidden="true"></span>
						</button>

						<div id="<?php echo esc_attr( $panel_id ); ?>" class="stc-dl-season__panel" <?php echo $is_open ? '' : 'hidden'; ?>>
							<?php if ( ! empty( $groups ) ) : ?>
								<script type="application/json" data-stc-season-data>
									<?php echo wp_json_encode( $groups_json, $json_flags ); ?>
								</script>

								<ul class="stc-dl__rows">
									<?php foreach ( $groups as $group_i => $group ) : ?>
										<?php
										$episodes_id = $panel_id . '-q' . (int) $group_i;
										$show_copy   = ( ! $can_download || $group['link_count'] > 0 );
										?>
										<li class="stc-dl-group" data-stc-group="<?php echo esc_attr( (string) (int) $group_i ); ?>" data-quality="<?php echo esc_attr( $group['quality'] ); ?>">
											<div class="stc-dl-row stc-dl-row--series">
												<div class="stc-dl-row__cell stc-dl-row__cell--count">
													<span class="stc-dl-row__label"><?php esc_html_e( 'تعداد قسمت‌ها:', 'streamit' ); ?></span>
													<bdi class="stc-dl-row__value"><?php echo esc_html( (string) count( $group['episodes'] ) ); ?></bdi>
												</div>

												<div class="stc-dl-row__cell stc-dl-row__cell--quality">
													<span class="stc-dl-row__label"><?php esc_html_e( 'کیفیت:', 'streamit' ); ?></span>
													<bdi class="stc-dl-row__quality"><?php echo esc_html( $group['quality'] ); ?></bdi>
												</div>

												<div class="stc-dl-row__cell stc-dl-row__cell--meta">
													<?php if ( '' !== $group['encoder'] ) : ?>
														<span class="stc-dl-row__field">
															<span class="stc-dl-row__label"><?php esc_html_e( 'انکودر:', 'streamit' ); ?></span>
															<bdi class="stc-dl-row__value"><?php echo esc_html( $group['encoder'] ); ?></bdi>
														</span>
													<?php endif; ?>
												</div>

												<div class="stc-dl-row__actions">
													<button
														type="button"
														class="btn btn-primary stc-dl-btn"
														data-stc-quality-toggle
														aria-expanded="false"
														aria-controls="<?php echo esc_attr( $episodes_id ); ?>"
													>
														<span><?php esc_html_e( 'مشاهده لینک‌ها', 'streamit' ); ?></span>
														<span class="stc-dl-chevron stc-dl-chevron--sm" aria-hidden="true"></span>
													</button>
												</div>
											</div>

											<div id="<?php echo esc_attr( $episodes_id ); ?>" class="stc-dl-episodes" hidden>
												<?php if ( $show_copy ) : ?>
													<div class="stc-dl-episodes__tools">
														<?php if ( $can_download ) : ?>
															<button type="button" class="btn btn-sm btn-secondary border stc-dl__util-btn" data-stc-copy>
																<?php esc_html_e( 'کپی تمام لینک‌ها', 'streamit' ); ?>
															</button>
															<span class="stc-dl-episodes__status" data-stc-copy-status role="status" aria-live="polite"></span>
														<?php else : ?>
															<button type="button" class="btn btn-sm btn-secondary border stc-dl__util-btn" data-bs-toggle="modal" data-bs-target="#subscribeRequiredModal">
																<?php esc_html_e( 'کپی تمام لینک‌ها', 'streamit' ); ?>
															</button>
														<?php endif; ?>
													</div>
												<?php endif; ?>
												<div class="stc-dl-episodes__grid" data-stc-episode-grid role="list"></div>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<?php if ( ! empty( $sub_groups ) ) : ?>
								<?php
								$sub_panel_id     = $panel_id . '-subtitles';
								$total_subs_count = 0;
								foreach ( $sub_groups as $sg ) {
									$total_subs_count += (int) $sg['count'];
								}
								?>
								<div class="stc-dl-series-subs" data-stc-subs>
									<button
										type="button"
										class="stc-dl-series-subs__toggle"
										data-stc-subs-toggle
										aria-expanded="false"
										aria-controls="<?php echo esc_attr( $sub_panel_id ); ?>"
									>
										<span class="stc-dl-series-subs__meta">
											<span class="stc-dl-series-subs__title">
												<?php esc_html_e( 'زیرنویس‌های این فصل', 'streamit' ); ?>
											</span>
											<span class="stc-dl-series-subs__badges">
												<?php foreach ( $sub_groups as $sg ) : ?>
													<span class="stc-dl-series-subs__badge"><?php echo esc_html( $sg['label'] ); ?></span>
												<?php endforeach; ?>
											</span>
											<span class="stc-dl-series-subs__count">
												<?php
												/* translators: %d: number of subtitle files */
												echo esc_html( sprintf( _n( '%d فایل زیرنویس', '%d فایل زیرنویس', $total_subs_count, 'streamit' ), $total_subs_count ) );
												?>
											</span>
										</span>
										<span class="stc-dl-chevron stc-dl-chevron--sm" aria-hidden="true"></span>
									</button>

									<div id="<?php echo esc_attr( $sub_panel_id ); ?>" class="stc-dl-series-subs__panel" hidden>
										<?php foreach ( $sub_groups as $sg ) : ?>
											<div class="stc-dl-series-subs__group">
												<div class="stc-dl-series-subs__group-head">
													<span class="stc-dl-series-subs__group-title">
														<?php
														/* translators: %s: release type e.g. WEB-DL or HDTV */
														printf( esc_html__( 'زیرنویس‌های نسخه %s', 'streamit' ), esc_html( $sg['label'] ) );
														?>
													</span>
													<span class="stc-dl-series-subs__group-count">
														<?php
														/* translators: %d: number of episodes */
														echo esc_html( sprintf( _n( '%d قسمت', '%d قسمت', count( $sg['episodes'] ), 'streamit' ), count( $sg['episodes'] ) ) );
														?>
													</span>
												</div>

												<div class="stc-dl-series-subs__grid">
													<?php foreach ( $sg['episodes'] as $sub_ep ) : ?>
														<div class="stc-dl-series-subs-card">
															<span class="stc-dl-series-subs-card__label">
																<?php
																$ep_ord = $sub_ep['ordinal'];
																printf( esc_html( $ui_i18n['episode'] ), esc_html( sprintf( '%02d', $ep_ord ) ) );
																?>
															</span>
															<div class="stc-dl-series-subs-card__actions">
																<?php foreach ( $sub_ep['subtitles'] as $sub_item ) : ?>
																	<?php if ( $can_download && ! empty( $sub_item['href'] ) ) : ?>
																		<a
																			href="<?php echo esc_url( $sub_item['href'] ); ?>"
																			class="btn btn-sm btn-outline-light stc-dl-series-subs-card__btn"
																			download
																		>
																			<span><?php echo esc_html( $sub_item['label'] ); ?></span>
																		</a>
																	<?php else : ?>
																		<button
																			type="button"
																			class="btn btn-sm btn-outline-light stc-dl-series-subs-card__btn"
																			data-bs-toggle="modal"
																			data-bs-target="#subscribeRequiredModal"
																		>
																			<span><?php echo esc_html( $sub_item['label'] ); ?></span>
																		</button>
																	<?php endif; ?>
																<?php endforeach; ?>
															</div>
														</div>
													<?php endforeach; ?>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php
			if ( ! empty( $catalog['ost'] ) && function_exists( 'streamit_child_render_ost_download_section' ) ) {
				streamit_child_render_ost_download_section( (array) $catalog['ost'], $can_download );
			}
			?>
		</div>
	</div>
</section>

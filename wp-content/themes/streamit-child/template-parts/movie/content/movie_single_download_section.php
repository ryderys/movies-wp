<?php
/**
 * Movie single: one row per downloadable quality + subtitle links.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $st_data ) || ! is_object( $st_data ) || ! function_exists( 'streamit_child_build_movie_download_catalog' ) ) {
	return;
}

$catalog = streamit_child_build_movie_download_catalog( $st_data );
if ( empty( $catalog['sources'] ) && empty( $catalog['subtitles'] ) ) {
	return;
}

$can_download = ! empty( $catalog['can_download'] );
streamit_child_enqueue_download_section_styles();

if ( ! $can_download ) {
	streamit_child_render_subscribe_required_modal( $st_data, 'movie', 'download' );
}

global $streamit_options;
$show_share = ! ( isset( $streamit_options['streamit_display_social_icons'] ) && 'no' === $streamit_options['streamit_display_social_icons'] );

$subtitle_groups = function_exists( 'streamit_child_movie_download_subtitle_groups' )
	? streamit_child_movie_download_subtitle_groups( (array) ( $catalog['subtitles'] ?? array() ) )
	: array();
?>
<section class="section-spacing-top stc-dl stc-dl--movie" id="movie-download" aria-labelledby="movie-download-title">
	<div class="container-fluid">
		<div class="stc-dl__box">
			<div class="stc-dl__head">
				<h5 class="main-title text-capitalize mb-0" id="movie-download-title">
					<?php esc_html_e( 'لینک‌های دانلود', 'streamit' ); ?>
				</h5>

				<?php if ( $show_share ) : ?>
					<div class="stc-dl__utils">
						<?php if ( $show_share ) : ?>
							<button type="button" class="btn btn-sm btn-secondary border stc-dl__util-btn" data-bs-toggle="modal" data-bs-target="#shareModal">
								<span class="stc-dl__icon" aria-hidden="true"><?php echo st_get_icon( 'share-2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span><?php esc_html_e( 'اشتراک‌گذاری', 'streamit' ); ?></span>
							</button>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $catalog['sources'] ) ) : ?>
				<ul class="stc-dl__rows">
					<?php foreach ( $catalog['sources'] as $source ) : ?>
						<?php
						/* translators: %s: video quality, e.g. WEB-DL 1080p */
						$download_label = sprintf( __( 'دانلود مستقیم %s', 'streamit' ), $source['quality'] );
						?>
						<li class="stc-dl-row stc-dl-row--movie">
							<div class="stc-dl-row__cell stc-dl-row__cell--quality">
								<span class="stc-dl-row__label"><?php esc_html_e( 'کیفیت:', 'streamit' ); ?></span>
								<bdi class="stc-dl-row__quality"><?php echo esc_html( $source['quality'] ); ?></bdi>
							</div>

							<div class="stc-dl-row__cell stc-dl-row__cell--meta">
								<?php if ( '' !== $source['encoder'] ) : ?>
									<span class="stc-dl-row__field">
										<span class="stc-dl-row__label"><?php esc_html_e( 'انکودر:', 'streamit' ); ?></span>
										<bdi class="stc-dl-row__value"><?php echo esc_html( $source['encoder'] ); ?></bdi>
									</span>
								<?php endif; ?>
								<?php if ( '' !== $source['file_size'] ) : ?>
									<span class="stc-dl-row__field">
										<span class="stc-dl-row__label"><?php esc_html_e( 'حجم:', 'streamit' ); ?></span>
										<bdi class="stc-dl-row__value"><?php echo esc_html( $source['file_size'] ); ?></bdi>
									</span>
								<?php endif; ?>
							</div>

							<div class="stc-dl-row__actions">
								<?php if ( $can_download && '' !== $source['href'] ) : ?>
									<a class="btn btn-primary stc-dl-btn" href="<?php echo esc_url( $source['href'] ); ?>" title="<?php echo esc_attr( $source['title'] ); ?>" aria-label="<?php echo esc_attr( $download_label ); ?>">
										<span class="stc-dl__icon" aria-hidden="true"><?php echo st_get_icon( 'download-2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span><?php esc_html_e( 'دانلود مستقیم', 'streamit' ); ?></span>
									</a>
								<?php elseif ( ! $can_download ) : ?>
									<button type="button" class="btn btn-primary stc-dl-btn" title="<?php echo esc_attr( $source['title'] ); ?>" aria-label="<?php echo esc_attr( $download_label ); ?>" data-bs-toggle="modal" data-bs-target="#subscribeRequiredModal">
										<span class="stc-dl__icon" aria-hidden="true"><?php echo st_get_icon( 'download-2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span><?php esc_html_e( 'دانلود مستقیم', 'streamit' ); ?></span>
									</button>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $subtitle_groups ) ) : ?>
				<?php
				$total_subs = 0;
				foreach ( $subtitle_groups as $sg ) {
					$total_subs += (int) ( $sg['count'] ?? 0 );
				}
				?>
				<details class="stc-dl-series-subs stc-dl-movie-subs">
					<summary class="stc-dl-series-subs__toggle">
						<span class="stc-dl-series-subs__meta">
							<span class="stc-dl-series-subs__title">
								<?php esc_html_e( 'زیرنویس‌ها', 'streamit' ); ?>
							</span>
							<span class="stc-dl-series-subs__badges">
								<?php foreach ( $subtitle_groups as $sg ) : ?>
									<span class="stc-dl-series-subs__badge"><?php echo esc_html( (string) ( $sg['label'] ?? '' ) ); ?></span>
								<?php endforeach; ?>
							</span>
							<span class="stc-dl-series-subs__count">
								<?php
								/* translators: %d: number of subtitle files */
								echo esc_html( sprintf( _n( '%d فایل زیرنویس', '%d فایل زیرنویس', $total_subs, 'streamit' ), $total_subs ) );
								?>
							</span>
						</span>
						<span class="stc-dl-chevron stc-dl-chevron--sm" aria-hidden="true"></span>
					</summary>

					<div class="stc-dl-series-subs__panel">
						<?php foreach ( $subtitle_groups as $sg ) : ?>
							<div class="stc-dl-series-subs__group">
								<div class="stc-dl-series-subs__group-head">
									<span class="stc-dl-series-subs__group-title">
										<?php
										/* translators: %s: release type e.g. WEB-DL or HDTV */
										printf( esc_html__( 'زیرنویس‌های نسخه %s', 'streamit' ), esc_html( (string) ( $sg['label'] ?? '' ) ) );
										?>
									</span>
								</div>

								<div class="stc-dl-series-subs__grid">
									<?php foreach ( (array) ( $sg['languages'] ?? array() ) as $lang ) : ?>
										<?php if ( empty( $lang['items'] ) ) : ?>
											<?php continue; ?>
										<?php endif; ?>
										<div class="stc-dl-series-subs-card">
											<span class="stc-dl-series-subs-card__label"><?php echo esc_html( (string) ( $lang['label'] ?? '' ) ); ?></span>
											<div class="stc-dl-series-subs-card__actions">
												<?php foreach ( (array) ( $lang['items'] ?? array() ) as $item ) : ?>
													<?php if ( $can_download && ! empty( $item['href'] ) ) : ?>
														<a
															href="<?php echo esc_url( (string) $item['href'] ); ?>"
															class="btn btn-sm btn-outline-light stc-dl-series-subs-card__btn"
															title="<?php echo esc_attr( (string) ( $item['title'] ?? '' ) ); ?>"
															download
														>
															<span><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></span>
														</a>
													<?php elseif ( ! $can_download ) : ?>
														<button
															type="button"
															class="btn btn-sm btn-outline-light stc-dl-series-subs-card__btn"
															title="<?php echo esc_attr( (string) ( $item['title'] ?? '' ) ); ?>"
															data-bs-toggle="modal"
															data-bs-target="#subscribeRequiredModal"
														>
															<span><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></span>
														</button>
													<?php else : ?>
														<span class="stc-dl-series-subs-card__btn"><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></span>
													<?php endif; ?>
												<?php endforeach; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</details>
			<?php endif; ?>
		</div>
	</div>
</section>

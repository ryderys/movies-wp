<?php
/**
 * Movie single: quality and subtitle download links.
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
?>
<div class="section-spacing-top stc-series-download stc-movie-download" id="movie-download">
	<div class="container-fluid">
		<div class="d-flex align-items-center justify-content-between mb-md-4 mb-3">
			<h5 class="main-title text-capitalize mb-0">
				<?php esc_html_e( 'لینک‌های دانلود', 'streamit' ); ?>
			</h5>
		</div>

		<div class="stc-series-download-list">
			<div class="stc-series-download-season is-open stc-movie-download-card">
				<div class="stc-series-download-season__header">
					<div class="stc-series-download-season__meta">
						<span class="stc-series-download-season__title"><?php esc_html_e( 'دانلود فیلم', 'streamit' ); ?></span>
						<span class="stc-series-download-season__count">
							<?php
							printf(
								/* translators: %d: number of available video qualities */
								esc_html( _n( '%d کیفیت', '%d کیفیت', count( $catalog['sources'] ), 'streamit' ) ),
								(int) count( $catalog['sources'] )
							);
							?>
						</span>
					</div>
				</div>

				<div class="stc-series-download-season__panel">
					<?php if ( ! empty( $catalog['sources'] ) ) : ?>
						<div class="stc-series-download-qualities" role="list">
							<?php foreach ( $catalog['sources'] as $source ) : ?>
								<?php if ( $can_download && '' !== $source['href'] ) : ?>
									<a class="stc-series-download-chip" role="listitem" href="<?php echo esc_url( $source['href'] ); ?>" title="<?php echo esc_attr( $source['title'] ); ?>">
										<?php echo esc_html( $source['quality'] ); ?>
									</a>
								<?php elseif ( ! $can_download ) : ?>
									<button
										type="button"
										class="stc-series-download-chip"
										role="listitem"
										title="<?php echo esc_attr( $source['title'] ); ?>"
										data-bs-toggle="modal"
										data-bs-target="#subscribeRequiredModal"
									>
										<?php echo esc_html( $source['quality'] ); ?>
									</button>
								<?php else : ?>
									<span class="stc-series-download-chip" role="listitem" title="<?php echo esc_attr( $source['title'] ); ?>">
										<?php echo esc_html( $source['quality'] ); ?>
									</span>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $catalog['subtitles'] ) ) : ?>
						<div class="stc-series-download-subs">
							<span class="stc-series-download-subs__label"><?php esc_html_e( 'زیرنویس', 'streamit' ); ?></span>
							<div class="stc-series-download-subs__links">
								<?php foreach ( $catalog['subtitles'] as $subtitle ) : ?>
									<?php if ( $can_download && '' !== $subtitle['href'] ) : ?>
										<a class="stc-series-download-sub-link" href="<?php echo esc_url( $subtitle['href'] ); ?>" download>
											<?php echo esc_html( $subtitle['label'] ); ?>
										</a>
									<?php elseif ( ! $can_download ) : ?>
										<button type="button" class="stc-series-download-sub-link" data-bs-toggle="modal" data-bs-target="#subscribeRequiredModal">
											<?php echo esc_html( $subtitle['label'] ); ?>
										</button>
									<?php else : ?>
										<span class="stc-series-download-sub-link">
											<?php echo esc_html( $subtitle['label'] ); ?>
										</span>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

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
?>
<section class="section-spacing-top stc-dl stc-dl--movie" id="movie-download" aria-labelledby="movie-download-title">
	<div class="container-fluid">
		<div class="stc-dl__box">
			<div class="stc-dl__head">
				<h5 class="main-title text-capitalize mb-0" id="movie-download-title">
					<?php esc_html_e( 'لینک‌های دانلود', 'streamit' ); ?>
				</h5>

				<?php if ( $show_share || ! empty( $catalog['subtitles'] ) ) : ?>
					<div class="stc-dl__utils">
						<?php if ( ! empty( $catalog['subtitles'] ) ) : ?>
							<div class="stc-dl__subs">
								<span class="stc-dl__subs-label"><?php esc_html_e( 'زیرنویس:', 'streamit' ); ?></span>
								<?php foreach ( $catalog['subtitles'] as $subtitle ) : ?>
									<?php if ( $can_download && '' !== $subtitle['href'] ) : ?>
										<a class="btn btn-sm btn-secondary border stc-dl__sub-link" href="<?php echo esc_url( $subtitle['href'] ); ?>" download>
											<?php echo esc_html( $subtitle['label'] ); ?>
										</a>
									<?php elseif ( ! $can_download ) : ?>
										<button type="button" class="btn btn-sm btn-secondary border stc-dl__sub-link" data-bs-toggle="modal" data-bs-target="#subscribeRequiredModal">
											<?php echo esc_html( $subtitle['label'] ); ?>
										</button>
									<?php else : ?>
										<span class="stc-dl__sub-link"><?php echo esc_html( $subtitle['label'] ); ?></span>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

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
		</div>
	</div>
</section>

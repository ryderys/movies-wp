<?php
/**
 * Series single: season download links section (compact episode grid).
 *
 * Season toggle shows episodes (no ZIP). Episode click reveals qualities/subs
 * in a shared panel populated from JSON — qualities are not pre-rendered for
 * every episode.
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

$catalog = streamit_child_build_series_download_catalog( $st_data );
if ( empty( $catalog['seasons'] ) ) {
	return;
}

$can_download = ! empty( $catalog['can_download'] );
streamit_child_enqueue_series_download_assets();

if ( ! $can_download ) {
	streamit_child_render_subscribe_required_modal( $st_data, 'tvshow', 'download' );
}

$ui_i18n = array(
	'downloadEpisode' => __( 'دانلود قسمت %d', 'streamit' ),
	'emptyMedia'      => __( 'رسانه دانلودی موجود نیست', 'streamit' ),
	'subtitles'       => __( 'زیرنویس', 'streamit' ),
	'download'        => __( 'دانلود', 'streamit' ),
	'showMore'        => __( 'نمایش قسمت‌های بیشتر', 'streamit' ),
);
?>
<div
	class="section-spacing-top stc-series-download"
	data-stc-series-download
	data-can-download="<?php echo $can_download ? '1' : '0'; ?>"
	data-page-size-desktop="24"
	data-page-size-mobile="12"
>
	<div class="container-fluid">
		<div class="d-flex align-items-center justify-content-between mb-md-4 mb-3">
			<h5 class="main-title text-capitalize mb-0">
				<?php esc_html_e( 'لینک‌های دانلود', 'streamit' ); ?>
			</h5>
		</div>

		<script type="application/json" class="stc-series-download-i18n">
			<?php echo wp_json_encode( $ui_i18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ); ?>
		</script>

		<div class="stc-series-download-list">
			<?php foreach ( $catalog['seasons'] as $season_i => $season ) : ?>
				<?php
				$panel_id   = 'stc-series-dl-panel-' . (int) $season['index'];
				$detail_id  = 'stc-series-dl-detail-' . (int) $season['index'];
				$count      = (int) $season['downloadable_episode_count'];
				$is_first   = ( 0 === (int) $season_i );
				/* translators: %d: number of episodes with downloads */
				$count_label = sprintf(
					_n( '%d قسمت قابل دانلود', '%d قسمت قابل دانلود', $count, 'streamit' ),
					$count
				);

				$episodes_ui = array();
				foreach ( array_values( $season['episodes'] ) as $ep_i => $episode ) {
					$episodes_ui[] = streamit_child_series_download_episode_ui_payload(
						$episode,
						$ep_i + 1
					);
				}
				?>
				<div
					class="stc-series-download-season<?php echo $is_first ? ' is-open' : ''; ?>"
					data-season-index="<?php echo esc_attr( (string) (int) $season['index'] ); ?>"
				>
					<div class="stc-series-download-season__header">
						<button
							type="button"
							class="stc-series-download-season__toggle"
							data-stc-season-toggle
							aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>"
							aria-controls="<?php echo esc_attr( $panel_id ); ?>"
						>
							<span class="stc-series-download-season__meta">
								<span class="stc-series-download-season__title"><?php echo esc_html( $season['name'] ); ?></span>
								<span class="stc-series-download-season__count"><?php echo esc_html( $count_label ); ?></span>
							</span>
							<span class="stc-series-download-season__actions">
								<span class="btn btn-primary stc-series-download-season__action-label">
									<?php esc_html_e( 'نمایش قسمت‌ها', 'streamit' ); ?>
								</span>
								<span class="stc-series-download-chevron" aria-hidden="true">
									<span class="stc-series-download-chevron__icon"></span>
								</span>
							</span>
						</button>
					</div>

					<div
						id="<?php echo esc_attr( $panel_id ); ?>"
						class="stc-series-download-season__panel"
						<?php echo $is_first ? '' : 'hidden'; ?>
					>
						<script type="application/json" class="stc-series-download-season-data">
							<?php echo wp_json_encode( $episodes_ui, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ); ?>
						</script>

						<div
							class="stc-series-download-grid"
							data-stc-episode-grid
							role="list"
						></div>

						<div
							id="<?php echo esc_attr( $detail_id ); ?>"
							class="stc-series-download-detail"
							data-stc-episode-detail
							hidden
						></div>

						<div class="stc-series-download-more-wrap" data-stc-more-wrap hidden>
							<button type="button" class="btn btn-secondary stc-series-download-more" data-stc-show-more>
								<?php esc_html_e( 'نمایش قسمت‌های بیشتر', 'streamit' ); ?>
							</button>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

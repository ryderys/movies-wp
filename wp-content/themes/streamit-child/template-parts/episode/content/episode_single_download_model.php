<?php
/**
 * Download modal for episodes (child override — adds subtitle downloads).
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

if ( ! streamit_child_user_can_download( $st_data, 'episode' ) ) {
	return;
}

$sources   = (array) $st_data->get_meta( '_sources' );
$subs      = streamit_child_get_subtitles( $st_data );
$has_video = function_exists( 'streamit_child_get_downloadable_sources' )
	? ! empty( streamit_child_get_downloadable_sources( $sources ) )
	: ! empty( $sources );

if ( ! $has_video && empty( $subs ) ) {
	return;
}

$post_id = (int) $st_data->get_id();
$valid_sources = function_exists( 'streamit_child_get_downloadable_sources' )
	? streamit_child_get_downloadable_sources( $sources )
	: array_filter(
		$sources,
		static function ( $src ) {
			$quality  = isset( $src['quality'] ) ? trim( (string) $src['quality'] ) : '';
			$download = isset( $src['download_content'] ) ? trim( (string) $src['download_content'] ) : '';
			if ( '' === $download && isset( $src['link'] ) ) {
				$download = trim( (string) $src['link'] );
			}
			return '' !== $quality && '' !== $download;
		}
	);
?>

<div class="modal downloadModal fade st-download-modal" id="downloadModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-xl playlist-modal stc-download-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title m-0" id="downloadModalLabel">
					<?php esc_html_e( 'دانلود', 'streamit' ); ?>
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'streamit' ); ?>"></button>
			</div>

			<div class="modal-body pt-0">
				<?php if ( ! empty( $valid_sources ) ) : ?>
					<ul class="list-inline m-0 p-0 downloadModal-list">
						<?php foreach ( $valid_sources as $source ) : ?>
							<li>
								<div class="stc-download-row">
									<div class="stc-download-info">
										<span class="stc-download-quality"><?php echo esc_html( $source['quality'] ); ?></span>
										<?php if ( '' !== trim( (string) ( $source['language'] ?? '' ) ) ) : ?>
											<span class="stc-download-lang"><?php echo esc_html( $source['language'] ); ?></span>
										<?php endif; ?>
										<?php streamit_child_render_download_source_meta( $source ); ?>
									</div>
									<div class="stc-download-action">
										<span class="stc-download-icon" aria-hidden="true">
											<?php echo st_get_icon( 'download-2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</span>
										<?php
										$dl_href = function_exists( 'streamit_child_resolve_download_href' )
											? streamit_child_resolve_download_href(
												$source['download_content'],
												$post_id,
												isset( $source['source_index'] ) ? (int) $source['source_index'] : 0
											)
											: $source['download_content'];
										?>
										<a href="<?php echo esc_url( $dl_href ); ?>" class="stc-download-btn link-primary">
											<?php esc_html_e( 'دانلود', 'streamit' ); ?>
										</a>
									</div>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php elseif ( empty( $subs ) ) : ?>
					<p class="text-muted text-center m-0">
						<?php esc_html_e( 'No downloadable content available.', 'streamit' ); ?>
					</p>
				<?php endif; ?>

				<?php streamit_child_render_subtitle_download_section( $subs ); ?>
			</div>
		</div>
	</div>
</div>

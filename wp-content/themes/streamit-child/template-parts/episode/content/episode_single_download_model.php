<?php
/**
 * Download modal for episodes (child override — adds subtitle downloads).
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

$sources   = (array) $st_data->get_meta( '_sources' );
$subs      = streamit_child_get_subtitles( $st_data );
$has_video = function_exists( 'streamit_child_get_downloadable_sources' )
	? ! empty( streamit_child_get_downloadable_sources( $sources ) )
	: ! empty( $sources );

if ( ! $has_video && empty( $subs ) ) {
	return;
}

$valid_sources = function_exists( 'streamit_child_get_downloadable_sources' )
	? streamit_child_get_downloadable_sources( $sources )
	: array_filter(
		$sources,
		static function ( $src ) {
			return ! empty( $src['quality'] ) && ! empty( $src['language'] ) && ! empty( $src['download_content'] );
		}
	);
?>

<div class="modal downloadModal fade st-download-modal" id="downloadModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered playlist-modal">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title m-0" id="downloadModalLabel">
					<?php esc_html_e( 'دانلود', 'streamit' ); ?>
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'streamit' ); ?>"></button>
			</div>

			<div class="modal-body pt-0">
				<?php if ( ! empty( $valid_sources ) ) : ?>
					<h6 class="stc-subtitle-title mb-2"><?php esc_html_e( 'کیفیت ویدیو', 'streamit' ); ?></h6>
					<ul class="list-inline m-0 p-0 downloadModal-list">
						<?php foreach ( $valid_sources as $source ) : ?>
							<li>
								<div class="d-flex align-items-center justify-content-between">
									<div class="flex-grow-1">
										<h6 class="mt-0 mb-1"><?php echo esc_html( $source['quality'] ); ?></h6>
										<p class="m-0 small"><?php echo esc_html( $source['language'] ); ?></p>
									</div>
									<div class="flex-shrink-0">
										<a href="<?php echo esc_url( $source['download_content'] ); ?>" class="link-primary" download>
											<?php echo st_get_icon( 'download-2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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

<?php
/**
 * Episode download quality modal.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sources       = (array) $st_data->get_meta( '_sources' );
$valid_sources = streamit_child_get_downloadable_sources( $sources );

if ( empty( $valid_sources ) ) {
	return;
}
?>

<div class="modal downloadModal fade st-download-modal" id="downloadModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered playlist-modal">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title m-0" id="downloadModalLabel">
					انتخاب کیفیت دانلود
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
			</div>

			<div class="modal-body pt-0">
				<ul class="list-inline m-0 p-0 downloadModal-list">
					<?php foreach ( $valid_sources as $source ) : ?>
						<li>
							<div class="d-flex align-items-center justify-content-between">
								<div class="flex-grow-1">
									<h6 class="mt-0 mb-1"><?php echo esc_html( $source['quality'] ); ?></h6>
									<p class="m-0 small"><?php echo esc_html( $source['language'] ); ?></p>
									<?php if ( ! empty( $source['name'] ) && $source['name'] !== $source['quality'] ) : ?>
										<p class="m-0 small text-muted"><?php echo esc_html( $source['name'] ); ?></p>
									<?php endif; ?>
								</div>
								<div class="flex-shrink-0">
									<a href="<?php echo esc_url( $source['download_content'] ); ?>" class="link-primary" download>
										<?php echo st_get_icon( 'download-2' ); ?>
									</a>
								</div>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
</div>

<?php
/**
 * Trailer modal markup for click-to-play trailers.
 *
 * Source MIME is set from data-trailer-mime at click time.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;
?>
<div
	class="modal trailerModal fade"
	id="trailerModal"
	tabindex="-1"
	role="dialog"
	aria-modal="true"
	aria-labelledby="streamitTrailerModalTitle"
	aria-hidden="true"
>
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-body">
				<h2 id="streamitTrailerModalTitle" class="visually-hidden">
					<?php esc_html_e( 'تماشای تریلر', 'streamit' ); ?>
				</h2>
				<div class="streamit_trailer_player">
					<iframe
						id="trailerIframe"
						src=""
						title="<?php esc_attr_e( 'Trailer video player', 'streamit' ); ?>"
						allowfullscreen
						allow="autoplay"
						loading="lazy"
						style="display:none;"
					></iframe>

					<video
						id="trailerVideo"
						controls
						playsinline
						preload="metadata"
						style="display:none; width:100%; height:auto;"
					>
						<source src="" type="">
						<?php esc_html_e( 'Your browser does not support HTML5 video.', 'streamit' ); ?>
					</video>
				</div>
			</div>
			<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'streamit' ); ?>"></button>
		</div>
	</div>
</div>

<?php
/**
 * Modal shown when play/download requires login or an active membership plan.
 *
 * @package streamit-child
 *
 * @var string $subscribe_url Plans page URL.
 * @var string $login_url     Login page URL.
 * @var string $signup_url    Registration page URL.
 * @var string $context       'play' or 'download'.
 */

defined( 'ABSPATH' ) || exit;

$is_logged_in = is_user_logged_in();
$title        = $is_logged_in ? __( 'اشتراک لازم است', 'streamit' ) : __( 'ورود یا ثبت‌نام لازم است', 'streamit' );
$message      = $is_logged_in
	? __( 'برای پخش یا دانلود این محتوا باید یکی از طرح‌های اشتراک را فعال کنید.', 'streamit' )
	: __( 'برای پخش یا دانلود این محتوا ابتدا باید ثبت‌نام کنید یا وارد حساب کاربری خود شوید.', 'streamit' );
$cta_label    = __( 'مشاهده طرح‌های اشتراک', 'streamit' );
?>
<div class="modal fade st-subscribe-required-modal" id="subscribeRequiredModal" tabindex="-1" aria-labelledby="subscribeRequiredModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title m-0" id="subscribeRequiredModalLabel"><?php echo esc_html( $title ); ?></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'streamit' ); ?>"></button>
			</div>
			<div class="modal-body">
				<p class="st-subscribe-required-modal__message mb-0"><?php echo esc_html( $message ); ?></p>
			</div>
			<div class="modal-footer flex-wrap gap-2">
				<?php if ( ! $is_logged_in ) : ?>
					<a class="btn btn-outline-secondary" href="<?php echo esc_url( $signup_url ); ?>">
						<?php esc_html_e( 'ثبت‌نام', 'streamit' ); ?>
					</a>
					<a class="btn btn-primary" href="<?php echo esc_url( $login_url ); ?>">
						<?php esc_html_e( 'ورود', 'streamit' ); ?>
					</a>
				<?php else : ?>
					<a class="btn btn-primary" href="<?php echo esc_url( $subscribe_url ); ?>">
						<?php echo esc_html( $cta_label ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
<script>
(function () {
	if (window.streamitSubscribeModalBackdropFix) {
		return;
	}
	window.streamitSubscribeModalBackdropFix = true;

	function removeStaleBackdrops() {
		document.querySelectorAll('.modal-backdrop:not(.streamit-search-modal-backdrop)').forEach(function (backdrop) {
			backdrop.remove();
		});
	}

	document.addEventListener('click', function (event) {
		var trigger = event.target.closest('[data-bs-target="#subscribeRequiredModal"]');
		if (trigger) {
			removeStaleBackdrops();
		}
	});

	document.addEventListener('hidden.bs.modal', function (event) {
		if (event.target.id === 'subscribeRequiredModal' && !document.querySelector('.modal.show')) {
			removeStaleBackdrops();
		}
	});
})();
</script>

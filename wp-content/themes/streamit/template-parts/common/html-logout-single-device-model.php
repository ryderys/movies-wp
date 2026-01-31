<?php
/**
 * Logout Single Device Confirmation Modal
 *
 * @package Streamit
 * @since 1.0.0
 */
?>

<!-- Logout Single Device Confirmation Modal -->
<div class="modal fade" id="logoutSingleDeviceModal" tabindex="-1" aria-labelledby="logoutSingleDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="logoutSingleDeviceModalLabel">
                    <?php esc_html_e('Logout Device', 'streamit'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0 mt-5">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="icon-alert-triangle text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="mb-3"><?php esc_html_e('Are you sure you want to logout this device?', 'streamit'); ?></h6>
                    <p class="text-muted mb-0">
                        <?php esc_html_e('This will log the selected device out of your account. You will remain logged in on your current device.', 'streamit'); ?>
                    </p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">
                    <?php esc_html_e('Cancel', 'streamit'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="confirmLogoutSingleBtn" data-device-id="">
                    <span class="d-flex align-items-center gap-2">
                        <span class="btn-text"><?php esc_html_e('Logout Device', 'streamit'); ?></span>
                        <span class="btn-loader spinner-border spinner-border-sm" style="display: none;"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

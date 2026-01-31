<?php
/**
 * Logout All Devices Confirmation Modal
 * 
 * @package Streamit
 * @since 1.0.0
 */
?>

<!-- Logout All Devices Confirmation Modal -->
<div class="modal fade" id="logoutAllDevicesModal" tabindex="-1" aria-labelledby="logoutAllDevicesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="logoutAllDevicesModalLabel">
                    Logout All Devices
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0 mt-5">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="icon-alert-triangle text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="mb-3">Are you sure you want to logout from all devices?</h6>
                    <p class="text-muted mb-0">
                        This will log you out from all devices except your current device. 
                        You'll need to login again on any other device you want to use.
                    </p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmLogoutAllBtn">
                    <span class="d-flex align-items-center gap-2">
                        <span class="btn-text">Logout All Devices</span>
                        <span class="btn-loader spinner-border spinner-border-sm" style="display: none;"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

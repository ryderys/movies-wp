<?php
/**
 * Logout All Devices Confirmation Modal
 * 
 * @package Streamit
 * @since 1.0.0
 */
?>

<!-- Logout All Devices Confirmation Modal -->
<div class="modal fade" id="DeleteAccountModal" tabindex="-1" aria-labelledby="DeleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="DeleteAccountModalLabel">
                    Delete Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0 mt-5">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="icon-user text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="mb-3">Are you sure you want to delete your account?</h6>
                    <p class="text-muted mb-0">
                        This will delete your account and all your data will be permanently removed and cannot be recovered.
                    </p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmDeleteAccountBtn">
                    <span class="d-flex align-items-center gap-2">
                        <span class="btn-text">Delete My Account</span>
                        <span class="btn-loader spinner-border spinner-border-sm" style="display: none;"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>


<?php

/**
 * The template for displaying the you are removed modal.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<!-- You are removed modal -->
<div class="modal downloadModal fade st-download-modal" id="youAreRemovedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered playlist-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e('Close', 'streamit'); ?>"></button>
            </div>

            <div class="modal-body pt-0">
                <div class="text-center py-4">
                    <div class="mb-4">
                        <i class="icon-logout text-danger me-2" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="mb-3"><?php esc_html_e('You’ve Been Removed', 'streamit'); ?></h6>
                    <p class="text-muted mb-3">
                        <?php esc_html_e('Your access has been removed by another user. You can no longer continue in this session..', 'streamit'); ?>
                    </p>
                </div>
            </div>
            <div class="modal-footer d-flex align-items-center justify-content-center gap-2">
                <a class="btn btn-primary" href="<?php echo esc_url(home_url('/')); ?>">
                    <?php esc_html_e('Go to Home', 'streamit'); ?>
                </a>
                <a class="btn btn-outline-primary" href="javascript:history.back()">
                    <?php esc_html_e('Back', 'streamit'); ?>
                </a>
            </div>  
        </div>
    </div>
</div>
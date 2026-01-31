<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Device Management Template -->
<div class="login-device-wrapper" style="display: none;">
    <h5 class="mb-4"></h5> <!-- Title will be set dynamically -->
    
    <!-- Loading State -->
    <div class="device-loading-state text-center py-4" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden"><?php esc_html_e('Loading...', 'streamit'); ?></span>
        </div>
    </div>
    
    <!-- Success State -->
    <div class="device-success-state text-center py-4" style="display: none;">
        <i class="icon-check-2 fs-1 mb-1"></i>
        <p class="text-success mb-0 fw-bold"><?php esc_html_e('Login Successful!', 'streamit'); ?></p>
    </div>
    
    <!-- Device List -->
    <ul class="list-inline m-0 p-0 login-device-list">
        <!-- Device items will be dynamically inserted here -->
    </ul>
</div>

<?php
// Include the common device item template
streamit_get_template('common/html-device-item-template.php');


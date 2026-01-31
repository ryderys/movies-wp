<?php
defined('ABSPATH') || exit;

$user_id = get_current_user_id();

?>
<div class="my-5 text-center" id="device-loading-spinner">
    <div class="spinner-border" role="status">
        <span class="visually-hidden"><?php esc_html_e('Loading...', 'streamit'); ?></span>
    </div>
</div>

<div class="card mb-5" id="device-management-content" style="display: none;">
    <div class="card-body">
        <!-- Hidden field for current user ID -->
        <input type="hidden" id="current-user-id" value="<?php echo esc_attr($user_id); ?>" />
        
        <div class="d-flex align-items-center gap-3">
            <i class="icon-shield fs-4 text-primary"></i>
            <h4 class="m-0"><?php esc_html_e('Device Management', 'streamit'); ?></h4>
        </div>

        <div class="mt-5 pt-2 d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-1" id="plan-name"><?php esc_html_e('Standard Plan', 'streamit'); ?></h5>
                <p class="m-0 font-size-14" id="device-usage"><?php esc_html_e('0 of 0 devices used', 'streamit'); ?></p>
            </div>
            <div>
                <span class="badge text-bg-primary p-3 font-size-14" id="device-badge"><?php esc_html_e('0/0 Devices', 'streamit'); ?></span>
            </div>
        </div>

        <div class="mt-5" id="device-usage-progress-section">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-1">
                <span class="font-size-14"><?php esc_html_e('Usage', 'streamit'); ?></span>
                <span class="font-size-14" id="usage-percentage"><?php esc_html_e('0%', 'streamit'); ?></span>
            </div>
            <div class="progress" role="progressbar" aria-label="Device usage" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-primary" id="usage-progress-bar" style="width: 0%"></div>
            </div>
        </div>
    </div>
</div>

<div class="my-5" id="device-limit-warning" style="display: none;">
    <div class="alert alert-warning text-white d-flex align-items-center gap-3" role="alert">
        <i class="icon-alert-triangle"></i>
        <span><?php esc_html_e('You have reached your device limit. Please log out of a device to add a new one.', 'streamit'); ?></span>
    </div>
</div>

<!-- General Success Message Area -->
<div class="general-success-message" style="display: none;">
	<div class="alert alert-success" role="alert">
		<span class="success-text"></span>
	</div>
</div>

<div class="active-devices" id="active-devices-section" style="display: none;">
    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <h4 class="mb-0"><?php esc_html_e('Active Devices', 'streamit'); ?></h4>
        <button class="btn btn-sm btn-outline-primary logout-all-devices-btn" type="button">
            <i class="icon-logout"></i> <?php esc_html_e('Logout All', 'streamit'); ?>
        </button>
    </div>
    
    <!-- Devices List -->
    <ul class="list-inline m-0 p-0 d-flex flex-column gap-3 active-devices-list" id="devices-list">
        <!-- Devices will be dynamically loaded here -->
    </ul>
</div>

<!-- Device Item Template (Hidden) -->
<template id="device-item-template">
    <li>
        <div class="login-device-card-2 p-4 rounded">
            <div class="d-flex align-items-sm-center row-gap-5 column-gap-3 flex-sm-row flex-column">
                <div class="flex-grow-1 d-flex gap-4">
                    <i class="device-icon flex-shrink-0 fs-3"></i>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <h5 class="mb-0 device-name"><?php esc_html_e('Device Name', 'streamit'); ?></h5>
                            <span class="badge text-bg-primary current-device-badge" style="display: none;"><?php esc_html_e('Current Device', 'streamit'); ?></span>
                        </div>
                        <p class="m-0 font-size-14 last-used"><?php esc_html_e('Last active: Unknown','streamit'); ?></p>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <button class="btn btn-primary device-logout-btn" data-device-id="" data-user-id="<?php echo esc_attr($user_id); ?>">
                        <span class="d-flex align-items-center gap-2">
                            <span class="btn-text"><?php esc_html_e('Log out', 'streamit'); ?></span>
                            <span class="btn-loader spinner-border spinner-border-sm" style="display: none;"></span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
     </li>
 </template>

<?php
// Include the logout all devices confirmation modal
get_template_part('template-parts/common/html-logout-all-devices-model');
// Include the logout single device confirmation modal
get_template_part('template-parts/common/html-logout-single-device-model');

<?php
/**
 * Common Device Item Template
 * 
 * This template is used across multiple components for displaying device items
 * in device management interfaces.
 *
 * @package streamit
 */

defined('ABSPATH') || exit;
?>

<!-- Device Item Template -->
<template id="device-item-template">
    <li>
        <div class="login-device-card rounded p-3 d-flex align-items-center justify-content-between gap-2">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-sm-3 gap-2">
                    <span class="flex-shrink-0">
                        <i class="device-icon fs-4"></i>
                    </span>
                    <div>
                        <h6 class="font-size-14 mb-1 device-name"><?php esc_html_e('Device Name', 'streamit'); ?></h6>
                        <span class="login-info">
                            <span><?php esc_html_e('Last used', 'streamit'); ?></span>
                            <span class="last-used"><?php esc_html_e('Unknown', 'streamit'); ?></span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0">
                <button class="btn btn-secondary px-3 py-2 device-logout-btn" 
                        data-device-id=""
                        data-username="">
                    <span class="btn-text"><?php esc_html_e('Log out', 'streamit'); ?></span>
                    <span class="btn-loader" style="display: none;">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden"><?php esc_html_e('Loading...', 'streamit'); ?></span>
                        </div>
                    </span>
                </button>
            </div>
        </div>
    </li>
</template>

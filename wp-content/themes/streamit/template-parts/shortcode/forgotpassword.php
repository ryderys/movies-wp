<?php

/**
 * Custom Forgot Password Template for Streamit.
 *
 * Template Name: Streamit Forgot Password
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}
global $streamit_options;

$site_name = get_bloginfo('name');
?>


<div class="streamit-registration">
    <?php
    $logo_url = isset($streamit_options['streamit_logo']['url']) && ! empty($streamit_options['streamit_logo']['url']) ? esc_url($streamit_options['streamit_logo']['url']) : get_template_directory_uri() . '/static/assets/images/logo.png'; ?>
    <a href="<?php echo esc_url(home_url()); ?>">
        <img class="img-fluid logo" src="<?php echo esc_url($logo_url) ?> " alt=" <?php echo esc_html($site_name) ?> ">
    </a>

    <form id="streamit-forgot-password-form" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="POST">
        <?php
        do_action('streamit_before_forgot_password_fields');

        // Loop through the form fields and render the HTML.
        if (!empty($form_fields)) :
        ?>
            <div class="registration-fields row">
                <?php foreach ($form_fields as $field_name => $field_details) : ?>
                    <div class="mb-3 <?php echo esc_attr($field_details['parent_class']); ?>">
                        <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_details['text']); ?>
                            <?php if (!empty($field_details['required'])) : ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        <input
                            type="<?php echo esc_attr($field_details['type']); ?>"
                            id="<?php echo esc_attr($field_name); ?>"
                            name="<?php echo esc_attr($field_name); ?>"
                            placeholder="<?php echo esc_attr($field_details['placeholder']); ?>"
                            class="form-control <?php echo esc_attr($field_details['class']); ?>"
                            <?php echo $field_details['required'] ? 'required' : ''; ?> />
                        <div class="field-error-message text-danger small mt-1" style="display: none;"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;

        wp_nonce_field('streamit_forgot_password', 'st_ajax_nonce');

        do_action('streamit_after_forgot_password_fields');
        ?>

        <div class="general-error-message" style="display: none;">
            <div class="alert alert-danger" role="alert">
                <span class="error-text"></span>
            </div>
        </div>

        <div class="general-success-message" style="display: none;">
            <div class="alert alert-success" role="alert">
                <span class="success-text"></span>
            </div>
        </div>

        <div class="submit">
            <button type="submit" class="btn btn-primary w-100" id="forgot-password-submit-btn">
                <span class="btn-text"><?php esc_html_e('Submit', 'streamit'); ?></span>
                <span class="btn-loader" style="display: none;">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden"><?php esc_html_e('Loading...', 'streamit'); ?></span>
                    </div>
                </span>
            </button>
        </div>

        <div class="login-form-bottom">
            <div class="d-flex justify-content-center align-items-center gap-2 links my-3">
                <?php esc_html_e('Got your password now?', 'streamit'); ?>
                <a href="<?php echo esc_url(streamit_login_page_url()); ?>" class="st-sub-card setting-dropdown">
                    <h6 class="m-0 text-primary">
                        <?php esc_html_e('Sing In', 'streamit'); ?>
                    </h6>
                </a>
            </div>
        </div>

    </form>
</div>
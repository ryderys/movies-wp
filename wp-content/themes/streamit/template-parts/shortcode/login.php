<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

global $streamit_options;
$site_name = get_bloginfo('name');
$atts = get_query_var('login_atts', array());
?>

<div class="streamit-login">
    <?php
    $logo_url = isset($streamit_options['streamit_logo']['url']) && ! empty($streamit_options['streamit_logo']['url']) ? esc_url($streamit_options['streamit_logo']['url']) : get_template_directory_uri() . '/static/assets/images/logo.png'; ?>
    <a href="<?php echo esc_url(home_url()); ?>">
        <img class="img-fluid logo" src="<?php echo esc_url($logo_url) ?> " alt=" <?php echo esc_html($site_name) ?> ">
    </a>

    <form id="streamit-login-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST">
        <?php

        // Render login fields dynamically if they exist
        do_action('streamit_before_login_fields');

        if (!empty($login_form_fields)) : ?>
            <div class="login-fields row">
                <?php foreach ($login_form_fields as $field_name => $field_details) : ?>
                    <div class="mb-3 position-relative <?php echo ($field_details['type'] === 'checkbox') ? 'col-6' : esc_attr($field_details['parent_class']); ?>">
                        <?php if ($field_details['type'] === 'checkbox') : ?>
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    id="<?php echo esc_attr($field_name); ?>"
                                    name="<?php echo esc_attr($field_name); ?>"
                                    value="<?php echo isset($field_details['value']) ? esc_attr($field_details['value']) : '1'; ?>"
                                    class="form-check-input <?php echo esc_attr($field_details['class']); ?>"
                                    <?php echo $field_details['required'] ? 'required' : ''; ?> />
                                <label class="form-check-label" for="<?php echo esc_attr($field_name); ?>">
                                    <?php echo esc_html($field_details['text']); ?>
                                    <?php if (!empty($field_details['required'])) : ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php else : ?>
                            <label for="<?php echo esc_attr($field_name); ?>">
                                <?php echo esc_html($field_details['text']); ?>
                                <?php if (!empty($field_details['required'])) : ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            <input
                                type="<?php echo esc_attr($field_details['type']); ?>"
                                id="<?php echo esc_attr($field_name); ?>"
                                name="<?php echo esc_attr($field_name); ?>"
                                value="<?php echo isset($field_details['value']) ? esc_attr($field_details['value']) : ''; ?>"
                                class="form-control <?php echo esc_attr($field_details['class']); ?>"
                                <?php echo $field_details['required'] ? 'required' : ''; ?>
                                placeholder="<?php echo !empty($field_details['placeholder']) ? esc_attr($field_details['placeholder']) : ''; ?>" />
                        <?php endif; ?>
                        <div class="field-error-message text-danger small mt-1" style="display: none;"></div>
                    </div>
                <?php endforeach; ?>

                <div class="forgot-password mb-3 col-6">
                    <a href="<?php echo esc_url(streamit_forgot_page_url()); ?>">
                        <?php esc_html_e('Forgot your password?', 'streamit'); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>


        <?php wp_nonce_field('streamit_login', 'st_login_nonce'); ?>

        <?php do_action('streamit_after_login_fields'); ?>

        <div class="general-error-message" style="display: none;">
            <div class="alert alert-danger" role="alert">
                <span class="error-text"></span>
            </div>
        </div>

        <div class="submit">
            <button type="submit" class="btn btn-primary w-100" id="login-submit-btn">
                <span class="btn-text"><?php esc_html_e('Login', 'streamit'); ?></span>
                <span class="btn-loader" style="display: none;">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden"><?php esc_html_e('Loading...', 'streamit'); ?></span>
                    </div>
                </span>
            </button>
        </div>

        <?php if (isset($streamit_options['streamit_signup_link'])) :
            $signup_link = streamit_signup_page_url(); ?>
            <?php $singup_title = (isset($streamit_options['streamit_signup_title']) && !empty($streamit_options['streamit_signup_title'])) ? $streamit_options['streamit_signup_title'] : esc_html__('Signup', 'streamit'); ?>
            <div class="login-form-bottom">
                <div class="d-flex justify-content-center align-items-center gap-2 links my-3">
                    <?php esc_html_e('If You are new? ', 'streamit'); ?>
                    <a href="<?php echo esc_url($signup_link) ?>" class="st-sub-card setting-dropdown">
                        <h6 class="m-0 text-primary">
                            <?php echo esc_html($singup_title); ?>
                        </h6>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="css_prefix-separator">
            <span class="or-section"><?php esc_html_e("  OR  ", 'streamit'); ?> </span>
        </div>
        <?php if (shortcode_exists('miniorange_social_login')) { ?>
            <div class="css_prefix-social-login-section">
                <?php echo do_shortcode('[miniorange_social_login]'); ?>
            </div>
        <?php } ?>
    </form>

    <?php
    // Include device management template
    include get_template_directory() . '/template-parts/shortcode/device-management.php';
    ?>

</div>
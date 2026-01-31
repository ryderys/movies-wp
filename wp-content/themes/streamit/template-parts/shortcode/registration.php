<?php

/**
 * Custom Registration Template for Streamit.
 *
 * Template Name: Streamit Registration
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}
// Include header if necessary.
global $streamit_options;
$site_name = get_bloginfo('name');

?>



<div class="streamit-registration">
    <?php
    $logo_url = isset($streamit_options['streamit_logo']['url']) && ! empty($streamit_options['streamit_logo']['url']) ? esc_url($streamit_options['streamit_logo']['url']) : get_template_directory_uri() . '/static/assets/images/logo.png'; ?>
    <a href="<?php echo esc_url(home_url()); ?>">
        <img class="img-fluid logo" src="<?php echo esc_url($logo_url) ?> " alt=" <?php echo esc_html($site_name) ?> ">
    </a>

    <?php
    // Check if user registration is allowed
    if (!get_option('users_can_register')) {
        echo '<p class="no_data_found">' . esc_html__('User registration is currently disabled.', 'streamit') . '</p></div>';
        return;
    }
    ?>

    <form id="streamit-registration-form" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="POST">
        <input type="hidden" name="action" value="st_ajax_post" />

        <?php
        // Add custom hooks for additional content before the registration fields.
        do_action('streamit_before_registration_fields');

        // Loop through the form fields and render the HTML.
        if (!empty($form_fields)) :
        ?>
            <div class="registration-fields row">
                <?php foreach ($form_fields as $field_name => $field_details) : ?>
                    <div class="mb-3 <?php echo esc_attr($field_details['parent_class']); ?>">
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
                            placeholder="<?php echo esc_attr($field_details['placeholder']); ?>"
                            class="form-control <?php echo esc_attr($field_details['class']); ?>"
                            <?php echo !empty($field_details['required']) ? 'required' : ''; ?> />

                        <div class="field-error-message text-danger small mt-1" style="display: none;"></div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endif;

        // Check for terms and conditions requirement
        if ($register_atts['terms_required'] === 'true') : ?>
            <div class="col-xl-12">
                <p class="login-remember d-flex align-items-center gap-1">
                    <input type="checkbox" class="st-term mb-0" name="st_term_condition" id="iqonic_term_condition" value="accepted">
                    <label for="iqonic_term_condition" class="mb-0">
                        <?php
                        // Translating the static text string
                        esc_html_e("I've read and accepted the", 'streamit');

                        // Get the terms and conditions page URL
                        $term_page_url = isset($streamit_options['streamit_term_condition']) && !empty($streamit_options['streamit_term_condition'])
                            ? get_page_link($streamit_options['streamit_term_condition'])
                            : home_url();
                        ?>
                        <a href="<?php echo esc_url($term_page_url); ?>">
                            <?php
                            // Translating the dynamic link text
                            esc_html_e('Terms & conditions', 'streamit');
                            ?>
                        </a>
                    </label>
                </p>
                <div class="field-error-message terms-error-message text-danger small mt-1" style="display: none;"></div>
            </div>

        <?php endif; ?>

        <?php wp_nonce_field('streamit_register', 'st_ajax_nonce'); ?>

        <?php
        // Add custom hooks for additional content after the registration fields.
        do_action('streamit_after_registration_fields');
        ?>

        <div class="general-error-message" style="display: none;">
            <div class="alert alert-danger" role="alert">
                <span class="error-text"></span>
            </div>
        </div>

        <div class="submit">
            <button type="submit" class="btn btn-primary w-100" id="registration-submit-btn">
                <span class="btn-text"><?php esc_html_e('Register', 'streamit'); ?></span>
                <span class="btn-loader" style="display: none;">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden"><?php esc_html_e('Loading...', 'streamit'); ?></span>
                    </div>
                </span>
            </button>
        </div>

        <div class="col-xl-12">
            <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 links mt-3   ">
                <?php if (isset($streamit_options['streamit_signin_link'])) {
                    $signin_title = $streamit_options['streamit_signin_title']; ?>
                    <?php _e('Already have an account? ', 'streamit'); ?>
                    <a href="<?php echo esc_url(streamit_login_page_url()) ?>" class="st-sub-card setting-dropdown">

                        <h6 class="m-0 text-primary">
                            <?php if (!empty($signin_title)) {
                                echo esc_html($signin_title);
                            } else {
                                echo esc_html__('Sign In', 'streamit');
                            } ?>
                        </h6>
                    </a>
                <?php } ?>
            </div>
            <?php if (shortcode_exists('miniorange_social_login') && !is_user_logged_in()) { ?>

                <div class="css_prefix-separator">
                    <span class="or-section"><?php esc_html_e("OR", 'streamit'); ?> </span>
                </div>

                <div class="css_prefix-social-login-section">
                    <?php echo do_shortcode('[miniorange_social_login]'); ?>
                </div>
            <?php } ?>

        </div>
    </form>

</div>
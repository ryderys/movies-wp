<?php
/**
 * The template for user account settings
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$current_user = wp_get_current_user();
?>

<div class="tab-pane fade show active" id="pills-account-settings" role="tabpanel" tabindex="0">

    <!-- Main Tab Title -->
    <div class="mb-4">
        <h4 class="fw-bold"><?php esc_html_e('Account Settings', 'streamit'); ?></h4>
    </div>
    
    <div class="general-error-message" style="display: none;">
        <div class="alert alert-danger" role="alert">
            <span class="error-text"></span>
        </div>
    </div>

    <div class="general-success-message profile-success-message" style="display: none;">
        <div class="alert alert-success" role="alert">
            <span class="success-text"></span>
        </div>
    </div>

    <div class="row g-4">
        <!-- === Profile Settings === -->
        <div class="col-md-6">
            <h5 class=""><?php esc_html_e('Profile Settings', 'streamit'); ?></h5>


            <form id="st_profile_edit" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="POST" enctype="multipart/form-data" class="row g-3">
                <?php wp_nonce_field('edit_profile_action', 'edit_profile_nonce'); ?>
                <input type="hidden" name="user_id" value="<?php echo esc_attr($current_user->ID); ?>">

                <!-- Avatar Upload Section -->
                <div class="col-12 text-center mb-4">
                    <div class="card border">
                        <div class="card-body">
                            <div class="position-relative d-inline-block avtar_image">
                                <?php
                                $user_avatar_custom = get_user_meta($current_user->ID, 'user_avatar', true);
                                $default_avatar_url = get_avatar_url($current_user->ID);
                                $avatar_url = $user_avatar_custom ?: $default_avatar_url;
                                ?>
                                <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php esc_attr_e('User Avatar', 'streamit'); ?>" class="user-image" id="profile-picture-preview">

                                <div class="avtar_action">
                                    <button type="button" class="avtar_action-btn" id="edit-profile-picture-btn">
                                        <?php echo st_get_icon('edit'); ?>
                                    </button>
                                    <button type="button" class="avtar_action-btn" id="remove-profile-picture-btn">
                                        <?php echo st_get_icon('trash'); ?>
                                    </button>
                                </div>

                                <input type="file" style="display:none" id="upload-profile-picture" accept="image/*" name="user_avatar">
                                <input type="hidden" name="current_avatar" id="current_avatar" value="<?php echo esc_url($avatar_url); ?>" />
                                <input type="hidden" id="default_avatar" value="<?php echo esc_url($default_avatar_url); ?>" />
                            </div>

                            <div class="avatar-error-message text-danger small mt-2" id="avatar-error-message" style="display: none;"></div>
                        </div>
                    </div>
                    
                </div>

                <!-- First Name Field -->
                <div class="col-md-6">
                    <label for="first_name" class="form-label"><?php esc_html_e('First Name', 'streamit'); ?></label>
                    <input type="text" class="form-control" name="first_name" id="first_name" value="<?php echo esc_attr($current_user->first_name); ?>" placeholder="<?php esc_attr_e('Enter your first name', 'streamit'); ?>" required>
                </div>

                <!-- Last Name Field -->
                <div class="col-md-6">
                    <label for="last_name" class="form-label"><?php esc_html_e('Last Name', 'streamit'); ?></label>
                    <input type="text" class="form-control" name="last_name" id="last_name" value="<?php echo esc_attr($current_user->last_name); ?>" placeholder="<?php esc_attr_e('Enter your last name', 'streamit'); ?>" required>
                </div>

                <!-- Email Field -->
                <div class="col-12">
                    <label for="user_email" class="form-label"><?php esc_html_e('Email', 'streamit'); ?></label>
                    <input type="email" class="form-control" name="user_email" id="user_email" value="<?php echo esc_attr($current_user->user_email); ?>" placeholder="<?php esc_attr_e('Enter your email address', 'streamit'); ?>" required>
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary" id="profile-edit-submit-btn">
                        <span class="btn-text"><?php esc_html_e('Save Profile', 'streamit'); ?></span>
                        <span class="btn-loader" style="display: none;">
                            <div class="spinner-border spinner-border-sm me-2" role="status">
                                <span class="visually-hidden"><?php esc_html_e('Saving...', 'streamit'); ?></span>
                            </div>
                        </span>
                    </button>
                </div>
            </form>
        </div>


        <!-- === Password Settings === -->
        <div class="col-md-6">
            <h5 class=""><?php esc_html_e('Password Settings', 'streamit'); ?></h5>
            
            <div class="general-success-message password-success-message" style="display: none;">
                <div class="alert alert-success" role="alert">
                    <span class="success-text"></span>
                </div>
            </div>

            <form id="st_password_change" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="POST" class="row g-3">
                <?php wp_nonce_field('st_ajax_nonce', '_ajax_nonce'); ?>
                <input type="hidden" name="user_id" value="<?php echo esc_attr($current_user->ID); ?>">

                <div class="col-12">
                    <label for="current_password" class="form-label"><?php esc_html_e('Current Password', 'streamit'); ?></label>
                    <div class="input-pwd">
                        <input type="password" class="form-control" name="current_password" id="current_password" placeholder="<?php esc_attr_e('Enter your current password', 'streamit'); ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="new_password" class="form-label"><?php esc_html_e('New Password', 'streamit'); ?></label>
                    <div class="input-pwd">
                        <input type="password" class="form-control" name="new_password" id="new_password" placeholder="<?php esc_attr_e('Enter new password', 'streamit'); ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="confirm_password" class="form-label"><?php esc_html_e('Confirm New Password', 'streamit'); ?></label>
                    <div class="input-pwd">
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="<?php esc_attr_e('Re-enter new password', 'streamit'); ?>" required>
                    </div>
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary" id="password-change-submit-btn">
                        <span class="btn-text"><?php esc_html_e('Change Password', 'streamit'); ?></span>
                        <span class="btn-loader" style="display: none;">
                            <div class="spinner-border spinner-border-sm me-2" role="status">
                                <span class="visually-hidden"><?php esc_html_e('Saving...', 'streamit'); ?></span>
                            </div>
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <!-- === Delete Account Section === -->
        <!-- <div class="col-12">
            <div class="delete-account-box d-flex justify-content-between align-items-center mt-4 p-4 rounded">
                <p class="mb-0 fw-semibold text-danger text-center w-75">
                    <?php esc_html_e('Once you delete your account, all your data will be permanently removed and cannot be recovered.', 'streamit'); ?>
                </p>
                <button class="btn btn-primary" id="delete-account-btn" type="button">
                    <?php esc_html_e('Delete Account', 'streamit'); ?>
                </button>
            </div>
        </div> -->

    </div>

</div>

<?php
// Include the delete account confirmation modal
get_template_part('template-parts/common/html-delete-account-model');
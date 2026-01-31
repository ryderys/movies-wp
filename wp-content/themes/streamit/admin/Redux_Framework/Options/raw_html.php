<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


$theme_setup_page = admin_url("themes.php?page=streamit-setup");
?>
<span class="welcome-back"><?php esc_html_e('Welcome back!', 'streamit'); ?></span>
<div class="dashboard-main-wrap">
    <div class="dashboard-main">
        <h4 class="redux-title"><?php esc_html_e('Experience Our Live Demo Of Streamit Wordpress Theme.', 'streamit'); ?></h4>
        <p class="redux-desc"><?php esc_html_e('To get and all new and amazing  update you need to activate your licence key.', 'streamit'); ?></p>
        <a href="<?php echo esc_url("https://streamit-wordpress.iqonic.design"); ?>" target="_blank" class="redux-btn"><?php esc_html_e('Live Demo', 'streamit'); ?></a>
    </div>
    <div class="redux-feature-main">
        <div class="redux-feature-box">
            <div class="icon-box-main">
                <i class="custom-Download"></i>
            </div>
            <h5 class="redux-title"><?php esc_html_e('Demo Import', 'streamit'); ?></h5>
            <p class="redux-desc"><?php esc_html_e('Import your demo content, widgets and theme settings with one click.', 'streamit'); ?></p>
            <a href="<?php echo esc_url($theme_setup_page); ?>" target="_blank" class="redux-btn"><?php esc_html_e('Start Import', 'streamit'); ?></a>
        </div>
        <div class="redux-feature-box">
            <div class="icon-box-main">
                <i class="custom-doc"></i>
            </div>
            <h5 class="redux-title"><?php esc_html_e('documentation', 'streamit'); ?></h5>
            <p class="redux-desc"><?php esc_html_e('Document include all the following except of theme, plugins, widgets & theme settings.', 'streamit'); ?></p>
            <a href="<?php echo esc_url("https://documentation.iqonic.design/streamit/"); ?>" target="_blank" class="redux-btn"><?php esc_html_e('Go To Documentation', 'streamit'); ?></a>
        </div>
        <div class="redux-feature-box">
            <div class="icon-box-main">
                <i class="custom-support"></i>
            </div>
            <h5 class="redux-title"><?php esc_html_e('need help?', 'streamit'); ?></h5>
            <p class="redux-desc"><?php esc_html_e("Need help with something you can't find an answer to in our documentation? Open Support Ticket.", "streamit"); ?></p>
            <a href="<?php echo esc_url("https://iqonic.desky.support/"); ?>" target="_blank" class="redux-btn"><?php esc_html_e('Submit a Ticket', 'streamit'); ?></a>
        </div>
    </div>
    <?php if (class_exists('MasVideos')) : ?>
        <div class="dashboard-main mt-5">
            <h4 class="redux-title"><?php esc_html_e('Migrate Your Old Data', 'streamit'); ?></h4>
            <p class="redux-desc"><?php esc_html_e('To ensure compatibility between your old data and the new system, you need to migrate your existing data.', 'streamit'); ?></br> <?php esc_html_e('Please install the Streamit Import Plugin to start the migration process. Simply click the button below to install it.', 'streamit'); ?></p>
            <button type="button" id="install_import_plugin" class="redux-btn"><span class="st-loader " style="display: none;"></span><?php esc_html_e('Install Plugin', 'streamit'); ?></button>
        </div>
    <?php endif; ?>
</div>
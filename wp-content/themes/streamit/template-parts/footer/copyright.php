<?php
/**
 * Displays the footer default area
 *
 * @package WordPress
 * @subpackage streamit
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;
?>

<?php if (isset($streamit_options)) { ?>
    <div class="copyright-footer">
        <div class="container-fluid">
            <div class="row align-items-center">

                <div class="col-md-6">
                    <?php echo streamit_replace_text_widget('ct-footer-copyright-sidebar-1'); ?>
                </div>
                <div class="col-md-6 mt-md-0 mt-5">
                    <?php echo streamit_replace_text_widget('ct-footer-copyright-sidebar-2'); ?>
                </div>

            </div>
        </div>
    </div>
<?php } else { ?>
    <div class="copyright-footer">
        <div class="container">
            <div class="row">
                <div class="col-sm-12">
                    <div class="pt-3 pb-3 text-center">
                        <span class="copyright">
                            <a target="_blank"
                                href="<?php echo esc_url('https://themeforest.net/user/iqonicthemes/portfolio/'); ?>">
                                <?php echo esc_html(date('Y')); ?>
                                <strong><?php esc_html_e(' Streamit ', 'streamit'); ?></strong>
                                <?php esc_html_e('. All Rights Reserved.', 'streamit'); ?>
                            </a>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- .site-info -->
    <?php
}
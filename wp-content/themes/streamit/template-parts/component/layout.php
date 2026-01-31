<?php

/**
 * Layout Switcher.
 *
 * @package streamit
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;
?>

<?php if (isset($streamit_options['streamit_enable_switcher']) && $streamit_options['streamit_enable_switcher'] == '0') {
    return false;
}
?>
<div class="rtl-switcher-box d-none d-md-block">
    <!-- Button to trigger the RTL Switcher -->
    <a class="btn btn-icon btn-setting" id="settingbutton" data-bs-toggle="offcanvas" data-bs-target="#rtl-switcher-options" role="button">
        <?php echo st_get_icon('setting'); ?>
    </a>

    <!-- Offcanvas for RTL Switcher Options -->
    <div class="offcanvas offcanvas-end rtl-switcher-options" tabindex="-1" id="rtl-switcher-options" data-bs-scroll="true" data-bs-backdrop="true">
        <div class="offcanvas-body">
            <!-- Close Button -->
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close">
                <?php echo st_get_icon('cross'); ?>
            </button>

            <!-- RTL / LTR Mode Selection -->
            <div class="modes">
                <div class="mode-option">
                    <input type="radio" value="ltr" class="btn-check" name="theme_scheme_direction" id="theme-scheme-direction-ltr">
                    <label class="btn-box" for="theme-scheme-direction-ltr">
                        <?php esc_html_e('LTR', 'streamit'); ?>
                    </label>
                </div>
                <div class="mode-option">
                    <input type="radio" value="rtl" class="btn-check" name="theme_scheme_direction" id="theme-scheme-direction-rtl">
                    <label class="btn-box" for="theme-scheme-direction-rtl">
                        <?php esc_html_e('RTL', 'streamit'); ?>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
class ST_Admin_Notice_Handler
{

    public function __construct()
    {
        add_action('admin_notices', array($this, 'migration_notice'));
        add_action('admin_notices', array($this, 'streamit_plugin_upgrade_notice'));

        // sale banner
        add_action('admin_notices',  array($this, 'streamit_sale_banner'), 0);
    }

    /**
     * Displays an admin notice to old streamit user
     */
    public function migration_notice()
    {
        if (class_exists('MasVideos')) : ?>
            <div class="notice streamit-migration-notice is-dismissible">
                <div class="streamit-migration-notice-inner">
                    <div class="notice-left-img">
                        <img src="<?php echo esc_url(get_template_directory_uri() . '/admin/assets/images/streamit-small-logo.png'); ?>" alt="">
                    </div>
                    <div>
                        <p>
                            <strong>
                                <?php esc_html_e(
                                    '🚀 Upgrade to Streamit 4.0 effortlessly! Migrate Movies, TV Shows, and Videos data from older versions with a single click.',
                                    'streamit'
                                ); ?>
                            </strong>
                        </p>
                        <p>
                            <a target="_blank" href="<?php echo esc_url('https://documentation.iqonic.design/streamit/new-streamit/whats-new'); ?>">
                                <?php esc_html_e('📌 What’s New?', 'streamit'); ?>
                            </a>
                        </p>
                    </div>
                </div>
                <div>
                    <button type="button" id="install_import_plugin" class="streamit-migration-button">
                        <span class="st-loader" style="display: none;"></span>
                        <?php esc_html_e('Install Plugin', 'streamit'); ?>
                    </button>
                </div>
            </div>
        <?php endif;
    }

    /**
     * Displays an admin notice if the Streamit plugin version is below 1.3.0.
     */
    public function streamit_plugin_upgrade_notice()
    {
        // Check if the plugin version is defined and less than 1.3.0.
        $streamit_plugin_version = '1.3.0';
        if (defined('STREAMIT_VERSION') && version_compare(STREAMIT_VERSION, $streamit_plugin_version, '<')) {
        ?>
            <div class="notice streamit-migration-notice is-dismissible">
                <div class="streamit-migration-notice-inner">
                    <div class="notice-left-img">
                        <img src="<?php echo esc_url(get_template_directory_uri() . '/admin/assets/images/streamit-small-logo.png'); ?>" alt="">
                    </div>
                    <div>
                        <p>
                            <?php
                            /* translators: %1$s: new version, %2$s: URL to release notes or update page */
                            echo wp_kses_post(
                                sprintf(
                                    __('A new update for Streamit plugin is available! Please update to version %1$s for improved features and security. <a href="%2$s" target="_blank">%3$s</a>', 'streamit'),
                                    $streamit_plugin_version,
                                    esc_url('https://documentation.iqonic.design/streamit/getting-started/update-plugin'), // Replace with your actual URL.
                                    esc_html__('Learn More', 'streamit')
                                )
                            );
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    public function streamit_sale_banner()
    {
        $type = "plugins";
        $product = "common";
        $get_sale_detail = get_transient('iq-notice');
        if (is_null($get_sale_detail) || $get_sale_detail === false) {
            $get_sale_detail = wp_remote_get("https://assets.iqonic.design/wp-product-notices/notices.json?ver=" . wp_rand());
            set_transient('iq-notice', $get_sale_detail, 3600);
        }

        if (!is_wp_error($get_sale_detail) && $content = json_decode(wp_remote_retrieve_body($get_sale_detail), true)) {
            $currentTime =  current_datetime();
            if (get_option($content['data']['notice-id'], 0)) {
                return;
            }
            if (($content['data']['start-sale-timestamp']  < $currentTime->getTimestamp() && $currentTime->getTimestamp() < $content['data']['end-sale-timestamp']) && isset($content[$type][$product])) { ?>
                <div class="iq-notice iq-sale-notice notice notice-success is-dismissible" style="padding: 0;">
                    <a target="_blank" href="<?php echo esc_url($content[$type][$product]['sale-ink'] ?? "#")  ?>">
                        <img src="<?php echo esc_url($content[$type][$product]['banner-img'] ?? "#")  ?>" style="object-fit: contain;padding: 0;margin: 0;display: block;" width="100%" alt="">
                    </a>
                    <input type="hidden" id="iq-notice-id" value="<?php echo esc_html($content['data']['notice-id']) ?>">
                    <input type="hidden" id="iq-notice-nounce" value="<?php echo wp_create_nonce('iq-dismiss-notice') ?>">
                </div>
<?php
            }
        }
    }
}


new ST_Admin_Notice_Handler();

<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
final class streamit_Admin_Component
{

    /**
     * Install Import Plugin For the Migration.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response object on success, error object on failure.
     */
    public function install_plugin(WP_REST_Request $request)
    {
        // Define the plugin source URL
        $plugin_source = esc_url_raw('https://assets.iqonic.design/wp/plugins/streamit_4/streamit-import.zip');

        // Plugin slug (path relative to the plugins directory)
        $plugin_slug = 'streamit-import/streamit-import.php'; // Adjust to the correct plugin file path

        // Check if the plugin is already active
        if (is_plugin_active($plugin_slug)) {
            return wp_send_json(
                array('success' => true, 'message' => esc_html__('The plugin is already active' , 'streamit')),
                200
            );
        }

        // Check if the plugin is already installed
        if ($this->is_plugin_installed($plugin_slug)) {
            // Activate the plugin if installed but not active
            $activation_result = activate_plugin($plugin_slug);
            if (is_wp_error($activation_result)) {
                return wp_send_json(
                    array('success' => false, 'message' => $activation_result->get_error_message()),
                    500
                );
            }

            return wp_send_json(
                array('success' => true, 'message' => esc_html__('The plugin was already installed and is now activated' ,'streamit')),
                200
            );
        }

        // Check if the source URL is valid
        if (!filter_var($plugin_source, FILTER_VALIDATE_URL)) {
            return wp_send_json(
                array('success' => false, 'message' => esc_html__('Invalid plugin source URL' , 'streamit')),
                400
            );
        }

        // Download the plugin ZIP file
        $plugin_path = download_url($plugin_source);

        if (is_wp_error($plugin_path)) {
            return wp_send_json(
                array('success' => false, 'message' => $plugin_path->get_error_message()),
                500
            );
        }

        // Include necessary WordPress files for installing plugins
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/plugin.php'; // Required for activation

        // Instantiate the plugin upgrader
        $upgrader = new Plugin_Upgrader();

        // Install the plugin from the ZIP file
        $result = $upgrader->install($plugin_path);

        // Clean up the downloaded ZIP file
        unlink($plugin_path);

        // Return the response based on the installation result
        if (is_wp_error($result)) {
            return wp_send_json(
                array('success' => false, 'message' => $result->get_error_message()),
                500
            );
        }

        // Activate the plugin after installation
        $activation_result = activate_plugin($plugin_slug);

        if (is_wp_error($activation_result)) {
            return wp_send_json(
                array('success' => false, 'message' => $activation_result->get_error_message()),
                500
            );
        }

        // Return success response
        return wp_send_json(
            array('success' => true, 'message' => esc_html__('Plugin installed and activated successfully' ,'streamit')),
            200
        );
    }

    /**
     * Check if the plugin is installed
     *
     * @param string $plugin_slug The plugin file path (e.g. 'plugin-name/plugin-file.php')
     * @return bool
     */
    private function is_plugin_installed($plugin_slug)
    {
        // Get the list of installed plugins
        $installed_plugins = get_plugins();

        // Check if the plugin exists in the list of installed plugins
        return isset($installed_plugins[$plugin_slug]);
    }
}

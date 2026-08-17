<?php
/**
 * Plugin Name: Movies WP Media Automation
 * Description: Media automation: scan/preview, import plan, Streamit adapter, and admin import.
 * Version: 0.4.0
 * Text Domain: movies-wp
 * Domain Path: /languages
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

define( 'MOVIES_WP_MEDIA_AUTOMATION_FILE', __FILE__ );
define( 'MOVIES_WP_MEDIA_AUTOMATION_DIR', __DIR__ );

add_action(
	'init',
	function () {
		load_plugin_textdomain(
			'movies-wp',
			false,
			dirname( plugin_basename( MOVIES_WP_MEDIA_AUTOMATION_FILE ) ) . '/languages'
		);
	}
);

require_once __DIR__ . '/includes/class-movies-wp-media-api-client.php';
require_once __DIR__ . '/includes/class-movies-wp-tmdb-preview-client.php';
require_once __DIR__ . '/includes/class-movies-wp-tmdb-tv-preview-client.php';
require_once __DIR__ . '/includes/class-movies-wp-media-preview-service.php';
require_once __DIR__ . '/includes/class-movies-wp-series-preview-service.php';
require_once __DIR__ . '/includes/class-movies-wp-media-import-plan.php';
require_once __DIR__ . '/includes/class-movies-wp-series-import-plan.php';
require_once __DIR__ . '/includes/class-movies-wp-streamit-adapter.php';
require_once __DIR__ . '/includes/class-movies-wp-streamit-tv-adapter.php';
require_once __DIR__ . '/includes/class-movies-wp-media-import-service.php';
require_once __DIR__ . '/includes/class-movies-wp-series-import-service.php';
require_once __DIR__ . '/includes/class-movies-wp-media-admin.php';
require_once __DIR__ . '/includes/class-movies-wp-series-admin.php';
require_once __DIR__ . '/includes/class-movies-wp-series-media-api-client.php';
require_once __DIR__ . '/includes/class-movies-wp-series-media-preview-service.php';
require_once __DIR__ . '/includes/class-movies-wp-series-media-import-plan.php';
require_once __DIR__ . '/includes/class-movies-wp-streamit-episode-media-adapter.php';
require_once __DIR__ . '/includes/class-movies-wp-series-media-import-service.php';
require_once __DIR__ . '/includes/class-movies-wp-series-media-admin.php';

Movies_WP_Media_Admin::init();
Movies_WP_Series_Admin::init();
Movies_WP_Series_Media_Admin::init();

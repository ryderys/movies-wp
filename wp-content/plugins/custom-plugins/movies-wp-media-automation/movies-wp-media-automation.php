<?php
/**
 * Plugin Name: Movies WP Media Automation
 * Description: Media automation: scan/preview, import plan, Streamit adapter, and admin import.
 * Version: 0.4.0
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

define( 'MOVIES_WP_MEDIA_AUTOMATION_FILE', __FILE__ );
define( 'MOVIES_WP_MEDIA_AUTOMATION_DIR', __DIR__ );

require_once __DIR__ . '/includes/class-movies-wp-media-api-client.php';
require_once __DIR__ . '/includes/class-movies-wp-tmdb-preview-client.php';
require_once __DIR__ . '/includes/class-movies-wp-media-preview-service.php';
require_once __DIR__ . '/includes/class-movies-wp-media-import-plan.php';
require_once __DIR__ . '/includes/class-movies-wp-streamit-adapter.php';
require_once __DIR__ . '/includes/class-movies-wp-media-import-service.php';
require_once __DIR__ . '/includes/class-movies-wp-media-admin.php';

Movies_WP_Media_Admin::init();

<?php
/**
 * Versioned MySQL table for the Telegram publish queue.
 *
 * Independent of Movies_WP_Series_Import_Schema. Nothing in this class
 * enables publishing, and it is not called from plugin activation.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Telegram_Schema {

	const OPTION  = 'movies_wp_telegram_schema_version';
	const VERSION = 1;

	public static function maybe_install() {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return false;
		}
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return true;
		}
		$ok = self::install();
		if ( ! $ok ) {
			return false;
		}
		update_option( self::OPTION, self::VERSION, true );
		return true;
	}

	/**
	 * @return bool
	 */
	public static function install() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}
		$upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
		if ( is_readable( $upgrade ) ) {
			require_once $upgrade;
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			return false;
		}
		$charset = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		$table   = self::table_name();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_key CHAR(64) NOT NULL,
			content_type VARCHAR(16) NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL,
			attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
			next_attempt_at DATETIME NULL,
			last_attempt_at DATETIME NULL,
			last_error VARCHAR(500) NULL,
			telegram_message_id VARCHAR(32) NULL,
			sent_at DATETIME NULL,
			claim_token VARCHAR(64) NULL,
			claimed_until DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_key (event_key),
			KEY status_next (status, next_attempt_at),
			KEY claim_expiry (status, claimed_until)
		) {$charset};";

		dbDelta( $sql );
		return self::tables_exist();
	}

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		$prefix = ( isset( $wpdb ) && is_object( $wpdb ) && isset( $wpdb->prefix ) ) ? (string) $wpdb->prefix : 'wp_';
		return $prefix . 'movies_wp_telegram_queue';
	}

	/**
	 * @return bool
	 */
	public static function tables_exist() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return false;
		}
		$table = self::table_name();
		$have  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $table === (string) $have;
	}
}

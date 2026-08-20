<?php
/**
 * Versioned MySQL tables for Series import snapshots and jobs.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Import_Schema {

	const OPTION  = 'movies_wp_series_import_schema_version';
	const VERSION = 2;

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
		$charset   = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		$snapshots = $wpdb->prefix . 'movies_wp_series_import_snapshots';
		$jobs      = $wpdb->prefix . 'movies_wp_series_import_jobs';

		$sql = "CREATE TABLE {$snapshots} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token_hash VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			blog_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
			payload LONGTEXT NOT NULL,
			fingerprint VARCHAR(64) NOT NULL DEFAULT '',
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY user_blog (user_id, blog_id),
			KEY expires_at (expires_at)
		) {$charset};\n";

		$sql .= "CREATE TABLE {$jobs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token_hash VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			blog_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
			tmdb_id BIGINT UNSIGNED NOT NULL,
			series_id BIGINT UNSIGNED NULL,
			directory TEXT NOT NULL,
			snapshot_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL,
			phase VARCHAR(32) NOT NULL,
			episode_done INT UNSIGNED NOT NULL DEFAULT 0,
			episode_total INT UNSIGNED NOT NULL DEFAULT 0,
			last_episode_id BIGINT UNSIGNED NULL,
			last_error LONGTEXT NULL,
			warnings_json LONGTEXT NULL,
			result_json LONGTEXT NULL,
			claimed_until DATETIME NULL,
			claim_token VARCHAR(64) NULL,
			active_slot TINYINT UNSIGNED NULL,
			elapsed_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			UNIQUE KEY snapshot_active (snapshot_id, user_id, blog_id, active_slot),
			KEY user_blog (user_id, blog_id),
			KEY status (status),
			KEY snapshot_id (snapshot_id)
		) {$charset};";

		dbDelta( $sql );

		if ( method_exists( $wpdb, 'query' ) ) {
			$wpdb->query(
				"UPDATE {$jobs} SET active_slot = 1 WHERE status IN ('preparing','queued','running','paused') AND (active_slot IS NULL OR active_slot = 0)"
			);
		}

		return self::tables_exist();
	}

	/**
	 * @return bool
	 */
	public static function tables_exist() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return false;
		}
		$snapshots = $wpdb->prefix . 'movies_wp_series_import_snapshots';
		$jobs      = $wpdb->prefix . 'movies_wp_series_import_jobs';
		$have_snap = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $snapshots ) );
		$have_jobs = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $jobs ) );
		return $snapshots === (string) $have_snap && $jobs === (string) $have_jobs;
	}
}

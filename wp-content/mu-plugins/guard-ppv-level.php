<?php
/**
 * Plugin Name: Guard PMPro PPV Level
 * Description: Prevents Streamit from creating duplicate Pay Per View membership levels.
 * Version: 1.0.0
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Re-bind streamit_ppv_level_id if missing but a PPV level already exists.
 */
function movies_wp_guard_ppv_rebind_option() {
	if ( ! function_exists( 'pmpro_getLevel' ) ) {
		return;
	}

	$opt = absint( get_option( 'streamit_ppv_level_id', 0 ) );
	if ( $opt > 0 && pmpro_getLevel( $opt ) ) {
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_membership_levels';

	$id = absint(
		$wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE name IN (%s, %s) ORDER BY id ASC LIMIT 1",
				'Pay Per View',
				'پرداخت به ازای هر بازدید'
			)
		)
	);

	if ( $id > 0 ) {
		update_option( 'streamit_ppv_level_id', $id );
	}
}
add_action( 'init', 'movies_wp_guard_ppv_rebind_option', 5 );

/**
 * Block INSERT of another PPV level when one already exists.
 *
 * @param string $query SQL.
 * @return string
 */
function movies_wp_guard_ppv_block_duplicate_insert( $query ) {
	global $wpdb;

	if ( ! is_string( $query ) || empty( $wpdb->prefix ) ) {
		return $query;
	}

	$table = $wpdb->prefix . 'pmpro_membership_levels';
	if ( ! preg_match( '/^\s*INSERT\s+INTO\s+[`"\']?' . preg_quote( $table, '/' ) . '[`"\']?/i', $query ) ) {
		return $query;
	}

	$is_ppv_insert = ( false !== stripos( $query, 'Pay Per View' ) )
		|| ( false !== strpos( $query, 'پرداخت به ازای هر بازدید' ) );

	if ( ! $is_ppv_insert ) {
		return $query;
	}

	$exists = absint(
		$wpdb->get_var(
			"SELECT id FROM `{$table}` WHERE name IN ('Pay Per View','پرداخت به ازای هر بازدید') ORDER BY id ASC LIMIT 1"
		)
	);

	if ( $exists > 0 ) {
		return 'SELECT 1';
	}

	return $query;
}
add_filter( 'query', 'movies_wp_guard_ppv_block_duplicate_insert', 0 );

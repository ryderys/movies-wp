<?php
/**
 * Plugin Name: PMPro Iranian Rial (IRR)
 * Description: Adds Iranian Rial currency to Paid Memberships Pro. Loads as a must-use plugin so it works regardless of active theme.
 * Version: 1.0.0
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return array
 */
function movies_wp_pmpro_irr_definition() {
	return array(
		'name'                => 'Iranian Rial (ریال)',
		'symbol'              => 'ریال&nbsp;',
		'decimals'            => 0,
		'thousands_separator' => ',',
		'decimal_separator'   => '.',
		'position'            => 'right',
	);
}

/**
 * @param array $currencies PMPro currencies.
 * @return array
 */
function movies_wp_pmpro_add_irr( $currencies ) {
	if ( ! is_array( $currencies ) ) {
		$currencies = array();
	}

	$currencies['IRR'] = movies_wp_pmpro_irr_definition();

	return $currencies;
}
add_filter( 'pmpro_currencies', 'movies_wp_pmpro_add_irr', 999 );

/**
 * Ensure IRR exists on the global after PMPro init.
 */
function movies_wp_pmpro_inject_irr_global() {
	global $pmpro_currencies;

	if ( ! is_array( $pmpro_currencies ) ) {
		return;
	}

	$pmpro_currencies['IRR'] = movies_wp_pmpro_irr_definition();
}
add_action( 'init', 'movies_wp_pmpro_inject_irr_global', 20 );

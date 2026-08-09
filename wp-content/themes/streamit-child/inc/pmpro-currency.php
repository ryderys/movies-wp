<?php
/**
 * Add Iranian Rial (IRR) to Paid Memberships Pro currencies.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * IRR definition for PMPro.
 *
 * @return array
 */
function streamit_child_pmpro_irr_definition() {
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
 * Register IRR via PMPro's currency filter (high priority so it wins over gateways).
 *
 * @param array $currencies Existing PMPro currencies.
 * @return array
 */
function streamit_child_pmpro_add_irr( $currencies ) {
	if ( ! is_array( $currencies ) ) {
		$currencies = array();
	}

	$currencies['IRR'] = streamit_child_pmpro_irr_definition();

	return $currencies;
}
add_filter( 'pmpro_currencies', 'streamit_child_pmpro_add_irr', 999 );

/**
 * Also inject IRR after PMPro init in case something rebuilt the global without our filter.
 */
function streamit_child_pmpro_inject_irr_global() {
	global $pmpro_currencies;

	if ( ! is_array( $pmpro_currencies ) ) {
		return;
	}

	$pmpro_currencies['IRR'] = streamit_child_pmpro_irr_definition();
}
add_action( 'init', 'streamit_child_pmpro_inject_irr_global', 20 );

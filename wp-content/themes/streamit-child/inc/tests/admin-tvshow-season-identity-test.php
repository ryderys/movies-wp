<?php
/**
 * CLI tests for TV-show season identity extraction.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/admin-tvshow-season-identity-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-tvshow-season-identity/' );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		unset( $hook, $callback, $priority, $args );
	}
}

require dirname( __DIR__ ) . '/admin-tvshow-season-identity.php';

$failures = 0;

function season_identity_assert( $condition, $message ) {
	global $failures;
	if ( $condition ) {
		echo "ok  {$message}\n";
		return;
	}
	$failures++;
	echo "FAIL {$message}\n";
}

$stored = array(
	array(
		'name'          => 'Season 1',
		'season_number' => '1',
		'image_id'      => 15,
		'episodes'      => array( 10, 11 ),
	),
	array(
		'name'          => 'Specials',
		'season_number' => '0',
		'image_id'      => '',
	),
	'not-a-season',
	array(
		'name'     => 'No identity',
		'image_id' => array( 'bad' ),
	),
);

$identities = streamit_child_tvshow_season_identities( $stored );

season_identity_assert( 4 === count( $identities ), 'one slot per stored row, including invalid rows' );
season_identity_assert( true === $identities[0]['has_season_number'] && '1' === $identities[0]['season_number'], 'season 1 number kept as stored string' );
season_identity_assert( true === $identities[0]['has_image_id'] && 15 === $identities[0]['image_id'], 'poster id kept as stored integer' );
season_identity_assert( true === $identities[1]['has_season_number'] && '0' === $identities[1]['season_number'], 'season 0 is preserved' );
season_identity_assert( true === $identities[1]['has_image_id'] && '' === $identities[1]['image_id'], 'empty poster id is preserved' );
season_identity_assert( false === $identities[2]['has_season_number'] && false === $identities[2]['has_image_id'], 'invalid row does not invent identity' );
season_identity_assert( false === $identities[3]['has_image_id'] && ! array_key_exists( 'season_number', $identities[3] ), 'non-scalar poster is omitted' );
season_identity_assert( array() === streamit_child_tvshow_season_identities( null ), 'missing meta yields no slots' );
season_identity_assert( array() === streamit_child_tvshow_season_identities( 'serialized-string' ), 'unparsed string is ignored' );

echo $failures ? "\n{$failures} failed\n" : "\nall passed\n";
exit( $failures ? 1 : 0 );

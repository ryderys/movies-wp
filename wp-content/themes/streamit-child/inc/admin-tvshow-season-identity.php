<?php
/**
 * Keep automation season identity on the Streamit TV-show edit save.
 *
 * Streamit's season payload is rebuilt from visible inputs and omits
 * season_number and image_id. This screen stamps those stored values onto
 * each existing season box so tvshow_form_data can copy them back.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One identity slot per stored season, in render order.
 *
 * A non-array row still occupies a slot so later seasons are not shifted
 * onto the wrong box. Non-scalar values are omitted.
 *
 * @param mixed $seasons Raw `_seasons` meta.
 * @return array<int, array{has_season_number:bool,has_image_id:bool,season_number?:mixed,image_id?:mixed}>
 */
function streamit_child_tvshow_season_identities( $seasons ) {
	if ( ! is_array( $seasons ) ) {
		return array();
	}

	$identities = array();

	foreach ( $seasons as $season ) {
		$identity = array(
			'has_season_number' => false,
			'has_image_id'       => false,
		);

		if ( is_array( $season ) ) {
			if ( array_key_exists( 'season_number', $season ) && is_scalar( $season['season_number'] ) ) {
				$identity['has_season_number'] = true;
				$identity['season_number']     = $season['season_number'];
			}
			if ( array_key_exists( 'image_id', $season ) && is_scalar( $season['image_id'] ) ) {
				$identity['has_image_id'] = true;
				$identity['image_id']     = $season['image_id'];
			}
		}

		$identities[] = $identity;
	}

	return $identities;
}

/**
 * Load `_seasons` for the TV show currently being edited.
 *
 * @param int $tvshow_id Streamit TV-show ID.
 * @return mixed
 */
function streamit_child_tvshow_edit_seasons_meta( $tvshow_id ) {
	$tvshow_id = absint( $tvshow_id );
	if ( $tvshow_id <= 0 ) {
		return array();
	}

	if ( function_exists( 'streamit_get_tvshow_meta' ) ) {
		$seasons = streamit_get_tvshow_meta( $tvshow_id, '_seasons', true );
	} else {
		$seasons = get_metadata( 'streamit_tvshow', $tvshow_id, '_seasons', true );
	}

	if ( is_wp_error( $seasons ) ) {
		return array();
	}
	if ( is_string( $seasons ) && function_exists( 'maybe_unserialize' ) ) {
		$seasons = maybe_unserialize( $seasons );
	}

	return $seasons;
}

/**
 * Enqueue the season-identity preserve script on the TV-show edit screen only.
 *
 * @param string $hook Current admin page hook.
 */
function streamit_child_enqueue_tvshow_season_identity( $hook ) {
	if ( 'admin_page_streamit-edit-tvshow' !== $hook ) {
		return;
	}

	$js_path = get_stylesheet_directory() . '/assets/js/admin-tvshow-season-identity.js';
	if ( ! file_exists( $js_path ) ) {
		return;
	}

	wp_enqueue_script(
		'streamit-child-admin-tvshow-season-identity',
		get_stylesheet_directory_uri() . '/assets/js/admin-tvshow-season-identity.js',
		array( 'jquery', 'wp-hooks' ),
		(string) filemtime( $js_path ),
		true
	);

	$tvshow_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$encoded   = wp_json_encode( streamit_child_tvshow_season_identities( streamit_child_tvshow_edit_seasons_meta( $tvshow_id ) ) );

	wp_localize_script(
		'streamit-child-admin-tvshow-season-identity',
		'streamitChildTvshowSeasons',
		array(
			'seasonsJson' => is_string( $encoded ) ? $encoded : '[]',
		)
	);
}
add_action( 'admin_enqueue_scripts', 'streamit_child_enqueue_tvshow_season_identity', 120 );

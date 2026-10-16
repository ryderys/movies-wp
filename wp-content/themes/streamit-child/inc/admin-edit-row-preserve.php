<?php
/**
 * Keep omitted Streamit row columns on movie, TV-show, and episode edit saves.
 *
 * streamit_update_movie(), streamit_update_tvshow(), and streamit_update_episode()
 * fill a partial edit payload with defaults, then Streamit_*::update() writes
 * every key that survives validation. streamit_before_update_* cannot change
 * that payload: it is an action, and the array is passed by value after
 * validation. Dropping the omitted keys from the update validation rules
 * during those three admin routes leaves the stored columns untouched.
 * Insert rules and direct class updates (Content Automation) are unchanged.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin AJAX routes whose controllers submit a partial row.
 *
 * @return string[]
 */
function streamit_child_edit_row_route_names() {
	return array(
		'update_movie',
		'streamit_update_tvshow',
		'streamit_update_episode',
	);
}

/**
 * Columns the edit form does not send. The update helpers would otherwise
 * replace them with defaults.
 *
 * post_modified and post_modified_gmt stay writable so a save still bumps
 * the modified time. post_type stays writable because the helper default
 * matches the record.
 *
 * @return string[]
 */
function streamit_child_edit_row_preserved_fields() {
	return array(
		'post_date',
		'post_date_gmt',
		'comment_count',
		'menu_order',
		'guid',
		'comment_status',
		'ping_status',
		'post_password',
		'post_content_filtered',
		'post_parent',
	);
}

/**
 * Remember whether this request is one of the three edit saves.
 *
 * @param string|null $route_name Route to store. Null reads the current flag.
 * @return bool
 */
function streamit_child_editing_streamit_row( $route_name = null ) {
	static $active = false;

	if ( is_string( $route_name ) ) {
		$active = in_array( $route_name, streamit_child_edit_row_route_names(), true );
	}

	return $active;
}

/**
 * Remove preserved columns from an update rule set while an edit save is active.
 *
 * @param array<string, mixed> $rules    Validation rules.
 * @param bool                 $is_edit  True only for an existing-record edit save.
 * @return array<string, mixed>
 */
function streamit_child_preserve_omitted_row_rules( $rules, $is_edit ) {
	if ( ! $is_edit || ! is_array( $rules ) ) {
		return $rules;
	}

	foreach ( streamit_child_edit_row_preserved_fields() as $field ) {
		unset( $rules[ $field ] );
	}

	return $rules;
}

/**
 * Mark the request before Streamit's AJAX handler builds the partial row.
 */
function streamit_child_mark_streamit_row_edit() {
	$route_name = isset( $_REQUEST['route_name'] ) ? sanitize_key( wp_unslash( $_REQUEST['route_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	streamit_child_editing_streamit_row( $route_name );
}
add_action( 'wp_ajax_streamit_ajax_post', 'streamit_child_mark_streamit_row_edit', 1 );

/**
 * @param array<string, mixed> $rules Update validation rules.
 * @return array<string, mixed>
 */
function streamit_child_filter_update_row_rules( $rules ) {
	return streamit_child_preserve_omitted_row_rules( $rules, streamit_child_editing_streamit_row() );
}
add_filter( 'streamit_update_movie_validation_rules', 'streamit_child_filter_update_row_rules' );
add_filter( 'streamit_update_tvshow_validation_rules', 'streamit_child_filter_update_row_rules' );
add_filter( 'streamit_update_episode_validation_rules', 'streamit_child_filter_update_row_rules' );

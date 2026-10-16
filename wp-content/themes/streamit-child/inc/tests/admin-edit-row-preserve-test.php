<?php
/**
 * Regression tests for edit-row preservation.
 *
 * These tests do not open a database. Streamit_*::update() writes a column
 * only when that column remains in the validated rule set and was present
 * in the payload. The tests assert that rule set.
 *
 * Run:
 *   php -n wp-content/themes/streamit-child/inc/tests/admin-edit-row-preserve-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-edit-row-preserve/' );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		unset( $hook, $callback, $priority, $args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		unset( $hook, $callback, $priority, $args );
	}
}

require dirname( __DIR__ ) . '/admin-edit-row-preserve.php';

$failures = 0;

function row_preserve_assert( $condition, $message ) {
	global $failures;
	if ( $condition ) {
		echo "ok  {$message}\n";
		return;
	}
	$failures++;
	echo "FAIL {$message}\n";
}

/**
 * Same column list Streamit_Movie::update(), Streamit_Tvshow::update(),
 * and Streamit_Episode::update() validate.
 *
 * @return array<string, array<int, string>>
 */
function row_preserve_update_rules() {
	return array(
		'ID'                    => array( 'required', 'integer' ),
		'post_author'           => array( 'required', 'integer' ),
		'post_date'             => array( 'required', 'date' ),
		'post_date_gmt'         => array( 'required', 'date' ),
		'post_content'          => array( 'nullable', 'string', 'sanitize:wp_kses_post' ),
		'post_title'            => array( 'required', 'string', 'sanitize:text' ),
		'post_excerpt'          => array( 'nullable', 'string', 'sanitize:text' ),
		'post_status'           => array( 'required', 'string', 'sanitize:text' ),
		'comment_status'        => array( 'required', 'string' ),
		'ping_status'           => array( 'required', 'string' ),
		'post_password'         => array( 'nullable', 'string', 'sanitize:text' ),
		'post_modified'         => array( 'required', 'date' ),
		'post_modified_gmt'     => array( 'required', 'date' ),
		'post_content_filtered' => array( 'nullable', 'string', 'sanitize:text' ),
		'post_parent'           => array( 'nullable', 'integer' ),
		'guid'                  => array( 'nullable', 'string', 'sanitize:url' ),
		'menu_order'            => array( 'required', 'integer' ),
		'post_type'             => array( 'required', 'string' ),
		'comment_count'         => array( 'required', 'integer' ),
		'post_name'             => array( 'required', 'string', 'sanitize:text' ),
	);
}

/**
 * Columns that reach $wpdb->update(). Mirrors setValidatedData(): a ruled
 * field is included only when the payload actually contains it.
 *
 * @param array<string, mixed> $rules
 * @param array<string, mixed> $payload
 * @return string[]
 */
function row_preserve_written_columns( $rules, $payload ) {
	$written = array();
	foreach ( $rules as $field => $rule ) {
		unset( $rule );
		if ( array_key_exists( $field, $payload ) ) {
			$written[] = $field;
		}
	}
	return $written;
}

function row_preserve_edit_payload( $type ) {
	return array(
		'ID'           => 42,
		'post_title'   => 'Edited title',
		'post_content' => 'Edited summary',
		'post_excerpt' => 'Edited excerpt',
		'post_status'  => 'publish',
		'post_author'  => 3,
		'post_name'    => 'edited-title',
		'post_date'             => '2026-10-16 00:30:00',
		'post_date_gmt'         => '2026-10-15 21:00:00',
		'comment_status'        => 'open',
		'ping_status'           => 'open',
		'post_password'         => '',
		'post_modified'         => '2026-10-16 00:30:00',
		'post_modified_gmt'     => '2026-10-15 21:00:00',
		'post_content_filtered' => '',
		'post_parent'           => 0,
		'guid'                  => '',
		'menu_order'            => 0,
		'post_type'             => $type,
		'comment_count'         => 0,
	);
}

$preserved = streamit_child_edit_row_preserved_fields();
$intentional = array( 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_author', 'post_name', 'post_modified', 'post_modified_gmt' );

foreach ( array( 'update_movie' => 'movie', 'streamit_update_tvshow' => 'tvshow', 'streamit_update_episode' => 'episode' ) as $route => $type ) {
	$rules   = streamit_child_preserve_omitted_row_rules( row_preserve_update_rules(), true );
	$written = row_preserve_written_columns( $rules, row_preserve_edit_payload( $type ) );

	foreach ( array( 'post_date', 'post_date_gmt', 'comment_count', 'menu_order', 'guid' ) as $field ) {
		row_preserve_assert( ! in_array( $field, $written, true ), "{$route}: omitted {$field} is not written" );
	}
	foreach ( $intentional as $field ) {
		row_preserve_assert( in_array( $field, $written, true ), "{$route}: edited {$field} is still written" );
	}
	row_preserve_assert( ! in_array( 'post_parent', $written, true ), "{$route}: omitted post_parent is not written" );
	row_preserve_assert( in_array( $route, streamit_child_edit_row_route_names(), true ), "{$route} is an edit route" );
}

$add_rules = streamit_child_preserve_omitted_row_rules( row_preserve_update_rules(), false );
row_preserve_assert( array_key_exists( 'post_date', $add_rules ), 'new-record path keeps the post_date rule' );
row_preserve_assert( array_key_exists( 'comment_count', $add_rules ), 'new-record path keeps the comment_count rule' );
row_preserve_assert( ! in_array( 'streamit_add_new_movie', streamit_child_edit_row_route_names(), true ), 'add movie is not an edit route' );
row_preserve_assert( ! in_array( 'streamit_add_new_tvshow', streamit_child_edit_row_route_names(), true ), 'add tvshow is not an edit route' );
row_preserve_assert( ! in_array( 'streamit_add_new_episode', streamit_child_edit_row_route_names(), true ), 'add episode is not an edit route' );

$automation_payload = row_preserve_edit_payload( 'episode' );
$automation_payload['post_date']     = '2020-01-02 03:04:05';
$automation_payload['post_date_gmt'] = '2020-01-02 00:04:05';
$automation_payload['comment_count'] = 12;
$automation_payload['menu_order']    = 7;
$automation_payload['guid']          = 'https://example.test/?episode=7';
$automation_payload['post_title']    = 'S01E07 - Pilot';
$automation_rules                    = streamit_child_preserve_omitted_row_rules( row_preserve_update_rules(), false );
$automation_written                  = row_preserve_written_columns( $automation_rules, $automation_payload );

foreach ( array( 'post_date', 'post_date_gmt', 'comment_count', 'menu_order', 'guid', 'post_title' ) as $field ) {
	row_preserve_assert( in_array( $field, $automation_written, true ), "automation update still writes {$field}" );
}
row_preserve_assert( $automation_rules === row_preserve_update_rules(), 'automation leaves the shared update rules unchanged' );

$edit_rules = streamit_child_preserve_omitted_row_rules( row_preserve_update_rules(), true );
foreach ( $preserved as $field ) {
	row_preserve_assert( ! array_key_exists( $field, $edit_rules ), "edit rules omit {$field}" );
}

echo $failures ? "\n{$failures} failed\n" : "\nall passed\n";
exit( $failures ? 1 : 0 );

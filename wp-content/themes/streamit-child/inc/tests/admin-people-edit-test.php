<?php
/**
 * Crew-label display and TV-show relation source tests.
 *
 * These tests do not open a database and do not call
 * streamit_add_person_relation() or streamit_remove_person_relation().
 * They assert which meta type the TV-show save reads, and which person
 * ids would be added or removed on `_tvshow_cast` / `_tvshow_crew`.
 *
 * Run:
 *   php -n wp-content/themes/streamit-child/inc/tests/admin-people-edit-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-people-edit/' );
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

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( (string) $key );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

$people_meta_reads = array();
$people_tv_cast    = array(
	array( 'id' => 1, 'character' => 'Ada' ),
	array( 'id' => 2, 'character' => 'Bea' ),
);
$people_tv_crew    = array(
	array( 'id' => 4, 'job' => 'Director' ),
);
$people_movie_cast = array(
	array( 'id' => 7, 'character' => 'Movie Ada' ),
	array( 'id' => 8, 'character' => 'Movie Bea' ),
);

if ( ! function_exists( 'get_metadata' ) ) {
	function get_metadata( $meta_type, $object_id, $meta_key = '', $single = false ) {
		global $people_meta_reads, $people_tv_cast, $people_tv_crew, $people_movie_cast;
		$people_meta_reads[] = array( $meta_type, (int) $object_id, $meta_key, (bool) $single );

		if ( 'streamit_tvshow' === $meta_type && '_cast' === $meta_key ) {
			return $people_tv_cast;
		}
		if ( 'streamit_tvshow' === $meta_type && '_crew' === $meta_key ) {
			return $people_tv_crew;
		}
		if ( 'streamit_movie' === $meta_type && '_cast' === $meta_key ) {
			return $people_movie_cast;
		}

		return array();
	}
}

require dirname( __DIR__ ) . '/admin-people-edit.php';

$failures = 0;

function people_assert( $condition, $message ) {
	global $failures;
	if ( $condition ) {
		echo "ok  {$message}\n";
		return;
	}
	$failures++;
	echo "FAIL {$message}\n";
}

$job_only = array(
	array(
		'id'  => 4,
		'job' => 'Director',
	),
);
$shown    = streamit_child_people_with_role_label( $job_only );
people_assert( 'Director' === $shown[0]['role'], 'job-only crew is shown as role' );
people_assert( 'Director' === $shown[0]['job'], 'showing a job does not drop the stored job' );
people_assert( ! isset( $job_only[0]['role'] ), 'display copy does not write role back onto the stored row' );

$role_only = array(
	array(
		'id'   => 5,
		'role' => 'Writer',
	),
);
$shown_role = streamit_child_people_with_role_label( $role_only );
people_assert( 'Writer' === $shown_role[0]['role'], 'existing role stays the editable label' );
people_assert( ! isset( $shown_role[0]['job'] ), 'a role-only row does not gain a job' );

$character_only = array(
	array(
		'id'        => 6,
		'character' => 'Host',
	),
);
$shown_character = streamit_child_people_with_role_label( $character_only );
people_assert( 'Host' === $shown_character[0]['role'], 'character-only crew is shown in the role field' );
people_assert( 'Host' === $shown_character[0]['character'], 'character stays on the display copy' );

$both = array(
	array(
		'id'   => 7,
		'role' => 'Writer',
		'job'  => 'Director',
	),
);
$shown_both = streamit_child_people_with_role_label( $both );
people_assert( 'Writer' === $shown_both[0]['role'], 'a meaningful role is not replaced by job' );

$_REQUEST = array(
	'route_name' => 'streamit_update_tvshow',
	'tvshow_id'  => 9,
);

$cast_check = streamit_child_redirect_tvshow_relation_meta( null, 9, '_cast', true );
$crew_check = streamit_child_redirect_tvshow_relation_meta( null, 9, '_crew', true );
people_assert( is_array( $cast_check ) && array_key_exists( 0, $cast_check ), 'TV cast short-circuit wraps the row list' );
people_assert( $people_tv_cast === $cast_check[0], 'TV-show save reads TV-show cast, not movie cast' );
people_assert( $people_tv_crew === $crew_check[0], 'TV-show save reads TV-show crew' );
people_assert(
	array( 'streamit_tvshow', 9, '_cast' ) === array( $people_meta_reads[0][0], $people_meta_reads[0][1], $people_meta_reads[0][2] ),
	'TV cast redirect queries streamit_tvshow meta'
);

$other = streamit_child_redirect_tvshow_relation_meta( 'movie-sentinel', 15, '_cast', true );
people_assert( 'movie-sentinel' === $other, 'a different object id is not redirected' );

$season = streamit_child_redirect_tvshow_relation_meta( 'season-sentinel', 9, '_seasons', true );
people_assert( 'season-sentinel' === $season, 'only cast and crew reads are redirected' );

$reads_before_movie     = count( $people_meta_reads );
$_REQUEST['route_name'] = 'update_movie';
$movie_route            = streamit_child_redirect_tvshow_relation_meta( 'movie-cast', 9, '_cast', true );
people_assert( 'movie-cast' === $movie_route, 'movie edit saves keep reading movie meta' );
people_assert( count( $people_meta_reads ) === $reads_before_movie, 'movie route does not query TV-show meta' );

$new_cast = array(
	array( 'id' => 2, 'character' => 'Bea' ),
	array( 'id' => 3, 'character' => 'Cy' ),
);
$cast_changes = streamit_child_person_relation_changes(
	streamit_child_person_ids_from_rows( $cast_check[0] ),
	streamit_child_person_ids_from_rows( $new_cast )
);
people_assert( array( 3 ) === $cast_changes['add'], 'adding a TV-show cast person is an add on the TV-show list' );
people_assert( array( 1 ) === $cast_changes['remove'], 'removing a TV-show cast person is a remove on the TV-show list' );
people_assert( ! in_array( 2, $cast_changes['add'], true ) && ! in_array( 2, $cast_changes['remove'], true ), 'a TV-show cast person who stays is not rewritten' );
people_assert( ! in_array( 7, $cast_changes['remove'], true ) && ! in_array( 8, $cast_changes['remove'], true ), 'movie cast people are not removed by the TV-show diff' );

$crew_cleared = streamit_child_person_relation_changes(
	streamit_child_person_ids_from_rows( $crew_check[0] ),
	array()
);
people_assert( array( 4 ) === $crew_cleared['remove'], 'clearing TV-show crew removes that crew person' );
people_assert( array() === $crew_cleared['add'], 'clearing TV-show crew adds nobody' );

$movie_changes = streamit_child_person_relation_changes(
	streamit_child_person_ids_from_rows( $people_movie_cast ),
	streamit_child_person_ids_from_rows( $people_movie_cast )
);
people_assert( array() === $movie_changes['add'] && array() === $movie_changes['remove'], 'an unchanged movie cast produces no relation edits' );
people_assert( ! in_array( 1, $movie_changes['remove'], true ), 'TV-show cast people are outside the movie relation diff' );

$foreign = streamit_child_person_relation_changes(
	streamit_child_person_ids_from_rows( $cast_check[0] ),
	streamit_child_person_ids_from_rows( $new_cast )
);
people_assert( ! in_array( 99, $foreign['remove'], true ), 'a person linked only to another TV show is not in this show remove list' );

if ( $failures > 0 ) {
	fwrite( STDERR, $failures . " failed\n" );
	exit( 1 );
}

echo "all people-edit assertions passed\n";

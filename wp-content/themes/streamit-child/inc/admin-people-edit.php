<?php
/**
 * Keep crew labels and TV-show person links intact on Streamit edit saves.
 *
 * The edit form rebuilds `_crew` as id/role/category, so an automation `job`
 * is dropped. Person sliders read `character` then `role`. This fills an
 * empty role for display, and the edit script copies `job` back onto the
 * save payload.
 *
 * update_tvshow() reads the previous cast/crew with streamit_get_movie_meta().
 * During that save only, those two reads return the TV-show rows instead.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A label the editor or the slider can show.
 *
 * @param mixed $value Stored or submitted label.
 * @return bool
 */
function streamit_child_people_label_meaningful( $value ) {
	if ( ! is_scalar( $value ) ) {
		return false;
	}

	return trim( (string) $value ) !== '';
}

/**
 * Crew label shown in the role field and person slider: role, character, then job.
 *
 * @param array<string, mixed> $row Crew or cast row.
 * @return string
 */
function streamit_child_people_display_label( $row ) {
	if ( ! is_array( $row ) ) {
		return '';
	}

	foreach ( array( 'role', 'character', 'job' ) as $key ) {
		if ( isset( $row[ $key ] ) && streamit_child_people_label_meaningful( $row[ $key ] ) ) {
			return trim( (string) $row[ $key ] );
		}
	}

	return '';
}

/**
 * View copy of crew rows with an empty role filled from character or job.
 *
 * The stored row is not written back. An existing role is left as stored.
 *
 * @param mixed $rows `_crew` meta.
 * @return mixed
 */
function streamit_child_people_with_role_label( $rows ) {
	if ( ! is_array( $rows ) ) {
		return $rows;
	}

	$copy = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			$copy[] = $row;
			continue;
		}

		if ( ! isset( $row['role'] ) || ! streamit_child_people_label_meaningful( $row['role'] ) ) {
			$label = streamit_child_people_display_label( $row );
			if ( streamit_child_people_label_meaningful( $label ) ) {
				$row['role'] = $label;
			}
		}

		$copy[] = $row;
	}

	return $copy;
}

/**
 * @param mixed $rows Crew meta from get_meta().
 * @return mixed
 */
function streamit_child_filter_movie_crew_labels( $rows ) {
	return streamit_child_people_with_role_label( $rows );
}

/**
 * @param mixed $rows Crew meta from get_meta().
 * @return mixed
 */
function streamit_child_filter_tvshow_crew_labels( $rows ) {
	return streamit_child_people_with_role_label( $rows );
}

add_filter( 'streamit_movie_get__crew', 'streamit_child_filter_movie_crew_labels' );
add_filter( 'streamit_tvshow_get__crew', 'streamit_child_filter_tvshow_crew_labels' );

/**
 * Whether this movie-meta read is the TV-show edit comparing previous cast/crew.
 *
 * @param string $route_name Current AJAX route.
 * @param mixed  $object_id  Object id passed to get_metadata().
 * @param mixed  $meta_key   Meta key passed to get_metadata().
 * @param mixed  $tvshow_id  TV-show id on the save request.
 * @return bool
 */
function streamit_child_should_read_tvshow_relation_meta( $route_name, $object_id, $meta_key, $tvshow_id ) {
	if ( 'streamit_update_tvshow' !== $route_name ) {
		return false;
	}
	if ( '_cast' !== $meta_key && '_crew' !== $meta_key ) {
		return false;
	}

	$object_id = (int) $object_id;
	$tvshow_id = (int) $tvshow_id;

	return $object_id > 0 && $object_id === $tvshow_id;
}

/**
 * Person ids in cast/crew rows, in stored order.
 *
 * @param mixed $rows Cast or crew rows.
 * @return array<int, mixed>
 */
function streamit_child_person_ids_from_rows( $rows ) {
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$ids = array();

	foreach ( $rows as $row ) {
		if ( is_array( $row ) && array_key_exists( 'id', $row ) ) {
			$ids[] = $row['id'];
		}
	}

	return $ids;
}

/**
 * Add and remove sets used by streamit_maintain_person_relation().
 *
 * Empty new ids remove every old id. Comparison is strict, matching the
 * parent helper. This does not write person meta.
 *
 * @param array<int, mixed> $old_ids Previous person ids.
 * @param array<int, mixed> $new_ids Submitted person ids.
 * @return array{add: array<int, mixed>, remove: array<int, mixed>}
 */
function streamit_child_person_relation_changes( $old_ids, $new_ids ) {
	$old_ids = is_array( $old_ids ) ? $old_ids : array();
	$new_ids = is_array( $new_ids ) ? $new_ids : array();

	if ( array() === $new_ids ) {
		return array(
			'add'    => array(),
			'remove' => array_values( $old_ids ),
		);
	}

	$add    = array();
	$remove = array();

	foreach ( $new_ids as $new_id ) {
		if ( ! in_array( $new_id, $old_ids, true ) ) {
			$add[] = $new_id;
		}
	}

	foreach ( $old_ids as $old_id ) {
		if ( ! in_array( $old_id, $new_ids, true ) ) {
			$remove[] = $old_id;
		}
	}

	return array(
		'add'    => $add,
		'remove' => $remove,
	);
}

/**
 * Short-circuit get_metadata( 'streamit_movie' ) during a TV-show edit save.
 *
 * WordPress returns $check[0] when the filter value is an array and $single
 * is true, so the full row list has to be wrapped once.
 *
 * @param mixed  $value     Existing short-circuit value.
 * @param mixed  $object_id Movie or TV-show id the controller asked for.
 * @param mixed  $meta_key  Meta key.
 * @param mixed  $single    Whether a single value was requested.
 * @return mixed
 */
function streamit_child_redirect_tvshow_relation_meta( $value, $object_id, $meta_key, $single ) {
	if ( ! $single || ! function_exists( 'get_metadata' ) ) {
		return $value;
	}

	$route_name = isset( $_REQUEST['route_name'] ) ? sanitize_key( wp_unslash( $_REQUEST['route_name'] ) ) : '';
	$tvshow_id  = isset( $_REQUEST['tvshow_id'] ) ? absint( wp_unslash( $_REQUEST['tvshow_id'] ) ) : 0;

	if ( ! streamit_child_should_read_tvshow_relation_meta( $route_name, $object_id, $meta_key, $tvshow_id ) ) {
		return $value;
	}

	return array( get_metadata( 'streamit_tvshow', (int) $object_id, (string) $meta_key, true ) );
}
add_filter( 'get_streamit_movie_metadata', 'streamit_child_redirect_tvshow_relation_meta', 10, 4 );

/**
 * Cast and crew stored on the movie or TV show being edited.
 *
 * @param string $type `movie` or `tvshow`.
 * @param int    $post_id Streamit object id.
 * @return array{cast: array<int, mixed>, crew: array<int, mixed>}
 */
function streamit_child_edit_people_meta( $type, $post_id ) {
	$post_id = absint( $post_id );
	$empty   = array(
		'cast' => array(),
		'crew' => array(),
	);

	if ( $post_id <= 0 ) {
		return $empty;
	}

	$is_tvshow = 'tvshow' === $type;
	$getter    = $is_tvshow ? 'streamit_get_tvshow_meta' : 'streamit_get_movie_meta';
	$meta_type = $is_tvshow ? 'streamit_tvshow' : 'streamit_movie';
	$loaded    = $empty;

	foreach ( array( 'cast' => '_cast', 'crew' => '_crew' ) as $slot => $meta_key ) {
		if ( function_exists( $getter ) ) {
			$rows = $getter( $post_id, $meta_key, true );
		} elseif ( function_exists( 'get_metadata' ) ) {
			$rows = get_metadata( $meta_type, $post_id, $meta_key, true );
		} else {
			$rows = array();
		}

		if ( is_string( $rows ) && function_exists( 'maybe_unserialize' ) ) {
			$rows = maybe_unserialize( $rows );
		}

		$loaded[ $slot ] = is_array( $rows ) ? $rows : array();
	}

	return $loaded;
}

/**
 * Edit script for movie and TV-show cast/crew label round-trips.
 *
 * @param string $hook Current admin page hook.
 */
function streamit_child_enqueue_people_edit( $hook ) {
	$screens = array(
		'admin_page_streamit-edit-movie'  => 'movie',
		'admin_page_streamit-edit-tvshow' => 'tvshow',
	);

	if ( ! isset( $screens[ $hook ] ) ) {
		return;
	}

	$js_path = get_stylesheet_directory() . '/assets/js/admin-people-labels.js';
	if ( ! file_exists( $js_path ) ) {
		return;
	}

	wp_enqueue_script(
		'streamit-child-admin-people-labels',
		get_stylesheet_directory_uri() . '/assets/js/admin-people-labels.js',
		array( 'jquery', 'wp-hooks' ),
		(string) filemtime( $js_path ),
		true
	);

	$post_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$people  = streamit_child_edit_people_meta( $screens[ $hook ], $post_id );
	$cast    = wp_json_encode( $people['cast'] );
	$crew    = wp_json_encode( $people['crew'] );

	wp_localize_script(
		'streamit-child-admin-people-labels',
		'streamitChildPeople',
		array(
			'castJson' => is_string( $cast ) ? $cast : '[]',
			'crewJson' => is_string( $crew ) ? $crew : '[]',
		)
	);
}
add_action( 'admin_enqueue_scripts', 'streamit_child_enqueue_people_edit', 120 );

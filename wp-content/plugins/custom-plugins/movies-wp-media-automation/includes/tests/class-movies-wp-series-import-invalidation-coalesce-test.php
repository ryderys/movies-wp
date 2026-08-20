<?php
/**
 * Isolated tests for Series Import invalidation coalescing.
 *
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-import-invalidation-coalesce-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-import-coalesce-test/' );
}

if ( ! class_exists( 'WP_Hook' ) ) {
	class WP_Hook {
		/**
		 * @var array<int, array<int, array{function:callable,accepted_args:int}>>
		 */
		public $callbacks = array();
	}
}

if ( ! isset( $GLOBALS['wp_filter'] ) || ! is_array( $GLOBALS['wp_filter'] ) ) {
	$GLOBALS['wp_filter'] = array();
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$hook = (string) $hook;
		if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) || ! $GLOBALS['wp_filter'][ $hook ] instanceof WP_Hook ) {
			$GLOBALS['wp_filter'][ $hook ] = new WP_Hook();
		}
		$GLOBALS['wp_filter'][ $hook ]->callbacks[ (int) $priority ][] = array(
			'function'      => $callback,
			'accepted_args' => (int) $accepted_args,
		);
	}
}
if ( ! function_exists( 'remove_all_actions' ) ) {
	function remove_all_actions( $hook ) {
		unset( $GLOBALS['wp_filter'][ (string) $hook ] );
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		$hook = (string) $hook;
		if ( empty( $GLOBALS['wp_filter'][ $hook ] ) || ! $GLOBALS['wp_filter'][ $hook ] instanceof WP_Hook ) {
			return;
		}
		foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $group ) {
			foreach ( $group as $entry ) {
				$n = isset( $entry['accepted_args'] ) ? (int) $entry['accepted_args'] : 1;
				call_user_func_array( $entry['function'], array_slice( $args, 0, $n ) );
			}
		}
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $key ) );
	}
}

$flush_count = 0;
function streamit_child_flush_streamit_cache() {
	global $flush_count;
	++$flush_count;
}

$invalidate_count = 0;
function streamit_child_invalidate_object_meta_cache( $type, $id ) {
	global $invalidate_count;
	unset( $type, $id );
	++$invalidate_count;
}

$live_invalidations = 0;
add_action(
	'streamit_after_update_episode',
	static function () {
		global $live_invalidations;
		++$live_invalidations;
	},
	10,
	2
);
add_action(
	'updated_streamit_episode_meta',
	static function () {
		global $live_invalidations;
		++$live_invalidations;
	},
	10,
	2
);
add_action(
	'streamit_after_update_movie',
	static function () {
		global $live_invalidations;
		++$live_invalidations;
	},
	10,
	2
);

require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-invalidation-coalesce.php';

$failures = 0;
function coalesce_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

echo "Series import invalidation coalesce\n";

Movies_WP_Series_Import_Invalidation_Coalesce::reset_for_tests();
$live_invalidations = 0;
do_action( 'streamit_after_update_episode', 10, array() );
coalesce_assert( 1 === $live_invalidations, 'normal non-import episode update still invalidates' );
do_action( 'streamit_after_update_movie', 20, array() );
coalesce_assert( 2 === $live_invalidations, 'normal non-import movie update still invalidates' );

$flush_count        = 0;
$live_invalidations = 0;
Movies_WP_Series_Import_Invalidation_Coalesce::begin();
coalesce_assert( Movies_WP_Series_Import_Invalidation_Coalesce::is_active(), 'Series import worker enters coalescing mode' );
do_action( 'streamit_after_update_episode', 101, array( 'post_name' => 'ep-101' ) );
do_action( 'updated_streamit_episode_meta', 1, 101 );
do_action( 'streamit_after_update_episode', 102, array( 'post_name' => 'ep-102' ) );
do_action( 'updated_streamit_episode_meta', 2, 102 );
coalesce_assert( 0 === $live_invalidations, 'live child invalidation is deferred during the chunk' );
coalesce_assert( 0 === $flush_count, 'full Streamit flush is not run per episode' );
coalesce_assert( 2 === Movies_WP_Series_Import_Invalidation_Coalesce::pending_count(), 'multiple episode writes collapse to unique objects' );
Movies_WP_Series_Import_Invalidation_Coalesce::finish();
coalesce_assert( 1 === $flush_count, 'required invalidation occurs once when the chunk ends' );
coalesce_assert( 0 === $invalidate_count, 'row-update chunks skip per-object meta invalidation after a full flush' );
coalesce_assert( Movies_WP_Series_Import_Invalidation_Coalesce::did_flush(), 'finish records a flush' );
coalesce_assert( ! Movies_WP_Series_Import_Invalidation_Coalesce::is_active(), 'coalescing state is cleared after success' );
coalesce_assert( 0 === Movies_WP_Series_Import_Invalidation_Coalesce::pending_count(), 'pending objects are cleared after success' );

$live_invalidations = 0;
do_action( 'streamit_after_update_episode', 103, array() );
coalesce_assert( 1 === $live_invalidations, 'after finish, episode updates invalidate immediately again' );

$flush_count        = 0;
$live_invalidations = 0;
Movies_WP_Series_Import_Invalidation_Coalesce::begin();
do_action( 'streamit_after_update_episode', 201, array() );
try {
	throw new RuntimeException( 'chunk failed' );
} catch ( RuntimeException $e ) {
	Movies_WP_Series_Import_Invalidation_Coalesce::finish();
}
coalesce_assert( ! Movies_WP_Series_Import_Invalidation_Coalesce::is_active(), 'coalescing state is cleared after failure' );
coalesce_assert( 1 === $flush_count, 'failure still performs the deferred flush' );
$live_invalidations = 0;
do_action( 'streamit_after_update_episode', 202, array() );
coalesce_assert( 1 === $live_invalidations, 'hooks are restored after failure' );

$flush_count = 0;
Movies_WP_Series_Import_Invalidation_Coalesce::begin();
do_action( 'streamit_after_update_episode', 301, array() );
Movies_WP_Series_Import_Invalidation_Coalesce::finish();
$first_flush = $flush_count;
$flush_count = 0;
Movies_WP_Series_Import_Invalidation_Coalesce::begin();
coalesce_assert( 0 === Movies_WP_Series_Import_Invalidation_Coalesce::pending_count(), 'a second worker does not inherit pending objects' );
do_action( 'streamit_after_update_episode', 302, array() );
Movies_WP_Series_Import_Invalidation_Coalesce::finish();
coalesce_assert( 1 === $first_flush && 1 === $flush_count, 'a second worker flush is independent' );

$movie_live = 0;
add_action(
	'streamit_after_update_movie',
	static function () use ( &$movie_live ) {
		++$movie_live;
	},
	11,
	2
);
do_action( 'streamit_after_update_movie', 9, array() );
coalesce_assert( $movie_live > 0, 'movie import/update hooks are not removed outside a Series worker' );

$flush_count        = 0;
$invalidate_count   = 0;
Movies_WP_Series_Import_Invalidation_Coalesce::begin();
do_action( 'updated_streamit_episode_meta', 1, 401 );
do_action( 'updated_streamit_episode_meta', 2, 401 );
do_action( 'added_streamit_episode_meta', 3, 402 );
Movies_WP_Series_Import_Invalidation_Coalesce::finish();
coalesce_assert( 0 === $flush_count, 'create/meta-only chunks do not call the full group flush' );
coalesce_assert( 2 === $invalidate_count, 'create/meta-only chunks invalidate each unique object once' );

if ( $failures > 0 ) {
	echo "FAILED {$failures} Series import invalidation coalesce assertion(s).\n";
	exit( 1 );
}
echo "All Series import invalidation coalesce tests passed.\n";
exit( 0 );

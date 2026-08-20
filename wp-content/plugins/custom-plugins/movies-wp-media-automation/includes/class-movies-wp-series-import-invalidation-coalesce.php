<?php
/**
 * Defer Streamit-child cache invalidation for one Series Import worker run.
 *
 * Live site hooks (streamit-child) flush every Streamit cache group on each
 * TV/episode row update. During an import chunk those flushes are redundant:
 * namespace bumps, transient deletes, and wp_cache_flush() leave the same
 * final read state if performed once at the end of the chunk.
 *
 * Does not modify Streamit core. Does not disable invalidation. Restores the
 * original hooks even if the worker fails.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Import_Invalidation_Coalesce {

	/**
	 * @var int
	 */
	private static $depth = 0;

	/**
	 * @var array<string, object|null>
	 */
	private static $saved_hooks = array();

	/**
	 * @var array<string, array{type:string,id:int,data:array}>
	 */
	private static $pending_objects = array();

	/**
	 * @var bool
	 */
	private static $had_row_update = false;

	/**
	 * @var bool
	 */
	private static $flushed = false;

	/**
	 * @var int
	 */
	private static $meta_invalidations = 0;

	public static function is_active() {
		return self::$depth > 0;
	}

	public static function reset_for_tests() {
		self::$depth              = 0;
		self::$saved_hooks        = array();
		self::$pending_objects    = array();
		self::$had_row_update     = false;
		self::$flushed            = false;
		self::$meta_invalidations = 0;
	}

	/**
	 * @return list<string>
	 */
	public static function hooked_names() {
		$hooks = array();
		foreach ( array( 'episode', 'movie', 'tvshow', 'video', 'person' ) as $type ) {
			$hooks[] = 'streamit_after_update_' . $type;
			$hooks[] = 'added_streamit_' . $type . '_meta';
			$hooks[] = 'updated_streamit_' . $type . '_meta';
			$hooks[] = 'deleted_streamit_' . $type . '_meta';
		}
		return $hooks;
	}

	public static function begin() {
		if ( self::$depth > 0 ) {
			++self::$depth;
			return;
		}
		if ( ! function_exists( 'add_action' ) || ! function_exists( 'remove_all_actions' ) ) {
			return;
		}
		if ( ! function_exists( 'streamit_child_flush_streamit_cache' ) && ! function_exists( 'streamit_child_invalidate_object_meta_cache' ) ) {
			return;
		}
		self::$saved_hooks     = array();
		self::$pending_objects = array();
		self::$had_row_update  = false;
		self::$flushed         = false;
		self::$meta_invalidations = 0;
		foreach ( self::hooked_names() as $hook ) {
			self::$saved_hooks[ $hook ] = isset( $GLOBALS['wp_filter'][ $hook ] ) && is_object( $GLOBALS['wp_filter'][ $hook ] )
				? clone $GLOBALS['wp_filter'][ $hook ]
				: null;
			remove_all_actions( $hook );
			$type = self::type_from_hook( $hook );
			if ( 0 === strpos( $hook, 'streamit_after_update_' ) ) {
				add_action(
					$hook,
					static function ( $object_id, $prepared_data = array() ) use ( $type ) {
						Movies_WP_Series_Import_Invalidation_Coalesce::record_object( $type, $object_id, is_array( $prepared_data ) ? $prepared_data : array(), true );
					},
					10,
					2
				);
			} else {
				add_action(
					$hook,
					static function ( $meta_id, $object_id ) use ( $type ) {
						unset( $meta_id );
						Movies_WP_Series_Import_Invalidation_Coalesce::record_object( $type, $object_id, array(), false );
					},
					10,
					2
				);
			}
		}
		self::$depth = 1;
	}

	public static function finish() {
		if ( self::$depth <= 0 ) {
			return;
		}
		--self::$depth;
		if ( self::$depth > 0 ) {
			return;
		}
		try {
			self::restore_hooks();
			self::flush_pending();
		} finally {
			self::$saved_hooks     = array();
			self::$pending_objects = array();
			self::$had_row_update  = false;
			self::$depth           = 0;
		}
	}

	public static function record_object( $type, $object_id, array $prepared_data, $row_update = false ) {
		$type = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $type ) : strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $type ) );
		$id   = absint( $object_id );
		if ( '' === $type || $id <= 0 ) {
			return;
		}
		if ( $row_update ) {
			self::$had_row_update = true;
		}
		$key = $type . ':' . $id;
		if ( ! isset( self::$pending_objects[ $key ] ) ) {
			self::$pending_objects[ $key ] = array(
				'type' => $type,
				'id'   => $id,
				'data' => $prepared_data,
			);
			return;
		}
		if ( array() !== $prepared_data ) {
			self::$pending_objects[ $key ]['data'] = $prepared_data;
		}
	}

	public static function pending_count() {
		return count( self::$pending_objects );
	}

	public static function did_flush() {
		return self::$flushed;
	}

	public static function meta_invalidation_count() {
		return self::$meta_invalidations;
	}

	public static function had_row_update() {
		return self::$had_row_update;
	}

	private static function restore_hooks() {
		if ( ! function_exists( 'remove_all_actions' ) ) {
			return;
		}
		foreach ( self::$saved_hooks as $hook => $saved ) {
			remove_all_actions( $hook );
			if ( null !== $saved ) {
				$GLOBALS['wp_filter'][ $hook ] = $saved;
			}
		}
	}

	private static function flush_pending() {
		if ( self::$had_row_update && function_exists( 'streamit_child_flush_streamit_cache' ) ) {
			streamit_child_flush_streamit_cache();
			self::$flushed = true;
			self::purge_rocket_urls();
			return;
		}
		if ( function_exists( 'streamit_child_invalidate_object_meta_cache' ) ) {
			foreach ( self::$pending_objects as $item ) {
				streamit_child_invalidate_object_meta_cache( $item['type'], $item['id'] );
				++self::$meta_invalidations;
			}
		}
	}

	private static function purge_rocket_urls() {
		if ( ! function_exists( 'rocket_clean_files' ) || ! function_exists( 'streamit_get_permalink' ) ) {
			return;
		}
		$urls = array();
		foreach ( self::$pending_objects as $item ) {
			$url = self::object_url( $item['type'], $item['id'], $item['data'] );
			if ( is_string( $url ) && '' !== $url ) {
				$urls[ $url ] = true;
			}
		}
		foreach ( array_keys( $urls ) as $url ) {
			rocket_clean_files( $url );
		}
	}

	/**
	 * @param array<string, mixed> $prepared_data
	 */
	private static function object_url( $type, $object_id, array $prepared_data ) {
		$slug = '';
		if ( ! empty( $prepared_data['post_name'] ) ) {
			$slug = (string) $prepared_data['post_name'];
		} else {
			$getter = 'streamit_get_' . $type;
			if ( function_exists( $getter ) ) {
				$obj = call_user_func( $getter, $object_id );
				if ( is_object( $obj ) && method_exists( $obj, 'get_post_name' ) ) {
					$slug = (string) $obj->get_post_name();
				}
			}
		}
		if ( '' === $slug ) {
			return '';
		}
		$url = streamit_get_permalink( $type, $slug );
		return is_string( $url ) ? $url : '';
	}

	private static function type_from_hook( $hook ) {
		$hook = (string) $hook;
		if ( 0 === strpos( $hook, 'streamit_after_update_' ) ) {
			return substr( $hook, strlen( 'streamit_after_update_' ) );
		}
		if ( preg_match( '/^(?:added|updated|deleted)_streamit_([a-z]+)_meta$/', $hook, $m ) ) {
			return $m[1];
		}
		return '';
	}
}

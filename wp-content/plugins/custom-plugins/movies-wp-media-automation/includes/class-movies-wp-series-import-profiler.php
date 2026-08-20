<?php
/**
 * Temporary Series import timing instrumentation.
 *
 * Writes progress as it happens so a 504 still leaves a trail. Does not change
 * import decisions or persistence. Remove after the bottleneck is identified.
 *
 * Disabled unless:
 *   define( 'MOVIES_WP_SERIES_IMPORT_PROFILE', true );
 *
 * Remove after diagnosis: this class plus every Movies_WP_Series_Import_Profiler call.
 *
 * Log: wp-content/uploads/movies-wp-series-import-profile.log
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Import_Profiler {

	/**
	 * @var float|null
	 */
	private static $started_at = null;

	/**
	 * @var string
	 */
	private static $log_file = '';

	/**
	 * @var array<string, array{count:int,total:float,slowest:float,slowest_label:string,objects:int}>
	 */
	private static $buckets = array();

	/**
	 * @var array<string, mixed>
	 */
	private static $http_pending = array();

	/**
	 * @var int
	 */
	private static $http_count = 0;

	/**
	 * @var float
	 */
	private static $http_total = 0.0;

	/**
	 * @var int
	 */
	private static $db_queries_start = 0;

	/**
	 * @var int
	 */
	private static $source_index = 0;

	/**
	 * @var int
	 */
	private static $subtitle_index = 0;

	/**
	 * @var int
	 */
	private static $episode_index = 0;

	/**
	 * @var int
	 */
	private static $still_index = 0;

	/**
	 * @var bool
	 */
	private static $hooks_installed = false;

	/**
	 * @var string
	 */
	private static $last_phase = '';

	/**
	 * @var array<string, string>
	 */
	private static $notes = array();

	/**
	 * @var string
	 */
	private static $phase = '';

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private static $flushes = array();

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private static $group_flushes = array();

	/**
	 * @var array<string, mixed>|null
	 */
	private static $current_episode = null;

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private static $episode_reports = array();

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private static $meta_ops = array();

	/**
	 * @var array<string, float>
	 */
	private static $pending_meta_writes = array();

	/**
	 * @var bool
	 */
	private static $child_window_open = false;

	/**
	 * @var float
	 */
	private static $child_window_started = 0.0;

	/**
	 * @var int
	 */
	private static $child_window_redis_deletes = 0;

	/**
	 * @var int
	 */
	private static $child_window_redis_sets = 0;

	/**
	 * @var float
	 */
	private static $child_window_redis_delete_s = 0.0;

	/**
	 * @var float
	 */
	private static $child_window_redis_set_s = 0.0;

	/**
	 * @var int
	 */
	private static $redis_deletes = 0;

	/**
	 * @var int
	 */
	private static $redis_sets = 0;

	/**
	 * @var bool
	 */
	private static $sql_window_open = false;

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private static $sql_window_queries = array();

	/**
	 * @var bool
	 */
	private static $wpdb_save_queries_prev = false;

	/**
	 * @var int
	 */
	private static $sql_window_query_index = 0;

	public static function enabled() {
		return defined( 'MOVIES_WP_SERIES_IMPORT_PROFILE' ) && MOVIES_WP_SERIES_IMPORT_PROFILE;
	}

	public static function is_active() {
		return self::enabled() && null !== self::$started_at;
	}

	public static function queries() {
		return self::query_count();
	}

	public static function start( $label = 'IMPORT START' ) {
		if ( ! self::enabled() || self::is_active() ) {
			return;
		}
		self::$started_at       = microtime( true );
		self::$buckets          = array();
		self::$http_pending     = array();
		self::$http_count       = 0;
		self::$http_total       = 0.0;
		self::$source_index     = 0;
		self::$subtitle_index   = 0;
		self::$episode_index    = 0;
		self::$still_index      = 0;
		self::$last_phase       = '';
		self::$notes            = array();
		self::$phase            = 'start';
		self::$flushes          = array();
		self::$group_flushes    = array();
		self::$current_episode  = null;
		self::$episode_reports  = array();
		self::$meta_ops         = array();
		self::$pending_meta_writes = array();
		self::$child_window_open = false;
		self::$child_window_started = 0.0;
		self::$child_window_redis_deletes = 0;
		self::$child_window_redis_sets = 0;
		self::$child_window_redis_delete_s = 0.0;
		self::$child_window_redis_set_s = 0.0;
		self::$redis_deletes    = 0;
		self::$redis_sets       = 0;
		self::$sql_window_open  = false;
		self::$sql_window_queries = array();
		self::$log_file         = self::resolve_log_file();
		self::$db_queries_start = self::query_count();
		self::install_hooks();
		register_shutdown_function( array( __CLASS__, 'shutdown' ) );
		self::progress( $label );
		self::log_request_context();
		self::log_episode_hooks();
	}

	public static function note( $key, $value ) {
		self::$notes[ (string) $key ] = (string) $value;
		if ( self::is_active() ) {
			self::progress( sprintf( 'NOTE %s=%s', (string) $key, (string) $value ) );
		}
	}

	public static function progress( $message ) {
		if ( ! self::is_active() ) {
			return;
		}
		self::$last_phase = (string) $message;
		$elapsed          = microtime( true ) - self::$started_at;
		$queries          = self::query_count() - self::$db_queries_start;
		$line             = sprintf(
			'[+%7.3fs / %dms] q=%d http=%d | %s',
			$elapsed,
			(int) round( $elapsed * 1000 ),
			max( 0, $queries ),
			self::$http_count,
			$message
		);
		self::write_line( $line );
	}

	/**
	 * @param string $bucket
	 * @return float
	 */
	public static function begin( $bucket ) {
		unset( $bucket );
		return microtime( true );
	}

	/**
	 * @return array{t:float,q:int,http:int}
	 */
	public static function phase_start( $name ) {
		$snap = array(
			't'    => microtime( true ),
			'q'    => self::query_count(),
			'http' => self::$http_count,
		);
		if ( self::is_active() ) {
			self::progress(
				sprintf(
					'PHASE START %s q_before=%d http_before=%d',
					(string) $name,
					$snap['q'] - self::$db_queries_start,
					$snap['http']
				)
			);
		}
		return $snap;
	}

	/**
	 * @param array{t:float,q:int,http:int} $snap
	 */
	public static function phase_end( $name, array $snap, $objects = 0, $note = '' ) {
		if ( ! self::is_active() ) {
			return;
		}
		$ms    = (int) round( ( microtime( true ) - (float) $snap['t'] ) * 1000 );
		$dq    = self::query_count() - (int) $snap['q'];
		$dhttp = self::$http_count - (int) $snap['http'];
		self::end( (string) $name, (float) $snap['t'], (string) $note, (int) $objects );
		self::progress(
			sprintf(
				'PHASE END %s elapsed_ms=%d objects=%d dq=%d dhttp=%d %s',
				(string) $name,
				$ms,
				(int) $objects,
				$dq,
				$dhttp,
				(string) $note
			)
		);
	}

	/**
	 * @param string $bucket
	 * @param float  $started
	 * @param string $label
	 * @param int    $objects
	 */
	public static function end( $bucket, $started, $label = '', $objects = 0 ) {
		if ( ! self::is_active() ) {
			return;
		}
		$duration = microtime( true ) - (float) $started;
		if ( ! isset( self::$buckets[ $bucket ] ) ) {
			self::$buckets[ $bucket ] = array(
				'count'         => 0,
				'total'         => 0.0,
				'slowest'       => 0.0,
				'slowest_label' => '',
				'objects'       => 0,
			);
		}
		self::$buckets[ $bucket ]['count']++;
		self::$buckets[ $bucket ]['total'] += $duration;
		self::$buckets[ $bucket ]['objects'] += (int) $objects;
		if ( $duration >= self::$buckets[ $bucket ]['slowest'] ) {
			self::$buckets[ $bucket ]['slowest']       = $duration;
			self::$buckets[ $bucket ]['slowest_label'] = (string) $label;
		}
	}

	public static function mark_episode_created( $season, $episode, array $details = array() ) {
		if ( ! self::is_active() ) {
			return;
		}
		++self::$episode_index;
		self::progress(
			sprintf(
				'EPISODE_TIMING S%02dE%02d action=%s episode_id=%d episode_total_ms=%d streamit_add_episode_ms=%d insert_span_ms=%d metadata_ms=%d add_metadata_ms=%d child_invalidation_ms=%d still_ms=%d http_ms=%d http_count=%d flush_count=%d flush_ms=%d flush_keys=%d query_count=%d unaccounted_ms=%d',
				(int) $season,
				(int) $episode,
				(string) ( $details['action'] ?? '' ),
				(int) ( $details['episode_id'] ?? 0 ),
				(int) ( $details['elapsed_ms'] ?? 0 ),
				(int) ( $details['streamit_add_episode_ms'] ?? $details['insert_ms'] ?? 0 ),
				(int) ( $details['insert_span_ms'] ?? $details['insert_ms'] ?? 0 ),
				(int) ( $details['meta_ms'] ?? 0 ),
				(int) ( $details['add_metadata_ms'] ?? self::sum_meta_field( 'write_ms' ) ),
				(int) ( $details['child_invalidation_ms'] ?? self::sum_meta_field( 'child_ms' ) ),
				(int) ( $details['still_ms'] ?? 0 ),
				(int) ( $details['http_ms'] ?? 0 ),
				(int) ( $details['http_count'] ?? 0 ),
				(int) ( $details['flush_count'] ?? self::current_episode_int( 'flush_count' ) ),
				(int) ( $details['flush_ms'] ?? self::current_episode_int( 'flush_ms' ) ),
				(int) ( $details['flush_keys'] ?? self::current_episode_int( 'flush_keys' ) ),
				(int) ( $details['dq'] ?? 0 ),
				(int) ( $details['unaccounted_ms'] ?? 0 )
			)
		);
	}

	public static function mark_source( $path ) {
		if ( ! self::is_active() ) {
			return;
		}
		++self::$source_index;
		self::progress( sprintf( 'SOURCE %d path=%s', self::$source_index, (string) $path ) );
	}

	public static function mark_subtitle( $path ) {
		if ( ! self::is_active() ) {
			return;
		}
		++self::$subtitle_index;
		self::progress( sprintf( 'SUBTITLE %d path=%s', self::$subtitle_index, (string) $path ) );
	}

	public static function mark_still( $season, $episode, $action ) {
		if ( ! self::is_active() ) {
			return;
		}
		++self::$still_index;
		self::progress(
			sprintf(
				'EPISODE STILL %d S%02dE%02d action=%s',
				self::$still_index,
				(int) $season,
				(int) $episode,
				(string) $action
			)
		);
	}

	public static function finish( $status = 'IMPORT COMPLETE' ) {
		if ( ! self::is_active() ) {
			return;
		}
		self::progress( $status );
		self::write_summary();
		self::$started_at = null;
	}

	public static function shutdown() {
		if ( ! self::is_active() ) {
			return;
		}
		$error = function_exists( 'error_get_last' ) ? error_get_last() : null;
		$fatal = is_array( $error ) && in_array( (int) ( $error['type'] ?? 0 ), array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true );
		self::progress(
			$fatal
				? 'IMPORT ABORTED fatal=' . (string) ( $error['message'] ?? '' )
				: 'IMPORT ABORTED last_phase=' . self::$last_phase
		);
		self::write_summary();
		self::$started_at = null;
	}

	/**
	 * @param mixed  $preempt
	 * @param array  $args
	 * @param string $url
	 * @return mixed
	 */
	public static function http_begin( $preempt, $args, $url ) {
		unset( $args );
		if ( self::is_active() ) {
			self::$http_pending[ (string) $url ] = microtime( true );
		}
		return $preempt;
	}

	/**
	 * @param array  $response
	 * @param array  $args
	 * @param string $url
	 * @return array
	 */
	public static function http_count() {
		return self::$http_count;
	}

	public static function http_total_ms() {
		return (int) round( self::$http_total * 1000 );
	}

	public static function reset_episode_observers() {
		self::$meta_ops        = array();
		self::$current_episode = null;
	}

	public static function begin_episode_window( $t0 = null ) {
		self::open_episode_window( null === $t0 ? microtime( true ) : (float) $t0 );
	}

	public static function log_request_context() {
		if ( ! self::is_active() ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$uri = preg_replace( '/([?&])(pass|password|token|key|secret|authorization)=[^&]*/i', '$1$2=redacted', $uri );
		self::progress(
			sprintf(
				'REQUEST_CONTEXT sapi=%s is_admin=%s wp_cli=%s streamit_disable_cache=%s uri=%s method=%s redis_prefix=%s selective_flush=%s timeout=%s read_timeout=%s',
				PHP_SAPI,
				( function_exists( 'is_admin' ) && is_admin() ) ? 'yes' : 'no',
				( defined( 'WP_CLI' ) && WP_CLI ) ? 'yes' : 'no',
				( function_exists( 'apply_filters' ) && apply_filters( 'streamit_disable_cache', false, 'profiler', null ) ) ? 'yes' : 'no',
				$uri,
				isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '',
				defined( 'WP_REDIS_PREFIX' ) ? (string) WP_REDIS_PREFIX : '',
				defined( 'WP_REDIS_SELECTIVE_FLUSH' ) ? ( WP_REDIS_SELECTIVE_FLUSH ? 'yes' : 'no' ) : 'undefined',
				defined( 'WP_REDIS_TIMEOUT' ) ? (string) WP_REDIS_TIMEOUT : 'undefined',
				defined( 'WP_REDIS_READ_TIMEOUT' ) ? (string) WP_REDIS_READ_TIMEOUT : 'undefined'
			)
		);
		$as = self::action_scheduler_counts();
		self::progress(
			sprintf(
				'REQUEST_AS pending=%s in_progress=%s',
				null === $as['pending'] ? 'n/a' : (string) $as['pending'],
				null === $as['in-progress'] ? 'n/a' : (string) $as['in-progress']
			)
		);
	}

	public static function log_episode_hooks() {
		if ( ! self::is_active() ) {
			return;
		}
		$hooks = array(
			'streamit_before_episode_insert',
			'streamit_after_episode_insert',
			'add_streamit_episode_meta',
			'added_streamit_episode_meta',
			'update_streamit_episode_meta',
			'updated_streamit_episode_meta',
		);
		foreach ( $hooks as $hook ) {
			self::progress( 'HOOK ' . $hook . '=' . wp_json_encode( self::hook_callback_detail( $hook ) ) );
		}
		if ( isset( $GLOBALS['wp_filter'] ) && is_array( $GLOBALS['wp_filter'] ) ) {
			$names = array();
			foreach ( array_keys( $GLOBALS['wp_filter'] ) as $hook ) {
				if ( is_string( $hook ) && preg_match( '/(episode_insert|episode_meta|streamit_episode)/i', $hook ) ) {
					$names[] = $hook;
				}
			}
			self::progress( 'HOOK_NAMES episode_related=' . wp_json_encode( array_values( $names ) ) );
		}
	}

	public static function http_end( $response, $args, $url ) {
		unset( $args );
		if ( ! self::is_active() ) {
			return $response;
		}
		$url     = (string) $url;
		$started = self::$http_pending[ $url ] ?? microtime( true );
		unset( self::$http_pending[ $url ] );
		$duration = microtime( true ) - $started;
		self::$http_count++;
		self::$http_total += $duration;
		$bucket = self::http_bucket( $url );
		self::end( $bucket, $started, $url, 1 );
		$code = is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
		self::progress(
			sprintf(
				'HTTP %s elapsed_ms=%d status=%d %s',
				$bucket,
				(int) round( $duration * 1000 ),
				$code,
				self::short_url( $url )
			)
		);
		return $response;
	}

	public static function set_phase( $phase ) {
		self::$phase = (string) $phase;
		if ( self::is_active() ) {
			self::progress( 'PHASE ' . self::$phase );
		}
	}

	/**
	 * @param callable $fn
	 * @return mixed
	 */
	public static function measure_callable( $label, callable $fn ) {
		$label = (string) $label;
		self::set_phase( $label );
		$t0    = microtime( true );
		$q0    = self::query_count();
		$flush = count( self::$flushes );
		if ( 'streamit_add_episode' === $label ) {
			self::open_episode_window( $t0 );
		}
		$result = $fn();
		$ms     = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		if ( 'streamit_add_episode' === $label && is_array( self::$current_episode ) ) {
			self::$current_episode['add_episode_ms']       = $ms;
			self::$current_episode['add_episode_returned'] = microtime( true );
			self::$current_episode['dq']                   = self::query_count() - $q0;
		}
		self::end( $label, $t0, $label, 1 );
		self::progress(
			sprintf(
				'CALL %s elapsed_ms=%d dq=%d flushes=%d',
				$label,
				$ms,
				self::query_count() - $q0,
				count( self::$flushes ) - $flush
			)
		);
		return $result;
	}

	public static function close_episode_meta_window() {
		if ( ! is_array( self::$current_episode ) ) {
			return;
		}
		self::$current_episode['meta_ops'] = array_values(
			array_filter(
				self::$meta_ops,
				static function ( $op ) {
					return 'episode' === ( $op['object_type'] ?? '' );
				}
			)
		);
		self::$current_episode['add_metadata_ms']     = self::sum_meta_field( 'write_ms' );
		self::$current_episode['child_invalidate_ms'] = self::sum_meta_field( 'child_ms' );
		self::$current_episode['meta_ops_count']      = count( self::$current_episode['meta_ops'] );
		self::$current_episode['child_redis_deletes'] = self::sum_meta_field( 'redis_deletes' );
		self::$current_episode['child_redis_sets']    = self::sum_meta_field( 'redis_sets' );
		self::$episode_reports[]                      = self::$current_episode;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function last_episode_report() {
		if ( array() === self::$episode_reports ) {
			return is_array( self::$current_episode ) ? self::$current_episode : array();
		}
		return self::$episode_reports[ count( self::$episode_reports ) - 1 ];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function environment_snapshot() {
		$as = self::action_scheduler_counts();
		$oc = ( isset( $GLOBALS['wp_object_cache'] ) && is_object( $GLOBALS['wp_object_cache'] ) )
			? $GLOBALS['wp_object_cache']
			: null;
		$redis = array(
			'dbsize'       => null,
			'prefix_keys'  => null,
			'prefix_scan_ms' => null,
		);
		if ( $oc ) {
			$redis = array_merge( $redis, self::redis_key_counts( $oc ) );
		}
		return array(
			'sapi'                    => PHP_SAPI,
			'is_admin'                => function_exists( 'is_admin' ) && is_admin(),
			'wp_cli'                  => defined( 'WP_CLI' ) && WP_CLI,
			'streamit_disable_cache'  => function_exists( 'apply_filters' ) ? (bool) apply_filters( 'streamit_disable_cache', false, 'profiler', null ) : false,
			'wp_redis_prefix'         => defined( 'WP_REDIS_PREFIX' ) ? (string) WP_REDIS_PREFIX : '',
			'wp_redis_selective_flush' => defined( 'WP_REDIS_SELECTIVE_FLUSH' ) ? (bool) WP_REDIS_SELECTIVE_FLUSH : null,
			'wp_redis_timeout'        => defined( 'WP_REDIS_TIMEOUT' ) ? WP_REDIS_TIMEOUT : null,
			'wp_redis_read_timeout'   => defined( 'WP_REDIS_READ_TIMEOUT' ) ? WP_REDIS_READ_TIMEOUT : null,
			'cache_class'             => $oc ? get_class( $oc ) : '',
			'redis_status'            => ( $oc && method_exists( $oc, 'redis_status' ) ) ? (bool) $oc->redis_status() : null,
			'redis_dbsize'            => $redis['dbsize'],
			'redis_prefix_keys'       => $redis['prefix_keys'],
			'redis_prefix_scan_ms'    => $redis['prefix_scan_ms'],
			'as_pending'              => $as['pending'],
			'as_in_progress'          => $as['in-progress'],
			'as_by_status'            => $as['by_status'],
			'episode_meta_hooks'      => self::hook_callback_counts( 'added_streamit_episode_meta' ),
			'updated_episode_meta_hooks' => self::hook_callback_counts( 'updated_streamit_episode_meta' ),
		);
	}

	public static function print_environment( $label = 'ENV' ) {
		$env = self::environment_snapshot();
		foreach ( $env as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? 'yes' : 'no';
			} elseif ( null === $value ) {
				$value = 'null';
			}
			$line = $label . ' ' . $key . '=' . $value;
			echo $line . "\n";
			if ( self::is_active() ) {
				self::progress( $line );
			}
		}
	}

	public static function print_last_episode_report() {
		$report = self::last_episode_report();
		$lines  = array(
			'STREAMIT_EPISODE_CREATE',
			'mysql_insert_ms=' . (int) ( $report['mysql_insert_ms'] ?? -1 ),
			'mysql_insert_note=before_to_after_minus_redis_flush_includes_options_transient_delete',
			'cache_flush_ms=' . (int) ( $report['flush_ms'] ?? 0 ),
			'cache_flush_us=' . (int) ( $report['flush_us'] ?? 0 ),
			'cache_flush_keys=' . (int) ( $report['flush_keys'] ?? 0 ),
			'cache_flush_count=' . (int) ( $report['flush_count'] ?? 0 ),
			'create_hook_span_ms=' . (int) ( $report['create_hook_span_ms'] ?? -1 ),
			'streamit_add_episode_ms=' . (int) ( $report['add_episode_ms'] ?? -1 ),
			'add_metadata_ms=' . (int) ( $report['add_metadata_ms'] ?? 0 ),
			'streamit_child_invalidate_object_meta_cache_ms=' . (int) ( $report['child_invalidate_ms'] ?? 0 ),
			'meta_ops=' . (int) ( $report['meta_ops_count'] ?? 0 ),
			'child_redis_deletes=' . (int) ( $report['child_redis_deletes'] ?? 0 ),
			'child_redis_sets=' . (int) ( $report['child_redis_sets'] ?? 0 ),
			'total_episode_meta_ms=' . (int) ( ( $report['add_metadata_ms'] ?? 0 ) + ( $report['child_invalidate_ms'] ?? 0 ) ),
		);
		foreach ( $lines as $line ) {
			echo $line . "\n";
			if ( self::is_active() ) {
				self::progress( $line );
			}
		}
		foreach ( self::$flushes as $i => $flush ) {
			$line = sprintf(
				'CACHE_FLUSH[%d] phase=%s execute_ms=%d execute_us=%d matched_keys=%d selective=%s prefix_salt_set=%s context=%s',
				$i,
				(string) ( $flush['phase'] ?? '' ),
				(int) ( $flush['execute_ms'] ?? 0 ),
				(int) ( $flush['execute_us'] ?? 0 ),
				(int) ( $flush['matched_keys'] ?? 0 ),
				! empty( $flush['selective'] ) ? 'yes' : 'no',
				! empty( $flush['salt_set'] ) ? 'yes' : 'no',
				(string) ( $flush['context'] ?? '' )
			);
			echo $line . "\n";
			if ( self::is_active() ) {
				self::progress( $line );
			}
		}
		foreach ( self::$group_flushes as $i => $flush ) {
			$line = sprintf(
				'CACHE_FLUSH_GROUP[%d] phase=%s execute_ms=%d salt=%s matched_keys=%d',
				$i,
				(string) ( $flush['phase'] ?? '' ),
				(int) ( $flush['execute_ms'] ?? 0 ),
				(string) ( $flush['salt'] ?? '' ),
				(int) ( $flush['matched_keys'] ?? 0 )
			);
			echo $line . "\n";
		}
		foreach ( $report['meta_ops'] ?? array() as $i => $op ) {
			$line = sprintf(
				'META_OP[%d] type=%s object_id=%d key=%s action=%s write_ms=%d child_ms=%d redis_deletes=%d redis_sets=%d redis_delete_us=%d redis_set_us=%d',
				$i,
				(string) ( $op['object_type'] ?? '' ),
				(int) ( $op['object_id'] ?? 0 ),
				(string) ( $op['meta_key'] ?? '' ),
				(string) ( $op['action'] ?? '' ),
				(int) ( $op['write_ms'] ?? 0 ),
				(int) ( $op['child_ms'] ?? 0 ),
				(int) ( $op['redis_deletes'] ?? 0 ),
				(int) ( $op['redis_sets'] ?? 0 ),
				(int) ( $op['redis_delete_us'] ?? 0 ),
				(int) ( $op['redis_set_us'] ?? 0 )
			);
			echo $line . "\n";
			if ( self::is_active() ) {
				self::progress( $line );
			}
		}
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function last_meta_op() {
		if ( array() === self::$meta_ops ) {
			return null;
		}
		return self::$meta_ops[ count( self::$meta_ops ) - 1 ];
	}

	/**
	 * @return array{deletes:int,sets:int,flushes:int}
	 */
	public static function redis_counters() {
		return array(
			'deletes' => self::$redis_deletes,
			'sets'    => self::$redis_sets,
			'flushes' => count( self::$flushes ),
		);
	}

	public static function begin_sql_window() {
		global $wpdb;
		self::$sql_window_open    = true;
		self::$sql_window_queries = array();
		if ( ! defined( 'SAVEQUERIES' ) ) {
			// This WP build only logs when the constant is truthy, not $wpdb->save_queries.
			// Scoped to this eval-file process; not a php-fpm/config change.
			define( 'SAVEQUERIES', true );
		}
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			self::$wpdb_save_queries_prev = ! empty( $wpdb->save_queries );
			$wpdb->save_queries           = true;
			self::$sql_window_query_index = ( isset( $wpdb->queries ) && is_array( $wpdb->queries ) ) ? count( $wpdb->queries ) : 0;
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function end_sql_window() {
		global $wpdb;
		self::$sql_window_open = false;
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			$wpdb->save_queries = self::$wpdb_save_queries_prev;
			if ( array() === self::$sql_window_queries && isset( $wpdb->queries ) && is_array( $wpdb->queries ) ) {
				$slice = array_slice( $wpdb->queries, self::$sql_window_query_index );
				foreach ( $slice as $row ) {
					self::$sql_window_queries[] = array(
						'query'         => (string) ( $row[0] ?? '' ),
						'duration_ms'   => round( ( (float) ( $row[1] ?? 0 ) ) * 1000, 3 ),
						'rows_affected' => null,
						'error'         => '',
						'caller'        => is_string( $row[2] ?? null ) ? (string) $row[2] : '',
					);
				}
			}
		}
		return self::$sql_window_queries;
	}

	/**
	 * @param array<string, mixed> $query_data
	 * @param string               $query
	 * @param float                $query_time
	 * @param string               $query_callstack
	 * @param float                $query_start
	 * @return array<string, mixed>
	 */
	public static function on_log_query_custom_data( $query_data, $query, $query_time, $query_callstack, $query_start ) {
		unset( $query_start );
		if ( ! self::$sql_window_open ) {
			return $query_data;
		}
		global $wpdb;
		$caller = '';
		if ( is_string( $query_callstack ) && '' !== $query_callstack ) {
			$lines  = preg_split( '/[\r\n]+/', $query_callstack ) ?: array();
			$caller = isset( $lines[0] ) ? (string) $lines[0] : '';
		}
		self::$sql_window_queries[] = array(
			'query'         => is_string( $query ) ? $query : wp_json_encode( $query ),
			'duration_ms'   => round( ( (float) $query_time ) * 1000, 3 ),
			'rows_affected' => ( isset( $wpdb ) && is_object( $wpdb ) ) ? (int) $wpdb->rows_affected : null,
			'error'         => ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->last_error ) ) ? (string) $wpdb->last_error : '',
			'caller'        => $caller,
		);
		return $query_data;
	}

	/**
	 * Read-only MySQL session diagnostics. Extra queries; do not call inside a timed SQL window.
	 *
	 * @return array<string, mixed>
	 */
	public static function mysql_session_snapshot() {
		$out = array(
			'connection_id' => null,
			'trx'           => array(),
			'status'        => array(),
			'ps_lock_waits' => null,
			'ps_error'      => '',
		);
		if ( empty( $GLOBALS['wpdb'] ) || ! is_object( $GLOBALS['wpdb'] ) ) {
			return $out;
		}
		global $wpdb;
		$out['connection_id'] = $wpdb->get_var( 'SELECT CONNECTION_ID()' );
		$trx                  = $wpdb->get_results(
			'SELECT trx_id, trx_state, trx_started, trx_rows_locked, trx_rows_modified, trx_wait_started, trx_query FROM information_schema.innodb_trx WHERE trx_mysql_thread_id = CONNECTION_ID()',
			ARRAY_A
		);
		$out['trx'] = is_array( $trx ) ? $trx : array();
		$rows       = $wpdb->get_results(
			"SHOW SESSION STATUS WHERE Variable_name IN (
				'Innodb_row_lock_current_waits',
				'Innodb_row_lock_time',
				'Innodb_row_lock_time_avg',
				'Innodb_row_lock_time_max',
				'Innodb_row_lock_waits',
				'Innodb_rows_read',
				'Innodb_rows_inserted',
				'Innodb_rows_updated',
				'Table_locks_immediate',
				'Table_locks_waited'
			)",
			ARRAY_A
		);
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$name = (string) ( $row['Variable_name'] ?? $row['variable_name'] ?? '' );
				if ( '' !== $name ) {
					$out['status'][ $name ] = $row['Value'] ?? $row['value'] ?? null;
				}
			}
		}
		$ps = $wpdb->get_results(
			"SELECT EVENT_NAME, ROUND(TIMER_WAIT/1000000000, 3) AS wait_ms
			 FROM performance_schema.events_waits_history_long
			 WHERE EVENT_NAME LIKE 'wait/lock/%' OR EVENT_NAME LIKE 'wait/io/table/%'
			 ORDER BY TIMER_WAIT DESC
			 LIMIT 8",
			ARRAY_A
		);
		if ( ! empty( $wpdb->last_error ) ) {
			$out['ps_error']      = (string) $wpdb->last_error;
			$out['ps_lock_waits'] = array();
			$wpdb->last_error     = '';
		} elseif ( is_array( $ps ) ) {
			$out['ps_lock_waits'] = $ps;
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $before
	 * @param array<string, mixed> $after
	 * @return array<string, float>
	 */
	public static function mysql_session_delta( array $before, array $after ) {
		$delta = array();
		$keys  = array(
			'Innodb_row_lock_waits',
			'Innodb_row_lock_time',
			'Innodb_row_lock_current_waits',
			'Innodb_rows_read',
			'Innodb_rows_inserted',
			'Innodb_rows_updated',
			'Table_locks_waited',
		);
		foreach ( $keys as $key ) {
			$b             = isset( $before['status'][ $key ] ) ? (float) $before['status'][ $key ] : 0.0;
			$a             = isset( $after['status'][ $key ] ) ? (float) $after['status'][ $key ] : 0.0;
			$delta[ $key ] = $a - $b;
		}
		return $delta;
	}

	/**
	 * @return array<int, array{priority:int,callback:string}>
	 */
	public static function hook_callback_detail( $hook ) {
		$out = array();
		if ( empty( $GLOBALS['wp_filter'][ $hook ] ) || ! is_object( $GLOBALS['wp_filter'][ $hook ] ) ) {
			return $out;
		}
		$callbacks = $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array();
		if ( ! is_array( $callbacks ) ) {
			return $out;
		}
		foreach ( $callbacks as $priority => $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			foreach ( $group as $entry ) {
				$fn = $entry['function'] ?? null;
				if ( is_string( $fn ) ) {
					$label = $fn;
				} elseif ( is_array( $fn ) ) {
					$obj    = $fn[0] ?? '';
					$method = $fn[1] ?? '';
					$obj    = is_object( $obj ) ? get_class( $obj ) : (string) $obj;
					$label  = $obj . '::' . (string) $method;
				} elseif ( $fn instanceof Closure ) {
					$label = 'Closure';
				} else {
					$label = gettype( $fn );
				}
				$out[] = array(
					'priority' => (int) $priority,
					'callback' => $label,
				);
			}
		}
		return $out;
	}

	public static function on_redis_flush( $results, $deprecated, $selective, $salt, $execute_time ) {
		unset( $deprecated );
		$event = array(
			'phase'        => self::$phase,
			'timestamp'    => gmdate( 'c' ),
			'execute_ms'   => (int) round( ( (float) $execute_time ) * 1000 ),
			'execute_us'   => (int) round( ( (float) $execute_time ) * 1000000 ),
			'selective'    => (bool) $selective,
			'salt_set'     => is_string( $salt ) && '' !== $salt,
			'prefix'       => defined( 'WP_REDIS_PREFIX' ) ? (string) WP_REDIS_PREFIX : '',
			'matched_keys' => self::parse_matched_keys( $results ),
			'context'      => self::request_context_label(),
			'raw_results'  => $results,
		);
		self::$flushes[] = $event;
		if ( is_array( self::$current_episode ) && 'episode_create' === self::$phase ) {
			self::$current_episode['flush_ms']    = (int) ( self::$current_episode['flush_ms'] ?? 0 ) + (int) $event['execute_ms'];
			self::$current_episode['flush_us']    = (int) ( self::$current_episode['flush_us'] ?? 0 ) + (int) $event['execute_us'];
			self::$current_episode['flush_keys']  = (int) ( self::$current_episode['flush_keys'] ?? 0 ) + (int) $event['matched_keys'];
			self::$current_episode['flush_count'] = (int) ( self::$current_episode['flush_count'] ?? 0 ) + 1;
		}
		if ( self::is_active() ) {
			self::end( 'redis_flush', microtime( true ) - (float) $execute_time, self::$phase, (int) $event['matched_keys'] );
			self::progress(
				sprintf(
					'REDIS_FLUSH phase=%s execute_ms=%d execute_us=%d matched_keys=%d selective=%s prefix=%s context=%s',
					$event['phase'],
					$event['execute_ms'],
					$event['execute_us'],
					$event['matched_keys'],
					$event['selective'] ? 'yes' : 'no',
					$event['prefix'],
					$event['context']
				)
			);
		}
	}

	public static function on_redis_flush_group( $results, $salt, $execute_time ) {
		$event = array(
			'phase'        => self::$phase,
			'timestamp'    => gmdate( 'c' ),
			'execute_ms'   => (int) round( ( (float) $execute_time ) * 1000 ),
			'execute_us'   => (int) round( ( (float) $execute_time ) * 1000000 ),
			'salt'         => is_string( $salt ) ? $salt : '',
			'matched_keys' => self::parse_matched_keys( $results ),
			'context'      => self::request_context_label(),
		);
		self::$group_flushes[] = $event;
		if ( self::is_active() ) {
			self::end( 'redis_flush_group', microtime( true ) - (float) $execute_time, (string) $event['salt'], (int) $event['matched_keys'] );
			self::progress(
				sprintf(
					'REDIS_FLUSH_GROUP phase=%s execute_ms=%d salt=%s matched_keys=%d',
					$event['phase'],
					$event['execute_ms'],
					$event['salt'],
					$event['matched_keys']
				)
			);
		}
	}

	public static function on_redis_delete( $key, $group, $execute_time ) {
		unset( $key, $group );
		++self::$redis_deletes;
		if ( self::$child_window_open ) {
			++self::$child_window_redis_deletes;
			self::$child_window_redis_delete_s += (float) $execute_time;
		}
	}

	public static function on_redis_set( $key, $value, $group, $expiration, $execute_time ) {
		unset( $key, $value, $group, $expiration );
		++self::$redis_sets;
		if ( self::$child_window_open ) {
			++self::$child_window_redis_sets;
			self::$child_window_redis_set_s += (float) $execute_time;
		}
	}

	public static function on_before_episode_insert() {
		self::$phase = 'episode_create';
		$t0          = microtime( true );
		self::open_episode_window( $t0 );
		self::$current_episode['before_insert'] = $t0;
		self::$current_episode['queries_before'] = self::query_count();
		if ( self::is_active() ) {
			self::progress( 'EPISODE_INSERT_ENTER' );
		}
	}

	public static function on_after_episode_insert() {
		if ( ! is_array( self::$current_episode ) ) {
			return;
		}
		$t1 = microtime( true );
		self::$current_episode['after_insert']        = $t1;
		self::$current_episode['queries_after']       = self::query_count();
		$span                                         = $t1 - (float) ( self::$current_episode['before_insert'] ?? $t1 );
		self::$current_episode['create_hook_span_ms'] = (int) round( $span * 1000 );
		$flush_s = ( (int) ( self::$current_episode['flush_us'] ?? 0 ) ) / 1000000;
		self::$current_episode['mysql_insert_ms'] = (int) max( 0, round( ( $span - $flush_s ) * 1000 ) );
		self::$phase = 'after_episode_insert';
		if ( self::is_active() ) {
			self::end( 'episode_mysql_insert', $t1 - ( $span - $flush_s ), 'before_after_minus_flush', 1 );
			self::progress(
				sprintf(
					'EPISODE_INSERT_EXIT span_ms=%d mysql_insert_ms=%d flush_ms=%d flush_keys=%d dq=%d',
					(int) self::$current_episode['create_hook_span_ms'],
					(int) self::$current_episode['mysql_insert_ms'],
					(int) ( self::$current_episode['flush_ms'] ?? 0 ),
					(int) ( self::$current_episode['flush_keys'] ?? 0 ),
					(int) self::$current_episode['queries_after'] - (int) self::$current_episode['queries_before']
				)
			);
		}
	}

	public static function on_meta_write_start( $object_type, $action, $object_id, $meta_key ) {
		$pending_key = $object_type . '|' . $action . '|' . (int) $object_id . '|' . (string) $meta_key;
		self::$pending_meta_writes[ $pending_key ] = microtime( true );
	}

	public static function on_meta_write_after_early( $object_type, $action, $object_id, $meta_key ) {
		$pending_key = $object_type . '|' . $action . '|' . (int) $object_id . '|' . (string) $meta_key;
		$started     = self::$pending_meta_writes[ $pending_key ] ?? microtime( true );
		unset( self::$pending_meta_writes[ $pending_key ] );
		$write_ms    = (int) round( ( microtime( true ) - $started ) * 1000 );
		self::$child_window_open           = true;
		self::$child_window_started        = microtime( true );
		self::$child_window_redis_deletes  = 0;
		self::$child_window_redis_sets     = 0;
		self::$child_window_redis_delete_s = 0.0;
		self::$child_window_redis_set_s    = 0.0;
		self::$meta_ops[] = array(
			'object_type' => $object_type,
			'action'      => $action,
			'object_id'   => (int) $object_id,
			'meta_key'    => (string) $meta_key,
			'write_ms'    => $write_ms,
			'child_ms'    => 0,
			'redis_deletes' => 0,
			'redis_sets'    => 0,
			'redis_delete_us' => 0,
			'redis_set_us'    => 0,
		);
		if ( self::is_active() ) {
			self::end( 'episode_add_metadata', $started, $object_type . ':' . $meta_key, 1 );
		}
	}

	public static function on_meta_write_after_late( $object_type ) {
		unset( $object_type );
		if ( ! self::$child_window_open || array() === self::$meta_ops ) {
			self::$child_window_open = false;
			return;
		}
		$idx = count( self::$meta_ops ) - 1;
		self::$meta_ops[ $idx ]['child_ms']        = (int) round( ( microtime( true ) - self::$child_window_started ) * 1000 );
		self::$meta_ops[ $idx ]['redis_deletes']   = self::$child_window_redis_deletes;
		self::$meta_ops[ $idx ]['redis_sets']      = self::$child_window_redis_sets;
		self::$meta_ops[ $idx ]['redis_delete_us'] = (int) round( self::$child_window_redis_delete_s * 1000000 );
		self::$meta_ops[ $idx ]['redis_set_us']    = (int) round( self::$child_window_redis_set_s * 1000000 );
		self::$child_window_open = false;
		if ( self::is_active() ) {
			self::end( 'episode_child_invalidate', self::$child_window_started, (string) ( self::$meta_ops[ $idx ]['meta_key'] ?? '' ), 1 );
			self::progress(
				sprintf(
					'META_CHILD type=%s key=%s write_ms=%d child_ms=%d redis_del=%d redis_set=%d',
					(string) ( self::$meta_ops[ $idx ]['object_type'] ?? '' ),
					(string) ( self::$meta_ops[ $idx ]['meta_key'] ?? '' ),
					(int) ( self::$meta_ops[ $idx ]['write_ms'] ?? 0 ),
					(int) ( self::$meta_ops[ $idx ]['child_ms'] ?? 0 ),
					(int) ( self::$meta_ops[ $idx ]['redis_deletes'] ?? 0 ),
					(int) ( self::$meta_ops[ $idx ]['redis_sets'] ?? 0 )
				)
			);
		}
	}

	private static function install_hooks() {
		if ( self::$hooks_installed ) {
			return;
		}
		self::$hooks_installed = true;
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'pre_http_request', array( __CLASS__, 'http_begin' ), 1, 3 );
			add_filter( 'http_response', array( __CLASS__, 'http_end' ), 999, 3 );
		}
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'redis_object_cache_flush', array( __CLASS__, 'on_redis_flush' ), 10, 5 );
		add_action( 'redis_object_cache_flush_group', array( __CLASS__, 'on_redis_flush_group' ), 10, 3 );
		add_action( 'redis_object_cache_delete', array( __CLASS__, 'on_redis_delete' ), 10, 3 );
		add_action( 'redis_object_cache_set', array( __CLASS__, 'on_redis_set' ), 10, 5 );
		add_filter( 'log_query_custom_data', array( __CLASS__, 'on_log_query_custom_data' ), 10, 5 );
		add_action( 'streamit_before_episode_insert', array( __CLASS__, 'on_before_episode_insert' ) );
		add_action( 'streamit_after_episode_insert', array( __CLASS__, 'on_after_episode_insert' ) );
		foreach ( array( 'episode', 'movie', 'tvshow', 'video', 'person' ) as $object_type ) {
			$meta_type = 'streamit_' . $object_type;
			add_action(
				"add_{$meta_type}_meta",
				static function ( $object_id, $meta_key ) use ( $object_type ) {
					Movies_WP_Series_Import_Profiler::on_meta_write_start( $object_type, 'add', $object_id, $meta_key );
				},
				1,
				2
			);
			add_action(
				"update_{$meta_type}_meta",
				static function ( $meta_id, $object_id, $meta_key ) use ( $object_type ) {
					unset( $meta_id );
					Movies_WP_Series_Import_Profiler::on_meta_write_start( $object_type, 'update', $object_id, $meta_key );
				},
				1,
				3
			);
			add_action(
				"added_{$meta_type}_meta",
				static function ( $meta_id, $object_id, $meta_key ) use ( $object_type ) {
					unset( $meta_id );
					Movies_WP_Series_Import_Profiler::on_meta_write_after_early( $object_type, 'add', $object_id, $meta_key );
				},
				1,
				3
			);
			add_action(
				"updated_{$meta_type}_meta",
				static function ( $meta_id, $object_id, $meta_key ) use ( $object_type ) {
					unset( $meta_id );
					Movies_WP_Series_Import_Profiler::on_meta_write_after_early( $object_type, 'update', $object_id, $meta_key );
				},
				1,
				3
			);
			add_action(
				"added_{$meta_type}_meta",
				static function () use ( $object_type ) {
					Movies_WP_Series_Import_Profiler::on_meta_write_after_late( $object_type );
				},
				11,
				0
			);
			add_action(
				"updated_{$meta_type}_meta",
				static function () use ( $object_type ) {
					Movies_WP_Series_Import_Profiler::on_meta_write_after_late( $object_type );
				},
				11,
				0
			);
		}
	}

	private static function open_episode_window( $t0 ) {
		if ( ! is_array( self::$current_episode ) ) {
			self::$current_episode = array(
				'entered'             => (float) $t0,
				'add_episode_ms'      => 0,
				'mysql_insert_ms'     => -1,
				'create_hook_span_ms' => -1,
				'flush_ms'            => 0,
				'flush_us'            => 0,
				'flush_keys'          => 0,
				'flush_count'         => 0,
				'add_metadata_ms'     => 0,
				'child_invalidate_ms' => 0,
				'meta_ops_count'      => 0,
				'meta_ops'            => array(),
				'child_redis_deletes' => 0,
				'child_redis_sets'    => 0,
			);
			self::$meta_ops = array();
			return;
		}
		if ( ! isset( self::$current_episode['entered'] ) ) {
			self::$current_episode['entered'] = (float) $t0;
		}
	}

	private static function current_episode_int( $key ) {
		return is_array( self::$current_episode ) ? (int) ( self::$current_episode[ $key ] ?? 0 ) : 0;
	}

	private static function sum_meta_field( $field ) {
		$total = 0;
		foreach ( self::$meta_ops as $op ) {
			if ( 'episode' !== ( $op['object_type'] ?? '' ) ) {
				continue;
			}
			$total += (int) ( $op[ $field ] ?? 0 );
		}
		return $total;
	}

	private static function parse_matched_keys( $results ) {
		$matched = 0;
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				if ( is_numeric( $row ) ) {
					$matched += (int) $row;
				} elseif ( is_array( $row ) && isset( $row[0] ) && is_numeric( $row[0] ) ) {
					$matched += (int) $row[0];
				}
			}
		} elseif ( is_numeric( $results ) ) {
			$matched = (int) $results;
		}
		return $matched;
	}

	private static function request_context_label() {
		$parts = array( PHP_SAPI );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$parts[] = 'wp-cli';
		}
		$parts[] = ( function_exists( 'is_admin' ) && is_admin() ) ? 'admin' : 'not_admin';
		return implode( '/', $parts );
	}

	/**
	 * @return array{pending:int|null,in-progress:int|null,by_status:array<string,int>}
	 */
	private static function action_scheduler_counts() {
		$empty = array(
			'pending'     => null,
			'in-progress' => null,
			'by_status'   => array(),
		);
		if ( empty( $GLOBALS['wpdb'] ) || ! is_object( $GLOBALS['wpdb'] ) ) {
			return $empty;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'actionscheduler_actions';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table !== $exists ) {
			return $empty;
		}
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status", ARRAY_A );
		$by   = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$by[ (string) ( $row['status'] ?? '' ) ] = (int) ( $row['c'] ?? 0 );
			}
		}
		return array(
			'pending'     => (int) ( $by['pending'] ?? 0 ),
			'in-progress' => (int) ( $by['in-progress'] ?? 0 ),
			'by_status'   => $by,
		);
	}

	/**
	 * @return array<string, int>
	 */
	private static function hook_callback_counts( $hook ) {
		$counts = array();
		if ( empty( $GLOBALS['wp_filter'][ $hook ] ) || ! is_object( $GLOBALS['wp_filter'][ $hook ] ) ) {
			return $counts;
		}
		$callbacks = $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array();
		if ( ! is_array( $callbacks ) ) {
			return $counts;
		}
		foreach ( $callbacks as $priority => $group ) {
			$counts[ 'p' . (string) $priority ] = is_array( $group ) ? count( $group ) : 0;
		}
		return $counts;
	}

	/**
	 * Read-only Redis key counts. Prefix count uses SCAN (no DEL).
	 *
	 * @param object $oc
	 * @return array{dbsize:?int,prefix_keys:?int,prefix_scan_ms:?int}
	 */
	private static function redis_key_counts( $oc ) {
		$out = array(
			'dbsize'         => null,
			'prefix_keys'    => null,
			'prefix_scan_ms' => null,
		);
		try {
			$redis = null;
			$ref   = new ReflectionObject( $oc );
			if ( $ref->hasProperty( 'redis' ) ) {
				$prop = $ref->getProperty( 'redis' );
				$prop->setAccessible( true );
				$redis = $prop->getValue( $oc );
			}
			if ( ! is_object( $redis ) ) {
				return $out;
			}
			if ( method_exists( $redis, 'dbsize' ) ) {
				$out['dbsize'] = (int) $redis->dbsize();
			}
			$prefix = defined( 'WP_REDIS_PREFIX' ) ? (string) WP_REDIS_PREFIX : '';
			if ( '' === $prefix || ! method_exists( $redis, 'eval' ) ) {
				return $out;
			}
			$script = "local cur='0' local i=0 local tmp local started=redis.call('TIME')[1] repeat tmp=redis.call('SCAN',cur,'MATCH',ARGV[1],'COUNT',500) cur=tmp[1] i=i+#tmp[2] until cur=='0' return i";
			$t0     = microtime( true );
			$count  = $redis->eval( $script, array( $prefix . '*' ), 0 );
			$out['prefix_scan_ms'] = (int) round( ( microtime( true ) - $t0 ) * 1000 );
			$out['prefix_keys']    = is_numeric( $count ) ? (int) $count : null;
		} catch ( Exception $e ) {
			unset( $e );
		}
		return $out;
	}

	private static function write_summary() {
		$total = microtime( true ) - self::$started_at;
		$rows  = self::$buckets;
		uasort(
			$rows,
			static function ( $left, $right ) {
				return $right['total'] <=> $left['total'];
			}
		);
		self::write_line( '--- SUMMARY ---' );
		self::write_line( sprintf( 'total=%.3fs (%dms) last_phase=%s', $total, (int) round( $total * 1000 ), self::$last_phase ) );
		self::write_line(
			sprintf(
				'db_queries=%d db_time=%s http_count=%d http_total=%.3fs (%dms)',
				max( 0, self::query_count() - self::$db_queries_start ),
				self::query_time_label(),
				self::$http_count,
				self::$http_total,
				(int) round( self::$http_total * 1000 )
			)
		);
		self::write_line(
			sprintf(
				'counted sources=%d subtitles=%d episodes=%d stills=%d',
				self::$source_index,
				self::$subtitle_index,
				self::$episode_index,
				self::$still_index
			)
		);
		foreach ( self::$notes as $key => $value ) {
			self::write_line( sprintf( 'note %s=%s', $key, $value ) );
		}
		$rank = 0;
		foreach ( $rows as $name => $row ) {
			++$rank;
			$avg = $row['count'] > 0 ? $row['total'] / $row['count'] : 0.0;
			$pct = $total > 0 ? ( 100.0 * $row['total'] / $total ) : 0.0;
			self::write_line(
				sprintf(
					'#%d %s count=%d objects=%d total=%.3fs (%dms) avg=%.3fs (%dms) slowest=%.3fs (%.1f%%) %s',
					$rank,
					$name,
					$row['count'],
					$row['objects'],
					$row['total'],
					(int) round( $row['total'] * 1000 ),
					$avg,
					(int) round( $avg * 1000 ),
					$row['slowest'],
					$pct,
					$row['slowest_label']
				)
			);
		}
		self::write_phase_table( $total );
		self::write_line( '--- END ---' );
	}

	private static function write_phase_table( $total ) {
		self::write_line( '| Phase            | Count | Total time | Average | Notes |' );
		self::write_line( '| ---------------- | ----: | ---------: | ------: | ----- |' );
		$specs = array(
			'TMDb'             => array(
				'buckets' => array( 'http.tmdb_api' ),
				'notes'   => self::join_notes(
					array(
						'tmdb_preview' => 'wrapper',
						'plan_rebuild.tmdb',
					)
				),
			),
			'Media scan'       => array(
				'buckets' => array( 'media_scan' ),
				'notes'   => self::note_value( 'plan_rebuild.media_scan' ) . ' ' . self::note_value( 'rematch.directory_scan_again' ),
			),
			'Series creation'  => array(
				'buckets' => array( 'series_row', 'series_meta' ),
				'notes'   => 'Streamit row + metadata; people/images excluded',
			),
			'Images'           => array(
				'buckets' => array( 'series_images', 'episode_still' ),
				'notes'   => 'series poster/backdrop + episode stills; season poster is under Season',
			),
			'People'           => array(
				'buckets' => array( 'people', 'person_resolve' ),
				'notes'   => 'prefer people wrapper if present',
			),
			'Season'           => array(
				'buckets' => array( 'season_create' ),
				'notes'   => 'includes season poster when set',
			),
			'Episode creation' => array(
				'buckets' => array( 'episode_create' ),
				'notes'   => 'insert=' . self::bucket_label( 'episode_insert' ) . ' meta=' . self::bucket_label( 'episode_meta' ) . ' still=' . self::bucket_label( 'episode_still' ),
			),
			'Rematch'          => array(
				'buckets' => array( 'media_rematch' ),
				'notes'   => 'scan_again=' . self::note_value( 'rematch.directory_scan_again' ) . ' tmdb_again=' . self::note_value( 'rematch.tmdb_query_again' ),
			),
			'Sources'          => array(
				'buckets' => array( 'episode_sources_write' ),
				'notes'   => 'paths=' . (string) self::$source_index,
			),
			'Subtitles'        => array(
				'buckets' => array( 'episode_subtitles_write' ),
				'notes'   => 'paths=' . (string) self::$subtitle_index,
			),
			'Total'            => array(
				'buckets' => array(),
				'notes'   => 'wall clock',
			),
		);

		foreach ( $specs as $phase => $spec ) {
			if ( 'People' === $phase && isset( self::$buckets['people'] ) ) {
				$spec['buckets'] = array( 'people' );
			}
			if ( 'Total' === $phase ) {
				self::write_line(
					sprintf(
						'| %-16s | %5d | %10s | %7s | %s |',
						$phase,
						1,
						self::fmt_duration( $total ),
						self::fmt_duration( $total ),
						$spec['notes']
					)
				);
				continue;
			}
			$agg = self::sum_buckets( $spec['buckets'] );
			self::write_line(
				sprintf(
					'| %-16s | %5d | %10s | %7s | %s |',
					$phase,
					$agg['count'],
					self::fmt_duration( $agg['total'] ),
					self::fmt_duration( $agg['avg'] ),
					trim( (string) $spec['notes'] )
				)
			);
		}
	}

	/**
	 * @param list<string> $names
	 * @return array{count:int,total:float,avg:float}
	 */
	private static function sum_buckets( array $names ) {
		$count = 0;
		$total = 0.0;
		foreach ( $names as $name ) {
			if ( ! isset( self::$buckets[ $name ] ) ) {
				continue;
			}
			$count += (int) self::$buckets[ $name ]['count'];
			$total += (float) self::$buckets[ $name ]['total'];
		}
		return array(
			'count' => $count,
			'total' => $total,
			'avg'   => $count > 0 ? $total / $count : 0.0,
		);
	}

	private static function bucket_label( $name ) {
		if ( ! isset( self::$buckets[ $name ] ) ) {
			return 'n/a';
		}
		$row = self::$buckets[ $name ];
		return sprintf( '%d/%s', $row['count'], self::fmt_duration( $row['total'] ) );
	}

	private static function note_value( $key ) {
		return isset( self::$notes[ $key ] ) ? $key . '=' . self::$notes[ $key ] : $key . '=?';
	}

	/**
	 * @param array<int|string, string> $items
	 */
	private static function join_notes( array $items ) {
		$parts = array();
		foreach ( $items as $key => $value ) {
			if ( is_int( $key ) ) {
				$parts[] = self::note_value( $value );
				continue;
			}
			if ( isset( self::$buckets[ $key ] ) ) {
				$parts[] = $key . '=' . self::fmt_duration( self::$buckets[ $key ]['total'] );
			} else {
				$parts[] = (string) $value;
			}
		}
		return implode( '; ', $parts );
	}

	private static function fmt_duration( $seconds ) {
		return sprintf( '%.3fs', (float) $seconds );
	}

	private static function write_line( $line ) {
		$stamp = gmdate( 'Y-m-d H:i:s' );
		$text  = $stamp . 'Z ' . $line . "\n";
		if ( '' !== self::$log_file ) {
			$handle = @fopen( self::$log_file, 'ab' );
			if ( is_resource( $handle ) ) {
				flock( $handle, LOCK_EX );
				fwrite( $handle, $text );
				fflush( $handle );
				flock( $handle, LOCK_UN );
				fclose( $handle );
			}
		}
		if ( function_exists( 'error_log' ) ) {
			error_log( 'series-import-profile ' . $line );
		}
	}

	private static function resolve_log_file() {
		if ( defined( 'WP_CONTENT_DIR' ) && is_string( WP_CONTENT_DIR ) && WP_CONTENT_DIR !== '' ) {
			$dir = WP_CONTENT_DIR . '/uploads';
			if ( is_dir( $dir ) || @mkdir( $dir, 0755, true ) ) {
				return $dir . '/movies-wp-series-import-profile.log';
			}
		}
		return rtrim( sys_get_temp_dir(), '/\\' ) . '/movies-wp-series-import-profile.log';
	}

	private static function query_count() {
		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && isset( $GLOBALS['wpdb']->num_queries ) ) {
			return (int) $GLOBALS['wpdb']->num_queries;
		}
		return 0;
	}

	private static function query_time_label() {
		if ( empty( $GLOBALS['wpdb'] ) || ! is_object( $GLOBALS['wpdb'] ) || empty( $GLOBALS['wpdb']->queries ) || ! is_array( $GLOBALS['wpdb']->queries ) ) {
			return 'n/a (SAVEQUERIES off)';
		}
		$total = 0.0;
		foreach ( $GLOBALS['wpdb']->queries as $query ) {
			if ( is_array( $query ) && isset( $query[1] ) ) {
				$total += (float) $query[1];
			}
		}
		return sprintf( '%.3fs', $total );
	}

	private static function http_bucket( $url ) {
		if ( defined( 'STREAMIT_MINIO_ENDPOINT' ) && STREAMIT_MINIO_ENDPOINT ) {
			$minio_host = parse_url( (string) STREAMIT_MINIO_ENDPOINT, PHP_URL_HOST );
			if ( is_string( $minio_host ) && '' !== $minio_host && str_contains( $url, $minio_host ) ) {
				return 'http.minio';
			}
		}
		if ( str_contains( $url, '/scan/series' ) || str_contains( $url, '/scan/movie' ) ) {
			return 'http.media_scan';
		}
		if ( str_contains( $url, '/3/tv/' ) || str_contains( $url, 'tmdb' ) ) {
			return str_contains( $url, '/t/p/' ) || str_contains( $url, 'image.tmdb' )
				? 'http.tmdb_image'
				: 'http.tmdb_api';
		}
		if ( preg_match( '/\.(jpg|jpeg|png|webp)(\?|$)/i', $url ) ) {
			return 'http.image_download';
		}
		return 'http.other';
	}

	private static function short_url( $url ) {
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return (string) $url;
		}
		$host = (string) ( $parts['host'] ?? '' );
		$path = (string) ( $parts['path'] ?? '' );
		return $host . $path;
	}
}

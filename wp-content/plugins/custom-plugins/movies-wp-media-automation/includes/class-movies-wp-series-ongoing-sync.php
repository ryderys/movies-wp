<?php
/**
 * Poll ongoing series for cheap media changes and enqueue Series import jobs.
 *
 * Detection uses GET /list/series (no ffprobe). Apply uses the existing
 * Orchestrator → snapshot → Action Scheduler import path so new episodes are
 * created and rematch live-scans sources/trailers.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Ongoing_Sync {

	const POLL_HOOK  = 'movies_wp_series_ongoing_poll';
	const GROUP      = 'movies-wp-series-ongoing';
	const INTERVAL   = 900; // 15 minutes.
	const MAX_ENQUEUE_PER_POLL = 2;

	/** @var bool */
	private static $running = false;

	public static function init() {
		add_action( self::POLL_HOOK, array( __CLASS__, 'handle_poll' ), 10, 0 );
		// Wrap so WP's `init` hook does not pass a non-array first argument.
		add_action(
			'init',
			static function () {
				self::ensure_scheduled( array() );
			},
			20
		);
	}

	public static function handle_poll() {
		try {
			self::poll( array() );
		} catch ( \Throwable $e ) {
			unset( $e );
		}
	}

	/**
	 * Keep one recurring poll action scheduled when Action Scheduler exists.
	 *
	 * @param array<string, mixed> $options
	 * @return bool
	 */
	public static function ensure_scheduled( $options = array() ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		try {
			if ( isset( $options['schedule_recurring'] ) && is_callable( $options['schedule_recurring'] ) ) {
				return (bool) call_user_func( $options['schedule_recurring'] );
			}
			if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
				return false;
			}
			$next = as_next_scheduled_action( self::POLL_HOOK, array(), self::GROUP );
			if ( false !== $next && null !== $next ) {
				return false;
			}
			$id = as_schedule_recurring_action( time() + 60, self::INTERVAL, self::POLL_HOOK, array(), self::GROUP, true );
			return false !== $id && null !== $id;
		} catch ( \Throwable $e ) {
			unset( $e );
			return false;
		}
	}

	/**
	 * Compare cheap list fingerprints and enqueue sync jobs for changed shows.
	 *
	 * @param array<string, mixed> $options
	 * @return array{checked:int,enqueued:int,skipped:int,errors:list<array{tvshow_id:int,code:string,message:string}>}
	 */
	public static function poll( array $options = array() ) {
		if ( self::$running ) {
			return array(
				'checked'  => 0,
				'enqueued' => 0,
				'skipped'  => 0,
				'errors'   => array(),
				'busy'     => true,
			);
		}
		self::$running = true;
		try {
			return self::process_poll( $options );
		} finally {
			self::$running = false;
		}
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array{checked:int,enqueued:int,skipped:int,errors:list<array{tvshow_id:int,code:string,message:string}>}
	 */
	private static function process_poll( array $options ) {
		$ids      = Movies_WP_Series_Ongoing::find_ongoing_ids( $options );
		$checked  = 0;
		$enqueued = 0;
		$skipped  = 0;
		$errors   = array();
		$max      = isset( $options['max_enqueue'] ) ? max( 1, (int) $options['max_enqueue'] ) : self::MAX_ENQUEUE_PER_POLL;

		foreach ( $ids as $tvshow_id ) {
			++$checked;
			if ( $enqueued >= $max ) {
				++$skipped;
				continue;
			}

			$result = self::maybe_enqueue_tvshow( (int) $tvshow_id, $options );
			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'tvshow_id' => (int) $tvshow_id,
					'code'      => $result->get_error_code(),
					'message'   => $result->get_error_message(),
				);
				continue;
			}
			if ( ! empty( $result['enqueued'] ) ) {
				++$enqueued;
			} else {
				++$skipped;
			}
		}

		return array(
			'checked'  => $checked,
			'enqueued' => $enqueued,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * @param int                  $tvshow_id
	 * @param array<string, mixed> $options
	 * @return array{enqueued:bool,reason?:string,job?:array}|WP_Error
	 */
	public static function maybe_enqueue_tvshow( $tvshow_id, array $options = array() ) {
		$tvshow_id = (int) $tvshow_id;
		if ( $tvshow_id <= 0 || ! Movies_WP_Series_Ongoing::is_ongoing( $tvshow_id, $options ) ) {
			return array(
				'enqueued' => false,
				'reason'   => 'not_ongoing',
			);
		}

		$input = Movies_WP_Series_Ongoing::sync_input_for_tvshow( $tvshow_id, $options );
		if ( is_wp_error( $input ) ) {
			return $input;
		}

		$dir = (string) $input['series_directory'];
		if ( self::directory_has_active_job( $dir, $options ) ) {
			return array(
				'enqueued' => false,
				'reason'   => 'job_active',
			);
		}

		$list = isset( $options['list_series'] ) && is_callable( $options['list_series'] )
			? call_user_func( $options['list_series'], $dir )
			: Movies_WP_Series_Media_Api_Client::list_series_directory( $dir );
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		if ( ! is_array( $list ) || empty( $list['ok'] ) ) {
			return new WP_Error( 'series_ongoing_list_invalid', __( 'Series list returned invalid data.', 'movies-wp' ) );
		}

		$fingerprint = Movies_WP_Series_Ongoing::fingerprint_from_list( $list );
		$stored      = Movies_WP_Series_Ongoing::get_fingerprint( $tvshow_id, $options );
		if ( '' !== $stored && hash_equals( $stored, $fingerprint ) ) {
			return array(
				'enqueued' => false,
				'reason'   => 'unchanged',
			);
		}

		return self::enqueue_import( $input, $options );
	}

	/**
	 * Build a Series Automation preview and enqueue the existing import job.
	 *
	 * @param array{tmdb_id:int,title:string,summary:string,series_directory:string} $input
	 * @param array<string, mixed>                                                   $options
	 * @return array{enqueued:bool,reason?:string,job?:array}|WP_Error
	 */
	public static function enqueue_import( array $input, array $options = array() ) {
		$preview = isset( $options['orchestrator_preview'] ) && is_callable( $options['orchestrator_preview'] )
			? call_user_func( $options['orchestrator_preview'], $input )
			: Movies_WP_Series_Orchestrator::build_preview( $input, $options );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		if ( ! is_array( $preview ) || true !== ( $preview['ready_to_import'] ?? null ) ) {
			$code = 'series_ongoing_not_ready';
			$msg  = __( 'Ongoing series sync preview is not ready to import.', 'movies-wp' );
			if ( is_array( $preview ) && ! empty( $preview['validation']['errors'][0]['message'] ) ) {
				$msg = (string) $preview['validation']['errors'][0]['message'];
			}
			return new WP_Error( $code, $msg, array( 'preview' => $preview ) );
		}

		// Mark so finalize can set the ongoing flag / fingerprint owner.
		if ( ! isset( $preview['input'] ) || ! is_array( $preview['input'] ) ) {
			$preview['input'] = $input;
		}
		$preview['input']['series_ongoing'] = true;
		$preview['input']['ongoing_sync']   = true;

		$context = array(
			'user_id' => isset( $options['user_id'] ) ? (int) $options['user_id'] : self::system_user_id( $options ),
			'blog_id' => isset( $options['blog_id'] ) ? (int) $options['blog_id'] : ( function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1 ),
		);

		$create = isset( $options['snapshot_create'] ) && is_callable( $options['snapshot_create'] )
			? $options['snapshot_create']
			: array( 'Movies_WP_Series_Import_Snapshot_Store', 'create' );
		$snapshot = call_user_func( $create, $preview, $context );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		$token = is_array( $snapshot ) ? (string) ( $snapshot['token'] ?? '' ) : '';
		if ( '' === $token ) {
			return new WP_Error( 'series_ongoing_snapshot_missing', __( 'Could not create a series sync snapshot.', 'movies-wp' ) );
		}

		$enqueue = isset( $options['enqueue_job'] ) && is_callable( $options['enqueue_job'] )
			? $options['enqueue_job']
			: array( 'Movies_WP_Series_Import_Job_Runner', 'enqueue_from_snapshot' );
		$job     = call_user_func( $enqueue, $token, $context, $options );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		if ( ! is_array( $job ) || empty( $job['token'] ) ) {
			return new WP_Error( 'series_ongoing_enqueue_failed', __( 'Could not enqueue the series sync job.', 'movies-wp' ) );
		}

		return array(
			'enqueued' => true,
			'job'      => $job,
		);
	}

	/**
	 * After a successful import, refresh the cheap fingerprint and optional ongoing flag.
	 *
	 * @param int                  $tvshow_id
	 * @param array<string, mixed> $payload Snapshot payload.
	 * @param array<string, mixed> $options
	 * @return void
	 */
	public static function after_successful_import( $tvshow_id, array $payload, array $options = array() ) {
		$tvshow_id = (int) $tvshow_id;
		if ( $tvshow_id <= 0 ) {
			return;
		}

		$input = is_array( $payload['input'] ?? null ) ? $payload['input'] : array();
		if ( ! empty( $input['series_ongoing'] ) || ! empty( $input['ongoing_sync'] ) ) {
			Movies_WP_Series_Ongoing::set_ongoing( $tvshow_id, true, $options );
		}

		if ( ! Movies_WP_Series_Ongoing::is_ongoing( $tvshow_id, $options ) ) {
			return;
		}

		$dir = isset( $input['series_directory'] ) ? (string) $input['series_directory'] : '';
		$dir = Movies_WP_Series_Media_Api_Client::normalize_directory( $dir );
		if ( is_wp_error( $dir ) ) {
			$stored = Movies_WP_Series_Ongoing::sync_input_for_tvshow( $tvshow_id, $options );
			$dir    = is_wp_error( $stored ) ? '' : (string) ( $stored['series_directory'] ?? '' );
		}
		if ( '' === $dir ) {
			return;
		}

		$list = isset( $options['list_series'] ) && is_callable( $options['list_series'] )
			? call_user_func( $options['list_series'], $dir )
			: Movies_WP_Series_Media_Api_Client::list_series_directory( $dir );
		if ( is_wp_error( $list ) || ! is_array( $list ) || empty( $list['ok'] ) ) {
			return;
		}

		Movies_WP_Series_Ongoing::set_fingerprint(
			$tvshow_id,
			Movies_WP_Series_Ongoing::fingerprint_from_list( $list ),
			$options
		);
	}

	/**
	 * @param string               $directory
	 * @param array<string, mixed> $options
	 * @return bool
	 */
	public static function directory_has_active_job( $directory, array $options = array() ) {
		$directory = Movies_WP_Series_Media_Api_Client::normalize_directory( $directory );
		if ( is_wp_error( $directory ) ) {
			return false;
		}
		if ( isset( $options['directory_has_active_job'] ) && is_callable( $options['directory_has_active_job'] ) ) {
			return (bool) call_user_func( $options['directory_has_active_job'], $directory );
		}
		if ( ! class_exists( 'Movies_WP_Series_Import_Job_Store', false ) ) {
			return false;
		}
		return Movies_WP_Series_Import_Job_Store::has_active_for_directory( $directory );
	}

	/**
	 * @param array<string, mixed> $options
	 * @return int
	 */
	private static function system_user_id( array $options ) {
		if ( isset( $options['system_user_id'] ) ) {
			return max( 1, (int) $options['system_user_id'] );
		}
		if ( function_exists( 'get_current_user_id' ) ) {
			$current = (int) get_current_user_id();
			if ( $current > 0 ) {
				return $current;
			}
		}
		return 1;
	}
}

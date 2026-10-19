<?php
/**
 * Action Scheduler runner for background Movie import jobs.
 *
 * @package movies-wp
 */
 
defined( 'ABSPATH' ) || exit;
 
require_once __DIR__ . '/class-movies-wp-movie-import-job-store.php';
 
class Movies_WP_Movie_Import_Job_Runner {
 
	const HOOK  = 'movies_wp_movie_import_run_job';
	const GROUP = 'movies-wp-movie-import';
 
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'handle' ), 10, 1 );
	}
 
	/**
	 * @param string $job_token
	 */
	public static function handle( $job_token ) {
		$result = self::run( (string) $job_token, array() );
		if ( is_wp_error( $result ) && 'movie_import_job_busy' === $result->get_error_code() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				error_log( 'movies-wp movie import: duplicate Action Scheduler fire ignored (job busy).' );
			}
			return;
		}
	}
 
	/**
	 * Enqueue a Movie import from normalized request inputs.
	 *
	 * @param array<string, mixed> $request Normalized inputs (same shape as Movies_WP_Media_Import_Service::execute()).
	 * @param array<string, mixed> $context user_id, blog_id.
	 * @param array<string, mixed> $options Test hooks.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function enqueue_from_request( array $request, array $context = array(), array $options = array() ) {
		$schedule = isset( $options['schedule_action'] ) && is_callable( $options['schedule_action'] )
			? $options['schedule_action']
			: null;
		if ( null === $schedule && ! function_exists( 'as_schedule_single_action' ) ) {
			return new WP_Error(
				'movie_import_action_scheduler_unavailable',
				__( 'Action Scheduler is not available. Movie import cannot run in the browser request.', 'movies-wp' )
			);
		}
 
		$job = Movies_WP_Movie_Import_Job_Store::create( $request, $context );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
 
		$scheduled = self::schedule( (string) $job['token'], $schedule, $options['unschedule_action'] ?? null );
		if ( is_wp_error( $scheduled ) ) {
			Movies_WP_Movie_Import_Job_Store::update(
				(string) $job['token'],
				array(
					'status'     => 'failed',
					'last_error' => $scheduled->get_error_message(),
				),
				$context
			);
			return $scheduled;
		}
 
		Movies_WP_Movie_Import_Job_Store::update(
			(string) $job['token'],
			array(
				'status'    => 'queued',
				'action_id' => $scheduled,
			),
			$context
		);
 
		$fresh = Movies_WP_Movie_Import_Job_Store::find_by_token( (string) $job['token'] );
		if ( ! is_array( $fresh ) ) {
			return new WP_Error( 'movie_import_job_missing', __( 'Movie import job disappeared after enqueue.', 'movies-wp' ) );
		}
		$fresh['enqueued'] = true;
		return $fresh;
	}
 
	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|WP_Error
	 */
	public static function run( $raw_token, array $options = array() ) {
		$started = microtime( true );
		$job     = Movies_WP_Movie_Import_Job_Store::claim( $raw_token, $options );
		if ( ! is_array( $job ) ) {
			$existing = Movies_WP_Movie_Import_Job_Store::find_by_token( $raw_token );
			if ( is_array( $existing ) && in_array( (string) $existing['status'], array( 'completed', 'failed', 'paused' ), true ) ) {
				return $existing;
			}
			return new WP_Error( 'movie_import_job_busy', __( 'This Movie import job is already running.', 'movies-wp' ) );
		}
 
		$status = (string) ( $job['status'] ?? '' );
		if ( in_array( $status, array( 'completed', 'failed', 'paused' ), true ) ) {
			return $job;
		}
 
		$claim_token             = (string) ( $job['claim_token'] ?? '' );
		$options['_claim_token'] = $claim_token;
		$user_id                 = (int) ( $job['user_id'] ?? 0 );
		if ( $user_id > 0 && function_exists( 'wp_set_current_user' ) ) {
			wp_set_current_user( $user_id );
		}
		if ( ! Movies_WP_Movie_Import_Job_Store::heartbeat( $raw_token, $claim_token, $options ) ) {
			return new WP_Error( 'movie_import_job_busy', __( 'This Movie import job is already running.', 'movies-wp' ) );
		}
 
		$request = isset( $job['request'] ) && is_array( $job['request'] ) ? $job['request'] : array();
		// Job execution must not depend on a browser-provided checkbox shape.
		$request['confirm_import'] = true;
 
		try {
			$result = Movies_WP_Media_Import_Service::execute( $request );
			$elapsed = (int) round( ( microtime( true ) - $started ) * 1000 );
			$elapsed += (int) ( $job['elapsed_ms'] ?? 0 );
 
			if ( is_array( $result ) && ! empty( $result['ok'] ) ) {
				Movies_WP_Movie_Import_Job_Store::update(
					$raw_token,
					array(
						'status'     => 'completed',
						'last_error' => null,
						'result'     => $result,
						'elapsed_ms' => $elapsed,
						'claimed_until' => null,
						'claim_token'   => null,
					),
					$options
				);
				$movie_id = (int) ( $result['movie_id'] ?? 0 );
				if ( $movie_id > 0 ) {
					self::enqueue_telegram( 'movie', $movie_id, $raw_token, $options );
				}
			} else {
				$msg = is_array( $result ) ? (string) ( $result['message'] ?? __( 'Import failed.', 'movies-wp' ) ) : __( 'Import failed.', 'movies-wp' );
				Movies_WP_Movie_Import_Job_Store::update(
					$raw_token,
					array(
						'status'     => 'failed',
						'last_error' => Movies_WP_Media_Import_Service::safe_text( $msg ),
						'result'     => is_array( $result ) ? $result : array(),
						'elapsed_ms' => $elapsed,
						'claimed_until' => null,
						'claim_token'   => null,
					),
					$options
				);
			}
		} catch ( \Throwable $e ) {
			$elapsed = (int) round( ( microtime( true ) - $started ) * 1000 );
			$elapsed += (int) ( $job['elapsed_ms'] ?? 0 );
			Movies_WP_Movie_Import_Job_Store::update(
				$raw_token,
				array(
					'status'     => 'failed',
					'last_error' => Movies_WP_Media_Import_Service::safe_text( $e->getMessage() ),
					'elapsed_ms' => $elapsed,
					'claimed_until' => null,
					'claim_token'   => null,
				),
				$options
			);
		}
 
		$fresh = Movies_WP_Movie_Import_Job_Store::find_by_token( $raw_token );
		return is_array( $fresh ) ? $fresh : new WP_Error( 'movie_import_job_missing', __( 'Movie import job disappeared during execution.', 'movies-wp' ) );
	}
 
	/**
	 * Re-queue a stalled, failed, or cancelled job. execute() re-plans from the stored
	 * request, so a movie created by an earlier attempt is updated, not duplicated.
	 *
	 * @param string $raw_token
	 * @param array<string, mixed> $options Test hooks.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function resume( $raw_token, array $options = array() ) {
		$job = Movies_WP_Movie_Import_Job_Store::find_by_token( $raw_token );
		if ( ! is_array( $job ) ) {
			return new WP_Error( 'movie_import_job_not_found', __( 'Movie import job was not found.', 'movies-wp' ) );
		}
		$status = (string) ( $job['status'] ?? '' );
		if ( 'completed' === $status ) {
			return $job;
		}
		if ( in_array( $status, array( 'queued', 'running' ), true ) && ! Movies_WP_Movie_Import_Job_Store::is_resume_eligible_stall( $job, $options ) ) {
			return new WP_Error(
				'movie_import_job_busy',
				__( 'Import is still running or has not been confirmed stalled. Resume was not started.', 'movies-wp' )
			);
		}

		Movies_WP_Movie_Import_Job_Store::update(
			$raw_token,
			array(
				'status'        => 'queued',
				'last_error'    => null,
				'action_id'     => null,
				'claimed_until' => null,
				'claim_token'   => null,
			),
			$options
		);
		$scheduled = self::schedule( (string) $raw_token, $options['schedule_action'] ?? null, $options['unschedule_action'] ?? null );
		if ( is_wp_error( $scheduled ) ) {
			Movies_WP_Movie_Import_Job_Store::update(
				$raw_token,
				array(
					'status'     => 'failed',
					'last_error' => $scheduled->get_error_message(),
				),
				$options
			);
			return $scheduled;
		}

		$fresh = Movies_WP_Movie_Import_Job_Store::find_by_token( $raw_token );
		return is_array( $fresh ) ? $fresh : new WP_Error( 'movie_import_job_not_found', __( 'Movie import job was not found.', 'movies-wp' ) );
	}

	/**
	 * @param string $raw_token
	 * @param array<string, mixed> $options Test hooks.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function cancel( $raw_token, array $options = array() ) {
		$job = Movies_WP_Movie_Import_Job_Store::find_by_token( $raw_token );
		if ( ! is_array( $job ) ) {
			return new WP_Error( 'movie_import_job_not_found', __( 'Movie import job was not found.', 'movies-wp' ) );
		}
 
		$unschedule = isset( $options['unschedule_action'] ) && is_callable( $options['unschedule_action'] )
			? $options['unschedule_action']
			: ( function_exists( 'as_unschedule_all_actions' ) ? 'as_unschedule_all_actions' : null );
		if ( is_callable( $unschedule ) ) {
			call_user_func( $unschedule, self::HOOK, array( (string) $raw_token ), self::GROUP );
		}
 
		Movies_WP_Movie_Import_Job_Store::update(
			$raw_token,
			array(
				'status'        => 'paused',
				'claimed_until' => null,
				'claim_token'   => null,
				'last_error'    => __( 'Import cancelled. No automated rollback was performed.', 'movies-wp' ),
			),
			$options
		);
 
		$fresh = Movies_WP_Movie_Import_Job_Store::find_by_token( $raw_token );
		return is_array( $fresh ) ? $fresh : new WP_Error( 'movie_import_job_not_found', __( 'Movie import job was not found.', 'movies-wp' ) );
	}
 
	/**
	 * @return int|true|WP_Error  Action id (when available), true for test stubs.
	 */
	private static function schedule( $raw_token, $schedule_action = null, $unschedule_action = null ) {
		if ( is_callable( $unschedule_action ) ) {
			call_user_func( $unschedule_action, self::HOOK, array( (string) $raw_token ), self::GROUP );
		} elseif ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array( (string) $raw_token ), self::GROUP );
		}
 
		if ( is_callable( $schedule_action ) ) {
			return call_user_func( $schedule_action, time(), self::HOOK, array( (string) $raw_token ), self::GROUP );
		}
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return new WP_Error(
				'movie_import_action_scheduler_unavailable',
				__( 'Action Scheduler is not available. Movie import cannot run in the browser request.', 'movies-wp' )
			);
		}
		return as_schedule_single_action( time(), self::HOOK, array( (string) $raw_token ), self::GROUP );
	}

	/**
	 * Queue a Telegram announcement after the import job is already saved.
	 * A missing publisher or a queue error cannot change the import result.
	 *
	 * @param string               $type
	 * @param int                  $post_id
	 * @param string               $token
	 * @param array<string, mixed> $options
	 */
	private static function enqueue_telegram( $type, $post_id, $token, array $options ) {
		try {
			if ( ! class_exists( 'Movies_WP_Telegram_Publisher' ) ) {
				return;
			}
			Movies_WP_Telegram_Publisher::enqueue( $type, (int) $post_id, (string) $token, $options );
		} catch ( \Throwable $e ) {
			unset( $e );
		}
	}
}


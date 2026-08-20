<?php
/**
 * Action Scheduler runner for resumable Series import jobs.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-movies-wp-series-import-snapshot-store.php';
require_once __DIR__ . '/class-movies-wp-series-import-job-store.php';
require_once __DIR__ . '/class-movies-wp-series-import-invalidation-coalesce.php';

class Movies_WP_Series_Import_Job_Runner {

	const HOOK  = 'movies_wp_series_import_run_job';
	const GROUP = 'movies-wp-series-import';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'handle' ), 10, 1 );
	}

	/**
	 * @param string $job_token
	 */
	public static function handle( $job_token ) {
		$result = self::run( (string) $job_token, array() );
		if ( is_wp_error( $result ) && 'series_import_job_busy' === $result->get_error_code() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				error_log( 'movies-wp series import: duplicate Action Scheduler fire ignored (job busy).' );
			}
			return;
		}
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|WP_Error
	 */
	public static function enqueue_from_snapshot( $raw_snapshot_token, array $context = array(), array $options = array() ) {
		$snapshot = Movies_WP_Series_Import_Snapshot_Store::load_valid( $raw_snapshot_token, $context );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		$payload = is_array( $snapshot['payload'] ?? null ) ? $snapshot['payload'] : array();
		$gate    = self::validate_snapshot_payload( $payload );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$user_id = (int) ( $context['user_id'] ?? 0 );
		$blog_id = (int) ( $context['blog_id'] ?? 1 );
		if ( Movies_WP_Series_Import_Job_Store::has_active_for_snapshot( (int) $snapshot['id'], $user_id, $blog_id ) ) {
			return new WP_Error( 'series_import_job_duplicate', __( 'An import job for this preview is already active.', 'movies-wp' ) );
		}

		$schedule = isset( $options['schedule_action'] ) && is_callable( $options['schedule_action'] )
			? $options['schedule_action']
			: null;
		if ( null === $schedule && ! function_exists( 'as_schedule_single_action' ) ) {
			return new WP_Error(
				'series_import_action_scheduler_unavailable',
				__( 'Action Scheduler is not available. Series import cannot run in the browser request.', 'movies-wp' )
			);
		}

		$episodes = self::flatten_episodes( $payload['metadata_plan'] ?? array() );
		$job      = Movies_WP_Series_Import_Job_Store::create(
			array(
				'user_id'       => $user_id,
				'blog_id'       => $blog_id,
				'tmdb_id'       => (int) ( $payload['input']['tmdb_id'] ?? 0 ),
				'directory'     => (string) ( $payload['input']['series_directory'] ?? '' ),
				'snapshot_id'   => (int) $snapshot['id'],
				'episode_total' => count( $episodes ),
				'result'        => array(
					'snapshot_token_hash' => (string) $snapshot['token_hash'],
					'fingerprint'         => (string) $snapshot['fingerprint'],
					'episode_ids_by_season' => array(),
				),
			),
			$context
		);
		if ( is_wp_error( $job ) ) {
			return $job;
		}

		$scheduled = self::schedule( (string) $job['token'], $schedule, $options['unschedule_action'] ?? null );
		if ( is_wp_error( $scheduled ) ) {
			Movies_WP_Series_Import_Job_Store::update(
				(string) $job['token'],
				array(
					'status'     => 'failed',
					'last_error' => $scheduled->get_error_message(),
				),
				$context
			);
			return $scheduled;
		}
		Movies_WP_Series_Import_Job_Store::update(
			(string) $job['token'],
			array(
				'status' => 'queued',
			),
			$context
		);
		$job = Movies_WP_Series_Import_Job_Store::find_by_token( (string) $job['token'] );
		if ( ! is_array( $job ) ) {
			return new WP_Error( 'series_import_job_missing', __( 'Series import job disappeared after enqueue.', 'movies-wp' ) );
		}
		$job['enqueued'] = true;
		return $job;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|WP_Error
	 */
	public static function run_inline( array $payload, array $options = array() ) {
		$gate = self::validate_snapshot_payload( $payload );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		if ( ! self::test_memory_enabled() ) {
			return new WP_Error(
				'series_import_inline_test_only',
				__( 'Inline Series import is only available to isolated tests.', 'movies-wp' )
			);
		}
		Movies_WP_Series_Import_Snapshot_Store::reset_memory();
		Movies_WP_Series_Import_Job_Store::reset_memory();
		$created = Movies_WP_Series_Import_Snapshot_Store::create( $payload, array( 'user_id' => 1, 'blog_id' => 1 ) );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$job = self::enqueue_from_snapshot(
			$created['token'],
			array(
				'user_id' => 1,
				'blog_id' => 1,
			),
			array(
				'schedule_action' => static function () {
					return true;
				},
			)
		);
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$guard = 0;
		while ( $guard < 80 ) {
			++$guard;
			$state = self::run( (string) $job['token'], $options );
			if ( is_wp_error( $state ) ) {
				return $state;
			}
			$status = (string) ( $state['status'] ?? '' );
			if ( in_array( $status, array( 'completed', 'failed', 'paused' ), true ) ) {
				return self::present( $state );
			}
		}
		return new WP_Error( 'series_import_job_loop', __( 'Series import job exceeded the inline step limit.', 'movies-wp' ) );
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|WP_Error
	 */
	public static function run( $raw_token, array $options = array() ) {
		$claim_started = microtime( true );
		if ( class_exists( 'Movies_WP_Series_Import_Profiler' ) && Movies_WP_Series_Import_Profiler::enabled() && ! Movies_WP_Series_Import_Profiler::is_active() ) {
			Movies_WP_Series_Import_Profiler::start( 'SERIES IMPORT JOB' );
		}
		$job = Movies_WP_Series_Import_Job_Store::claim( $raw_token, $options );
		if ( ! is_array( $job ) ) {
			$existing = Movies_WP_Series_Import_Job_Store::find_by_token( $raw_token );
			if ( is_array( $existing ) && in_array( (string) $existing['status'], array( 'completed', 'failed' ), true ) ) {
				return $existing;
			}
			return new WP_Error( 'series_import_job_busy', __( 'This Series import job is already running.', 'movies-wp' ) );
		}
		if ( in_array( (string) $job['status'], array( 'completed', 'failed' ), true ) ) {
			return $job;
		}
		if ( 'paused' === (string) $job['status'] ) {
			return $job;
		}

		if ( class_exists( 'Movies_WP_Series_Import_Profiler' ) && Movies_WP_Series_Import_Profiler::is_active() ) {
			Movies_WP_Series_Import_Profiler::progress(
				sprintf(
					'JOB_CLAIM elapsed_ms=%d status=%s phase=%s episode_done=%d',
					(int) round( ( microtime( true ) - $claim_started ) * 1000 ),
					(string) $job['status'],
					(string) $job['phase'],
					(int) ( $job['episode_done'] ?? 0 )
				)
			);
			Movies_WP_Series_Import_Profiler::set_phase( (string) $job['phase'] );
		}

		$claim_token = (string) ( $job['claim_token'] ?? '' );
		$options['_claim_token'] = $claim_token;
		$user_id     = (int) ( $job['user_id'] ?? 0 );
		if ( $user_id > 0 && function_exists( 'wp_set_current_user' ) ) {
			wp_set_current_user( $user_id );
		}
		if ( ! self::heartbeat( $raw_token, $options ) ) {
			return new WP_Error( 'series_import_job_busy', __( 'This Series import job is already running.', 'movies-wp' ) );
		}

		$snapshot = self::snapshot_for_job( $job, $options );
		if ( is_wp_error( $snapshot ) ) {
			self::fail( $raw_token, $snapshot->get_error_message(), $job, $options );
			return Movies_WP_Series_Import_Job_Store::find_by_token( $raw_token );
		}
		$payload = $snapshot['payload'];
		$plan    = is_array( $payload['metadata_plan'] ?? null ) ? $payload['metadata_plan'] : array();
		$started = microtime( true );

		Movies_WP_Series_Import_Invalidation_Coalesce::begin();
		try {
			$phase = (string) $job['phase'];
			if ( 'series' === $phase ) {
				self::phase_series( $raw_token, $job, $plan, $options );
			} elseif ( 'people' === $phase ) {
				self::phase_people( $raw_token, $job, $plan, $options );
			} elseif ( 'images' === $phase ) {
				self::phase_images( $raw_token, $job, $plan, $options );
			} elseif ( 'episodes' === $phase ) {
				self::phase_episodes( $raw_token, $job, $plan, $options );
			} elseif ( 'season' === $phase ) {
				self::phase_season( $raw_token, $job, $plan, $options );
			} elseif ( 'rematch' === $phase ) {
				self::phase_rematch( $raw_token, $job, $payload, $options );
			} elseif ( 'media' === $phase ) {
				self::phase_media( $raw_token, $job, $options );
			} elseif ( 'finalize' === $phase ) {
				self::phase_finalize( $raw_token, $job, $options );
			} else {
				self::fail( $raw_token, 'Unknown import phase: ' . $phase, $job, $options );
			}
		} catch ( \Throwable $e ) {
			self::fail( $raw_token, $e->getMessage(), $job, $options );
		} finally {
			Movies_WP_Series_Import_Invalidation_Coalesce::finish();
		}

		$elapsed = (int) round( ( microtime( true ) - $started ) * 1000 );
		$job     = Movies_WP_Series_Import_Job_Store::find_by_token( $raw_token );
		if ( ! is_array( $job ) ) {
			return new WP_Error( 'series_import_job_missing', __( 'Series import job disappeared during execution.', 'movies-wp' ) );
		}
		if ( ! self::still_owns_claim( $job, $claim_token ) ) {
			return new WP_Error( 'series_import_job_busy', __( 'This Series import job is already running.', 'movies-wp' ) );
		}
		$elapsed += (int) ( $job['elapsed_ms'] ?? 0 );
		if ( 'running' === (string) $job['status'] ) {
			$scheduled = self::schedule( $raw_token, $options['schedule_action'] ?? null, $options['unschedule_action'] ?? null );
			if ( is_wp_error( $scheduled ) ) {
				self::fail( $raw_token, $scheduled->get_error_message(), $job, $options );
				return Movies_WP_Series_Import_Job_Store::find_by_token( $raw_token );
			}
			Movies_WP_Series_Import_Job_Store::update(
				$raw_token,
				array(
					'status'        => 'queued',
					'claimed_until' => null,
					'claim_token'   => null,
					'elapsed_ms'    => $elapsed,
				),
				$options
			);
			$job = Movies_WP_Series_Import_Job_Store::find_by_token( $raw_token );
		} else {
			Movies_WP_Series_Import_Job_Store::update( $raw_token, array( 'elapsed_ms' => $elapsed ), $options );
			$job = Movies_WP_Series_Import_Job_Store::find_by_token( $raw_token );
		}
		return is_array( $job ) ? $job : new WP_Error( 'series_import_job_missing', __( 'Series import job disappeared during execution.', 'movies-wp' ) );
	}

	public static function resume( $raw_token, array $options = array() ) {
		$job = Movies_WP_Series_Import_Job_Store::find_by_token( $raw_token );
		if ( ! is_array( $job ) ) {
			return new WP_Error( 'series_import_job_not_found', __( 'Series import job was not found.', 'movies-wp' ) );
		}
		if ( 'completed' === (string) $job['status'] ) {
			return $job;
		}
		Movies_WP_Series_Import_Job_Store::update(
			$raw_token,
			array(
				'status'        => 'queued',
				'last_error'    => null,
				'claimed_until' => null,
			),
			$options
		);
		$scheduled = self::schedule( $raw_token, $options['schedule_action'] ?? null, $options['unschedule_action'] ?? null );
		if ( is_wp_error( $scheduled ) ) {
			return $scheduled;
		}
		return Movies_WP_Series_Import_Job_Store::find_by_token( $raw_token );
	}

	public static function cancel( $raw_token, array $options = array() ) {
		$job = Movies_WP_Series_Import_Job_Store::find_by_token( $raw_token );
		if ( ! is_array( $job ) ) {
			return new WP_Error( 'series_import_job_not_found', __( 'Series import job was not found.', 'movies-wp' ) );
		}
		$unschedule = isset( $options['unschedule_action'] ) && is_callable( $options['unschedule_action'] )
			? $options['unschedule_action']
			: ( function_exists( 'as_unschedule_all_actions' ) ? 'as_unschedule_all_actions' : null );
		if ( is_callable( $unschedule ) ) {
			call_user_func( $unschedule, self::HOOK, array( $raw_token ), self::GROUP );
		}
		Movies_WP_Series_Import_Job_Store::update(
			$raw_token,
			array(
				'status'        => 'paused',
				'claimed_until' => null,
				'last_error'    => __( 'Import cancelled. Catalog data was not deleted.', 'movies-wp' ),
			),
			$options
		);
		return Movies_WP_Series_Import_Job_Store::find_by_token( $raw_token );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function validate_snapshot_payload( array $payload ) {
		if ( true !== ( $payload['ok'] ?? null ) || true !== ( $payload['ready_to_import'] ?? null ) ) {
			return new WP_Error( 'series_automation_not_ready', __( 'Series Automation preview contains errors and is not ready to import.', 'movies-wp' ) );
		}
		$plan = is_array( $payload['metadata_plan'] ?? null ) ? $payload['metadata_plan'] : array();
		if ( true !== ( $plan['ready_to_import'] ?? null ) || true !== ( $plan['ok'] ?? null ) ) {
			return new WP_Error( 'series_automation_not_ready', __( 'Series import plan is not ready.', 'movies-wp' ) );
		}
		$input = is_array( $payload['input'] ?? null ) ? $payload['input'] : array();
		$tmdb  = absint( $input['tmdb_id'] ?? 0 );
		$dir   = (string) ( $input['series_directory'] ?? '' );
		$title = trim( (string) ( $input['title'] ?? '' ) );
		if ( $tmdb <= 0 || '' === $dir || '' === $title ) {
			return new WP_Error( 'series_import_snapshot_identity', __( 'Series snapshot is missing operator identity.', 'movies-wp' ) );
		}
		if ( absint( $plan['series']['tmdb_id'] ?? 0 ) !== $tmdb ) {
			return new WP_Error( 'series_import_snapshot_identity', __( 'Series snapshot TMDb identity does not match the plan.', 'movies-wp' ) );
		}
		return true;
	}

	public static function episode_chunk_size() {
		$size = 4;
		if ( function_exists( 'apply_filters' ) ) {
			$size = (int) apply_filters( 'movies_wp_series_import_episode_chunk_size', 4 );
		}
		if ( $size < 3 ) {
			$size = 3;
		}
		if ( $size > 5 ) {
			$size = 5;
		}
		return $size;
	}

	private static function phase_series( $token, array $job, array $plan, array $options ) {
		if ( ! self::heartbeat( $token, $options ) ) {
			return;
		}
		$apply = isset( $options['apply_series'] ) && is_callable( $options['apply_series'] )
			? $options['apply_series']
			: array( 'Movies_WP_Streamit_TV_Adapter', 'apply_series_phase' );
		$result = call_user_func( $apply, $plan, $options );
		if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
			self::fail( $token, self::error_message( $result ), $job, $options, $result );
			return;
		}
		$state = is_array( $job['result'] ) ? $job['result'] : array();
		$state['series']    = $result;
		$state['series_id'] = (int) ( $result['series_id'] ?? 0 );
		$state['action']    = (string) ( $result['action'] ?? '' );
		self::note_warnings( $token, $job, $result, $options );
		Movies_WP_Series_Import_Job_Store::update(
			$token,
			array(
				'phase'     => 'people',
				'series_id' => $state['series_id'],
				'result'    => $state,
				'status'    => 'running',
			),
			$options
		);
	}

	private static function phase_people( $token, array $job, array $plan, array $options ) {
		if ( ! self::heartbeat( $token, $options ) ) {
			return;
		}
		$series_id = (int) ( $job['series_id'] ?? ( $job['result']['series_id'] ?? 0 ) );
		$apply     = isset( $options['apply_people'] ) && is_callable( $options['apply_people'] )
			? $options['apply_people']
			: array( 'Movies_WP_Streamit_TV_Adapter', 'apply_people_phase' );
		$result = call_user_func( $apply, $series_id, $plan, $options );
		if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
			self::fail( $token, self::error_message( $result ), $job, $options, $result );
			return;
		}
		self::note_warnings( $token, $job, $result, $options );
		$state           = is_array( $job['result'] ) ? $job['result'] : array();
		$state['people'] = $result;
		Movies_WP_Series_Import_Job_Store::update(
			$token,
			array(
				'phase'  => 'images',
				'result' => $state,
				'status' => 'running',
			),
			$options
		);
	}

	private static function phase_images( $token, array $job, array $plan, array $options ) {
		if ( ! self::heartbeat( $token, $options ) ) {
			return;
		}
		$series_id = (int) ( $job['series_id'] ?? ( $job['result']['series_id'] ?? 0 ) );
		$apply     = isset( $options['apply_images'] ) && is_callable( $options['apply_images'] )
			? $options['apply_images']
			: array( 'Movies_WP_Streamit_TV_Adapter', 'apply_images_phase' );
		$result = call_user_func( $apply, $series_id, $plan, $options );
		if ( ! is_array( $result ) ) {
			self::fail( $token, 'Series image phase returned invalid data.', $job, $options );
			return;
		}
		self::note_warnings( $token, $job, $result, $options );
		if ( empty( $result['ok'] ) && empty( $result['warning_only'] ) ) {
			$hard = true;
			foreach ( isset( $result['images'] ) && is_array( $result['images'] ) ? $result['images'] : array() as $image ) {
				if ( is_array( $image ) && empty( $image['ok'] ) ) {
					$hard = true;
				}
			}
			if ( $hard && empty( $result['continue'] ) ) {
				self::append_warning( $token, $job, self::error_message( $result ), $options );
			}
		}
		$state           = is_array( $job['result'] ) ? $job['result'] : array();
		$state['images'] = $result;
		Movies_WP_Series_Import_Job_Store::update(
			$token,
			array(
				'phase'  => 'episodes',
				'result' => $state,
				'status' => 'running',
			),
			$options
		);
	}

	private static function phase_episodes( $token, array $job, array $plan, array $options ) {
		if ( ! self::heartbeat( $token, $options ) ) {
			return;
		}
		$series_id = (int) ( $job['series_id'] ?? ( $job['result']['series_id'] ?? 0 ) );
		$episodes  = self::flatten_episodes( $plan );
		$done      = (int) ( $job['episode_done'] ?? 0 );
		$chunk     = self::episode_chunk_size();
		$apply     = isset( $options['persist_episode'] ) && is_callable( $options['persist_episode'] )
			? $options['persist_episode']
			: array( 'Movies_WP_Streamit_TV_Adapter', 'apply_episode_phase' );
		$state     = is_array( $job['result'] ) ? $job['result'] : array();
		$by_season = isset( $state['episode_ids_by_season'] ) && is_array( $state['episode_ids_by_season'] )
			? $state['episode_ids_by_season']
			: array();
		$processed = 0;

		while ( $processed < $chunk && $done < count( $episodes ) ) {
			$episode_plan = $episodes[ $done ];
			$season_key   = (string) ( $episode_plan['season_number'] ?? '' );
			if ( ! isset( $by_season[ $season_key ] ) ) {
				$by_season[ $season_key ] = array();
			}
			$existing_id = absint( $episode_plan['existing_episode_id'] ?? 0 );
			if ( $existing_id > 0 ) {
				$result = array(
					'ok'             => true,
					'skipped'        => true,
					'action'         => 'update',
					'episode_id'     => $existing_id,
					'season_number'  => $season_key,
					'episode_number' => (int) ( $episode_plan['episode_number'] ?? 0 ),
				);
			} else {
				$pending    = isset( $state['pending_created_episode'] ) && is_array( $state['pending_created_episode'] )
					? $state['pending_created_episode']
					: array();
				$pending_id = absint( $pending['episode_id'] ?? 0 );
				if ( $pending_id > 0 && (int) ( $pending['index'] ?? -1 ) === $done ) {
					$episode_plan['retry_created_episode_id'] = $pending_id;
				}
				$persist_options                            = $options;
				$persist_options['remember_created_episode'] = static function ( $episode_id ) use ( $token, &$state, $done, $options ) {
					$state['pending_created_episode'] = array(
						'index'      => $done,
						'episode_id' => absint( $episode_id ),
					);
					$saved = Movies_WP_Series_Import_Job_Store::update(
						$token,
						array(
							'result' => $state,
							'status' => 'running',
							'phase'  => 'episodes',
						),
						$options
					);
					if ( ! $saved ) {
						return false;
					}
					$fresh = Movies_WP_Series_Import_Job_Store::find_by_token( $token );
					if ( is_array( $fresh ) && isset( $fresh['result'] ) && is_array( $fresh['result'] ) ) {
						$state = $fresh['result'];
					}
					return true;
				};
				$result = call_user_func( $apply, $series_id, $episode_plan, $persist_options );
			}
			if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
				self::fail( $token, self::error_message( $result ), $job, $options, $result );
				return;
			}
			$id = absint( $result['episode_id'] ?? 0 );
			if ( $id > 0 && ! in_array( $id, $by_season[ $season_key ], true ) ) {
				$by_season[ $season_key ][] = $id;
			}
			++$done;
			++$processed;
			$state['episode_ids_by_season'] = $by_season;
			$state['episodes']              = isset( $state['episodes'] ) && is_array( $state['episodes'] ) ? $state['episodes'] : array();
			$state['episodes'][]            = $result;
			unset( $state['pending_created_episode'] );
			Movies_WP_Series_Import_Job_Store::update(
				$token,
				array(
					'episode_done'    => $done,
					'last_episode_id' => $id > 0 ? $id : null,
					'result'          => $state,
					'status'          => 'running',
					'phase'           => 'episodes',
				),
				$options
			);
			if ( ! self::heartbeat( $token, $options ) ) {
				return;
			}
			$job = Movies_WP_Series_Import_Job_Store::find_by_token( $token );
			if ( ! is_array( $job ) ) {
				return;
			}
			$state = is_array( $job['result'] ) ? $job['result'] : $state;
		}

		if ( $done >= count( $episodes ) ) {
			Movies_WP_Series_Import_Job_Store::update(
				$token,
				array(
					'phase'  => 'season',
					'status' => 'running',
					'result' => $state,
				),
				$options
			);
		}
	}

	private static function phase_season( $token, array $job, array $plan, array $options ) {
		if ( ! self::heartbeat( $token, $options ) ) {
			return;
		}
		$series_id = (int) ( $job['series_id'] ?? ( $job['result']['series_id'] ?? 0 ) );
		$by_season = isset( $job['result']['episode_ids_by_season'] ) && is_array( $job['result']['episode_ids_by_season'] )
			? $job['result']['episode_ids_by_season']
			: array();
		$apply     = isset( $options['apply_seasons'] ) && is_callable( $options['apply_seasons'] )
			? $options['apply_seasons']
			: array( 'Movies_WP_Streamit_TV_Adapter', 'apply_seasons_phase' );
		$result = call_user_func( $apply, $series_id, $plan, $by_season, $options );
		if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
			self::fail( $token, self::error_message( $result ), $job, $options, $result );
			return;
		}
		self::note_warnings( $token, $job, $result, $options );
		$state           = is_array( $job['result'] ) ? $job['result'] : array();
		$state['seasons'] = $result;
		Movies_WP_Series_Import_Job_Store::update(
			$token,
			array(
				'phase'  => 'rematch',
				'result' => $state,
				'status' => 'running',
			),
			$options
		);
	}

	private static function phase_rematch( $token, array $job, array $payload, array $options ) {
		if ( ! self::heartbeat( $token, $options ) ) {
			return;
		}
		$series_id = (int) ( $job['series_id'] ?? ( $job['result']['series_id'] ?? 0 ) );
		$scan      = is_array( $payload['media'] ?? null ) ? $payload['media'] : array();
		$input     = array(
			'tvshow_id'        => $series_id,
			'expected_tmdb_id' => absint( $payload['input']['tmdb_id'] ?? 0 ),
			'series_directory' => (string) ( $payload['input']['series_directory'] ?? '' ),
		);
		$preview_opts = $options;
		$fingerprint  = Movies_WP_Series_Import_Snapshot_Store::fingerprint( $payload );
		$stored       = (string) ( $job['result']['fingerprint'] ?? $fingerprint );
		$force_scan   = ! empty( $options['force_rescan'] );
		$expired      = isset( $payload['_snapshot_expired'] ) && $payload['_snapshot_expired'];
		if ( ! $expired && isset( $job['snapshot_id'] ) ) {
			$row = Movies_WP_Series_Import_Snapshot_Store::find_by_id( (int) $job['snapshot_id'] );
			if ( is_array( $row ) && isset( $row['expires_at'] ) ) {
				$now = isset( $options['now'] ) ? (int) $options['now'] : time();
				$expired = strtotime( (string) $row['expires_at'] . ' UTC' ) < $now;
			}
		}
		if ( ! $force_scan && ! $expired && $stored === $fingerprint && array() !== $scan ) {
			$preview_opts['scan'] = $scan;
		}
		$build = isset( $options['media_preview_build'] ) && is_callable( $options['media_preview_build'] )
			? $options['media_preview_build']
			: array( 'Movies_WP_Series_Media_Preview_Service', 'build' );
		$media_preview = call_user_func( $build, $input, $preview_opts );
		if ( is_wp_error( $media_preview ) ) {
			self::fail( $token, $media_preview->get_error_message(), $job, $options );
			return;
		}
		if ( ! is_array( $media_preview ) ) {
			self::fail( $token, __( 'Series media preview returned invalid data after metadata import.', 'movies-wp' ), $job, $options );
			return;
		}
		$plan_build = isset( $options['media_plan_build'] ) && is_callable( $options['media_plan_build'] )
			? $options['media_plan_build']
			: array( 'Movies_WP_Series_Media_Import_Plan', 'build' );
		$media_plan = call_user_func( $plan_build, $media_preview, $options );
		if ( is_wp_error( $media_plan ) ) {
			self::fail( $token, $media_plan->get_error_message(), $job, $options );
			return;
		}
		if ( ! is_array( $media_plan ) || true !== ( $media_plan['ready_to_import'] ?? null ) ) {
			self::fail( $token, __( 'Series metadata was imported, but the rebuilt media plan is not safe to execute.', 'movies-wp' ), $job, $options );
			return;
		}
		$state                  = is_array( $job['result'] ) ? $job['result'] : array();
		$state['media_preview'] = array(
			'ok'       => true,
			'episodes' => $media_preview['episodes'] ?? array(),
			'reused_scan' => isset( $preview_opts['scan'] ),
		);
		$state['media_plan'] = $media_plan;
		Movies_WP_Series_Import_Job_Store::update(
			$token,
			array(
				'phase'  => 'media',
				'result' => $state,
				'status' => 'running',
			),
			$options
		);
	}

	private static function phase_media( $token, array $job, array $options ) {
		if ( ! self::heartbeat( $token, $options ) ) {
			return;
		}
		$plan = isset( $job['result']['media_plan'] ) && is_array( $job['result']['media_plan'] )
			? $job['result']['media_plan']
			: array();
		$exec = isset( $options['media_import_execute'] ) && is_callable( $options['media_import_execute'] )
			? $options['media_import_execute']
			: array( 'Movies_WP_Series_Media_Import_Service', 'execute' );
		$result = call_user_func( $exec, $plan, $options );
		if ( ! is_array( $result ) ) {
			self::fail( $token, __( 'Series media import returned invalid data.', 'movies-wp' ), $job, $options );
			return;
		}
		self::note_warnings( $token, $job, $result, $options );
		if ( empty( $result['ok'] ) ) {
			self::fail( $token, self::error_message( $result ), $job, $options, $result );
			return;
		}
		$state          = is_array( $job['result'] ) ? $job['result'] : array();
		$state['media'] = $result;
		Movies_WP_Series_Import_Job_Store::update(
			$token,
			array(
				'phase'  => 'finalize',
				'result' => $state,
				'status' => 'running',
			),
			$options
		);
	}

	private static function phase_finalize( $token, array $job, array $options ) {
		if ( ! self::heartbeat( $token, $options ) ) {
			return;
		}
		$series_id = (int) ( $job['series_id'] ?? 0 );
		$media     = is_array( $job['result']['media'] ?? null ) ? $job['result']['media'] : array();
		if ( $series_id <= 0 ) {
			self::fail( $token, __( 'Series metadata import succeeded without a valid Streamit Series ID.', 'movies-wp' ), $job, $options );
			return;
		}
		if ( isset( $media['tvshow_id'] ) && absint( $media['tvshow_id'] ) !== $series_id ) {
			self::fail( $token, __( 'Series media import returned an unexpected TV show identity.', 'movies-wp' ), $job, $options );
			return;
		}
		Movies_WP_Series_Import_Job_Store::update(
			$token,
			array(
				'phase'         => 'completed',
				'status'        => 'completed',
				'claimed_until' => null,
				'last_error'    => null,
			),
			$options
		);
	}

	private static function snapshot_for_job( array $job, array $options ) {
		if ( isset( $options['snapshot_payload'] ) && is_array( $options['snapshot_payload'] ) ) {
			return array( 'payload' => $options['snapshot_payload'] );
		}
		$row = Movies_WP_Series_Import_Snapshot_Store::find_by_id( (int) ( $job['snapshot_id'] ?? 0 ) );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'series_import_snapshot_not_found', __( 'Series import snapshot was not found.', 'movies-wp' ) );
		}
		return $row;
	}

	private static function fail( $token, $message, array $job, array $options, $result = null ) {
		unset( $result );
		Movies_WP_Series_Import_Job_Store::update(
			$token,
			array(
				'status'        => 'failed',
				'last_error'    => (string) $message,
				'claimed_until' => null,
			),
			$options
		);
	}

	private static function note_warnings( $token, array $job, array $result, array $options ) {
		$incoming = array();
		if ( isset( $result['warnings'] ) && is_array( $result['warnings'] ) ) {
			$incoming = $result['warnings'];
		}
		if ( isset( $result['error'] ) && is_array( $result['error'] ) && ! empty( $result['ok'] ) ) {
			$incoming[] = $result['error'];
		}
		if ( array() === $incoming ) {
			return;
		}
		$warnings = isset( $job['warnings'] ) && is_array( $job['warnings'] ) ? $job['warnings'] : array();
		foreach ( $incoming as $warning ) {
			$warnings[] = $warning;
		}
		Movies_WP_Series_Import_Job_Store::update( $token, array( 'warnings' => $warnings ), $options );
	}

	private static function append_warning( $token, array $job, $message, array $options ) {
		$warnings   = isset( $job['warnings'] ) && is_array( $job['warnings'] ) ? $job['warnings'] : array();
		$warnings[] = array(
			'code'    => 'series_import_phase_warning',
			'message' => (string) $message,
		);
		Movies_WP_Series_Import_Job_Store::update( $token, array( 'warnings' => $warnings ), $options );
	}

	private static function error_message( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}
		if ( is_array( $result ) ) {
			if ( isset( $result['error']['message'] ) ) {
				return (string) $result['error']['message'];
			}
			if ( isset( $result['errors'][0]['message'] ) ) {
				return (string) $result['errors'][0]['message'];
			}
			if ( isset( $result['message'] ) ) {
				return (string) $result['message'];
			}
		}
		return __( 'Series import phase failed.', 'movies-wp' );
	}

	private static function flatten_episodes( array $plan ) {
		$out = array();
		foreach ( isset( $plan['seasons'] ) && is_array( $plan['seasons'] ) ? $plan['seasons'] : array() as $season ) {
			if ( ! is_array( $season ) ) {
				continue;
			}
			foreach ( isset( $season['episodes'] ) && is_array( $season['episodes'] ) ? $season['episodes'] : array() as $episode ) {
				if ( is_array( $episode ) ) {
					$out[] = $episode;
				}
			}
		}
		return $out;
	}

	private static function schedule( $raw_token, $callback = null, $unschedule = null ) {
		if ( is_callable( $unschedule ) ) {
			call_user_func( $unschedule, self::HOOK, array( $raw_token ), self::GROUP );
		} elseif ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array( $raw_token ), self::GROUP );
		}

		if ( is_callable( $callback ) ) {
			$id = call_user_func( $callback, time(), self::HOOK, array( $raw_token ), self::GROUP );
			return self::schedule_result( $id );
		}
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return new WP_Error(
				'series_import_action_scheduler_unavailable',
				__( 'Action Scheduler is not available. Series import cannot run in the browser request.', 'movies-wp' )
			);
		}
		$id = as_schedule_single_action( time(), self::HOOK, array( $raw_token ), self::GROUP );
		return self::schedule_result( $id );
	}

	/**
	 * @param mixed $id
	 * @return true|WP_Error
	 */
	private static function schedule_result( $id ) {
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( true === $id || ( is_int( $id ) && $id > 0 ) || ( is_string( $id ) && ctype_digit( $id ) && (int) $id > 0 ) ) {
			return true;
		}
		return new WP_Error(
			'series_import_schedule_failed',
			__( 'Could not schedule the next Series import step.', 'movies-wp' )
		);
	}

	private static function heartbeat( $token, array $options ) {
		$claim = (string) ( $options['_claim_token'] ?? '' );
		if ( '' === $claim ) {
			return false;
		}
		return Movies_WP_Series_Import_Job_Store::heartbeat( $token, $claim, $options );
	}

	private static function still_owns_claim( array $job, $claim_token ) {
		if ( in_array( (string) ( $job['status'] ?? '' ), array( 'completed', 'failed', 'paused' ), true ) ) {
			return true;
		}
		return (string) ( $job['claim_token'] ?? '' ) === (string) $claim_token && '' !== (string) $claim_token;
	}

	public static function test_memory_enabled() {
		return defined( 'MOVIES_WP_SERIES_IMPORT_TEST_MEMORY' ) && MOVIES_WP_SERIES_IMPORT_TEST_MEMORY;
	}

	private static function present( array $job ) {
		$media = is_array( $job['result']['media'] ?? null ) ? $job['result']['media'] : null;
		$ok    = 'completed' === (string) $job['status'];
		return array(
			'ok'             => $ok,
			'partial'        => ! $ok && (int) ( $job['series_id'] ?? 0 ) > 0,
			'type'           => 'series_automation',
			'series_id'      => $job['series_id'] ?? null,
			'action'         => $job['result']['action'] ?? null,
			'completed'      => (int) ( is_array( $media ) ? ( $media['completed'] ?? 0 ) : 0 ),
			'job'            => $job,
			'errors'         => '' === (string) ( $job['last_error'] ?? '' ) ? array() : array( array( 'message' => $job['last_error'] ) ),
			'warnings'       => isset( $job['warnings'] ) && is_array( $job['warnings'] ) ? $job['warnings'] : array(),
			'episodes'       => isset( $job['result']['episodes'] ) && is_array( $job['result']['episodes'] ) ? $job['result']['episodes'] : array(),
			'media_episodes' => isset( $media['episodes'] ) && is_array( $media['episodes'] ) ? $media['episodes'] : array(),
			'stages'         => array(
				'metadata' => (int) ( $job['series_id'] ?? 0 ) > 0 ? 'completed' : 'failed',
				'media'    => is_array( $media ) && ! empty( $media['ok'] ) ? 'completed' : ( 'failed' === (string) $job['status'] && (int) ( $job['series_id'] ?? 0 ) > 0 ? 'failed' : 'skipped' ),
			),
		);
	}
}

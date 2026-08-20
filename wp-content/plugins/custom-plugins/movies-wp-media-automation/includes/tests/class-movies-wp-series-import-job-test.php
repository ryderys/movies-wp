<?php
/**
 * CLI tests for Series import snapshots, jobs, and the Action Scheduler runner.
 *
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-import-job-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-import-job-test/' );
}
if ( ! defined( 'MOVIES_WP_SERIES_IMPORT_TEST_MEMORY' ) ) {
	define( 'MOVIES_WP_SERIES_IMPORT_TEST_MEMORY', true );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code, $message ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12 ) {
		static $n = 0;
		++$n;
		return substr( hash( 'sha256', 'movies-wp-job-test-' . $n ), 0, (int) $length );
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-snapshot-store.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-job-store.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-job-runner.php';

$failures = 0;
$scheduled = array();

function job_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function job_same( $expected, $actual, string $label ): void {
	job_assert( $expected === $actual, $label . ' expected=' . var_export( $expected, true ) . ' got=' . var_export( $actual, true ) );
}

function job_payload( array $episodes = array(), bool $ready = true ): array {
	if ( array() === $episodes ) {
		$episodes = array(
			array(
				'action'         => 'create',
				'season_number'  => '1',
				'episode_number' => 1,
				'name'           => 'One',
			),
			array(
				'action'         => 'create',
				'season_number'  => '1',
				'episode_number' => 2,
				'name'           => 'Two',
			),
		);
	}
	return array(
		'ok'              => true,
		'type'            => 'series_automation',
		'ready_to_import' => $ready,
		'input'           => array(
			'tmdb_id'          => 100,
			'title'            => 'Test',
			'summary'          => 'S',
			'series_directory' => 'series/korea/2024/Show',
		),
		'metadata_plan'   => array(
			'ok'              => true,
			'ready_to_import' => $ready,
			'series'          => array(
				'tmdb_id' => 100,
				'title'   => 'Test',
				'summary' => 'S',
			),
			'seasons'         => array(
				array(
					'season_number' => '1',
					'episodes'      => $episodes,
				),
			),
			'images'          => array(),
			'sources_policy'  => array(
				'mutate'  => false,
				'actions' => array(),
			),
			'identity'        => array(
				'action' => 'create',
			),
		),
		'media'           => array(
			'ok'       => true,
			'kind'     => 'series',
			'ready'    => true,
			'episodes' => array(
				array(
					'season_number'  => '1',
					'episode_number' => '1',
					'sources'        => array( array( 'media_path' => 'series/korea/2024/Show/a.mkv' ) ),
					'subtitles'      => array(),
				),
			),
		),
	);
}

function job_hooks( array &$calls, ?callable $persist = null ): array {
	global $scheduled;
	return array(
		'apply_series'        => static function () use ( &$calls ): array {
			++$calls['series'];
			return array( 'ok' => true, 'series_id' => 40, 'action' => 'create' );
		},
		'apply_people'        => static function () use ( &$calls ): array {
			++$calls['people'];
			return array( 'ok' => true, 'warnings' => array( array( 'code' => 'person_skip', 'message' => 'already exists' ) ) );
		},
		'apply_images'        => static function () use ( &$calls ): array {
			++$calls['images'];
			return array( 'ok' => true, 'continue' => true, 'images' => array() );
		},
		'persist_episode'     => $persist ?? static function ( $series_id, array $plan ) use ( &$calls ): array {
			++$calls['episodes'];
			return array(
				'ok'             => true,
				'episode_id'     => 800 + (int) ( $plan['episode_number'] ?? 0 ),
				'season_number'  => '1',
				'episode_number' => (int) ( $plan['episode_number'] ?? 0 ),
			);
		},
		'apply_seasons'       => static function () use ( &$calls ): array {
			++$calls['season'];
			return array( 'ok' => true, 'seasons' => array() );
		},
		'media_preview_build' => static function ( array $input, array $opts = array() ) use ( &$calls ): array {
			++$calls['rematch'];
			$calls['reused_scan'] = isset( $opts['scan'] );
			unset( $input );
			return array(
				'ok'       => true,
				'episodes' => array(
					array(
						'episode_id'     => 801,
						'season_number'  => '1',
						'episode_number' => '1',
						'sources'        => array(),
					),
				),
			);
		},
		'media_plan_build'    => static function (): array {
			return array(
				'ok'              => true,
				'ready_to_import' => true,
				'type'            => 'series_media',
				'contract'        => array(
					'kind'    => 'series_media_import_plan',
					'version' => 1,
				),
				'identity'        => array(
					'tvshow_id'        => 40,
					'series_directory' => 'series/korea/2024/Show',
				),
				'episodes'        => array(),
			);
		},
		'media_import_execute'=> static function () use ( &$calls ): array {
			++$calls['media'];
			return array(
				'ok'        => true,
				'tvshow_id' => 40,
				'completed' => 1,
				'episodes'  => array(),
			);
		},
		'schedule_action'     => static function () use ( &$scheduled ) {
			$scheduled[] = true;
			return true;
		},
		'unschedule_action'   => static function () use ( &$calls ) {
			++$calls['unschedule'];
			return true;
		},
	);
}

echo "Series import snapshot store\n";
Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();

$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload(), array( 'user_id' => 9, 'blog_id' => 1, 'now' => 1000 ) );
job_assert( is_array( $created ) && isset( $created['token'] ), 'snapshot creation returns raw token' );
$loaded = Movies_WP_Series_Import_Snapshot_Store::load_valid( $created['token'], array( 'user_id' => 9, 'blog_id' => 1, 'now' => 1001 ) );
job_assert( is_array( $loaded ), 'valid token loads snapshot' );
job_assert( $created['token'] !== ( $loaded['token_hash'] ?? '' ), 'raw token is not stored as hash' );
job_same(
	Movies_WP_Series_Import_Snapshot_Store::hash_token( $created['token'] ),
	$loaded['token_hash'],
	'snapshot token is stored hashed'
);

$expired = Movies_WP_Series_Import_Snapshot_Store::load_valid( $created['token'], array( 'user_id' => 9, 'blog_id' => 1, 'now' => 1000 + 2000 ) );
job_same( 'series_import_snapshot_expired', $expired->get_error_code(), 'expired snapshot is rejected' );

$user = Movies_WP_Series_Import_Snapshot_Store::load_valid( $created['token'], array( 'user_id' => 8, 'blog_id' => 1, 'now' => 1001 ) );
job_same( 'series_import_snapshot_user_mismatch', $user->get_error_code(), 'user binding is enforced' );

$site = Movies_WP_Series_Import_Snapshot_Store::load_valid( $created['token'], array( 'user_id' => 9, 'blog_id' => 2, 'now' => 1001 ) );
job_same( 'series_import_snapshot_site_mismatch', $site->get_error_code(), 'site binding is enforced' );

echo "Series import enqueue\n";
Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload(), array( 'user_id' => 9, 'blog_id' => 1 ) );
$scheduled = array();
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 9, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () use ( &$scheduled ) {
			$scheduled[] = Movies_WP_Series_Import_Job_Runner::HOOK;
			return true;
		},
	)
);
job_assert( is_array( $job ) && ! empty( $job['enqueued'] ), 'job is created and enqueued' );
job_same( 'queued', $job['status'], 'new job starts queued' );
job_same( 1, count( $scheduled ), 'Action Scheduler schedule is invoked' );
job_same( Movies_WP_Series_Import_Job_Runner::GROUP, 'movies-wp-series-import', 'uses dedicated AS group' );

$unavailable = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 9, 'blog_id' => 1 ),
	array()
);
job_assert( is_wp_error( $unavailable ), 'missing Action Scheduler is an error' );
job_same( 'series_import_job_duplicate', $unavailable->get_error_code(), 'active job blocks a second enqueue for the same snapshot' );

Movies_WP_Series_Import_Job_Store::reset_memory();
$unavailable = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 9, 'blog_id' => 1 ),
	array()
);
job_same( 'series_import_action_scheduler_unavailable', $unavailable->get_error_code(), 'Action Scheduler unavailable does not run synchronously' );

$not_ready = Movies_WP_Series_Import_Job_Runner::validate_snapshot_payload( job_payload( array(), false ) );
job_same( 'series_automation_not_ready', $not_ready->get_error_code(), 'fake/unready plan is rejected' );

echo "Series import runner phases\n";
Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$calls = array( 'series' => 0, 'people' => 0, 'images' => 0, 'episodes' => 0, 'season' => 0, 'rematch' => 0, 'media' => 0, 'unschedule' => 0, 'reused_scan' => false );
$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload(), array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () {
			return true;
		},
	)
);
$hooks = job_hooks( $calls );
$state = null;
for ( $i = 0; $i < 20; $i++ ) {
	$state = Movies_WP_Series_Import_Job_Runner::run( $job['token'], $hooks );
	if ( is_array( $state ) && 'completed' === ( $state['status'] ?? '' ) ) {
		break;
	}
}
job_same( 'completed', $state['status'] ?? '', 'completed only after all phases' );
job_same( 1, $calls['series'], 'series phase ran once' );
job_same( 1, $calls['people'], 'people phase ran once' );
job_same( 1, $calls['images'], 'images phase ran once' );
job_same( 2, $calls['episodes'], 'each episode is persisted' );
job_same( 1, $calls['season'], 'season phase ran' );
job_same( 1, $calls['media'], 'media phase ran after warnings' );
job_same( true, $calls['reused_scan'], 'rematch reused snapshot scan' );
job_same( 2, (int) ( $state['episode_done'] ?? 0 ), 'episode cursor reaches total' );
job_assert( array() !== ( $state['warnings'] ?? array() ), 'people warning did not stop later phases' );
$again = Movies_WP_Series_Import_Job_Runner::run( $job['token'], $hooks );
job_same( 'completed', $again['status'] ?? '', 'completed jobs are not rerun' );
job_same( 1, $calls['season'], 'season upsert is not repeated after completion' );
job_assert( ! Movies_WP_Series_Import_Invalidation_Coalesce::is_active(), 'coalescing is not left active after a completed worker run' );

echo "Resume after failure and skip existing\n";
Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$calls = array( 'series' => 0, 'people' => 0, 'images' => 0, 'episodes' => 0, 'season' => 0, 'rematch' => 0, 'media' => 0, 'unschedule' => 0, 'reused_scan' => false );
$fail_n = 0;
$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload(), array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () {
			return true;
		},
	)
);
$hooks = job_hooks(
	$calls,
	static function ( $series_id, array $plan ) use ( &$calls, &$fail_n ): array {
		++$calls['episodes'];
		++$fail_n;
		if ( 1 === $fail_n ) {
			return array( 'ok' => false, 'error' => array( 'message' => 'boom' ) );
		}
		return array(
			'ok'             => true,
			'episode_id'     => 900 + (int) $plan['episode_number'],
			'season_number'  => '1',
			'episode_number' => (int) $plan['episode_number'],
		);
	}
);
$state = null;
for ( $i = 0; $i < 8; $i++ ) {
	$state = Movies_WP_Series_Import_Job_Runner::run( $job['token'], $hooks );
	if ( is_array( $state ) && 'failed' === ( $state['status'] ?? '' ) ) {
		break;
	}
}
job_same( 'failed', $state['status'] ?? '', 'episode failure pauses as failed' );
job_same( 0, (int) ( $state['episode_done'] ?? -1 ), 'failed episode is not marked complete' );
job_assert( ! Movies_WP_Series_Import_Invalidation_Coalesce::is_active(), 'coalescing is not left active after a failed worker run' );
$resumed = Movies_WP_Series_Import_Job_Runner::resume( $job['token'], $hooks );
job_same( 'queued', $resumed['status'] ?? '', 'resume requeues the job' );
for ( $i = 0; $i < 20; $i++ ) {
	$state = Movies_WP_Series_Import_Job_Runner::run( $job['token'], $hooks );
	if ( is_array( $state ) && 'completed' === ( $state['status'] ?? '' ) ) {
		break;
	}
}
job_same( 'completed', $state['status'] ?? '', 'job completes after resume' );

$skip_payload = job_payload(
	array(
		array(
			'action'              => 'update',
			'existing_episode_id' => 724,
			'season_number'       => '1',
			'episode_number'      => 1,
			'name'                => 'Existing',
		),
	)
);
Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$calls = array( 'series' => 0, 'people' => 0, 'images' => 0, 'episodes' => 0, 'season' => 0, 'rematch' => 0, 'media' => 0, 'unschedule' => 0, 'reused_scan' => false );
$created = Movies_WP_Series_Import_Snapshot_Store::create( $skip_payload, array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () {
			return true;
		},
	)
);
$hooks = job_hooks( $calls );
for ( $i = 0; $i < 20; $i++ ) {
	$state = Movies_WP_Series_Import_Job_Runner::run( $job['token'], $hooks );
	if ( is_array( $state ) && in_array( ( $state['status'] ?? '' ), array( 'completed', 'failed' ), true ) ) {
		break;
	}
}
job_same( 0, $calls['episodes'], 'existing episodes are skipped' );
job_same( 724, (int) ( $state['last_episode_id'] ?? 0 ), 'skipped existing episode id is recorded' );
job_same( 'completed', $state['status'] ?? '', 'skip existing still reaches completed' );

$reuse_series = $skip_payload;
$reuse_series['metadata_plan']['identity']['action'] = 'update';
$reuse_series['metadata_plan']['identity']['existing_series_id'] = 31;
$calls = array( 'series' => 0, 'people' => 0, 'images' => 0, 'episodes' => 0, 'season' => 0, 'rematch' => 0, 'media' => 0, 'unschedule' => 0, 'reused_scan' => false );
Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$created = Movies_WP_Series_Import_Snapshot_Store::create( $reuse_series, array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () {
			return true;
		},
	)
);
$hooks = job_hooks( $calls );
$hooks['apply_series'] = static function ( array $plan ) use ( &$calls ): array {
	++$calls['series'];
	job_same( 'update', $plan['identity']['action'], 'existing series uses update identity' );
	job_same( 31, (int) $plan['identity']['existing_series_id'], 'existing series id is reused' );
	return array( 'ok' => true, 'series_id' => 31, 'action' => 'update' );
};
for ( $i = 0; $i < 20; $i++ ) {
	$state = Movies_WP_Series_Import_Job_Runner::run( $job['token'], $hooks );
	if ( is_array( $state ) && 'completed' === ( $state['status'] ?? '' ) ) {
		break;
	}
}
job_same( 31, (int) ( $state['series_id'] ?? 0 ), 'job reuses existing series id' );

echo "Cancel and concurrency\n";
Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$calls = array( 'series' => 0, 'people' => 0, 'images' => 0, 'episodes' => 0, 'season' => 0, 'rematch' => 0, 'media' => 0, 'unschedule' => 0, 'reused_scan' => false );
$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload(), array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () {
			return true;
		},
	)
);
$cancelled = Movies_WP_Series_Import_Job_Runner::cancel( $job['token'], job_hooks( $calls ) );
job_same( 'paused', $cancelled['status'] ?? '', 'cancel marks job paused' );
job_same( 1, $calls['unschedule'], 'cancel unschedules future AS work' );
job_assert( ! isset( $cancelled['deleted_catalog'] ), 'cancel does not delete catalog data' );

Movies_WP_Series_Import_Job_Store::update( $job['token'], array( 'status' => 'running', 'claimed_until' => gmdate( 'Y-m-d H:i:s', time() + 60 ) ) );
$busy = Movies_WP_Series_Import_Job_Runner::run( $job['token'], job_hooks( $calls ) );
job_same( 'series_import_job_busy', $busy->get_error_code(), 'concurrent execution of the same job is rejected' );
Movies_WP_Series_Import_Job_Runner::handle( $job['token'] );
$after_handle = Movies_WP_Series_Import_Job_Store::find_by_token( $job['token'] );
job_same( 'running', $after_handle['status'] ?? '', 'duplicate AS handle does not mark a busy job failed' );

job_same( 4, Movies_WP_Series_Import_Job_Runner::episode_chunk_size(), 'default episode chunk size is 4' );

echo "Atomic claim, lease, enqueue, and scheduling\n";
Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload(), array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () {
			return true;
		},
	)
);
$claimed = Movies_WP_Series_Import_Job_Store::claim( $job['token'], array( 'now' => 1000 ) );
job_assert( is_array( $claimed ) && '' !== (string) ( $claimed['claim_token'] ?? '' ), 'atomic claim issues a claim token' );
$competitor = Movies_WP_Series_Import_Job_Store::claim( $job['token'], array( 'now' => 1000 ) );
job_assert( null === $competitor, 'second worker cannot claim while the lease is valid' );
job_assert(
	Movies_WP_Series_Import_Job_Store::heartbeat( $job['token'], (string) $claimed['claim_token'], array( 'now' => 1100 ) ),
	'owning worker can renew the lease'
);
job_assert(
	Movies_WP_Series_Import_Job_Store::heartbeat( $job['token'], (string) $claimed['claim_token'], array( 'now' => 1100 ) ),
	'same-second heartbeat still counts as owning the lease'
);
$during_renewal = Movies_WP_Series_Import_Job_Store::claim( $job['token'], array( 'now' => 1200 ) );
job_assert( null === $during_renewal, 'another worker cannot claim while the lease is being renewed' );
$recovered = Movies_WP_Series_Import_Job_Store::claim( $job['token'], array( 'now' => 1100 + 181 ) );
job_assert( is_array( $recovered ), 'expired claim can be recovered' );
job_assert( (string) $recovered['claim_token'] !== (string) $claimed['claim_token'], 'recovery issues a new claim token' );

$dup = Movies_WP_Series_Import_Job_Store::create(
	array(
		'user_id'     => 1,
		'blog_id'     => 1,
		'snapshot_id' => (int) $created['id'],
		'tmdb_id'     => 100,
	)
);
job_same( 'series_import_job_duplicate', $dup->get_error_code(), 'unique active slot rejects a second active job for the same snapshot' );

$pending = 0;
$sched_opts = array(
	'schedule_action'   => static function () use ( &$pending ) {
		++$pending;
		return true;
	},
	'unschedule_action' => static function () use ( &$pending ) {
		$pending = 0;
	},
);
Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload(), array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	$sched_opts
);
job_same( 1, $pending, 'enqueue leaves one pending action' );
Movies_WP_Series_Import_Job_Runner::resume( $job['token'], $sched_opts );
Movies_WP_Series_Import_Job_Runner::resume( $job['token'], $sched_opts );
job_same( 1, $pending, 'resume does not stack duplicate pending actions' );

Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload(), array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () {
			return false;
		},
	)
);
job_same( 'series_import_schedule_failed', $job->get_error_code(), 'initial enqueue scheduling failure is explicit' );

echo "Episode cursor, live identity, and partial media\n";
Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$calls = array( 'series' => 0, 'people' => 0, 'images' => 0, 'episodes' => 0, 'season' => 0, 'rematch' => 0, 'media' => 0, 'unschedule' => 0, 'reused_scan' => false );
$seen_done = array();
$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload(), array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () {
			return true;
		},
	)
);
$hooks = job_hooks(
	$calls,
	static function ( $series_id, array $plan ) use ( &$calls, &$seen_done, $job ): array {
		$row = Movies_WP_Series_Import_Job_Store::find_by_token( $job['token'] );
		$seen_done[] = (int) ( $row['episode_done'] ?? 0 );
		++$calls['episodes'];
		return array(
			'ok'             => true,
			'episode_id'     => 800 + (int) ( $plan['episode_number'] ?? 0 ),
			'season_number'  => '1',
			'episode_number' => (int) ( $plan['episode_number'] ?? 0 ),
		);
	}
);
$state = null;
for ( $i = 0; $i < 20; $i++ ) {
	$state = Movies_WP_Series_Import_Job_Runner::run( $job['token'], $hooks );
	if ( is_array( $state ) && 'completed' === ( $state['status'] ?? '' ) ) {
		break;
	}
}
job_same( array( 0, 1 ), $seen_done, 'each successful episode persists progress before the next episode' );
job_same( 2, (int) ( $state['episode_done'] ?? 0 ), 'chunk completes with persisted episode cursor' );

Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$calls = array( 'series' => 0, 'people' => 0, 'images' => 0, 'episodes' => 0, 'season' => 0, 'rematch' => 0, 'media' => 0, 'unschedule' => 0, 'reused_scan' => false );
$catalog = array();
$creates = 0;
$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload( array( array(
	'action'         => 'create',
	'season_number'  => '1',
	'episode_number' => 1,
	'tmdb_id'        => 9001,
	'name'           => 'One',
) ) ), array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () {
			return true;
		},
	)
);
$hooks = job_hooks(
	$calls,
	static function ( $series_id, array $plan ) use ( &$calls, &$catalog, &$creates ): array {
		++$calls['episodes'];
		foreach ( $catalog as $row ) {
			if ( (int) $row['episode_number'] === (int) ( $plan['episode_number'] ?? 0 ) ) {
				return array(
					'ok'             => true,
					'episode_id'     => (int) $row['id'],
					'season_number'  => '1',
					'episode_number' => (int) $row['episode_number'],
					'reused'         => true,
				);
			}
		}
		++$creates;
		$id       = 500 + $creates;
		$catalog[] = array(
			'id'             => $id,
			'episode_number' => (int) ( $plan['episode_number'] ?? 0 ),
		);
		return array(
			'ok'     => false,
			'error'  => array( 'message' => 'meta failed after create' ),
			'episode_id' => $id,
		);
	}
);
$state = null;
for ( $i = 0; $i < 12; $i++ ) {
	$state = Movies_WP_Series_Import_Job_Runner::run( $job['token'], $hooks );
	if ( is_array( $state ) && in_array( ( $state['status'] ?? '' ), array( 'completed', 'failed' ), true ) && 1 === $creates && (int) ( $state['episode_done'] ?? 0 ) === 0 ) {
		if ( 'failed' === $state['status'] ) {
			Movies_WP_Series_Import_Job_Runner::resume( $job['token'], $hooks );
		}
	}
	if ( is_array( $state ) && 'completed' === ( $state['status'] ?? '' ) ) {
		break;
	}
}
job_same( 1, $creates, 'retry after partial create does not call create again' );
job_same( 1, count( $catalog ), 'exactly one episode remains after retry' );
job_same( 'completed', $state['status'] ?? '', 'retry finishes the existing episode row' );

Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$calls = array( 'series' => 0, 'people' => 0, 'images' => 0, 'episodes' => 0, 'season' => 0, 'rematch' => 0, 'media' => 0, 'unschedule' => 0, 'reused_scan' => false );
$creates = 0;
$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload( array( array(
	'action'         => 'create',
	'season_number'  => '1',
	'episode_number' => 1,
	'tmdb_id'        => 9001,
	'name'           => 'One',
) ) ), array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () {
			return true;
		},
	)
);
$hooks = job_hooks(
	$calls,
	static function ( $series_id, array $plan, array $opts = array() ) use ( &$calls, &$creates ): array {
		unset( $series_id );
		++$calls['episodes'];
		$retry = absint( $plan['retry_created_episode_id'] ?? 0 );
		if ( $retry > 0 ) {
			return array(
				'ok'             => true,
				'action'         => 'update',
				'episode_id'     => $retry,
				'season_number'  => '1',
				'episode_number' => (int) ( $plan['episode_number'] ?? 0 ),
			);
		}
		++$creates;
		$remembered_ok = null;
		if ( isset( $opts['remember_created_episode'] ) && is_callable( $opts['remember_created_episode'] ) ) {
			$remembered_ok = call_user_func( $opts['remember_created_episode'], 70 );
		}
		if ( true !== $remembered_ok ) {
			return array(
				'ok'         => false,
				'episode_id' => 70,
				'error'      => array( 'code' => 'series_tv_adapter_episode_remember_failed', 'message' => 'remember failed' ),
			);
		}
		return array(
			'ok'         => false,
			'episode_id' => 70,
			'error'      => array( 'message' => 'crash after remember' ),
		);
	}
);
$state = null;
for ( $i = 0; $i < 12; $i++ ) {
	$state = Movies_WP_Series_Import_Job_Runner::run( $job['token'], $hooks );
	if ( is_array( $state ) && 'failed' === ( $state['status'] ?? '' ) && 1 === $creates ) {
		$pending = $state['result']['pending_created_episode'] ?? array();
		job_same( 70, (int) ( $pending['episode_id'] ?? 0 ), 'job stores created episode id before episode_done advances' );
		job_same( 0, (int) ( $state['episode_done'] ?? -1 ), 'remembered id does not advance episode_done' );
		job_assert( 'completed' !== ( $state['status'] ?? '' ), 'remembered pending id does not complete the job' );
		Movies_WP_Series_Import_Job_Runner::resume( $job['token'], $hooks );
	}
	if ( is_array( $state ) && 'completed' === ( $state['status'] ?? '' ) ) {
		break;
	}
}
job_same( 1, $creates, 'job retry after remembered create does not create again' );
job_same( 'completed', $state['status'] ?? '', 'job retry after remembered create completes' );
job_same( 70, (int) ( $state['last_episode_id'] ?? 0 ), 'job retry reuses the remembered episode id' );

Movies_WP_Series_Import_Snapshot_Store::reset_memory();
Movies_WP_Series_Import_Job_Store::reset_memory();
$calls = array( 'series' => 0, 'people' => 0, 'images' => 0, 'episodes' => 0, 'season' => 0, 'rematch' => 0, 'media' => 0, 'unschedule' => 0, 'reused_scan' => false );
$created = Movies_WP_Series_Import_Snapshot_Store::create( job_payload(), array( 'user_id' => 1, 'blog_id' => 1 ) );
$job = Movies_WP_Series_Import_Job_Runner::enqueue_from_snapshot(
	$created['token'],
	array( 'user_id' => 1, 'blog_id' => 1 ),
	array(
		'schedule_action' => static function () {
			return true;
		},
	)
);
$hooks = job_hooks( $calls );
$hooks['media_import_execute'] = static function () use ( &$calls ): array {
	++$calls['media'];
	return array(
		'ok'        => false,
		'partial'   => true,
		'tvshow_id' => 40,
		'completed' => 0,
		'episodes'  => array(),
		'error'     => array( 'message' => 'partial media' ),
	);
};
$state = null;
for ( $i = 0; $i < 20; $i++ ) {
	$state = Movies_WP_Series_Import_Job_Runner::run( $job['token'], $hooks );
	if ( is_array( $state ) && in_array( ( $state['status'] ?? '' ), array( 'completed', 'failed', 'paused' ), true ) ) {
		break;
	}
}
job_same( 'failed', $state['status'] ?? '', 'partial media cannot reach completed' );
job_assert( 'completed' !== ( $state['phase'] ?? '' ), 'finalize is not marked completed after partial media' );

$adapter = (string) file_get_contents( dirname( __DIR__ ) . '/class-movies-wp-streamit-episode-media-adapter.php' );
job_assert( str_contains( $adapter, 'function merge_source_rows' ), 'media merge-by-path remains in the episode adapter' );
job_assert( str_contains( $adapter, 'function merge_subtitle_rows' ), 'subtitle merge-by-path remains in the episode adapter' );

$mismatched = job_payload();
$mismatched['metadata_plan']['series']['tmdb_id'] = 999;
job_same(
	'series_import_snapshot_identity',
	Movies_WP_Series_Import_Job_Runner::validate_snapshot_payload( $mismatched )->get_error_code(),
	'browser-altered plan identity is rejected'
);

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series import job tests passed.\n";
exit( $failures ? 1 : 0 );

<?php
/**
 * Movie import job store/runner: same-second claim + heartbeat, stall detection, and Resume.
 *
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-movie-import-job-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-movie-import-job-test/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
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
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'maybe_serialize' ) ) {
	function maybe_serialize( $data ) {
		return ( is_array( $data ) || is_object( $data ) ) ? serialize( $data ) : $data;
	}
}

$options = array();
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $options;
		return $options[ $name ] ?? $default;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
		global $options;
		unset( $deprecated, $autoload );
		if ( array_key_exists( $name, $options ) ) {
			return false;
		}
		$options[ $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	// Mirrors core: returns false when the new value equals the stored value.
	function update_option( $name, $value, $autoload = null ) {
		global $options;
		unset( $autoload );
		if ( array_key_exists( $name, $options ) && maybe_serialize( $options[ $name ] ) === maybe_serialize( $value ) ) {
			return false;
		}
		$options[ $name ] = $value;
		return true;
	}
}

if ( ! class_exists( 'Movies_WP_Media_Import_Service' ) ) {
	class Movies_WP_Media_Import_Service {
		public static $calls = 0;
		public static function execute( array $request ) {
			unset( $request );
			++self::$calls;
			return array(
				'ok'      => true,
				'message' => 'Imported.',
			);
		}
		public static function safe_text( $text ) {
			return (string) $text;
		}
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-movie-import-job-runner.php';

$failures = 0;
function job_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

$now     = 1760208372;
$context = array(
	'now'     => $now,
	'user_id' => 0,
	'blog_id' => 1,
);
$request = array(
	'tmdb_id'         => 882580,
	'title'           => 'Christmas Carol',
	'media_directory' => 'Movie/Korea/2022/Christmas.Carol',
);

echo "Movie import job store\n";
$job = Movies_WP_Movie_Import_Job_Store::create( $request, $context );
job_assert( is_array( $job ), 'create returns a job' );
$token   = (string) $job['token'];
$claimed = Movies_WP_Movie_Import_Job_Store::claim( $token, $context );
job_assert( is_array( $claimed ) && 'running' === $claimed['status'], 'claim marks the job running' );
job_assert(
	true === Movies_WP_Movie_Import_Job_Store::heartbeat( $token, (string) $claimed['claim_token'], $context ),
	'heartbeat in the same second as claim succeeds'
);
job_assert(
	false === Movies_WP_Movie_Import_Job_Store::heartbeat( $token, 'not-the-claim-token', $context ),
	'heartbeat with a foreign claim token still fails'
);
job_assert( false === Movies_WP_Movie_Import_Job_Store::update( 'missing-token', array( 'status' => 'failed' ), $context ), 'update of a missing job fails' );

echo "\nMovie import job runner\n";
$job    = Movies_WP_Movie_Import_Job_Store::create( $request, $context );
$token  = (string) $job['token'];
$result = Movies_WP_Movie_Import_Job_Runner::run( $token, array( 'now' => $now ) );
job_assert( is_array( $result ), 'run returns the job, not a busy error' );
$result = is_array( $result ) ? $result : array( 'claim_token' => 'busy' );
job_assert( 'completed' === (string) ( $result['status'] ?? '' ), 'same-second claim + heartbeat completes the import' );
job_assert( 1 === Movies_WP_Media_Import_Service::$calls, 'import service executed exactly once' );
job_assert( empty( $result['claim_token'] ), 'completed job releases its claim' );

echo "\nstall detection and resume eligibility\n";
$job   = Movies_WP_Movie_Import_Job_Store::create( $request, $context );
$token = (string) $job['token'];
Movies_WP_Movie_Import_Job_Store::claim( $token, $context );
$running   = Movies_WP_Movie_Import_Job_Store::find_by_token( $token );
$recovery  = Movies_WP_Movie_Import_Job_Store::stall_recovery_seconds();
job_assert( $recovery > 300, 'recovery threshold outlasts one worker run (300s)' );
job_assert( false === Movies_WP_Movie_Import_Job_Store::is_possibly_stalled( $running, array( 'now' => $now + 60 ) ), 'fresh running job is not stalled' );
job_assert( false === Movies_WP_Movie_Import_Job_Store::is_resume_eligible_stall( $running, array( 'now' => $now + 300 ) ), 'running job within one worker run is not Resume-eligible' );
job_assert( true === Movies_WP_Movie_Import_Job_Store::is_possibly_stalled( $running, array( 'now' => $now + $recovery + 1 ) ), 'silent running job is possibly stalled' );
job_assert( true === Movies_WP_Movie_Import_Job_Store::is_resume_eligible_stall( $running, array( 'now' => $now + $recovery + 1 ) ), 'silent running job past recovery is Resume-eligible' );

$queued = array(
	'status'     => 'queued',
	'updated_at' => gmdate( 'Y-m-d H:i:s', $now ),
);
job_assert( false === Movies_WP_Movie_Import_Job_Store::is_possibly_stalled( $queued, array( 'now' => $now + 60 ) ), 'recently queued job is not stalled' );
job_assert( true === Movies_WP_Movie_Import_Job_Store::is_possibly_stalled( $queued, array( 'now' => $now + Movies_WP_Movie_Import_Job_Store::lease_seconds() + 1 ) ), 'queued job never picked up is possibly stalled' );
job_assert( false === Movies_WP_Movie_Import_Job_Store::is_resume_eligible_stall( $queued, array( 'now' => $now + Movies_WP_Movie_Import_Job_Store::lease_seconds() + 1 ) ), 'queued soft stall is not Resume-eligible yet' );
job_assert( true === Movies_WP_Movie_Import_Job_Store::is_resume_eligible_stall( $queued, array( 'now' => $now + $recovery + 1 ) ), 'queued job past recovery is Resume-eligible' );
foreach ( array( 'completed', 'failed', 'paused' ) as $terminal ) {
	$row = array(
		'status'     => $terminal,
		'updated_at' => gmdate( 'Y-m-d H:i:s', $now ),
	);
	job_assert( false === Movies_WP_Movie_Import_Job_Store::is_resume_eligible_stall( $row, array( 'now' => $now + 99999 ) ), "{$terminal} is never a stall" );
}

echo "\nresume\n";
$scheduled     = array();
$resume_opts   = static function ( $at ) use ( &$scheduled ) {
	return array(
		'now'               => $at,
		'schedule_action'   => static function ( $ts, $hook, $args, $group ) use ( &$scheduled ) {
			unset( $ts );
			$scheduled[] = array( $hook, $args, $group );
			return 123;
		},
		'unschedule_action' => static function () {},
	);
};

$busy = Movies_WP_Movie_Import_Job_Runner::resume( $token, $resume_opts( $now + 60 ) );
job_assert( is_wp_error( $busy ) && 'movie_import_job_busy' === $busy->get_error_code(), 'resume refuses a running job with recent activity' );
$after = Movies_WP_Movie_Import_Job_Store::find_by_token( $token );
job_assert( 'running' === (string) $after['status'] && ! empty( $after['claim_token'] ), 'refused resume leaves the job running' );
job_assert( array() === $scheduled, 'refused resume schedules nothing' );

$resumed = Movies_WP_Movie_Import_Job_Runner::resume( $token, $resume_opts( $now + $recovery + 1 ) );
job_assert( is_array( $resumed ) && 'queued' === (string) $resumed['status'], 'hard-stalled resume re-queues the job' );
job_assert( is_array( $resumed ) && empty( $resumed['claim_token'] ) && empty( $resumed['claimed_until'] ), 'resume clears the dead claim' );
job_assert( 1 === count( $scheduled ) && array( $token ) === $scheduled[0][1], 'resume schedules one action for this job' );

Movies_WP_Movie_Import_Job_Store::update( $token, array( 'status' => 'failed' ), $context );
$scheduled = array();
$retry     = Movies_WP_Movie_Import_Job_Runner::resume( $token, $resume_opts( $now + $recovery + 2 ) );
job_assert( is_array( $retry ) && 'queued' === (string) $retry['status'] && 1 === count( $scheduled ), 'failed job can be resumed' );

Movies_WP_Movie_Import_Job_Store::update( $token, array( 'status' => 'completed' ), $context );
$scheduled = array();
$done      = Movies_WP_Movie_Import_Job_Runner::resume( $token, $resume_opts( $now + $recovery + 3 ) );
job_assert( is_array( $done ) && 'completed' === (string) $done['status'] && array() === $scheduled, 'completed job is not re-run' );

Movies_WP_Movie_Import_Job_Store::update( $token, array( 'status' => 'paused' ), $context );
$no_as  = $resume_opts( $now + $recovery + 4 );
$no_as['schedule_action'] = static function () {
	return new WP_Error( 'movie_import_action_scheduler_unavailable', 'Action Scheduler is not available.' );
};
$fail = Movies_WP_Movie_Import_Job_Runner::resume( $token, $no_as );
$row  = Movies_WP_Movie_Import_Job_Store::find_by_token( $token );
job_assert( is_wp_error( $fail ) && 'failed' === (string) $row['status'], 'scheduling failure marks the job failed instead of leaving it queued' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Movie import job tests passed.\n";
exit( $failures ? 1 : 0 );

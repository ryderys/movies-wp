<?php
/**
 * CLI tests for Series import Recent Imports listing, stall detection, and progress helpers.
 *
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-import-job-monitor-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-import-job-monitor-test/' );
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
		return substr( hash( 'sha256', 'movies-wp-monitor-test-' . $n ), 0, (int) $length );
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}
if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( $from, $to = 0 ) {
		$to = $to ? (int) $to : time();
		$diff = max( 0, (int) $to - (int) $from );
		if ( $diff < 60 ) {
			return $diff . ' seconds';
		}
		return (int) floor( $diff / 60 ) . ' minutes';
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-snapshot-store.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-job-store.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-admin.php';

$failures = 0;

function mon_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function mon_same( $expected, $actual, string $label ): void {
	mon_assert( $expected === $actual, $label . ' expected=' . var_export( $expected, true ) . ' got=' . var_export( $actual, true ) );
}

function mon_create_job( array $fields, array $context = array() ): array {
	$job = Movies_WP_Series_Import_Job_Store::create( $fields, $context );
	mon_assert( is_array( $job ) && ! empty( $job['token'] ), 'job created' );
	return is_array( $job ) ? $job : array();
}

Movies_WP_Series_Import_Job_Store::reset_memory();
Movies_WP_Series_Import_Snapshot_Store::reset_memory();

echo "listing scope and order\n";
$base = array(
	'tmdb_id'       => 101,
	'directory'     => 'series/korea/2024/Show.A',
	'snapshot_id'   => 1,
	'episode_total' => 12,
	'result'        => array( 'display_title' => 'Show A' ),
);
$job_a = mon_create_job(
	array_merge( $base, array( 'user_id' => 7, 'blog_id' => 1, 'snapshot_id' => 1 ) ),
	array( 'now' => 1000 )
);
Movies_WP_Series_Import_Job_Store::update(
	$job_a['token'],
	array( 'status' => 'running', 'phase' => 'episodes', 'episode_done' => 3 ),
	array( 'now' => 1300 )
);
$job_b = mon_create_job(
	array_merge( $base, array( 'user_id' => 7, 'blog_id' => 1, 'snapshot_id' => 2, 'directory' => 'series/korea/2024/Show.B', 'result' => array( 'display_title' => 'Show B' ) ) ),
	array( 'now' => 1100 )
);
Movies_WP_Series_Import_Job_Store::update( $job_b['token'], array( 'status' => 'queued' ), array( 'now' => 1400 ) );
$other_user = mon_create_job(
	array_merge( $base, array( 'user_id' => 99, 'blog_id' => 1, 'snapshot_id' => 3, 'result' => array( 'display_title' => 'Other User' ) ) ),
	array( 'now' => 1500 )
);
$other_blog = mon_create_job(
	array_merge( $base, array( 'user_id' => 7, 'blog_id' => 2, 'snapshot_id' => 4, 'result' => array( 'display_title' => 'Other Blog' ) ) ),
	array( 'now' => 1600 )
);

$list = Movies_WP_Series_Import_Job_Store::list_for_owner( 7, 1, 10 );
mon_same( 2, count( $list ), 'only current user+blog jobs listed' );
mon_same( 'Show B', Movies_WP_Series_Admin::job_display_title( $list[0] ), 'newest updated_at is first' );
mon_same( 'Show A', Movies_WP_Series_Admin::job_display_title( $list[1] ), 'older updated job is second' );
foreach ( $list as $row ) {
	mon_assert( (int) $row['user_id'] === 7 && (int) $row['blog_id'] === 1, 'listed job stays scoped' );
	mon_assert( ! empty( $row['token'] ), 'listed job has access token for View' );
	mon_assert( ! isset( $row['token_hash'] ) || $row['token'] !== $row['token_hash'], 'View uses opaque token not hash as identity' );
}
$bounded = Movies_WP_Series_Import_Job_Store::list_for_owner( 7, 1, 1 );
mon_same( 1, count( $bounded ), 'list limit is bounded' );
mon_same( 'Show B', Movies_WP_Series_Admin::job_display_title( $bounded[0] ), 'limit keeps newest' );
unset( $other_user, $other_blog );

echo "\nstatus and progress labels\n";
mon_same( 'Queued', Movies_WP_Series_Admin::job_status_label( 'queued' ), 'queued label' );
mon_same( 'Running', Movies_WP_Series_Admin::job_status_label( 'running' ), 'running label' );
mon_same( 'Completed', Movies_WP_Series_Admin::job_status_label( 'completed' ), 'completed label' );
mon_same( 'Failed', Movies_WP_Series_Admin::job_status_label( 'failed' ), 'failed label' );
mon_same( 'Paused', Movies_WP_Series_Admin::job_status_label( 'paused' ), 'paused label' );

$early = array(
	'status'        => 'running',
	'phase'         => 'people',
	'episode_done'  => 0,
	'episode_total' => 12,
);
mon_same( 'Cast and crew', Movies_WP_Series_Admin::job_progress_label( $early ), 'early phase does not fabricate episode progress' );
$episode_phase = array(
	'status'        => 'running',
	'phase'         => 'episodes',
	'episode_done'  => 12,
	'episode_total' => 16,
);
mon_same( '12 / 16 episodes', Movies_WP_Series_Admin::job_progress_label( $episode_phase ), 'episode progress uses done/total' );

echo "\nerror truncation\n";
$long = str_repeat( 'x', 200 );
$failed_job = array(
	'status'     => 'failed',
	'last_error' => $long,
);
$summary = Movies_WP_Series_Admin::job_error_summary( $failed_job, 120 );
$summary_len = function_exists( 'mb_strlen' ) ? mb_strlen( $summary ) : strlen( $summary );
mon_assert( $summary_len <= 120, 'list error summary is truncated' );
mon_assert( str_ends_with( $summary, '…' ), 'truncated error ends with ellipsis' );
mon_same( $long, $failed_job['last_error'], 'full error remains on the job for Progress' );

echo "\nView URL reuses existing token\n";
$url = Movies_WP_Series_Admin::progress_url( $job_a['token'] );
mon_assert( str_contains( $url, 'job_token=' . rawurlencode( $job_a['token'] ) ), 'View URL embeds existing job token' );
mon_assert( str_contains( $url, 'movies-wp-series-automation' ), 'View URL targets Series Automation progress' );

echo "\nstall detection and resume eligibility\n";
$now = 10_000;
$lease = Movies_WP_Series_Import_Job_Store::lease_seconds();
$recovery = Movies_WP_Series_Import_Job_Store::stall_recovery_seconds();

$running_fresh = array(
	'status'        => 'running',
	'claimed_until' => gmdate( 'Y-m-d H:i:s', $now + 60 ),
	'updated_at'    => gmdate( 'Y-m-d H:i:s', $now - 10 ),
);
mon_same( false, Movies_WP_Series_Import_Job_Store::is_possibly_stalled( $running_fresh, array( 'now' => $now ) ), 'healthy running with valid lease is not stalled' );
mon_same( false, Movies_WP_Series_Admin::job_can_resume( $running_fresh, array( 'now' => $now ) ), 'healthy running does not offer Resume' );

$running_lease_near = array(
	'status'        => 'running',
	'claimed_until' => gmdate( 'Y-m-d H:i:s', $now + 5 ),
	'updated_at'    => gmdate( 'Y-m-d H:i:s', $now - 2 ),
);
mon_same( false, Movies_WP_Series_Import_Job_Store::is_possibly_stalled( $running_lease_near, array( 'now' => $now ) ), 'running near lease expiry is not stalled yet' );

$running_expired_recent = array(
	'status'        => 'running',
	'claimed_until' => gmdate( 'Y-m-d H:i:s', $now - 1 ),
	'updated_at'    => gmdate( 'Y-m-d H:i:s', $now - 10 ),
);
mon_same( true, Movies_WP_Series_Import_Job_Store::is_possibly_stalled( $running_expired_recent, array( 'now' => $now ) ), 'expired lease + recent updated_at is soft-stalled' );
mon_same( false, Movies_WP_Series_Import_Job_Store::is_resume_eligible_stall( $running_expired_recent, array( 'now' => $now ) ), 'expired lease + recent updated_at is not Resume-eligible' );
mon_same( false, Movies_WP_Series_Admin::job_can_resume( $running_expired_recent, array( 'now' => $now ) ), 'Resume not offered while updated_at is recent' );

$running_hard = array(
	'status'        => 'running',
	'claimed_until' => gmdate( 'Y-m-d H:i:s', $now - 1 ),
	'updated_at'    => gmdate( 'Y-m-d H:i:s', $now - $recovery - 5 ),
);
mon_same( true, Movies_WP_Series_Import_Job_Store::is_possibly_stalled( $running_hard, array( 'now' => $now ) ), 'hard-stalled running is possibly stalled' );
mon_same( true, Movies_WP_Series_Import_Job_Store::is_resume_eligible_stall( $running_hard, array( 'now' => $now ) ), 'hard-stalled running is Resume-eligible' );
mon_same( true, Movies_WP_Series_Admin::job_can_resume( $running_hard, array( 'now' => $now ) ), 'dead worker after recovery threshold offers Resume' );

$queued_soft = array(
	'status'     => 'queued',
	'updated_at' => gmdate( 'Y-m-d H:i:s', $now - $lease - 5 ),
);
mon_same( true, Movies_WP_Series_Import_Job_Store::is_possibly_stalled( $queued_soft, array( 'now' => $now ) ), 'queued + lease-stale is soft-stalled' );
mon_same( false, Movies_WP_Series_Admin::job_can_resume( $queued_soft, array( 'now' => $now ) ), 'queued soft stall does not offer Resume yet' );

$queued_hard = array(
	'status'     => 'queued',
	'updated_at' => gmdate( 'Y-m-d H:i:s', $now - $recovery - 5 ),
);
mon_same( true, Movies_WP_Series_Admin::job_can_resume( $queued_hard, array( 'now' => $now ) ), 'queued hard stall offers Resume' );

$queued_fresh = array(
	'status'     => 'queued',
	'updated_at' => gmdate( 'Y-m-d H:i:s', $now - 30 ),
);
mon_same( false, Movies_WP_Series_Import_Job_Store::is_possibly_stalled( $queued_fresh, array( 'now' => $now ) ), 'queued with recent updated_at is not stalled' );

foreach ( array( 'completed', 'failed', 'paused' ) as $terminal ) {
	$row = array(
		'status'        => $terminal,
		'updated_at'    => gmdate( 'Y-m-d H:i:s', $now - 3600 ),
		'claimed_until' => gmdate( 'Y-m-d H:i:s', $now - 3600 ),
	);
	mon_same( false, Movies_WP_Series_Import_Job_Store::is_possibly_stalled( $row, array( 'now' => $now ) ), $terminal . ' is never stalled' );
}

mon_same( true, Movies_WP_Series_Admin::job_can_resume( array( 'status' => 'paused' ) ), 'paused offers Resume' );
mon_same( true, Movies_WP_Series_Admin::job_can_resume( array( 'status' => 'failed' ) ), 'failed offers Resume' );

echo "\nheartbeat keeps lease valid\n";
Movies_WP_Series_Import_Job_Store::reset_memory();
$hb_job = mon_create_job(
	array(
		'user_id'       => 3,
		'blog_id'       => 1,
		'tmdb_id'       => 9,
		'directory'     => 'series/x',
		'snapshot_id'   => 90,
		'episode_total' => 1,
	),
	array( 'now' => $now )
);
Movies_WP_Series_Import_Job_Store::update( $hb_job['token'], array( 'status' => 'queued' ), array( 'now' => $now ) );
$claimed = Movies_WP_Series_Import_Job_Store::claim( $hb_job['token'], array( 'now' => $now ) );
mon_assert( is_array( $claimed ) && 'running' === (string) ( $claimed['status'] ?? '' ), 'claim succeeds for heartbeat fixture' );
$claim_token = (string) ( $claimed['claim_token'] ?? '' );
$mid = $now + 90;
mon_assert(
	Movies_WP_Series_Import_Job_Store::heartbeat( $hb_job['token'], $claim_token, array( 'now' => $mid ) ),
	'heartbeat renews during long phase'
);
$after = Movies_WP_Series_Import_Job_Store::find_by_token( $hb_job['token'] );
mon_assert( strtotime( (string) $after['claimed_until'] . ' UTC' ) > $mid, 'heartbeat extends claimed_until past mid-phase' );
mon_same( false, Movies_WP_Series_Import_Job_Store::is_possibly_stalled( $after, array( 'now' => $mid ) ), 'heartbeating long phase is not stalled' );

echo "\nresume refuse soft-stalled running\n";
require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-job-runner.php';
Movies_WP_Series_Import_Job_Store::reset_memory();
$soft = mon_create_job(
	array(
		'user_id'       => 4,
		'blog_id'       => 1,
		'tmdb_id'       => 11,
		'directory'     => 'series/y',
		'snapshot_id'   => 91,
		'episode_total' => 2,
		'result'        => array( 'display_title' => 'Soft' ),
	),
	array( 'now' => $now - 1000 )
);
Movies_WP_Series_Import_Job_Store::update(
	$soft['token'],
	array(
		'status'        => 'running',
		'phase'         => 'media',
		'claimed_until' => gmdate( 'Y-m-d H:i:s', $now - 10 ),
		'episode_done'  => 1,
	),
	array( 'now' => $now - 10 )
);
$soft_row = Movies_WP_Series_Import_Job_Store::find_by_token( $soft['token'] );
$refuse = Movies_WP_Series_Import_Job_Runner::resume(
	$soft['token'],
	array(
		'now'               => $now,
		'schedule_action'   => static function () {
			return 1;
		},
		'unschedule_action' => static function () {
		},
	)
);
mon_assert( is_wp_error( $refuse ), 'resume refuses soft-stalled running job' );
mon_same( 'series_import_job_busy', $refuse->get_error_code(), 'soft-stall resume uses busy code' );
$soft_after = Movies_WP_Series_Import_Job_Store::find_by_token( $soft['token'] );
mon_same( 'running', (string) ( $soft_after['status'] ?? '' ), 'refused resume does not force queued' );
mon_same( 'media', (string) ( $soft_after['phase'] ?? '' ), 'refused resume keeps phase cursor' );
mon_same( 1, (int) ( $soft_after['episode_done'] ?? 0 ), 'refused resume keeps episode cursor' );

echo "\nresume hard-stalled dead worker\n";
Movies_WP_Series_Import_Job_Store::update(
	$soft['token'],
	array(
		'status'        => 'running',
		'claimed_until' => gmdate( 'Y-m-d H:i:s', $now - 50 ),
	),
	array( 'now' => $now - $recovery - 20 )
);
$scheduled = array();
$ok_resume = Movies_WP_Series_Import_Job_Runner::resume(
	$soft['token'],
	array(
		'now'               => $now,
		'schedule_action'   => static function () use ( &$scheduled ) {
			$scheduled[] = 1;
			return 1;
		},
		'unschedule_action' => static function () {
		},
	)
);
mon_assert( is_array( $ok_resume ), 'hard-stalled resume succeeds' );
mon_same( 'queued', (string) ( $ok_resume['status'] ?? '' ), 'hard-stalled resume requeues same job' );
mon_same( 'media', (string) ( $ok_resume['phase'] ?? '' ), 'hard-stalled resume keeps phase' );
mon_same( 1, (int) ( $ok_resume['episode_done'] ?? 0 ), 'hard-stalled resume keeps cursor' );
mon_same( 1, count( $scheduled ), 'hard-stalled resume schedules one AS action' );
mon_same( $soft['token'], (string) ( $ok_resume['token'] ?? $soft['token'] ), 'resume reuses same job token' );

echo "\naccess token survives result updates\n";
Movies_WP_Series_Import_Job_Store::reset_memory();
$token_job = mon_create_job(
	array(
		'user_id'       => 7,
		'blog_id'       => 1,
		'tmdb_id'       => 77,
		'directory'     => 'series/korea/2024/Show.A',
		'snapshot_id'   => 701,
		'episode_total' => 2,
		'result'        => array( 'display_title' => 'Show A' ),
	),
	array( 'now' => 1000 )
);
Movies_WP_Series_Import_Job_Store::update(
	$token_job['token'],
	array(
		'result' => array(
			'display_title' => 'Show A Updated',
			'series_id'     => 55,
		),
	),
	array( 'now' => 1700 )
);
$relisted = Movies_WP_Series_Import_Job_Store::list_for_owner( 7, 1, 10 );
$found_a  = null;
foreach ( $relisted as $row ) {
	if ( (string) ( $row['token'] ?? '' ) === (string) $token_job['token'] ) {
		$found_a = $row;
		break;
	}
}
mon_assert( is_array( $found_a ), 'updated job still listed' );
mon_same( $token_job['token'], $found_a['token'] ?? null, 'access token preserved across result updates' );
mon_same( 'Show A Updated', Movies_WP_Series_Admin::job_display_title( $found_a ), 'display title updates' );

echo "\nadmin Resume refusal notice (soft stall)\n";
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}
require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-job-runner.php';
Movies_WP_Series_Import_Job_Store::reset_memory();
$soft_admin = mon_create_job(
	array(
		'user_id'       => 7,
		'blog_id'       => 1,
		'tmdb_id'       => 12,
		'directory'     => 'series/z',
		'snapshot_id'   => 92,
		'episode_total' => 2,
		'result'        => array( 'display_title' => 'Soft Admin' ),
	),
	array( 'now' => $now - 1000 )
);
Movies_WP_Series_Import_Job_Store::update(
	$soft_admin['token'],
	array(
		'status'        => 'running',
		'phase'         => 'media',
		'claimed_until' => gmdate( 'Y-m-d H:i:s', $now - 10 ),
		'episode_done'  => 1,
	),
	array( 'now' => $now - 10 )
);
$admin_redirect = null;
$admin_notice   = null;
$admin_scheduled = 0;
Movies_WP_Series_Admin::handle_mutation_request(
	array(
		'_wpnonce'                               => 'valid',
		Movies_WP_Series_Admin::ACTION_FIELD     => Movies_WP_Series_Admin::RESUME_ACTION,
		'job_token'                              => $soft_admin['token'],
	),
	array(
		'now'               => $now,
		'user_id'           => 7,
		'blog_id'           => 1,
		'current_user_can'  => static function () {
			return true;
		},
		'verify_nonce'      => static function () {
			return true;
		},
		'schedule_action'   => static function () use ( &$admin_scheduled ) {
			++$admin_scheduled;
			return 1;
		},
		'unschedule_action' => static function () {
		},
		'redirect'          => static function ( $url, $status ) use ( &$admin_redirect ): void {
			$admin_redirect = array( 'url' => (string) $url, 'status' => (int) $status );
		},
		'on_notice'         => static function ( $notice ) use ( &$admin_notice ): void {
			$admin_notice = $notice;
		},
	)
);
$soft_admin_after = Movies_WP_Series_Import_Job_Store::find_by_token( $soft_admin['token'] );
mon_assert( is_array( $admin_notice ), 'admin soft-stall Resume surfaces notice' );
mon_same(
	'Import is still running or has not been confirmed stalled. Resume was not started.',
	$admin_notice['message'] ?? null,
	'admin soft-stall notice explains refusal'
);
mon_same( 'running', (string) ( $soft_admin_after['status'] ?? '' ), 'admin soft-stall Resume leaves job running' );
mon_same( 1, (int) ( $soft_admin_after['episode_done'] ?? 0 ), 'admin soft-stall Resume keeps cursor' );
mon_same( 0, $admin_scheduled, 'admin soft-stall Resume schedules no AS action' );
mon_assert( str_contains( (string) ( $admin_redirect['url'] ?? '' ), 'resume_notice=busy' ), 'admin soft-stall redirects with busy notice key' );

echo "\nclear finished Recent Imports\n";
Movies_WP_Series_Import_Job_Store::reset_memory();
$clear_jobs = array();
foreach ( array( 'completed', 'failed', 'running', 'queued', 'paused' ) as $i => $clear_status ) {
	$clear_jobs[ $clear_status ] = mon_create_job(
		array( 'user_id' => 7, 'blog_id' => 1, 'tmdb_id' => 300 + $i, 'directory' => 'series/c' . $i, 'snapshot_id' => 300 + $i, 'episode_total' => 1 ),
		array( 'now' => 1000 + $i )
	);
	Movies_WP_Series_Import_Job_Store::update( $clear_jobs[ $clear_status ]['token'], array( 'status' => $clear_status ), array( 'now' => 1000 + $i ) );
}
$foreign_done = mon_create_job(
	array( 'user_id' => 99, 'blog_id' => 1, 'tmdb_id' => 399, 'directory' => 'series/other', 'snapshot_id' => 399, 'episode_total' => 1 ),
	array( 'now' => 1000 )
);
Movies_WP_Series_Import_Job_Store::update( $foreign_done['token'], array( 'status' => 'completed' ), array( 'now' => 1000 ) );

$clear_redirect = null;
Movies_WP_Series_Admin::handle_mutation_request(
	array(
		'_wpnonce'                           => 'valid',
		Movies_WP_Series_Admin::ACTION_FIELD => Movies_WP_Series_Admin::CLEAR_RECENT_ACTION,
	),
	array(
		'user_id'          => 7,
		'blog_id'          => 1,
		'current_user_can' => static function () {
			return true;
		},
		'verify_nonce'     => static function () {
			return true;
		},
		'redirect'         => static function ( $url ) use ( &$clear_redirect ): void {
			$clear_redirect = (string) $url;
		},
	)
);
$remaining = array_map(
	static function ( $row ) {
		return (string) $row['status'];
	},
	Movies_WP_Series_Import_Job_Store::list_for_owner( 7, 1, 10 )
);
sort( $remaining );
mon_same( array( 'paused', 'queued', 'running' ), $remaining, 'clear removes only completed/failed jobs' );
mon_assert( is_array( Movies_WP_Series_Import_Job_Store::find_by_token( $foreign_done['token'] ) ), 'clear keeps other users\' jobs' );
mon_assert( str_contains( (string) $clear_redirect, 'recent_cleared=2' ), 'clear redirects back with removed count' );

$denied_redirect = null;
$denied_message  = null;
Movies_WP_Series_Admin::handle_mutation_request(
	array(
		'_wpnonce'                           => 'bad',
		Movies_WP_Series_Admin::ACTION_FIELD => Movies_WP_Series_Admin::CLEAR_RECENT_ACTION,
	),
	array(
		'user_id'          => 99,
		'blog_id'          => 1,
		'current_user_can' => static function () {
			return true;
		},
		'verify_nonce'     => static function () {
			return false;
		},
		'wp_die'           => static function ( $message ) use ( &$denied_message ): void {
			$denied_message = (string) $message;
		},
		'redirect'         => static function ( $url ) use ( &$denied_redirect ): void {
			$denied_redirect = (string) $url;
		},
	)
);
mon_assert( null !== $denied_message && null === $denied_redirect, 'clear with invalid nonce is refused' );
mon_assert( is_array( Movies_WP_Series_Import_Job_Store::find_by_token( $foreign_done['token'] ) ), 'refused clear deletes nothing' );

echo "\nactivity label\n";
$activity = Movies_WP_Series_Admin::job_activity_label(
	array( 'updated_at' => gmdate( 'Y-m-d H:i:s', $now - 120 ) ),
	array( 'now' => $now )
);
mon_assert( str_contains( $activity, 'Last activity:' ), 'activity label includes prefix' );
mon_assert( str_contains( $activity, 'ago' ), 'activity label uses relative time when available' );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} Series import job monitor test(s) failed.\n" );
	exit( 1 );
}
echo "\nAll Series import job monitor tests passed.\n";

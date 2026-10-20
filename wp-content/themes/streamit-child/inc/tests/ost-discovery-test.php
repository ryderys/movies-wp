<?php
/**
 * CLI tests for asynchronous OST discovery.
 *
 * Run:
 *   php -n wp-content/themes/streamit-child/inc/tests/ost-discovery-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-ost-discovery-test/' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

$GLOBALS['ost_test_transients'] = array();
$GLOBALS['ost_test_options']    = array();
$GLOBALS['ost_test_filters']    = array();
$GLOBALS['ost_test_actions']    = array();
$GLOBALS['ost_test_schedule']   = array(
	'calls'     => 0,
	'args'      => array(),
	'pending'   => array(),
	'available' => true,
	'next_id'   => 1,
);
$GLOBALS['ost_test_scan']       = array(
	'calls'  => 0,
	'dirs'   => array(),
	'result' => array(
		'ok'     => true,
		'tracks' => array(),
	),
);

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['ost_test_filters'][ $hook ][] = array(
			'callback' => $callback,
			'priority' => (int) $priority,
			'args'     => (int) $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['ost_test_filters'][ $hook ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['ost_test_filters'][ $hook ] as $entry ) {
			$callback = $entry['callback'];
			$n        = max( 1, (int) $entry['args'] );
			$pass     = array_merge( array( $value ), array_slice( $args, 0, $n - 1 ) );
			$value    = call_user_func_array( $callback, $pass );
		}
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['ost_test_actions'][ $hook ][] = array(
			'callback' => $callback,
			'priority' => (int) $priority,
			'args'     => (int) $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return array_key_exists( $key, $GLOBALS['ost_test_transients'] )
			? $GLOBALS['ost_test_transients'][ $key ]
			: false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl = 0 ) {
		unset( $ttl );
		$GLOBALS['ost_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['ost_test_transients'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['ost_test_options'] )
			? $GLOBALS['ost_test_options'][ $key ]
			: $default;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) {
		unset( $deprecated, $autoload );
		if ( array_key_exists( $key, $GLOBALS['ost_test_options'] ) ) {
			return false;
		}
		$GLOBALS['ost_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		$existed = array_key_exists( $key, $GLOBALS['ost_test_options'] );
		unset( $GLOBALS['ost_test_options'][ $key ] );
		return $existed;
	}
}

if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	function wp_using_ext_object_cache() {
		return false;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		unset( $domain );
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		unset( $domain );
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		unset( $domain );
		echo esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'st_get_icon' ) ) {
	function st_get_icon( $name ) {
		return '<span data-icon="' . esc_attr( (string) $name ) . '"></span>';
	}
}

function as_has_scheduled_action( $hook, $args = null, $group = '' ) {
	foreach ( $GLOBALS['ost_test_schedule']['pending'] as $job ) {
		if ( $job['hook'] !== $hook || $job['group'] !== $group ) {
			continue;
		}
		if ( null === $args || $job['args'] === $args ) {
			return true;
		}
	}
	return false;
}

function as_schedule_single_action( $timestamp, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) {
	unset( $timestamp, $unique, $priority );
	$GLOBALS['ost_test_schedule']['calls']++;
	$GLOBALS['ost_test_schedule']['args'][] = array(
		'hook'  => $hook,
		'args'  => $args,
		'group' => $group,
	);
	if ( empty( $GLOBALS['ost_test_schedule']['available'] ) ) {
		return 0;
	}
	if ( as_has_scheduled_action( $hook, $args, $group ) ) {
		return 0;
	}
	$id = (int) $GLOBALS['ost_test_schedule']['next_id']++;
	$GLOBALS['ost_test_schedule']['pending'][] = array(
		'id'    => $id,
		'hook'  => $hook,
		'args'  => $args,
		'group' => $group,
	);
	return $id;
}

require_once dirname( __DIR__ ) . '/ost-download.php';

$failures = 0;

function assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
}

function assert_eq( $expected, $actual, string $label ): void {
	assert_true( $expected === $actual, $label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' );
}

function ost_test_reset(): void {
	$GLOBALS['ost_test_transients'] = array();
	$GLOBALS['ost_test_options']    = array();
	$GLOBALS['ost_test_schedule']   = array(
		'calls'     => 0,
		'args'      => array(),
		'pending'   => array(),
		'available' => true,
		'next_id'   => 1,
	);
	$GLOBALS['ost_test_scan']       = array(
		'calls'  => 0,
		'dirs'   => array(),
		'result' => array(
			'ok'     => true,
			'tracks' => array(),
		),
	);
	$GLOBALS['ost_test_filters']['streamit_child_ost_pre_scan']               = array();
	$GLOBALS['ost_test_filters']['streamit_child_ost_pre_schedule_discovery'] = array();

	add_filter(
		'streamit_child_ost_pre_scan',
		static function ( $pre, $dir ) {
			unset( $pre );
			$GLOBALS['ost_test_scan']['calls']++;
			$GLOBALS['ost_test_scan']['dirs'][] = $dir;
			return $GLOBALS['ost_test_scan']['result'];
		},
		10,
		2
	);
}

class Ost_Discovery_Test_Title {
	/** @var array<string, mixed> */
	private $meta;

	public function __construct( array $meta ) {
		$this->meta = $meta;
	}

	public function get_id() {
		return 42;
	}

	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? null;
	}
}

$series_dir = 'series/korea/2024/Our.Happy.Days';
$track_ok   = array(
	'filename'   => 'theme.mp3',
	'media_path' => $series_dir . '/OST/theme.mp3',
	'size_label' => '3 MB',
);

echo "ost discovery tests\n\n";

// 1) Valid _ost meta: no scan, no schedule.
ost_test_reset();
$st = new Ost_Discovery_Test_Title(
	array(
		'_media_directory' => $series_dir,
		'_ost'             => array(
			array(
				'media_path' => $series_dir . '/OST/theme.mp3',
				'size_label' => '3 MB',
			),
		),
	)
);
$tracks = streamit_child_get_ost_tracks( $st );
assert_eq( 1, count( $tracks ), 'meta hit returns one track' );
assert_eq( 0, $GLOBALS['ost_test_scan']['calls'], 'meta hit does not scan' );
assert_eq( 0, $GLOBALS['ost_test_schedule']['calls'], 'meta hit does not schedule' );

// 2) Cached non-empty transient: no scan, no schedule.
ost_test_reset();
set_transient( streamit_child_ost_result_transient_key( $series_dir ), array( $track_ok ), streamit_child_ost_positive_ttl() );
$st = new Ost_Discovery_Test_Title(
	array(
		'_media_directory' => $series_dir,
		'_ost'             => array(),
	)
);
$tracks = streamit_child_get_ost_tracks( $st );
assert_eq( 1, count( $tracks ), 'positive transient returns tracks' );
assert_eq( 0, $GLOBALS['ost_test_scan']['calls'], 'positive transient does not scan' );
assert_eq( 0, $GLOBALS['ost_test_schedule']['calls'], 'positive transient does not schedule' );

// 3) Cached empty transient: no scan until expiry.
ost_test_reset();
set_transient( streamit_child_ost_result_transient_key( $series_dir ), array(), streamit_child_ost_positive_ttl() );
$tracks = streamit_child_get_ost_tracks( $st );
assert_eq( 0, count( $tracks ), 'empty positive transient returns no tracks' );
assert_eq( 0, $GLOBALS['ost_test_scan']['calls'], 'empty positive transient does not scan' );
assert_eq( 0, $GLOBALS['ost_test_schedule']['calls'], 'empty positive transient does not schedule' );

// 4) Cache miss: immediate empty, schedules at most one job, no scan.
ost_test_reset();
$tracks = streamit_child_get_ost_tracks( $st );
assert_eq( 0, count( $tracks ), 'cache miss returns empty immediately' );
assert_eq( 0, $GLOBALS['ost_test_scan']['calls'], 'cache miss does not call scan client' );
assert_eq( 1, $GLOBALS['ost_test_schedule']['calls'], 'cache miss schedules one job' );
streamit_child_get_ost_tracks( $st );
assert_eq( 1, $GLOBALS['ost_test_schedule']['calls'], 'second miss does not duplicate pending job' );
assert_eq( 1, count( $GLOBALS['ost_test_schedule']['pending'] ), 'only one pending discovery job' );

// 5) Worker success stores tracks; subsequent read has no scan.
ost_test_reset();
$GLOBALS['ost_test_scan']['result'] = array(
	'ok'     => true,
	'tracks' => array( $track_ok ),
);
streamit_child_ost_run_discovery( $series_dir );
assert_eq( 1, $GLOBALS['ost_test_scan']['calls'], 'worker performs one scan' );
$cached = get_transient( streamit_child_ost_result_transient_key( $series_dir ) );
assert_true( is_array( $cached ) && 1 === count( $cached ), 'worker stores validated tracks' );
$tracks = streamit_child_get_ost_tracks( $st );
assert_eq( 1, count( $tracks ), 'read after worker returns tracks' );
assert_eq( 1, $GLOBALS['ost_test_scan']['calls'], 'read after worker does not scan again' );
assert_eq( 0, $GLOBALS['ost_test_schedule']['calls'], 'read after worker does not schedule' );

// 6) Worker failure/timeout: empty result, lock released, negative backoff, no immediate reschedule.
ost_test_reset();
$GLOBALS['ost_test_scan']['result'] = array(
	'ok'     => false,
	'tracks' => array(),
);
streamit_child_ost_run_discovery( $series_dir );
assert_eq( false, get_transient( streamit_child_ost_result_transient_key( $series_dir ) ), 'failure does not write positive result' );
assert_eq( 1, get_transient( streamit_child_ost_failure_transient_key( $series_dir ) ), 'failure writes negative-cache marker' );
assert_eq( false, get_option( streamit_child_ost_lock_key( $series_dir ), false ), 'failure releases lock' );
$tracks = streamit_child_get_ost_tracks( $st );
assert_eq( 0, count( $tracks ), 'failure backoff returns empty' );
assert_eq( 0, $GLOBALS['ost_test_schedule']['calls'], 'failure backoff does not schedule' );

// Exception path.
ost_test_reset();
$GLOBALS['ost_test_filters']['streamit_child_ost_pre_scan'] = array(
	array(
		'callback' => static function () {
			throw new RuntimeException( 'simulated scan boom' );
		},
		'priority' => 10,
		'args'     => 2,
	),
);
streamit_child_ost_run_discovery( $series_dir );
assert_eq( 1, get_transient( streamit_child_ost_failure_transient_key( $series_dir ) ), 'exception writes negative-cache marker' );
assert_eq( false, get_option( streamit_child_ost_lock_key( $series_dir ), false ), 'exception releases lock' );

// 7) Concurrent workers: only one scan.
ost_test_reset();
$GLOBALS['ost_test_scan']['result'] = array(
	'ok'     => true,
	'tracks' => array( $track_ok ),
);
assert_true( streamit_child_ost_acquire_discovery_lock( $series_dir ), 'first lock acquire succeeds' );
streamit_child_ost_run_discovery( $series_dir );
assert_eq( 0, $GLOBALS['ost_test_scan']['calls'], 'second worker skips scan while lock held' );
streamit_child_ost_release_discovery_lock( $series_dir );
streamit_child_ost_run_discovery( $series_dir );
assert_eq( 1, $GLOBALS['ost_test_scan']['calls'], 'scan runs after lock release' );

// 8) Invalid / traversal paths rejected.
ost_test_reset();
$GLOBALS['ost_test_scan']['result'] = array(
	'ok'     => true,
	'tracks' => array(
		array(
			'filename'   => 'evil.mp3',
			'media_path' => $series_dir . '/OST/../secret.mp3',
			'size_label' => '',
		),
		array(
			'filename'   => 'ok.mp3',
			'media_path' => $series_dir . '/OST/ok.mp3',
			'size_label' => '',
		),
		array(
			'filename'   => 'nested.mp3',
			'media_path' => $series_dir . '/OST/nested/ok.mp3',
			'size_label' => '',
		),
	),
);
// Force scan_directory to use tracks_from_scan_files on raw files shape via filter returning ok+raw style.
// Instead call tracks_from_scan_files directly for validation contract, and worker path with pre-normalized tracks.
$validated = streamit_child_ost_tracks_from_scan_files(
	array(
		array(
			'kind'       => 'ost',
			'media_path' => $series_dir . '/OST/../secret.mp3',
		),
		array(
			'kind'       => 'ost',
			'media_path' => $series_dir . '/OST/ok.mp3',
		),
		array(
			'kind'       => 'ost',
			'media_path' => $series_dir . '/OST/nested/ok.mp3',
		),
		array(
			'kind'       => 'video',
			'media_path' => $series_dir . '/OST/skip-me.mp3',
		),
	),
	$series_dir
);
assert_eq( 1, count( $validated ), 'only allowlisted OST basename paths survive' );
assert_eq( $series_dir . '/OST/ok.mp3', $validated[0]['media_path'] ?? null, 'valid OST path kept' );

// Worker stores only pre-validated tracks from scan helper.
$GLOBALS['ost_test_scan']['result'] = array(
	'ok'     => true,
	'tracks' => streamit_child_ost_tracks_from_scan_files(
		array(
			array(
				'kind'       => 'ost',
				'media_path' => $series_dir . '/OST/../secret.mp3',
			),
			array(
				'kind'       => 'ost',
				'media_path' => $series_dir . '/OST/ok.mp3',
			),
		),
		$series_dir
	),
);
streamit_child_ost_run_discovery( $series_dir );
$cached = get_transient( streamit_child_ost_result_transient_key( $series_dir ) );
assert_eq( 1, is_array( $cached ) ? count( $cached ) : -1, 'worker stores only validated tracks' );

// 9) Action Scheduler unavailable: frontend non-blocking.
ost_test_reset();
$GLOBALS['ost_test_schedule']['available'] = false;
$tracks = streamit_child_get_ost_tracks( $st );
assert_eq( 0, count( $tracks ), 'AS unavailable still returns empty' );
assert_eq( 0, $GLOBALS['ost_test_scan']['calls'], 'AS unavailable does not scan on frontend' );
assert_eq( 1, $GLOBALS['ost_test_schedule']['calls'], 'schedule helper attempted once' );
assert_eq( 0, count( $GLOBALS['ost_test_schedule']['pending'] ), 'no pending job when AS returns 0' );

// Also cover pre_schedule short-circuit when AS functions absent path via filter.
ost_test_reset();
add_filter(
	'streamit_child_ost_pre_schedule_discovery',
	static function () {
		return false;
	},
	10,
	2
);
$tracks = streamit_child_get_ost_tracks( $st );
assert_eq( 0, count( $tracks ), 'schedule short-circuit remains non-blocking' );
assert_eq( 0, $GLOBALS['ost_test_scan']['calls'], 'schedule short-circuit does not scan' );

// 10) Empty OST rows: render outputs nothing.
ost_test_reset();
ob_start();
streamit_child_render_ost_download_section( array(), true );
$html = ob_get_clean();
assert_eq( '', $html, 'empty OST rows render no markup' );

// 11) Movie and TV-show integration paths.
ost_test_reset();
if ( ! function_exists( 'movies_wp_user_can_access_media' ) ) {
	function movies_wp_user_can_access_media( $user_id ) {
		unset( $user_id );
		return true;
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}
if ( ! function_exists( 'streamit_child_get_downloadable_sources' ) ) {
	require_once dirname( __DIR__ ) . '/sources-download.php';
}
if ( ! function_exists( 'streamit_child_normalize_subtitles' ) ) {
	require_once dirname( __DIR__ ) . '/subtitles.php';
}
if ( ! function_exists( 'streamit_child_resolve_download_href' ) ) {
	function streamit_child_resolve_download_href( $stored, $post_id, $index = 0 ) {
		return 'https://example.test/dl?post=' . (int) $post_id . '&i=' . (int) $index;
	}
}
if ( ! function_exists( 'streamit_child_resolve_subtitle_url' ) ) {
	function streamit_child_resolve_subtitle_url( $stored, $type = 'v' ) {
		unset( $type );
		return '' === trim( (string) $stored ) ? '' : 'https://example.test/sub';
	}
}
if ( ! function_exists( 'streamit_child_resolve_ost_download_url' ) ) {
	// real function exists from ost-download.php
}

require_once dirname( __DIR__ ) . '/series-download.php';
require_once dirname( __DIR__ ) . '/movie-download.php';

$tv = new Ost_Discovery_Test_Title(
	array(
		'_media_directory' => $series_dir,
		'_ost'             => array(),
		'_seasons'         => array(
			array(
				'name'          => 'Season 1',
				'season_number' => '1',
				'episodes'      => array( 101 ),
			),
		),
	)
);
$episodes = array(
	101 => array(
		'object'    => new class() {
			public function get_id() {
				return 101;
			}
			public function get_permalink() {
				return 'https://example.test/e/101';
			}
			public function get_title() {
				return 'E01';
			}
			public function get_meta( $key ) {
				return null;
			}
		},
		'sources'   => array(
			array(
				'quality'          => '1080p',
				'download_content' => 'series/x/E01.mkv',
				'link'             => 'series/x/E01.mkv',
			),
		),
		'subtitles' => array(),
		'title'     => 'E01',
		'permalink' => 'https://example.test/e/101',
	),
);
// Avoid availability SQL path: call from_data with st_data so OST is included.
$catalog = streamit_child_build_series_download_catalog_from_data(
	array(
		array(
			'name'          => 'Season 1',
			'season_number' => '1',
			'episodes'      => array( 101 ),
		),
	),
	$episodes,
	true,
	$tv
);
assert_true( isset( $catalog['ost'] ) && is_array( $catalog['ost'] ), 'series catalog exposes ost key' );
assert_eq( 0, $GLOBALS['ost_test_scan']['calls'], 'series catalog build does not scan' );
assert_eq( 1, $GLOBALS['ost_test_schedule']['calls'], 'series catalog schedules discovery on miss' );

ost_test_reset();
$movie_dir = 'movie/Film.Title';
$movie     = new Ost_Discovery_Test_Title(
	array(
		'_media_directory' => $movie_dir,
		'_ost'             => array(),
		'_source'          => array(
			array(
				'quality'          => '1080p',
				'link'             => 'movie/Film.Title/Film.1080p.mkv',
				'download_content' => 'movie/Film.Title/Film.1080p.mkv',
			),
		),
	)
);
if ( ! function_exists( 'streamit_child_get_subtitles' ) ) {
	function streamit_child_get_subtitles( $st_data ) {
		unset( $st_data );
		return array();
	}
}
$movie_catalog = streamit_child_build_movie_download_catalog( $movie );
assert_true( isset( $movie_catalog['ost'] ) && is_array( $movie_catalog['ost'] ), 'movie catalog exposes ost key' );
assert_eq( 0, $GLOBALS['ost_test_scan']['calls'], 'movie catalog build does not scan' );
assert_eq( 1, $GLOBALS['ost_test_schedule']['calls'], 'movie catalog schedules discovery on miss' );

// Negative TTL constant policy.
assert_true( streamit_child_ost_negative_ttl() < streamit_child_ost_positive_ttl(), 'negative TTL is shorter than positive TTL' );
assert_eq( 2 * MINUTE_IN_SECONDS, streamit_child_ost_negative_ttl(), 'negative TTL is 2 minutes' );
assert_eq( 15 * MINUTE_IN_SECONDS, streamit_child_ost_positive_ttl(), 'positive TTL remains 15 minutes' );

// Hook registered for Action Scheduler worker.
assert_true( ! empty( $GLOBALS['ost_test_actions'][ STREAMIT_CHILD_OST_DISCOVER_HOOK ] ), 'discover action is registered' );

if ( $failures > 0 ) {
	echo "\n{$failures} ost discovery test(s) failed.\n";
	exit( 1 );
}

echo "\nAll OST discovery tests passed.\n";

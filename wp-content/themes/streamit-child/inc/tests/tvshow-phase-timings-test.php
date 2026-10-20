<?php
/**
 * CLI tests for opt-in TV-show frontend phase timings.
 *
 * Run:
 *   php -n wp-content/themes/streamit-child/inc/tests/tvshow-phase-timings-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-tvshow-phase-timings-test/' );
}

$GLOBALS['tvshow_timing_test_filters'] = array();
$GLOBALS['tvshow_timing_test_logs']    = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['tvshow_timing_test_filters'][ $hook ][] = array(
			'callback' => $callback,
			'args'     => (int) $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['tvshow_timing_test_filters'][ $hook ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['tvshow_timing_test_filters'][ $hook ] as $entry ) {
			$n    = max( 1, (int) $entry['args'] );
			$pass = array_merge( array( $value ), array_slice( $args, 0, $n - 1 ) );
			$value = call_user_func_array( $entry['callback'], $pass );
		}
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {
		unset( $args );
		return true;
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

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) {
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/sources-download.php';
require_once dirname( __DIR__ ) . '/subtitles.php';
require_once dirname( __DIR__ ) . '/series-download.php';

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

function tvshow_timing_capture_logs(): void {
	$GLOBALS['tvshow_timing_test_logs'] = array();
	$GLOBALS['tvshow_timing_test_filters']['streamit_child_tvshow_timing_line'] = array(
		array(
			'callback' => static function ( $line ) {
				if ( is_string( $line ) && '' !== $line ) {
					$GLOBALS['tvshow_timing_test_logs'][] = $line;
				}
				return $line;
			},
			'args'     => 3,
		),
	);
}

echo "tvshow phase timings tests\n\n";

// 1) Disabled by default — no log emission.
streamit_child_tvshow_timing_reset();
tvshow_timing_capture_logs();
assert_true( ! streamit_child_tvshow_timings_enabled(), 'timings disabled when constant unset' );
streamit_child_tvshow_time_begin( 'episode_objects' );
usleep( 1000 );
streamit_child_tvshow_time_end( 'episode_objects' );
assert_eq( 0, count( $GLOBALS['tvshow_timing_test_logs'] ), 'disabled path emits no timing lines' );

// 2) Enabled timers emit fixed phase label and non-negative ms.
if ( ! defined( 'STREAMIT_CHILD_TVSHOW_TIMINGS' ) ) {
	define( 'STREAMIT_CHILD_TVSHOW_TIMINGS', true );
}
streamit_child_tvshow_timing_reset();
tvshow_timing_capture_logs();
assert_true( streamit_child_tvshow_timings_enabled(), 'timings enabled when constant true' );
streamit_child_tvshow_time_begin( 'get_available_seasons' );
usleep( 2000 );
streamit_child_tvshow_time_end( 'get_available_seasons' );
assert_eq( 1, count( $GLOBALS['tvshow_timing_test_logs'] ), 'enabled end emits one line' );
$line = $GLOBALS['tvshow_timing_test_logs'][0];
assert_true( (bool) preg_match( '/^STC_TVSHOW_TIMING phase=get_available_seasons ms=\d+\.\d{2}$/', $line ), 'line uses fixed label and ms format: ' . $line );
assert_true( (bool) preg_match( '/ms=(\d+\.\d{2})$/', $line, $m ), 'ms is parseable' );
assert_true( isset( $m[1] ) && (float) $m[1] >= 0, 'elapsed ms is non-negative' );

// 3) Unmatched / repeated / invalid labels fail safely.
streamit_child_tvshow_timing_reset();
tvshow_timing_capture_logs();
streamit_child_tvshow_time_end( 'download_catalog' );
assert_eq( 0, count( $GLOBALS['tvshow_timing_test_logs'] ), 'unmatched end is a no-op' );

streamit_child_tvshow_time_begin( 'not_a_real_phase' );
streamit_child_tvshow_time_end( 'not_a_real_phase' );
assert_eq( 0, count( $GLOBALS['tvshow_timing_test_logs'] ), 'unknown labels are ignored' );

streamit_child_tvshow_time_begin( 'episode_objects' );
usleep( 500 );
streamit_child_tvshow_time_begin( 'episode_objects' );
usleep( 500 );
streamit_child_tvshow_time_end( 'episode_objects' );
assert_eq( 1, count( $GLOBALS['tvshow_timing_test_logs'] ), 'repeated begin still ends once' );
streamit_child_tvshow_time_end( 'episode_objects' );
assert_eq( 1, count( $GLOBALS['tvshow_timing_test_logs'] ), 'second end after close is ignored' );

// 3b) slider_cards is allowlisted and follows the same opt-in emission rules.
assert_true( in_array( 'slider_cards', streamit_child_tvshow_timing_phases(), true ), 'slider_cards is an allowlisted phase' );
streamit_child_tvshow_timing_reset();
tvshow_timing_capture_logs();
streamit_child_tvshow_time_begin( 'slider_cards' );
usleep( 1000 );
streamit_child_tvshow_time_end( 'slider_cards' );
assert_eq( 1, count( $GLOBALS['tvshow_timing_test_logs'] ), 'slider_cards emits one line when enabled' );
assert_true(
	(bool) preg_match( '/^STC_TVSHOW_TIMING phase=slider_cards ms=\d+\.\d{2}$/', $GLOBALS['tvshow_timing_test_logs'][0] ),
	'slider_cards line uses fixed label format: ' . $GLOBALS['tvshow_timing_test_logs'][0]
);

// 3c) HTTP localization phases are allowlisted; request-start begin is opt-in only.
$http_phases = array( 'bootstrap_to_template', 'header', 'detail_metalist', 'after_morelike', 'footer' );
foreach ( $http_phases as $phase ) {
	assert_true( in_array( $phase, streamit_child_tvshow_timing_phases(), true ), $phase . ' is allowlisted' );
}
assert_true( streamit_child_tvshow_timing_is_tvshow_single( 'tvshow', 'single' ), 'tvshow/single is detected' );
assert_true( ! streamit_child_tvshow_timing_is_tvshow_single( 'movie', 'single' ), 'movie/single is not tvshow single' );

streamit_child_tvshow_timing_reset();
tvshow_timing_capture_logs();
streamit_child_tvshow_time_begin( 'detail_metalist' );
usleep( 1000 );
streamit_child_tvshow_time_end( 'detail_metalist' );
assert_eq( 1, count( $GLOBALS['tvshow_timing_test_logs'] ), 'detail_metalist emits when enabled' );
assert_true(
	(bool) preg_match( '/^STC_TVSHOW_TIMING phase=detail_metalist ms=\d+\.\d{2}$/', $GLOBALS['tvshow_timing_test_logs'][0] ),
	'detail_metalist line format'
);

streamit_child_tvshow_timing_reset();
tvshow_timing_capture_logs();
streamit_child_tvshow_time_begin( 'after_morelike' );
usleep( 1000 );
streamit_child_tvshow_time_end( 'after_morelike' );
assert_true(
	(bool) preg_match( '/^STC_TVSHOW_TIMING phase=after_morelike ms=\d+\.\d{2}$/', $GLOBALS['tvshow_timing_test_logs'][0] ?? '' ),
	'after_morelike line format'
);

streamit_child_tvshow_timing_reset();
tvshow_timing_capture_logs();
$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 0.05;
streamit_child_tvshow_time_begin_from_request( 'bootstrap_to_template' );
streamit_child_tvshow_time_end( 'bootstrap_to_template' );
assert_eq( 1, count( $GLOBALS['tvshow_timing_test_logs'] ), 'bootstrap_to_template emits when REQUEST_TIME_FLOAT set' );
assert_true(
	(bool) preg_match( '/^STC_TVSHOW_TIMING phase=bootstrap_to_template ms=\d+\.\d{2}$/', $GLOBALS['tvshow_timing_test_logs'][0] ),
	'bootstrap_to_template line format'
);
assert_true(
	(bool) preg_match( '/ms=(\d+\.\d{2})$/', $GLOBALS['tvshow_timing_test_logs'][0], $boot_m )
	&& isset( $boot_m[1] )
	&& (float) $boot_m[1] >= 40.0,
	'bootstrap_to_template uses request-start clock'
);

streamit_child_tvshow_timing_reset();
tvshow_timing_capture_logs();
unset( $_SERVER['REQUEST_TIME_FLOAT'] );
streamit_child_tvshow_time_begin_from_request( 'bootstrap_to_template' );
streamit_child_tvshow_time_end( 'bootstrap_to_template' );
assert_eq( 0, count( $GLOBALS['tvshow_timing_test_logs'] ), 'bootstrap_to_template skipped without REQUEST_TIME_FLOAT' );

// 4) Instrumentation helpers do not change catalog/helper return values.
streamit_child_tvshow_timing_reset();
tvshow_timing_capture_logs();

$seasons = array(
	array(
		'name'          => 'Season 1',
		'season_number' => '1',
		'episodes'      => array( 101, 102 ),
	),
);
$episodes = array(
	101 => array(
		'title'          => 'E01',
		'season_number'  => '1',
		'episode_number' => 'E01',
		'sources'        => array(
			array(
				'quality'          => '1080p',
				'download_content' => 'series/x/E01.mkv',
				'link'             => 'series/x/E01.mkv',
			),
		),
		'subtitles'      => array(),
	),
	102 => array(
		'title'          => 'E02',
		'season_number'  => '1',
		'episode_number' => 'E02',
		'sources'        => array(
			array(
				'quality'          => '1080p',
				'download_content' => 'series/x/E02.mkv',
				'link'             => 'series/x/E02.mkv',
			),
		),
		'subtitles'      => array(),
	),
);

streamit_child_tvshow_time_begin( 'download_catalog' );
$catalog_a = streamit_child_build_series_download_catalog_from_data( $seasons, $episodes, true );
streamit_child_tvshow_time_end( 'download_catalog' );

streamit_child_tvshow_timing_reset();
$catalog_b = streamit_child_build_series_download_catalog_from_data( $seasons, $episodes, true );

assert_eq( $catalog_b, $catalog_a, 'timing around catalog builder does not change output' );
assert_eq( 1, count( $catalog_a['seasons'] ?? array() ), 'catalog still has one season' );
assert_true( count( $GLOBALS['tvshow_timing_test_logs'] ) >= 1, 'enabled catalog wrap still emits a timing line' );

if ( $failures > 0 ) {
	echo "\n{$failures} tvshow phase timing test(s) failed.\n";
	exit( 1 );
}

echo "\nAll TV-show phase timing tests passed.\n";

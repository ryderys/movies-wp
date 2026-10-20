<?php
/**
 * CLI tests for ongoing series fingerprint + poll enqueue guards.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-ongoing-sync-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-ongoing-test/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
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

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-api-client.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-ongoing.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-ongoing-sync.php';

$failures = 0;

function og_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function og_same( $expected, $actual, string $label ): void {
	og_assert( $expected === $actual, $label . ' got=' . var_export( $actual, true ) );
}

echo "Ongoing fingerprint\n";

$list_a = array(
	'ok'    => true,
	'kind'  => 'series',
	'files' => array(
		array( 'media_path' => 'series/k/2026/Show/Trailer/a.mp4', 'size_bytes' => 10, 'kind' => 'trailer' ),
		array( 'media_path' => 'series/k/2026/Show/480p/Show.S01E01.mkv', 'size_bytes' => 100, 'kind' => 'video' ),
	),
);
$list_b = array(
	'ok'    => true,
	'kind'  => 'series',
	'files' => array(
		array( 'media_path' => 'series/k/2026/Show/480p/Show.S01E01.mkv', 'size_bytes' => 100, 'kind' => 'video' ),
		array( 'media_path' => 'series/k/2026/Show/Trailer/a.mp4', 'size_bytes' => 10, 'kind' => 'trailer' ),
	),
);
$list_c = array(
	'ok'    => true,
	'kind'  => 'series',
	'files' => array(
		array( 'media_path' => 'series/k/2026/Show/480p/Show.S01E01.mkv', 'size_bytes' => 100, 'kind' => 'video' ),
		array( 'media_path' => 'series/k/2026/Show/480p/Show.S01E02.mkv', 'size_bytes' => 120, 'kind' => 'video' ),
		array( 'media_path' => 'series/k/2026/Show/Trailer/a.mp4', 'size_bytes' => 10, 'kind' => 'trailer' ),
	),
);

$fp_a = Movies_WP_Series_Ongoing::fingerprint_from_list( $list_a );
$fp_b = Movies_WP_Series_Ongoing::fingerprint_from_list( $list_b );
$fp_c = Movies_WP_Series_Ongoing::fingerprint_from_list( $list_c );
og_same( $fp_a, $fp_b, 'fingerprint ignores file order' );
og_assert( $fp_a !== $fp_c, 'new episode path changes fingerprint' );

echo "Poll enqueue guards\n";

$meta = array(
	97 => array(
		Movies_WP_Series_Ongoing::META_ONGOING     => '1',
		'_media_directory'                         => 'series/korea/2026/A.Trap.Called.Desire',
		'_tmdb_id'                                 => '326991',
		Movies_WP_Series_Ongoing::META_FINGERPRINT => $fp_a,
	),
);

$opts = array(
	'get_tvshow_meta' => static function ( $id, $key ) use ( &$meta ) {
		return $meta[ (int) $id ][ $key ] ?? '';
	},
	'update_tvshow_meta' => static function ( $id, $key, $value ) use ( &$meta ) {
		$meta[ (int) $id ][ (string) $key ] = $value;
		return true;
	},
	'find_ongoing_ids' => static function () {
		return array( 97 );
	},
	'get_tvshow' => static function () {
		return (object) array( 'post_title' => 'A Trap Called Desire' );
	},
	'list_series' => static function () use ( $list_a ) {
		return $list_a;
	},
	'directory_has_active_job' => static function () {
		return false;
	},
	'orchestrator_preview' => static function () {
		og_assert( false, 'unchanged fingerprint must not build a preview' );
		return new WP_Error( 'unexpected', 'should not run' );
	},
);

$unchanged = Movies_WP_Series_Ongoing_Sync::maybe_enqueue_tvshow( 97, $opts );
og_same( false, $unchanged['enqueued'] ?? null, 'unchanged list does not enqueue' );
og_same( 'unchanged', $unchanged['reason'] ?? null, 'unchanged reason' );

$opts['list_series'] = static function () use ( $list_c ) {
	return $list_c;
};
$opts['directory_has_active_job'] = static function () {
	return true;
};
$busy = Movies_WP_Series_Ongoing_Sync::maybe_enqueue_tvshow( 97, $opts );
og_same( false, $busy['enqueued'] ?? null, 'active job blocks enqueue' );
og_same( 'job_active', $busy['reason'] ?? null, 'job_active reason' );

$enqueued = 0;
$opts['directory_has_active_job'] = static function () {
	return false;
};
$opts['orchestrator_preview'] = static function ( array $input ) {
	return array(
		'ok'              => true,
		'ready_to_import' => true,
		'input'           => $input,
		'metadata_plan'   => array( 'ready_to_import' => true ),
		'validation'      => array( 'errors' => array(), 'warnings' => array() ),
	);
};
$opts['snapshot_create'] = static function () {
	return array( 'token' => 'snap-token' );
};
$opts['enqueue_job'] = static function () use ( &$enqueued ) {
	++$enqueued;
	return array( 'token' => 'job-token', 'enqueued' => true );
};

$changed = Movies_WP_Series_Ongoing_Sync::maybe_enqueue_tvshow( 97, $opts );
og_same( true, $changed['enqueued'] ?? null, 'changed list enqueues import' );
og_same( 1, $enqueued, 'enqueue_job called once' );

echo "After successful import stores fingerprint + ongoing\n";

$meta[98] = array(
	'_media_directory' => 'series/korea/2026/A.Trap.Called.Desire',
	'_tmdb_id'         => '326991',
);
Movies_WP_Series_Ongoing_Sync::after_successful_import(
	98,
	array(
		'input' => array(
			'series_directory' => 'series/korea/2026/A.Trap.Called.Desire',
			'series_ongoing'   => true,
		),
	),
	array(
		'get_tvshow_meta' => static function ( $id, $key ) use ( &$meta ) {
			return $meta[ (int) $id ][ $key ] ?? '';
		},
		'update_tvshow_meta' => static function ( $id, $key, $value ) use ( &$meta ) {
			$meta[ (int) $id ][ (string) $key ] = $value;
			return true;
		},
		'list_series' => static function () use ( $list_c ) {
			return $list_c;
		},
	)
);
og_same( '1', (string) ( $meta[98][ Movies_WP_Series_Ongoing::META_ONGOING ] ?? '' ), 'ongoing flag saved' );
og_same( $fp_c, $meta[98][ Movies_WP_Series_Ongoing::META_FINGERPRINT ] ?? null, 'fingerprint saved after success' );

og_same( '/list/series', Movies_WP_Series_Media_Api_Client::LIST_REQUEST_PATH, 'list route constant' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll ongoing series sync tests passed.\n";
exit( $failures ? 1 : 0 );

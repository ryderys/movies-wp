<?php
/**
 * Production execute() must not enable in-memory test stores.
 *
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-import-inline-safety-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-import-inline-safety-test/' );
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

require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-snapshot-store.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-job-store.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-job-runner.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-orchestrator.php';

$failures = 0;
function inline_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

echo "Series import inline safety\n";
inline_assert(
	false === Movies_WP_Series_Import_Job_Runner::test_memory_enabled(),
	'production requests do not enable test memory stores'
);

$payload = array(
	'ok'              => true,
	'ready_to_import' => true,
	'input'           => array(
		'tmdb_id'          => 100,
		'title'            => 'Test',
		'series_directory' => 'series/korea/2024/Show',
	),
	'metadata_plan'   => array(
		'ok'              => true,
		'ready_to_import' => true,
		'series'          => array( 'tmdb_id' => 100 ),
		'seasons'         => array(),
	),
);
$result = Movies_WP_Series_Import_Job_Runner::run_inline( $payload );
inline_assert( is_wp_error( $result ), 'run_inline without the test flag is an error' );
inline_assert(
	'series_import_inline_test_only' === $result->get_error_code(),
	'run_inline refuses to select in-memory stores in production'
);

$exec = Movies_WP_Series_Orchestrator::execute(
	array(
		'tmdb_id'          => 100,
		'title'            => 'Test',
		'series_directory' => 'series/korea/2024/Show',
	),
	array( 'snapshot' => $payload )
);
inline_assert( is_wp_error( $exec ), 'execute() without the test flag does not run an in-memory import' );
inline_assert(
	'series_import_inline_test_only' === $exec->get_error_code(),
	'execute() cannot silently use test memory stores'
);

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series import inline safety tests passed.\n";
exit( $failures ? 1 : 0 );

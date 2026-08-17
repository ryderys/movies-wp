<?php
/**
 * Deterministic request-boundary tests for Movies_WP_Series_Admin.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-admin-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-admin-test/' );
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code, $message ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) { return $text; }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}

require_once dirname( __DIR__ ) . '/class-movies-wp-series-admin.php';

$failures = 0;

function series_admin_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function series_admin_same( $expected, $actual, string $label ): void {
	series_admin_assert(
		$expected === $actual,
		$label . ' expected=' . var_export( $expected, true ) . ' got=' . var_export( $actual, true )
	);
}

function series_admin_preview(): array {
	return array(
		'ok'              => true,
		'type'            => 'series',
		'input'           => array(
			'tmdb_id' => 100,
			'title'   => 'عنوان محلی',
			'summary' => 'خلاصه',
		),
		'series'          => array(
			'tmdb_id' => 100,
			'name'    => 'TMDb Series',
			'seasons' => array(),
		),
		'validation'      => array(
			'errors'   => array(),
			'warnings' => array(),
		),
		'ready_to_import' => true,
	);
}

function series_admin_plan(): array {
	return array(
		'ok'              => true,
		'type'            => 'series',
		'contract'        => array(
			'kind'      => 'series_import_plan',
			'version'   => 1,
			'read_only' => true,
		),
		'identity'        => array(
			'action'             => 'create',
			'existing_series_id' => null,
			'match_by'           => '_tmdb_id',
		),
		'series'          => array(
			'tmdb_id' => 100,
			'title'   => 'عنوان محلی',
		),
		'images'          => array(),
		'sources_policy'  => array(
			'episode_meta_key' => '_sources',
			'mutate'           => false,
			'actions'          => array(),
		),
		'seasons'         => array(),
		'warnings'        => array(),
		'errors'          => array(),
		'ready_to_import' => true,
	);
}

function series_admin_post( bool $confirmed = true ): array {
	return array(
		'_wpnonce'                    => 'valid',
		'tmdb_id'                    => '100',
		'title'                      => '  عنوان محلی  ',
		'summary'                    => 'خلاصه',
		'confirm_import'             => $confirmed ? '1' : null,
		'plan'                       => array( 'identity' => array( 'action' => 'update' ) ),
		'identity_action'            => 'update',
		'existing_series_id'         => 999,
		'_sources'                   => array( array( 'url' => 'malicious' ) ),
		'episodes'                   => array( array( 'existing_episode_id' => 999 ) ),
		'images'                     => array( 'poster' => array( 'action' => 'delete' ) ),
	);
}

function series_admin_options( array &$calls, ?array &$received_plan = null ): array {
	return array(
		'current_user_can' => static function ( $capability ) use ( &$calls ): bool {
			++$calls['capability'];
			return 'manage_options' === $capability;
		},
		'verify_nonce'     => static function ( $nonce, $action ) use ( &$calls ): bool {
			++$calls['nonce'];
			return 'valid' === $nonce
				&& in_array( $action, array( Movies_WP_Series_Admin::PREVIEW_NONCE, Movies_WP_Series_Admin::IMPORT_NONCE ), true );
		},
		'preview_build'    => static function ( array $values ) use ( &$calls ): array {
			++$calls['preview'];
			series_admin_same( array( 'tmdb_id', 'title', 'summary' ), array_keys( $values ), 'preview receives whitelisted inputs only' );
			return series_admin_preview();
		},
		'plan_build'       => static function ( array $preview ) use ( &$calls ): array {
			++$calls['plan'];
			series_admin_same( 100, $preview['series']['tmdb_id'], 'plan receives authoritative preview' );
			return series_admin_plan();
		},
		'import_execute'   => static function ( array $plan ) use ( &$calls, &$received_plan ): array {
			++$calls['import'];
			$received_plan = $plan;
			return array(
				'ok'        => true,
				'partial'   => false,
				'series_id' => 501,
				'action'    => 'create',
				'warnings'  => array(),
				'errors'    => array(),
				'series'    => array( 'ok' => true ),
				'seasons'   => array(),
				'episodes'  => array(),
				'images'    => array(),
			);
		},
	);
}

echo "Series admin request contract\n";

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'plan' => 0, 'import' => 0 );
$received_plan = null;
$preview_result = Movies_WP_Series_Admin::process_preview_request(
	series_admin_post(),
	series_admin_options( $calls, $received_plan )
);
series_admin_assert( is_array( $preview_result ), 'valid preview request returns context' );
series_admin_same( 'عنوان محلی', $preview_result['values']['title'], 'preview uses normalized server result' );
series_admin_same( 1, $calls['preview'], 'preview service called once' );
series_admin_same( 1, $calls['plan'], 'plan builder called once' );
series_admin_same( 0, $calls['import'], 'preview performs no import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'plan' => 0, 'import' => 0 );
$received_plan = null;
$import_result = Movies_WP_Series_Admin::process_import_request(
	series_admin_post(),
	series_admin_options( $calls, $received_plan )
);
series_admin_assert( is_array( $import_result ) && true === $import_result['ok'], 'valid import returns service result' );
series_admin_same( 1, $calls['preview'], 'import rebuilds preview exactly once' );
series_admin_same( 1, $calls['plan'], 'import rebuilds plan exactly once' );
series_admin_same( 1, $calls['import'], 'Import Service invoked exactly once' );
series_admin_same( series_admin_plan(), $received_plan, 'Import Service receives only rebuilt authoritative plan' );
series_admin_assert( ! isset( $received_plan['_sources'] ), 'browser _sources payload is discarded' );
series_admin_assert( ! isset( $received_plan['existing_series_id'] ), 'browser identity fields are discarded' );
series_admin_same( false, $received_plan['sources_policy']['mutate'], 'rebuilt plan preserves _sources immutability' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'plan' => 0, 'import' => 0 );
$received_plan = null;
$confirmation_error = Movies_WP_Series_Admin::process_import_request(
	series_admin_post( false ),
	series_admin_options( $calls, $received_plan )
);
series_admin_same( 'series_import_confirmation_required', $confirmation_error->get_error_code(), 'explicit confirmation is required' );
series_admin_same( 0, $calls['preview'], 'missing confirmation blocks preview rebuild' );
series_admin_same( 0, $calls['import'], 'missing confirmation blocks import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'plan' => 0, 'import' => 0 );
$received_plan = null;
$options = series_admin_options( $calls, $received_plan );
$options['current_user_can'] = static function (): bool { return false; };
$forbidden = Movies_WP_Series_Admin::process_import_request( series_admin_post(), $options );
series_admin_same( 'series_import_forbidden', $forbidden->get_error_code(), 'capability failure is deterministic' );
series_admin_same( 0, $calls['import'], 'capability failure blocks import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'plan' => 0, 'import' => 0 );
$received_plan = null;
$options = series_admin_options( $calls, $received_plan );
$options['verify_nonce'] = static function (): bool { return false; };
$invalid_nonce = Movies_WP_Series_Admin::process_import_request( series_admin_post(), $options );
series_admin_same( 'series_import_invalid_nonce', $invalid_nonce->get_error_code(), 'nonce failure is deterministic' );
series_admin_same( 0, $calls['preview'], 'nonce failure blocks preview rebuild' );
series_admin_same( 0, $calls['import'], 'nonce failure blocks import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'plan' => 0, 'import' => 0 );
$received_plan = null;
$options = series_admin_options( $calls, $received_plan );
$options['plan_build'] = static function () use ( &$calls ) {
	++$calls['plan'];
	return new WP_Error( 'series_import_duplicate_identity', 'Duplicate identity.' );
};
$plan_error = Movies_WP_Series_Admin::process_import_request( series_admin_post(), $options );
series_admin_same( 'series_import_duplicate_identity', $plan_error->get_error_code(), 'plan error is preserved' );
series_admin_same( 0, $calls['import'], 'invalid plan is rejected before import' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series admin contract tests passed.\n";
exit( $failures ? 1 : 0 );

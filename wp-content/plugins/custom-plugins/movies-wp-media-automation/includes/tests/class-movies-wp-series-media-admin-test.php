<?php
/**
 * Deterministic request-boundary tests for Movies_WP_Series_Media_Admin.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-media-admin-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-media-admin-test/' );
}
if ( ! defined( 'MOVIES_WP_MEDIA_AUTOMATION_DIR' ) ) {
	define( 'MOVIES_WP_MEDIA_AUTOMATION_DIR', dirname( __DIR__, 2 ) );
}
if ( ! defined( 'MOVIES_WP_MEDIA_AUTOMATION_FILE' ) ) {
	define( 'MOVIES_WP_MEDIA_AUTOMATION_FILE', MOVIES_WP_MEDIA_AUTOMATION_DIR . '/movies-wp-media-automation.php' );
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
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! class_exists( 'Movies_WP_Media_Admin' ) ) {
	class Movies_WP_Media_Admin {
		const SLUG = 'movies-wp-media-automation';
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-admin.php';

$failures = 0;

function sm_admin_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function sm_admin_same( $expected, $actual, string $label ): void {
	sm_admin_assert(
		$expected === $actual,
		$label . ' expected=' . var_export( $expected, true ) . ' got=' . var_export( $actual, true )
	);
}

function sm_admin_preview(): array {
	return array(
		'ok'              => true,
		'type'            => 'series_media',
		'input'           => array(
			'tvshow_id'        => 50,
			'expected_tmdb_id' => 0,
			'series_directory' => 'Series/korea/2024/Show',
		),
		'validation'      => array(
			'errors'   => array(),
			'warnings' => array(),
		),
		'ready_to_import' => true,
	);
}

function sm_admin_plan(): array {
	return array(
		'ok'              => true,
		'type'            => 'series_media',
		'contract'        => array(
			'kind'      => 'series_media_import_plan',
			'version'   => 1,
			'read_only' => true,
		),
		'identity'        => array(
			'tvshow_id'        => 50,
			'series_directory' => 'Series/korea/2024/Show',
			'expected_tmdb_id' => 0,
		),
		'episodes'        => array(),
		'warnings'        => array(),
		'errors'          => array(),
		'ready_to_import' => true,
	);
}

function sm_admin_post( bool $confirmed = true ): array {
	return array(
		'_wpnonce'         => 'valid',
		'tvshow_id'        => '50',
		'expected_tmdb_id' => '',
		'series_directory' => 'Series/korea/2024/Show',
		'confirm_import'   => $confirmed ? '1' : null,
		'plan'             => array(
			'episodes' => array(
				array(
					'operations' => array(
						'_sources' => array(
							array(
								'action' => 'upsert',
								'path'   => 'Series/evil/injected.mkv',
							),
						),
					),
				),
			),
		),
		'_sources'         => array(
			array(
				'link' => 'Series/evil/browser.mkv',
			),
		),
	);
}

/**
 * @param array<string, int> $calls
 * @param array<string, mixed>|null $received_plan
 * @return array<string, mixed>
 */
function sm_admin_options( array &$calls, ?array &$received_plan = null ): array {
	return array(
		'current_user_can' => static function ( $capability ) use ( &$calls ): bool {
			++$calls['capability'];
			return 'manage_options' === $capability;
		},
		'verify_nonce'     => static function ( $nonce, $action ) use ( &$calls ): bool {
			++$calls['nonce'];
			return 'valid' === $nonce
				&& in_array( $action, array( Movies_WP_Series_Media_Admin::PREVIEW_NONCE, Movies_WP_Series_Media_Admin::IMPORT_NONCE ), true );
		},
		'preview_build'    => static function ( array $values ) use ( &$calls ): array {
			++$calls['preview'];
			sm_admin_same(
				array( 'tvshow_id', 'expected_tmdb_id', 'series_directory' ),
				array_keys( $values ),
				'preview receives whitelisted inputs only'
			);
			return sm_admin_preview();
		},
		'plan_build'       => static function ( array $preview ) use ( &$calls ): array {
			++$calls['plan'];
			sm_admin_same( 50, $preview['input']['tvshow_id'], 'plan receives authoritative preview' );
			return sm_admin_plan();
		},
		'import_execute'   => static function ( array $plan ) use ( &$calls, &$received_plan ): array {
			++$calls['import'];
			$received_plan = $plan;
			return array(
				'ok'        => true,
				'partial'   => false,
				'tvshow_id' => 50,
				'completed' => 1,
				'errors'    => array(),
				'warnings'  => array(),
			);
		},
	);
}

echo "Series media admin request contract\n";

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'plan' => 0, 'import' => 0 );
$received_plan = null;
$preview_result = Movies_WP_Series_Media_Admin::process_preview_request(
	sm_admin_post(),
	sm_admin_options( $calls, $received_plan )
);
sm_admin_assert( is_array( $preview_result ), 'valid preview request returns context' );
sm_admin_same( 'Series/korea/2024/Show', $preview_result['values']['series_directory'], 'preview uses normalized directory' );
sm_admin_same( 1, $calls['preview'], 'preview service called once' );
sm_admin_same( 1, $calls['plan'], 'plan builder called once' );
sm_admin_same( 0, $calls['import'], 'preview performs no import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'plan' => 0, 'import' => 0 );
$received_plan = null;
$import_result = Movies_WP_Series_Media_Admin::process_import_request(
	sm_admin_post(),
	sm_admin_options( $calls, $received_plan )
);
sm_admin_assert( is_array( $import_result ) && true === $import_result['ok'], 'valid import returns service result' );
sm_admin_same( 1, $calls['preview'], 'import rebuilds preview exactly once' );
sm_admin_same( 1, $calls['plan'], 'import rebuilds plan exactly once' );
sm_admin_same( 1, $calls['import'], 'import service invoked exactly once' );
sm_admin_same( sm_admin_plan(), $received_plan, 'import service receives only rebuilt authoritative plan' );
sm_admin_assert( ! isset( $received_plan['_sources'] ), 'browser _sources payload is discarded' );
sm_admin_assert( empty( $received_plan['episodes'] ), 'browser plan episodes are discarded' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'plan' => 0, 'import' => 0 );
$received_plan = null;
$confirmation_error = Movies_WP_Series_Media_Admin::process_import_request(
	sm_admin_post( false ),
	sm_admin_options( $calls, $received_plan )
);
sm_admin_same( 'series_media_import_confirmation_required', $confirmation_error->get_error_code(), 'explicit confirmation is required' );
sm_admin_same( 0, $calls['preview'], 'missing confirmation blocks preview rebuild' );
sm_admin_same( 0, $calls['import'], 'missing confirmation blocks import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'plan' => 0, 'import' => 0 );
$received_plan = null;
$options = sm_admin_options( $calls, $received_plan );
$options['current_user_can'] = static function (): bool {
	return false;
};
$forbidden = Movies_WP_Series_Media_Admin::process_import_request( sm_admin_post(), $options );
sm_admin_same( 'series_media_import_forbidden', $forbidden->get_error_code(), 'capability failure is deterministic' );
sm_admin_same( 0, $calls['import'], 'capability failure blocks import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'plan' => 0, 'import' => 0 );
$received_plan = null;
$options = sm_admin_options( $calls, $received_plan );
$options['verify_nonce'] = static function (): bool {
	return false;
};
$invalid_nonce = Movies_WP_Series_Media_Admin::process_import_request( sm_admin_post(), $options );
sm_admin_same( 'series_media_import_invalid_nonce', $invalid_nonce->get_error_code(), 'nonce failure is deterministic' );
sm_admin_same( 0, $calls['preview'], 'nonce failure blocks preview rebuild' );
sm_admin_same( 0, $calls['import'], 'nonce failure blocks import' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series media admin contract tests passed.\n";
exit( $failures ? 1 : 0 );

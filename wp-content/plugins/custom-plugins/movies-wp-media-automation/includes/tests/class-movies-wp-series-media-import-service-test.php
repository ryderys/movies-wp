<?php
/**
 * CLI tests for Movies_WP_Series_Media_Import_Service.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-media-import-service-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-media-import-service-test/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
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
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' );
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-api-client.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-preview-service.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-import-plan.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-import-service.php';

$failures = 0;
$adapter_calls = 0;

function sm_import_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function sm_import_same( $expected, $actual, string $label ): void {
	sm_import_assert( $expected === $actual, $label . ' got=' . var_export( $actual, true ) );
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function sm_import_plan( array $overrides = array() ): array {
	$plan = array(
		'ok'              => true,
		'type'            => 'series_media',
		'ready_to_import' => true,
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
		'warnings'        => array(),
		'errors'          => array(),
		'episodes'        => array(
			array(
				'episode_id'     => 101,
				'tvshow_id'      => 50,
				'season_number'  => '0',
				'episode_number' => '1',
				'operations'     => array(
					'_sources'   => array(
						array(
							'action' => 'upsert',
							'path'   => 'Series/korea/2024/Show/S00E01.mkv',
							'row'    => array(
								'link'             => 'Series/korea/2024/Show/S00E01.mkv',
								'download_content' => 'Series/korea/2024/Show/S00E01.mkv',
							),
						),
					),
					'_subtitles' => array(),
				),
			),
		),
	);
	return array_replace_recursive( $plan, $overrides );
}

echo "Series media import service validation\n";

$invalid = Movies_WP_Series_Media_Import_Service::execute(
	sm_import_plan(
		array(
			'contract' => array( 'kind' => 'wrong', 'version' => 1 ),
		)
	)
);
sm_import_same( false, $invalid['ok'], 'invalid contract rejected' );
sm_import_same( 'series_media_import_service_invalid_contract', $invalid['errors'][0]['code'], 'invalid contract code' );

$with_errors = Movies_WP_Series_Media_Import_Service::execute(
	sm_import_plan(
		array(
			'errors' => array(
				array(
					'code'    => 'missing_episode',
					'message' => 'Missing',
				),
			),
		)
	)
);
sm_import_same( false, $with_errors['ok'], 'plan with errors rejected before adapter' );

$bad_path = Movies_WP_Series_Media_Import_Service::execute(
	sm_import_plan(
		array(
			'episodes' => array(
				array(
					'episode_id'     => 101,
					'tvshow_id'      => 50,
					'season_number'  => '1',
					'episode_number' => '1',
					'operations'     => array(
						'_sources' => array(
							array(
								'action' => 'upsert',
								'path'   => 'https://signed.example.test/file.mkv',
								'row'    => array(),
							),
						),
					),
				),
			),
		)
	)
);
sm_import_same( false, $bad_path['ok'], 'signed path rejected' );
sm_import_same( 'series_media_import_service_invalid_path', $bad_path['errors'][0]['code'], 'signed path error code' );

$forbidden_meta = Movies_WP_Series_Media_Import_Service::execute(
	sm_import_plan(
		array(
			'episodes' => array(
				array(
					'episode_id'     => 101,
					'tvshow_id'      => 50,
					'season_number'  => '1',
					'episode_number' => '1',
					'operations'     => array(
						'_title' => array(),
					),
				),
			),
		)
	)
);
sm_import_same( false, $forbidden_meta['ok'], 'forbidden meta key rejected' );
sm_import_same( 'series_media_import_service_forbidden_meta_key', $forbidden_meta['errors'][0]['code'], 'forbidden meta key code' );

echo "Series media import service adapter orchestration\n";

$adapter_calls = 0;
$success = Movies_WP_Series_Media_Import_Service::execute(
	sm_import_plan(),
	array(
		'adapter_apply' => static function ( array $plan ) use ( &$adapter_calls ): array {
			++$adapter_calls;
			sm_import_same( '0', $plan['episodes'][0]['season_number'], 'season zero preserved in adapter call' );
			return array(
				'ok'        => true,
				'partial'   => false,
				'completed' => 1,
				'episodes'  => array(),
				'errors'    => array(),
				'warnings'  => array(),
			);
		},
	)
);
sm_import_same( 1, $adapter_calls, 'adapter invoked exactly once' );
sm_import_same( true, $success['ok'], 'valid plan succeeds' );
sm_import_same( 1, $success['completed'], 'completed count preserved' );

$adapter_calls = 0;
Movies_WP_Series_Media_Import_Service::execute(
	sm_import_plan(),
	array(
		'adapter_apply' => static function () use ( &$adapter_calls ): array {
			++$adapter_calls;
			return array(
				'ok'        => false,
				'partial'   => true,
				'completed' => 0,
				'episodes'  => array(),
				'errors'    => array(
					array(
						'code'    => 'episode_media_partial_failure',
						'message' => 'Partial',
					),
				),
				'warnings'  => array(),
			);
		},
	)
);
sm_import_same( 1, $adapter_calls, 'partial adapter result still invoked once' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series media import service tests passed.\n";
exit( $failures ? 1 : 0 );

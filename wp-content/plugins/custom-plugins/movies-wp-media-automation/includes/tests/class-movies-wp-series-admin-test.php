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
		'type'            => 'series_automation',
		'input'           => array(
			'tmdb_id'          => 100,
			'title'            => 'عنوان محلی',
			'summary'          => 'خلاصه',
			'series_directory' => 'series/korea/2024/Show',
		),
		'series'          => array(
			'tmdb_id' => 100,
			'name'    => 'TMDb Series',
			'seasons' => array(),
		),
		'metadata_plan'   => series_admin_plan(),
		'media'           => array(
			'directory' => array( 'path' => 'series/korea/2024/Show' ),
			'episodes'  => array(),
		),
		'episodes'        => array(),
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
		'series_directory'           => 'series/korea/2024/Show',
		'confirm_import'             => $confirmed ? '1' : null,
		'plan'                       => array( 'identity' => array( 'action' => 'update' ) ),
		'identity_action'            => 'update',
		'existing_series_id'         => 999,
		'_sources'                   => array( array( 'url' => 'malicious' ) ),
		'episodes'                   => array( array( 'existing_episode_id' => 999 ) ),
		'images'                     => array( 'poster' => array( 'action' => 'delete' ) ),
	);
}

function series_admin_options( array &$calls, ?array &$received_values = null ): array {
	return array(
		'current_user_can'    => static function ( $capability ) use ( &$calls ): bool {
			++$calls['capability'];
			return 'manage_options' === $capability;
		},
		'verify_nonce'        => static function ( $nonce, $action ) use ( &$calls ): bool {
			++$calls['nonce'];
			return 'valid' === $nonce
				&& in_array( $action, array( Movies_WP_Series_Admin::PREVIEW_NONCE, Movies_WP_Series_Admin::IMPORT_NONCE ), true );
		},
		'orchestrator_preview' => static function ( array $values ) use ( &$calls ): array {
			++$calls['preview'];
			series_admin_same( array( 'tmdb_id', 'title', 'summary', 'series_directory' ), array_keys( $values ), 'preview receives whitelisted inputs only' );
			return series_admin_preview();
		},
		'orchestrator_execute' => static function ( array $values ) use ( &$calls, &$received_values ): array {
			++$calls['import'];
			$received_values = $values;
			return array(
				'ok'        => true,
				'partial'   => false,
				'series_id' => 501,
				'action'    => 'create',
				'completed' => 1,
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

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0 );
$received_values = null;
$preview_result = Movies_WP_Series_Admin::process_preview_request(
	series_admin_post(),
	series_admin_options( $calls, $received_values )
);
series_admin_assert( is_array( $preview_result ), 'valid preview request returns context' );
series_admin_same( 'عنوان محلی', $preview_result['values']['title'], 'preview uses normalized server result' );
series_admin_same( 'series/korea/2024/Show', $preview_result['values']['series_directory'], 'preview keeps lowercase series directory' );
series_admin_same( 1, $calls['preview'], 'orchestrator preview called once' );
series_admin_same( 0, $calls['import'], 'preview performs no import' );
series_admin_same( series_admin_plan(), $preview_result['plan'], 'admin displays metadata plan from combined preview' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0 );
$received_values = null;
$import_result = Movies_WP_Series_Admin::process_import_request(
	series_admin_post(),
	series_admin_options( $calls, $received_values )
);
series_admin_assert( is_array( $import_result ) && true === $import_result['ok'], 'valid import returns orchestrator result' );
series_admin_same( 0, $calls['preview'], 'import does not reuse the preview-time media plan' );
series_admin_same( 1, $calls['import'], 'orchestrator invoked exactly once' );
series_admin_same(
	array(
		'tmdb_id'          => 100,
		'title'            => 'عنوان محلی',
		'summary'          => 'خلاصه',
		'series_directory' => 'series/korea/2024/Show',
	),
	$received_values,
	'orchestrator receives only rebuilt operator inputs'
);
series_admin_assert( ! isset( $received_values['plan'] ), 'browser plan payload is discarded' );
series_admin_assert( ! isset( $received_values['_sources'] ), 'browser _sources payload is discarded' );
series_admin_assert( ! isset( $received_values['existing_series_id'] ), 'browser identity fields are discarded' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0 );
$received_values = null;
$confirmation_error = Movies_WP_Series_Admin::process_import_request(
	series_admin_post( false ),
	series_admin_options( $calls, $received_values )
);
series_admin_same( 'series_import_confirmation_required', $confirmation_error->get_error_code(), 'explicit confirmation is required' );
series_admin_same( 0, $calls['preview'], 'missing confirmation blocks preview rebuild' );
series_admin_same( 0, $calls['import'], 'missing confirmation blocks import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0 );
$received_values = null;
$options = series_admin_options( $calls, $received_values );
$options['current_user_can'] = static function (): bool { return false; };
$forbidden = Movies_WP_Series_Admin::process_import_request( series_admin_post(), $options );
series_admin_same( 'series_import_forbidden', $forbidden->get_error_code(), 'capability failure is deterministic' );
series_admin_same( 0, $calls['import'], 'capability failure blocks import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0 );
$received_values = null;
$options = series_admin_options( $calls, $received_values );
$options['verify_nonce'] = static function (): bool { return false; };
$invalid_nonce = Movies_WP_Series_Admin::process_import_request( series_admin_post(), $options );
series_admin_same( 'series_import_invalid_nonce', $invalid_nonce->get_error_code(), 'nonce failure is deterministic' );
series_admin_same( 0, $calls['preview'], 'nonce failure blocks preview rebuild' );
series_admin_same( 0, $calls['import'], 'nonce failure blocks import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0 );
$received_values = null;
$options = series_admin_options( $calls, $received_values );
$options['orchestrator_preview'] = static function () use ( &$calls ) {
	++$calls['preview'];
	return new WP_Error( 'series_import_duplicate_identity', 'Duplicate identity.' );
};
$plan_error = Movies_WP_Series_Admin::process_preview_request( series_admin_post(), $options );
series_admin_same( 'series_import_duplicate_identity', $plan_error->get_error_code(), 'preview/plan error is preserved' );
series_admin_same( 0, $calls['import'], 'invalid preview is rejected before import' );

$view = (string) file_get_contents( dirname( __DIR__ ) . '/views/series-preview.php' );
series_admin_assert( str_contains( $view, 'name="series_directory"' ), 'Series page includes the directory field' );
series_admin_assert( str_contains( $view, 'What will happen' ), 'Series page leads with operator-facing import outcome' );
series_admin_assert( str_contains( $view, 'Episode coverage' ), 'Series page shows episode coverage' );
series_admin_assert( str_contains( $view, 'Episode details' ), 'Series page keeps episode matches under details' );
series_admin_assert( ! str_contains( $view, 'Import Plan summary' ), 'internal import-plan summary is not the primary heading' );
series_admin_assert( ! str_contains( $view, 'Always preserved' ), 'source preservation is not marketed as an operator feature' );
series_admin_assert( ! str_contains( $view, 'metadata_and_media' ), 'internal media status codes stay out of the view' );
series_admin_assert( ! str_contains( $view, 'keep_existing_untouched' ), 'internal source-policy values stay out of the view' );

echo "Series admin presentation helpers\n";

$still_warnings = array();
for ( $i = 1; $i <= 24; $i++ ) {
	$still_warnings[] = array(
		'code'    => 'series_episode_still_missing',
		'message' => sprintf( 'S01E%02d has no episode still on TMDb.', $i ),
	);
}
$still_groups = Movies_WP_Series_Admin::grouped_issues( $still_warnings );
series_admin_same( 1, count( $still_groups ), 'identical still warnings collapse to one group' );
series_admin_same( 24, $still_groups[0]['count'], 'still group retains the original warning count' );
series_admin_same(
	'24 episodes have no TMDb stills. Episode still images will be skipped.',
	$still_groups[0]['summary'],
	'missing stills use a single non-blocking operator summary'
);
series_admin_same( 24, count( $still_groups[0]['details'] ), 'still group keeps expandable per-episode details' );

$coverage = Movies_WP_Series_Admin::episode_coverage(
	array(
		array( 'season_number' => 1, 'episode_number' => 1, 'status' => 'metadata_and_media', 'source_count' => 3, 'subtitle_count' => 2 ),
		array( 'season_number' => 1, 'episode_number' => 24, 'status' => 'metadata_and_media', 'source_count' => 3, 'subtitle_count' => 2 ),
		array( 'season_number' => 1, 'episode_number' => 99, 'status' => 'media_without_tmdb', 'source_count' => 1, 'subtitle_count' => 0 ),
	)
);
series_admin_same( 2, $coverage['total'], 'coverage denominator counts TMDb episodes only' );
series_admin_same( 2, $coverage['matched'], 'coverage numerator counts matched TMDb episodes' );
series_admin_same( 'S01E01–S01E24', $coverage['range'], 'coverage range uses first and last matched codes' );
series_admin_same( true, $coverage['uniform'], 'uniform source counts are detected' );
series_admin_same( 3, $coverage['videos_per_episode'], 'uniform video count is exposed' );
series_admin_same( 2, $coverage['subtitles_per_episode'], 'uniform subtitle count is exposed' );
series_admin_same( 'Matched', Movies_WP_Series_Admin::media_status_label( 'metadata_and_media' ), 'internal media status is operator-facing' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series admin contract tests passed.\n";
exit( $failures ? 1 : 0 );

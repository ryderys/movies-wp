<?php
/**
 * CLI tests for Movies_WP_Series_Media_Import_Plan.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-media-import-plan-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-media-plan-test/' );
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
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
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
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}
		$out = @unserialize( $value );
		return false === $out && 'b:0;' !== $value ? $value : $out;
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

$failures = 0;

function sm_plan_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function sm_plan_same( $expected, $actual, string $label ): void {
	sm_plan_assert( $expected === $actual, $label . ' got=' . var_export( $actual, true ) );
}

/**
 * @return array<string, mixed>
 */
function sm_plan_preview(): array {
	return array(
		'ok'              => true,
		'type'            => 'series_media',
		'ready_to_import' => true,
		'input'           => array(
			'tvshow_id'        => 50,
			'expected_tmdb_id' => 0,
			'series_directory' => 'Series/korea/2024/Show',
		),
		'validation'      => array(
			'errors'   => array(),
			'warnings' => array(),
		),
		'episodes'        => array(
			array(
				'status'         => 'matched',
				'episode_id'     => 101,
				'tvshow_id'      => 50,
				'season_number'  => '1',
				'episode_number' => '1',
				'tmdb_id'        => 900,
				'sources'        => array(
					array(
						'media_path' => 'Series/korea/2024/Show/720p/S01E01.mkv',
						'quality'    => '720p',
						'size_label' => '1.0 GB',
					),
					array(
						'media_path' => 'Series/korea/2024/Show/720p/S01E01.mkv',
						'quality'    => '720p',
					),
				),
				'subtitles'      => array(
					array(
						'media_path' => 'Series/korea/2024/Show/SUB.ENG/S01E01.srt',
						'extension'  => 'srt',
						'subtitle'     => array(
							'label'   => 'ENG',
							'srclang' => 'en',
							'format'  => 'SRT',
						),
					),
					array(
						'media_path' => 'Series/korea/2024/Show/SUB.ENG/S01E01.ass',
						'extension'  => 'ass',
						'subtitle'     => array(
							'label'   => 'ENG',
							'srclang' => 'en',
							'format'  => 'ASS',
						),
					),
				),
			),
		),
	);
}

echo "Series media import plan path validation\n";

$signed = Movies_WP_Series_Media_Import_Plan::normalize_series_path( 'https://media.example.test/Series/korea/file.mkv' );
sm_plan_assert( is_wp_error( $signed ), 'signed URL rejected' );

$absolute = Movies_WP_Series_Media_Import_Plan::normalize_series_path( '/data/Series/korea/file.mkv' );
sm_plan_assert( is_wp_error( $absolute ), 'absolute path rejected' );

$valid = Movies_WP_Series_Media_Import_Plan::normalize_series_path( 'Series/korea/2024/Show/file.mkv' );
sm_plan_same( 'Series/korea/2024/Show/file.mkv', $valid, 'relative series path accepted' );

echo "Series media import plan operations\n";

$existing_sources = array(
	array(
		'name'             => 'Manual',
		'link'             => 'Series/korea/2024/Show/manual.mkv',
		'custom_flag'      => 'keep-me',
		'download_content' => 'Series/korea/2024/Show/manual.mkv',
	),
);
$existing_subtitles = array(
	array(
		'label'   => 'FA',
		'url'     => 'Series/korea/2024/Show/SUB.FA/S01E01.srt',
		'default' => 1,
	),
);

$plan = Movies_WP_Series_Media_Import_Plan::build(
	sm_plan_preview(),
	array(
		'get_sources'   => static function () use ( $existing_sources ): array {
			return $existing_sources;
		},
		'get_subtitles' => static function () use ( $existing_subtitles ): array {
			return $existing_subtitles;
		},
	)
);
sm_plan_assert( is_array( $plan ), 'plan builds from matched preview' );
sm_plan_same( true, $plan['ready_to_import'], 'matched preview yields ready plan' );
sm_plan_same( 'series_media_import_plan', $plan['contract']['kind'], 'plan contract kind' );
sm_plan_same( 1, count( $plan['episodes'] ), 'one episode planned' );

$ops = $plan['episodes'][0]['operations'];
$source_actions = array_column( $ops['_sources'], 'action' );
sm_plan_assert( in_array( 'upsert', $source_actions, true ), 'scan source upsert planned' );
sm_plan_assert( in_array( 'preserve', $source_actions, true ), 'existing unmatched source preserved' );

$upsert = null;
foreach ( $ops['_sources'] as $operation ) {
	if ( 'upsert' === $operation['action'] && str_contains( (string) $operation['path'], 'S01E01.mkv' ) ) {
		$upsert = $operation;
		break;
	}
}
sm_plan_assert( is_array( $upsert ), 'upsert operation found' );
sm_plan_same( 'Series/korea/2024/Show/720p/S01E01.mkv', $upsert['row']['link'], 'source row stores relative path not signed URL' );
sm_plan_same( 'Series/korea/2024/Show/720p/S01E01.mkv', $upsert['row']['download_content'], 'download_content stores relative path' );

$subtitle_warnings = array_filter(
	$plan['warnings'],
	static function ( $warning ): bool {
		return is_array( $warning ) && ( $warning['code'] ?? '' ) === 'subtitle_playback_unsupported';
	}
);
sm_plan_assert( count( $subtitle_warnings ) >= 1, 'unsupported subtitle extension warns' );

$missing = Movies_WP_Series_Media_Import_Plan::build(
	array_merge(
		sm_plan_preview(),
		array(
			'episodes' => array(
				array(
					'status'         => 'missing_episode',
					'season_number'  => '1',
					'episode_number' => '9',
				),
			),
		)
	)
);
sm_plan_same( false, $missing['ready_to_import'], 'missing episode blocks plan readiness' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series media import plan tests passed.\n";
exit( $failures ? 1 : 0 );

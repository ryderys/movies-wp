<?php
/**
 * CLI tests for Movies_WP_Series_Media_Preview_Service.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-media-preview-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-media-preview-test/' );
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

$failures = 0;

function sm_preview_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function sm_preview_same( $expected, $actual, string $label ): void {
	sm_preview_assert( $expected === $actual, $label . ' got=' . var_export( $actual, true ) );
}

/**
 * @return array<string, mixed>
 */
function sm_preview_scan(): array {
	return array(
		'ok'       => true,
		'kind'     => 'series',
		'ready'    => true,
		'files'    => array(),
		'episodes' => array(
			array(
				'season_number'  => '1',
				'episode_number' => '1',
				'token'          => 'S01E01',
				'sources'        => array(
					array(
						'media_path' => 'Series/korea/2024/Show/720p/S01E01.mkv',
						'quality'    => '720p',
						'size_label' => '1.0 GB',
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
				),
			),
			array(
				'season_number'  => '1',
				'episode_number' => '2',
				'token'          => 'S01E02',
				'sources'        => array(),
				'subtitles'      => array(),
			),
		),
		'warnings' => array(),
		'errors'   => array(),
	);
}

/**
 * @return array<int, array<string, mixed>>
 */
function sm_preview_episodes(): array {
	return array(
		array(
			'id'             => 101,
			'tvshow_id'      => 50,
			'tmdb_id'        => 900,
			'season_number'  => '1',
			'episode_number' => '1',
		),
	);
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function sm_preview_options( array $rows = array(), ?int $tmdb_id = null ): array {
	$episode_rows = $rows !== array() ? $rows : sm_preview_episodes();
	return array(
		'get_tvshow'       => static function ( int $id ): array {
			return array( 'id' => $id );
		},
		'get_tvshow_meta'  => static function ( int $id, string $key ) use ( $tmdb_id ) {
			unset( $id );
			return '_tmdb_id' === $key ? ( $tmdb_id ?? 900 ) : null;
		},
		'scan_series'      => static function (): array {
			return sm_preview_scan();
		},
		'find_episodes'    => static function () use ( $episode_rows ): array {
			return $episode_rows;
		},
	);
}

echo "Series media preview input validation\n";

$invalid = Movies_WP_Series_Media_Preview_Service::build(
	array(
		'tvshow_id'        => 0,
		'series_directory' => 'Series/korea/2024/Show',
	)
);
sm_preview_assert( is_wp_error( $invalid ), 'missing tvshow_id rejected' );
sm_preview_same( 'series_media_preview_invalid_input', $invalid->get_error_code(), 'invalid input error code' );

$tmdb_mismatch = Movies_WP_Series_Media_Preview_Service::build(
	array(
		'tvshow_id'         => 50,
		'expected_tmdb_id'  => 111,
		'series_directory'  => 'Series/korea/2024/Show',
	),
	sm_preview_options( sm_preview_episodes(), 900 )
);
sm_preview_assert( is_wp_error( $tmdb_mismatch ), 'expected tmdb mismatch rejected' );
sm_preview_same( 'series_media_preview_tmdb_mismatch', $tmdb_mismatch->get_error_code(), 'tmdb mismatch code' );

echo "Series media preview strict episode lookup\n";

$preview = Movies_WP_Series_Media_Preview_Service::build(
	array(
		'tvshow_id'        => 50,
		'series_directory' => 'Series/korea/2024/Show',
	),
	sm_preview_options()
);
sm_preview_assert( is_array( $preview ), 'valid preview builds' );
sm_preview_same( 'series_media', $preview['type'], 'preview type is series_media' );
sm_preview_same( false, $preview['ready_to_import'], 'missing episode blocks import readiness' );
sm_preview_same( 'matched', $preview['episodes'][0]['status'], 'S01E01 matches existing episode' );
sm_preview_same( 101, $preview['episodes'][0]['episode_id'], 'matched episode id resolved' );
sm_preview_same( 'missing_episode', $preview['episodes'][1]['status'], 'S01E02 is missing' );
sm_preview_assert( ! empty( $preview['validation']['errors'] ), 'missing episode produces error' );

$ambiguous_options = sm_preview_options(
	array(
		array(
			'id'             => 101,
			'tvshow_id'      => 50,
			'season_number'  => '1',
			'episode_number' => '1',
		),
		array(
			'id'             => 102,
			'tvshow_id'      => 50,
			'season_number'  => '1',
			'episode_number' => '1',
		),
	)
);
$ambiguous = Movies_WP_Series_Media_Preview_Service::build(
	array(
		'tvshow_id'        => 50,
		'series_directory' => 'Series/korea/2024/Show',
	),
	$ambiguous_options
);
sm_preview_same( 'ambiguous_episode', $ambiguous['episodes'][0]['status'], 'duplicate episode rows are ambiguous' );

echo "Series media preview canonical identity\n";

sm_preview_same( '0', Movies_WP_Series_Media_Preview_Service::canonical_season_string( 0 ), 'season zero canonicalizes' );
sm_preview_same( '1', Movies_WP_Series_Media_Preview_Service::canonical_episode_string( 'E01' ), 'episode E01 canonicalizes' );
sm_preview_assert( null === Movies_WP_Series_Media_Preview_Service::canonical_episode_string( 0 ), 'episode zero rejected' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series media preview tests passed.\n";
exit( $failures ? 1 : 0 );

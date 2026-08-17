<?php
/**
 * CLI tests for Movies_WP_Streamit_Episode_Media_Adapter.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-streamit-episode-media-adapter-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-episode-media-adapter-test/' );
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
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}
		$out = @unserialize( $value );
		return false === $out && 'b:0;' !== $value ? $value : $out;
	}
}
if ( ! function_exists( 'maybe_serialize' ) ) {
	function maybe_serialize( $data ) {
		return serialize( $data );
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		unset( $type );
		return '2026-08-17 10:00:00';
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
require_once dirname( __DIR__ ) . '/class-movies-wp-streamit-episode-media-adapter.php';

$failures = 0;

/** @var array<int, array<string, mixed>> */
$GLOBALS['episode_meta_store'] = array();

/** @var array<string, int> */
$GLOBALS['forbidden_writes'] = array(
	'_player' => 0,
	'title'     => 0,
);

function ema_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function ema_same( $expected, $actual, string $label ): void {
	ema_assert( $expected === $actual, $label . ' got=' . var_export( $actual, true ) );
}

/**
 * @return array<string, mixed>
 */
function ema_plan(): array {
	return array(
		'warnings' => array(),
		'episodes' => array(
			array(
				'episode_id'     => 101,
				'tvshow_id'      => 50,
				'season_number'  => '1',
				'episode_number' => '1',
				'tmdb_id'        => 900,
				'ownership'      => array(
					'tvshow_id'      => 50,
					'season_number'  => '1',
					'episode_number' => '1',
					'tmdb_id'        => 900,
				),
				'operations'     => array(
					'_sources'   => array(
						array(
							'action' => 'preserve',
							'path'   => 'series/korea/2024/Show/manual.mkv',
							'row'    => array(
								'name'             => 'Manual',
								'link'             => 'series/korea/2024/Show/manual.mkv',
								'custom_flag'      => 'keep-me',
								'download_content' => 'series/korea/2024/Show/manual.mkv',
							),
						),
						array(
							'action' => 'upsert',
							'path'   => 'series/korea/2024/Show/720p/S01E01.mkv',
							'row'    => array(
								'name'             => '',
								'link'             => 'series/korea/2024/Show/720p/S01E01.mkv',
								'is_affiliate'     => '0',
								'quality'          => '720p',
								'language'         => '',
								'player'           => '',
								'date_added'       => '{{import_date}}',
								'download_content' => 'series/korea/2024/Show/720p/S01E01.mkv',
								'file_size'        => '1.0 GB',
							),
						),
					),
					'_subtitles' => array(
						array(
							'action' => 'preserve',
							'path'   => 'series/korea/2024/Show/SUB.FA/S01E01.srt',
							'row'    => array(
								'label'   => 'FA',
								'url'     => 'series/korea/2024/Show/SUB.FA/S01E01.srt',
								'default' => 1,
							),
						),
						array(
							'action' => 'upsert',
							'path'   => 'series/korea/2024/Show/SUB.ENG/S01E01.srt',
							'row'    => array(
								'label'   => 'ENG',
								'srclang' => 'en',
								'url'     => 'series/korea/2024/Show/SUB.ENG/S01E01.srt',
								'default' => 0,
								'format'  => 'SRT',
							),
						),
					),
				),
			),
		),
	);
}

/**
 * @param bool $readback_fail
 * @param bool $with_duplicate_sources
 * @return array<string, mixed>
 */
function ema_options( bool $readback_fail = false, bool $with_duplicate_sources = false ): array {
	$sources = array(
		array(
			'name'             => 'Manual',
			'link'             => 'series/korea/2024/Show/manual.mkv',
			'custom_flag'      => 'keep-me',
			'download_content' => 'series/korea/2024/Show/manual.mkv',
		),
	);
	if ( $with_duplicate_sources ) {
		$sources[] = array(
			'name'             => 'Dup',
			'link'             => 'series/korea/2024/Show/dup.mkv',
			'download_content' => 'series/korea/2024/Show/dup.mkv',
		);
		$sources[] = array(
			'name'             => 'Dup',
			'link'             => 'series/korea/2024/Show/dup.mkv',
			'download_content' => 'series/korea/2024/Show/dup.mkv',
		);
	}

	$GLOBALS['episode_meta_store'][101] = array(
		'tvshow_id'       => 50,
		'_season_number'  => '1',
		'_episode_number' => '1',
		'_tmdb_id'        => 900,
		'_sources'        => $sources,
		'_subtitles'      => array(
			array(
				'label'   => 'FA',
				'url'     => 'series/korea/2024/Show/SUB.FA/S01E01.srt',
				'default' => 1,
			),
		),
	);

	return array(
		'now_local'           => '2026-08-17 10:00:00',
		'get_episode'           => static function ( int $id ): array {
			return array( 'id' => $id );
		},
		'get_episode_meta'      => static function ( int $id, string $key ) {
			return $GLOBALS['episode_meta_store'][ $id ][ $key ] ?? null;
		},
		'update_episode_meta'   => static function ( int $id, string $key, $value ) use ( $readback_fail ): bool {
			if ( isset( $GLOBALS['forbidden_writes'][ $key ] ) ) {
				++$GLOBALS['forbidden_writes'][ $key ];
			}
			if ( ! in_array( $key, array( '_sources', '_subtitles' ), true ) ) {
				return false;
			}
			$GLOBALS['episode_meta_store'][ $id ][ $key ] = $value;
			if ( $readback_fail && '_sources' === $key ) {
				$GLOBALS['episode_meta_store'][ $id ][ $key ] = array();
			}
			return true;
		},
	);
}

echo "Episode media adapter ownership and duplicate guards\n";

$duplicate_options = ema_options( false, true );
$duplicate_plan    = ema_plan();
$result            = Movies_WP_Streamit_Episode_Media_Adapter::apply( $duplicate_plan, $duplicate_options );
ema_same( false, $result['ok'], 'duplicate existing source path hard-fails' );
ema_same( 0, $result['completed'], 'duplicate existing source path completes zero episodes' );

$conflict_options = ema_options();
$conflict_plan    = ema_plan();
$conflict_plan['episodes'][0]['tvshow_id'] = 999;
$conflict = Movies_WP_Streamit_Episode_Media_Adapter::apply( $conflict_plan, $conflict_options );
ema_same( false, $conflict['ok'], 'ownership conflict rejected' );
ema_same( 'episode_ownership_conflict', $conflict['errors'][0]['code'] ?? '', 'ownership conflict code stable' );

echo "Episode media adapter merge behavior\n";

$success = Movies_WP_Streamit_Episode_Media_Adapter::apply( ema_plan(), ema_options() );
ema_same( true, $success['ok'], 'valid plan applies successfully' );
ema_same( 1, $success['completed'], 'one episode completed' );

$sources = $GLOBALS['episode_meta_store'][101]['_sources'];
ema_same( 2, count( $sources ), 'merged sources preserve order with upsert append' );
ema_same( 'keep-me', $sources[0]['custom_flag'], 'unknown manual fields retained' );
ema_same( 'series/korea/2024/Show/720p/S01E01.mkv', $sources[1]['link'], 'new source appended with lowercase relative path' );
ema_same( '2026-08-17 10:00:00', $sources[1]['date_added'], 'import date placeholder resolved' );
ema_assert( ! str_contains( (string) $sources[1]['link'], 'https://' ), 'no signed URL persisted in sources' );

$subtitles = $GLOBALS['episode_meta_store'][101]['_subtitles'];
ema_same( 2, count( $subtitles ), 'subtitles merged with preserve + upsert' );
ema_same( 1, $subtitles[0]['default'], 'existing default subtitle preserved on upsert' );

ema_same( 0, $GLOBALS['forbidden_writes']['_player'], 'adapter never writes player meta' );
ema_same( 0, $GLOBALS['forbidden_writes']['title'], 'adapter never writes unrelated meta' );

echo "Episode media adapter partial readback failure\n";

$partial = Movies_WP_Streamit_Episode_Media_Adapter::apply( ema_plan(), ema_options( true ) );
ema_same( false, $partial['ok'], 'readback mismatch yields failure' );
ema_same( true, $partial['partial'], 'readback mismatch marked partial' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll episode media adapter tests passed.\n";
exit( $failures ? 1 : 0 );

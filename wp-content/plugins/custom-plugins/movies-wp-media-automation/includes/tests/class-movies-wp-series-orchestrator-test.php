<?php
/**
 * Deterministic tests for Movies_WP_Series_Orchestrator.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-orchestrator-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-orchestrator-test/' );
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

require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-api-client.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-preview-service.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-orchestrator.php';

$failures = 0;

function orch_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function orch_same( $expected, $actual, string $label ): void {
	orch_assert(
		$expected === $actual,
		$label . ' expected=' . var_export( $expected, true ) . ' got=' . var_export( $actual, true )
	);
}

function orch_codes( array $issues ): array {
	$codes = array();
	foreach ( $issues as $issue ) {
		if ( is_array( $issue ) && isset( $issue['code'] ) ) {
			$codes[] = (string) $issue['code'];
		}
	}
	return $codes;
}

function orch_input(): array {
	return array(
		'tmdb_id'          => 100,
		'title'            => 'عنوان محلی',
		'summary'          => 'خلاصه',
		'series_directory' => 'series/korea/2024/Show',
	);
}

function orch_metadata_preview(): array {
	return array(
		'ok'     => true,
		'type'   => 'series',
		'input'  => array(
			'tmdb_id' => 100,
			'title'   => 'عنوان محلی',
			'summary' => 'خلاصه',
		),
		'series' => array(
			'tmdb_id' => 100,
			'name'    => 'TMDb Series',
			'seasons' => array(
				array(
					'season_number' => 1,
					'name'          => 'Season 1',
					'episodes'      => array(
						array(
							'tmdb_id'        => 9001,
							'season_number'  => 1,
							'episode_number' => 1,
							'name'           => 'Pilot',
						),
						array(
							'tmdb_id'        => 9002,
							'season_number'  => 1,
							'episode_number' => 2,
							'name'           => 'Second',
						),
					),
				),
			),
		),
		'validation'      => array(
			'errors'   => array(),
			'warnings' => array(),
		),
		'ready_to_import' => true,
	);
}

function orch_metadata_plan( string $action = 'create' ): array {
	return array(
		'ok'              => true,
		'type'            => 'series',
		'contract'        => array(
			'kind'      => 'series_import_plan',
			'version'   => 1,
			'read_only' => true,
		),
		'identity'        => array(
			'action'             => $action,
			'existing_series_id' => 'update' === $action ? 50 : null,
			'match_by'           => '_tmdb_id',
		),
		'series'          => array(
			'tmdb_id' => 100,
			'title'   => 'عنوان محلی',
			'summary' => 'خلاصه',
		),
		'images'          => array(),
		'sources_policy'  => array(
			'episode_meta_key' => '_sources',
			'mutate'           => false,
			'actions'          => array(),
		),
		'seasons'         => array(
			array(
				'action'        => $action,
				'season_number' => '1',
				'episodes'      => array(
					array(
						'action'         => $action,
						'tmdb_id'        => 9001,
						'season_number'  => '1',
						'episode_number' => 1,
						'name'           => 'Pilot',
						'sources_action' => 'keep_existing_untouched',
					),
					array(
						'action'         => $action,
						'tmdb_id'        => 9002,
						'season_number'  => '1',
						'episode_number' => 2,
						'name'           => 'Second',
						'sources_action' => 'keep_existing_untouched',
					),
				),
			),
		),
		'warnings'        => array(),
		'errors'          => array(),
		'ready_to_import' => true,
	);
}

function orch_scan( array $episodes = array() ): array {
	return array(
		'ok'        => true,
		'kind'      => 'series',
		'ready'     => true,
		'directory' => array(
			'path' => 'series/korea/2024/Show',
		),
		'files'     => array(),
		'episodes'  => $episodes,
		'warnings'  => array(),
		'errors'    => array(),
		'stats'     => array(
			'video_count'    => 1,
			'subtitle_count' => 1,
		),
	);
}

function orch_scan_episode( $season, $episode, bool $with_files = true ): array {
	$row = array(
		'season_number'  => (string) $season,
		'episode_number' => (string) $episode,
		'token'          => sprintf( 'S%02dE%02d', (int) $season, (int) $episode ),
		'sources'        => array(),
		'subtitles'      => array(),
	);
	if ( $with_files ) {
		$row['sources'][] = array(
			'media_path' => sprintf( 'series/korea/2024/Show/720p/S%02dE%02d.mkv', (int) $season, (int) $episode ),
			'quality'    => '720p',
		);
		$row['subtitles'][] = array(
			'media_path' => sprintf( 'series/korea/2024/Show/SUB.ENG/S%02dE%02d.srt', (int) $season, (int) $episode ),
			'extension'  => 'srt',
		);
	}
	return $row;
}

function orch_scan_episode_only( $episode, bool $with_files = true ): array {
	$row = array(
		'identity_type'  => 'episode_only',
		'season_number'  => null,
		'episode_number' => (string) $episode,
		'token'          => sprintf( 'EP%02d', (int) $episode ),
		'sources'        => array(),
		'subtitles'      => array(),
	);
	if ( $with_files ) {
		$row['sources'][] = array(
			'media_path' => sprintf( 'series/korea/2024/Show/720p/Show.EP%02d.mkv', (int) $episode ),
			'quality'    => '720p',
		);
		$row['subtitles'][] = array(
			'media_path' => sprintf( 'series/korea/2024/Show/SUB.ENG/Show.EP%02d.srt', (int) $episode ),
			'extension'  => 'srt',
		);
	}
	return $row;
}

function orch_media_preview( int $tvshow_id = 501, bool $episode_only = false ): array {
	$media_path = $episode_only
		? 'series/korea/2024/Show/720p/Show.EP01.mkv'
		: 'series/korea/2024/Show/720p/S01E01.mkv';
	return array(
		'ok'              => true,
		'type'            => 'series_media',
		'input'           => array(
			'tvshow_id'         => $tvshow_id,
			'expected_tmdb_id'  => 100,
			'series_directory'  => 'series/korea/2024/Show',
		),
		'episodes'        => array(
			array(
				'status'         => 'matched',
				'episode_id'     => 701,
				'tvshow_id'      => $tvshow_id,
				'season_number'  => '1',
				'episode_number' => '1',
				'tmdb_id'        => 9001,
				'sources'        => array(
					array( 'media_path' => $media_path ),
				),
				'subtitles'      => array(),
			),
		),
		'validation'      => array(
			'errors'   => array(),
			'warnings' => array(),
		),
		'ready_to_import' => true,
	);
}

function orch_media_plan( int $tvshow_id = 501, bool $episode_only = false ): array {
	$media_path = $episode_only
		? 'series/korea/2024/Show/720p/Show.EP01.mkv'
		: 'series/korea/2024/Show/720p/S01E01.mkv';
	return array(
		'ok'              => true,
		'type'            => 'series_media',
		'contract'        => array(
			'kind'      => 'series_media_import_plan',
			'version'   => 1,
			'read_only' => true,
		),
		'identity'        => array(
			'tvshow_id'         => $tvshow_id,
			'series_directory'  => 'series/korea/2024/Show',
			'expected_tmdb_id'  => 100,
		),
		'episodes'        => array(
			array(
				'episode_id'     => 701,
				'tvshow_id'      => $tvshow_id,
				'season_number'  => '1',
				'episode_number' => '1',
				'operations'     => array(
					'_sources'   => array(
						array(
							'action' => 'upsert',
							'path'   => $media_path,
						),
					),
					'_subtitles' => array(),
				),
			),
		),
		'warnings'        => array(),
		'errors'          => array(),
		'ready_to_import' => true,
	);
}

/**
 * @param array<string, int> $calls
 * @return array<string, mixed>
 */
function orch_options( array &$calls, array $scan_episodes, string $identity_action = 'create' ): array {
	$episode_only = 1 === count( $scan_episodes ) && 'episode_only' === ( $scan_episodes[0]['identity_type'] ?? '' );
	return array(
		'metadata_preview_build'   => static function ( array $values ) use ( &$calls ): array {
			++$calls['metadata_preview'];
			orch_same( array( 'tmdb_id', 'title', 'summary' ), array_keys( $values ), 'metadata preview receives metadata inputs only' );
			orch_assert( ! isset( $values['series_directory'] ), 'metadata preview does not receive directory' );
			return orch_metadata_preview();
		},
		'metadata_plan_build'      => static function ( array $preview ) use ( &$calls, $identity_action ): array {
			++$calls['metadata_plan'];
			orch_same( 100, $preview['series']['tmdb_id'], 'metadata plan receives TMDb preview' );
			return orch_metadata_plan( $identity_action );
		},
		'scan_series'              => static function ( string $directory ) use ( &$calls, $scan_episodes ): array {
			++$calls['scan'];
			orch_same( 'series/korea/2024/Show', $directory, 'scan receives normalized directory' );
			return orch_scan( $scan_episodes );
		},
		'metadata_import_execute'  => static function ( array $plan ) use ( &$calls, $identity_action ): array {
			++$calls['metadata_import'];
			orch_same( false, $plan['sources_policy']['mutate'], 'metadata import plan still forbids _sources' );
			orch_same( $identity_action, $plan['identity']['action'], 'metadata identity action preserved' );
			return array(
				'ok'        => true,
				'partial'   => false,
				'series_id' => 501,
				'action'    => $identity_action,
				'warnings'  => array(),
				'errors'    => array(),
				'series'    => array( 'ok' => true, 'series_id' => 501 ),
				'seasons'   => array(),
				'episodes'  => array(
					array(
						'ok'             => true,
						'action'         => $identity_action,
						'episode_id'     => 701,
						'season_number'  => '1',
						'episode_number' => 1,
					),
					array(
						'ok'             => true,
						'action'         => $identity_action,
						'episode_id'     => 702,
						'season_number'  => '1',
						'episode_number' => 2,
					),
				),
				'images'    => array(),
			);
		},
		'media_preview_build'      => static function ( array $values ) use ( &$calls, $episode_only ): array {
			++$calls['media_preview'];
			orch_same( 501, $values['tvshow_id'], 'rebuilt media preview uses live series id' );
			orch_same( 100, $values['expected_tmdb_id'], 'rebuilt media preview checks TMDb identity' );
			orch_same( 'series/korea/2024/Show', $values['series_directory'], 'rebuilt media preview uses lowercase series directory' );
			orch_assert( $calls['metadata_import'] > 0, 'media preview rebuild happens after metadata import' );
			return orch_media_preview( 501, $episode_only );
		},
		'media_plan_build'         => static function ( array $preview ) use ( &$calls, $episode_only ): array {
			++$calls['media_plan'];
			orch_same( 701, $preview['episodes'][0]['episode_id'], 'media plan uses newly created episode id' );
			if ( $episode_only ) {
				orch_same( 'series/korea/2024/Show/720p/Show.EP01.mkv', $preview['episodes'][0]['sources'][0]['media_path'], 'rebuilt media preview retains EP01 source' );
			}
			return orch_media_plan( 501, $episode_only );
		},
		'media_import_execute'     => static function ( array $plan ) use ( &$calls, $episode_only ): array {
			++$calls['media_import'];
			orch_same( 701, $plan['episodes'][0]['episode_id'], 'media import attaches to created episode id' );
			orch_same( array( '_sources', '_subtitles' ), array_keys( $plan['episodes'][0]['operations'] ), 'media plan only contains source and subtitle operations' );
			if ( $episode_only ) {
				orch_same( 'series/korea/2024/Show/720p/Show.EP01.mkv', $plan['episodes'][0]['operations']['_sources'][0]['path'], 'EP01 source attaches only after metadata creates the episode id' );
			}
			return array(
				'ok'        => true,
				'partial'   => false,
				'tvshow_id' => 501,
				'completed' => 1,
				'episodes'  => array(
					array(
						'ok'         => true,
						'episode_id' => 701,
					),
				),
				'errors'    => array(),
				'warnings'  => array(),
			);
		},
	);
}

echo "Series orchestrator preview matching\n";

$calls = array(
	'metadata_preview' => 0,
	'metadata_plan'    => 0,
	'scan'             => 0,
	'metadata_import'  => 0,
	'media_preview'    => 0,
	'media_plan'       => 0,
	'media_import'     => 0,
);
$preview = Movies_WP_Series_Orchestrator::build_preview(
	orch_input(),
	orch_options( $calls, array() )
);
orch_assert( is_array( $preview ), 'preview with TMDb episodes and no Streamit/media still builds' );
orch_same( 'series_automation', $preview['type'], 'combined preview type' );
orch_same( true, $preview['ready_to_import'], 'missing Streamit IDs do not block combined preview' );
orch_same( 'metadata_only', $preview['episodes'][0]['status'], 'TMDb episode without media is metadata_only' );
orch_same( 'metadata_only', $preview['episodes'][1]['status'], 'second TMDb episode without media is metadata_only' );
orch_same( 0, $calls['metadata_import'], 'preview never imports metadata' );
orch_same( 0, $calls['media_import'], 'preview never imports media' );

$calls = array(
	'metadata_preview' => 0,
	'metadata_plan'    => 0,
	'scan'             => 0,
	'metadata_import'  => 0,
	'media_preview'    => 0,
	'media_plan'       => 0,
	'media_import'     => 0,
);
$matched = Movies_WP_Series_Orchestrator::build_preview(
	orch_input(),
	orch_options( $calls, array( orch_scan_episode( 1, 1 ) ) )
);
orch_same( true, $matched['ready_to_import'], 'matching TMDb + media is ready' );
orch_same( 'metadata_and_media', $matched['episodes'][0]['status'], 'S01E01 has TMDb metadata and files' );
orch_same( 'metadata_only', $matched['episodes'][1]['status'], 'S01E02 remains metadata-only' );
orch_same( 1, $matched['episodes'][0]['source_count'], 'matched source count is copied from scan' );

$calls = array(
	'metadata_preview' => 0,
	'metadata_plan'    => 0,
	'scan'             => 0,
	'metadata_import'  => 0,
	'media_preview'    => 0,
	'media_plan'       => 0,
	'media_import'     => 0,
);
$episode_only = Movies_WP_Series_Orchestrator::build_preview(
	orch_input(),
	orch_options( $calls, array( orch_scan_episode_only( 1 ) ) )
);
orch_same( true, $episode_only['ready_to_import'], 'EP01 resolves against authoritative TMDb context' );
orch_same( '1', $episode_only['episodes'][0]['season_number'], 'EP01 resolves to TMDb season 1' );
orch_same( 'metadata_and_media', $episode_only['episodes'][0]['status'], 'resolved EP01 joins TMDb episode' );

$mixed = Movies_WP_Series_Orchestrator::build_preview(
	orch_input(),
	orch_options( $calls, array( orch_scan_episode( 1, 1 ), orch_scan_episode_only( 1 ) ) )
);
orch_same( true, $mixed['ready_to_import'], 'explicit S01E01 and EP01 files can coexist' );
orch_same( 2, $mixed['episodes'][0]['source_count'], 'explicit and resolved EP01 sources merge under TMDb episode' );
orch_same( 2, $mixed['episodes'][0]['subtitle_count'], 'explicit and resolved EP01 subtitles merge under TMDb episode' );

$episode_only_orphan = Movies_WP_Series_Orchestrator::build_preview(
	orch_input(),
	orch_options( $calls, array( orch_scan_episode_only( 9 ) ) )
);
orch_same( false, $episode_only_orphan['ready_to_import'], 'EP09 without TMDb episode blocks import' );
orch_assert( in_array( 'episode_only_without_authoritative_match', orch_codes( $episode_only_orphan['validation']['errors'] ), true ), 'EP09 never creates an episode from filename' );

$ep_orphan_calls = array(
	'metadata_preview' => 0,
	'metadata_plan'    => 0,
	'scan'             => 0,
	'metadata_import'  => 0,
	'media_preview'    => 0,
	'media_plan'       => 0,
	'media_import'     => 0,
);
$blocked_ep_import = Movies_WP_Series_Orchestrator::execute(
	orch_input(),
	orch_options( $ep_orphan_calls, array( orch_scan_episode_only( 9 ) ) )
);
orch_assert( is_wp_error( $blocked_ep_import ), 'EP09 import is rejected before persistence' );
orch_same( 'series_automation_not_ready', $blocked_ep_import->get_error_code(), 'EP09 cannot create an episode from filename' );
orch_same( 0, $ep_orphan_calls['metadata_import'], 'EP09 blocks before TMDb metadata persistence' );
orch_same( 0, $ep_orphan_calls['media_import'], 'EP09 blocks before media persistence' );

$calls = array(
	'metadata_preview' => 0,
	'metadata_plan'    => 0,
	'scan'             => 0,
	'metadata_import'  => 0,
	'media_preview'    => 0,
	'media_plan'       => 0,
	'media_import'     => 0,
);
$orphan = Movies_WP_Series_Orchestrator::build_preview(
	orch_input(),
	orch_options( $calls, array( orch_scan_episode( 1, 1 ), orch_scan_episode( 1, 9 ) ) )
);
orch_same( false, $orphan['ready_to_import'], 'media without TMDb episode blocks import' );
orch_assert( in_array( 'series_automation_media_without_tmdb_episode', orch_codes( $orphan['validation']['errors'] ), true ), 'orphan media is a hard error' );
$statuses = array();
foreach ( $orphan['episodes'] as $row ) {
	$statuses[ $row['season_number'] . ':' . $row['episode_number'] ] = $row['status'];
}
orch_same( 'media_without_tmdb', $statuses['1:9'], 'S01E09 is shown as media without TMDb' );

$calls = array(
	'metadata_preview' => 0,
	'metadata_plan'    => 0,
	'scan'             => 0,
	'metadata_import'  => 0,
	'media_preview'    => 0,
	'media_plan'       => 0,
	'media_import'     => 0,
);
$ambiguous = Movies_WP_Series_Orchestrator::build_preview(
	orch_input(),
	orch_options( $calls, array( orch_scan_episode( 1, 1 ), orch_scan_episode( 1, 1 ) ) )
);
orch_same( false, $ambiguous['ready_to_import'], 'duplicate scan identities block import' );
orch_assert( in_array( 'series_automation_ambiguous_scan_episode', orch_codes( $ambiguous['validation']['errors'] ), true ), 'duplicate S/E is a hard error' );

echo "Series orchestrator import sequencing\n";

$calls = array(
	'metadata_preview' => 0,
	'metadata_plan'    => 0,
	'scan'             => 0,
	'metadata_import'  => 0,
	'media_preview'    => 0,
	'media_plan'       => 0,
	'media_import'     => 0,
);
$result = Movies_WP_Series_Orchestrator::execute(
	orch_input(),
	orch_options( $calls, array( orch_scan_episode( 1, 1 ) ) )
);
orch_assert( is_array( $result ) && true === $result['ok'], 'successful create import returns combined result' );
orch_same( 501, $result['series_id'], 'combined result exposes created series id' );
orch_same( 1, $calls['metadata_import'], 'metadata import runs once' );
orch_same( 1, $calls['media_preview'], 'media preview is rebuilt after metadata' );
orch_same( 1, $calls['media_plan'], 'media plan is rebuilt after metadata' );
orch_same( 1, $calls['media_import'], 'media import runs once after rebuild' );
orch_same( 'completed', $result['stages']['metadata'], 'metadata stage completed' );
orch_same( 'completed', $result['stages']['media'], 'media stage completed' );
orch_same( 701, $result['episodes'][0]['episode_id'], 'metadata result episode ids are preserved' );
orch_same( 701, $result['media_episodes'][0]['episode_id'], 'media result uses created episode ids' );

$calls = array(
	'metadata_preview' => 0,
	'metadata_plan'    => 0,
	'scan'             => 0,
	'metadata_import'  => 0,
	'media_preview'    => 0,
	'media_plan'       => 0,
	'media_import'     => 0,
);
$episode_only_result = Movies_WP_Series_Orchestrator::execute(
	orch_input(),
	orch_options( $calls, array( orch_scan_episode_only( 1 ) ) )
);
orch_assert( is_array( $episode_only_result ) && true === $episode_only_result['ok'], 'fresh EP01 import completes after authoritative Season 1 resolution' );
orch_same( 1, $calls['metadata_import'], 'EP01 metadata creates the authoritative episode first' );
orch_same( 1, $calls['media_preview'], 'EP01 media rematches against live episode ids' );
orch_same( 1, $calls['media_import'], 'EP01 source attaches after rematching' );

$calls = array(
	'metadata_preview' => 0,
	'metadata_plan'    => 0,
	'scan'             => 0,
	'metadata_import'  => 0,
	'media_preview'    => 0,
	'media_plan'       => 0,
	'media_import'     => 0,
);
$updated = Movies_WP_Series_Orchestrator::execute(
	orch_input(),
	orch_options( $calls, array( orch_scan_episode( 1, 1 ) ), 'update' )
);
orch_assert( is_array( $updated ) && true === $updated['ok'], 'existing series can still be updated' );
orch_same( 'update', $updated['action'], 'update identity is preserved' );

$calls = array(
	'metadata_preview' => 0,
	'metadata_plan'    => 0,
	'scan'             => 0,
	'metadata_import'  => 0,
	'media_preview'    => 0,
	'media_plan'       => 0,
	'media_import'     => 0,
);
$fail_options = orch_options( $calls, array( orch_scan_episode( 1, 1 ) ) );
$fail_options['metadata_import_execute'] = static function ( array $plan ) use ( &$calls ): array {
	++$calls['metadata_import'];
	unset( $plan );
	return array(
		'ok'        => false,
		'partial'   => false,
		'series_id' => null,
		'action'    => 'create',
		'warnings'  => array(),
		'errors'    => array(
			array(
				'code'    => 'series_tv_adapter_series_create_failed',
				'message' => 'create failed',
			),
		),
		'series'    => array(),
		'seasons'   => array(),
		'episodes'  => array(),
		'images'    => array(),
	);
};
$failed = Movies_WP_Series_Orchestrator::execute( orch_input(), $fail_options );
orch_same( false, $failed['ok'], 'metadata failure is not successful' );
orch_same( 1, $calls['metadata_import'], 'metadata import still ran' );
orch_same( 0, $calls['media_preview'], 'metadata failure skips media rebuild' );
orch_same( 0, $calls['media_import'], 'metadata failure skips media import' );
orch_same( 'skipped', $failed['stages']['media'], 'media stage is skipped' );

$calls = array(
	'metadata_preview' => 0,
	'metadata_plan'    => 0,
	'scan'             => 0,
	'metadata_import'  => 0,
	'media_preview'    => 0,
	'media_plan'       => 0,
	'media_import'     => 0,
);
$rebuild_fail = orch_options( $calls, array( orch_scan_episode( 1, 1 ) ) );
$rebuild_fail['media_preview_build'] = static function ( array $values ) use ( &$calls ) {
	++$calls['media_preview'];
	unset( $values );
	return new WP_Error( 'series_media_preview_tvshow_not_found', 'TV show not found after create.' );
};
$partial = Movies_WP_Series_Orchestrator::execute( orch_input(), $rebuild_fail );
orch_same( false, $partial['ok'], 'media rebuild failure is not successful' );
orch_same( true, $partial['partial'], 'metadata success with media rebuild failure is partial' );
orch_same( 501, $partial['series_id'], 'partial result still reports created series id' );
orch_same( 0, $calls['media_import'], 'failed media rebuild does not attach files' );

$blocked = Movies_WP_Series_Orchestrator::execute(
	orch_input(),
	orch_options( $calls, array( orch_scan_episode( 1, 9 ) ) )
);
orch_assert( is_wp_error( $blocked ), 'import of media without TMDb is rejected before persistence' );
orch_same( 'series_automation_not_ready', $blocked->get_error_code(), 'unready combined preview blocks import' );

echo "Series orchestrator bootstrap contract\n";

$bootstrap = (string) file_get_contents( dirname( __DIR__, 2 ) . '/movies-wp-media-automation.php' );
orch_assert( str_contains( $bootstrap, 'Movies_WP_Series_Admin::init();' ), 'Series Automation remains registered' );
orch_assert( ! str_contains( $bootstrap, 'Movies_WP_Series_Media_Admin::init();' ), 'Series Media Automation submenu is no longer registered' );
orch_assert( str_contains( $bootstrap, 'class-movies-wp-series-orchestrator.php' ), 'orchestrator is loaded' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series orchestrator tests passed.\n";
exit( $failures ? 1 : 0 );

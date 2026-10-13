<?php
/**
 * Comprehensive Phase 2 test suite for Series Automation import pipeline.
 *
 * Covers:
 * 1. TMDB lists S1–S3, only S3 scanned: only S3 imported as available content.
 * 2. 10 TMDB episodes, 4 scanned: only 4 created/updated.
 * 3. Incremental season import from different year dir: series post updated, existing season/sources preserved.
 * 4. Re-running identical import creates no duplicates.
 * 5. S03E01, S03.E01, S03 E01, S3E1 correctly identified.
 * 6. Episode-only filenames require explicit season number when no reliable season can otherwise be determined.
 * 7. Conflicting season identifiers rejected with clear error.
 * 8. Unknown media category folders produce preview warnings.
 * 9. Two release variants at same resolution remain distinguishable in download box.
 * 10. Stale create plan cannot create duplicate series if TMDb ID now exists.
 * 11. Existing movie-import behavior and existing manual download sources remain intact.
 *
 * Run:
 *   php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-phase2-import-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-phase2-test/' );
}
if ( ! defined( 'MOVIES_WP_SERIES_IMPORT_TEST_MEMORY' ) ) {
	define( 'MOVIES_WP_SERIES_IMPORT_TEST_MEMORY', true );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code, $message, $data = null ) {
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
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) {
		return $value;
	}
}

class TV_Adapter_Object {
	private $data;
	public function __construct( array $data ) { $this->data = $data; }
	private function value( $key ) { return $this->data[ $key ]; }
	public function get_post_author() { return $this->value( 'post_author' ); }
	public function get_post_date() { return $this->value( 'post_date' ); }
	public function get_post_date_gmt() { return $this->value( 'post_date_gmt' ); }
	public function get_post_content() { return $this->value( 'post_content' ); }
	public function get_post_title() { return $this->value( 'post_title' ); }
	public function get_post_excerpt() { return $this->value( 'post_excerpt' ); }
	public function get_post_status() { return $this->value( 'post_status' ); }
	public function get_comment_status() { return $this->value( 'comment_status' ); }
	public function get_ping_status() { return $this->value( 'ping_status' ); }
	public function get_post_password() { return $this->value( 'post_password' ); }
	public function get_post_name() { return $this->value( 'post_name' ); }
	public function get_to_ping() { return $this->value( 'to_ping' ); }
	public function get_pinged() { return $this->value( 'pinged' ); }
	public function get_post_modified() { return $this->value( 'post_modified' ); }
	public function get_post_modified_gmt() { return $this->value( 'post_modified_gmt' ); }
	public function get_post_content_filtered() { return $this->value( 'post_content_filtered' ); }
	public function get_post_parent() { return $this->value( 'post_parent' ); }
	public function get_guid() { return $this->value( 'guid' ); }
	public function get_menu_order() { return $this->value( 'menu_order' ); }
	public function get_post_type() { return $this->value( 'post_type' ); }
	public function get_post_mime_type() { return $this->value( 'post_mime_type' ); }
	public function get_comment_count() { return $this->value( 'comment_count' ); }
}

function tv_adapter_fields( string $type, string $title, string $content = '' ): array {
	return array(
		'post_author' => 1, 'post_date' => '2020-01-01 00:00:00', 'post_date_gmt' => '2020-01-01 00:00:00',
		'post_content' => $content, 'post_title' => $title, 'post_excerpt' => '', 'post_status' => 'publish',
		'comment_status' => 'open', 'ping_status' => 'open', 'post_password' => '', 'post_name' => 'existing',
		'to_ping' => '', 'pinged' => '', 'post_modified' => '2020-01-01 00:00:00',
		'post_modified_gmt' => '2020-01-01 00:00:00', 'post_content_filtered' => '', 'post_parent' => 0,
		'guid' => '', 'menu_order' => 0, 'post_type' => $type, 'post_mime_type' => '', 'comment_count' => 0,
	);
}

require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-api-client.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-preview-service.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-plan.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-import-plan.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-orchestrator.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-streamit-tv-adapter.php';
$root_dir = dirname( __DIR__, 6 );
require_once $root_dir . '/media-server/lib/series-episode-identity.php';
require_once $root_dir . '/media-server/lib/series-category.php';
require_once $root_dir . '/media-server/lib/series-list.php';
require_once $root_dir . '/wp-content/themes/streamit-child/inc/sources-download.php';
require_once $root_dir . '/wp-content/themes/streamit-child/inc/series-download.php';

$failures = 0;

function p2_assert( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function p2_same( $expected, $actual, string $label ): void {
	global $failures;
	if ( $expected === $actual ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label} (expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ")\n";
}

echo "=== Requirement 5: Delimited filename patterns ===\n";
$f1 = media_parse_series_episode_identity( 'Show.S03E01.mkv' );
p2_same( true, $f1['ok'] ?? false, 'S03E01 parsed ok' );
p2_same( '3', $f1['season_number'] ?? '', 'S03E01 season 3' );
p2_same( '1', $f1['episode_number'] ?? '', 'S03E01 episode 1' );

$f2 = media_parse_series_episode_identity( 'Show.S03.E01.mkv' );
p2_same( true, $f2['ok'] ?? false, 'S03.E01 parsed ok' );
p2_same( '3', $f2['season_number'] ?? '', 'S03.E01 season 3' );
p2_same( '1', $f2['episode_number'] ?? '', 'S03.E01 episode 1' );

$f3 = media_parse_series_episode_identity( 'Show.S03 E01.mkv' );
p2_same( true, $f3['ok'] ?? false, 'S03 E01 parsed ok' );
p2_same( '3', $f3['season_number'] ?? '', 'S03 E01 season 3' );
p2_same( '1', $f3['episode_number'] ?? '', 'S03 E01 episode 1' );

$f4 = media_parse_series_episode_identity( 'Show.S3E1.mkv' );
p2_same( true, $f4['ok'] ?? false, 'S3E1 parsed ok' );
p2_same( '3', $f4['season_number'] ?? '', 'S3E1 season 3' );
p2_same( '1', $f4['episode_number'] ?? '', 'S3E1 episode 1' );

echo "\n=== Requirement 6 & 7: Episode-only season requirements & conflicts ===\n";
// Setup authoritative episodes with 2 seasons: S1 has 10 eps, S2 has 3 eps
$two_season_episodes = array();
for ( $e = 1; $e <= 10; ++$e ) {
	$two_season_episodes[] = array( 'season_number' => '1', 'episode_number' => (string) $e, 'name' => "S1E{$e}" );
}
for ( $e = 1; $e <= 3; ++$e ) {
	$two_season_episodes[] = array( 'season_number' => '2', 'episode_number' => (string) $e, 'name' => "S2E{$e}" );
}

// 6. Episode-only without explicit season on multi-season show fails without guessing
$ep5_scan = array(
	array(
		'identity_type'  => 'episode_only',
		'season_number'  => null,
		'episode_number' => '5',
		'token'          => 'EP05',
		'sources'        => array(),
		'subtitles'      => array(),
	),
);
$res_no_season = Movies_WP_Series_Media_Preview_Service::resolve_scan_episode_groups(
	$ep5_scan,
	$two_season_episodes,
	null
);
p2_same( 0, count( $res_no_season['episodes'] ), 'EP05 without explicit season produces no resolved episode' );
p2_same( 1, count( $res_no_season['errors'] ), 'EP05 without explicit season produces 1 validation error' );
p2_same( 'episode_only_requires_explicit_season', $res_no_season['errors'][0]['code'] ?? '', 'EP05 requires explicit season error code' );

// Providing explicit season 1 resolves EP05 cleanly
$res_with_season = Movies_WP_Series_Media_Preview_Service::resolve_scan_episode_groups(
	$ep5_scan,
	$two_season_episodes,
	'1'
);
p2_same( 1, count( $res_with_season['episodes'] ), 'EP05 with explicit season 1 resolves' );
p2_same( '1', $res_with_season['episodes'][0]['season_number'] ?? '', 'EP05 resolved season is 1' );
p2_same( '5', $res_with_season['episodes'][0]['episode_number'] ?? '', 'EP05 resolved episode is 5' );
p2_same( 0, count( $res_with_season['errors'] ), 'EP05 with explicit season 1 has no errors' );

// 7. Conflicting season identifiers are rejected
$s1e1_scan = array(
	array(
		'identity_type'  => 'season_episode',
		'season_number'  => '1',
		'episode_number' => '1',
		'token'          => 'S01E01',
		'sources'        => array(),
		'subtitles'      => array(),
	),
);
$res_conflict = Movies_WP_Series_Media_Preview_Service::resolve_scan_episode_groups(
	$s1e1_scan,
	$two_season_episodes,
	'2' // Operator explicitly gave season 2, but file is S01E01
);
p2_same( 0, count( $res_conflict['episodes'] ), 'conflicting season produces no resolved episodes' );
p2_same( 1, count( $res_conflict['errors'] ), 'conflicting season produces error' );
p2_same( 'series_automation_season_conflict', $res_conflict['errors'][0]['code'] ?? '', 'explicit conflict error code' );

echo "\n=== Requirement 8: Unknown media category folders produce preview warnings ===\n";
$unknown_category = media_classify_series_category( 'Extras' );
p2_same( 'UNKNOWN', $unknown_category['type'], 'Extras classified as UNKNOWN' );
p2_same( 1, count( $unknown_category['warnings'] ), 'UNKNOWN category produces warning' );
p2_same( 'unknown_category', $unknown_category['warnings'][0]['code'], 'warning code is unknown_category' );

$season_folder = media_classify_series_category( 'Season 3' );
p2_same( 'UNKNOWN', $season_folder['type'], 'Season 3 folder classified as UNKNOWN (not release dir)' );
p2_same( 'unknown_category', $season_folder['warnings'][0]['code'], 'Season 3 produces unknown_category warning' );

echo "\n=== Requirement 1 & 2: Media Host is Source of Truth for Seasons & Episodes ===\n";
// TMDb lists S1 (10 eps), S2 (10 eps), S3 (10 eps)
$tmdb_seasons = array();
for ( $s = 1; $s <= 3; ++$s ) {
	$eps = array();
	for ( $e = 1; $e <= 10; ++$e ) {
		$eps[] = array(
			'season_number'  => (string) $s,
			'episode_number' => $e,
			'name'           => "Season {$s} Episode {$e}",
			'tmdb_id'        => ( $s * 1000 ) + $e,
		);
	}
	$tmdb_seasons[] = array(
		'season_number' => (string) $s,
		'name'          => "Season {$s}",
		'episodes'      => $eps,
	);
}

$metadata_preview_fixture = array(
	'ok'              => true,
	'type'            => 'series',
	'ready_to_import' => true,
	'input'           => array(
		'tmdb_id'          => 777,
		'title'            => 'My Series',
		'summary'          => 'Overview',
		'series_directory' => 'series/korea/2024/My.Series',
	),
	'series'          => array(
		'tmdb_id' => 777,
		'title'   => 'My Series',
		'summary' => 'Overview',
		'seasons' => $tmdb_seasons,
	),
	'validation'      => array( 'errors' => array(), 'warnings' => array() ),
);

// Media directory contains ONLY S03 with 4 episodes (episodes 1 to 4)
$scan_fixture = array(
	'ok'        => true,
	'kind'      => 'series',
	'ready'     => true,
	'directory' => array( 'path' => 'series/korea/2024/My.Series' ),
	'errors'    => array(),
	'warnings'  => array(),
	'episodes'  => array(
		array(
			'identity_type'  => 'season_episode',
			'season_number'  => '3',
			'episode_number' => '1',
			'token'          => 'S03E01',
			'sources'        => array( array( 'media_path' => 'series/korea/2024/My.Series/1080p WEB-DL/S03E01.mkv', 'quality' => '1080p', 'source_type' => 'WEB-DL' ) ),
			'subtitles'      => array(),
		),
		array(
			'identity_type'  => 'season_episode',
			'season_number'  => '3',
			'episode_number' => '2',
			'token'          => 'S03E02',
			'sources'        => array( array( 'media_path' => 'series/korea/2024/My.Series/1080p WEB-DL/S03E02.mkv', 'quality' => '1080p', 'source_type' => 'WEB-DL' ) ),
			'subtitles'      => array(),
		),
		array(
			'identity_type'  => 'season_episode',
			'season_number'  => '3',
			'episode_number' => '3',
			'token'          => 'S03E03',
			'sources'        => array( array( 'media_path' => 'series/korea/2024/My.Series/1080p WEB-DL/S03E03.mkv', 'quality' => '1080p', 'source_type' => 'WEB-DL' ) ),
			'subtitles'      => array(),
		),
		array(
			'identity_type'  => 'season_episode',
			'season_number'  => '3',
			'episode_number' => '4',
			'token'          => 'S03E04',
			'sources'        => array( array( 'media_path' => 'series/korea/2024/My.Series/1080p WEB-DL/S03E04.mkv', 'quality' => '1080p', 'source_type' => 'WEB-DL' ) ),
			'subtitles'      => array(),
		),
	),
);

// 1. Build preview with Orchestrator
$orch_options = array(
	'metadata_preview_build' => static function () use ( $metadata_preview_fixture ) {
		return $metadata_preview_fixture;
	},
	'scan_series'            => static function () use ( $scan_fixture ) {
		return $scan_fixture;
	},
	'find_series_by_tmdb'    => static function () {
		return array(); // New series
	},
);

$preview = Movies_WP_Series_Orchestrator::build_preview(
	array(
		'tmdb_id'          => 777,
		'title'            => 'My Series',
		'summary'          => 'Overview',
		'series_directory' => 'series/korea/2024/My.Series',
	),
	$orch_options
);

p2_same( true, $preview['ready_to_import'], 'orchestrator preview is ready' );

// Verify import plan only contains Season 3 and only 4 episodes!
$plan = $preview['metadata_plan'];
p2_same( 1, count( $plan['seasons'] ), 'Import plan contains ONLY 1 season (Season 3), omitting unscanned S1 and S2' );
p2_same( '3', $plan['seasons'][0]['season_number'], 'Planned season is Season 3' );
p2_same( 4, count( $plan['seasons'][0]['episodes'] ), 'Planned season contains ONLY the 4 scanned episodes' );

// Verify preview reporting of unavailable episodes as informational metadata_only with action => null
$episodes = $preview['episodes'];
p2_same( 30, count( $episodes ), 'Preview reports all 30 TMDb episodes' );

$matched_count       = 0;
$metadata_only_count = 0;
foreach ( $episodes as $ep ) {
	if ( 'metadata_and_media' === $ep['status'] ) {
		++$matched_count;
		p2_assert( null !== $ep['action'] && '' !== $ep['action'], "scanned episode S{$ep['season_number']}E{$ep['episode_number']} has valid action" );
	} elseif ( 'metadata_only' === $ep['status'] ) {
		++$metadata_only_count;
		p2_same( null, $ep['action'], "unscanned episode S{$ep['season_number']}E{$ep['episode_number']} has null action" );
	}
}
p2_same( 4, $matched_count, 'exactly 4 matched episodes with media' );
p2_same( 26, $metadata_only_count, 'exactly 26 informational metadata_only episodes' );

// Execute the plan with TV adapter to verify database writes
$persisted_series   = array();
$persisted_episodes = array();
$persisted_meta     = array();

$adapter_options = array(
	'create_tvshow'       => static function ( array $row ) use ( &$persisted_series ): int {
		$persisted_series = $row;
		return 500;
	},
	'get_tvshow'          => static function ( int $id ) use ( &$persisted_series ) {
		return 500 === $id ? new TV_Adapter_Object( tv_adapter_fields( 'tvshow', $persisted_series['post_title'] ?? 'My Series', $persisted_series['post_content'] ?? '' ) ) : null;
	},
	'update_tvshow_row'   => static function ( int $id, array $row ) use ( &$persisted_series ) {
		$persisted_series = $row;
		return true;
	},
	'create_episode'      => static function ( array $row ) use ( &$persisted_episodes ): int {
		static $id = 1000;
		++$id;
		$persisted_episodes[ $id ] = $row;
		return $id;
	},
	'get_episode'         => static function ( int $id ) use ( &$persisted_episodes ) {
		return isset( $persisted_episodes[ $id ] ) ? new TV_Adapter_Object( tv_adapter_fields( 'episode', $persisted_episodes[ $id ]['post_title'] ?? 'Episode' ) ) : null;
	},
	'update_episode_row'  => static function ( int $id, array $row ) use ( &$persisted_episodes ) {
		$persisted_episodes[ $id ] = $row;
		return true;
	},
	'update_tvshow_meta'  => static function ( int $id, string $key, $value ) use ( &$persisted_meta ): bool {
		$persisted_meta["series_{$id}_{$key}"] = $value;
		return true;
	},
	'get_tvshow_meta'     => static function ( int $id, string $key ) use ( &$persisted_meta ) {
		return $persisted_meta["series_{$id}_{$key}"] ?? null;
	},
	'update_episode_meta' => static function ( int $id, string $key, $value ) use ( &$persisted_meta ): bool {
		$persisted_meta["episode_{$id}_{$key}"] = $value;
		return true;
	},
	'get_episode_meta'    => static function ( int $id, string $key ) use ( &$persisted_meta ) {
		return $persisted_meta["episode_{$id}_{$key}"] ?? null;
	},
	'find_existing_series' => static function () {
		return null;
	},
);

$apply_series = Movies_WP_Streamit_TV_Adapter::apply_series_phase( $plan, $adapter_options );
p2_same( true, $apply_series['ok'] ?? false, 'apply_series_phase succeeds' );
p2_same( 500, $apply_series['series_id'] ?? 0, 'series id is 500' );

$created_ep_ids = array();
foreach ( $plan['seasons'][0]['episodes'] as $ep_plan ) {
	$ep_res = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 500, $ep_plan, $adapter_options );
	p2_same( true, $ep_res['ok'] ?? false, "episode S{$ep_plan['season_number']}E{$ep_plan['episode_number']} created" );
	$created_ep_ids[] = (int) $ep_res['episode_id'];
}
p2_same( 4, count( $created_ep_ids ), 'exactly 4 episodes created in database' );

$apply_seasons = Movies_WP_Streamit_TV_Adapter::apply_seasons_phase( 500, $plan, array( '3' => $created_ep_ids ), $adapter_options );
p2_same( true, $apply_seasons['ok'] ?? false, 'apply_seasons_phase succeeds' );

$stored_seasons = $persisted_meta['series_500__seasons'] ?? array();
p2_same( 1, count( $stored_seasons ), 'stored _seasons contains ONLY 1 season' );
p2_same( '3', $stored_seasons[0]['season_number'], 'stored season is Season 3' );
p2_same( $created_ep_ids, $stored_seasons[0]['episodes'], 'stored Season 3 contains only the 4 created episode IDs' );

echo "\n=== Requirement 3, 4 & 8: Incremental season import from new year dir & idempotency ===\n";
// Existing series has Season 3 (persisted above). Now we import Season 4 from 2026 directory:
// series/korea/2026/My.Series with S04E01.
$scan_s4 = array(
	'ok'        => true,
	'kind'      => 'series',
	'ready'     => true,
	'directory' => array( 'path' => 'series/korea/2026/My.Series' ),
	'errors'    => array(),
	'warnings'  => array(),
	'episodes'  => array(
		array(
			'identity_type'  => 'season_episode',
			'season_number'  => '4',
			'episode_number' => '1',
			'token'          => 'S04E01',
			'sources'        => array( array( 'media_path' => 'series/korea/2026/My.Series/1080p WEB-DL/S04E01.mkv', 'quality' => '1080p', 'source_type' => 'WEB-DL' ) ),
			'subtitles'      => array(),
		),
	),
);

// TMDb includes Season 4
$tmdb_seasons_with_s4 = $tmdb_seasons;
$tmdb_seasons_with_s4[] = array(
	'season_number' => '4',
	'name'          => 'Season 4',
	'episodes'      => array(
		array(
			'season_number'  => '4',
			'episode_number' => 1,
			'name'           => 'Season 4 Episode 1',
			'tmdb_id'        => 4001,
		),
	),
);
$metadata_s4 = $metadata_preview_fixture;
$metadata_s4['series']['seasons'] = $tmdb_seasons_with_s4;

// Build incremental plan against existing series 500
$plan_s4_options = array(
	'find_series_by_tmdb'  => static function ( int $tmdb_id ) {
		return 777 === $tmdb_id ? array( 500 ) : array();
	},
	'find_episodes'        => static function ( int $series_id ) use ( $created_ep_ids ) {
		$rows = array();
		foreach ( $created_ep_ids as $idx => $id ) {
			$rows[] = array(
				'id'             => $id,
				'season_number'  => '3',
				'episode_number' => (string) ( $idx + 1 ),
				'tmdb_id'        => 3001 + $idx,
			);
		}
		return $rows;
	},
	'get_tvshow_meta'      => static function ( int $id, string $key ) use ( &$persisted_meta ) {
		return $persisted_meta["series_{$id}_{$key}"] ?? null;
	},
	'get_seasons'          => static function ( int $id ) use ( &$persisted_meta ) {
		return $persisted_meta["series_{$id}__seasons"] ?? array();
	},
	'scanned_episode_keys' => array( '4:1' ),
);

$plan_s4 = Movies_WP_Series_Import_Plan::build( $metadata_s4, $plan_s4_options );
p2_same( true, $plan_s4['ready_to_import'], 'incremental plan builds ok' );
p2_same( 'update', $plan_s4['identity']['action'], 'identity action is update' );
p2_same( 500, $plan_s4['identity']['existing_series_id'], 'identifies existing series 500' );
p2_same( 1, count( $plan_s4['seasons'] ), 'only Season 4 is planned for creation' );
p2_same( '4', $plan_s4['seasons'][0]['season_number'], 'planned season is Season 4' );

// Execute incremental Season 4 import
$ep_s4_res = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 500, $plan_s4['seasons'][0]['episodes'][0], $adapter_options );
p2_same( true, $ep_s4_res['ok'] ?? false, 'S04E01 created' );
$s4_ep_id = (int) $ep_s4_res['episode_id'];

$apply_s4_seasons = Movies_WP_Streamit_TV_Adapter::apply_seasons_phase(
	500,
	$plan_s4,
	array( '4' => array( $s4_ep_id ) ),
	$adapter_options
);
p2_same( true, $apply_s4_seasons['ok'] ?? false, 'incremental seasons phase succeeds' );

$stored_both_seasons = $persisted_meta['series_500__seasons'] ?? array();
p2_same( 2, count( $stored_both_seasons ), 'both Season 3 and Season 4 are in _seasons' );
p2_same( '3', $stored_both_seasons[0]['season_number'], 'Season 3 is first (numerically sorted)' );
p2_same( '4', $stored_both_seasons[1]['season_number'], 'Season 4 is second (numerically sorted)' );
p2_same( $created_ep_ids, $stored_both_seasons[0]['episodes'], 'Season 3 original episodes preserved intact' );
p2_same( array( $s4_ep_id ), $stored_both_seasons[1]['episodes'], 'Season 4 episode linked' );

// 4. Re-running identical import creates no duplicates
$plan_s4_rerun_options = $plan_s4_options;
$plan_s4_rerun_options['find_episodes'] = static function ( int $series_id ) use ( $created_ep_ids, $s4_ep_id ) {
	$rows = array();
	foreach ( $created_ep_ids as $idx => $id ) {
		$rows[] = array( 'id' => $id, 'season_number' => '3', 'episode_number' => (string) ( $idx + 1 ), 'tmdb_id' => 3001 + $idx );
	}
	$rows[] = array( 'id' => $s4_ep_id, 'season_number' => '4', 'episode_number' => '1', 'tmdb_id' => 4001 );
	return $rows;
};

$plan_s4_rerun = Movies_WP_Series_Import_Plan::build( $metadata_s4, $plan_s4_rerun_options );
p2_same( 'update', $plan_s4_rerun['seasons'][0]['episodes'][0]['action'], 're-run marks existing S04E01 as update' );
p2_same( $s4_ep_id, $plan_s4_rerun['seasons'][0]['episodes'][0]['existing_episode_id'], 're-run reuses existing episode ID' );

$rerun_ep_res = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 500, $plan_s4_rerun['seasons'][0]['episodes'][0], $adapter_options );
p2_same( $s4_ep_id, (int) $rerun_ep_res['episode_id'], 're-run does not create duplicate episode' );

$apply_rerun_seasons = Movies_WP_Streamit_TV_Adapter::apply_seasons_phase(
	500,
	$plan_s4_rerun,
	array( '4' => array( $s4_ep_id ) ),
	$adapter_options
);
$stored_rerun_seasons = $persisted_meta['series_500__seasons'] ?? array();
p2_same( 2, count( $stored_rerun_seasons ), 'season count remains exactly 2 (no duplicate seasons)' );
p2_same( array( $s4_ep_id ), $stored_rerun_seasons[1]['episodes'], 'Season 4 episode list has no duplicate IDs' );

echo "\n=== Requirement 9: Download box release variants remain distinguishable ===\n";
$sources_with_variants = array(
	array(
		'media_path'  => 'series/korea/2024/Show/1080p WEB-DL/Show.S01E01.1080p.WEB-DL.mkv',
		'quality'     => '1080p',
		'source_type' => 'WEB-DL',
		'size_label'  => '1.5 GB',
	),
	array(
		'media_path'  => 'series/korea/2024/Show/1080p WEBRip/Show.S01E01.1080p.WEBRip.mkv',
		'quality'     => '1080p',
		'source_type' => 'WEBRip',
		'size_label'  => '1.2 GB',
	),
);

// Test media import plan preserves release type in name
$plan_media = Movies_WP_Series_Media_Import_Plan::build(
	array(
		'ok'         => true,
		'type'       => 'series_media',
		'input'      => array(
			'tvshow_id'        => 500,
			'series_directory' => 'series/korea/2024/Show',
			'expected_tmdb_id' => 777,
		),
		'validation' => array( 'errors' => array(), 'warnings' => array() ),
		'episodes'   => array(
			array(
				'season_number'  => '1',
				'episode_number' => '1',
				'status'         => 'matched',
				'episode_id'     => 1001,
				'sources'        => $sources_with_variants,
				'subtitles'      => array(),
			),
		),
	),
	array(
		'get_sources'   => static function () {
			return array();
		},
		'get_subtitles' => static function () {
			return array();
		},
	)
);

p2_same( true, $plan_media['ok'] ?? false, 'media import plan builds' );
$planned_sources = $plan_media['episodes'][0]['operations']['_sources'];
p2_same( 2, count( $planned_sources ), 'two sources planned' );
p2_same( 'WEB-DL', $planned_sources[0]['row']['name'], 'first source name preserves WEB-DL release type' );
p2_same( 'WEBRip', $planned_sources[1]['row']['name'], 'second source name preserves WEBRip release type' );

// Test child-theme download catalog encoder helper
$encoder1 = streamit_child_download_source_encoder( $planned_sources[0]['row'] );
$encoder2 = streamit_child_download_source_encoder( $planned_sources[1]['row'] );
p2_same( 'WEB-DL', $encoder1, 'child-theme encoder helper extracts WEB-DL' );
p2_same( 'WEBRip', $encoder2, 'child-theme encoder helper extracts WEBRip' );

// Test download grouping keeps variants separate
$download_episode = array(
	'has_download' => true,
	'label'        => 'S01E01',
	'sources'      => array(
		array(
			'quality'          => '1080p',
			'name'             => 'WEB-DL',
			'download_content' => 'series/Show/S01E01.WEB-DL.mkv',
			'file_size'        => '1.5 GB',
			'href'             => 'https://example.com/dl/1',
		),
		array(
			'quality'          => '1080p',
			'name'             => 'WEBRip',
			'download_content' => 'series/Show/S01E01.WEBRip.mkv',
			'file_size'        => '1.2 GB',
			'href'             => 'https://example.com/dl/2',
		),
	),
);

$groups = streamit_child_series_download_quality_groups( array( $download_episode ) );
p2_same( 2, count( $groups ), '1080p WEB-DL and 1080p WEBRip remain 2 distinct groups in download box (not collapsed)' );
p2_same( '1080p', $groups[0]['quality'], 'first group quality is 1080p' );
p2_same( 'WEB-DL', $groups[0]['encoder'], 'first group encoder is WEB-DL' );
p2_same( '1080p', $groups[1]['quality'], 'second group quality is 1080p' );
p2_same( 'WEBRip', $groups[1]['encoder'], 'second group encoder is WEBRip' );

echo "\n=== Requirement 10: Stale create plan cannot create duplicate series ===\n";
// Suppose preview generated a "create" plan for tmdb_id 999
$stale_plan = array(
	'ok'              => true,
	'ready_to_import' => true,
	'contract'        => array( 'kind' => 'series_import_plan' ),
	'sources_policy'  => array( 'mutate' => false, 'actions' => array() ),
	'seasons'         => array(),
	'identity'        => array(
		'action'             => 'create',
		'existing_series_id' => null,
	),
	'series'          => array(
		'tmdb_id' => 999,
		'title'   => 'Stale Title',
		'summary' => '', // Empty summary to test preservation of manual metadata
	),
);

// But series 888 was already created in the meantime
$existing_series_state = array(
	'post_title'   => 'Existing Manually Edited Title',
	'post_content' => 'Existing Detailed Summary',
);
$create_call_count = 0;
$update_call_count = 0;

$stale_guard_options = array(
	'find_existing_series' => static function ( int $tmdb_id ) {
		return 999 === $tmdb_id ? 888 : null;
	},
	'create_tvshow'        => static function () use ( &$create_call_count ) {
		++$create_call_count;
		return 9999;
	},
	'get_tvshow'           => static function ( int $id ) use ( &$existing_series_state ) {
		return 888 === $id ? new TV_Adapter_Object( tv_adapter_fields( 'tvshow', $existing_series_state['post_title'], $existing_series_state['post_content'] ) ) : null;
	},
	'update_tvshow_row'    => static function ( int $id, array $row ) use ( &$update_call_count, &$existing_series_state ) {
		++$update_call_count;
		$existing_series_state = $row;
		return true;
	},
	'update_tvshow_meta'   => static function () {
		return true;
	},
	'get_tvshow_meta'      => static function () {
		return null;
	},
);

$stale_result = Movies_WP_Streamit_TV_Adapter::apply_series_phase( $stale_plan, $stale_guard_options );
p2_same( true, $stale_result['ok'] ?? false, 'stale plan succeeds' );
p2_same( 888, $stale_result['series_id'] ?? 0, 'reused existing series 888' );
p2_same( 0, $create_call_count, 'create_tvshow was NOT called' );
p2_same( 1, $update_call_count, 'update_tvshow_row was called instead' );
p2_same( 'Existing Detailed Summary', $existing_series_state['post_content'], 'empty summary in plan did NOT wipe existing content' );

echo "\n=== Requirement 11: Manual download sources preserved ===\n";
$manual_existing_sources = array(
	array(
		'name'             => 'Custom VIP Server',
		'link'             => 'series/korea/2024/Show/1080p-manual/Show.S01E01.mp4',
		'quality'          => '1080p',
		'download_content' => 'series/korea/2024/Show/1080p-manual/Show.S01E01.mp4',
		'file_size'        => '2.0 GB',
	),
);

$scan_new_source = array(
	array(
		'media_path'  => 'series/korea/2024/Show/1080p WEB-DL/Show.S01E01.mkv',
		'quality'     => '1080p',
		'source_type' => 'WEB-DL',
		'size_label'  => '1.5 GB',
	),
);

$plan_preserve_manual = Movies_WP_Series_Media_Import_Plan::build(
	array(
		'ok'         => true,
		'type'       => 'series_media',
		'input'      => array(
			'tvshow_id'        => 500,
			'series_directory' => 'series/korea/2024/Show',
			'expected_tmdb_id' => 777,
		),
		'validation' => array( 'errors' => array(), 'warnings' => array() ),
		'episodes'   => array(
			array(
				'season_number'  => '1',
				'episode_number' => '1',
				'status'         => 'matched',
				'episode_id'     => 1001,
				'sources'        => $scan_new_source,
				'subtitles'      => array(),
			),
		),
	),
	array(
		'get_sources'   => static function () use ( $manual_existing_sources ) {
			return $manual_existing_sources;
		},
		'get_subtitles' => static function () {
			return array();
		},
	)
);

$planned_ops = $plan_preserve_manual['episodes'][0]['operations']['_sources'];
p2_same( 2, count( $planned_ops ), 'both new and manual sources planned' );
p2_same( 'upsert', $planned_ops[0]['action'], 'new source is upsert' );
p2_same( 'preserve', $planned_ops[1]['action'], 'manual source action is preserve' );
p2_same( 'Custom VIP Server', $planned_ops[1]['row']['name'], 'manual source name is preserved' );
p2_same( 'series/korea/2024/Show/1080p-manual/Show.S01E01.mp4', $planned_ops[1]['row']['link'], 'manual source link is preserved' );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} test failure(s) in Phase 2 suite\n" );
	exit( 1 );
}

echo "\nAll Phase 2 tests passed successfully!\n";
exit( 0 );

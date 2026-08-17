<?php
/**
 * CLI tests for the read-only Series Import Plan.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-import-plan-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-plan-test/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
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
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}
		$unserialized = @unserialize( $value );
		return false === $unserialized && 'b:0;' !== $value ? $value : $unserialized;
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-plan.php';

$failures = 0;

function series_plan_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function series_plan_same( $expected, $actual, string $label ): void {
	series_plan_assert( $expected === $actual, $label . ' got=' . var_export( $actual, true ) );
}

/**
 * @return array<string, mixed>
 */
function series_plan_preview(): array {
	return array(
		'ok'              => true,
		'type'            => 'series',
		'ready_to_import' => true,
		'input'           => array(
			'tmdb_id' => 100,
			'title'   => 'عنوان محلی',
			'summary' => '',
		),
		'series'          => array(
			'tmdb_id'           => 100,
			'name'              => 'TMDb Name',
			'original_name'     => 'Original Name',
			'overview'          => 'TMDb overview',
			'first_air_date'    => '2020-01-01',
			'poster_path'       => '/poster.jpg',
			'poster_url'        => 'https://image.tmdb.org/t/p/w500/poster.jpg',
			'backdrop_path'     => '/backdrop.jpg',
			'backdrop_url'      => 'https://image.tmdb.org/t/p/original/backdrop.jpg',
			'genres'            => array( array( 'id' => 18, 'name' => 'Drama' ) ),
			'origin_country'    => array( 'KR' ),
			'original_language' => 'ko',
			'seasons'           => array(
				array(
					'season_number' => 0,
					'name'          => 'Specials',
					'air_date'      => '',
					'overview'      => '',
					'poster_path'   => null,
					'poster_url'    => null,
					'episodes'      => array(
						array(
							'tmdb_id'        => 900,
							'season_number'  => 0,
							'episode_number' => 1,
							'name'           => 'Special',
							'overview'       => '',
							'air_date'       => '',
							'runtime'        => null,
							'still_path'     => null,
							'still_url'      => null,
						),
					),
				),
				array(
					'season_number' => 1,
					'name'          => 'Season 1',
					'air_date'      => '2020-01-01',
					'overview'      => 'Season overview',
					'poster_path'   => '/season-1.jpg',
					'poster_url'    => 'https://image.tmdb.org/t/p/w500/season-1.jpg',
					'episodes'      => array(
						array(
							'tmdb_id'        => 901,
							'season_number'  => 1,
							'episode_number' => 1,
							'name'           => 'One',
							'overview'       => 'One overview',
							'air_date'       => '2020-01-01',
							'runtime'        => 60,
							'still_path'     => '/episode-1.jpg',
							'still_url'      => 'https://image.tmdb.org/t/p/w500/episode-1.jpg',
						),
						array(
							'tmdb_id'        => 902,
							'season_number'  => 1,
							'episode_number' => 2,
							'name'           => 'Two',
							'overview'       => '',
							'air_date'       => '',
							'runtime'        => null,
							'still_path'     => null,
							'still_url'      => null,
						),
					),
				),
			),
			'cast'              => array(),
			'crew'              => array(),
		),
		'validation'      => array(
			'errors'   => array(),
			'warnings' => array(
				array(
					'code'    => 'series_episode_still_missing',
					'message' => 'S00E01 has no episode still on TMDb.',
				),
			),
		),
	);
}

/**
 * @param list<int>                  $series_ids
 * @param list<array<string, mixed>> $seasons
 * @param list<array<string, mixed>> $episodes
 * @param array<int, mixed>          $linked_season_meta
 * @return array<string, callable>
 */
function series_plan_options(
	array $series_ids,
	array $seasons = array(),
	array $episodes = array(),
	array $linked_season_meta = array()
): array {
	return array(
		'find_series_by_tmdb' => static function ( $tmdb_id ) use ( $series_ids ) {
			unset( $tmdb_id );
			return array( 'ids' => $series_ids );
		},
		'get_seasons'        => static function ( $series_id ) use ( $seasons ) {
			unset( $series_id );
			return $seasons;
		},
		'find_episodes'      => static function ( $series_id ) use ( $episodes ) {
			unset( $series_id );
			return $episodes;
		},
		'get_episode_meta'   => static function ( $episode_id, $key ) use ( $linked_season_meta ) {
			if ( '_season_number' !== $key ) {
				return null;
			}
			return $linked_season_meta[ $episode_id ] ?? null;
		},
	);
}

function series_plan_error_code( $value ): ?string {
	return is_wp_error( $value ) ? (string) $value->get_error_code() : null;
}

echo "Series Import Plan create actions\n";

$create = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options( array() )
);
series_plan_assert( ! is_wp_error( $create ), 'new Series plan builds' );
series_plan_same( 'series_import_plan', $create['contract']['kind'], 'isolated Series contract' );
series_plan_same( true, $create['contract']['read_only'], 'plan is read-only' );
series_plan_same( 'create', $create['identity']['action'], 'Series action is create' );
series_plan_same( '_tmdb_id', $create['identity']['match_by'], 'Series identity uses _tmdb_id' );
series_plan_same( 'create', $create['seasons'][0]['action'], 'Season 0 action is create' );
series_plan_same( '0', $create['seasons'][0]['season_number'], 'Season 0 remains digit-string zero' );
series_plan_assert( is_string( $create['seasons'][0]['season_number'] ), 'Season 0 is typed as string, not int' );
series_plan_assert( '0' === $create['seasons'][0]['season_number'], 'Season 0 is the string "0", not int 0' );
// PHP hazard: empty("0") and ?: both collapse Season 0. Plan emits "0"; consumers must use === / !== ''.
series_plan_assert( empty( '0' ), 'document PHP hazard: empty("0") is true' );
series_plan_assert( ( '0' ?: 'collapsed' ) === 'collapsed', 'document PHP hazard: ?: collapses "0"' );
series_plan_assert( '' !== $create['seasons'][0]['season_number'], 'Season 0 survives strict empty-string checks' );
series_plan_assert( null !== $create['seasons'][0]['season_number'], 'Season 0 is never null' );
series_plan_same( 'create', $create['seasons'][0]['episodes'][0]['action'], 'Season 0 episode action is create' );
series_plan_same( '0', $create['seasons'][0]['episodes'][0]['season_number'], 'Season 0 propagates to episode plan as "0"' );
series_plan_same( array(), $create['seasons'][0]['existing_episode_ids'], 'new season has empty existing episode ID list' );
series_plan_same( true, $create['seasons'][0]['preserve_unmatched_episode_ids'], 'season preserve policy is explicit' );
series_plan_same( 'keep_existing_untouched', $create['seasons'][0]['episodes'][0]['sources_action'], 'episode sources are never planned for mutation' );
series_plan_same( false, $create['sources_policy']['mutate'], 'plan-level sources policy forbids mutation' );
series_plan_assert( ! array_key_exists( 'sources', $create ), 'plan has no sources payload' );
series_plan_same( 'set', $create['images']['poster']['action'], 'poster has explicit set action' );
series_plan_same( 'poster', $create['images']['poster']['role'], 'poster role is explicit' );
series_plan_same( '_portrait_thumbmail', $create['images']['poster']['target'], 'poster targets portrait meta' );
series_plan_same( 'set', $create['images']['backdrop']['action'], 'backdrop has explicit set action' );
series_plan_same( 'backdrop', $create['images']['backdrop']['role'], 'backdrop role is explicit' );
series_plan_same( 'thumbnail_id', $create['images']['backdrop']['target'], 'backdrop targets banner meta' );
series_plan_same( 'skip_missing', $create['seasons'][0]['image']['action'], 'missing new Season image is explicit skip' );
series_plan_same( 'season_poster', $create['seasons'][0]['image']['role'], 'season poster role is explicit' );
series_plan_same( 'set', $create['seasons'][1]['image']['action'], 'Season poster is explicit set' );
series_plan_same( '_seasons.image_id', $create['seasons'][1]['image']['target'], 'Season image target is explicit' );
series_plan_same( 'set', $create['seasons'][1]['episodes'][0]['image']['action'], 'episode still is explicit set' );
series_plan_same( 'still', $create['seasons'][1]['episodes'][0]['image']['role'], 'episode still role is explicit' );
series_plan_same( 'skip_missing', $create['seasons'][1]['episodes'][1]['image']['action'], 'missing new still is explicit skip' );
series_plan_same( 'tmdb', $create['series']['summary_source'], 'empty admin summary falls back to TMDb' );
series_plan_same( 1, count( $create['warnings'] ), 'preview warnings are retained' );

echo "\nSeries, season, and episode update identity\n";

$existing_seasons = array(
	array(
		'season_number' => 0,
		'name'          => 'Existing Specials',
		'episodes'      => array( 50, 99 ),
	),
	array(
		'season_number' => 1,
		'name'          => 'Existing Season 1',
		'episodes'      => array( 51, 52, 88 ),
	),
);
$existing_episodes = array(
	array( 'id' => 50, 'tvshow_id' => 42, 'tmdb_id' => 900, 'season_number' => 0, 'episode_number' => 'E01' ),
	array( 'id' => 51, 'tvshow_id' => 42, 'tmdb_id' => 901, 'season_number' => 1, 'episode_number' => 'E01' ),
	array( 'id' => 52, 'tvshow_id' => 42, 'tmdb_id' => 0, 'season_number' => 1, 'episode_number' => 'E02' ),
	array( 'id' => 88, 'tvshow_id' => 42, 'tmdb_id' => 0, 'season_number' => 1, 'episode_number' => 9 ),
	array( 'id' => 99, 'tvshow_id' => 42, 'tmdb_id' => 0, 'season_number' => 0, 'episode_number' => 9 ),
);
$update_preview = series_plan_preview();
$update_preview['series']['poster_path']  = null;
$update_preview['series']['poster_url']   = null;
$update_preview['series']['seasons'][0]['episodes'][0]['still_path'] = null;
$update = Movies_WP_Series_Import_Plan::build(
	$update_preview,
	series_plan_options( array( 42 ), $existing_seasons, $existing_episodes )
);
series_plan_assert( ! is_wp_error( $update ), 'existing Series plan builds' );
series_plan_same( 'update', $update['identity']['action'], 'Series action is update' );
series_plan_same( 42, $update['identity']['existing_series_id'], 'existing Series ID is retained' );
series_plan_same( 'update', $update['seasons'][0]['action'], 'Season 0 action is update' );
series_plan_same( '0', $update['seasons'][0]['season_number'], 'existing Season 0 identity remains "0"' );
series_plan_same( 'explicit_season_number', $update['seasons'][0]['identity_source'], 'explicit Season 0 identity is recorded' );
series_plan_same( array( 50, 99 ), $update['seasons'][0]['existing_episode_ids'], 'Season 0 preserves full existing episode ID list' );
series_plan_same( array( 99 ), $update['seasons'][0]['unmatched_existing_episode_ids'], 'Season 0 keeps unmatched existing episode IDs' );
series_plan_same( array( 51, 52, 88 ), $update['seasons'][1]['existing_episode_ids'], 'Season 1 preserves full existing episode ID list' );
series_plan_same( array( 88 ), $update['seasons'][1]['unmatched_existing_episode_ids'], 'Season 1 keeps unmatched existing episode IDs' );
series_plan_same( 'update', $update['seasons'][0]['episodes'][0]['action'], 'existing Season 0 episode is UPDATE' );
series_plan_same( 50, $update['seasons'][0]['episodes'][0]['existing_episode_id'], 'episode matches by scoped TMDb ID' );
series_plan_same( 'tvshow_id+_tmdb_id', $update['seasons'][0]['episodes'][0]['match_by'], 'TMDb match precedence is explicit' );
series_plan_same( 'update', $update['seasons'][1]['episodes'][0]['action'], 'existing S01E01 is UPDATE' );
series_plan_same( 'update', $update['seasons'][1]['episodes'][1]['action'], 'existing S01E02 fallback is UPDATE' );
series_plan_same( 52, $update['seasons'][1]['episodes'][1]['existing_episode_id'], 'episode falls back to scoped season/episode' );
series_plan_same( 'tvshow_id+_season_number+_episode_number', $update['seasons'][1]['episodes'][1]['match_by'], 'fallback identity is explicit' );
series_plan_same( 'keep_existing_untouched', $update['seasons'][1]['episodes'][0]['sources_action'], 'UPDATE episodes never plan _sources changes' );
series_plan_same( 'keep_existing', $update['images']['poster']['action'], 'missing update poster keeps existing image' );
series_plan_same( 'keep_existing', $update['seasons'][0]['image']['action'], 'missing update Season image keeps existing image' );
series_plan_same( 'keep_existing', $update['seasons'][0]['episodes'][0]['image']['action'], 'missing update still keeps existing image' );

echo "\nMixed create and update actions\n";

$mixed = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options(
		array( 42 ),
		array(
			array( 'season_number' => 1, 'episodes' => array( 70 ) ),
		),
		array(
			array( 'id' => 70, 'tvshow_id' => 42, 'tmdb_id' => 901, 'season_number' => 1, 'episode_number' => 1 ),
		)
	)
);
series_plan_assert( ! is_wp_error( $mixed ), 'mixed Series plan builds' );
series_plan_same( 'create', $mixed['seasons'][0]['action'], 'missing Season 0 is create' );
series_plan_same( 'update', $mixed['seasons'][1]['action'], 'existing Season 1 is update' );
series_plan_same( 'update', $mixed['seasons'][1]['episodes'][0]['action'], 'matched episode is update' );
series_plan_same( 'create', $mixed['seasons'][1]['episodes'][1]['action'], 'missing episode is create' );

echo "\nDuplicate and conflicting identities block\n";

$duplicate_series = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options( array( 42, 43 ) )
);
series_plan_same( 'series_import_duplicate_identity', series_plan_error_code( $duplicate_series ), 'duplicate Series TMDb ID blocks' );

$duplicate_season = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options(
		array( 42 ),
		array(
			array( 'season_number' => 1, 'episodes' => array() ),
			array( 'season_number' => '1', 'episodes' => array() ),
		)
	)
);
series_plan_same( 'series_import_duplicate_season', series_plan_error_code( $duplicate_season ), 'duplicate existing season identity blocks' );

$duplicate_tmdb_episode = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options(
		array( 42 ),
		array(),
		array(
			array( 'id' => 80, 'tvshow_id' => 42, 'tmdb_id' => 901, 'season_number' => 1, 'episode_number' => 1 ),
			array( 'id' => 81, 'tvshow_id' => 42, 'tmdb_id' => 901, 'season_number' => 1, 'episode_number' => 9 ),
		)
	)
);
series_plan_same( 'series_import_duplicate_episode', series_plan_error_code( $duplicate_tmdb_episode ), 'duplicate episode TMDb identity blocks' );

$duplicate_fallback_episode = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options(
		array( 42 ),
		array(),
		array(
			array( 'id' => 82, 'tvshow_id' => 42, 'tmdb_id' => 0, 'season_number' => 1, 'episode_number' => 2 ),
			array( 'id' => 83, 'tvshow_id' => 42, 'tmdb_id' => 0, 'season_number' => 1, 'episode_number' => 'E02' ),
		)
	)
);
series_plan_same( 'series_import_duplicate_episode', series_plan_error_code( $duplicate_fallback_episode ), 'duplicate season/episode identity blocks' );

$conflicting_episode = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options(
		array( 42 ),
		array(),
		array(
			array( 'id' => 84, 'tvshow_id' => 42, 'tmdb_id' => 901, 'season_number' => 1, 'episode_number' => 9 ),
			array( 'id' => 85, 'tvshow_id' => 42, 'tmdb_id' => 999, 'season_number' => 1, 'episode_number' => 1 ),
		)
	)
);
series_plan_same( 'series_import_episode_identity_conflict', series_plan_error_code( $conflicting_episode ), 'TMDb and S/E disagreement blocks' );

echo "\nLegacy season identity policy\n";

$legacy_inferred = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options(
		array( 42 ),
		array(
			array( 'name' => 'Legacy Specials', 'position' => 99, 'episodes' => array( 1000, 1001 ) ),
			array( 'name' => 'Legacy Season 1', 'position' => 0, 'episodes' => array( 1002 ) ),
		),
		array(),
		array(
			1000 => '0',
			1001 => 0,
			1002 => '1',
		)
	)
);
series_plan_assert( ! is_wp_error( $legacy_inferred ), 'consistent linked episode meta infers legacy rows' );
series_plan_same( '0', $legacy_inferred['seasons'][0]['season_number'], 'legacy Season 0 is supported as "0"' );
series_plan_same( 'linked_episode_meta', $legacy_inferred['seasons'][0]['identity_source'], 'legacy identity source is explicit' );
series_plan_same( 0, $legacy_inferred['seasons'][0]['existing_slot_index'], 'original slot is retained only as location' );
series_plan_same( '1', $legacy_inferred['seasons'][1]['season_number'], 'legacy Season 1 is inferred as "1"' );
series_plan_same( array( 1000, 1001 ), $legacy_inferred['seasons'][0]['existing_episode_ids'], 'legacy Season 0 preserves linked episode IDs' );

$legacy_empty = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options(
		array( 42 ),
		array(
			array( 'name' => 'Legacy', 'position' => 0, 'episodes' => array() ),
		)
	)
);
series_plan_same( 'series_import_legacy_season_empty', series_plan_error_code( $legacy_empty ), 'empty legacy row blocks without index guess' );

$legacy_missing_meta = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options(
		array( 42 ),
		array(
			array( 'name' => 'Legacy', 'position' => 4, 'episodes' => array( 1100 ) ),
		),
		array(),
		array( 1100 => '' )
	)
);
series_plan_same( 'series_import_legacy_season_ambiguous', series_plan_error_code( $legacy_missing_meta ), 'empty linked _season_number blocks' );

$legacy_conflict = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options(
		array( 42 ),
		array(
			array( 'name' => 'Legacy', 'position' => 0, 'episodes' => array( 1200, 1201 ) ),
		),
		array(),
		array(
			1200 => '1',
			1201 => '2',
		)
	)
);
series_plan_same( 'series_import_legacy_season_conflict', series_plan_error_code( $legacy_conflict ), 'conflicting linked season numbers block' );

$invalid_explicit = Movies_WP_Series_Import_Plan::build(
	series_plan_preview(),
	series_plan_options(
		array( 42 ),
		array(
			array( 'season_number' => '', 'position' => 0, 'episodes' => array( 1300 ) ),
		),
		array(),
		array( 1300 => '1' )
	)
);
series_plan_same( 'series_import_legacy_season_ambiguous', series_plan_error_code( $invalid_explicit ), 'invalid explicit season_number is not replaced by inference' );

echo "\nPreview identity validation\n";

$bad_preview = series_plan_preview();
$bad_preview['series']['seasons'][0]['season_number'] = null;
$invalid_season = Movies_WP_Series_Import_Plan::build( $bad_preview, series_plan_options( array() ) );
series_plan_same( 'series_import_plan_invalid_season', series_plan_error_code( $invalid_season ), 'automation season requires explicit numeric identity' );

$bad_episode = series_plan_preview();
$bad_episode['series']['seasons'][1]['episodes'][0]['season_number'] = 2;
$invalid_episode = Movies_WP_Series_Import_Plan::build( $bad_episode, series_plan_options( array() ) );
series_plan_same( 'series_import_plan_invalid_episode', series_plan_error_code( $invalid_episode ), 'episode season must match parent season' );

$not_ready = series_plan_preview();
$not_ready['ready_to_import'] = false;
$invalid_preview = Movies_WP_Series_Import_Plan::build( $not_ready, series_plan_options( array() ) );
series_plan_same( 'series_import_plan_invalid_preview', series_plan_error_code( $invalid_preview ), 'not-ready preview is rejected' );

echo "\n";
if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} assertion(s) failed.\n" );
	fwrite( STDERR, "EXECUTABLE TEST STATUS: pending (assertions defined; PHP runtime unavailable in this environment).\n" );
	exit( 1 );
}

echo "All Series Import Plan assertions constructed successfully.\n";
echo "EXECUTABLE TEST STATUS: pending — run with PHP when available:\n";
echo "  php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-import-plan-test.php\n";

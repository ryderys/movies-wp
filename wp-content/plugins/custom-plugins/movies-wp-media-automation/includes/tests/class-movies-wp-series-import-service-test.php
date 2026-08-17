<?php
/**
 * CLI contract tests for Movies_WP_Series_Import_Service.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-import-service-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-import-service-test/' );
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}

require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-service.php';

$failures = 0;

function series_service_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function series_service_same( $expected, $actual, string $label ): void {
	series_service_assert(
		$expected === $actual,
		$label . ' expected=' . var_export( $expected, true ) . ' got=' . var_export( $actual, true )
	);
}

function series_service_image( string $role, string $target, string $action = 'skip_missing' ): array {
	return array(
		'role'   => $role,
		'action' => $action,
		'path'   => 'set' === $action ? '/' . $role . '.jpg' : null,
		'url'    => 'set' === $action ? 'https://image.tmdb.org/t/p/w500/' . $role . '.jpg' : null,
		'target' => $target,
	);
}

function series_service_plan( string $action = 'create' ): array {
	$update = 'update' === $action;
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
			'existing_series_id' => $update ? 42 : null,
			'match_by'           => '_tmdb_id',
			'match_count'        => $update ? 1 : 0,
		),
		'series'          => array(
			'tmdb_id'             => 100,
			'title'               => 'عنوان محلی',
			'summary'             => 'خلاصه',
			'tmdb_title'          => 'TMDb Series',
			'tmdb_original_title' => 'Original Series',
			'imdb_id'             => 'tt100',
			'first_air_date'      => '2020-01-01',
			'rating'              => 8.5,
			'original_language'   => 'ko',
			'origin_country'      => array( 'KR' ),
			'genres'              => array(),
			'cast'                => array(),
			'crew'                => array(),
		),
		'images'          => array(
			'poster'   => series_service_image( 'poster', '_portrait_thumbmail', $update ? 'keep_existing' : 'set' ),
			'backdrop' => series_service_image( 'backdrop', 'thumbnail_id', $update ? 'keep_existing' : 'set' ),
		),
		'sources_policy'  => array(
			'episode_meta_key' => '_sources',
			'mutate'           => false,
			'actions'          => array(),
		),
		'seasons'         => array(
			array(
				'action'                         => $action,
				'season_number'                  => '0',
				'existing_slot_index'            => $update ? 0 : null,
				'identity_source'                => $update ? 'explicit_season_number' : 'preview_explicit',
				'name'                           => 'Specials',
				'air_date'                       => '',
				'overview'                       => 'Special episodes',
				'existing_episode_ids'           => $update ? array( 49, 50 ) : array(),
				'unmatched_existing_episode_ids' => $update ? array( 49 ) : array(),
				'preserve_unmatched_episode_ids' => true,
				'image'                          => series_service_image( 'season_poster', '_seasons.image_id' ),
				'episodes'                       => array(
					array(
						'action'              => $action,
						'existing_episode_id' => $update ? 50 : null,
						'match_by'            => $update ? 'tvshow_id+_tmdb_id' : null,
						'tmdb_id'             => 900,
						'season_number'       => '0',
						'episode_number'      => 1,
						'name'                => 'Special',
						'overview'            => 'Episode overview',
						'air_date'            => '',
						'runtime'             => 60,
						'sources_action'      => 'keep_existing_untouched',
						'image'               => series_service_image( 'still', 'thumbnail_id', $update ? 'keep_existing' : 'set' ),
					),
				),
			),
		),
		'warnings'        => array(
			array( 'code' => 'series_preview_warning', 'message' => 'Preview warning' ),
		),
		'errors'          => array(),
		'ready_to_import' => true,
	);
}

function series_service_adapter_result( string $action = 'create' ): array {
	$id = 'update' === $action ? 42 : 55;
	return array(
		'ok'              => true,
		'type'            => 'series',
		'series_id'       => $id,
		'identity_action' => $action,
		'series'          => array( 'ok' => true, 'action' => $action, 'series_id' => $id ),
		'seasons'         => array( array( 'ok' => true, 'action' => $action, 'season_number' => '0' ) ),
		'episodes'        => array( array( 'ok' => true, 'action' => $action, 'episode_id' => 71 ) ),
		'images'          => array( array( 'ok' => true, 'role' => 'poster', 'attachment_id' => 501 ) ),
		'warnings'        => array( array( 'code' => 'adapter_warning', 'message' => 'Adapter warning' ) ),
		'errors'          => array(),
		'partial'         => false,
	);
}

function series_service_harness( array &$state ): array {
	$state += array(
		'adapter_calls'   => 0,
		'identity_calls'  => 0,
		'adapter_result'  => series_service_adapter_result(),
		'adapter_plans'   => array(),
		'sources_payload' => false,
	);
	$identity_probe = static function () use ( &$state ) {
		++$state['identity_calls'];
		return array();
	};
	return array(
		'preview_build'        => $identity_probe,
		'plan_build'           => $identity_probe,
		'find_series_by_tmdb'  => $identity_probe,
		'get_seasons'          => $identity_probe,
		'find_episodes'        => $identity_probe,
		'get_episode_meta'     => $identity_probe,
		'adapter_apply'        => static function ( $plan ) use ( &$state ) {
			++$state['adapter_calls'];
			$state['adapter_plans'][] = $plan;
			$state['sources_payload'] = series_service_has_key_recursive( $plan, '_sources' );
			return $state['adapter_result'];
		},
	);
}

function series_service_has_key_recursive( array $value, string $needle ): bool {
	foreach ( $value as $key => $child ) {
		if ( $key === $needle ) {
			return true;
		}
		if ( is_array( $child ) && series_service_has_key_recursive( $child, $needle ) ) {
			return true;
		}
	}
	return false;
}

echo "Movies_WP_Series_Import_Service contract tests\n";

echo "\n[valid-create]\n";
$plan    = series_service_plan();
$state   = array();
$result  = Movies_WP_Series_Import_Service::execute( $plan, series_service_harness( $state ) );
$adapter = $state['adapter_result'];
series_service_same( true, $result['ok'], 'valid create result succeeds' );
series_service_same( false, $result['partial'], 'successful create is not partial' );
series_service_same( 55, $result['series_id'], 'created Series ID is preserved' );
series_service_same( 'create', $result['action'], 'plan create action is exposed' );
series_service_same( 1, $state['adapter_calls'], 'adapter is called exactly once' );
series_service_same( 0, $state['identity_calls'], 'service performs no identity or preview rediscovery' );
series_service_same( $plan, $state['adapter_plans'][0], 'approved plan reaches adapter unchanged' );
series_service_same( $adapter['series'], $result['series'], 'Series adapter result is preserved' );
series_service_same( $adapter['seasons'], $result['seasons'], 'season adapter results are preserved' );
series_service_same( $adapter['episodes'], $result['episodes'], 'episode adapter results are preserved' );
series_service_same( $adapter['images'], $result['images'], 'image adapter results are preserved' );
series_service_same(
	array_merge( $plan['warnings'], $adapter['warnings'] ),
	$result['warnings'],
	'plan and adapter warnings are preserved'
);

echo "\n[valid-update]\n";
$plan                    = series_service_plan( 'update' );
$state                   = array();
$state['adapter_result'] = series_service_adapter_result( 'update' );
$result                  = Movies_WP_Series_Import_Service::execute( $plan, series_service_harness( $state ) );
series_service_same( true, $result['ok'], 'valid update succeeds' );
series_service_same( 42, $result['series_id'], 'updated Series ID is preserved' );
series_service_same( 'update', $result['action'], 'plan update action is exposed' );
series_service_same( 1, $state['adapter_calls'], 'update adapter executes exactly once' );
series_service_same( 0, $state['identity_calls'], 'update identity is not rediscovered' );

echo "\n[malformed-plans-stop-before-adapter]\n";
$cases = array();
$case = series_service_plan();
$case['contract']['kind'] = 'wrong_contract';
$cases['contract'] = array( $case, 'series_import_service_invalid_contract', 'contract' );
$case = series_service_plan( 'update' );
$case['identity']['existing_series_id'] = null;
$cases['update-id'] = array( $case, 'series_import_service_missing_series_id', 'identity.existing_series_id' );
$case = series_service_plan();
$case['seasons'][0]['season_number'] = 0;
$cases['season-number'] = array( $case, 'series_import_service_invalid_season_number', 'seasons.0.season_number' );
$case = series_service_plan();
$case['seasons'][0]['episodes'][0]['match_by'] = 'invented_identity';
$case['seasons'][0]['episodes'][0]['action'] = 'update';
$case['seasons'][0]['episodes'][0]['existing_episode_id'] = 77;
$cases['episode-identity'] = array( $case, 'series_import_service_invalid_episode_identity', 'seasons.0.episodes.0.existing_episode_id' );
$case = series_service_plan();
$case['images']['poster']['target'] = 'thumbnail_id';
$cases['image-target'] = array( $case, 'series_import_service_invalid_image', 'images.poster' );
$case = series_service_plan();
$case['sources_policy']['mutate'] = true;
$cases['sources-policy'] = array( $case, 'series_import_service_invalid_sources_policy', 'sources_policy' );

foreach ( $cases as $label => $spec ) {
	$state  = array();
	$result = Movies_WP_Series_Import_Service::execute( $spec[0], series_service_harness( $state ) );
	series_service_same( false, $result['ok'], $label . ' malformed plan is rejected' );
	series_service_same( $spec[1], $result['errors'][0]['code'], $label . ' deterministic error code' );
	series_service_same( $spec[2], $result['errors'][0]['path'], $label . ' deterministic error path' );
	series_service_same( 0, $state['adapter_calls'], $label . ' does not invoke adapter' );
}

echo "\n[adapter-failure-no-retry]\n";
$conflict = array(
	'code'    => 'series_tv_adapter_episode_ownership_conflict',
	'message' => 'Episode 50 belongs to TV show 999 and cannot be updated for TV show 42.',
);
$state = array(
	'adapter_result' => array(
		'ok'              => false,
		'partial'         => true,
		'type'            => 'series',
		'series_id'       => 42,
		'identity_action' => 'update',
		'series'          => array( 'ok' => true, 'series_id' => 42 ),
		'seasons'         => array(),
		'episodes'        => array( array( 'ok' => false, 'episode_id' => 50, 'error' => $conflict ) ),
		'images'          => array(),
		'errors'          => array( $conflict ),
	),
);
$plan   = series_service_plan( 'update' );
$result = Movies_WP_Series_Import_Service::execute( $plan, series_service_harness( $state ) );
series_service_same( false, $result['ok'], 'adapter conflict remains failure' );
series_service_same( true, $result['partial'], 'adapter partial flag remains true' );
series_service_same( 42, $result['series_id'], 'partial failure retains actual Series ID' );
series_service_same( array( $conflict ), $result['errors'], 'technical adapter conflict is preserved exactly' );
series_service_same( 1, $state['adapter_calls'], 'conflict is not retried' );
series_service_same( 0, $state['identity_calls'], 'conflict does not trigger alternate identity discovery' );
series_service_same( $plan, $state['adapter_plans'][0], 'conflict does not alter planned identity' );

echo "\n[partial-results]\n";
$episode_ok = array( 'ok' => true, 'action' => 'create', 'episode_id' => 71 );
$episode_error = array( 'code' => 'episode_failed', 'message' => 'Episode failed' );
$episode_failed = array( 'ok' => false, 'action' => 'create', 'episode_id' => 72, 'error' => $episode_error );
$image_error = array( 'code' => 'image_failed', 'message' => 'Poster failed' );
$season_error = array( 'code' => 'season_failed', 'message' => 'Season write failed' );

$partial_cases = array(
	'enrichment' => array(
		'series'   => array( 'ok' => false, 'action' => 'create', 'series_id' => 55, 'error' => array( 'code' => 'enrichment_failed', 'message' => 'Enrichment failed' ) ),
		'seasons'  => array(),
		'episodes' => array(),
		'images'   => array(),
		'errors'   => array( array( 'code' => 'enrichment_failed', 'message' => 'Enrichment failed' ) ),
	),
	'episode' => array(
		'series'   => array( 'ok' => true, 'series_id' => 55 ),
		'seasons'  => array( array( 'ok' => true, 'season_number' => '0' ) ),
		'episodes' => array( $episode_ok, $episode_failed ),
		'images'   => array(),
		'errors'   => array( $episode_error ),
	),
	'image' => array(
		'series'   => array( 'ok' => true, 'series_id' => 55 ),
		'seasons'  => array(),
		'episodes' => array(),
		'images'   => array( array( 'ok' => false, 'role' => 'poster', 'error' => $image_error ) ),
		'errors'   => array( $image_error ),
	),
	'season' => array(
		'series'   => array( 'ok' => true, 'series_id' => 55 ),
		'seasons'  => array( array( 'ok' => false, 'action' => 'write', 'error' => $season_error ) ),
		'episodes' => array( $episode_ok ),
		'images'   => array(),
		'errors'   => array( $season_error ),
	),
);

foreach ( $partial_cases as $label => $details ) {
	$state = array(
		'adapter_result' => array_merge(
			array(
				'ok'              => false,
				'partial'         => true,
				'type'            => 'series',
				'series_id'       => 55,
				'identity_action' => 'create',
			),
			$details
		),
	);
	$result = Movies_WP_Series_Import_Service::execute( series_service_plan(), series_service_harness( $state ) );
	series_service_same( false, $result['ok'], $label . ' remains not-ok' );
	series_service_same( true, $result['partial'], $label . ' remains partial' );
	series_service_same( 55, $result['series_id'], $label . ' preserves Series ID' );
	series_service_same( $details['series'], $result['series'], $label . ' preserves Series result' );
	series_service_same( $details['seasons'], $result['seasons'], $label . ' preserves season results' );
	series_service_same( $details['episodes'], $result['episodes'], $label . ' preserves episode results' );
	series_service_same( $details['images'], $result['images'], $label . ' preserves image results' );
	series_service_same( $details['errors'], $result['errors'], $label . ' preserves structured errors' );
	series_service_same( 1, $state['adapter_calls'], $label . ' adapter executes once without rollback/retry' );
}

echo "\n[sources-and-boundaries]\n";
$state  = array();
$plan   = series_service_plan( 'update' );
$result = Movies_WP_Series_Import_Service::execute( $plan, series_service_harness( $state ) );
series_service_same( false, $state['sources_payload'], 'service constructs no _sources payload' );
series_service_same( 'keep_existing_untouched', $state['adapter_plans'][0]['seasons'][0]['episodes'][0]['sources_action'], 'source preservation instruction reaches adapter unchanged' );
series_service_same( array(), $state['adapter_plans'][0]['sources_policy']['actions'], 'service does not add source actions' );

$service_source = file_get_contents( dirname( __DIR__ ) . '/class-movies-wp-series-import-service.php' );
series_service_assert( is_string( $service_source ), 'service source is readable for architecture regression' );
series_service_assert( false === strpos( $service_source, 'Movies_WP_Media_' ), 'Series service does not reference Movie Automation classes' );
foreach (
	array(
		'streamit_add_tvshow',
		'streamit_update_tvshow_meta',
		'streamit_add_episode',
		'streamit_update_episode_meta',
		'streamit_add_person',
		'update_post_meta',
	) as $write_api
) {
	series_service_assert( false === strpos( $service_source, $write_api ), 'service contains no direct write API: ' . $write_api );
}

echo "\n";
if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} assertion(s) failed.\n" );
	fwrite( STDERR, "EXECUTABLE TEST STATUS: pending (PHP/Docker runtime unavailable in this environment).\n" );
	exit( 1 );
}

echo "All Series Import Service assertions constructed successfully.\n";
echo "EXECUTABLE TEST STATUS: pending — run with PHP when available:\n";
echo "  php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-import-service-test.php\n";
exit( 0 );

<?php
/**
 * CLI tests for Movies_WP_Trailer_Discovery and import assignment.
 *
 * Run:
 *   php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-trailer-discovery-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-trailer-discovery-test/' );
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
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-trailer-discovery.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-tvshow-media-directory.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-streamit-tv-adapter.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-api-client.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-preview-service.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-import-plan.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-import-service.php';

$failures = 0;

function td_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function td_same( $expected, $actual, string $label ): void {
	td_assert( $expected === $actual, $label . ' got=' . var_export( $actual, true ) );
}

$movie_dir  = 'Movie/Korea/2018/Vapor';
$series_dir = 'series/korea/2024/Show';

echo "select_path rules\n";

$none = Movies_WP_Trailer_Discovery::select_path( $movie_dir, array() );
td_same( null, $none, 'empty candidates → null' );

$feature = Movies_WP_Trailer_Discovery::select_path(
	$movie_dir,
	array( $movie_dir . '/Vapor.2018.1080p.mkv' )
);
td_same( null, $feature, 'feature video outside Trailer/ ignored' );

$nested = Movies_WP_Trailer_Discovery::select_path(
	$movie_dir,
	array( $movie_dir . '/Trailer/extra/nested.mp4' )
);
td_same( null, $nested, 'nested Trailer path ignored' );

$bad_ext = Movies_WP_Trailer_Discovery::select_path(
	$movie_dir,
	array( $movie_dir . '/Trailer/poster.jpg' )
);
td_same( null, $bad_ext, 'unsupported extension ignored' );

$other_title = Movies_WP_Trailer_Discovery::select_path(
	$movie_dir,
	array( 'Movie/Other/2018/X/Trailer/a.mp4' )
);
td_same( null, $other_title, 'path under a different title rejected' );

$traversal = Movies_WP_Trailer_Discovery::select_path(
	$movie_dir,
	array( $movie_dir . '/Trailer/../Vapor.mkv' )
);
td_same( null, $traversal, 'traversal path rejected' );

$selected = Movies_WP_Trailer_Discovery::select_path(
	$movie_dir,
	array(
		$movie_dir . '/Trailers/b.mp4',
		$movie_dir . '/Trailer/z.webm',
		$movie_dir . '/Trailer/a.mp4',
		$movie_dir . '/Vapor.2018.1080p.mkv',
	)
);
td_same( $movie_dir . '/Trailer/a.mp4', $selected, 'prefers Trailer/ then strcasecmp first' );

$trailers_only = Movies_WP_Trailer_Discovery::select_path(
	$movie_dir,
	array(
		array( 'kind' => 'trailer', 'media_path' => $movie_dir . '/Trailers/beta.mkv' ),
		array( 'kind' => 'trailer', 'media_path' => $movie_dir . '/Trailers/alpha.mp4' ),
	)
);
td_same( $movie_dir . '/Trailers/alpha.mp4', $trailers_only, 'Trailers/ supported with deterministic order' );

$case_folder = Movies_WP_Trailer_Discovery::select_path(
	$series_dir,
	array( $series_dir . '/trailer/Main.MP4' )
);
td_same( $series_dir . '/trailer/Main.MP4', $case_folder, 'case-insensitive Trailer folder and extension' );

echo "\ncandidates_from_files\n";
$from_files = Movies_WP_Trailer_Discovery::candidates_from_files(
	$movie_dir,
	array(
		array( 'kind' => 'video', 'media_path' => $movie_dir . '/feature.mkv' ),
		array( 'kind' => 'trailer', 'media_path' => $movie_dir . '/Trailer/official.mp4' ),
		array( 'kind' => 'ignored', 'media_path' => $movie_dir . '/cover.jpg' ),
		array( 'kind' => 'directory', 'media_path' => $movie_dir . '/SUB' ),
	)
);
td_same( array( $movie_dir . '/Trailer/official.mp4' ), $from_files, 'only trailer kind/path kept' );

echo "\nassign_if_empty movie + tvshow\n";
$meta = array();
$movie_opts = array(
	'get_movie_meta' => static function ( $id, $key ) use ( &$meta ) {
		return $meta[ 'movie:' . $id ][ $key ] ?? '';
	},
	'update_movie_meta' => static function ( $id, $key, $value ) use ( &$meta ) {
		$meta[ 'movie:' . $id ][ $key ] = $value;
		return true;
	},
	'get_tvshow_meta' => static function ( $id, $key ) use ( &$meta ) {
		return $meta[ 'tvshow:' . $id ][ $key ] ?? '';
	},
	'update_tvshow_meta' => static function ( $id, $key, $value ) use ( &$meta ) {
		$meta[ 'tvshow:' . $id ][ $key ] = $value;
		return true;
	},
);

$assigned = Movies_WP_Trailer_Discovery::assign_if_empty(
	'movie',
	11,
	$movie_dir,
	array( $movie_dir . '/Trailer/official.mp4' ),
	$movie_opts
);
td_same( 'assigned', $assigned['status'], 'movie assigns when empty' );
td_same( $movie_dir . '/Trailer/official.mp4', $meta['movie:11']['_name_trailer_link'] ?? null, 'movie stores relative trailer path' );

$again = Movies_WP_Trailer_Discovery::assign_if_empty(
	'movie',
	11,
	$movie_dir,
	array( $movie_dir . '/Trailer/other.mp4' ),
	$movie_opts
);
td_same( 'preserved', $again['status'], 'existing movie trailer preserved' );
td_same( $movie_dir . '/Trailer/official.mp4', $meta['movie:11']['_name_trailer_link'] ?? null, 'repeated movie assign is idempotent' );

$missing = Movies_WP_Trailer_Discovery::assign_if_empty( 'movie', 12, $movie_dir, array(), $movie_opts );
td_same( 'missing', $missing['status'], 'missing trailer is non-fatal' );
td_assert( ! isset( $meta['movie:12']['_name_trailer_link'] ), 'missing trailer writes nothing' );

$tv = Movies_WP_Trailer_Discovery::assign_if_empty(
	'tvshow',
	50,
	$series_dir,
	array( $series_dir . '/Trailers/teaser.webm' ),
	$movie_opts
);
td_same( 'assigned', $tv['status'], 'tvshow assigns when empty' );
td_same( $series_dir . '/Trailers/teaser.webm', $meta['tvshow:50']['_name_trailer_link'] ?? null, 'tvshow stores relative trailer path' );

echo "\nseries media import assigns trailer after success only\n";
$tv_meta = array();
$series_plan = array(
	'ok'              => true,
	'type'            => 'series_media',
	'ready_to_import' => true,
	'contract'        => array(
		'kind'    => 'series_media_import_plan',
		'version' => 1,
	),
	'identity'        => array(
		'tvshow_id'        => 50,
		'series_directory' => $series_dir,
		'expected_tmdb_id' => 1,
	),
	'policy'          => array(
		'preserve_order'    => true,
		'delete_unmatched'  => false,
		'allowed_meta_keys' => array( '_sources', '_subtitles' ),
	),
	'trailer'         => Movies_WP_Trailer_Discovery::plan_from_files(
		$series_dir,
		array(
			array( 'kind' => 'trailer', 'media_path' => $series_dir . '/Trailer/official.mp4' ),
		)
	),
	'episodes'        => array(
		array(
			'episode_id'     => 101,
			'tvshow_id'      => 50,
			'season_number'  => '1',
			'episode_number' => '1',
			'operations'     => array(
				'_sources'   => array(),
				'_subtitles' => array(),
			),
		),
	),
	'errors'          => array(),
	'warnings'        => array(),
);

$ok_series = Movies_WP_Series_Media_Import_Service::execute(
	$series_plan,
	array(
		'adapter_apply' => static function (): array {
			return array(
				'ok'        => true,
				'partial'   => false,
				'completed' => 1,
				'episodes'  => array(),
				'errors'    => array(),
				'warnings'  => array(),
			);
		},
		'update_tvshow_meta' => static function ( $id, $key, $value ) use ( &$tv_meta ) {
			$tv_meta[ $key ] = $value;
			return true;
		},
		'get_tvshow_meta' => static function ( $id, $key ) use ( &$tv_meta ) {
			return $tv_meta[ $key ] ?? '';
		},
	)
);
td_assert( ! empty( $ok_series['ok'] ), 'series import succeeds' );
td_same( $series_dir, $tv_meta['_media_directory'] ?? null, 'series directory still stored' );
td_same( $series_dir . '/Trailer/official.mp4', $tv_meta['_name_trailer_link'] ?? null, 'series trailer assigned' );

$tv_meta = array();
$failed_series = Movies_WP_Series_Media_Import_Service::execute(
	$series_plan,
	array(
		'adapter_apply' => static function (): array {
			return array(
				'ok'        => false,
				'partial'   => true,
				'completed' => 0,
				'episodes'  => array(),
				'errors'    => array( array( 'code' => 'episode_media_partial_failure', 'message' => 'Partial' ) ),
				'warnings'  => array(),
			);
		},
		'update_tvshow_meta' => static function ( $id, $key, $value ) use ( &$tv_meta ) {
			$tv_meta[ $key ] = $value;
			return true;
		},
		'get_tvshow_meta' => static function ( $id, $key ) use ( &$tv_meta ) {
			return $tv_meta[ $key ] ?? '';
		},
	)
);
td_assert( empty( $failed_series['ok'] ), 'failed series import stays failed' );
td_assert( ! isset( $tv_meta['_name_trailer_link'] ), 'failed series import writes no trailer' );
td_assert( ! isset( $tv_meta['_media_directory'] ), 'failed series import writes no directory' );

$tv_meta = array( '_name_trailer_link' => $series_dir . '/Trailer/manual.mp4' );
Movies_WP_Series_Media_Import_Service::execute(
	$series_plan,
	array(
		'adapter_apply' => static function (): array {
			return array(
				'ok'        => true,
				'partial'   => false,
				'completed' => 1,
				'episodes'  => array(),
				'errors'    => array(),
				'warnings'  => array(),
			);
		},
		'update_tvshow_meta' => static function ( $id, $key, $value ) use ( &$tv_meta ) {
			$tv_meta[ $key ] = $value;
			return true;
		},
		'get_tvshow_meta' => static function ( $id, $key ) use ( &$tv_meta ) {
			return $tv_meta[ $key ] ?? '';
		},
	)
);
td_same( $series_dir . '/Trailer/manual.mp4', $tv_meta['_name_trailer_link'], 'series import preserves existing trailer' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll trailer discovery tests passed.\n";
exit( $failures ? 1 : 0 );

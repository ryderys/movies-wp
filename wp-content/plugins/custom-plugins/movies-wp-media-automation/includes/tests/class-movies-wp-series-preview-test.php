<?php
/**
 * CLI tests for the side-effect-free Series preview domain layer.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-preview-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-preview-test/' );
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
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key ) {
		unset( $key );
		return array( 'tmdb' => array( 'api_key' => 'test-key' ) );
	}
}

$plugin = dirname( __DIR__ );
require_once $plugin . '/class-movies-wp-tmdb-tv-preview-client.php';
require_once $plugin . '/class-movies-wp-series-preview-service.php';

$failures = 0;

function series_assert_true( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function series_assert_same( $expected, $actual, string $label ): void {
	series_assert_true( $expected === $actual, $label . ' got=' . var_export( $actual, true ) );
}

function valid_series_fixture(): array {
	return array(
		'id'                 => 1396,
		'name'               => 'Breaking Bad',
		'original_name'      => 'Breaking Bad',
		'overview'           => 'Overview',
		'first_air_date'     => '2008-01-20',
		'vote_average'       => 8.91,
		'poster_path'        => '/poster.jpg',
		'backdrop_path'      => '/backdrop.jpg',
		'genres'             => array(
			array( 'id' => 18, 'name' => 'Drama' ),
			array( 'id' => 80, 'name' => 'Crime' ),
		),
		'origin_country'     => array( 'US' ),
		'original_language'  => 'en',
		'number_of_seasons'  => 1,
		'number_of_episodes' => 1,
		'external_ids'       => array( 'imdb_id' => 'tt0903747' ),
		'credits'            => array(
			'cast' => array(
				array( 'id' => 1, 'name' => 'Bryan Cranston', 'character' => 'Walter White', 'order' => 0 ),
			),
			'crew' => array(
				array( 'id' => 2, 'name' => 'Vince Gilligan', 'job' => 'Creator' ),
			),
		),
		'seasons'            => array(
			array(
				'season_number' => 1,
				'name'          => 'Season 1',
				'air_date'      => '2008-01-20',
				'overview'      => 'Season overview',
				'poster_path'   => '/season.jpg',
				'episode_count' => 1,
				'episodes'      => array(
					array(
						'id'             => 62085,
						'season_number'  => 1,
						'episode_number' => 1,
						'name'           => 'Pilot',
						'overview'       => 'Pilot overview',
						'air_date'       => '2008-01-20',
						'runtime'        => 59,
						'still_path'     => '/still.jpg',
					),
				),
			),
		),
	);
}

echo "Series preview normalization\n";

$valid = Movies_WP_Tmdb_TV_Preview_Client::normalize_series( valid_series_fixture() );
series_assert_true( ! is_wp_error( $valid ), 'valid series normalizes' );
series_assert_same( 1396, $valid['tmdb_id'], 'series TMDb ID is normalized' );
series_assert_same( 'tt0903747', $valid['imdb_id'], 'external IMDb ID is normalized' );
series_assert_same( '/poster.jpg', $valid['poster_path'], 'poster path is retained' );
series_assert_same( '/backdrop.jpg', $valid['backdrop_path'], 'backdrop path is retained' );
series_assert_same( 1, $valid['seasons'][0]['episodes'][0]['season_number'], 'episode season identity is retained' );
series_assert_same( 59, $valid['seasons'][0]['episodes'][0]['runtime'], 'episode runtime is normalized' );

$minimal = Movies_WP_Tmdb_TV_Preview_Client::normalize_series(
	array(
		'id'   => 10,
		'name' => 'Minimal',
	)
);
series_assert_true( ! is_wp_error( $minimal ), 'missing optional series fields are accepted' );
series_assert_same( '', $minimal['original_name'], 'missing original name becomes empty string' );
series_assert_same( null, $minimal['rating'], 'missing rating becomes null' );
series_assert_same( array(), $minimal['genres'], 'missing genres become an empty list' );
series_assert_same( null, $minimal['poster_path'], 'missing poster becomes null' );
series_assert_same( null, $minimal['poster_url'], 'missing poster URL becomes null' );
series_assert_same( null, $minimal['backdrop_path'], 'missing backdrop becomes null' );
series_assert_same( null, $minimal['backdrop_url'], 'missing backdrop URL becomes null' );

$specials = Movies_WP_Tmdb_TV_Preview_Client::normalize_season(
	array(
		'season_number' => 0,
		'name'          => '',
		'episodes'      => array(
			array(
				'id'             => 500,
				'episode_number' => 1,
				'name'           => 'Special',
			),
		),
	)
);
series_assert_true( ! is_wp_error( $specials ), 'Season 0 is accepted' );
series_assert_same( 0, $specials['season_number'], 'Season 0 identity remains numeric zero' );
series_assert_same( 'Specials', $specials['name'], 'Season 0 receives the Specials fallback label' );
series_assert_same( 0, $specials['episodes'][0]['season_number'], 'Season 0 propagates to episodes' );

$incomplete_episode = Movies_WP_Tmdb_TV_Preview_Client::normalize_episode(
	array(
		'id'             => 501,
		'season_number'  => 0,
		'episode_number' => 2,
		'name'           => 'Another Special',
	)
);
series_assert_true( ! is_wp_error( $incomplete_episode ), 'missing optional episode fields are accepted' );
series_assert_same( null, $incomplete_episode['still_path'], 'missing episode still becomes null' );
series_assert_same( null, $incomplete_episode['still_url'], 'missing episode still URL becomes null' );
series_assert_same( null, $incomplete_episode['runtime'], 'missing runtime becomes null' );
series_assert_same( '', $incomplete_episode['air_date'], 'missing air date becomes empty string' );

$malformed_series = Movies_WP_Tmdb_TV_Preview_Client::normalize_series( array( 'name' => 'Missing ID' ) );
series_assert_true( is_wp_error( $malformed_series ), 'series without TMDb ID is rejected' );
series_assert_same( 'series_preview_invalid_tmdb_response', $malformed_series->get_error_code(), 'malformed series error is stable' );

$malformed_name = Movies_WP_Tmdb_TV_Preview_Client::normalize_series( array( 'id' => 100 ) );
series_assert_true( is_wp_error( $malformed_name ), 'series without a name is rejected' );

$malformed_season = Movies_WP_Tmdb_TV_Preview_Client::normalize_season( array( 'name' => 'No number' ) );
series_assert_true( is_wp_error( $malformed_season ), 'season without numeric identity is rejected' );

$malformed_episode = Movies_WP_Tmdb_TV_Preview_Client::normalize_episode(
	array(
		'id'            => 500,
		'season_number' => 1,
	)
);
series_assert_true( is_wp_error( $malformed_episode ), 'episode without episode number is rejected' );

$preview_fixture                   = valid_series_fixture();
$preview_fixture['poster_path']    = null;
$preview_fixture['backdrop_path']  = null;
$preview_fixture['seasons'][0]['episodes'][0]['still_path'] = null;
$preview = Movies_WP_Series_Preview_Service::build(
	array(
		'tmdb_id' => 1396,
		'title'   => 'برکینگ بد',
		'summary' => '',
	),
	array(
		'get_series' => static function ( $tmdb_id ) use ( $preview_fixture ) {
			unset( $tmdb_id );
			return Movies_WP_Tmdb_TV_Preview_Client::normalize_series( $preview_fixture );
		},
	)
);
series_assert_true( ! is_wp_error( $preview ), 'preview service accepts normalized Series data' );
series_assert_same( 'series', $preview['type'], 'preview is explicitly typed as Series' );
series_assert_true( true === $preview['ready_to_import'], 'missing optional images do not block import' );
$warning_codes = array_column( $preview['validation']['warnings'], 'code' );
series_assert_true( in_array( 'series_poster_missing', $warning_codes, true ), 'missing poster produces a warning' );
series_assert_true( in_array( 'series_backdrop_missing', $warning_codes, true ), 'missing backdrop produces a warning' );
series_assert_true( in_array( 'series_episode_still_missing', $warning_codes, true ), 'missing episode still produces a warning' );

$invalid_input = Movies_WP_Series_Preview_Service::build(
	array( 'tmdb_id' => 0, 'title' => '' ),
	array( 'get_series' => static fn() => array() )
);
series_assert_true( is_wp_error( $invalid_input ), 'invalid operator input is rejected before TMDb access' );

echo "\n";
if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} assertion(s) failed.\n" );
	exit( 1 );
}

echo "All Series preview assertions passed.\n";

<?php
/**
 * CLI tests for enqueueing Telegram announcements after successful imports.
 *
 * php -n wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-telegram-enqueue-test.php
 *
 * Uses in-memory job and queue stores. Does not send Telegram messages.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-telegram-enqueue-test/' );
}
if ( ! defined( 'MOVIES_WP_SERIES_IMPORT_TEST_MEMORY' ) ) {
	define( 'MOVIES_WP_SERIES_IMPORT_TEST_MEMORY', true );
}
if ( ! defined( 'MOVIES_WP_TELEGRAM_TEST_MEMORY' ) ) {
	define( 'MOVIES_WP_TELEGRAM_TEST_MEMORY', true );
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
if ( ! function_exists( 'maybe_serialize' ) ) {
	function maybe_serialize( $data ) {
		return ( is_array( $data ) || is_object( $data ) ) ? serialize( $data ) : $data;
	}
}

$options = array();
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $options;
		return $options[ $name ] ?? $default;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
		global $options;
		unset( $deprecated, $autoload );
		if ( array_key_exists( $name, $options ) ) {
			return false;
		}
		$options[ $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		global $options;
		unset( $autoload );
		$options[ $name ] = $value;
		return true;
	}
}

if ( ! class_exists( 'Movies_WP_Media_Import_Service' ) ) {
	class Movies_WP_Media_Import_Service {
		public static $result = array(
			'ok'       => true,
			'movie_id' => 41,
		);

		public static function execute( array $request ) {
			unset( $request );
			return self::$result;
		}

		public static function safe_text( $text ) {
			return (string) $text;
		}
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-publisher.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-movie-import-job-runner.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-job-runner.php';

$failures = 0;
$http_calls = 0;

function telegram_enqueue_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function telegram_enqueue_snapshot( $type, $post_id, array $options = array() ) {
	unset( $post_id, $options );
	return array(
		'ok'       => true,
		'snapshot' => array(
			'type'         => (string) $type,
			'title'        => 'نمونه',
			'series_title' => 'سریال',
			'season'       => 1,
			'episode'      => 2,
			'description'  => '',
			'genres'       => array(),
			'permalink'    => '',
			'poster_url'   => '',
		),
	);
}

function telegram_enqueue_options( array $extra = array() ): array {
	return array_merge(
		array(
			'now'                => 1760208372,
			'telegram_snapshot'  => 'telegram_enqueue_snapshot',
		),
		$extra
	);
}

function telegram_enqueue_enable( bool $global, bool $movies, bool $series, bool $episodes ): void {
	global $options;
	$options[ Movies_WP_Telegram_Config::OPT_GLOBAL ]  = $global;
	$options[ Movies_WP_Telegram_Config::OPT_MOVIE ]   = $movies;
	$options[ Movies_WP_Telegram_Config::OPT_SERIES ]  = $series;
	$options[ Movies_WP_Telegram_Config::OPT_EPISODE ] = $episodes;
}

function telegram_enqueue_credentials( bool $present ): void {
	if ( $present ) {
		putenv( Movies_WP_Telegram_Config::TOKEN_KEY . '=test-token' );
		putenv( Movies_WP_Telegram_Config::CHAT_KEY . '=-1001234567890' );
		return;
	}
	putenv( Movies_WP_Telegram_Config::TOKEN_KEY );
	putenv( Movies_WP_Telegram_Config::CHAT_KEY );
}

function telegram_enqueue_movie( array $options ): array {
	$context = array(
		'now'     => 1760208372,
		'user_id' => 0,
		'blog_id' => 1,
	);
	$job = Movies_WP_Movie_Import_Job_Store::create(
		array(
			'tmdb_id'         => 882580,
			'title'           => 'Christmas Carol',
			'media_directory' => 'Movie/Korea/2022/Christmas.Carol',
		),
		$context
	);
	$token = is_array( $job ) ? (string) $job['token'] : '';
	$result = Movies_WP_Movie_Import_Job_Runner::run( $token, $options );
	return is_array( $result ) ? $result : array( 'status' => 'error', 'result' => $result );
}

function telegram_enqueue_series_job( int $series_id ): array {
	static $snapshot_id = 0;
	++$snapshot_id;
	$created = Movies_WP_Series_Import_Job_Store::create(
		array(
			'series_id'   => $series_id,
			'tmdb_id'     => 1396,
			'snapshot_id' => $snapshot_id,
			'user_id'     => 1,
			'result'      => array(),
		),
		array( 'now' => 1760208372 )
	);
	if ( ! is_array( $created ) ) {
		return array();
	}
	$claimed = Movies_WP_Series_Import_Job_Store::claim( (string) $created['token'], array( 'now' => 1760208372 ) );
	return is_array( $claimed ) ? $claimed : array();
}

function telegram_enqueue_invoke( string $method, array $args ): void {
	$ref = new ReflectionMethod( 'Movies_WP_Series_Import_Job_Runner', $method );
	$ref->setAccessible( true );
	$ref->invokeArgs( null, $args );
}

$saved_env = array(
	Movies_WP_Telegram_Config::TOKEN_KEY => getenv( Movies_WP_Telegram_Config::TOKEN_KEY ),
	Movies_WP_Telegram_Config::CHAT_KEY  => getenv( Movies_WP_Telegram_Config::CHAT_KEY ),
);
telegram_enqueue_credentials( false );
Movies_WP_Telegram_Queue::reset_state();

echo "Telegram enqueue guards\n";
telegram_enqueue_enable( false, false, false, false );
$disabled = Movies_WP_Telegram_Publisher::enqueue( 'movie', 41, 'job-token-1', telegram_enqueue_options() );
telegram_enqueue_assert( false === $disabled['enqueued'] && 0 === Movies_WP_Telegram_Queue::count_rows(), 'disabled switches do not enqueue' );

telegram_enqueue_enable( true, true, true, true );
$missing = Movies_WP_Telegram_Publisher::enqueue( 'movie', 41, 'job-token-1', telegram_enqueue_options() );
telegram_enqueue_assert( false === $missing['enqueued'] && 0 === Movies_WP_Telegram_Queue::count_rows(), 'missing credentials do not enqueue' );

telegram_enqueue_credentials( true );
$invalid_id = Movies_WP_Telegram_Publisher::enqueue( 'movie', 0, 'job-token-1', telegram_enqueue_options() );
telegram_enqueue_assert( false === $invalid_id['enqueued'] && 0 === Movies_WP_Telegram_Queue::count_rows(), 'a non-positive post ID is not queued' );
$invalid_type = Movies_WP_Telegram_Publisher::enqueue( 'trailer', 41, 'job-token-1', telegram_enqueue_options() );
telegram_enqueue_assert( false === $invalid_type['enqueued'] && 0 === Movies_WP_Telegram_Queue::count_rows(), 'an unknown content type is not queued' );

$bad_snapshot = Movies_WP_Telegram_Publisher::enqueue(
	'movie',
	41,
	'job-token-1',
	telegram_enqueue_options(
		array(
			'telegram_snapshot' => static function () {
				return array( 'ok' => false, 'code' => 'not_found' );
			},
		)
	)
);
telegram_enqueue_assert( false === $bad_snapshot['enqueued'] && 0 === Movies_WP_Telegram_Queue::count_rows(), 'a failed snapshot is not queued' );

echo "\nMovie import success\n";
Movies_WP_Media_Import_Service::$result = array(
	'ok'       => true,
	'movie_id' => 41,
	'message'  => 'Imported.',
);
$movie_options = telegram_enqueue_options();
$movie         = telegram_enqueue_movie( $movie_options );
telegram_enqueue_assert( 'completed' === (string) ( $movie['status'] ?? '' ), 'successful movie import stays completed' );
telegram_enqueue_assert( 41 === (int) ( $movie['result']['movie_id'] ?? 0 ), 'successful movie import keeps its movie ID' );
telegram_enqueue_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'a successful movie import enqueues one announcement' );

$again = Movies_WP_Movie_Import_Job_Runner::run( (string) $movie['token'], $movie_options );
$again = is_array( $again ) ? $again : array();
telegram_enqueue_assert( 'completed' === (string) ( $again['status'] ?? '' ), 'a duplicate movie callback stays completed' );
telegram_enqueue_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'a duplicate movie callback does not add a queue row' );

$second = telegram_enqueue_movie( $movie_options );
telegram_enqueue_assert( 'completed' === (string) ( $second['status'] ?? '' ), 'a later movie import stays completed' );
telegram_enqueue_assert( 2 === Movies_WP_Telegram_Queue::count_rows(), 'a later movie import with a new token enqueues another announcement' );

echo "\nMovie import failures\n";
Movies_WP_Media_Import_Service::$result = array(
	'ok'      => false,
	'message' => 'Import failed.',
);
$failed = telegram_enqueue_movie( $movie_options );
telegram_enqueue_assert( 'failed' === (string) ( $failed['status'] ?? '' ), 'a failed movie import stays failed' );
telegram_enqueue_assert( 2 === Movies_WP_Telegram_Queue::count_rows(), 'a failed movie import does not enqueue' );

Movies_WP_Media_Import_Service::$result = array(
	'ok'      => true,
	'message' => 'Imported.',
);
$no_id = telegram_enqueue_movie( $movie_options );
telegram_enqueue_assert( 'completed' === (string) ( $no_id['status'] ?? '' ), 'a movie import without an ID stays completed' );
telegram_enqueue_assert( 2 === Movies_WP_Telegram_Queue::count_rows(), 'a movie import without an ID does not enqueue' );

Movies_WP_Media_Import_Service::$result = array(
	'ok'       => true,
	'movie_id' => 41,
	'message'  => 'Imported.',
);
$thrown = telegram_enqueue_movie(
	telegram_enqueue_options(
		array(
			'telegram_insert' => static function () {
				throw new RuntimeException( 'queue insert failed' );
			},
		)
	)
);
telegram_enqueue_assert( 'completed' === (string) ( $thrown['status'] ?? '' ), 'a queue exception leaves the movie job completed' );
telegram_enqueue_assert( 41 === (int) ( $thrown['result']['movie_id'] ?? 0 ), 'a queue exception leaves the movie result unchanged' );
telegram_enqueue_assert( 2 === Movies_WP_Telegram_Queue::count_rows(), 'a queue exception does not add a row' );

echo "\nSeries media and finalize\n";
Movies_WP_Telegram_Queue::reset_state();
$series = telegram_enqueue_series_job( 70 );
$series_options = telegram_enqueue_options(
	array(
		'_claim_token' => (string) ( $series['claim_token'] ?? '' ),
		'media_import_execute' => static function () {
			return array(
				'ok'        => true,
				'tvshow_id' => 70,
			);
		},
	)
);
telegram_enqueue_invoke( 'phase_media', array( (string) $series['token'], $series, $series_options ) );
$after_media = Movies_WP_Series_Import_Job_Store::find_by_token( (string) $series['token'] );
$after_media = is_array( $after_media ) ? $after_media : array();
telegram_enqueue_assert( 'running' === (string) ( $after_media['status'] ?? '' ), 'series media success stays running' );
telegram_enqueue_assert( 'finalize' === (string) ( $after_media['phase'] ?? '' ), 'series media success advances to finalize' );
telegram_enqueue_assert( 0 === Movies_WP_Telegram_Queue::count_rows(), 'the series media phase does not enqueue' );

telegram_enqueue_invoke( 'phase_finalize', array( (string) $series['token'], $after_media, $series_options ) );
$final = Movies_WP_Series_Import_Job_Store::find_by_token( (string) $series['token'] );
$final = is_array( $final ) ? $final : array();
telegram_enqueue_assert( 'completed' === (string) ( $final['status'] ?? '' ), 'series finalize stays completed' );
telegram_enqueue_assert( 'completed' === (string) ( $final['phase'] ?? '' ), 'series finalize phase stays completed' );
telegram_enqueue_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'series finalize enqueues one announcement' );

telegram_enqueue_invoke( 'phase_finalize', array( (string) $series['token'], $final, $series_options ) );
$final_again = Movies_WP_Series_Import_Job_Store::find_by_token( (string) $series['token'] );
telegram_enqueue_assert( 'completed' === (string) ( is_array( $final_again ) ? $final_again['status'] : '' ), 'a duplicate series callback stays completed' );
telegram_enqueue_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'a duplicate series callback does not add a queue row' );

$other = telegram_enqueue_series_job( 71 );
$other_options = telegram_enqueue_options(
	array(
		'_claim_token' => (string) ( $other['claim_token'] ?? '' ),
	)
);
telegram_enqueue_invoke( 'phase_finalize', array( (string) $other['token'], $other, $other_options ) );
$other_final = Movies_WP_Series_Import_Job_Store::find_by_token( (string) $other['token'] );
telegram_enqueue_assert( 'completed' === (string) ( is_array( $other_final ) ? $other_final['status'] : '' ), 'a later series import stays completed' );
telegram_enqueue_assert( 2 === Movies_WP_Telegram_Queue::count_rows(), 'a later series import with a new token enqueues another announcement' );

echo "\nEpisode import\n";
Movies_WP_Telegram_Queue::reset_state();
$episode_job = telegram_enqueue_series_job( 70 );
$episode_options = telegram_enqueue_options(
	array(
		'_claim_token'    => (string) ( $episode_job['claim_token'] ?? '' ),
		'persist_episode' => static function ( $series_id, array $episode ) {
			unset( $series_id, $episode );
			return array(
				'ok'         => true,
				'episode_id' => 80,
			);
		},
	)
);
$episode_plan = array(
	'seasons' => array(
		array(
			'season_number' => '1',
			'episodes'      => array(
				array(
					'season_number'  => '1',
					'episode_number' => 1,
				),
			),
		),
	),
);
telegram_enqueue_invoke( 'phase_episodes', array( (string) $episode_job['token'], $episode_job, $episode_plan, $episode_options ) );
$after_episode = Movies_WP_Series_Import_Job_Store::find_by_token( (string) $episode_job['token'] );
$after_episode = is_array( $after_episode ) ? $after_episode : array();
telegram_enqueue_assert( 'running' === (string) ( $after_episode['status'] ?? '' ), 'a successful episode import stays running' );
telegram_enqueue_assert( 1 === (int) ( $after_episode['episode_done'] ?? 0 ), 'a successful episode import records progress' );
telegram_enqueue_assert( 80 === (int) ( $after_episode['last_episode_id'] ?? 0 ), 'a successful episode import keeps the episode ID' );
telegram_enqueue_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'a successful episode import enqueues one announcement' );

$repeat = Movies_WP_Telegram_Publisher::enqueue( 'episode', 80, (string) $episode_job['token'], $episode_options );
telegram_enqueue_assert( true === $repeat['duplicate'], 'the same episode token is an idempotent queue insert' );
telegram_enqueue_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'a duplicate episode callback does not add a queue row' );

$failed_episode = telegram_enqueue_series_job( 70 );
$failed_episode_options = telegram_enqueue_options(
	array(
		'_claim_token'    => (string) ( $failed_episode['claim_token'] ?? '' ),
		'persist_episode' => static function () {
			return array(
				'ok'      => false,
				'message' => 'Episode failed.',
			);
		},
	)
);
telegram_enqueue_invoke( 'phase_episodes', array( (string) $failed_episode['token'], $failed_episode, $episode_plan, $failed_episode_options ) );
$failed_episode_row = Movies_WP_Series_Import_Job_Store::find_by_token( (string) $failed_episode['token'] );
telegram_enqueue_assert( 'failed' === (string) ( is_array( $failed_episode_row ) ? $failed_episode_row['status'] : '' ), 'a failed episode import stays failed' );
telegram_enqueue_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'a failed episode import does not enqueue' );

$skipped_episode = telegram_enqueue_series_job( 70 );
$skipped_plan    = $episode_plan;
$skipped_plan['seasons'][0]['episodes'][0]['existing_episode_id'] = 81;
$skipped_options = telegram_enqueue_options(
	array(
		'_claim_token' => (string) ( $skipped_episode['claim_token'] ?? '' ),
		'persist_episode' => static function () {
			throw new RuntimeException( 'existing episodes must not call apply' );
		},
	)
);
telegram_enqueue_invoke( 'phase_episodes', array( (string) $skipped_episode['token'], $skipped_episode, $skipped_plan, $skipped_options ) );
$skipped_row = Movies_WP_Series_Import_Job_Store::find_by_token( (string) $skipped_episode['token'] );
telegram_enqueue_assert( 'running' === (string) ( is_array( $skipped_row ) ? $skipped_row['status'] : '' ), 'an existing episode stays recorded as running' );
telegram_enqueue_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'an existing episode does not enqueue' );

$explode = telegram_enqueue_series_job( 70 );
$explode_options = telegram_enqueue_options(
	array(
		'_claim_token'    => (string) ( $explode['claim_token'] ?? '' ),
		'persist_episode' => static function () {
			return array(
				'ok'         => true,
				'episode_id' => 82,
			);
		},
		'telegram_insert' => static function () {
			throw new RuntimeException( 'database unavailable' );
		},
	)
);
telegram_enqueue_invoke( 'phase_episodes', array( (string) $explode['token'], $explode, $episode_plan, $explode_options ) );
$explode_row = Movies_WP_Series_Import_Job_Store::find_by_token( (string) $explode['token'] );
$explode_row = is_array( $explode_row ) ? $explode_row : array();
telegram_enqueue_assert( 'running' === (string) ( $explode_row['status'] ?? '' ), 'a queue exception leaves the episode job running' );
telegram_enqueue_assert( 82 === (int) ( $explode_row['last_episode_id'] ?? 0 ), 'a queue exception keeps the saved episode ID' );
telegram_enqueue_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'a database exception does not add an episode row' );

telegram_enqueue_assert( 0 === $http_calls, 'import enqueue does not send a Telegram message' );

foreach ( $saved_env as $key => $value ) {
	if ( is_string( $value ) && '' !== $value ) {
		putenv( $key . '=' . $value );
	} else {
		putenv( $key );
	}
}

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Telegram enqueue tests passed.\n";
exit( $failures > 0 ? 1 : 0 );

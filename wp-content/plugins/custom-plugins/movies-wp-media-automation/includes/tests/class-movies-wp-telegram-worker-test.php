<?php
/**
 * CLI tests for the Telegram Action Scheduler worker.
 *
 * php -n wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-telegram-worker-test.php
 *
 * Client responses are stubbed. No Telegram request is sent.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-telegram-worker-test/' );
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
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		global $telegram_worker_hooks;
		$telegram_worker_hooks[] = array(
			'hook'     => (string) $hook,
			'priority' => (int) $priority,
			'args'     => (int) $accepted_args,
		);
	}
}

$options = array();
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $options;
		return $options[ $name ] ?? $default;
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

require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-worker.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-publisher.php';

$failures = 0;
$telegram_worker_hooks = array();
$now = 1760208372;

function telegram_worker_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function telegram_worker_enable( bool $global, bool $movies, bool $series, bool $episodes ): void {
	global $options;
	$options[ Movies_WP_Telegram_Config::OPT_GLOBAL ]  = $global;
	$options[ Movies_WP_Telegram_Config::OPT_MOVIE ]   = $movies;
	$options[ Movies_WP_Telegram_Config::OPT_SERIES ]  = $series;
	$options[ Movies_WP_Telegram_Config::OPT_EPISODE ] = $episodes;
}

function telegram_worker_credentials( bool $present ): void {
	if ( $present ) {
		putenv( Movies_WP_Telegram_Config::TOKEN_KEY . '=123456789:TESTTOKENVALUE12' );
		putenv( Movies_WP_Telegram_Config::CHAT_KEY . '=-1001234567890' );
		return;
	}
	putenv( Movies_WP_Telegram_Config::TOKEN_KEY );
	putenv( Movies_WP_Telegram_Config::CHAT_KEY );
}

function telegram_worker_snapshot( $type, $post_id, array $options = array() ) {
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
			'permalink'    => 'https://example.com/movie/sample',
			'poster_url'   => 'https://cdn.example.com/Movie/Korea/secret.jpg',
		),
	);
}

function telegram_worker_insert( string $type, int $post_id, string $source ): void {
	global $now;
	$result = Movies_WP_Telegram_Queue::insert( $type, $post_id, $source, array( 'now' => $now ) );
	if ( empty( $result['ok'] ) ) {
		throw new RuntimeException( 'fixture insert failed' );
	}
}

function telegram_worker_options( array $extra = array() ): array {
	global $now;
	return array_merge(
		array(
			'now'               => $now,
			'telegram_snapshot' => 'telegram_worker_snapshot',
		),
		$extra
	);
}

function telegram_worker_status_count( string $status ): int {
	$counts = Movies_WP_Telegram_Queue::counts();
	return (int) ( $counts[ $status ] ?? 0 );
}

$saved_env = array(
	Movies_WP_Telegram_Config::TOKEN_KEY => getenv( Movies_WP_Telegram_Config::TOKEN_KEY ),
	Movies_WP_Telegram_Config::CHAT_KEY  => getenv( Movies_WP_Telegram_Config::CHAT_KEY ),
);

echo "Telegram worker scheduling guards\n";
Movies_WP_Telegram_Worker::init();
$registered = false;
foreach ( $telegram_worker_hooks as $hook ) {
	if ( Movies_WP_Telegram_Worker::HOOK === $hook['hook'] && 0 === $hook['args'] ) {
		$registered = true;
	}
}
telegram_worker_assert( $registered, 'init only registers the Action Scheduler hook' );
$worker_source = (string) file_get_contents( dirname( __DIR__ ) . '/class-movies-wp-telegram-worker.php' );
telegram_worker_assert( ! str_contains( $worker_source, 'register_activation_hook' ), 'the worker is not scheduled from plugin activation' );
telegram_worker_assert( ! str_contains( $worker_source, 'wp_schedule_event' ), 'the worker does not use WP-Cron' );
$bootstrap = (string) file_get_contents( dirname( __DIR__, 2 ) . '/movies-wp-media-automation.php' );
telegram_worker_assert( str_contains( $bootstrap, 'Movies_WP_Telegram_Worker::init' ), 'plugin bootstrap registers the worker' );
telegram_worker_assert( ! str_contains( $bootstrap, 'register_activation_hook' ), 'plugin bootstrap has no activation scheduler' );
telegram_worker_assert( ! str_contains( $bootstrap, 'movies_wp_telegram_enabled' ), 'plugin bootstrap does not enable publishing' );
$movie_runner = (string) file_get_contents( dirname( __DIR__ ) . '/class-movies-wp-movie-import-job-runner.php' );
$series_runner = (string) file_get_contents( dirname( __DIR__ ) . '/class-movies-wp-series-import-job-runner.php' );
telegram_worker_assert( ! str_contains( $movie_runner, 'Movies_WP_Telegram_Client::deliver' ), 'the movie runner does not send Telegram messages' );
telegram_worker_assert( ! str_contains( $series_runner, 'Movies_WP_Telegram_Client::deliver' ), 'the series runner does not send Telegram messages' );

Movies_WP_Telegram_Queue::reset_state();
telegram_worker_credentials( true );
telegram_worker_enable( false, true, true, true );
$schedule_calls = 0;
$disabled_schedule = Movies_WP_Telegram_Worker::ensure_scheduled(
	telegram_worker_options(
		array(
			'schedule_action' => static function () use ( &$schedule_calls ) {
				++$schedule_calls;
				return 1;
			},
		)
	)
);
telegram_worker_assert( false === $disabled_schedule && 0 === $schedule_calls, 'disabled publishing does not schedule a worker' );

telegram_worker_enable( true, true, true, true );
$planned = array();
$schedule_hook = static function ( $when, $hook, $args, $group, $unique ) use ( &$planned ) {
	$planned[] = array(
		'when'   => (int) $when,
		'hook'   => (string) $hook,
		'args'   => $args,
		'group'  => (string) $group,
		'unique' => (bool) $unique,
	);
	return count( $planned );
};
$next_hook = static function ( ...$unused ) use ( &$planned, $now ) {
	unset( $unused );
	if ( array() === $planned ) {
		return false;
	}
	return (int) $planned[0]['when'] > 0 ? (int) $planned[0]['when'] : $now;
};
$first_schedule = Movies_WP_Telegram_Worker::ensure_scheduled(
	telegram_worker_options(
		array(
			'schedule_action' => $schedule_hook,
			'next_scheduled'  => $next_hook,
		)
	)
);
$second_schedule = Movies_WP_Telegram_Worker::ensure_scheduled(
	telegram_worker_options(
		array(
			'schedule_action' => $schedule_hook,
			'next_scheduled'  => $next_hook,
		)
	)
);
telegram_worker_assert( true === $first_schedule && false === $second_schedule, 'a second schedule is skipped while one action is pending' );
telegram_worker_assert( 1 === count( $planned ) && true === $planned[0]['unique'], 'the scheduled action is unique' );
telegram_worker_assert( Movies_WP_Telegram_Worker::HOOK === $planned[0]['hook'], 'the scheduled hook is the worker hook' );
telegram_worker_assert( Movies_WP_Telegram_Worker::GROUP === $planned[0]['group'], 'the scheduled action uses the worker group' );

echo "\nTelegram worker disabled and incomplete configuration\n";
Movies_WP_Telegram_Queue::reset_state();
telegram_worker_credentials( false );
telegram_worker_enable( true, true, true, true );
telegram_worker_insert( 'movie', 41, 'job-token-1' );
$deliveries = 0;
$missing = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function () use ( &$deliveries ) {
				++$deliveries;
				return array( 'outcome' => 'sent', 'message_id' => '11', 'error' => '' );
			},
		)
	)
);
telegram_worker_assert( 0 === $missing['processed'] && 0 === $deliveries, 'missing credentials do not send' );
telegram_worker_assert( 1 === telegram_worker_status_count( 'pending' ), 'missing credentials leave the row pending' );

telegram_worker_credentials( true );
telegram_worker_enable( false, false, false, false );
$disabled = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function () use ( &$deliveries ) {
				++$deliveries;
				return array( 'outcome' => 'sent', 'message_id' => '11', 'error' => '' );
			},
		)
	)
);
telegram_worker_assert( 0 === $disabled['processed'] && 0 === $deliveries, 'disabled switches do not send' );
telegram_worker_assert( 1 === telegram_worker_status_count( 'pending' ), 'disabled switches leave the row pending' );

echo "\nTelegram worker outcomes\n";
telegram_worker_enable( true, true, true, true );
Movies_WP_Telegram_Queue::reset_state();
telegram_worker_insert( 'movie', 41, 'job-token-sent' );
$sent = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function () {
				return array( 'outcome' => 'sent', 'message_id' => '4242', 'error' => '' );
			},
		)
	)
);
telegram_worker_assert( 1 === $sent['processed'] && 'sent' === $sent['results'][0]['outcome'] && true === $sent['results'][0]['saved'], 'a sent outcome stores the Telegram message ID' );
telegram_worker_assert( 1 === telegram_worker_status_count( 'sent' ), 'a sent row is marked sent' );
$sent_again = Movies_WP_Telegram_Worker::run( telegram_worker_options( array( 'deliver' => static function () {
	throw new RuntimeException( 'sent rows must not be delivered again' );
} ) ) );
telegram_worker_assert( 0 === $sent_again['processed'], 'a sent row is not sent again' );

Movies_WP_Telegram_Queue::reset_state();
telegram_worker_insert( 'series', 70, 'job-token-retry' );
$retry = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function () {
				return array(
					'outcome' => 'retryable',
					'error'   => 'bot123456789:TESTTOKENVALUE12 failed for https://cdn.example.com/secret Movie/Korea/secret.mp4',
				);
			},
		)
	)
);
telegram_worker_assert( 'retryable' === $retry['results'][0]['outcome'] && true === $retry['results'][0]['saved'], 'a retryable outcome uses the queue backoff' );
telegram_worker_assert( 1 === telegram_worker_status_count( 'retry' ), 'a retryable row waits for its next attempt' );
$errors = Movies_WP_Telegram_Queue::recent_failures( 5 );
$error_text = isset( $errors[0]['last_error'] ) ? (string) $errors[0]['last_error'] : '';
telegram_worker_assert( ! str_contains( $error_text, 'TESTTOKENVALUE12' ), 'stored errors do not contain the bot token' );
telegram_worker_assert( ! str_contains( $error_text, 'https://cdn.example.com/secret' ), 'stored errors do not contain the request URL' );
telegram_worker_assert( ! str_contains( $error_text, 'Movie/Korea/secret.mp4' ), 'stored errors do not contain an internal media path' );
$not_due = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function () {
				throw new RuntimeException( 'a retry is not due yet' );
			},
		)
	)
);
telegram_worker_assert( 0 === $not_due['processed'], 'a retry is not claimed before its backoff' );

Movies_WP_Telegram_Queue::reset_state();
telegram_worker_insert( 'episode', 80, 'job-token-permanent' );
$permanent = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function () {
				return array( 'outcome' => 'permanent', 'error' => 'chat not found' );
			},
		)
	)
);
telegram_worker_assert( 'permanent' === $permanent['results'][0]['outcome'] && 1 === telegram_worker_status_count( 'failed' ), 'a permanent outcome is marked failed' );
$permanent_again = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function () {
				throw new RuntimeException( 'failed rows must not be retried' );
			},
		)
	)
);
telegram_worker_assert( 0 === $permanent_again['processed'], 'a permanent failure is not retried' );

Movies_WP_Telegram_Queue::reset_state();
telegram_worker_insert( 'movie', 42, 'job-token-ambiguous' );
$ambiguous = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function () {
				return array( 'outcome' => 'ambiguous', 'error' => 'timed out' );
			},
		)
	)
);
telegram_worker_assert( 'ambiguous' === $ambiguous['results'][0]['outcome'] && 1 === telegram_worker_status_count( 'ambiguous' ), 'an ambiguous outcome is terminal' );
$ambiguous_later = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'now'     => $now + 100000,
			'deliver' => static function () {
				throw new RuntimeException( 'ambiguous rows must not be resent' );
			},
		)
	)
);
telegram_worker_assert( 0 === $ambiguous_later['processed'], 'an ambiguous row is not resent automatically' );

echo "\nTelegram worker batch, claims, and follow-up\n";
Movies_WP_Telegram_Queue::reset_state();
telegram_worker_enable( true, true, true, true );
telegram_worker_insert( 'movie', 41, 'job-token-batch' );
telegram_worker_insert( 'movie', 43, 'job-token-batch-2' );
telegram_worker_insert( 'movie', 44, 'job-token-batch-3' );
$batch_deliveries = 0;
$follow_ups = array();
$batch = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'batch_size'      => 2,
			'schedule_action' => static function ( $when, ...$unused ) use ( &$follow_ups ) {
				unset( $unused );
				$follow_ups[] = (int) $when;
				return 1;
			},
			'next_scheduled'  => static function ( ...$unused ) {
				unset( $unused );
				return false;
			},
			'deliver'         => static function () use ( &$batch_deliveries ) {
				++$batch_deliveries;
				return array( 'outcome' => 'sent', 'message_id' => (string) ( 5000 + $batch_deliveries ), 'error' => '' );
			},
		)
	)
);
telegram_worker_assert( 2 === $batch['processed'] && 2 === $batch_deliveries, 'the worker stops at the batch limit' );
telegram_worker_assert( 1 === telegram_worker_status_count( 'pending' ), 'a row past the batch limit stays pending' );
telegram_worker_assert( array() !== $follow_ups && $follow_ups[0] === $now, 'remaining due work schedules another worker' );
$remaining = Movies_WP_Telegram_Queue::claim( array( 'now' => $now ) );
telegram_worker_assert( is_array( $remaining ), 'the row past the batch limit can still be claimed' );
Movies_WP_Telegram_Queue::release_claim( (int) $remaining['id'], (string) $remaining['claim_token'], array( 'now' => $now ) );
telegram_worker_assert( false === Movies_WP_Telegram_Queue::release_claim( (int) $remaining['id'], (string) $remaining['claim_token'], array( 'now' => $now ) ), 'a released claim cannot be released again' );

Movies_WP_Telegram_Queue::reset_state();
telegram_worker_enable( true, true, true, false );
telegram_worker_insert( 'episode', 81, 'job-token-skip' );
telegram_worker_insert( 'movie', 44, 'job-token-movie' );
$typed = 0;
Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function ( array $snapshot ) use ( &$typed ) {
				++$typed;
				telegram_worker_assert( 'movie' === (string) ( $snapshot['type'] ?? '' ), 'a disabled episode type is not delivered' );
				return array( 'outcome' => 'sent', 'message_id' => '88', 'error' => '' );
			},
		)
	)
);
telegram_worker_assert( 1 === $typed && 1 === telegram_worker_status_count( 'pending' ), 'only the enabled content type is claimed' );

Movies_WP_Telegram_Queue::reset_state();
telegram_worker_enable( true, true, true, true );
telegram_worker_insert( 'movie', 45, 'job-token-held' );
$held = Movies_WP_Telegram_Queue::claim( array( 'now' => $now ) );
$held_deliveries = 0;
$busy = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function () use ( &$held_deliveries ) {
				++$held_deliveries;
				return array( 'outcome' => 'sent', 'message_id' => '89', 'error' => '' );
			},
		)
	)
);
telegram_worker_assert( is_array( $held ) && 0 === $busy['processed'] && 0 === $held_deliveries, 'a row owned by another claim is not delivered' );
telegram_worker_assert( 1 === telegram_worker_status_count( 'claimed' ), 'the existing claim stays owned' );

$expired = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'now'     => $now + Movies_WP_Telegram_Queue::LEASE_SECONDS + 5,
			'deliver' => static function () {
				return array( 'outcome' => 'sent', 'message_id' => '90', 'error' => '' );
			},
		)
	)
);
telegram_worker_assert( 1 === $expired['processed'] && 1 === telegram_worker_status_count( 'sent' ), 'an expired claim is recovered and delivered once' );

Movies_WP_Telegram_Queue::reset_state();
telegram_worker_insert( 'movie', 46, 'job-token-nested' );
$inner = null;
$nested = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function () use ( &$inner ) {
				$inner = Movies_WP_Telegram_Worker::run(
					telegram_worker_options(
						array(
							'deliver' => static function () {
								throw new RuntimeException( 'a nested worker must not deliver' );
							},
						)
					)
				);
				return array( 'outcome' => 'sent', 'message_id' => '91', 'error' => '' );
			},
		)
	)
);
telegram_worker_assert( is_array( $inner ) && true === $inner['busy'] && 0 === $inner['processed'], 'a concurrent worker in the same process does not claim' );
telegram_worker_assert( 1 === $nested['processed'] && 1 === telegram_worker_status_count( 'sent' ), 'the owning worker still completes its delivery' );

Movies_WP_Telegram_Queue::reset_state();
telegram_worker_insert( 'movie', 47, 'job-token-throw' );
$thrown = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'deliver' => static function () {
				throw new RuntimeException( 'bot123456789:TESTTOKENVALUE12 database down' );
			},
		)
	)
);
telegram_worker_assert( 'retryable' === $thrown['results'][0]['outcome'] && 1 === telegram_worker_status_count( 'retry' ), 'a client exception becomes a bounded retry' );
$thrown_error = Movies_WP_Telegram_Queue::recent_failures( 1 );
telegram_worker_assert( ! str_contains( (string) ( $thrown_error[0]['last_error'] ?? '' ), 'TESTTOKENVALUE12' ), 'an exception message is stored without credentials' );

$calls = array();
Movies_WP_Telegram_Queue::reset_state();
telegram_worker_insert( 'movie', 48, 'job-token-http' );
$http_result = Movies_WP_Telegram_Worker::run(
	telegram_worker_options(
		array(
			'telegram_snapshot' => static function () {
				return array(
					'ok'       => true,
					'snapshot' => array(
						'type'        => 'movie',
						'title'       => 'نمونه',
						'description' => '',
						'genres'      => array(),
						'permalink'   => 'https://example.com/movie/sample',
						'poster_url'  => 'https://cdn.example.com/Movie/Korea/secret.jpg',
					),
				);
			},
			'http_post'         => static function ( $url, array $args ) use ( &$calls ) {
				$calls[] = array(
					'url'  => (string) $url,
					'body' => (string) ( $args['body'] ?? '' ),
				);
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"ok":true,"result":{"message_id":92}}',
				);
			},
		)
	)
);
telegram_worker_assert( 1 === $http_result['processed'] && 'sent' === $http_result['results'][0]['outcome'], 'the real client can deliver through the worker without a network call' );
telegram_worker_assert( 1 === count( $calls ) && ! str_contains( $calls[0]['body'], 'Movie/Korea/secret.jpg' ), 'the outbound body omits an internal media path' );
telegram_worker_assert( ! str_contains( $calls[0]['body'], 'TESTTOKENVALUE12' ), 'the outbound body omits the bot token' );

echo "\nTelegram publisher schedules the worker\n";
Movies_WP_Telegram_Queue::reset_state();
$published_schedules = 0;
$published = Movies_WP_Telegram_Publisher::enqueue(
	'movie',
	49,
	'job-token-publish',
	telegram_worker_options(
		array(
			'schedule_action' => static function ( ...$unused ) use ( &$published_schedules ) {
				unset( $unused );
				++$published_schedules;
				return 7;
			},
			'next_scheduled'  => static function ( ...$unused ) use ( &$published_schedules, $now ) {
				unset( $unused );
				return $published_schedules > 0 ? $now : false;
			},
		)
	)
);
$published_again = Movies_WP_Telegram_Publisher::enqueue(
	'movie',
	49,
	'job-token-publish',
	telegram_worker_options(
		array(
			'schedule_action' => static function ( ...$unused ) use ( &$published_schedules ) {
				unset( $unused );
				++$published_schedules;
				return 8;
			},
			'next_scheduled'  => static function ( ...$unused ) use ( $now ) {
				unset( $unused );
				return $now;
			},
		)
	)
);
telegram_worker_assert( true === $published['enqueued'] && false === $published['duplicate'], 'a successful enqueue still reports the queue insert' );
telegram_worker_assert( true === $published_again['duplicate'] && 1 === $published_schedules, 'a duplicate enqueue does not schedule a second worker' );
telegram_worker_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'scheduling does not create another queue row' );

foreach ( $saved_env as $key => $value ) {
	if ( is_string( $value ) && '' !== $value ) {
		putenv( $key . '=' . $value );
	} else {
		putenv( $key );
	}
}

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Telegram worker tests passed.\n";
exit( $failures > 0 ? 1 : 0 );

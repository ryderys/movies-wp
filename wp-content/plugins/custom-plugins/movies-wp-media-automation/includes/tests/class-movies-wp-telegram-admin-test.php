<?php
/**
 * CLI tests for the Telegram administrator page.
 *
 * php -n wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-telegram-admin-test.php
 *
 * Telegram HTTP is stubbed. No message is sent.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-telegram-admin-test/' );
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
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text ) {
		return esc_html( $text );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		$value = strtolower( (string) $value );
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
	}
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action ) {
		echo '<input type="hidden" name="_wpnonce" value="test-nonce" />';
		return $action;
	}
}
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text, $type = 'primary', $name = 'submit', $wrap = true ) {
		unset( $type, $name, $wrap );
		echo '<button type="submit">' . esc_html( $text ) . '</button>';
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		unset( $hook, $callback, $priority, $args );
	}
}
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent, $page_title, $menu_title, $capability, $slug, $callback = null ) {
		global $telegram_admin_menus;
		$telegram_admin_menus[] = array(
			'parent'     => (string) $parent,
			'capability' => (string) $capability,
			'slug'       => (string) $slug,
		);
		unset( $page_title, $menu_title, $callback );
		return 'telegram-hook';
	}
}

$options = array();
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $options;
		return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
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

require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-admin.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-publisher.php';

$failures = 0;
$telegram_admin_menus = array();
$now = 1760208372;
$http_calls = array();

function telegram_admin_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function telegram_admin_post( array $post, array $extra = array() ) {
	return Movies_WP_Telegram_Admin::handle_post(
		array_merge(
			array(
				'_wpnonce' => 'valid',
			),
			$post
		),
		array_merge(
			array(
				'now'               => 1760208372,
				'current_user_can'  => static function ( $capability ) {
					return 'manage_options' === $capability;
				},
				'verify_nonce'      => static function ( $nonce, $action ) {
					unset( $action );
					return 'valid' === $nonce;
				},
			),
			$extra
		)
	);
}

function telegram_admin_http( array $extra = array() ): array {
	global $http_calls;
	$extra['http_post'] = static function ( $url, array $args ) use ( &$http_calls ) {
		$http_calls[] = array(
			'url'  => (string) $url,
			'body' => (string) ( $args['body'] ?? '' ),
		);
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"ok":true,"result":{"message_id":77}}',
		);
	};
	return $extra;
}

$saved_env = array(
	Movies_WP_Telegram_Config::TOKEN_KEY => getenv( Movies_WP_Telegram_Config::TOKEN_KEY ),
	Movies_WP_Telegram_Config::CHAT_KEY  => getenv( Movies_WP_Telegram_Config::CHAT_KEY ),
);
putenv( Movies_WP_Telegram_Config::TOKEN_KEY );
putenv( Movies_WP_Telegram_Config::CHAT_KEY );

echo "Telegram admin access and defaults\n";
Movies_WP_Telegram_Admin::register_menu();
telegram_admin_assert(
	isset( $telegram_admin_menus[0]['capability'] ) && 'manage_options' === $telegram_admin_menus[0]['capability'],
	'the menu requires manage_options'
);
$state = Movies_WP_Telegram_Admin::page_state();
telegram_admin_assert( false === $state['global_enabled'], 'the global switch defaults off' );
telegram_admin_assert( false === $state['movie_enabled'], 'the movie switch defaults off' );
telegram_admin_assert( false === $state['series_enabled'], 'the series switch defaults off' );
telegram_admin_assert( false === $state['episode_enabled'], 'the episode switch defaults off' );
telegram_admin_assert( false === $state['paused'], 'delivery is not paused by default' );
telegram_admin_assert( false === $state['configured'], 'missing credentials are reported as not configured' );
telegram_admin_assert( ! array_key_exists( 'token', $state ) && ! array_key_exists( 'chat_id', $state ), 'page state has no credential values' );

$denied = telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::SAVE_ACTION,
		'telegram_global'                      => '1',
	),
	array(
		'current_user_can' => static function () {
			return false;
		},
	)
);
telegram_admin_assert( is_wp_error( $denied ) && 'telegram_admin_forbidden' === $denied->get_error_code(), 'an unauthorized user is rejected' );
telegram_admin_assert( false === Movies_WP_Telegram_Config::global_enabled(), 'an unauthorized request does not enable publishing' );

$bad_nonce = telegram_admin_post(
	array(
		'_wpnonce'                             => 'nope',
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::SAVE_ACTION,
		'telegram_global'                      => '1',
	)
);
telegram_admin_assert( is_wp_error( $bad_nonce ) && 'telegram_admin_invalid_nonce' === $bad_nonce->get_error_code(), 'a bad nonce is rejected' );
telegram_admin_assert( false === Movies_WP_Telegram_Config::global_enabled(), 'a bad nonce does not enable publishing' );

echo "\nTelegram admin settings\n";
$saved = telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::SAVE_ACTION,
		'telegram_global'                      => '1',
		'telegram_movies'                      => '1',
		'telegram_series'                      => 'yes',
		'telegram_episodes'                    => 'true',
	)
);
telegram_admin_assert( is_array( $saved ) && ! empty( $saved['ok'] ), 'valid settings save' );
telegram_admin_assert( true === Movies_WP_Telegram_Config::global_enabled(), 'an explicit global value enables publishing' );
telegram_admin_assert( true === Movies_WP_Telegram_Config::movie_enabled(), 'an explicit movie value enables movie announcements' );
telegram_admin_assert( false === Movies_WP_Telegram_Config::series_enabled(), 'a non-1 series value stays off' );
telegram_admin_assert( false === Movies_WP_Telegram_Config::episode_enabled(), 'a non-1 episode value stays off' );

$cleared = telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::SAVE_ACTION,
	)
);
telegram_admin_assert( is_array( $cleared ) && false === Movies_WP_Telegram_Config::global_enabled(), 'omitted switches are saved as off' );
telegram_admin_assert( false === Movies_WP_Telegram_Config::movie_enabled(), 'omitted movie switch is saved as off' );

echo "\nTelegram admin pause and test post\n";
putenv( Movies_WP_Telegram_Config::TOKEN_KEY . '=123456789:TESTTOKENVALUE12' );
putenv( Movies_WP_Telegram_Config::CHAT_KEY . '=-1001234567890' );
telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::SAVE_ACTION,
		'telegram_global'                      => '1',
		'telegram_movies'                      => '1',
	)
);
$paused = telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::PAUSE_ACTION,
	)
);
telegram_admin_assert( is_array( $paused ) && true === Movies_WP_Telegram_Config::paused(), 'pause disables delivery' );
telegram_admin_assert( true === Movies_WP_Telegram_Config::global_enabled(), 'pause does not change the publishing switches' );
Movies_WP_Telegram_Queue::reset_state();
$queued = Movies_WP_Telegram_Publisher::enqueue( 'movie', 41, 'job-token-paused', array( 'now' => $now, 'telegram_snapshot' => static function () {
	return array(
		'ok'       => true,
		'snapshot' => array(
			'type'        => 'movie',
			'title'       => 'نمونه',
			'description' => '',
			'genres'      => array(),
			'permalink'   => '',
			'poster_url'  => '',
		),
	);
} ) );
telegram_admin_assert( ! empty( $queued['enqueued'] ), 'a paused site still enqueues a successful import' );
$deliveries = 0;
$paused_run = Movies_WP_Telegram_Worker::run(
	array(
		'now'     => $now,
		'deliver' => static function () use ( &$deliveries ) {
			++$deliveries;
			return array( 'outcome' => 'sent', 'message_id' => '1', 'error' => '' );
		},
	)
);
telegram_admin_assert( 0 === $paused_run['processed'] && 0 === $deliveries, 'a paused worker does not send' );
telegram_admin_assert( 1 === Movies_WP_Telegram_Queue::counts()['pending'], 'the imported announcement stays pending while paused' );

$unconfirmed = telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::TEST_ACTION,
	),
	telegram_admin_http()
);
telegram_admin_assert( is_wp_error( $unconfirmed ) && 'telegram_test_unconfirmed' === $unconfirmed->get_error_code(), 'a test post requires confirmation' );
telegram_admin_assert( array() === $http_calls, 'an unconfirmed test post makes no HTTP call' );
telegram_admin_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'an unconfirmed test post does not enqueue' );

putenv( Movies_WP_Telegram_Config::TOKEN_KEY );
$missing = telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::TEST_ACTION,
		Movies_WP_Telegram_Admin::CONFIRM_FIELD => '1',
	),
	telegram_admin_http()
);
telegram_admin_assert( is_wp_error( $missing ) && 'telegram_test_unconfigured' === $missing->get_error_code(), 'a test post requires credentials' );
telegram_admin_assert( array() === $http_calls, 'missing credentials make no HTTP call' );
putenv( Movies_WP_Telegram_Config::TOKEN_KEY . '=123456789:TESTTOKENVALUE12' );

$tested = telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::TEST_ACTION,
		Movies_WP_Telegram_Admin::CONFIRM_FIELD => '1',
	),
	telegram_admin_http()
);
telegram_admin_assert( is_array( $tested ) && ! empty( $tested['ok'] ), 'a confirmed test post is sent' );
telegram_admin_assert( 1 === count( $http_calls ), 'a confirmed test post makes one HTTP call' );
telegram_admin_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'a test post does not add a queue row' );
$payload = json_decode( $http_calls[0]['body'], true );
$text = is_array( $payload ) ? (string) ( $payload['text'] ?? '' ) : '';
telegram_admin_assert( str_contains( $text, 'آزمایش اتصال تلگرام' ), 'the test message identifies itself' );
telegram_admin_assert( ! str_contains( $http_calls[0]['body'], 'TESTTOKENVALUE12' ), 'the test request body does not contain the bot token' );

$markup = Movies_WP_Telegram_Admin::render_markup( Movies_WP_Telegram_Admin::page_state() );
telegram_admin_assert( str_contains( $markup, 'Configured' ), 'the page shows that credentials are configured' );
telegram_admin_assert( ! str_contains( $markup, 'TESTTOKENVALUE12' ), 'the page does not show the bot token' );
telegram_admin_assert( ! str_contains( $markup, '-1001234567890' ), 'the page does not show the destination' );
telegram_admin_assert( ! str_contains( $markup, 'http_post' ), 'the page does not send a test message while rendering' );
telegram_admin_assert( str_contains( $markup, 'Pause delivery' ) || str_contains( $markup, 'Resume delivery' ), 'the page shows the pause control' );

echo "\nTelegram admin retry and escaping\n";
Movies_WP_Telegram_Queue::reset_state();
$failed = Movies_WP_Telegram_Queue::insert( 'movie', 42, 'job-token-failed', array( 'now' => $now ) );
$failed_claim = Movies_WP_Telegram_Queue::claim( array( 'now' => $now ) );
Movies_WP_Telegram_Queue::record_permanent(
	(int) $failed['id'],
	(string) $failed_claim['claim_token'],
	'<script>alert(1)</script> bot123456789:TESTTOKENVALUE12 https://cdn.example.com/secret Movie/Korea/secret.mp4',
	array( 'now' => $now )
);
$ambiguous = Movies_WP_Telegram_Queue::insert( 'episode', 80, 'job-token-ambiguous', array( 'now' => $now ) );
$ambiguous_claim = Movies_WP_Telegram_Queue::claim( array( 'now' => $now ) );
Movies_WP_Telegram_Queue::record_ambiguous(
	(int) $ambiguous['id'],
	(string) $ambiguous_claim['claim_token'],
	'timed out',
	array( 'now' => $now )
);
$rendered = Movies_WP_Telegram_Admin::render_markup( Movies_WP_Telegram_Admin::page_state() );
telegram_admin_assert( ! str_contains( $rendered, '<script>' ), 'failure text is escaped' );
telegram_admin_assert( str_contains( $rendered, '&lt;script&gt;' ), 'escaped failure text remains visible' );
telegram_admin_assert( ! str_contains( $rendered, 'TESTTOKENVALUE12' ), 'failure notices do not contain the bot token' );
telegram_admin_assert( ! str_contains( $rendered, 'https://cdn.example.com/secret' ), 'failure notices do not contain request URLs' );
telegram_admin_assert( ! str_contains( $rendered, 'Movie/Korea/secret.mp4' ), 'failure notices do not contain internal paths' );
telegram_admin_assert( 1 === substr_count( $rendered, '>Retry<' ), 'only the failed job offers a retry' );

$ambiguous_retry = telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::RETRY_ACTION,
		'queue_id'                             => (string) $ambiguous['id'],
	)
);
telegram_admin_assert( is_wp_error( $ambiguous_retry ), 'an ambiguous job cannot be retried' );
telegram_admin_assert( 1 === Movies_WP_Telegram_Queue::counts()['ambiguous'], 'an ambiguous job stays ambiguous' );

$resumed = telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::RESUME_ACTION,
	)
);
telegram_admin_assert( is_array( $resumed ) && false === Movies_WP_Telegram_Config::paused(), 'resume allows delivery again' );
$schedules = 0;
$retried = telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::RETRY_ACTION,
		'queue_id'                             => (string) $failed['id'],
	),
	array(
		'schedule_action' => static function () use ( &$schedules ) {
			++$schedules;
			return 1;
		},
		'next_scheduled'  => static function () {
			return false;
		},
	)
);
telegram_admin_assert( is_array( $retried ) && ! empty( $retried['ok'] ), 'a failed job can be retried' );
telegram_admin_assert( 1 === Movies_WP_Telegram_Queue::counts()['pending'], 'the failed job returns to pending' );
telegram_admin_assert( 1 === Movies_WP_Telegram_Queue::counts()['ambiguous'], 'retrying a failed job does not clear an ambiguous job' );
telegram_admin_assert( 1 === $schedules, 'a manual retry schedules the worker' );
$zero = telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::RETRY_ACTION,
		'queue_id'                             => '0',
	)
);
telegram_admin_assert( is_wp_error( $zero ), 'a missing queue id is rejected' );

telegram_admin_post(
	array(
		Movies_WP_Telegram_Admin::ACTION_FIELD => Movies_WP_Telegram_Admin::SAVE_ACTION,
	)
);
telegram_admin_assert( false === Movies_WP_Telegram_Config::global_enabled(), 'the test leaves publishing disabled' );
telegram_admin_assert( false === Movies_WP_Telegram_Config::movie_enabled(), 'the test leaves the movie switch disabled' );

foreach ( $saved_env as $key => $value ) {
	if ( is_string( $value ) && '' !== $value ) {
		putenv( $key . '=' . $value );
	} else {
		putenv( $key );
	}
}

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Telegram admin tests passed.\n";
exit( $failures > 0 ? 1 : 0 );

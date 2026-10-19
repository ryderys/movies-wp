<?php
/**
 * CLI tests for Telegram announcement formatting and the Bot API client.
 *
 * php -n wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-telegram-message-test.php
 *
 * HTTP is stubbed. No Telegram request is sent.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-telegram-message-test/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;

		public function __construct( $code = '', $message = '', $data = '' ) {
			unset( $data );
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

require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-formatter.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-client.php';

$failures = 0;
function telegram_message_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

/**
 * @param list<array{code:int,body:string}|WP_Error> $script
 * @return array{calls:list<array{url:string,body:array<string,mixed>}>,post:callable}
 */
function telegram_message_script( array $script ): array {
	$state = array(
		'calls'  => array(),
		'script' => $script,
	);
	$post = static function ( $url, $args ) use ( &$state ) {
		$decoded = json_decode( (string) ( $args['body'] ?? '' ), true );
		$state['calls'][] = array(
			'url'  => (string) $url,
			'body' => is_array( $decoded ) ? $decoded : array(),
		);
		$next = array_shift( $state['script'] );
		if ( $next instanceof WP_Error ) {
			return $next;
		}
		return array(
			'response' => array( 'code' => (int) ( $next['code'] ?? 0 ) ),
			'body'     => (string) ( $next['body'] ?? '' ),
		);
	};
	return array( 'state' => &$state, 'post' => $post );
}

function telegram_message_ok( int $message_id ): array {
	return array(
		'code' => 200,
		'body' => (string) json_encode( array( 'ok' => true, 'result' => array( 'message_id' => $message_id ) ) ),
	);
}

echo "Telegram formatter\n";
$movie = Movies_WP_Telegram_Formatter::build(
	array(
		'type'        => 'movie',
		'title'       => 'جدایی & <نادر>',
		'year'        => 2011,
		'genres'      => array( 'درام', 'خانوادگی' ),
		'description' => 'خلاصه‌ای کوتاه',
		'permalink'   => 'https://example.com/movie/a-separation',
		'poster_url'  => 'https://cdn.example.com/posters/separation.jpg',
	)
);
telegram_message_assert( true === $movie['ok'], 'movie announcement builds' );
telegram_message_assert( 'HTML' === $movie['parse_mode'], 'parse mode is HTML' );
telegram_message_assert( str_contains( (string) $movie['text'], '<b>جدایی &amp; &lt;نادر&gt;</b>' ), 'title is escaped' );
telegram_message_assert( str_contains( (string) $movie['text'], '2011 · درام، خانوادگی' ), 'year and genres are included' );
telegram_message_assert( str_contains( (string) $movie['text'], 'خلاصه‌ای کوتاه' ), 'Persian description is included' );
telegram_message_assert( 'تماشا و دانلود' === $movie['button_label'], 'button label is Persian' );
telegram_message_assert( 'https://example.com/movie/a-separation' === $movie['button_url'], 'button uses the public permalink' );
telegram_message_assert( 'https://cdn.example.com/posters/separation.jpg' === $movie['photo_url'], 'public poster is kept' );

$sparse = Movies_WP_Telegram_Formatter::build(
	array(
		'type'        => 'series',
		'title'       => 'سریال نمونه',
		'year'        => null,
		'genres'      => array(),
		'description' => '',
		'permalink'   => 'https://example.com/tvshow/sample',
		'poster_url'  => '',
	)
);
telegram_message_assert( ! str_contains( (string) $sparse['text'], ' · ' ), 'missing year and genres omit the meta line' );
telegram_message_assert( ! str_contains( strtolower( (string) $sparse['text'] ), 'overview' ), 'missing Persian description is not replaced with English' );
telegram_message_assert( null === $sparse['photo_url'], 'missing poster uses the text fallback' );

$episode = Movies_WP_Telegram_Formatter::build(
	array(
		'type'         => 'episode',
		'series_title' => 'سریال نمونه',
		'title'        => 'شروع',
		'season'       => '1',
		'episode'      => 2,
		'description'  => 'قسمت اول داستان',
		'permalink'    => 'https://example.com/episode/sample-s01e02',
		'poster_url'   => 'https://cdn.example.com/stills/e2.png',
	)
);
telegram_message_assert( str_contains( (string) $episode['text'], '<b>سریال نمونه</b>' ), 'episode uses the series title' );
telegram_message_assert( str_contains( (string) $episode['text'], 'فصل 1، قسمت 2' ), 'episode includes season and episode numbers' );
telegram_message_assert( str_contains( (string) $episode['text'], 'شروع' ), 'episode includes its title' );
telegram_message_assert( 'https://example.com/episode/sample-s01e02' === $episode['button_url'], 'episode button uses the episode permalink' );

$partial_episode = Movies_WP_Telegram_Formatter::build(
	array(
		'type'         => 'episode',
		'series_title' => 'سریال نمونه',
		'title'        => '',
		'season'       => null,
		'episode'      => null,
		'description'  => '',
		'permalink'    => 'https://example.com/tvshow/sample',
	)
);
telegram_message_assert( ! str_contains( (string) $partial_episode['text'], 'فصل' ), 'missing episode numbers omit the slot line' );
telegram_message_assert( '<b>سریال نمونه</b>' === $partial_episode['text'], 'episode with only a series title stays clean' );

$unsafe = Movies_WP_Telegram_Formatter::build(
	array(
		'type'       => 'movie',
		'title'      => 'فیلم',
		'permalink'  => 'https://files.example.com/Movie/Korea/2018/Vapor/film.mp4',
		'poster_url' => 'https://10.1.1.5/poster.jpg?X-Amz-Signature=abc',
	)
);
telegram_message_assert( null === $unsafe['photo_url'], 'private or signed poster URLs are dropped' );
telegram_message_assert( null === $unsafe['button_url'], 'media paths are not used as the public link' );
telegram_message_assert(
	null === Movies_WP_Telegram_Formatter::public_page_url( 'http://example.com/movie/a' ),
	'non-https permalinks are rejected'
);
telegram_message_assert(
	null === Movies_WP_Telegram_Formatter::public_image_url( 'https://cdn.example.com/poster.jpg?token=secret' ),
	'signed image URLs are rejected'
);

$long = Movies_WP_Telegram_Formatter::build(
	array(
		'type'        => 'movie',
		'title'       => 'فیلم بلند',
		'description' => str_repeat( 'ت', 4000 ),
		'permalink'   => 'https://example.com/movie/long',
		'poster_url'  => 'https://cdn.example.com/posters/long.jpg',
	)
);
telegram_message_assert(
	preg_match_all( '/./u', (string) $long['text'], $chars ) <= Movies_WP_Telegram_Formatter::CAPTION_MAX,
	'photo captions stay within the Telegram caption limit'
);

echo "Telegram client\n";
$token = '123456789:TESTTOKENVALUE12';
$chat  = '-1001234567890';
$base  = array(
	'type'       => 'movie',
	'title'      => 'جدایی',
	'permalink'  => 'https://example.com/movie/a-separation',
	'poster_url' => 'https://cdn.example.com/posters/separation.jpg',
);

$sent = telegram_message_script( array( telegram_message_ok( 77 ) ) );
$result = Movies_WP_Telegram_Client::deliver( $base, array( 'token' => $token, 'chat_id' => $chat, 'http_post' => $sent['post'] ) );
telegram_message_assert( 'sent' === $result['outcome'] && '77' === $result['message_id'], 'successful send stores the Telegram message ID' );
telegram_message_assert( str_ends_with( $sent['state']['calls'][0]['url'], '/sendPhoto' ), 'a poster uses sendPhoto' );
telegram_message_assert( 'HTML' === $sent['state']['calls'][0]['body']['parse_mode'], 'request uses HTML parse mode' );
telegram_message_assert( 'https://cdn.example.com/posters/separation.jpg' === $sent['state']['calls'][0]['body']['photo'], 'sendPhoto receives the poster URL' );
telegram_message_assert( ! str_contains( (string) json_encode( $result ), $token ), 'the result does not contain the bot token' );

$text_only = $base;
$text_only['poster_url'] = '';
$text = telegram_message_script( array( telegram_message_ok( 78 ) ) );
$text_result = Movies_WP_Telegram_Client::deliver( $text_only, array( 'token' => $token, 'chat_id' => $chat, 'http_post' => $text['post'] ) );
telegram_message_assert( 'sent' === $text_result['outcome'] && str_ends_with( $text['state']['calls'][0]['url'], '/sendMessage' ), 'missing poster sends a text message' );

$server = telegram_message_script(
	array(
		array(
			'code' => 500,
			'body' => '{"ok":false,"description":"internal"}',
		),
	)
);
$server_result = Movies_WP_Telegram_Client::deliver( $text_only, array( 'token' => $token, 'chat_id' => $chat, 'http_post' => $server['post'] ) );
telegram_message_assert( 'retryable' === $server_result['outcome'], 'HTTP 500 is retryable' );

$denied = telegram_message_script(
	array(
		array(
			'code' => 401,
			'body' => '{"ok":false,"description":"Unauthorized bot' . $token . '"}',
		),
	)
);
$denied_result = Movies_WP_Telegram_Client::deliver( $text_only, array( 'token' => $token, 'chat_id' => $chat, 'http_post' => $denied['post'] ) );
telegram_message_assert( 'permanent' === $denied_result['outcome'], 'HTTP 401 is permanent' );
telegram_message_assert( ! str_contains( $denied_result['error'], $token ), 'API errors are sanitized' );

$timeout = telegram_message_script( array( new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) ) );
$timeout_result = Movies_WP_Telegram_Client::deliver( $base, array( 'token' => $token, 'chat_id' => $chat, 'http_post' => $timeout['post'] ) );
telegram_message_assert( 'ambiguous' === $timeout_result['outcome'], 'a timeout is ambiguous' );
telegram_message_assert( 1 === count( $timeout['state']['calls'] ), 'a photo timeout does not send a second text message' );

$fallback = telegram_message_script(
	array(
		array(
			'code' => 400,
			'body' => '{"ok":false,"description":"Bad Request: failed to get HTTP URL content"}',
		),
		telegram_message_ok( 79 ),
	)
);
$fallback_result = Movies_WP_Telegram_Client::deliver( $base, array( 'token' => $token, 'chat_id' => $chat, 'http_post' => $fallback['post'] ) );
telegram_message_assert( 'sent' === $fallback_result['outcome'] && '79' === $fallback_result['message_id'], 'an unreachable poster falls back to text' );
telegram_message_assert(
	2 === count( $fallback['state']['calls'] ) && str_ends_with( $fallback['state']['calls'][1]['url'], '/sendMessage' ),
	'the fallback is a single text send'
);

$refused = telegram_message_script( array( new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' ) ) );
$refused_result = Movies_WP_Telegram_Client::deliver( $text_only, array( 'token' => $token, 'chat_id' => $chat, 'http_post' => $refused['post'] ) );
telegram_message_assert( 'retryable' === $refused_result['outcome'], 'a connection failure before a response is retryable' );

$missing = Movies_WP_Telegram_Client::deliver( $text_only, array( 'http_post' => static function () {
	return telegram_message_ok( 1 );
} ) );
telegram_message_assert( 'permanent' === $missing['outcome'] && 'unconfigured' === $missing['code'], 'missing credentials do not send' );

$bootstrap = (string) file_get_contents( dirname( __DIR__, 2 ) . '/movies-wp-media-automation.php' );
telegram_message_assert( str_contains( $bootstrap, 'class-movies-wp-telegram-publisher.php' ), 'plugin bootstrap loads the Telegram publisher' );
telegram_message_assert( ! str_contains( $bootstrap, 'Movies_WP_Telegram_Client::deliver' ), 'plugin bootstrap does not send Telegram messages' );
telegram_message_assert( ! str_contains( $bootstrap, 'movies_wp_telegram_enabled' ), 'plugin bootstrap does not enable publishing' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Telegram message tests passed.\n";
exit( $failures ? 1 : 0 );

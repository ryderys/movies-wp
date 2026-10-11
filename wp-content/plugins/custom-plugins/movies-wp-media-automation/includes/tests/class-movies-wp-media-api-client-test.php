<?php
/**
 * CLI tests for Movies_WP_Media_Api_Client transport retry.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-media-api-client-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-media-api-client-test/' );
}
define( 'MEDIA_SCAN_API_URL', 'https://media.example.test' );
define( 'MEDIA_SCAN_API_KEY', 'test-key' );
define( 'MEDIA_SCAN_HMAC_SECRET', 'test-secret' );

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
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' );
	}
}

$http_queue = array();
$http_calls = 0;
function wp_remote_get( $url, $args = array() ) {
	global $http_queue, $http_calls;
	unset( $url, $args );
	++$http_calls;
	return array_shift( $http_queue );
}
function wp_remote_retrieve_response_code( $response ) {
	return $response['code'];
}
function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}

require_once dirname( __DIR__ ) . '/class-movies-wp-media-api-client.php';

$failures = 0;
function api_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

$ok_response   = array(
	'code' => 200,
	'body' => json_encode( array( 'ok' => true, 'kind' => 'movie', 'files' => array() ) ),
);
$refused       = new WP_Error( 'http_request_failed', "cURL error 7: Failed to connect to media.example.test port 443 after 1302 ms: Couldn't connect to server" );
$timed_out     = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 20001 milliseconds' );
$dir           = 'Movie/Korea/2022/Christmas.Carol';
$scan          = static function ( array $queue ) use ( $dir ) {
	global $http_queue, $http_calls;
	$http_queue = $queue;
	$http_calls = 0;
	return Movies_WP_Media_Api_Client::scan_movie_directory( $dir );
};

echo "Media API client transport retry\n";

$result = $scan( array( $ok_response ) );
api_assert( is_array( $result ) && 1 === $http_calls, 'success on first attempt makes one request' );

$result = $scan( array( $refused, $ok_response ) );
api_assert( is_array( $result ) && 2 === $http_calls, 'refused connection is retried once and succeeds' );

$result = $scan( array( $refused, $refused ) );
api_assert( is_wp_error( $result ) && 'media_api_request_error' === $result->get_error_code(), 'two refused connections return the request error' );
api_assert( 2 === $http_calls, 'retry happens only once' );

$result = $scan( array( $timed_out, $ok_response ) );
api_assert( is_wp_error( $result ) && 'media_api_timeout' === $result->get_error_code(), 'timeout returns the timeout error' );
api_assert( 1 === $http_calls, 'timeout is not retried' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll media API client tests passed.\n";
exit( $failures ? 1 : 0 );

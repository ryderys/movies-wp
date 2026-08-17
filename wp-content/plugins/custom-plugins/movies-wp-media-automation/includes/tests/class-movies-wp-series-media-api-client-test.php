<?php
/**
 * CLI tests for Movies_WP_Series_Media_Api_Client.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-media-api-client-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-media-api-test/' );
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

require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-api-client.php';

$failures = 0;

function sm_api_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function sm_api_same( $expected, $actual, string $label ): void {
	sm_api_assert( $expected === $actual, $label . ' got=' . var_export( $actual, true ) );
}

echo "Series media API client directory normalization\n";

$valid = Movies_WP_Series_Media_Api_Client::normalize_directory( 'series/Chin/2025/Spring.Burning' );
sm_api_same( 'series/Chin/2025/Spring.Burning', $valid, 'canonical lowercase series path normalizes' );

$backslash = Movies_WP_Series_Media_Api_Client::normalize_directory( 'series\\korea\\2024\\Show' );
sm_api_same( 'series/korea/2024/Show', $backslash, 'backslashes normalize to forward slashes' );

foreach ( array( '', '/series/korea/2024/Show', 'Movie/korea/2024/Show', 'Series/Chin/2025/Spring.Burning', 'series/korea/2024', 'series/../korea/2024/Show', 'series/korea/2024/..' ) as $bad ) {
	$result = Movies_WP_Series_Media_Api_Client::normalize_directory( $bad );
	sm_api_assert( is_wp_error( $result ), 'invalid path rejected: ' . $bad );
	sm_api_same( 'series_media_api_invalid_dir', $result->get_error_code(), 'invalid path error code stable for ' . $bad );
}

echo "Series media API client config\n";

sm_api_same( '/scan/series', Movies_WP_Series_Media_Api_Client::REQUEST_PATH, 'Series scan HTTP route remains unchanged' );

if ( ! class_exists( 'Movies_WP_Media_Api_Client' ) ) {
	class Movies_WP_Media_Api_Client {
		public static function config() {
			return array(
				'base_url'    => 'https://media.example.test',
				'api_key'     => 'test-key',
				'hmac_secret' => 'test-secret',
			);
		}
	}
}

$config = Movies_WP_Series_Media_Api_Client::config();
sm_api_assert( is_array( $config ), 'config resolves through movie client' );
sm_api_same( 'https://media.example.test', $config['base_url'], 'config base URL is reused' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series media API client tests passed.\n";
exit( $failures ? 1 : 0 );

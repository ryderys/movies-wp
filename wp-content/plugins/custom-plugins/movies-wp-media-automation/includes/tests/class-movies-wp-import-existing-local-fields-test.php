<?php
/**
 * CLI tests for Movies_WP_Import_Existing_Local_Fields.
 *
 * Run: php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-import-existing-local-fields-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-existing-local-fields-test/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '', $data = '' ) {
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
		return $text;
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-import-existing-local-fields.php';

$failures = 0;

function assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
}

function assert_eq( $expected, $actual, string $label ): void {
	assert_true( $expected === $actual, $label . ' got=' . var_export( $actual, true ) );
}

class Existing_Local_Fields_Stub {
	private $title;
	private $content;

	public function __construct( $title, $content ) {
		$this->title   = (string) $title;
		$this->content = (string) $content;
	}

	public function get_post_title() {
		return $this->title;
	}

	public function get_post_content() {
		return $this->content;
	}
}

echo "[existing-local-fields]\n";

{
	$r = Movies_WP_Import_Existing_Local_Fields::fill_movie(
		111,
		'Operator Title',
		'Operator Summary',
		array(
			'find_by_tmdb' => static function () {
				return array( 'ids' => array( 9 ) );
			},
		)
	);
	assert_true( ! is_wp_error( $r ), '1. explicit title accepted' );
	assert_eq( 'Operator Title', $r['title'] ?? null, '1. title kept' );
	assert_eq( 'Operator Summary', $r['summary'] ?? null, '1. summary kept' );
}

{
	$r = Movies_WP_Import_Existing_Local_Fields::fill_movie(
		111,
		'',
		'',
		array(
			'find_by_tmdb' => static function () {
				return array( 'ids' => array() );
			},
		)
	);
	assert_true( is_wp_error( $r ), '2. blank title on create rejected' );
	assert_eq( 'import_existing_local_fields_title_required', $r->get_error_code(), '2. code' );
}

{
	$r = Movies_WP_Import_Existing_Local_Fields::fill_movie(
		111,
		'',
		'',
		array(
			'find_by_tmdb' => static function () {
				return array( 'ids' => array( 42 ) );
			},
			'get_movie'    => static function ( $id ) {
				assert_eq( 42, $id, '3. loads matched movie id' );
				return new Existing_Local_Fields_Stub( 'Stored Movie', 'Stored Movie Summary' );
			},
		)
	);
	assert_true( ! is_wp_error( $r ), '3. blank title reuses existing movie' );
	assert_eq( 'Stored Movie', $r['title'] ?? null, '3. title from Streamit' );
	assert_eq( 'Stored Movie Summary', $r['summary'] ?? null, '3. summary from Streamit' );
}

{
	$r = Movies_WP_Import_Existing_Local_Fields::fill_series(
		222,
		'',
		'Override Summary',
		array(
			'find_series_by_tmdb' => static function () {
				return array( 'ids' => array( 7 ) );
			},
			'get_tvshow'          => static function () {
				return new Existing_Local_Fields_Stub( 'Stored Series', 'Stored Series Summary' );
			},
		)
	);
	assert_true( ! is_wp_error( $r ), '4. blank series title reuses existing' );
	assert_eq( 'Stored Series', $r['title'] ?? null, '4. series title from Streamit' );
	assert_eq( 'Override Summary', $r['summary'] ?? null, '4. explicit summary wins' );
}

{
	$r = Movies_WP_Import_Existing_Local_Fields::fill_movie(
		111,
		'',
		'',
		array(
			'find_by_tmdb' => static function () {
				return array( 'ids' => array( 1, 2 ) );
			},
		)
	);
	assert_true( is_wp_error( $r ), '5. duplicate TMDb rejected' );
	assert_eq( 'import_existing_local_fields_duplicate', $r->get_error_code(), '5. code' );
}

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nAll existing-local-fields assertions passed.\n";

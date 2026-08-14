<?php
/**
 * CLI tests for subtitle render-time signed URL resolution.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/subtitle-signed-url-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-subtitle-signed-url-test/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		private $code;
		/** @var string */
		private $message;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			unset( $data );
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

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {
		unset( $args );
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {
		unset( $args );
		return true;
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) {
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
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
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '/' ) {
		return 'https://asiastarx.ir' . $path;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( array $args, $url ) {
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
	}
}

/** @var list<array{path:string,type:string}> */
$GLOBALS['subtitle_sign_calls'] = array();

if ( ! function_exists( 'movies_wp_media_signed_url' ) ) {
	function movies_wp_media_signed_url( $relative_path, $type = 'v', $ttl_seconds = null ) {
		unset( $ttl_seconds );
		$path = str_replace( '\\', '/', ltrim( (string) $relative_path, '/' ) );
		if ( '' === $path || str_contains( $path, '..' ) ) {
			return new WP_Error( 'media_path_invalid', 'bad path' );
		}
		$GLOBALS['subtitle_sign_calls'][] = array(
			'path' => $path,
			'type' => (string) $type,
		);
		return 'https://media.asiastarx.ir/' . $type . '/TOKEN_' . rawurlencode( $path );
	}
}

require_once dirname( __DIR__ ) . '/subtitles.php';
require_once dirname( __DIR__ ) . '/subtitle-track.php';

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
	assert_true( $expected === $actual, $label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' );
}

echo "subtitle render-time signed URL tests\n\n";

$path = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.WEB-DL.srt';
$GLOBALS['subtitle_sign_calls'] = array();

$signed = streamit_child_resolve_subtitle_url( $path, 'v' );
assert_true( str_starts_with( $signed, 'https://media.asiastarx.ir/v/' ), 'valid relative .srt → signed URL generated' );
assert_true( ! str_contains( $signed, '/data' ), 'no /data path exposed in signed URL' );
assert_eq( 1, count( $GLOBALS['subtitle_sign_calls'] ), 'signed via movies_wp_media_signed_url once' );
assert_eq( $path, $GLOBALS['subtitle_sign_calls'][0]['path'] ?? null, 'signer received relative Movie/... path' );
assert_eq( 'v', $GLOBALS['subtitle_sign_calls'][0]['type'] ?? null, 'player uses type v' );

$dl = streamit_child_resolve_subtitle_url( $path, 'd' );
assert_true( str_starts_with( $dl, 'https://media.asiastarx.ir/d/' ), 'download uses type d' );

$from_data = streamit_child_resolve_subtitle_url( '/data/' . $path, 'v' );
assert_true( str_starts_with( $from_data, 'https://media.asiastarx.ir/v/' ), '/data prefix stripped then signed' );
assert_true( ! str_contains( $from_data, '/data' ), 'stripped /data not exposed' );

assert_eq( '', streamit_child_resolve_subtitle_url( '', 'v' ), 'missing subtitle path handled safely' );
assert_eq( '', streamit_child_resolve_subtitle_url( '   ', 'v' ), 'whitespace-only path handled safely' );

$normalized = streamit_child_normalize_subtitles(
	array(
		array(
			'url'     => $path,
			'srclang' => 'fa',
			'label'   => 'فارسی',
			'format'  => 'SRT',
		),
		array(
			'url'     => $path,
			'srclang' => '',
			'label'   => '',
			'format'  => 'SRT',
		),
	)
);
assert_eq( 2, count( $normalized ), 'normalize keeps both subtitle rows' );
assert_eq( $path, $normalized[0]['url'] ?? null, 'signed URL is not persisted in normalized storage shape' );
assert_eq( 'fa', $normalized[0]['srclang'] ?? null, 'known language preserved' );
assert_eq( '', $normalized[1]['srclang'] ?? null, 'unknown language remains empty' );

// Track markup itself is covered by subtitle-track-test.php; here we only check
// that building it still resolves through the render-time signer and leaves
// stored values alone.
$before_calls = count( $GLOBALS['subtitle_sign_calls'] );
$tracks       = streamit_child_build_subtitle_tracks( $normalized, 26, 'movie' );
assert_true( '' !== $tracks, 'tracks are produced for resolvable rows' );
assert_true( ! str_contains( $tracks, '/data/' ), 'track markup has no /data' );
assert_true( str_contains( $tracks, 'srclang="fa"' ), 'track preserves known srclang' );
assert_true( str_contains( $tracks, 'srclang="und"' ), 'empty language uses HTML und only in markup' );
assert_true( count( $GLOBALS['subtitle_sign_calls'] ) > $before_calls, 'tracks resolve at render time' );

// Re-check storage shape unchanged after render helper (no persistence side effect).
assert_eq( $path, $normalized[0]['url'] ?? null, 'render does not mutate stored relative url' );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}
echo "All subtitle signed-URL assertions passed.\n";
exit( 0 );

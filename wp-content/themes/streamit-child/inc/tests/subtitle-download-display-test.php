<?php
/**
 * CLI tests for subtitle download modal display helpers.
 *
 * Display-only: basename + format. No persistence / signing changes.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/subtitle-download-display-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-subtitle-download-display-test/' );
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

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		unset( $domain );
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'st_get_icon' ) ) {
	function st_get_icon( $name ) {
		return '<i data-icon="' . htmlspecialchars( (string) $name, ENT_QUOTES, 'UTF-8' ) . '"></i>';
	}
}

if ( ! function_exists( 'movies_wp_media_signed_url' ) ) {
	function movies_wp_media_signed_url( $relative_path, $type = 'v', $ttl_seconds = null ) {
		unset( $ttl_seconds );
		$path = str_replace( '\\', '/', ltrim( (string) $relative_path, '/' ) );
		return 'https://media.asiastarx.ir/' . $type . '/TOKEN_' . rawurlencode( $path );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}

require_once dirname( __DIR__ ) . '/sources-download.php';
require_once dirname( __DIR__ ) . '/subtitles.php';

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

echo "subtitle download display tests\n\n";

$unknown_url = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.WEB-DL.srt';
$fa_url      = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.WEB-DL.fa.srt';
$zip_url     = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.Subtitles.zip';

$normalized = streamit_child_normalize_subtitles(
	array(
		array(
			'label'   => '',
			'srclang' => '',
			'url'     => $unknown_url,
			'format'  => '',
			'default' => 0,
		),
		array(
			'label'   => 'فارسی',
			'srclang' => 'fa',
			'url'     => $fa_url,
			'format'  => 'srt',
			'default' => 0,
		),
		array(
			'label'   => '',
			'srclang' => '',
			'url'     => $zip_url,
			'format'  => '',
			'default' => 0,
		),
	)
);

assert_eq( 3, count( $normalized ), 'three subtitle rows normalized' );
assert_eq( 'زیرنویس', $normalized[0]['label'], 'empty language/label falls back to generic زیرنویس' );
assert_eq( '', $normalized[0]['srclang'], 'empty srclang stays empty (not invented)' );
assert_eq( 'فارسی', $normalized[1]['label'], 'known label preserved' );
assert_eq( 'fa', $normalized[1]['srclang'], 'known srclang preserved' );

assert_eq(
	'Decision.to.Leave.2022.WEB-DL.srt',
	streamit_child_subtitle_display_basename( $unknown_url ),
	'basename from relative url'
);
assert_eq(
	'Decision.to.Leave.Subtitles.zip',
	streamit_child_subtitle_display_basename( $zip_url ),
	'basename for zip pack'
);

assert_eq( 'SRT', streamit_child_subtitle_display_format( $normalized[0] ), 'format derived from extension when stored empty' );
assert_eq( 'SRT', streamit_child_subtitle_display_format( $normalized[1] ), 'stored format uppercased' );
assert_eq( 'ZIP', streamit_child_subtitle_display_format( $normalized[2] ), 'zip format from extension' );

assert_eq(
	array( 'Decision.to.Leave.2022.WEB-DL.srt', 'SRT' ),
	streamit_child_subtitle_download_meta_values( $normalized[0] ),
	'unknown-lang secondary: basename · SRT'
);
assert_eq(
	array( 'Decision.to.Leave.2022.WEB-DL.fa.srt', 'SRT' ),
	streamit_child_subtitle_download_meta_values( $normalized[1] ),
	'known-lang secondary: basename · SRT'
);
assert_eq(
	array( 'Decision.to.Leave.Subtitles.zip', 'ZIP' ),
	streamit_child_subtitle_download_meta_values( $normalized[2] ),
	'zip secondary: basename · ZIP'
);

$raw_before = array(
	array(
		'label'   => '',
		'srclang' => '',
		'url'     => $unknown_url,
		'format'  => '',
		'default' => 0,
	),
);
$raw_copy = $raw_before;
streamit_child_normalize_subtitles( $raw_copy );
streamit_child_subtitle_download_meta_values(
	array(
		'url'    => $unknown_url,
		'format' => '',
	)
);
assert_eq( $raw_before, $raw_copy, 'display helpers do not mutate stored subtitle rows' );

ob_start();
streamit_child_render_subtitle_download_section( $normalized );
$html = (string) ob_get_clean();
assert_true( str_contains( $html, 'Decision.to.Leave.2022.WEB-DL.srt · SRT' ), 'rendered unknown subtitle shows basename · SRT' );
assert_true( str_contains( $html, 'Decision.to.Leave.2022.WEB-DL.fa.srt · SRT' ), 'rendered fa subtitle shows basename · SRT' );
assert_true( str_contains( $html, 'Decision.to.Leave.Subtitles.zip · ZIP' ), 'rendered zip subtitle shows basename · ZIP' );
assert_true( str_contains( $html, 'فارسی' ), 'rendered known label' );
assert_true( ! str_contains( $html, 'نوع زیرنویس' ), 'no نوع زیرنویس label' );
assert_true( ! str_contains( $html, '/v/' ), 'no stream tokens in display markup beyond signed download hrefs is fine' );
assert_true( str_contains( $html, '/d/TOKEN_' ), 'download href still uses signed /d/ mint at render time' );
assert_true( ! str_contains( $html, $unknown_url . '/d/' ), 'relative path itself is not altered in storage sense' );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}
echo "All subtitle-download-display assertions passed.\n";
exit( 0 );

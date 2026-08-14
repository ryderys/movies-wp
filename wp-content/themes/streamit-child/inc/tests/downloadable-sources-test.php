<?php
/**
 * CLI tests for streamit_child_get_downloadable_sources() language-optional gate.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/downloadable-sources-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-downloadable-sources-test/' );
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

require_once dirname( __DIR__ ) . '/sources-download.php';

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

echo "streamit_child_get_downloadable_sources tests\n\n";

$path = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.1080p.mkv';

$empty_lang = streamit_child_get_downloadable_sources(
	array(
		array(
			'quality'          => '1080p',
			'language'         => '',
			'download_content' => $path,
			'name'             => '',
		),
	)
);
assert_eq( 1, count( $empty_lang ), 'quality + download + empty language → included' );
assert_eq( '1080p', $empty_lang[0]['quality'] ?? null, 'empty-language row keeps quality' );
assert_eq( '', $empty_lang[0]['language'] ?? null, 'empty language preserved (not invented)' );
assert_eq( $path, $empty_lang[0]['download_content'] ?? null, 'empty-language row keeps download_content' );

$known_lang = streamit_child_get_downloadable_sources(
	array(
		array(
			'quality'          => '720p',
			'language'         => 'Korean',
			'download_content' => $path,
			'name'             => 'AirenTeam',
		),
	)
);
assert_eq( 1, count( $known_lang ), 'quality + download + known language → included' );
assert_eq( 'Korean', $known_lang[0]['language'] ?? null, 'known language preserved' );
assert_eq( 'AirenTeam', $known_lang[0]['encoder'] ?? null, 'encoder/name preserved' );

$missing_quality = streamit_child_get_downloadable_sources(
	array(
		array(
			'quality'          => '',
			'language'         => 'Korean',
			'download_content' => $path,
		),
	)
);
assert_eq( 0, count( $missing_quality ), 'missing quality → excluded' );

$missing_download = streamit_child_get_downloadable_sources(
	array(
		array(
			'quality'          => '1080p',
			'language'         => 'Korean',
			'download_content' => '',
			'link'             => '',
		),
	)
);
assert_eq( 0, count( $missing_download ), 'missing download_content (and link) → excluded' );

$link_fallback = streamit_child_get_downloadable_sources(
	array(
		array(
			'quality'          => '480p',
			'language'         => '',
			'download_content' => '',
			'link'             => $path,
		),
	)
);
assert_eq( 1, count( $link_fallback ), 'empty download_content falls back to link' );
assert_eq( $path, $link_fallback[0]['download_content'] ?? null, 'link used as download_content' );
assert_eq( '', $link_fallback[0]['language'] ?? null, 'link-fallback keeps empty language' );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}
echo "All downloadable-sources assertions passed.\n";
exit( 0 );

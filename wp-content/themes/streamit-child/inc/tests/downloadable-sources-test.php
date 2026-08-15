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

echo "\nstreamit_child_download_source_meta_values tests\n\n";

assert_eq(
	'MKV',
	streamit_child_download_path_extension( $path ),
	'container derived from relative Movie/... path'
);
assert_eq(
	'SRT',
	streamit_child_download_path_extension( 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.WEB-DL.srt' ),
	'extension from subtitle-like relative path'
);
assert_eq(
	'MP4',
	streamit_child_download_path_extension( 'https://cdn.example/files/movie.mp4?token=abc' ),
	'extension from absolute URL path, query ignored'
);
assert_eq( '', streamit_child_download_path_extension( 'Movie/Korea/2022/Decision.to.Leave/noext' ), 'no extension → empty' );

assert_eq(
	array( 'KNPSK', 'MKV', '1.54 GB' ),
	streamit_child_download_source_meta_values(
		array(
			'quality'          => '1080p',
			'name'             => 'KNPSK',
			'download_content' => $path,
			'file_size'        => '1.54 GB',
		)
	),
	'name · container · size when name differs from quality'
);

assert_eq(
	array( 'MKV', '1.54 GB' ),
	streamit_child_download_source_meta_values(
		array(
			'quality'          => '1080p',
			'name'             => '',
			'download_content' => $path,
			'file_size'        => '1.54 GB',
		)
	),
	'empty name omits encoder chip'
);

assert_eq(
	array( 'MKV', '1.54 GB' ),
	streamit_child_download_source_meta_values(
		array(
			'quality'          => '1080p',
			'name'             => '1080p',
			'download_content' => $path,
			'file_size'        => '1.54 GB',
		)
	),
	'name identical to quality is not repeated'
);

$movie26 = streamit_child_get_downloadable_sources(
	array(
		array(
			'quality'          => '1080p',
			'name'             => '',
			'language'         => '',
			'link'             => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.1080p.mkv',
			'download_content' => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.1080p.mkv',
			'file_size'        => '1.54 GB',
		),
		array(
			'quality'          => '480p',
			'name'             => '',
			'language'         => '',
			'link'             => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.480p-a.mkv',
			'download_content' => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.480p-a.mkv',
			'file_size'        => '405.57 MB',
		),
		array(
			'quality'          => '480p',
			'name'             => '',
			'language'         => '',
			'link'             => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.480p-b.mkv',
			'download_content' => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.480p-b.mkv',
			'file_size'        => '401.24 MB',
		),
		array(
			'quality'          => '720p',
			'name'             => '',
			'language'         => '',
			'link'             => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.720p-a.mkv',
			'download_content' => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.720p-a.mkv',
			'file_size'        => '611.13 MB',
		),
		array(
			'quality'          => '720p',
			'name'             => '',
			'language'         => '',
			'link'             => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.720p-b.mkv',
			'download_content' => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.720p-b.mkv',
			'file_size'        => '606.75 MB',
		),
	)
);
assert_eq( 5, count( $movie26 ), 'Movie 26 keeps five separate download rows' );
assert_eq(
	array(
		array( 'MKV', '1.54 GB' ),
		array( 'MKV', '405.57 MB' ),
		array( 'MKV', '401.24 MB' ),
		array( 'MKV', '611.13 MB' ),
		array( 'MKV', '606.75 MB' ),
	),
	array_map( 'streamit_child_download_source_meta_values', $movie26 ),
	'Movie 26 secondary lines: container · size, no invented encoder'
);
assert_eq(
	array( '1080p', '480p', '480p', '720p', '720p' ),
	array_column( $movie26, 'quality' ),
	'duplicate qualities remain distinct rows'
);
assert_eq(
	array( 0, 1, 2, 3, 4 ),
	array_column( $movie26, 'source_index' ),
	'source_index preserved for gateway downloads'
);

ob_start();
streamit_child_render_download_source_meta( $movie26[0] );
$rendered_meta = (string) ob_get_clean();
assert_true( str_contains( $rendered_meta, 'MKV · 1.54 GB' ), 'rendered meta uses compact · separators' );
assert_true( ! str_contains( $rendered_meta, 'حجم' ), 'rendered meta has no حجم label' );
assert_true( ! str_contains( $rendered_meta, 'Encoder' ), 'rendered meta has no Encoder label' );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}
echo "All downloadable-sources assertions passed.\n";
exit( 0 );

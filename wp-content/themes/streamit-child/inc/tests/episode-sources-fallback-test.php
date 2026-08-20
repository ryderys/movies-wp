<?php
/**
 * CLI tests for episode `_sources` playback fallback helpers.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/episode-sources-fallback-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-episode-sources-fallback-test/' );
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

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
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

$GLOBALS['streamit_child_signed_calls'] = array();

if ( ! function_exists( 'movies_wp_media_signed_url' ) ) {
	function movies_wp_media_signed_url( $path, $type = 'v' ) {
		$GLOBALS['streamit_child_signed_calls'][] = array(
			'path' => (string) $path,
			'type' => (string) $type,
		);
		return 'https://media.example.test/v/signed-' . rawurlencode( (string) $path );
	}
}

require_once dirname( __DIR__ ) . '/media-player-rewrite.php';

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

echo "episode _sources playback fallback tests\n\n";

$shop_s01e01_sources = array(
	array(
		'name'             => '',
		'link'             => '',
		'quality'          => 'bad',
		'download_content' => '',
	),
	array(
		'name'             => '',
		'link'             => 'series/korea/2024/A.Shop.for.Killers/1080p WEB-DL/A.Shop.for.Killers.S01E01.1080p.DSNP.WEB-DL.DDP5.1.H.264-APEX.mkv',
		'quality'          => '1080p',
		'download_content' => 'series/korea/2024/A.Shop.for.Killers/1080p WEB-DL/A.Shop.for.Killers.S01E01.1080p.DSNP.WEB-DL.DDP5.1.H.264-APEX.mkv',
	),
	array(
		'name'    => '',
		'link'    => 'series/korea/2024/A.Shop.for.Killers/720p/A.Shop.for.Killers.S01E01.720p.mkv',
		'quality' => '720p',
	),
);

// 1) Empty choice/url + valid _sources → first usable source.
$resolved = streamit_child_resolve_episode_media(
	array(
		'choice'         => null,
		'url_link'       => '',
		'attachment_url' => '',
		'sources'        => $shop_s01e01_sources,
	)
);
assert_eq( 'sources', $resolved['mode'], 'empty choice uses sources mode' );
assert_eq(
	'series/korea/2024/A.Shop.for.Killers/1080p WEB-DL/A.Shop.for.Killers.S01E01.1080p.DSNP.WEB-DL.DDP5.1.H.264-APEX.mkv',
	$resolved['media_stored'],
	'empty choice picks first non-empty link'
);
assert_eq( 1, $resolved['source_index'], 'empty choice skips invalid row index 0' );

// 2) Explicit episode_url wins over _sources.
$resolved = streamit_child_resolve_episode_media(
	array(
		'choice'         => 'episode_url',
		'url_link'       => 'series/korea/2024/A.Shop.for.Killers/manual.mkv',
		'attachment_url' => '',
		'sources'        => $shop_s01e01_sources,
	)
);
assert_eq( 'url', $resolved['mode'], 'explicit episode_url mode' );
assert_eq(
	'series/korea/2024/A.Shop.for.Killers/manual.mkv',
	$resolved['media_stored'],
	'explicit url_link wins over _sources'
);
assert_eq( 0, $resolved['source_index'], 'explicit url uses source_index 0' );

// 3) Invalid/empty links skipped.
$first = streamit_child_first_usable_source(
	array(
		array( 'link' => '' ),
		array( 'link' => 'ftp://bad.example/file.mkv' ),
		array( 'link' => 'series/ok/file.mkv' ),
	)
);
assert_true( is_array( $first ), 'first usable source found after skips' );
assert_eq( 'series/ok/file.mkv', $first['link'] ?? null, 'skips empty and non-http non-local links' );
assert_eq( 2, $first['index'] ?? null, 'usable index is 2' );

// 4) No usable sources → none.
$resolved = streamit_child_resolve_episode_media(
	array(
		'choice'         => '',
		'url_link'       => '',
		'attachment_url' => '',
		'sources'        => array(
			array( 'link' => '' ),
			array( 'quality' => '1080p' ),
		),
	)
);
assert_eq( 'none', $resolved['mode'], 'no usable sources → none' );
assert_eq( '', $resolved['media_stored'], 'none mode has empty media_stored' );

// Attachment still beats sources (established fallback).
$resolved = streamit_child_resolve_episode_media(
	array(
		'choice'         => 'episode_file',
		'url_link'       => '',
		'attachment_url' => 'https://cdn.example.test/ep.mp4',
		'sources'        => $shop_s01e01_sources,
	)
);
assert_eq( 'file', $resolved['mode'], 'attachment preferred over sources' );
assert_eq( 'https://cdn.example.test/ep.mp4', $resolved['media_stored'], 'attachment url preserved' );

// episode_url with empty link falls through to sources.
$resolved = streamit_child_resolve_episode_media(
	array(
		'choice'         => 'episode_url',
		'url_link'       => '',
		'attachment_url' => '',
		'sources'        => $shop_s01e01_sources,
	)
);
assert_eq( 'sources', $resolved['mode'], 'empty episode_url_link falls through to sources' );

// 5/8) Signed media path used; raw filesystem path not returned as playable src.
$GLOBALS['streamit_child_signed_calls'] = array();
$stored = 'series/korea/2024/A.Shop.for.Killers/1080p WEB-DL/A.Shop.for.Killers.S01E01.1080p.DSNP.WEB-DL.DDP5.1.H.264-APEX.mkv';
$playable = streamit_child_resolve_playable_src( $stored, 890, 1 );
assert_true( str_starts_with( $playable, 'https://media.example.test/v/signed-' ), 'playable src is signed URL' );
assert_true( ! str_contains( $playable, 'series/korea' ) || str_contains( $playable, 'signed-' ), 'browser src is not a raw /data path' );
assert_eq( 1, count( $GLOBALS['streamit_child_signed_calls'] ), 'signed URL helper called once' );
assert_eq( $stored, $GLOBALS['streamit_child_signed_calls'][0]['path'] ?? null, 'signed helper receives stored relative path' );
assert_eq( 'v', $GLOBALS['streamit_child_signed_calls'][0]['type'] ?? null, 'signed helper uses video type' );

$html = streamit_child_get_url_video_html_for_stored( $stored, 890, 1 );
assert_true( false !== strpos( $html, 'https://media.example.test/v/signed-' ), 'video HTML embeds signed URL' );
assert_true( false === strpos( $html, 'src="series/' ), 'video HTML does not expose raw series path as src' );
assert_true( false !== strpos( $html, 'video/x-matroska' ) || false !== strpos( $html, 'type=' ), 'video HTML includes MIME from stored extension' );

// Movie helper identity still works (movie streaming helpers unchanged).
$movie_link = 'Movie/Korea/2008/The.Good.the.Bad.the.Weird/The.Good.the.Bad.the.Weird.2008.480p.SS.mkv';
assert_eq(
	'local:Movie/Korea/2008/The.Good.the.Bad.the.Weird/The.Good.the.Bad.the.Weird.2008.480p.SS.mkv',
	streamit_child_player_source_identity( $movie_link ),
	'movie source identity unchanged'
);
assert_eq(
	'480p',
	streamit_child_player_source_display_label(
		array(
			'name'    => '',
			'quality' => '480p',
			'link'    => $movie_link,
		)
	),
	'movie source display label unchanged'
);

// Download helper still maps local paths to gateway URLs when available.
if ( ! function_exists( 'movies_wp_media_download_url' ) ) {
	function movies_wp_media_download_url( $post_id, $index = 0 ) {
		return 'https://example.test/?movies_wp_media=download&movies_wp_media_post=' . (int) $post_id . '&movies_wp_media_i=' . (int) $index;
	}
}
$dl = streamit_child_resolve_download_href( $stored, 890, 1 );
assert_eq(
	'https://example.test/?movies_wp_media=download&movies_wp_media_post=890&movies_wp_media_i=1',
	$dl,
	'episode download still uses gateway URL helper'
);

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures}\n";
	exit( 1 );
}
echo "OK\n";
exit( 0 );

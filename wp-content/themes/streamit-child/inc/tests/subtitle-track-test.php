<?php
/**
 * CLI tests for the streaming subtitle track path.
 *
 * Covers the same-origin <track src>, SRT → WebVTT conversion, and survival of
 * tracks across manual source switching (data-sources entries).
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/subtitle-track-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-subtitle-track-test/' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		private $code;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code = (string) $code;
			unset( $message, $data );
		}

		public function get_error_code() {
			return $this->code;
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
		return str_replace( '&', '&#038;', (string) $url );
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

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		unset( $key );
		return false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( ...$args ) {
		unset( $args );
		return true;
	}
}

if ( ! function_exists( 'movies_wp_media_signed_url' ) ) {
	function movies_wp_media_signed_url( $relative_path, $type = 'v', $ttl_seconds = null ) {
		unset( $ttl_seconds );
		$path = str_replace( '\\', '/', ltrim( (string) $relative_path, '/' ) );
		if ( '' === $path || str_contains( $path, '..' ) ) {
			return new WP_Error( 'media_path_invalid', 'bad path' );
		}
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
	++$failures;
	echo "  FAIL  {$label}\n";
}

function assert_eq( $expected, $actual, string $label ): void {
	assert_true(
		$expected === $actual,
		$label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')'
	);
}

echo "streaming subtitle track tests\n\n";

$movie_id = 26;
$path     = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.WEB-DL.srt';
$subs     = streamit_child_normalize_subtitles(
	array(
		array(
			'url'     => $path,
			'srclang' => '',
			'label'   => '',
			'format'  => 'SRT',
		),
	)
);

echo "track markup\n";

$tracks = streamit_child_build_subtitle_tracks( $subs, $movie_id, 'movie' );

assert_true( '' !== $tracks, 'movie 26 subtitle row produces a <track> element' );
assert_true( str_contains( $tracks, 'kind="captions"' ), 'track declares kind=captions' );
assert_true( str_contains( $tracks, 'srclang="und"' ), 'empty srclang renders as und, no invented language' );
assert_true( str_contains( $tracks, 'label="' ), 'track carries a label' );
assert_true(
	str_contains( $tracks, 'src="https://asiastarx.ir/?streamit_subtitle=26' ),
	'track src is same-origin so the browser will actually fetch it'
);
assert_true( ! str_contains( $tracks, 'media.asiastarx.ir' ), 'track src is not the cross-origin media host' );
assert_true( ! str_contains( $tracks, '/data/' ), 'track src never exposes a /data path' );
assert_true(
	str_contains( $tracks, 'streamit_subtitle_i=0' ),
	'track src addresses the subtitle by its normalized index'
);
assert_eq( $path, $subs[0]['url'], 'stored url stays relative after building tracks' );
assert_eq( '', streamit_child_build_subtitle_tracks( $subs, 0, 'movie' ), 'no post context means no track markup' );
assert_eq( '', streamit_child_build_subtitle_tracks( array(), $movie_id, 'movie' ), 'no subtitles means no track markup' );

echo "\nplayer html injection\n";

$default_html = '<video class="plyr__video-embed" id="streamit_player" playsinline >'
	. '<source src="https://media.asiastarx.ir/v/TOKEN_default" type="video/mp4" />'
	. '</video>';

$with_tracks = streamit_child_insert_subtitle_tracks( $default_html, $tracks );

assert_true( str_contains( $with_tracks, '<track' ), 'default source html gains a track' );
assert_true(
	strpos( $with_tracks, '<track' ) < strpos( $with_tracks, '</video>' ),
	'track sits inside the <video> element'
);
assert_true(
	strpos( $with_tracks, '<source' ) < strpos( $with_tracks, '<track' ),
	'track follows the <source> element'
);
assert_eq(
	$with_tracks,
	streamit_child_insert_subtitle_tracks( $with_tracks, $tracks ),
	'injection is idempotent, never duplicating tracks'
);

$embed_html = '<iframe src="https://www.youtube.com/embed/x"></iframe>';
assert_eq( $embed_html, streamit_child_insert_subtitle_tracks( $embed_html, $tracks ), 'iframe embeds are left untouched' );
assert_eq( $default_html, streamit_child_insert_subtitle_tracks( $default_html, '' ), 'no tracks means html is unchanged' );

echo "\nsource switching\n";

// mediaplayer.js replaces the player markup with the stored data-sources entry,
// so every quality entry (including same-quality alternatives) must already
// carry the tracks.
$quality_sources = array(
	'1080p'          => '1080p',
	'720p Source 1'  => '720p',
	'720p Source 2'  => '720p',
	'480p Source 1'  => '480p',
	'480p Source 2'  => '480p',
);
$quality_html = array();
foreach ( $quality_sources as $source_label => $quality ) {
	$quality_html[ $source_label ] = streamit_child_insert_subtitle_tracks(
		'<video class="plyr__video-embed" id="streamit_player" playsinline >'
			. '<source src="https://media.asiastarx.ir/v/TOKEN_' . rawurlencode( $source_label ) . '" type="video/mp4" />'
			. '</video>',
		$tracks
	);
}

$processed_sources = array_merge(
	array( array( 'name' => 'Default', 'content' => $with_tracks ) ),
	array_map(
		static function ( $source_label ) use ( $quality_html, $quality_sources ) {
			return array(
				'name'    => $source_label,
				'quality' => $quality_sources[ $source_label ],
				'content' => $quality_html[ $source_label ],
			);
		},
		array_keys( $quality_html )
	)
);

foreach ( $processed_sources as $source ) {
	assert_true(
		str_contains( $source['content'], 'kind="captions"' ),
		'source "' . $source['name'] . '" keeps the caption track after switching'
	);
}

$encoded = json_encode( $processed_sources );
assert_true( is_string( $encoded ) && str_contains( $encoded, 'kind=\\"captions\\"' ), 'tracks survive data-sources encoding' );

echo "\ncaption language for plyr\n";

assert_eq( 'und', streamit_child_subtitle_track_language( $subs ), 'unknown language stays und for the player config' );
assert_eq(
	'fa',
	streamit_child_subtitle_track_language(
		streamit_child_normalize_subtitles( array( array( 'url' => $path, 'srclang' => 'fa' ) ) )
	),
	'known language is used as-is'
);
assert_eq(
	'en',
	streamit_child_subtitle_track_language(
		streamit_child_normalize_subtitles(
			array(
				array( 'url' => $path, 'srclang' => 'fa' ),
				array( 'url' => $path, 'srclang' => 'en', 'default' => 1 ),
			)
		)
	),
	'the row marked default wins'
);
assert_eq( '', streamit_child_subtitle_track_language( array() ), 'no subtitles means no caption language' );

echo "\nsrt to webvtt conversion\n";

$srt = "\xEF\xBB\xBF1\r\n00:00:01,234 --> 00:00:03,456\r\nخط اول\r\n\r\n2\r\n01:02:03,000 --> 01:02:05,500\r\n<i>line two</i>\r\n";
$vtt = streamit_child_srt_to_vtt( $srt );

assert_true( str_starts_with( $vtt, "WEBVTT\n\n" ), 'output starts with the WEBVTT signature' );
assert_true( ! str_contains( $vtt, "\xEF\xBB\xBF" ), 'BOM is stripped' );
assert_true( ! str_contains( $vtt, "\r" ), 'CRLF line endings are normalized' );
assert_true( str_contains( $vtt, '00:00:01.234 --> 00:00:03.456' ), 'millisecond comma becomes a dot' );
assert_true( str_contains( $vtt, '01:02:03.000 --> 01:02:05.500' ), 'every cue timestamp is converted' );
assert_true( str_contains( $vtt, 'خط اول' ), 'Persian cue text is preserved' );
assert_true( str_contains( $vtt, '<i>line two</i>' ), 'inline markup is preserved' );

assert_eq(
	"WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nhi\n",
	streamit_child_subtitle_body_to_vtt( "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nhi\n", 'vtt' ),
	'existing WebVTT passes through unchanged'
);
assert_true(
	str_starts_with( streamit_child_subtitle_body_to_vtt( "1\n00:00:01,000 --> 00:00:02,000\nhi\n", 'vtt' ), 'WEBVTT' ),
	'a .vtt file that is really SubRip is still repaired'
);

// Skip when this PHP build has no Windows-1256 (common on slim Docker images).
$mb_encodings = function_exists( 'mb_list_encodings' ) ? mb_list_encodings() : array();
if ( function_exists( 'mb_convert_encoding' ) && in_array( 'Windows-1256', $mb_encodings, true ) ) {
	$legacy = mb_convert_encoding( "1\n00:00:01,000 --> 00:00:02,000\nسلام\n", 'Windows-1256', 'UTF-8' );
	$out    = streamit_child_subtitle_body_to_vtt( $legacy, 'srt' );
	assert_true( mb_check_encoding( $out, 'UTF-8' ), 'Windows-1256 subtitles are decoded to UTF-8' );
	assert_true( str_contains( $out, 'سلام' ), 'Windows-1256 Persian text survives conversion' );
} else {
	echo "  skip  Windows-1256 encoding not available in this PHP build\n";
}

echo "\nsubtitle format gate\n";

assert_eq( 'srt', streamit_child_subtitle_extension( $path ), 'relative .srt extension detected' );
assert_eq( 'vtt', streamit_child_subtitle_extension( 'https://example.com/a/b.vtt?x=1' ), 'extension read from URL path' );
assert_eq( '', streamit_child_subtitle_extension( '' ), 'empty path has no extension' );
assert_eq(
	'',
	streamit_child_subtitle_fetch_vtt( 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.mkv' ),
	'the endpoint refuses to proxy non-subtitle files'
);

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}
echo "All streaming subtitle track assertions passed.\n";
exit( 0 );

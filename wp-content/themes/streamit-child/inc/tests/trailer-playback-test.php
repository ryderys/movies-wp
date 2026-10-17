<?php
/**
 * CLI tests for public trailer playback resolution.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/trailer-playback-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-trailer-playback-test/' );
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

if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {
		unset( $args );
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {
		unset( $args );
		return true;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

/** @var list<array{path:string,type:string}> */
$GLOBALS['trailer_sign_calls'] = array();

if ( ! function_exists( 'movies_wp_media_normalize_relative_path' ) ) {
	function movies_wp_media_normalize_relative_path( $relative_path ) {
		$path = str_replace( '\\', '/', (string) $relative_path );
		$path = ltrim( $path, '/' );
		if ( '' === $path || false !== strpos( $path, "\0" ) ) {
			return new WP_Error( 'media_path_empty', 'empty' );
		}
		foreach ( explode( '/', $path ) as $part ) {
			if ( '' === $part || '.' === $part || '..' === $part ) {
				return new WP_Error( 'media_path_traversal', 'bad' );
			}
		}
		return $path;
	}
}

if ( ! function_exists( 'movies_wp_media_signed_url' ) ) {
	function movies_wp_media_signed_url( $relative_path, $type = 'v', $ttl_seconds = null ) {
		unset( $ttl_seconds );
		$path = movies_wp_media_normalize_relative_path( $relative_path );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		$GLOBALS['trailer_sign_calls'][] = array(
			'path' => $path,
			'type' => (string) $type,
		);
		return 'https://media.asiastarx.ir/' . $type . '/TOKEN_' . rawurlencode( $path );
	}
}

if ( ! function_exists( 'streamit_get_trailer_embed' ) ) {
	function streamit_get_trailer_embed( $trailer_link ) {
		$trailer_link = (string) $trailer_link;
		if ( '' === $trailer_link ) {
			return array(
				'type'         => '',
				'trailer_link' => '',
			);
		}
		$ext = strtolower( (string) pathinfo( (string) parse_url( $trailer_link, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'mp4', 'mkv', 'webm' ), true ) ) {
			return array(
				'type'         => 'video',
				'trailer_link' => $trailer_link,
			);
		}
		if ( false !== stripos( $trailer_link, 'youtube.com' ) || false !== stripos( $trailer_link, 'youtu.be' ) ) {
			return array(
				'type'         => 'iframe',
				'trailer_link' => 'https://www.youtube.com/embed/stub',
			);
		}
		if ( false !== stripos( $trailer_link, 'vimeo.com' ) ) {
			return array(
				'type'         => 'iframe',
				'trailer_link' => 'https://player.vimeo.com/video/stub',
			);
		}
		return array(
			'type'         => '',
			'trailer_link' => '',
		);
	}
}

require_once dirname( __DIR__ ) . '/media-player-rewrite.php';
require_once dirname( __DIR__ ) . '/trailer-playback.php';

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

echo "public trailer playback tests\n\n";

$GLOBALS['trailer_sign_calls'] = array();

$empty = streamit_child_get_trailer_playback( '' );
assert_eq( '', $empty['type'], 'empty meta → empty type' );
assert_eq( '', $empty['trailer_link'], 'empty meta → empty link' );
assert_eq( 0, count( $GLOBALS['trailer_sign_calls'] ), 'empty meta does not mint' );

$yt = streamit_child_get_trailer_playback( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
assert_eq( 'iframe', $yt['type'], 'YouTube stays iframe embed' );
assert_true( str_contains( $yt['trailer_link'], 'youtube.com/embed' ), 'YouTube embed URL preserved' );
assert_eq( 0, count( $GLOBALS['trailer_sign_calls'] ), 'external YouTube does not mint' );

$aparat_embed = 'https://www.aparat.com/video/video/embed/videohash/nwlrj1m/vt/frame';
$aparat       = streamit_child_get_trailer_playback( 'https://www.aparat.com/v/nwlrj1m' );
assert_eq( 'iframe', $aparat['type'], 'Aparat www watch URL is an iframe' );
assert_eq( $aparat_embed, $aparat['trailer_link'], 'Aparat www watch URL becomes the embed URL' );
assert_eq( '', $aparat['mime'], 'Aparat embed has no video MIME' );
assert_eq( 0, count( $GLOBALS['trailer_sign_calls'] ), 'Aparat www URL does not mint' );

$aparat_apex = streamit_child_get_trailer_playback( 'https://aparat.com/v/nwlrj1m' );
assert_eq( 'iframe', $aparat_apex['type'], 'Aparat apex host is an iframe' );
assert_eq( $aparat_embed, $aparat_apex['trailer_link'], 'Aparat apex host becomes the www embed URL' );
assert_eq( 0, count( $GLOBALS['trailer_sign_calls'] ), 'Aparat apex URL does not mint' );

$aparat_slash = streamit_child_get_trailer_playback( 'https://www.aparat.com/v/nwlrj1m/' );
assert_eq( $aparat_embed, $aparat_slash['trailer_link'], 'Aparat trailing slash still embeds' );

$bad_extra = streamit_child_get_trailer_playback( 'https://www.aparat.com/v/nwlrj1m/extra' );
assert_eq( '', $bad_extra['trailer_link'], 'extra Aparat path is rejected' );
assert_eq( '', $bad_extra['type'], 'extra Aparat path has no embed type' );

$bad_short = streamit_child_get_trailer_playback( 'https://www.aparat.com/v/abcd' );
assert_eq( '', $bad_short['trailer_link'], 'short Aparat hash is rejected' );

$bad_dot = streamit_child_get_trailer_playback( 'https://www.aparat.com/v/nwl.rj1m' );
assert_eq( '', $bad_dot['trailer_link'], 'dotted Aparat hash is rejected' );

$bad_query = streamit_child_get_trailer_playback( 'https://www.aparat.com/v/nwlrj1m?autoplay=1' );
assert_eq( '', $bad_query['trailer_link'], 'Aparat URL with query is rejected' );

$bad_http = streamit_child_get_trailer_playback( 'http://www.aparat.com/v/nwlrj1m' );
assert_eq( '', $bad_http['trailer_link'], 'plain http Aparat URL is rejected' );

$bad_host = streamit_child_get_trailer_playback( 'https://evil.aparat.com/v/nwlrj1m' );
assert_eq( '', $bad_host['trailer_link'], 'unexpected Aparat host is rejected' );

$other_host = streamit_child_get_trailer_playback( 'https://example.com/v/nwlrj1m' );
assert_eq( '', $other_host['trailer_link'], 'unexpected host is rejected' );
assert_eq( 0, count( $GLOBALS['trailer_sign_calls'] ), 'rejected Aparat-like URLs do not mint' );

$vimeo = streamit_child_get_trailer_playback( 'https://vimeo.com/123456' );
assert_eq( 'iframe', $vimeo['type'], 'Vimeo stays iframe embed' );
assert_eq( 0, count( $GLOBALS['trailer_sign_calls'] ), 'external Vimeo does not mint' );

$ext_mp4 = streamit_child_get_trailer_playback( 'https://cdn.example.com/promo.mp4' );
assert_eq( 'video', $ext_mp4['type'], 'external mp4 stays video' );
assert_eq( 'https://cdn.example.com/promo.mp4', $ext_mp4['trailer_link'], 'external mp4 URL unchanged' );
assert_eq( 'video/mp4', $ext_mp4['mime'], 'external mp4 MIME' );
assert_eq( 0, count( $GLOBALS['trailer_sign_calls'] ), 'external mp4 does not mint' );

$valid = 'Movie/Korea/2022/Decision.to.Leave/Trailer/Decision.to.Leave.trailer.mp4';
$GLOBALS['trailer_sign_calls'] = array();
$local = streamit_child_get_trailer_playback( $valid );
assert_eq( 'video', $local['type'], 'valid Trailer/ mp4 → video' );
assert_true( str_starts_with( $local['trailer_link'], 'https://media.asiastarx.ir/v/' ), 'valid Trailer/ mp4 is signed' );
assert_eq( 'video/mp4', $local['mime'], 'mp4 MIME from mapping' );
assert_eq( 1, count( $GLOBALS['trailer_sign_calls'] ), 'mint called once for valid path' );
assert_eq( $valid, $GLOBALS['trailer_sign_calls'][0]['path'] ?? null, 'signer receives relative Trailer path' );
assert_true( ! str_contains( $local['trailer_link'], '/data' ), 'signed URL does not expose /data' );

$mkv = 'series/korea/2024/Family.by.Choice/Trailers/family.trailer.mkv';
$GLOBALS['trailer_sign_calls'] = array();
$local_mkv = streamit_child_get_trailer_playback( $mkv );
assert_eq( 'video', $local_mkv['type'], 'Trailers/ mkv → video' );
assert_eq( 'video/x-matroska', $local_mkv['mime'], 'mkv MIME is not video/mp4' );
assert_eq( $mkv, $GLOBALS['trailer_sign_calls'][0]['path'] ?? null, 'Trailers/ path signed' );

$case = 'Movie/Iran/2024/Foo/trailer/Foo.trailer.MP4';
assert_true( streamit_child_is_allowed_trailer_media_path( $case ), 'trailer folder name is case-insensitive' );

$from_data = streamit_child_get_trailer_playback( '/data/' . $valid );
assert_true( str_starts_with( $from_data['trailer_link'], 'https://media.asiastarx.ir/v/' ), '/data prefix stripped then signed' );

$GLOBALS['trailer_sign_calls'] = array();
$feature = streamit_child_get_trailer_playback( 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.1080p.mkv' );
assert_eq( '', $feature['trailer_link'], 'feature file outside Trailer/ is rejected' );
assert_eq( 0, count( $GLOBALS['trailer_sign_calls'] ), 'feature file is never signed' );

$nested = streamit_child_get_trailer_playback( 'Movie/Korea/2022/Foo/Trailer/extra/nested.mp4' );
assert_eq( '', $nested['trailer_link'], 'nested file under Trailer/ subdir is rejected' );
assert_eq( 0, count( $GLOBALS['trailer_sign_calls'] ), 'nested Trailer path is never signed' );

$traversal = streamit_child_get_trailer_playback( 'Movie/Korea/2022/Foo/Trailer/../Foo.mkv' );
assert_eq( '', $traversal['trailer_link'], 'path traversal is rejected' );
assert_eq( 0, count( $GLOBALS['trailer_sign_calls'] ), 'traversal path is never signed' );

$false_folder = streamit_child_get_trailer_playback( 'Movie/Korea/2022/Foo/NotTrailer/clip.mp4' );
assert_eq( '', $false_folder['trailer_link'], 'NotTrailer folder is rejected' );

$btn_none = streamit_child_get_movie_trailer_button( '' );
assert_eq( null, $btn_none, 'empty meta hides Watch Trailer button' );

$btn_bad = streamit_child_get_movie_trailer_button( 'https://www.aparat.com/v/abcd' );
assert_eq( null, $btn_bad, 'invalid Aparat URL hides Watch Trailer button' );

$btn_aparat = streamit_child_get_movie_trailer_button( 'https://www.aparat.com/v/nwlrj1m' );
assert_true( is_array( $btn_aparat ), 'valid Aparat shows Watch Trailer button' );
assert_eq( 'iframe', $btn_aparat['type'] ?? null, 'Aparat button type is iframe' );
assert_eq( $aparat_embed, $btn_aparat['trailer_link'] ?? null, 'Aparat button uses embed URL' );

$btn_yt = streamit_child_get_movie_trailer_button( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
assert_true( is_array( $btn_yt ), 'YouTube shows Watch Trailer button' );
assert_eq( 'iframe', $btn_yt['type'] ?? null, 'YouTube button type is iframe' );

$btn_vimeo = streamit_child_get_movie_trailer_button( 'https://vimeo.com/123456' );
assert_true( is_array( $btn_vimeo ), 'Vimeo shows Watch Trailer button' );
assert_eq( 'iframe', $btn_vimeo['type'] ?? null, 'Vimeo button type is iframe' );

$GLOBALS['trailer_sign_calls'] = array();
$btn_local = streamit_child_get_movie_trailer_button( $valid );
assert_true( is_array( $btn_local ), 'local Trailer path shows Watch Trailer button' );
assert_eq( 'video', $btn_local['type'] ?? null, 'local button type is video' );
assert_true( str_starts_with( (string) ( $btn_local['trailer_link'] ?? '' ), 'https://media.asiastarx.ir/v/' ), 'local button uses signed URL' );

$btn_feature = streamit_child_get_movie_trailer_button( 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.1080p.mkv' );
assert_eq( null, $btn_feature, 'non-trailer local path hides Watch Trailer button' );

echo "\nadmin trailer link acceptance\n";
assert_true( streamit_child_is_accepted_trailer_link( '' ), 'empty trailer link is allowed' );
assert_true( streamit_child_is_accepted_trailer_link( '  ' ), 'blank trailer link is allowed' );
assert_true(
	streamit_child_is_accepted_trailer_link( 'Movie/Chin/2016/Bounty.Hunters/Trailer/official.mp4' ),
	'existing relative Trailer/ path is accepted'
);
assert_true(
	streamit_child_is_accepted_trailer_link( 'series/Chin/2025/Dream.Show/Trailers/teaser.webm' ),
	'Trailers/ webm path is accepted'
);
assert_true(
	streamit_child_is_accepted_trailer_link( 'Movie/Iran/2024/Foo/trailer/Foo.trailer.MKV' ),
	'case-insensitive trailer folder and extension are accepted'
);
assert_true(
	streamit_child_is_accepted_trailer_link( 'https://cdn.example.com/trailers/official.mp4' ),
	'ordinary https trailer URL is accepted'
);
assert_true(
	streamit_child_is_accepted_trailer_link( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ),
	'YouTube URL is accepted'
);
assert_true(
	streamit_child_is_accepted_trailer_link( 'https://youtu.be/dQw4w9WgXcQ' ),
	'YouTube short URL is accepted'
);
assert_true(
	streamit_child_is_accepted_trailer_link( 'https://vimeo.com/123456' ),
	'Vimeo URL is accepted'
);
assert_true(
	streamit_child_is_accepted_trailer_link( 'https://www.aparat.com/v/nwlrj1m' ),
	'Aparat URL is accepted'
);
assert_true(
	streamit_child_is_accepted_trailer_link( 'http://cdn.example.com/trailer.mp4' ),
	'existing http trailer URL is accepted'
);
assert_true( ! streamit_child_is_accepted_trailer_link( 'not a url' ), 'plain text is rejected' );
assert_true( ! streamit_child_is_accepted_trailer_link( 'https://' ), 'scheme-only URL is rejected' );
assert_true( ! streamit_child_is_accepted_trailer_link( 'javascript:alert(1)' ), 'javascript URL is rejected' );
assert_true( ! streamit_child_is_accepted_trailer_link( 'ftp://cdn.example.com/a.mp4' ), 'non-http scheme is rejected' );
assert_true(
	! streamit_child_is_accepted_trailer_link( 'Movie/Korea/2022/Foo/Decision.to.Leave.mkv' ),
	'feature path outside Trailer/ is rejected'
);
assert_true(
	! streamit_child_is_accepted_trailer_link( 'Movie/Korea/2022/Foo/Trailer/../Foo.mkv' ),
	'traversal path is rejected'
);
assert_true(
	! streamit_child_is_accepted_trailer_link( 'Movie/Korea/2022/Foo/Trailer/extra/nested.mp4' ),
	'nested Trailer path is rejected'
);
assert_true(
	! streamit_child_is_accepted_trailer_link( 'Movie/Korea/2022/Foo/Trailer/poster.jpg' ),
	'non-video under Trailer/ is rejected'
);

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED {$failures} assertion(s)\n";
	exit( 1 );
}

echo "All trailer playback tests passed.\n";
exit( 0 );

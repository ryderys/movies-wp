<?php
/**
 * CLI tests for media_scan_movie_dir()
 * (resolve → list → parse → probe → validate → normalize).
 *
 * Run: php media-server/tests/movie-scan-test.php
 *
 * Uses an injectable ffprobe runner — no real ffprobe binary required.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/movie-scan.php';

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

function find_file( array $files, string $name ): ?array {
	foreach ( $files as $file ) {
		if ( ( $file['name'] ?? '' ) === $name ) {
			return $file;
		}
	}
	return null;
}

/**
 * @param list<string> $probed_basenames Filled with basename of every path passed to ffprobe.
 * @return callable
 */
function make_scan_ffprobe_runner( array &$probed_basenames, ?string $fail_basename = null, ?string $hevc_basename = null ): callable {
	return static function ( array $argv ) use ( &$probed_basenames, $fail_basename, $hevc_basename ): array {
		$path = (string) end( $argv );
		$base = basename( $path );
		$probed_basenames[] = $base;

		if ( $fail_basename !== null && $base === $fail_basename ) {
			return array(
				'exit'   => 1,
				'stdout' => '',
				'stderr' => 'Invalid data found when processing input',
			);
		}

		$width  = 1920;
		$height = 1080;
		$lower  = strtolower( $base );
		if ( str_contains( $lower, '720p' ) ) {
			$width  = 1280;
			$height = 720;
		} elseif ( str_contains( $lower, '480p' ) ) {
			$width  = 854;
			$height = 480;
		} elseif ( str_contains( $lower, '2160p' ) || str_contains( $lower, '.4k.' ) ) {
			$width  = 3840;
			$height = 2160;
		}

		$codec = ( $hevc_basename !== null && $base === $hevc_basename ) ? 'hevc' : 'h264';

		$payload = array(
			'format'  => array( 'duration' => '6152.48' ),
			'streams' => array(
				array(
					'codec_type' => 'video',
					'codec_name' => $codec,
					'width'      => $width,
					'height'     => $height,
				),
				array(
					'codec_type' => 'audio',
					'codec_name' => 'aac',
					'channels'   => 2,
					'tags'       => array( 'language' => 'fa' ),
				),
				array(
					'codec_type' => 'audio',
					'codec_name' => 'aac',
					'channels'   => 2,
					'tags'       => array( 'language' => 'en' ),
				),
				array(
					'codec_type' => 'subtitle',
					'codec_name' => 'subrip',
					'tags'       => array( 'language' => 'ko' ),
				),
			),
		);

		$json = json_encode( $payload, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			return array(
				'exit'   => 1,
				'stdout' => '',
				'stderr' => 'fixture encode failed',
			);
		}

		return array(
			'exit'   => 0,
			'stdout' => $json,
			'stderr' => '',
		);
	};
}

$tmp        = sys_get_temp_dir() . '/movie-scan-test-' . bin2hex( random_bytes( 4 ) );
$movie_root = $tmp . '/Movie';

$soul    = $movie_root . '/Korea/2018/The.Soul.Mate';
$vapor   = $movie_root . '/Korea/2018/Vapor';
$believe = $movie_root . '/Korea/2023/Believer.2';

foreach ( array( $soul, $vapor, $believe ) as $dir ) {
	if ( ! mkdir( $dir, 0777, true ) ) {
		fwrite( STDERR, "Could not create {$dir}\n" );
		exit( 1 );
	}
}

$soul_1080  = 'The.Soul.Mate.1080p.WEB-DL.mkv';
$soul_720   = 'The.Soul.Mate.2018.WEB-DL.720p.SS.mkv';
$vapor_480  = 'Vapor.2018.480p.WAVVE.WEB-DL.mkv';
$vapor_1080 = 'Vapor.2018.1080p.WAVVE.WEB-DL.AAC2.0.H.264-tG1R0.mkv';
$bel_mkv    = 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.mkv';
$bel_srt    = 'Believer.2.NF.AirenTeam.srt';
$bel_fa_srt = 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.fa.srt';

file_put_contents( $soul . '/' . $soul_1080, str_repeat( 'a', 100 ) );
file_put_contents( $soul . '/' . $soul_720, str_repeat( 'b', 200 ) );
file_put_contents( $vapor . '/' . $vapor_480, str_repeat( 'c', 300 ) );
file_put_contents( $vapor . '/' . $vapor_1080, str_repeat( 'd', 400 ) );
file_put_contents( $believe . '/' . $bel_mkv, str_repeat( 'e', 500 ) );
file_put_contents( $believe . '/' . $bel_srt, str_repeat( 'f', 50 ) );
file_put_contents( $believe . '/' . $bel_fa_srt, str_repeat( 'g', 40 ) );
file_put_contents( $vapor . '/cover.jpg', 'jpg' );
mkdir( $vapor . '/SUB', 0777, true );
file_put_contents( $vapor . '/SUB/nested.srt', 'nope' );

$cleanup = static function () use ( $tmp ): void {
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $it as $f ) {
		$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
	}
	rmdir( $tmp );
};

$probed = array();
$opts   = array( 'ffprobe_runner' => make_scan_ffprobe_runner( $probed ) );

echo "resolver errors pass through\n";
$bad = media_scan_movie_dir( 'series/Korea/2018/Nope', $tmp, $movie_root );
assert_true( ( $bad['ok'] ?? true ) === false && ( $bad['code'] ?? '' ) === 'invalid_structure', 'series path is resolver error' );

echo "\nThe.Soul.Mate\n";
$probed = array();
$opts   = array( 'ffprobe_runner' => make_scan_ffprobe_runner( $probed ) );
$scan   = media_scan_movie_dir( 'Movie/Korea/2018/The.Soul.Mate', $tmp, $movie_root, $opts );
assert_true( ( $scan['ok'] ?? false ) === true, 'soul scan ok' );
assert_true( ( $scan['country'] ?? '' ) === 'Korea', 'soul country from resolver' );
assert_true( ( $scan['year'] ?? 0 ) === 2018, 'soul year from resolver' );
assert_true( ( $scan['movie_name'] ?? '' ) === 'The.Soul.Mate', 'soul movie_name' );

$f1080 = find_file( $scan['files'] ?? array(), $soul_1080 );
assert_true( is_array( $f1080 ) && $f1080['kind'] === 'video', '1080p kind from lister' );
assert_true( is_array( $f1080 ) && $f1080['size_bytes'] === 100, '1080p size from lister' );
assert_true( is_array( $f1080 ) && $f1080['quality'] === '1080p', '1080p quality from parser' );
assert_true( is_array( $f1080 ) && $f1080['source_type'] === 'WEB-DL', '1080p source from parser' );
assert_true( is_array( $f1080 ) && $f1080['audio_confidence'] === 'unknown', '1080p audio unknown' );
assert_true( is_array( $f1080 ) && $f1080['encoder'] === null, '1080p encoder null' );
assert_true(
	is_array( $f1080 ) && $f1080['media_path'] === 'Movie/Korea/2018/The.Soul.Mate/' . $soul_1080,
	'1080p media_path'
);
assert_true( is_array( $f1080 ) && isset( $f1080['probe'] ) && is_array( $f1080['probe'] ), '1080p has nested probe' );
assert_true( is_array( $f1080 ) && ( $f1080['probe']['ok'] ?? false ) === true, '1080p probe ok' );
assert_true( is_array( $f1080 ) && isset( $f1080['validation'] ) && is_array( $f1080['validation'] ), '1080p has validation' );
assert_true(
	is_array( $f1080 ) && ( $f1080['validation']['facts']['probe_resolution']['quality_class'] ?? null ) === '1080p',
	'1080p validation resolution fact'
);
assert_true(
	is_array( $f1080 ) && ! in_array( 'filename_probe_resolution_mismatch', array_column( $f1080['validation']['warnings'] ?? array(), 'code' ), true ),
	'1080p no resolution mismatch'
);
assert_true( is_array( $f1080 ) && isset( $f1080['detected'] ) && is_array( $f1080['detected'] ), '1080p has detected' );
assert_true(
	is_array( $f1080 ) && ( $f1080['detected']['identity']['quality']['value'] ?? null ) === '1080p',
	'detected.identity.quality from filename'
);
assert_true(
	is_array( $f1080 ) && ( $f1080['detected']['identity']['quality']['source'] ?? null ) === 'filename',
	'detected quality source=filename'
);
assert_true(
	is_array( $f1080 ) && ( $f1080['detected']['video']['width'] ?? null ) === 1920 && ( $f1080['detected']['video']['height'] ?? null ) === 1080,
	'detected.video WxH from ffprobe'
);
assert_true(
	is_array( $f1080 ) && ( $f1080['detected']['video']['codec']['value'] ?? null ) === 'h264',
	'detected.video.codec from ffprobe'
);
assert_true(
	is_array( $f1080 ) && ( $f1080['detected']['video']['codec']['source'] ?? null ) === 'ffprobe',
	'detected codec source=ffprobe'
);
assert_true(
	is_array( $f1080 )
		&& ( $f1080['validation']['facts'] ?? null ) === ( $f1080['detected']['validation']['facts'] ?? null )
		&& array_column( $f1080['validation']['warnings'] ?? array(), 'code' )
			=== array_column( $f1080['detected']['validation']['warnings'] ?? array(), 'code' ),
	'top-level validation preserved inside detected.validation'
);

$f720 = find_file( $scan['files'] ?? array(), $soul_720 );
assert_true( is_array( $f720 ) && $f720['quality'] === '720p', '720p quality' );
assert_true( is_array( $f720 ) && $f720['source_type'] === 'WEB-DL', '720p source' );
assert_true( is_array( $f720 ) && $f720['encoder'] === null, 'SS is not encoder' );
assert_true( is_array( $f720 ) && in_array( 'SS', $f720['unclassified'] ?? array(), true ), 'SS unclassified' );
assert_true( is_array( $f720 ) && isset( $f720['probe']['ok'] ), '720p has probe' );
assert_true(
	is_array( $f720 ) && in_array( 'SS', $f720['detected']['release']['unclassified'] ?? array(), true ),
	'detected: SS remains unclassified'
);
assert_true(
	is_array( $f720 ) && ( $f720['detected']['release']['encoder'] ?? null ) === null,
	'detected: SS not encoder'
);

echo "\nVapor (parser + probe stay separate)\n";
$probed = array();
$opts   = array( 'ffprobe_runner' => make_scan_ffprobe_runner( $probed ) );
$vscan  = media_scan_movie_dir( 'Movie/Korea/2018/Vapor', $tmp, $movie_root, $opts );
assert_true( ( $vscan['ok'] ?? false ) === true, 'vapor scan ok' );
assert_true( ( $vscan['movie_name'] ?? '' ) === 'Vapor', 'vapor movie_name' );

$v480 = find_file( $vscan['files'] ?? array(), $vapor_480 );
assert_true( is_array( $v480 ) && $v480['quality'] === '480p' && $v480['provider'] === 'WAVVE', '480p WAVVE WEB-DL' );
assert_true( is_array( $v480 ) && $v480['source_type'] === 'WEB-DL', '480p source' );
assert_true( is_array( $v480 ) && $v480['size_bytes'] === 300, '480p size preserved' );
assert_true( is_array( $v480 ) && ( $v480['probe']['ok'] ?? false ) === true, '480p probe ok' );

$v1080 = find_file( $vscan['files'] ?? array(), $vapor_1080 );
assert_true( is_array( $v1080 ) && $v1080['audio_codec'] === 'AAC2.0', '1080p audio_codec from parser' );
assert_true( is_array( $v1080 ) && $v1080['video_codec'] === 'H.264', '1080p video_codec from parser' );
assert_true( is_array( $v1080 ) && $v1080['release_group'] === 'tG1R0', '1080p release_group' );
assert_true( is_array( $v1080 ) && $v1080['audio_confidence'] === 'unknown', '1080p audio unknown from parser' );
assert_true( is_array( $v1080 ) && ( $v1080['probe']['ok'] ?? false ) === true, '1080p probe ok' );
assert_true( is_array( $v1080 ) && ( $v1080['probe']['duration'] ?? null ) === 6152, 'probe duration nested' );
assert_true( is_array( $v1080 ) && ( $v1080['probe']['video']['codec'] ?? null ) === 'h264', 'probe.video.codec separate from video_codec' );
assert_true( is_array( $v1080 ) && ( $v1080['probe']['video']['width'] ?? null ) === 1920, 'probe.video.width' );
assert_true( is_array( $v1080 ) && count( $v1080['probe']['audio'] ?? array() ) === 2, 'multiple probe audio tracks' );
assert_true( is_array( $v1080 ) && ( $v1080['probe']['audio'][0]['language'] ?? null ) === 'fa', 'probe.audio language stays nested' );
assert_true( is_array( $v1080 ) && ( $v1080['probe']['audio'][1]['language'] ?? null ) === 'en', 'second probe audio language' );
assert_true(
	is_array( $v1080 ) && ( $v1080['audio_languages'] ?? array() ) === array(),
	'parser audio_languages not overwritten by probe'
);
assert_true( is_array( $v1080 ) && count( $v1080['probe']['subtitles'] ?? array() ) === 1, 'embedded probe.subtitles preserved' );
assert_true( is_array( $v1080 ) && ( $v1080['probe']['subtitles'][0]['language'] ?? null ) === 'ko', 'embedded subtitle language nested' );
assert_true( is_array( $v1080 ) && isset( $v1080['validation'] ), '1080p has nested validation' );
assert_true(
	is_array( $v1080 ) && ( $v1080['validation']['facts']['probe_audio_languages'] ?? null ) === array( 'fa', 'en' ),
	'validation facts keep probe audio langs separate from parser'
);
assert_true(
	is_array( $v1080 ) && ( ( $v1080['validation']['facts']['probe_audio_languages'] ?? array() ) !== array() ),
	'unknown filename audio → probe languages remain in facts'
);
assert_true(
	is_array( $v1080 ) && ! in_array( 'probe_audio_language_detected', array_column( $v1080['validation']['warnings'] ?? array(), 'code' ), true ),
	'probe language reconciliation is not a validation warning'
);
assert_true( is_array( $v1080 ) && $v1080['video_codec'] === 'H.264', 'parser video_codec not overwritten by validation' );
assert_true( is_array( $v480 ) && ( $v480['validation']['facts']['probe_resolution']['quality_class'] ?? null ) === '480p', '480p resolution class matches filename' );
assert_true( is_array( $v1080 ) && isset( $v1080['detected'] ), 'vapor 1080p has detected' );
assert_true(
	is_array( $v1080 ) && ( $v1080['detected']['video']['codec_filename']['value'] ?? null ) === 'H.264',
	'detected.video.codec_filename preserves filename codec'
);
assert_true(
	is_array( $v1080 ) && ( $v1080['detected']['video']['codec']['value'] ?? null ) === 'h264',
	'detected.video.codec from ffprobe (not filename)'
);
assert_true(
	is_array( $v1080 ) && count( $v1080['detected']['audio']['tracks'] ?? array() ) === 2,
	'detected.audio.tracks from ffprobe only'
);
assert_true(
	is_array( $v1080 ) && ( $v1080['detected']['audio']['tracks'][0]['language'] ?? null ) === 'fa',
	'detected audio lang from probe, not country'
);
assert_true(
	is_array( $v1080 ) && ( $v1080['detected']['audio']['tracks'][0]['language'] ?? null ) !== 'ko',
	'Korea directory never becomes audio language'
);
assert_true(
	is_array( $v1080 ) && ( $v1080['detected']['release']['release_group'] ?? null ) === 'tG1R0',
	'detected: tG1R0 remains release_group'
);
assert_true(
	is_array( $v1080 ) && ( $v1080['detected']['subtitles']['sidecar'] ?? 'x' ) === null,
	'detected: video has no sidecar association'
);
assert_true(
	is_array( $v1080 ) && $v1080['quality'] === '1080p' && ( $v1080['detected']['identity']['quality']['value'] ?? null ) === '1080p',
	'top-level quality still present and matches detected'
);

$jpg = find_file( $vscan['files'] ?? array(), 'cover.jpg' );
assert_true( is_array( $jpg ) && $jpg['kind'] === 'ignored', 'jpg ignored' );
assert_true( is_array( $jpg ) && ! array_key_exists( 'quality', $jpg ), 'jpg is not parsed' );
assert_true( is_array( $jpg ) && ! array_key_exists( 'probe', $jpg ), 'jpg has no probe' );
assert_true( is_array( $jpg ) && ! array_key_exists( 'validation', $jpg ), 'jpg has no validation' );
assert_true( is_array( $jpg ) && ! array_key_exists( 'detected', $jpg ), 'jpg has no detected' );

$sub = find_file( $vscan['files'] ?? array(), 'SUB' );
assert_true( is_array( $sub ) && $sub['kind'] === 'directory', 'SUB not recursed' );
assert_true( is_array( $sub ) && ! array_key_exists( 'probe', $sub ), 'directory has no probe' );
assert_true( is_array( $sub ) && ! array_key_exists( 'validation', $sub ), 'directory has no validation' );
assert_true( is_array( $sub ) && ! array_key_exists( 'detected', $sub ), 'directory has no detected' );
$top_codes = array_column( $vscan['warnings'] ?? array(), 'code' );
assert_true( in_array( 'unexpected_subdirectory', $top_codes, true ), 'lister subdirectory warning kept' );
assert_true( ! in_array( 'nested.srt', array_column( $vscan['files'] ?? array(), 'name' ), true ), 'no nested srt' );

echo "\nBeliever.2 (sidecar subtitle never probed)\n";
$probed = array();
$opts   = array( 'ffprobe_runner' => make_scan_ffprobe_runner( $probed ) );
$bscan  = media_scan_movie_dir( 'Movie/Korea/2023/Believer.2', $tmp, $movie_root, $opts );
assert_true( ( $bscan['ok'] ?? false ) === true, 'believer scan ok' );

$bmkv = find_file( $bscan['files'] ?? array(), $bel_mkv );
assert_true( is_array( $bmkv ) && $bmkv['provider'] === 'NF' && $bmkv['release_group'] === 'MARK', 'Believer mkv NF/MARK' );
assert_true( is_array( $bmkv ) && $bmkv['quality'] === '1080p', 'Believer quality' );
assert_true( is_array( $bmkv ) && isset( $bmkv['probe'] ) && ( $bmkv['probe']['ok'] ?? false ) === true, 'Believer video probed' );
assert_true( is_array( $bmkv ) && isset( $bmkv['detected'] ), 'Believer has detected' );
assert_true(
	is_array( $bmkv ) && ( $bmkv['detected']['release']['release_group'] ?? null ) === 'MARK',
	'detected: MARK remains release_group'
);
assert_true(
	is_array( $bmkv ) && ( $bmkv['detected']['subtitles']['sidecar'] ?? 'x' ) === null,
	'detected: Believer video has no sidecar association'
);

$bsrt = find_file( $bscan['files'] ?? array(), $bel_srt );
assert_true( is_array( $bsrt ) && $bsrt['kind'] === 'subtitle', 'srt kind from lister' );
assert_true( is_array( $bsrt ) && $bsrt['subtitle_lang'] === null, 'srt language unknown' );
assert_true( is_array( $bsrt ) && ( $bsrt['group_hint'] ?? null ) === 'AirenTeam', 'srt group_hint' );
assert_true( is_array( $bsrt ) && $bsrt['size_bytes'] === 50, 'srt size from lister' );
assert_true( is_array( $bsrt ) && ! array_key_exists( 'probe', $bsrt ), 'unknown-lang srt not probed' );
assert_true( is_array( $bsrt ) && ! array_key_exists( 'validation', $bsrt ), 'srt has no video validation' );
assert_true( is_array( $bsrt ) && isset( $bsrt['detected'] ) && ( $bsrt['detected']['kind'] ?? null ) === 'subtitle', 'srt has detected subtitle' );
assert_true(
	is_array( $bsrt ) && ( $bsrt['detected']['release']['group_hint'] ?? null ) === 'AirenTeam',
	'detected: AirenTeam remains group_hint'
);
assert_true( is_array( $bsrt ) && ( $bsrt['detected']['audio'] ?? 'x' ) === null, 'srt detected.audio=null' );
assert_true( is_array( $bsrt ) && ( $bsrt['detected']['video'] ?? 'x' ) === null, 'srt detected.video=null' );

$bfa = find_file( $bscan['files'] ?? array(), $bel_fa_srt );
assert_true( is_array( $bfa ) && $bfa['kind'] === 'subtitle', 'fa.srt kind=subtitle' );
assert_true( is_array( $bfa ) && ( $bfa['subtitle_lang'] ?? null ) === 'fa', 'fa.srt subtitle_lang=fa' );
assert_true( is_array( $bfa ) && ! array_key_exists( 'probe', $bfa ), 'fa.srt not probed' );
assert_true( is_array( $bfa ) && ! array_key_exists( 'validation', $bfa ), 'fa.srt has no video validation' );
assert_true( is_array( $bfa ) && isset( $bfa['detected'] ) && ( $bfa['detected']['kind'] ?? null ) === 'subtitle', 'fa.srt remains subtitle in detected' );
assert_true(
	is_array( $bfa ) && ( $bfa['detected']['subtitle']['language']['value'] ?? null ) === 'fa',
	'detected.subtitle.language from filename'
);
assert_true( is_array( $bfa ) && ( $bfa['detected']['audio'] ?? 'x' ) === null, 'fa.srt detected.audio=null' );
assert_true( is_array( $bfa ) && ( $bfa['detected']['video'] ?? 'x' ) === null, 'fa.srt detected.video=null' );
assert_true( ! in_array( $bel_srt, $probed, true ), 'AirenTeam.srt never sent to ffprobe' );
assert_true( ! in_array( $bel_fa_srt, $probed, true ), 'fa.srt never sent to ffprobe' );
assert_true( in_array( $bel_mkv, $probed, true ), 'Believer mkv was sent to ffprobe' );
assert_true( count( $probed ) === 1, 'only one Believer path probed (the video)' );

echo "\nProbe failure does not fail the movie scan\n";
$probed = array();
$opts   = array( 'ffprobe_runner' => make_scan_ffprobe_runner( $probed, $vapor_1080 ) );
$fail_scan = media_scan_movie_dir( 'Movie/Korea/2018/Vapor', $tmp, $movie_root, $opts );
assert_true( ( $fail_scan['ok'] ?? false ) === true, 'scan still ok when one probe fails' );

$fail_v1080 = find_file( $fail_scan['files'] ?? array(), $vapor_1080 );
assert_true( is_array( $fail_v1080 ) && ( $fail_v1080['probe']['ok'] ?? true ) === false, 'failed probe on video file' );
assert_true( is_array( $fail_v1080 ) && $fail_v1080['quality'] === '1080p', 'parser fields kept after probe failure' );
assert_true( is_array( $fail_v1080 ) && $fail_v1080['video_codec'] === 'H.264', 'video_codec kept after probe failure' );
$fail_codes = array_column( $fail_v1080['warnings'] ?? array(), 'code' );
assert_true( in_array( 'probe_failed', $fail_codes, true ), 'probe_failed file warning' );

$ok_v480 = find_file( $fail_scan['files'] ?? array(), $vapor_480 );
assert_true( is_array( $ok_v480 ) && ( $ok_v480['probe']['ok'] ?? false ) === true, 'other videos still probe ok' );
assert_true(
	is_array( $fail_v1080 ) && isset( $fail_v1080['validation'] ) && ( $fail_v1080['validation']['facts'] ?? null ) === array(),
	'failed probe → empty validation facts'
);
assert_true( is_array( $fail_v1080 ) && isset( $fail_v1080['detected'] ), 'failed probe still has detected' );
assert_true(
	is_array( $fail_v1080 ) && ( $fail_v1080['detected']['identity']['quality']['value'] ?? null ) === '1080p',
	'failed probe: quality still from filename'
);
assert_true(
	is_array( $fail_v1080 ) && ( $fail_v1080['detected']['video']['codec']['value'] ?? null ) === null,
	'failed probe: detected does not invent codec'
);
assert_true(
	is_array( $fail_v1080 ) && ( $fail_v1080['detected']['video']['width'] ?? null ) === null,
	'failed probe: detected does not invent width'
);
assert_true(
	is_array( $fail_v1080 ) && ( $fail_v1080['detected']['audio']['tracks'] ?? null ) === array(),
	'failed probe: detected does not invent audio tracks'
);
assert_true(
	is_array( $fail_v1080 ) && ( $fail_v1080['detected']['video']['probe_ok'] ?? true ) === false,
	'failed probe: detected.video.probe_ok=false'
);

echo "\nValidation reports codec mismatch without rewriting parser fields\n";
$probed = array();
$opts   = array( 'ffprobe_runner' => make_scan_ffprobe_runner( $probed, null, $vapor_1080 ) );
$mis    = media_scan_movie_dir( 'Movie/Korea/2018/Vapor', $tmp, $movie_root, $opts );
$mis1080 = find_file( $mis['files'] ?? array(), $vapor_1080 );
assert_true( ( $mis['ok'] ?? false ) === true, 'scan ok with codec mismatch' );
assert_true( is_array( $mis1080 ) && $mis1080['video_codec'] === 'H.264', 'parser video_codec still H.264' );
assert_true( is_array( $mis1080 ) && ( $mis1080['probe']['video']['codec'] ?? null ) === 'hevc', 'probe codec hevc' );
assert_true(
	is_array( $mis1080 ) && in_array( 'filename_probe_video_codec_mismatch', array_column( $mis1080['validation']['warnings'] ?? array(), 'code' ), true ),
	'validation reports codec mismatch'
);
assert_true(
	is_array( $mis1080 ) && in_array(
		'filename_probe_video_codec_mismatch',
		array_column( $mis1080['detected']['validation']['warnings'] ?? array(), 'code' ),
		true
	),
	'detected.validation also reports codec mismatch'
);
assert_true(
	is_array( $mis1080 ) && ( $mis1080['detected']['video']['codec_filename']['value'] ?? null ) === 'H.264',
	'detected keeps filename codec after mismatch'
);
assert_true(
	is_array( $mis1080 ) && ( $mis1080['detected']['video']['codec']['value'] ?? null ) === 'hevc',
	'detected keeps ffprobe codec after mismatch'
);

echo "\nNormalizer runs after enrichment (detected reflects final probe+validation)\n";
assert_true(
	is_array( $mis1080 )
		&& isset( $mis1080['probe'], $mis1080['validation'], $mis1080['detected'] )
		&& ( $mis1080['detected']['video']['codec']['value'] ?? null ) === ( $mis1080['probe']['video']['codec'] ?? null ),
	'detected codec matches final probe codec'
);

$cleanup();

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

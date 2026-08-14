<?php
/**
 * CLI tests for media_normalize_detected_file().
 *
 * Run: php media-server/tests/media-detected-file-test.php
 *
 * Pure: uses filename parser + validation helpers only.
 * No filesystem, ffprobe binary, HTTP, WordPress, or Streamit.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/filename-parser.php';
require_once dirname( __DIR__ ) . '/lib/media-validation.php';
require_once dirname( __DIR__ ) . '/lib/media-detected-file.php';

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

/**
 * Build an enriched scan-shaped video file from a real fixture basename + probe.
 *
 * @param array<string, mixed> $probe
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function enrich_video( string $basename, string $directory, array $probe, array $extra = array() ): array {
	$parsed = media_parse_filename( $basename );
	$file   = array_merge(
		array(
			'name'       => $basename,
			'media_path' => $directory . '/' . $basename,
			'extension'  => strtolower( (string) pathinfo( $basename, PATHINFO_EXTENSION ) ),
			'kind'       => 'video',
			'size_bytes' => 1000,
			'size_label' => '1000 B',
		),
		$extra
	);

	$skip = array( 'kind' => true, 'input' => true, 'format' => true );
	foreach ( $parsed as $key => $value ) {
		if ( isset( $skip[ $key ] ) || str_starts_with( (string) $key, '_' ) ) {
			continue;
		}
		$file[ $key ] = $value;
	}

	$file['probe'] = $probe;
	$file['validation'] = media_validate_video_file( $file );

	return $file;
}

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function enrich_subtitle( string $basename, string $directory, array $extra = array() ): array {
	$parsed = media_parse_filename( $basename );
	$file   = array_merge(
		array(
			'name'       => $basename,
			'media_path' => $directory . '/' . $basename,
			'extension'  => strtolower( (string) pathinfo( $basename, PATHINFO_EXTENSION ) ),
			'kind'       => 'subtitle',
			'size_bytes' => 50,
			'size_label' => '50 B',
		),
		$extra
	);

	$skip = array( 'kind' => true, 'input' => true, 'format' => true );
	foreach ( $parsed as $key => $value ) {
		if ( isset( $skip[ $key ] ) || str_starts_with( (string) $key, '_' ) ) {
			continue;
		}
		$file[ $key ] = $value;
	}

	return $file;
}

/**
 * @param list<array{language?: string|null, codec?: string|null, channels?: int|null}> $audio
 * @param list<array{language?: string|null, codec?: string|null}> $subs
 * @return array<string, mixed>
 */
function ok_probe( string $codec, int $width, int $height, array $audio = array(), array $subs = array(), int $duration = 6152 ): array {
	return array(
		'ok'        => true,
		'duration'  => $duration,
		'video'     => array(
			'codec'  => $codec,
			'width'  => $width,
			'height' => $height,
		),
		'audio'     => $audio,
		'subtitles' => $subs,
	);
}

$dir_soul    = 'Movie/Korea/2018/The.Soul.Mate';
$dir_vapor   = 'Movie/Korea/2018/Vapor';
$dir_believe = 'Movie/Korea/2023/Believer.2';

$soul_1080  = 'The.Soul.Mate.1080p.WEB-DL.mkv';
$soul_720   = 'The.Soul.Mate.2018.WEB-DL.720p.SS.mkv';
$vapor_480  = 'Vapor.2018.480p.WAVVE.WEB-DL.mkv';
$vapor_1080 = 'Vapor.2018.1080p.WAVVE.WEB-DL.AAC2.0.H.264-tG1R0.mkv';
$bel_mkv    = 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.mkv';
$bel_srt    = 'Believer.2.NF.AirenTeam.srt';
$bel_fa_srt = 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.fa.srt';

echo "Believer.2 video contract\n";
$bel_raw = enrich_video(
	$bel_mkv,
	$dir_believe,
	ok_probe(
		'h264',
		1920,
		1080,
		array(
			array( 'language' => 'fa', 'codec' => 'aac', 'channels' => 2 ),
		)
	)
);
$before = $bel_raw;
$bel    = media_normalize_detected_file( $bel_raw );
assert_true( $bel_raw === $before, 'input not mutated' );
assert_true( ( $bel['kind'] ?? null ) === 'video', 'kind=video' );
assert_true( ( $bel['identity']['quality']['value'] ?? null ) === '1080p', 'quality from filename' );
assert_true( ( $bel['identity']['quality']['source'] ?? null ) === 'filename', 'quality source=filename' );
assert_true( ( $bel['identity']['provider']['value'] ?? null ) === 'NF', 'provider NF' );
assert_true( ( $bel['identity']['source_type']['value'] ?? null ) === 'WEB-DL', 'source_type WEB-DL' );
assert_true( ( $bel['video']['codec']['value'] ?? null ) === 'h264', 'ffprobe codec factual' );
assert_true( ( $bel['video']['codec']['source'] ?? null ) === 'ffprobe', 'codec source=ffprobe' );
assert_true( ( $bel['video']['codec_filename']['value'] ?? null ) === 'H.264', 'filename codec preserved' );
assert_true( ( $bel['video']['width'] ?? null ) === 1920 && ( $bel['video']['height'] ?? null ) === 1080, 'dimensions from probe' );
assert_true( ( $bel['video']['duration'] ?? null ) === 6152, 'duration from probe' );
assert_true( ( $bel['audio']['tracks'][0]['language'] ?? null ) === 'fa', 'audio language from probe tracks' );
assert_true( ( $bel['audio']['confidence'] ?? null ) === 'unknown', 'unknown audio remains unknown' );
assert_true( ( $bel['audio']['label'] ?? null ) === null, 'no invented audio label' );
assert_true( ( $bel['release']['release_group'] ?? null ) === 'MARK', 'MARK is release_group' );
assert_true( ( $bel['release']['encoder'] ?? null ) === null, 'MARK is not encoder' );
assert_true( ( $bel['subtitles']['sidecar'] ?? 'x' ) === null, 'no sidecar association on video' );
assert_true( isset( $bel['validation'] ) && is_array( $bel['validation'] ), 'validation preserved' );
assert_true( ( $bel['hints']['year'] ?? null ) === 2023, 'year hint from filename' );

echo "\nFilename quality not replaced by probe resolution\n";
$mismatch = enrich_video(
	$soul_1080,
	$dir_soul,
	ok_probe( 'h264', 1280, 720 )
);
$n = media_normalize_detected_file( $mismatch );
assert_true( ( $n['identity']['quality']['value'] ?? null ) === '1080p', 'quality stays 1080p' );
assert_true( ( $n['video']['width'] ?? null ) === 1280 && ( $n['video']['height'] ?? null ) === 720, 'probe dims separate' );
assert_true(
	( $n['validation']['facts']['probe_resolution']['quality_class'] ?? null ) === '720p',
	'validation fact has probe class 720p'
);
assert_true(
	in_array( 'filename_probe_resolution_mismatch', array_column( $n['validation']['warnings'], 'code' ), true ),
	'resolution mismatch stays in validation'
);

echo "\nVapor 1080p: filename codec + release_group + audio_codec hint\n";
$vapor = media_normalize_detected_file(
	enrich_video(
		$vapor_1080,
		$dir_vapor,
		ok_probe(
			'h264',
			1920,
			1080,
			array(
				array( 'language' => 'fa', 'codec' => 'aac', 'channels' => 2 ),
				array( 'language' => 'en', 'codec' => 'aac', 'channels' => 2 ),
			),
			array(
				array( 'language' => 'ko', 'codec' => 'subrip' ),
			)
		)
	)
);
assert_true( ( $vapor['video']['codec_filename']['value'] ?? null ) === 'H.264', 'filename H.264 available' );
assert_true( ( $vapor['video']['codec']['value'] ?? null ) === 'h264', 'probe h264 factual' );
assert_true( ( $vapor['audio']['codec_filename'] ?? null ) === 'AAC2.0', 'filename audio_codec preserved' );
assert_true( ( $vapor['release']['release_group'] ?? null ) === 'tG1R0', 'tG1R0 release_group' );
assert_true( ( $vapor['release']['encoder'] ?? null ) === null, 'tG1R0 not encoder' );
assert_true( ( $vapor['identity']['provider']['value'] ?? null ) === 'WAVVE', 'WAVVE provider' );
assert_true( count( $vapor['audio']['tracks'] ) === 2, 'both probe audio tracks kept' );
assert_true( count( $vapor['subtitles']['embedded'] ) === 1, 'embedded probe subtitle kept' );
assert_true( ( $vapor['subtitles']['embedded'][0]['language'] ?? null ) === 'ko', 'embedded sub lang from probe' );
assert_true( ( $vapor['audio']['tracks'][0]['language'] ?? null ) !== 'ko', 'directory Korea does not become audio' );

echo "\nSS remains unclassified\n";
$ss = media_normalize_detected_file(
	enrich_video( $soul_720, $dir_soul, ok_probe( 'h264', 1280, 720 ) )
);
assert_true( ( $ss['identity']['quality']['value'] ?? null ) === '720p', '720p quality' );
assert_true( in_array( 'SS', $ss['release']['unclassified'] ?? array(), true ), 'SS unclassified' );
assert_true( ( $ss['release']['encoder'] ?? null ) === null, 'SS not encoder' );

echo "\nVapor 480p WAVVE\n";
$v480 = media_normalize_detected_file(
	enrich_video( $vapor_480, $dir_vapor, ok_probe( 'h264', 854, 480 ) )
);
assert_true( ( $v480['identity']['quality']['value'] ?? null ) === '480p', '480p filename quality' );
assert_true( ( $v480['identity']['provider']['value'] ?? null ) === 'WAVVE', 'WAVVE' );
assert_true( ( $v480['audio']['confidence'] ?? null ) === 'unknown', 'audio still unknown' );

echo "\nMissing probe does not invent values\n";
$noprobe = enrich_video(
	$soul_1080,
	$dir_soul,
	array(
		'ok'        => false,
		'code'      => 'ffprobe_failed',
		'message'   => 'failed',
		'duration'  => null,
		'video'     => null,
		'audio'     => array(),
		'subtitles' => array(),
	)
);
$np = media_normalize_detected_file( $noprobe );
assert_true( ( $np['identity']['quality']['value'] ?? null ) === '1080p', 'quality still from filename' );
assert_true( ( $np['video']['codec']['value'] ?? null ) === null, 'no invented probe codec' );
assert_true( ( $np['video']['width'] ?? null ) === null && ( $np['video']['height'] ?? null ) === null, 'no invented dims' );
assert_true( ( $np['video']['duration'] ?? null ) === null, 'no invented duration' );
assert_true( ( $np['video']['probe_ok'] ?? true ) === false, 'probe_ok false' );
assert_true( ( $np['audio']['tracks'] ?? null ) === array(), 'no invented audio tracks' );
assert_true( ( $np['video']['codec_filename']['value'] ?? null ) === null, 'no filename codec on this fixture' );

echo "\nAirenTeam.srt — subtitle, group_hint, not encoder, no audio\n";
$srt_raw = enrich_subtitle( $bel_srt, $dir_believe );
$srt     = media_normalize_detected_file( $srt_raw );
assert_true( ( $srt['kind'] ?? null ) === 'subtitle', 'kind=subtitle' );
assert_true( ( $srt['subtitle']['language']['value'] ?? null ) === null, 'AirenTeam.srt language unknown' );
assert_true( ( $srt['release']['group_hint'] ?? null ) === 'AirenTeam', 'AirenTeam is group_hint' );
assert_true( ( $srt['release']['encoder'] ?? null ) === null, 'AirenTeam not encoder' );
assert_true( ( $srt['audio'] ?? 'x' ) === null, 'subtitle has no audio block' );
assert_true( ( $srt['video'] ?? 'x' ) === null, 'subtitle has no video association' );
assert_true( ! isset( $srt['subtitles']['sidecar'] ), 'subtitle is not nested under a video sidecar key' );

echo "\n.fa.srt remains subtitle and never becomes audio\n";
$fa = media_normalize_detected_file( enrich_subtitle( $bel_fa_srt, $dir_believe ) );
assert_true( ( $fa['kind'] ?? null ) === 'subtitle', 'fa.srt kind=subtitle' );
assert_true( ( $fa['subtitle']['language']['value'] ?? null ) === 'fa', 'fa.srt language=fa' );
assert_true( ( $fa['subtitle']['language']['source'] ?? null ) === 'filename', 'subtitle lang source=filename' );
assert_true( ( $fa['subtitle']['confidence'] ?? null ) === 'high', 'subtitle confidence high' );
assert_true( ( $fa['subtitle']['format'] ?? null ) === 'srt', 'format srt' );
assert_true( ( $fa['audio'] ?? 'x' ) === null, 'fa.srt does not create audio' );

echo "\nund language preserved as tag, not remapped\n";
$und = media_normalize_detected_file(
	enrich_video(
		$soul_1080,
		$dir_soul,
		ok_probe(
			'h264',
			1920,
			1080,
			array(
				array( 'language' => 'und', 'codec' => 'aac', 'channels' => 2 ),
			)
		)
	)
);
assert_true( ( $und['audio']['tracks'][0]['language'] ?? null ) === 'und', 'und kept literal' );
assert_true( ( $und['audio']['label'] ?? null ) === null, 'und does not invent English/Persian label' );

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

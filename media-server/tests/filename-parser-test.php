<?php
/**
 * CLI tests for media_parse_filename().
 *
 * Run: php media-server/tests/filename-parser-test.php
 * Dump: php media-server/tests/filename-parser-test.php --dump
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/filename-parser.php';

$failures = 0;
$dump     = in_array( '--dump', $argv, true );

function assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
}

function warning_codes( array $parsed ): array {
	return array_values( array_column( $parsed['warnings'], 'code' ) );
}

$real = array(
	'data/Movie/Korea/2018/The.Soul.Mate/The.Soul.Mate.1080p.WEB-DL.mkv',
	'data/Movie/Korea/2018/The.Soul.Mate/The.Soul.Mate.2018.WEB-DL.720p.SS.mkv',
	'data/Movie/Korea/2018/Vapor/Vapor.2018.480p.WAVVE.WEB-DL.mkv',
	'data/Movie/Korea/2018/Vapor/Vapor.2018.1080p.WAVVE.WEB-DL.AAC2.0.H.264-tG1R0.mkv',
	'data/Movie/Korea/2023/Believer.2/Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.mkv',
	'data/Movie/Korea/2023/Believer.2/Believer.2.NF.AirenTeam.srt',
	'data/Movie/Korea/2023/Believer.2/Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.mkv',
);

echo "real library fixtures\n";

$a = media_parse_filename( $real[0] );
assert_true( $a['kind'] === 'video', '1 kind video' );
assert_true( $a['quality'] === '1080p', '1 quality 1080p' );
assert_true( $a['source_type'] === 'WEB-DL', '1 source WEB-DL' );
assert_true( $a['provider'] === null, '1 provider null' );
assert_true( $a['video_codec'] === null, '1 video_codec null' );
assert_true( $a['audio_codec'] === null, '1 audio_codec null' );
assert_true( $a['release_group'] === null, '1 release_group null' );
assert_true( $a['encoder'] === null, '1 encoder null' );
assert_true( $a['audio_label'] === null && $a['audio_confidence'] === 'unknown', '1 audio unknown' );
assert_true( $a['audio_languages'] === array(), '1 audio_languages empty' );
assert_true( $a['subtitle_lang'] === null, '1 no subtitle lang on video' );

$b = media_parse_filename( $real[1] );
assert_true( $b['quality'] === '720p', '2 quality 720p (after WEB-DL)' );
assert_true( $b['source_type'] === 'WEB-DL', '2 source WEB-DL' );
assert_true( $b['encoder'] === null, '2 SS is not encoder' );
assert_true( $b['release_group'] === null, '2 SS is not release_group' );
assert_true( in_array( 'SS', $b['unclassified'], true ), '2 SS unclassified' );
assert_true( $b['audio_confidence'] === 'unknown', '2 audio unknown' );
assert_true( $b['year_hint'] === 2018, '2 year_hint 2018' );

$c = media_parse_filename( $real[2] );
assert_true( $c['quality'] === '480p', '3 quality 480p' );
assert_true( $c['provider'] === 'WAVVE', '3 provider WAVVE' );
assert_true( $c['source_type'] === 'WEB-DL', '3 source WEB-DL' );
assert_true( $c['audio_label'] === null && $c['audio_confidence'] === 'unknown', '3 audio unknown' );
assert_true( $c['encoder'] === null, '3 encoder null' );

$d = media_parse_filename( $real[3] );
assert_true( $d['quality'] === '1080p', '4 quality 1080p' );
assert_true( $d['provider'] === 'WAVVE', '4 provider WAVVE' );
assert_true( $d['source_type'] === 'WEB-DL', '4 source WEB-DL' );
assert_true( $d['audio_codec'] === 'AAC2.0', '4 audio_codec AAC2.0' );
assert_true( $d['video_codec'] === 'H.264', '4 video_codec H.264' );
assert_true( $d['release_group'] === 'tG1R0', '4 release_group tG1R0' );
assert_true( $d['encoder'] === null, '4 encoder null' );
assert_true( $d['audio_confidence'] === 'unknown', '4 audio language unknown' );

$e = media_parse_filename( $real[4] );
assert_true( $e['quality'] === '1080p', '5 quality 1080p' );
assert_true( $e['provider'] === 'NF', '5 provider NF' );
assert_true( $e['source_type'] === 'WEB-DL', '5 source WEB-DL' );
assert_true( $e['video_codec'] === 'H.264', '5 video_codec H.264' );
assert_true( $e['release_group'] === 'MARK', '5 release_group MARK' );
assert_true( $e['encoder'] === null, '5 encoder null' );
assert_true( $e['audio_confidence'] === 'unknown', '5 audio unknown' );
assert_true( $e['title_hint'] === 'Believer.2', '5 title_hint Believer.2' );

$f = media_parse_filename( $real[5] );
assert_true( $f['kind'] === 'subtitle', '6 kind subtitle' );
assert_true( $f['format'] === 'srt', '6 format srt' );
assert_true( $f['subtitle_lang'] === null, '6 subtitle_lang unknown' );
assert_true( $f['subtitle_confidence'] === 'unknown', '6 subtitle_confidence unknown' );
assert_true( $f['provider'] === 'NF', '6 provider NF' );
assert_true( $f['group_hint'] === 'AirenTeam', '6 group_hint AirenTeam' );
assert_true( $f['encoder'] === null, '6 encoder null' );
assert_true( in_array( 'subtitle_lang_unknown', warning_codes( $f ), true ), '6 warning subtitle_lang_unknown' );

$g = media_parse_filename( $real[6] );
assert_true( $g['release_group'] === $e['release_group'] && $g['quality'] === '1080p', '7 same as 5' );

echo "\ncountry path must not imply audio\n";
$korea = media_parse_filename( $real[0] );
assert_true( $korea['audio_label'] === null, 'Korea path does not set Korean audio' );
assert_true( $korea['audio_languages'] === array(), 'Korea path does not set audio_languages' );

echo "\nexplicit audio / subtitle tokens\n";
$dub = media_parse_filename( 'Movie.2025.1080p.WEB-DL.Persian.Dub.mkv' );
assert_true( $dub['audio_label'] === 'Persian Dub' && $dub['audio_confidence'] === 'high', 'Persian.Dub' );
assert_true( $dub['audio_languages'] === array( 'fa' ), 'Persian.Dub languages fa' );

$dual = media_parse_filename( 'Movie.2025.1080p.WEB-DL.Dual.Audio.mkv' );
assert_true( $dual['audio_label'] === 'Dual Audio' && $dual['audio_confidence'] === 'high', 'Dual.Audio' );

$fa = media_parse_filename( 'Movie.2025.1080p.WEB-DL.fa.srt' );
assert_true( $fa['kind'] === 'subtitle' && $fa['subtitle_lang'] === 'fa', 'fa.srt → fa' );
assert_true( $fa['quality'] === '1080p' && $fa['source_type'] === 'WEB-DL', 'fa.srt keeps quality/source' );
assert_true( $fa['audio_confidence'] === 'unknown', 'subtitle file does not set audio' );

$en = media_parse_filename( 'Movie.2025.1080p.WEB-DL.en.srt' );
assert_true( $en['subtitle_lang'] === 'en', 'en.srt → en' );

echo "\nquality priority and encoder allowlist\n";
$hd = media_parse_filename( 'Movie.2025.1080p.HD.WEB-DL.mkv' );
assert_true( $hd['quality'] === '1080p', '1080p wins over HD' );

$yify = media_parse_filename( 'Movie.2025.1080p.WEB-DL.YIFY.mkv' );
assert_true( $yify['encoder'] === 'YIFY', 'YIFY is a known encoder' );

$fhd = media_parse_filename( 'Movie.2025.FHD.BluRay.mkv' );
assert_true( $fhd['quality'] === '1080p' && $fhd['source_type'] === 'BluRay', 'FHD + BluRay' );

echo "\nDecision.to.Leave leftovers: KNPSK hint vs SS unclassified\n";
$d1080 = media_parse_filename( 'Decision.to.Leave.2022.1080p.KNPSK.WEB-DL.DDP5.1.x264-tG1R0.mkv' );
assert_true( $d1080['quality'] === '1080p', 'DTL 1080 quality' );
assert_true( $d1080['source_type'] === 'WEB-DL', 'DTL 1080 source' );
assert_true( $d1080['release_group'] === 'tG1R0', 'DTL trailing -tG1R0 is release_group' );
assert_true( $d1080['group_hint'] === 'KNPSK', 'DTL KNPSK is group_hint' );
assert_true( $d1080['encoder'] === null, 'DTL encoder null' );
assert_true( ! in_array( 'KNPSK', $d1080['unclassified'], true ), 'DTL KNPSK not also unclassified' );
assert_true( in_array( 'unconfirmed_group', warning_codes( $d1080 ), true ), 'DTL unconfirmed_group once' );

$d480ss = media_parse_filename( 'Decision.to.Leave.2022.480p.KNPSK.WEB-DL.SS.mkv' );
assert_true( $d480ss['group_hint'] === 'KNPSK', 'DTL SS: KNPSK still group_hint' );
assert_true( in_array( 'SS', $d480ss['unclassified'], true ), 'DTL SS: SS unclassified' );
assert_true( ! in_array( 'KNPSK', $d480ss['unclassified'], true ), 'DTL SS: KNPSK not unclassified' );
assert_true( $d480ss['encoder'] === null && $d480ss['release_group'] === null, 'DTL SS: no encoder/group' );
assert_true( in_array( 'unconfirmed_group', warning_codes( $d480ss ), true ), 'DTL SS: unconfirmed_group' );
assert_true( in_array( 'unclassified_tokens', warning_codes( $d480ss ), true ), 'DTL SS: unclassified_tokens for SS' );

$dsrt = media_parse_filename( 'Decision.to.Leave.2022.WEB-DL.srt' );
assert_true( $dsrt['kind'] === 'subtitle', 'DTL srt kind' );
assert_true( $dsrt['subtitle_lang'] === null, 'DTL srt lang null' );
assert_true( in_array( 'subtitle_lang_unknown', warning_codes( $dsrt ), true ), 'DTL srt warning' );

if ( $dump ) {
	echo "\n--- dump (7 real filenames) ---\n";
	foreach ( $real as $i => $path ) {
		$parsed = media_parse_filename( $path );
		echo "\n#" . ( $i + 1 ) . ' ' . basename( $path ) . "\n";
		echo json_encode( $parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
	}
}

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

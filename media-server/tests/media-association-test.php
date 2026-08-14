<?php
/**
 * CLI tests for media_associate_movie_files().
 *
 * Run: php media-server/tests/media-association-test.php
 *
 * Pure: uses filename parser + normalizer only.
 * No filesystem, ffprobe binary, HTTP, WordPress, or Streamit.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/filename-parser.php';
require_once dirname( __DIR__ ) . '/lib/media-validation.php';
require_once dirname( __DIR__ ) . '/lib/media-detected-file.php';
require_once dirname( __DIR__ ) . '/lib/media-association.php';

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
 * Build a scan-shaped video/subtitle entry with `detected`, without probing.
 *
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function assoc_file( string $kind, string $basename, string $directory, array $extra = array() ): array {
	$parsed = media_parse_filename( $basename );
	$file   = array_merge(
		array(
			'name'       => $basename,
			'media_path' => $directory . '/' . $basename,
			'extension'  => strtolower( (string) pathinfo( $basename, PATHINFO_EXTENSION ) ),
			'kind'       => $kind,
			'size_bytes' => $kind === 'video' ? 1000 : 50,
			'size_label' => $kind === 'video' ? '1000 B' : '50 B',
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

	if ( $kind === 'video' ) {
		$file['probe'] = array(
			'ok'        => true,
			'duration'  => 100,
			'video'     => array(
				'codec'  => 'h264',
				'width'  => 1920,
				'height' => 1080,
			),
			'audio'     => array(
				array( 'language' => 'en', 'codec' => 'aac', 'channels' => 2 ),
			),
			'subtitles' => array(),
		);
		$file['validation'] = media_validate_video_file( $file );
	}

	$file['detected'] = media_normalize_detected_file( $file );
	return $file;
}

function find_assoc( array $associations, string $subtitle_path ): ?array {
	foreach ( $associations as $row ) {
		if ( ( $row['subtitle'] ?? '' ) === $subtitle_path ) {
			return $row;
		}
	}
	return null;
}

$dir = 'Movie/Test/2025/Sample';

echo "exact 1080p stem match\n";
$v1 = assoc_file( 'video', 'Movie.2025.1080p.WEB-DL.mkv', $dir );
$s1 = assoc_file( 'subtitle', 'Movie.2025.1080p.WEB-DL.fa.srt', $dir );
$before = array( $v1, $s1 );
$result = media_associate_movie_files( $before );
assert_true( $before[0] === $v1 && $before[1] === $s1, 'input elements not mutated' );
assert_true( count( $result['associations'] ) === 1, 'one association' );
$a = find_assoc( $result['associations'], $s1['media_path'] );
assert_true( is_array( $a ) && ( $a['video'] ?? null ) === $v1['media_path'], 'associated to matching video' );
assert_true( is_array( $a ) && ( $a['confidence'] ?? null ) === 'high', 'confidence high' );
assert_true( is_array( $a ) && ( $a['reason'] ?? null ) === 'exact_stem', 'reason exact_stem' );
assert_true( $result['unassociated_subtitles'] === array(), 'no unassociated' );
assert_true( ( $s1['detected']['kind'] ?? null ) === 'subtitle', '.fa.srt remains subtitle' );
assert_true( ( $s1['detected']['audio'] ?? 'x' ) === null, '.fa.srt is not audio' );
assert_true( ( $s1['detected']['subtitle']['language']['value'] ?? null ) === 'fa', 'fa language on subtitle' );

echo "\ndifferent quality → no match\n";
$v720 = assoc_file( 'video', 'Movie.2025.720p.WEB-DL.mkv', $dir );
$s1080 = assoc_file( 'subtitle', 'Movie.2025.1080p.WEB-DL.fa.srt', $dir );
$r = media_associate_movie_files( array( $v720, $s1080 ) );
assert_true( $r['associations'] === array(), 'quality mismatch not associated' );
assert_true( $r['unassociated_subtitles'] === array( $s1080['media_path'] ), 'subtitle unassociated' );

echo "\ndifferent provider → no match\n";
$v_nf = assoc_file( 'video', 'Movie.2025.1080p.WEB-DL.NF.mkv', $dir );
$s_wv = assoc_file( 'subtitle', 'Movie.2025.1080p.WEB-DL.WAVVE.fa.srt', $dir );
$r = media_associate_movie_files( array( $v_nf, $s_wv ) );
assert_true( $r['associations'] === array(), 'provider mismatch not associated' );

echo "\nexact release/version match with provider\n";
$v_nf2 = assoc_file( 'video', 'Movie.2025.1080p.WEB-DL.NF.mkv', $dir );
$s_nf  = assoc_file( 'subtitle', 'Movie.2025.1080p.WEB-DL.NF.fa.srt', $dir );
$r = media_associate_movie_files( array( $v_nf2, $s_nf ) );
assert_true( count( $r['associations'] ) === 1, 'NF version associated' );
assert_true( ( $r['associations'][0]['video'] ?? null ) === $v_nf2['media_path'], 'NF video path' );

echo "\nambiguous short subtitle stem → unassociated\n";
$v_full = assoc_file( 'video', 'Movie.2025.1080p.WEB-DL.mkv', $dir );
$s_short = assoc_file( 'subtitle', 'Movie.2025.fa.srt', $dir );
$r = media_associate_movie_files( array( $v_full, $s_short ) );
assert_true( $r['associations'] === array(), 'short stem not associated' );
assert_true( in_array( $s_short['media_path'], $r['unassociated_subtitles'], true ), 'short stem unassociated' );

echo "\nambiguous two-video case → no arbitrary choice\n";
$va = assoc_file( 'video', 'Movie.2025.1080p.WEB-DL.A.mkv', $dir );
$vb = assoc_file( 'video', 'Movie.2025.1080p.WEB-DL.B.mkv', $dir );
$s_amb = assoc_file( 'subtitle', 'Movie.2025.1080p.WEB-DL.fa.srt', $dir );
$r = media_associate_movie_files( array( $va, $vb, $s_amb ) );
assert_true( $r['associations'] === array(), 'ambiguous not associated' );
assert_true( in_array( $s_amb['media_path'], $r['unassociated_subtitles'], true ), 'ambiguous listed unassociated' );

// Two videos with identical stems (mkv + mp4) matching one subtitle.
$v_mkv = assoc_file( 'video', 'Movie.2025.1080p.WEB-DL.mkv', $dir );
$v_mp4 = assoc_file( 'video', 'Movie.2025.1080p.WEB-DL.mp4', $dir );
$s_both = assoc_file( 'subtitle', 'Movie.2025.1080p.WEB-DL.fa.srt', $dir );
$r = media_associate_movie_files( array( $v_mkv, $v_mp4, $s_both ) );
assert_true( $r['associations'] === array(), 'identical stems across two videos → no pick' );
assert_true(
	count( $r['warnings'] ) === 1 && ( $r['warnings'][0]['code'] ?? '' ) === 'ambiguous_subtitle_match',
	'ambiguous_subtitle_match warning'
);

echo "\n.en.srt does not become audio\n";
$v_en = assoc_file( 'video', 'Movie.2025.1080p.WEB-DL.mkv', $dir );
$s_en = assoc_file( 'subtitle', 'Movie.2025.1080p.WEB-DL.en.srt', $dir );
$r = media_associate_movie_files( array( $v_en, $s_en ) );
assert_true( count( $r['associations'] ) === 1, 'en.srt associates by stem' );
assert_true( ( $s_en['detected']['kind'] ?? null ) === 'subtitle', 'en.srt remains subtitle' );
assert_true( ( $s_en['detected']['audio'] ?? 'x' ) === null, 'en.srt is not audio' );
assert_true( ( $s_en['detected']['subtitle']['language']['value'] ?? null ) === 'en', 'en language' );

echo "\nmissing subtitle language remains unknown; AirenTeam not associated by directory\n";
$bel_dir = 'Movie/Korea/2023/Believer.2';
$bel_v = assoc_file( 'video', 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.mkv', $bel_dir );
$bel_s = assoc_file( 'subtitle', 'Believer.2.NF.AirenTeam.srt', $bel_dir );
$r = media_associate_movie_files( array( $bel_v, $bel_s ) );
assert_true( $r['associations'] === array(), 'AirenTeam.srt not forced by directory' );
assert_true( ( $bel_s['subtitle_lang'] ?? null ) === null, 'subtitle language unknown' );
assert_true( ( $bel_s['group_hint'] ?? null ) === 'AirenTeam', 'AirenTeam group_hint preserved on file' );
assert_true( ( $bel_s['detected']['release']['group_hint'] ?? null ) === 'AirenTeam', 'group_hint in detected' );

echo "\nBeliever exact MARK stem + fa.srt associates\n";
$bel_fa = assoc_file( 'subtitle', 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.fa.srt', $bel_dir );
$r = media_associate_movie_files( array( $bel_v, $bel_s, $bel_fa ) );
assert_true( count( $r['associations'] ) === 1, 'only fa.srt associates' );
assert_true( ( $r['associations'][0]['subtitle'] ?? null ) === $bel_fa['media_path'], 'fa.srt associated' );
assert_true( ( $r['associations'][0]['video'] ?? null ) === $bel_v['media_path'], 'to Believer video' );
assert_true( in_array( $bel_s['media_path'], $r['unassociated_subtitles'], true ), 'AirenTeam still unassociated' );
assert_true( ( $bel_v['detected']['release']['release_group'] ?? null ) === 'MARK', 'MARK remains release_group' );

echo "\nmultiple subtitles can associate to the same video\n";
$v_m = assoc_file( 'video', 'Movie.2025.1080p.WEB-DL.mkv', $dir );
$s_fa = assoc_file( 'subtitle', 'Movie.2025.1080p.WEB-DL.fa.srt', $dir );
$s_en2 = assoc_file( 'subtitle', 'Movie.2025.1080p.WEB-DL.en.srt', $dir );
$r = media_associate_movie_files( array( $v_m, $s_fa, $s_en2 ) );
assert_true( count( $r['associations'] ) === 2, 'two associations' );
assert_true( ( $r['associations'][0]['video'] ?? null ) === $v_m['media_path'], 'first → same video' );
assert_true( ( $r['associations'][1]['video'] ?? null ) === $v_m['media_path'], 'second → same video' );
assert_true( $r['unassociated_subtitles'] === array(), 'none unassociated' );

echo "\nVapor / Soul fixtures: tG1R0 release_group; SS unclassified; no false assoc\n";
$vapor_dir = 'Movie/Korea/2018/Vapor';
$vapor_v = assoc_file( 'video', 'Vapor.2018.1080p.WAVVE.WEB-DL.AAC2.0.H.264-tG1R0.mkv', $vapor_dir );
assert_true( ( $vapor_v['release_group'] ?? null ) === 'tG1R0', 'tG1R0 release_group' );
$soul_dir = 'Movie/Korea/2018/The.Soul.Mate';
$soul_v = assoc_file( 'video', 'The.Soul.Mate.2018.WEB-DL.720p.SS.mkv', $soul_dir );
assert_true( in_array( 'SS', $soul_v['unclassified'] ?? array(), true ), 'SS unclassified' );
$r = media_associate_movie_files( array( $vapor_v, $soul_v, $bel_fa ) );
assert_true( $r['associations'] === array(), 'Believer fa.srt does not attach to Vapor/Soul' );

echo "\nfiles list returned unchanged (same references)\n";
$input = array( $v1, $s1 );
$out   = media_associate_movie_files( $input );
assert_true( $out['files'] === $input, 'files key is original list' );

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

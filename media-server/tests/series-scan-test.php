<?php
/**
 * CLI tests for media_scan_series_dir().
 *
 * Run: php media-server/tests/series-scan-test.php
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/series-scan.php';
require_once dirname( __DIR__ ) . '/lib/scan-auth.php';

$failures = 0;

function series_scan_assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function series_scan_find_file( array $files, string $name ): ?array {
	foreach ( $files as $file ) {
		if ( ( $file['name'] ?? '' ) === $name ) {
			return $file;
		}
	}
	return null;
}

function series_scan_ffprobe_runner(): callable {
	return static function ( array $argv ): array {
		return array(
			'exit'   => 0,
			'stdout' => json_encode(
				array(
					'format'  => array( 'duration' => '1200.0' ),
					'streams' => array(
						array(
							'codec_type' => 'video',
							'codec_name' => 'hevc',
							'width'      => 1280,
							'height'     => 720,
						),
					),
				)
			),
			'stderr' => '',
		);
	};
}

$tmp = sys_get_temp_dir() . '/series-scan-test-' . bin2hex( random_bytes( 4 ) );
$series_root = $tmp . '/series';
$show_dir    = $series_root . '/korea/2024/I.Really.Really.Like.You';
$dirs = array(
	$show_dir . '/720p x265 WEB-DL',
	$show_dir . '/1080p WEB-DL',
	$show_dir . '/540p WEB-DL',
	$show_dir . '/SUB.ENG',
	$show_dir . '/SUB.ENG/WEB-DL',
	$show_dir . '/SUB.CHI',
	$show_dir . '/SUB.CHI/GroupName',
	$show_dir . '/SUB',
	$show_dir . '/SUB/WEB-DL',
	$show_dir . '/SUB/BluRay',
	$show_dir . '/SUB/HDTV',
	$show_dir . '/SUB/Any-Other-Name',
	$show_dir . '/OST',
	$show_dir . '/random-folder',
);
foreach ( $dirs as $dir ) {
	if ( ! is_dir( $dir ) && ! mkdir( $dir, 0777, true ) ) {
		fwrite( STDERR, "Could not create {$dir}\n" );
		exit( 1 );
	}
}

$files = array(
	'720p x265 WEB-DL/I.Really.Really.Like.You.S01E01.720p.x265.WEB-DL.mkv',
	'1080p WEB-DL/I.Really.Really.Like.You.S01E01.720p.WEB-DL.mkv',
	'1080p WEB-DL/I.Really.Really.Like.You.EP01.1080p.WEB-DL.mkv',
	'540p WEB-DL/A.Love.to.Kill.2005.S01E02.540p.KCW.WEB-DL.mkv',
	'SUB.ENG/I.Really.Really.Like.You.S01E02.IMBC.WEB-DL.ENG.srt',
	'SUB.ENG/I.Really.Really.Like.You.EP01.WEB-DL.ENG.srt',
	'SUB.ENG/WEB-DL/Boys.Over.Flowers.S01E02.NF.WEB-DL.ENG.srt',
	'SUB/WEB-DL/I.Really.Really.Like.You.EP02.WEB-DL.srt',
	'SUB/BluRay/Show.S01E04.srt',
	'SUB/HDTV/Show.EP05.srt',
	'SUB/Any-Other-Name/Show.EP06.srt',
	'SUB.CHI/GroupName/Show.S01E01.CHI.srt',
	'SUB.ENG/WEB-DL/nested/extra.srt',
	'SUB/WEB-DL/foo/deep.srt',
	'SUB/random-folder/Show.EP03.srt',
	'OST/theme.mp3',
	'720p x265 WEB-DL/nested/extra.mkv',
	'random-folder/Show.S01E03.mkv',
	'Show.S01E99.mkv',
);
foreach ( $files as $relative ) {
	$path = $show_dir . '/' . $relative;
	$parent = dirname( $path );
	if ( ! is_dir( $parent ) ) {
		mkdir( $parent, 0777, true );
	}
	file_put_contents( $path, str_repeat( 'x', 1024 ) );
}

$cleanup = static function () use ( $tmp ): void {
	if ( ! is_dir( $tmp ) ) {
		return;
	}
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $it as $f ) {
		$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
	}
	rmdir( $tmp );
};

echo "scan contract\n";
$result = media_scan_series_dir(
	'series/korea/2024/I.Really.Really.Like.You',
	$tmp,
	$series_root,
	array( 'ffprobe_runner' => series_scan_ffprobe_runner() )
);
series_scan_assert_true( ( $result['ok'] ?? false ) === true, 'scan succeeds' );
series_scan_assert_true( ( $result['contract']['kind'] ?? '' ) === 'series_scan', 'contract kind' );
series_scan_assert_true( isset( $result['episodes'] ) && is_array( $result['episodes'] ), 'episodes present' );
series_scan_assert_true( isset( $result['stats']['source_count'] ), 'stats present' );

echo "\nidentity and quality\n";
$e01 = series_scan_find_file( $result['files'], 'I.Really.Really.Like.You.S01E01.720p.x265.WEB-DL.mkv' );
series_scan_assert_true( ( $e01['episode']['season_number'] ?? '' ) === '1', 'S01E01 season' );
series_scan_assert_true( ( $e01['episode']['episode_number'] ?? '' ) === '1', 'S01E01 episode' );
series_scan_assert_true( ( $e01['quality'] ?? '' ) === '720p', 'filename quality wins' );

$e540 = series_scan_find_file( $result['files'], 'A.Love.to.Kill.2005.S01E02.540p.KCW.WEB-DL.mkv' );
series_scan_assert_true( ( $e540['quality'] ?? '' ) === '540p', '540p fallback works' );
series_scan_assert_true( in_array( '540p', $e540['unclassified'] ?? array(), true ), '540p remains unclassified by generic parser' );
series_scan_assert_true(
	( $e540['group_hint'] ?? '' ) === 'KCW' || in_array( 'KCW', $e540['unclassified'] ?? array(), true ),
	'KCW remains unconfirmed group/unclassified'
);
series_scan_assert_true( ( $e540['release_group'] ?? null ) === null, 'KCW is not confirmed release_group' );
series_scan_assert_true( ( $e540['quality'] ?? '' ) !== 'KCW', 'KCW is not treated as quality' );

$ep01 = series_scan_find_file( $result['files'], 'I.Really.Really.Like.You.EP01.1080p.WEB-DL.mkv' );
series_scan_assert_true( ( $ep01['episode']['identity_type'] ?? '' ) === 'episode_only', 'EP01 video keeps episode-only identity' );
series_scan_assert_true( null === ( $ep01['episode']['season_number'] ?? null ), 'EP01 video does not invent season' );
series_scan_assert_true( ( $ep01['episode']['episode_number'] ?? '' ) === '1', 'EP01 video episode number' );

$imdb = series_scan_find_file( $result['files'], 'I.Really.Really.Like.You.S01E02.IMBC.WEB-DL.ENG.srt' );
series_scan_assert_true( ( $imdb['kind'] ?? '' ) === 'subtitle', 'subtitle stays subtitle kind' );
series_scan_assert_true(
	str_contains( (string) ( $imdb['title_hint'] ?? '' ), 'IMBC' )
		|| ( $imdb['group_hint'] ?? '' ) === 'IMBC'
		|| in_array( 'IMBC', $imdb['unclassified'] ?? array(), true ),
	'IMBC remains unclassified by known media fields'
);
series_scan_assert_true( ( $imdb['source_type'] ?? '' ) === 'WEB-DL', 'IMBC does not displace WEB-DL source' );
series_scan_assert_true( ( $imdb['subtitle_lang'] ?? '' ) === 'en', 'ENG subtitle language still detected' );

$nested_eng = series_scan_find_file( $result['files'], 'Boys.Over.Flowers.S01E02.NF.WEB-DL.ENG.srt' );
series_scan_assert_true( is_array( $nested_eng ), 'SUB.ENG/WEB-DL nested subtitle is listed' );
series_scan_assert_true( ( $nested_eng['kind'] ?? '' ) === 'subtitle', 'nested WEB-DL file stays a subtitle' );
series_scan_assert_true( ( $nested_eng['language_hint'] ?? '' ) === 'ENG', 'SUB.ENG language hint is preserved under WEB-DL' );
series_scan_assert_true( str_contains( (string) ( $nested_eng['media_path'] ?? '' ), '/SUB.ENG/WEB-DL/' ), 'nested subtitle keeps SUB.ENG/WEB-DL path' );

$nested_sub = series_scan_find_file( $result['files'], 'I.Really.Really.Like.You.EP02.WEB-DL.srt' );
series_scan_assert_true( is_array( $nested_sub ), 'SUB/WEB-DL nested subtitle is listed' );
series_scan_assert_true( ( $nested_sub['episode']['identity_type'] ?? '' ) === 'episode_only', 'nested SUB EP02 stays episode-only' );
series_scan_assert_true( ( $nested_sub['language_hint'] ?? null ) === null, 'plain SUB has no language hint' );

$bluray = series_scan_find_file( $result['files'], 'Show.S01E04.srt' );
series_scan_assert_true( is_array( $bluray ), 'SUB/BluRay nested subtitle is listed' );
series_scan_assert_true( ( $bluray['episode']['season_number'] ?? '' ) === '1', 'nested BluRay S01E04 keeps season from filename' );
series_scan_assert_true( ( $bluray['language_hint'] ?? null ) === null, 'SUB/BluRay inherits no language hint' );

$hdtv = series_scan_find_file( $result['files'], 'Show.EP05.srt' );
series_scan_assert_true( is_array( $hdtv ), 'SUB/HDTV nested subtitle is listed' );
series_scan_assert_true( ( $hdtv['episode']['identity_type'] ?? '' ) === 'episode_only', 'nested HDTV EP05 stays episode-only' );

$other = series_scan_find_file( $result['files'], 'Show.EP06.srt' );
series_scan_assert_true( is_array( $other ), 'arbitrary nested subtitle folder is listed' );
series_scan_assert_true( str_contains( (string) ( $other['media_path'] ?? '' ), '/SUB/Any-Other-Name/' ), 'arbitrary nested folder keeps its path' );

$random_nested = series_scan_find_file( $result['files'], 'Show.EP03.srt' );
series_scan_assert_true( is_array( $random_nested ), 'non-source nested subtitle folder is listed' );

$chi = series_scan_find_file( $result['files'], 'Show.S01E01.CHI.srt' );
series_scan_assert_true( is_array( $chi ), 'SUB.CHI nested subtitle is listed' );
series_scan_assert_true( ( $chi['language_hint'] ?? '' ) === 'CHI', 'SUB.CHI language hint is preserved under an arbitrary nested folder' );
series_scan_assert_true( ( $chi['language_hint'] ?? '' ) !== ( $nested_eng['language_hint'] ?? '' ), 'SUB.CHI and SUB.ENG stay distinct language tracks' );

$skipped_nested = series_scan_find_file( $result['files'], 'extra.srt' );
series_scan_assert_true( $skipped_nested === null, 'second-level subtitle nesting is still skipped' );
$skipped_deep = series_scan_find_file( $result['files'], 'deep.srt' );
series_scan_assert_true( $skipped_deep === null, 'SUB/WEB-DL/foo is still skipped as excessive nesting' );

$one_level_skipped = false;
$deep_skipped      = false;
$foo_skipped       = false;
foreach ( $result['warnings'] as $warning ) {
	if ( ( $warning['code'] ?? '' ) !== 'excessive_subtitle_nesting' ) {
		continue;
	}
	$warning_name = (string) ( $warning['name'] ?? '' );
	if ( in_array( $warning_name, array( 'WEB-DL', 'BluRay', 'HDTV', 'Any-Other-Name', 'random-folder', 'GroupName' ), true ) ) {
		$one_level_skipped = true;
	}
	if ( str_contains( $warning_name, 'nested' ) ) {
		$deep_skipped = true;
	}
	if ( str_contains( $warning_name, '/foo' ) ) {
		$foo_skipped = true;
	}
}
series_scan_assert_true( ! $one_level_skipped, 'one-level nested subtitle folders are not treated as unknown nesting' );
series_scan_assert_true( $deep_skipped, 'excessive subtitle nesting still warns' );
series_scan_assert_true( $foo_skipped, 'second nested directory under SUB still warns' );

echo "\ngrouping and exclusions\n";
$episode1 = null;
foreach ( $result['episodes'] as $episode ) {
	if ( ( $episode['season_number'] ?? '' ) === '1' && ( $episode['episode_number'] ?? '' ) === '1' ) {
		$episode1 = $episode;
		break;
	}
}
series_scan_assert_true( $episode1 !== null && count( $episode1['sources'] ?? array() ) >= 2, 'multiple sources for one episode' );

$episode_only1 = null;
foreach ( $result['episodes'] as $episode ) {
	if ( null === ( $episode['season_number'] ?? null ) && ( $episode['episode_number'] ?? '' ) === '1' ) {
		$episode_only1 = $episode;
		break;
	}
}
series_scan_assert_true( null !== $episode_only1, 'EP01 coexists with explicit S01E01 group' );
series_scan_assert_true( count( $episode_only1['sources'] ?? array() ) === 1, 'EP01 video grouped without season' );
series_scan_assert_true( count( $episode_only1['subtitles'] ?? array() ) === 1, 'EP01 subtitle associates with EP01 video' );

$has_ost = false;
foreach ( $result['files'] as $file ) {
	if ( str_contains( (string) ( $file['media_path'] ?? '' ), '/OST/' ) ) {
		$has_ost = true;
	}
}
series_scan_assert_true( ! $has_ost, 'OST excluded from files' );

$has_root_file = false;
foreach ( $result['warnings'] as $warning ) {
	if ( ( $warning['code'] ?? '' ) === 'unexpected_series_root_file' ) {
		$has_root_file = true;
	}
	if ( ( $warning['code'] ?? '' ) === 'unexpected_video_subdirectory' ) {
		series_scan_assert_true( true, 'nested video directory warns' );
	}
	if ( ( $warning['code'] ?? '' ) === 'supplementary_skipped' ) {
		series_scan_assert_true( true, 'OST supplementary skipped' );
	}
}
series_scan_assert_true( $has_root_file, 'root file warns' );

echo "\nscan auth route signature\n";
$dir = 'series/Chin/2025/Spring.Burning';
$movie = media_scan_canonical_request( '1700000000', $dir );
$series = media_scan_canonical_request_for_path( '1700000000', '/scan/series', $dir );
$uppercase = media_scan_canonical_request_for_path( '1700000000', '/scan/series', 'Series/Chin/2025/Spring.Burning' );
series_scan_assert_true( str_contains( $movie, '/scan/movie' ), 'movie route preserved' );
series_scan_assert_true( str_contains( $series, '/scan/series' ), 'series route distinct' );
series_scan_assert_true( $movie !== $series, 'series and movie signatures differ' );
series_scan_assert_true( str_contains( $series, 'dir=series%2FChin%2F2025%2FSpring.Burning' ), 'canonical request signs exact lowercase dir value' );
series_scan_assert_true( $series !== $uppercase, 'dir casing changes canonical request and signature input' );
$secret = 'test-secret';
$signature = media_scan_signature( $series, $secret );
series_scan_assert_true( media_scan_signature_valid( $series, $secret, $signature ), 'lowercase series dir HMAC validates' );

$cleanup();

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failure(s)\n" );
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

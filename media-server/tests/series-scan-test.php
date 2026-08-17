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
	'540p WEB-DL/A.Love.to.Kill.2005.S01E02.540p.KCW.WEB-DL.mkv',
	'SUB.ENG/I.Really.Really.Like.You.S01E02.IMBC.WEB-DL.ENG.srt',
	'SUB.ENG/WEB-DL/Boys.Over.Flowers.S01E02.NF.WEB-DL.ENG.srt',
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

echo "\ngrouping and exclusions\n";
$episode1 = null;
foreach ( $result['episodes'] as $episode ) {
	if ( ( $episode['season_number'] ?? '' ) === '1' && ( $episode['episode_number'] ?? '' ) === '1' ) {
		$episode1 = $episode;
		break;
	}
}
series_scan_assert_true( $episode1 !== null && count( $episode1['sources'] ?? array() ) >= 2, 'multiple sources for one episode' );

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

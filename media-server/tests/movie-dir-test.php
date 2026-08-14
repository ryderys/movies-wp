<?php
/**
 * CLI tests for media_resolve_movie_dir().
 *
 * Run: php media-server/tests/movie-dir-test.php
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/movie-dir.php';

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

function assert_error( array $result, string $code, string $label ): void {
	assert_true( ( $result['ok'] ?? true ) === false && ( $result['code'] ?? '' ) === $code, $label );
}

$tmp = sys_get_temp_dir() . '/movie-dir-test-' . bin2hex( random_bytes( 4 ) );
$movie_root = $tmp . '/Movie';
$ok_dir     = $movie_root . '/Chin/2016/Bounty.Hunters';
$series_dir = $tmp . '/series/Chin/2025/A.Dream.within.A.Dream';

if ( ! mkdir( $ok_dir, 0777, true ) ) {
	fwrite( STDERR, "Could not create fixture dir\n" );
	exit( 1 );
}
if ( ! mkdir( $series_dir, 0777, true ) ) {
	fwrite( STDERR, "Could not create series fixture dir\n" );
	exit( 1 );
}

$file_path = $ok_dir . '/Bounty.Hunters.2016.1080p.WEB-DL.SS.mkv';
file_put_contents( $file_path, 'x' );

$file_as_dir = $movie_root . '/Chin/2016/NotADirectory.mkv';
file_put_contents( $file_as_dir, 'x' );

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

echo "media_resolve_movie_dir\n";

$ok = media_resolve_movie_dir( 'Movie/Chin/2016/Bounty.Hunters', $tmp, $movie_root );
assert_true( ( $ok['ok'] ?? false ) === true, 'accepts canonical relative movie dir' );
assert_true( ( $ok['kind'] ?? '' ) === 'movie', 'kind=movie' );
assert_true( ( $ok['directory'] ?? '' ) === 'Movie/Chin/2016/Bounty.Hunters', 'directory' );
assert_true( ( $ok['country'] ?? '' ) === 'Chin', 'country' );
assert_true( ( $ok['year'] ?? 0 ) === 2016, 'year' );
assert_true( ( $ok['movie_name'] ?? '' ) === 'Bounty.Hunters', 'movie_name' );

$slash = media_resolve_movie_dir( 'Movie/Chin/2016/Bounty.Hunters/', $tmp, $movie_root );
assert_true( ( $slash['ok'] ?? false ) === true, 'trims trailing slash' );

$bs = media_resolve_movie_dir( 'Movie\\Chin\\2016\\Bounty.Hunters', $tmp, $movie_root );
assert_true( ( $bs['ok'] ?? false ) === true, 'normalizes backslashes' );

assert_error( media_resolve_movie_dir( '', $tmp, $movie_root ), 'empty_path', 'rejects empty' );
assert_error( media_resolve_movie_dir( "Movie/Chin/2016/Bounty\0Hunters", $tmp, $movie_root ), 'invalid_path', 'rejects null byte' );
assert_error( media_resolve_movie_dir( '/Movie/Chin/2016/Bounty.Hunters', $tmp, $movie_root ), 'absolute_path', 'rejects absolute unix path' );
assert_error( media_resolve_movie_dir( 'Movie/Chin/2016/../Bounty.Hunters', $tmp, $movie_root ), 'invalid_segment', 'rejects ..' );
assert_error( media_resolve_movie_dir( 'Movie/Chin/./2016/Bounty.Hunters', $tmp, $movie_root ), 'invalid_segment', 'rejects .' );
assert_error( media_resolve_movie_dir( 'Movie/Chin//2016/Bounty.Hunters', $tmp, $movie_root ), 'invalid_segment', 'rejects empty segment' );
assert_error( media_resolve_movie_dir( 'series/Chin/2025/A.Dream.within.A.Dream', $tmp, $movie_root ), 'invalid_structure', 'rejects series path' );
assert_error( media_resolve_movie_dir( 'Movie/Chin/2016', $tmp, $movie_root ), 'invalid_structure', 'rejects year folder (too shallow)' );
assert_error( media_resolve_movie_dir( 'Movie/Chin/2016/Bounty.Hunters/extra', $tmp, $movie_root ), 'invalid_structure', 'rejects nested path (too deep)' );
assert_error( media_resolve_movie_dir( 'Movie/Chin/1800/Bounty.Hunters', $tmp, $movie_root ), 'invalid_year', 'rejects 1800' );
assert_error( media_resolve_movie_dir( 'Movie/Chin/2100/Bounty.Hunters', $tmp, $movie_root ), 'invalid_year', 'rejects 2100' );
assert_error( media_resolve_movie_dir( 'Movie/Chin/abcd/Bounty.Hunters', $tmp, $movie_root ), 'invalid_year', 'rejects non-numeric year' );
assert_error(
	media_resolve_movie_dir( 'Movie/Chin/2016/Bounty.Hunters/Bounty.Hunters.2016.1080p.WEB-DL.SS.mkv', $tmp, $movie_root ),
	'invalid_structure',
	'rejects file path (too deep)'
);
assert_error(
	media_resolve_movie_dir( 'Movie/Chin/2016/Missing.Movie', $tmp, $movie_root ),
	'not_found',
	'rejects missing directory'
);
assert_error(
	media_resolve_movie_dir( 'Movie/Chin/2016/NotADirectory.mkv', $tmp, $movie_root ),
	'not_directory',
	'rejects file instead of directory'
);

$outside_media = media_resolve_movie_dir( 'Movie/Chin/2016/Bounty.Hunters', $tmp . '/does-not-exist', $movie_root );
assert_error( $outside_media, 'root_not_found', 'rejects missing MEDIA_ROOT' );

echo "\nmedia_movie_dir_roots defaults\n";
$prev_media = getenv( 'MEDIA_ROOT' );
$prev_data  = getenv( 'MEDIA_DATA_ROOT' );
$prev_movie = getenv( 'MOVIE_ROOT' );
putenv( 'MEDIA_ROOT' );
putenv( 'MEDIA_DATA_ROOT' );
putenv( 'MOVIE_ROOT' );
$defaults = media_movie_dir_roots();
assert_true( $defaults['media_root'] === '/data', 'default MEDIA_ROOT is /data (config only)' );
assert_true( $defaults['movie_root'] === '/data/Movie', 'default MOVIE_ROOT is {MEDIA_ROOT}/Movie' );
if ( is_string( $prev_media ) && $prev_media !== '' ) {
	putenv( 'MEDIA_ROOT=' . $prev_media );
}
if ( is_string( $prev_data ) && $prev_data !== '' ) {
	putenv( 'MEDIA_DATA_ROOT=' . $prev_data );
}
if ( is_string( $prev_movie ) && $prev_movie !== '' ) {
	putenv( 'MOVIE_ROOT=' . $prev_movie );
}

$cleanup();

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

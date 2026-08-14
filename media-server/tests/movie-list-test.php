<?php
/**
 * CLI tests for media_list_movie_dir().
 *
 * Run: php media-server/tests/movie-list-test.php
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/movie-list.php';

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

function warning_codes_for( array $warnings, string $name ): array {
	$codes = array();
	foreach ( $warnings as $warning ) {
		if ( ( $warning['name'] ?? '' ) === $name ) {
			$codes[] = $warning['code'];
		}
	}
	return $codes;
}

$tmp        = sys_get_temp_dir() . '/movie-list-test-' . bin2hex( random_bytes( 4 ) );
$movie_root = $tmp . '/Movie';
$ok_dir     = $movie_root . '/Chin/2016/Bounty.Hunters';
$outside    = $tmp . '/outside.txt';

if ( ! mkdir( $ok_dir, 0777, true ) ) {
	fwrite( STDERR, "Could not create fixture dir\n" );
	exit( 1 );
}

file_put_contents( $ok_dir . '/Bounty.Hunters.2016.1080p.WEB-DL.SS.mkv', str_repeat( 'a', 2048 ) );
file_put_contents( $ok_dir . '/Bounty.Hunters.2016.1080p.WEB-DL.SS.srt', str_repeat( 'b', 100 ) );
file_put_contents( $ok_dir . '/cover.jpg', 'jpg' );
file_put_contents( $ok_dir . '/RARBG.txt', 'nfo-like' );
file_put_contents( $ok_dir . '/Bounty.Hunters.2016.sample.mkv', str_repeat( 's', 50 ) );
file_put_contents( $ok_dir . '/movie.iso', 'iso' );
mkdir( $ok_dir . '/SUB', 0777, true );
file_put_contents( $ok_dir . '/SUB/nested.srt', 'should-not-be-listed' );
file_put_contents( $outside, 'secret' );

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

echo "media_format_size_label\n";
assert_true( media_format_size_label( 2523456789 ) === '2.35 GB', '2523456789 → 2.35 GB' );
assert_true( media_format_size_label( 0 ) === '0 B', '0 B' );
assert_true( media_format_size_label( 1023 ) === '1023 B', '1023 B' );
assert_true( media_format_size_label( 1024 ) === '1.00 KB', '1024 → 1.00 KB' );

echo "\nmedia_list_movie_dir rejects via resolver\n";
$bad = media_list_movie_dir( 'series/Chin/2025/Nope', $tmp, $movie_root );
assert_true( ( $bad['ok'] ?? true ) === false && ( $bad['code'] ?? '' ) === 'invalid_structure', 'list uses resolver first' );

echo "\nmedia_list_movie_dir listing\n";
$list = media_list_movie_dir( 'Movie/Chin/2016/Bounty.Hunters', $tmp, $movie_root );
assert_true( ( $list['ok'] ?? false ) === true, 'lists a valid movie dir' );
assert_true( ( $list['directory'] ?? '' ) === 'Movie/Chin/2016/Bounty.Hunters', 'keeps resolver directory' );

$files = $list['files'] ?? array();
$names = array_column( $files, 'name' );
assert_true( ! in_array( 'nested.srt', $names, true ), 'does not recurse into SUB' );

$mkv = find_file( $files, 'Bounty.Hunters.2016.1080p.WEB-DL.SS.mkv' );
assert_true( is_array( $mkv ) && $mkv['kind'] === 'video', 'mkv is video' );
assert_true( is_array( $mkv ) && $mkv['extension'] === 'mkv', 'mkv extension' );
assert_true( is_array( $mkv ) && $mkv['size_bytes'] === 2048, 'mkv size_bytes' );
assert_true( is_array( $mkv ) && $mkv['size_label'] === '2.00 KB', 'mkv size_label' );
assert_true(
	is_array( $mkv ) && $mkv['media_path'] === 'Movie/Chin/2016/Bounty.Hunters/Bounty.Hunters.2016.1080p.WEB-DL.SS.mkv',
	'relative media_path'
);

$srt = find_file( $files, 'Bounty.Hunters.2016.1080p.WEB-DL.SS.srt' );
assert_true( is_array( $srt ) && $srt['kind'] === 'subtitle', 'srt is subtitle' );
assert_true( is_array( $srt ) && $srt['size_bytes'] === 100, 'srt size_bytes' );

$jpg = find_file( $files, 'cover.jpg' );
assert_true( is_array( $jpg ) && $jpg['kind'] === 'ignored', 'jpg is ignored' );

$txt = find_file( $files, 'RARBG.txt' );
assert_true( is_array( $txt ) && $txt['kind'] === 'ignored', 'txt is ignored' );

$sample = find_file( $files, 'Bounty.Hunters.2016.sample.mkv' );
assert_true( is_array( $sample ) && $sample['kind'] === 'ignored', 'sample mkv is ignored' );
assert_true(
	in_array( 'sample_or_trailer', warning_codes_for( $list['warnings'] ?? array(), 'Bounty.Hunters.2016.sample.mkv' ), true ),
	'sample warning'
);

$iso = find_file( $files, 'movie.iso' );
assert_true( is_array( $iso ) && $iso['kind'] === 'ignored', 'unknown extension is ignored' );
assert_true(
	in_array( 'unrecognized_extension', warning_codes_for( $list['warnings'] ?? array(), 'movie.iso' ), true ),
	'unrecognized extension warning'
);

$sub = find_file( $files, 'SUB' );
assert_true( is_array( $sub ) && $sub['kind'] === 'directory', 'SUB is directory' );
assert_true( is_array( $sub ) && $sub['size_bytes'] === null, 'directory has no size' );
assert_true(
	in_array( 'unexpected_subdirectory', warning_codes_for( $list['warnings'] ?? array(), 'SUB' ), true ),
	'subdirectory warning'
);

if ( function_exists( 'symlink' ) ) {
	$link = $ok_dir . '/escape.txt';
	$linked = @symlink( $outside, $link );
	if ( $linked ) {
		$again = media_list_movie_dir( 'Movie/Chin/2016/Bounty.Hunters', $tmp, $movie_root );
		$escape = find_file( $again['files'] ?? array(), 'escape.txt' );
		assert_true( is_array( $escape ) && $escape['kind'] === 'ignored', 'outside symlink is ignored' );
		assert_true( is_array( $escape ) && $escape['size_bytes'] === null, 'outside symlink is not stat\'d' );
		assert_true(
			in_array( 'symlink_outside', warning_codes_for( $again['warnings'] ?? array(), 'escape.txt' ), true ),
			'outside symlink warning'
		);
	} else {
		echo "  skip  symlink (not permitted on this host)\n";
	}
}

$cleanup();

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

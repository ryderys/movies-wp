<?php
/**
 * CLI tests for media_resolve_series_dir().
 *
 * Run: php media-server/tests/series-dir-test.php
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/series-dir.php';

$failures = 0;

function series_dir_assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function series_dir_assert_error( array $result, string $code, string $label ): void {
	series_dir_assert_true( ( $result['ok'] ?? true ) === false && ( $result['code'] ?? '' ) === $code, $label );
}

$tmp = sys_get_temp_dir() . '/series-dir-test-' . bin2hex( random_bytes( 4 ) );
$series_root = $tmp . '/series';
$ok_dir      = $series_root . '/korea/2024/Marry.My.Husband';

if ( ! mkdir( $ok_dir, 0777, true ) ) {
	fwrite( STDERR, "Could not create fixture dir\n" );
	exit( 1 );
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

echo "media_resolve_series_dir\n";

$ok = media_resolve_series_dir( 'series/korea/2024/Marry.My.Husband', $tmp, $series_root );
series_dir_assert_true( ( $ok['ok'] ?? false ) === true, 'accepts canonical relative series dir' );
series_dir_assert_true( ( $ok['kind'] ?? '' ) === 'series', 'kind=series' );
series_dir_assert_true( ( $ok['directory'] ?? '' ) === 'series/korea/2024/Marry.My.Husband', 'directory' );
series_dir_assert_true( ( $ok['country'] ?? '' ) === 'korea', 'country' );
series_dir_assert_true( ( $ok['year'] ?? 0 ) === 2024, 'year' );
series_dir_assert_true( ( $ok['series_title'] ?? '' ) === 'Marry.My.Husband', 'series_title' );

series_dir_assert_error( media_resolve_series_dir( '', $tmp, $series_root ), 'empty_path', 'rejects empty' );
series_dir_assert_error( media_resolve_series_dir( "series/korea/2024/Marry\0My.Husband", $tmp, $series_root ), 'invalid_path', 'rejects NUL' );
series_dir_assert_error( media_resolve_series_dir( '/series/korea/2024/Marry.My.Husband', $tmp, $series_root ), 'absolute_path', 'rejects absolute path' );
series_dir_assert_error( media_resolve_series_dir( 'series/korea/2024/../Marry.My.Husband', $tmp, $series_root ), 'invalid_segment', 'rejects ..' );
series_dir_assert_error( media_resolve_series_dir( 'Movie/korea/2024/Marry.My.Husband', $tmp, $series_root ), 'invalid_structure', 'rejects movie path' );
series_dir_assert_error( media_resolve_series_dir( 'Series/korea/2024/Marry.My.Husband', $tmp, $series_root ), 'invalid_structure', 'rejects non-canonical uppercase Series prefix' );
series_dir_assert_error( media_resolve_series_dir( 'series/korea/1800/Marry.My.Husband', $tmp, $series_root ), 'invalid_year', 'rejects wrong year' );
series_dir_assert_error( media_resolve_series_dir( 'series/korea/2024/Missing.Show', $tmp, $series_root ), 'not_found', 'rejects missing directory' );

echo "\nmedia_series_dir_roots defaults\n";
$defaults = media_series_dir_roots();
series_dir_assert_true( $defaults['series_root'] === '/data/series', 'default SERIES_ROOT is /data/series' );

$cleanup();

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failure(s)\n" );
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

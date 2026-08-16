<?php
/**
 * Deterministic CLI tests for the TMDb proxy host deployment patch.
 *
 * Run:
 *   php scripts/tests/tmdb-proxy-host-patch-test.php
 */

require dirname( __DIR__ ) . '/patch-tmdb-proxy-host.php';

$failures = 0;

/**
 * @param bool   $condition Assertion result.
 * @param string $label     Assertion label.
 * @return void
 */
function assert_true( $condition, $label ) {
	global $failures;
	if ( $condition ) {
		echo "ok {$label}\n";
		return;
	}
	++$failures;
	echo "FAIL {$label}\n";
}

/**
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $label    Assertion label.
 * @return void
 */
function assert_same( $expected, $actual, $label ) {
	global $failures;
	if ( $expected === $actual ) {
		echo "ok {$label}\n";
		return;
	}
	++$failures;
	echo "FAIL {$label}\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

echo "tmdb proxy host patch\n\n";

$host   = 'tmdb.asiastars.ir';
$source = <<<'PHP'
<?php
function streamit_get_tmdb_image_url($image_path, $size = 'original') {
    return 'https://tmdb.youssefi-ashkan-ys.workers.dev/t/p/' . $size . '/' . $image_path;
}

$api_url = [
    'name'   => 'https://tmdb.youssefi-ashkan-ys.workers.dev/3/search/movie',
    'id'     => 'https://tmdb.youssefi-ashkan-ys.workers.dev/3/movie/' . $keyword,
];

$details_url = "https://tmdb.youssefi-ashkan-ys.workers.dev/3/movie/{$tmdb_id}?api_key={$api_key}";
$is_cloudflare_worker = (strpos($url, 'tmdb.youssefi-ashkan-ys.workers.dev') !== false);

if (strpos($err, 'SSL') !== false) {
    $note = 'certificate';
}
$local = home_url('/wp-json/movies/v1/thing');
PHP;

$changes = array();
$patched = streamit_patch_tmdb_proxy_host_content( $source, $host, $changes );

assert_same( array( 'tmdb.youssefi-ashkan-ys.workers.dev' ), $changes, 'only the dead worker host is replaced' );
assert_true( false === strpos( $patched, 'workers.dev' ), 'no workers.dev reference survives' );
assert_true(
	false !== strpos( $patched, "'https://tmdb.asiastars.ir/t/p/' . \$size" ),
	'image endpoint uses the new host'
);
assert_true(
	false !== strpos( $patched, "'https://tmdb.asiastars.ir/3/search/movie'" ),
	'single-quoted API endpoint uses the new host'
);
assert_true(
	false !== strpos( $patched, '"https://tmdb.asiastars.ir/3/movie/{$tmdb_id}?api_key={$api_key}"' ),
	'interpolated API endpoint uses the new host'
);
assert_true(
	false !== strpos( $patched, "strpos(\$url, 'tmdb.asiastars.ir')" ),
	'worker detection guard tracks the new host'
);
assert_true(
	false !== strpos( $patched, "strpos(\$err, 'SSL')" ),
	'unrelated strpos checks are untouched'
);
assert_true(
	false !== strpos( $patched, "home_url('/wp-json/movies/v1/thing')" ),
	'non-TMDb code is untouched'
);

$second_changes = array();
$second_pass    = streamit_patch_tmdb_proxy_host_content( $patched, $host, $second_changes );

assert_true( $patched === $second_pass, 'deployment patch is idempotent' );
assert_same( array(), $second_changes, 'second patch pass makes no changes' );

$moved_changes = array();
$moved         = streamit_patch_tmdb_proxy_host_content( $patched, 'tmdb.example.net', $moved_changes );

assert_same( array( 'tmdb.asiastars.ir' ), $moved_changes, 'a later host move is a single replacement' );
assert_true( false !== strpos( $moved, 'https://tmdb.example.net/3/search/movie' ), 'host move rewrites endpoints' );

echo "\nhost resolution\n\n";

assert_same(
	'tmdb.example.org',
	streamit_patch_tmdb_proxy_host_target( array( '--host=TMDB.Example.ORG' ) ),
	'explicit --host wins and is normalized'
);
assert_same(
	'tmdb.asiastars.ir',
	streamit_patch_tmdb_proxy_host_target( array() ),
	'default host matches the child theme constant'
);

echo "\n";

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} assertion(s) failed.\n" );
	exit( 1 );
}

echo "All tmdb-proxy-host assertions passed.\n";

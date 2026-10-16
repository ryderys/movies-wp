<?php
/**
 * CLI tests for trailer upload auth, validation, and placement.
 *
 * Run: php media-server/tests/trailer-upload-test.php
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/trailer-upload.php';

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

/**
 * Minimal ISO BMFF / MP4 stub (ftyp at offset 4).
 */
function fixture_mp4_bytes(): string {
	return "\x00\x00\x00\x18" . 'ftypisom' . str_repeat( "\x00", 16 );
}

/**
 * Minimal EBML / Matroska stub.
 */
function fixture_mkv_bytes(): string {
	return "\x1A\x45\xDF\xA3" . str_repeat( "\x00", 28 );
}

$tmp = sys_get_temp_dir() . '/trailer-upload-test-' . bin2hex( random_bytes( 4 ) );
$movie_root = $tmp . '/Movie';
$series_root = $tmp . '/series';
$ok_movie = $movie_root . '/Chin/2016/Bounty.Hunters';
$ok_series = $series_root . '/Chin/2025/Dream.Show';
$nonce_dir = $tmp . '/nonces';

if ( ! mkdir( $ok_movie, 0777, true ) || ! mkdir( $ok_series, 0777, true ) || ! mkdir( $nonce_dir, 0777, true ) ) {
	fwrite( STDERR, "Could not create fixture dirs\n" );
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

register_shutdown_function( $cleanup );

echo "POST canonical / HMAC\n";
$dir  = 'Movie/Chin/2016/Bounty.Hunters';
$file = 'trailer.mp4';
$sha  = hash( 'sha256', fixture_mp4_bytes() );
$ts   = '1700000000';
$q    = media_upload_trailer_canonical_query( $dir, $file, $sha );
assert_true( str_contains( $q, 'dir=' ), 'query has dir' );
assert_true( str_contains( $q, 'filename=' ), 'query has filename' );
assert_true( str_contains( $q, 'sha256=' ), 'query has sha256' );

$canon = media_upload_trailer_canonical_request( $ts, $dir, $file, $sha );
assert_true(
	str_starts_with( $canon, $ts . "\nPOST\n/upload/trailer\n" ),
	'canonical includes POST method and path'
);
assert_true( ! str_contains( $canon, "\nGET\n" ), 'does not reuse GET canonical' );

$secret = 'test-secret';
$sig    = media_scan_signature( $canon, $secret );
assert_true( media_scan_signature_valid( $canon, $secret, $sig ), 'valid POST signature' );

$tampered = media_upload_trailer_canonical_request( $ts, $dir, 'evil.mp4', $sha );
assert_true( ! media_scan_signature_valid( $tampered, $secret, $sig ), 'rejects filename tamper' );

$get_canon = media_scan_canonical_request( $ts, $dir );
assert_true( ! media_scan_signature_valid( $get_canon, $secret, $sig ), 'GET signature not valid for POST' );

echo "\nstale / replay\n";
$now = 1_700_000_000;
assert_true( media_scan_timestamp_valid( (string) $now, 300, $now ), 'fresh timestamp' );
assert_true( ! media_scan_timestamp_valid( (string) ( $now - 301 ), 300, $now ), 'stale timestamp rejected' );

$store = array();
$store_fn = static function ( string $key, int $ttl ) use ( &$store ): bool {
	if ( isset( $store[ $key ] ) ) {
		return false;
	}
	$store[ $key ] = $ttl;
	return true;
};
assert_true( media_upload_nonce_consume( $canon, 300, $nonce_dir, $store_fn ), 'first nonce accepted' );
assert_true( ! media_upload_nonce_consume( $canon, 300, $nonce_dir, $store_fn ), 'replay nonce rejected' );

assert_true( media_upload_nonce_consume( $canon . '-b', 300, $nonce_dir ), 'file nonce first use' );
assert_true( ! media_upload_nonce_consume( $canon . '-b', 300, $nonce_dir ), 'file nonce replay rejected' );

echo "\nfilename / type validation\n";
assert_error( media_upload_trailer_validate_filename( '' ), 'invalid_filename', 'empty filename' );
assert_error( media_upload_trailer_validate_filename( '../x.mp4' ), 'invalid_filename', 'path traversal filename' );
assert_error( media_upload_trailer_validate_filename( 'a/b.mp4' ), 'invalid_filename', 'slash in filename' );
assert_error( media_upload_trailer_validate_filename( 'trailer.exe' ), 'invalid_filename', 'bad extension' );
$ok_name = media_upload_trailer_validate_filename( 'My.Trailer.MP4' );
assert_true( ( $ok_name['ok'] ?? false ) === true && ( $ok_name['filename'] ?? '' ) === 'My.Trailer.mp4', 'normalizes extension case' );

echo "\npath / title resolution\n";
$place_opts = array(
	'max_bytes'        => 1024 * 1024,
	'require_ffprobe'  => false,
	'ffprobe_runner'   => null,
);

$src = $tmp . '/src.mp4';
file_put_contents( $src, fixture_mp4_bytes() );
$sha = hash_file( 'sha256', $src );

assert_error(
	media_upload_trailer_place(
		'/Movie/Chin/2016/Bounty.Hunters',
		'trailer.mp4',
		$src,
		$sha,
		$tmp,
		$movie_root,
		$series_root,
		$place_opts
	),
	'absolute_path',
	'rejects absolute dir'
);

assert_error(
	media_upload_trailer_place(
		'Movie/Chin/2016/../Bounty.Hunters',
		'trailer.mp4',
		$src,
		$sha,
		$tmp,
		$movie_root,
		$series_root,
		$place_opts
	),
	'invalid_segment',
	'rejects traversal dir'
);

assert_error(
	media_upload_trailer_place(
		'Movie/Chin/2016/Does.Not.Exist',
		'trailer.mp4',
		$src,
		$sha,
		$tmp,
		$movie_root,
		$series_root,
		$place_opts
	),
	'not_found',
	'rejects missing title dir'
);

echo "\nsize / checksum / sniff\n";
assert_error(
	media_upload_trailer_place(
		$dir,
		'trailer.mp4',
		$src,
		$sha,
		$tmp,
		$movie_root,
		$series_root,
		array_merge( $place_opts, array( 'max_bytes' => 4 ) )
	),
	'too_large',
	'rejects oversize'
);

assert_error(
	media_upload_trailer_place(
		$dir,
		'trailer.mp4',
		$src,
		str_repeat( 'a', 64 ),
		$tmp,
		$movie_root,
		$series_root,
		$place_opts
	),
	'checksum_mismatch',
	'rejects checksum mismatch'
);

$bad = $tmp . '/bad.bin';
file_put_contents( $bad, 'not-a-video-file!!!!!!!!!!!!' );
$bad_sha = hash_file( 'sha256', $bad );
assert_error(
	media_upload_trailer_place(
		$dir,
		'trailer.mp4',
		$bad,
		$bad_sha,
		$tmp,
		$movie_root,
		$series_root,
		$place_opts
	),
	'invalid_type',
	'rejects non-media bytes labeled mp4'
);

$mkv_src = $tmp . '/src.mkv';
file_put_contents( $mkv_src, fixture_mkv_bytes() );
$mkv_sha = hash_file( 'sha256', $mkv_src );
assert_error(
	media_upload_trailer_place(
		$dir,
		'trailer.mp4',
		$mkv_src,
		$mkv_sha,
		$tmp,
		$movie_root,
		$series_root,
		$place_opts
	),
	'invalid_type',
	'rejects mkv bytes with mp4 name'
);

echo "\nsuccessful placement + overwrite refusal + temp cleanup\n";
$probe_runner = static function ( array $argv ): array {
	return array(
		'exit'   => 0,
		'stdout' => json_encode(
			array(
				'format'  => array( 'duration' => '10.0' ),
				'streams' => array(
					array(
						'codec_type' => 'video',
						'codec_name' => 'h264',
						'width'      => 1280,
						'height'     => 720,
					),
				),
			)
		),
		'stderr' => '',
	);
};

$ok = media_upload_trailer_place(
	$dir,
	'official.mp4',
	$src,
	$sha,
	$tmp,
	$movie_root,
	$series_root,
	array(
		'max_bytes'       => 1024 * 1024,
		'ffprobe_runner'  => $probe_runner,
	)
);
assert_true( ( $ok['ok'] ?? false ) === true, 'places trailer under Trailer/' );
assert_true(
	( $ok['path'] ?? '' ) === 'Movie/Chin/2016/Bounty.Hunters/Trailer/official.mp4',
	'returns relative path only'
);
assert_true(
	is_file( $ok_movie . '/Trailer/official.mp4' ),
	'file exists on disk under Trailer/'
);
assert_true(
	! str_contains( (string) ( $ok['path'] ?? '' ), $tmp ),
	'response has no absolute path'
);

// Leftover temp files should not remain after success.
$leftover = glob( $ok_movie . '/Trailer/.upload-*.tmp' ) ?: array();
assert_true( $leftover === array(), 'no leftover temp files after success' );

$dup = media_upload_trailer_place(
	$dir,
	'official.mp4',
	$src,
	$sha,
	$tmp,
	$movie_root,
	$series_root,
	array(
		'max_bytes'      => 1024 * 1024,
		'ffprobe_runner' => $probe_runner,
	)
);
assert_error( $dup, 'already_exists', 'refuses overwrite by default' );

// Failed sniff should clean temp.
$fail_tmp_before = glob( $ok_movie . '/Trailer/.upload-*.tmp' ) ?: array();
media_upload_trailer_place(
	$dir,
	'other.mp4',
	$bad,
	$bad_sha,
	$tmp,
	$movie_root,
	$series_root,
	$place_opts
);
$fail_tmp_after = glob( $ok_movie . '/Trailer/.upload-*.tmp' ) ?: array();
assert_true( $fail_tmp_after === $fail_tmp_before, 'cleans temp after validation failure' );

echo "\nexclusive publish (no overwrite race)\n";
$race_name = 'race-target.mp4';
$race_final = $ok_movie . '/Trailer/' . $race_name;
$original   = "ORIGINAL_DESTINATION_BYTES_DO_NOT_TOUCH\n";
file_put_contents( $race_final, $original );
$orig_hash = hash_file( 'sha256', $race_final );

$race = media_upload_trailer_place(
	$dir,
	$race_name,
	$src,
	$sha,
	$tmp,
	$movie_root,
	$series_root,
	array(
		'max_bytes'      => 1024 * 1024,
		'ffprobe_runner' => $probe_runner,
	)
);
assert_error( $race, 'already_exists', 'second upload targeting same final path fails' );
assert_true( is_file( $race_final ), 'existing destination still present' );
assert_true( hash_file( 'sha256', $race_final ) === $orig_hash, 'existing destination hash unchanged' );
assert_true( file_get_contents( $race_final ) === $original, 'existing destination bytes unchanged' );
$race_tmps = glob( $ok_movie . '/Trailer/.upload-*.tmp' ) ?: array();
assert_true( $race_tmps === array(), 'race failure cleans temp; destination untouched' );

// Deterministic exclusive publish: link into empty name, then second link must fail.
$pub_a = $ok_movie . '/Trailer/.pub-a.tmp';
$pub_b = $ok_movie . '/Trailer/.pub-b.tmp';
$pub_final = $ok_movie . '/Trailer/exclusive.mp4';
file_put_contents( $pub_a, fixture_mp4_bytes() . 'A' );
file_put_contents( $pub_b, fixture_mp4_bytes() . 'B' );
$first = media_upload_trailer_publish_exclusive( $pub_a, $pub_final );
assert_true( ( $first['ok'] ?? false ) === true, 'first exclusive publish succeeds' );
assert_true( is_file( $pub_final ), 'final exists after first publish' );
$final_hash = hash_file( 'sha256', $pub_final );
$second = media_upload_trailer_publish_exclusive( $pub_b, $pub_final );
assert_error( $second, 'already_exists', 'second exclusive publish refuses same final' );
assert_true( hash_file( 'sha256', $pub_final ) === $final_hash, 'final unchanged after losing race' );
assert_true( is_file( $pub_b ), 'losing temp left for caller cleanup (not deleted destination)' );
@unlink( $pub_b );
if ( is_file( $pub_a ) ) {
	@unlink( $pub_a );
}

echo "\nHTTP path: require ffprobe (magic alone insufficient)\n";
$http_opts_no_probe = array(
	'max_bytes'         => 1024 * 1024,
	'require_ffprobe'   => true,
	'ffprobe_runner'    => null,
	'ffprobe_config'    => array( 'bin' => '/nonexistent/ffprobe-media-upload-test' ),
);
$no_probe = media_upload_trailer_place(
	$dir,
	'magic-only.mp4',
	$src,
	$sha,
	$tmp,
	$movie_root,
	$series_root,
	$http_opts_no_probe
);
assert_error( $no_probe, 'ffprobe_missing', 'HTTP-equivalent path rejects when ffprobe unavailable' );
assert_true( ! is_file( $ok_movie . '/Trailer/magic-only.mp4' ), 'magic-only file not written without probe' );

$probe_no_video = static function ( array $argv ): array {
	return array(
		'exit'   => 0,
		'stdout' => json_encode(
			array(
				'format'  => array( 'duration' => '1.0' ),
				'streams' => array(
					array(
						'codec_type' => 'audio',
						'codec_name' => 'aac',
					),
				),
			)
		),
		'stderr' => '',
	);
};
$audio_only = media_upload_trailer_place(
	$dir,
	'audio-only.mp4',
	$src,
	$sha,
	$tmp,
	$movie_root,
	$series_root,
	array(
		'max_bytes'       => 1024 * 1024,
		'require_ffprobe' => true,
		'ffprobe_runner'  => $probe_no_video,
	)
);
assert_error( $audio_only, 'invalid_media', 'rejects probe result with no video stream' );

$ffprobe_bin = MEDIA_FFPROBE_DEFAULT_BIN;
$ffprobe_ok  = media_ffprobe_bin_usable( $ffprobe_bin );
echo $ffprobe_ok
	? "  info  ffprobe available at {$ffprobe_bin}\n"
	: "  info  ffprobe NOT available in this environment (skipped live probe success)\n";

if ( $ffprobe_ok ) {
	// Real ffprobe will reject our tiny magic stub as invalid media — that still
	// proves the HTTP path does not accept magic-only fakes. Prefer a runner that
	// simulates a valid video only when the binary is present for path coverage.
	$live = media_upload_trailer_place(
		$dir,
		'live-probe-stub.mp4',
		$src,
		$sha,
		$tmp,
		$movie_root,
		$series_root,
		array(
			'max_bytes'       => 1024 * 1024,
			'require_ffprobe' => true,
			'ffprobe_config'  => array( 'bin' => $ffprobe_bin ),
		)
	);
	assert_true(
		( $live['ok'] ?? true ) === false && in_array( (string) ( $live['code'] ?? '' ), array( 'invalid_media', 'ffprobe_missing' ), true ),
		'live ffprobe rejects magic-only stub (not treated as valid video)'
	);
	assert_true( ! is_file( $ok_movie . '/Trailer/live-probe-stub.mp4' ), 'rejected live-probe stub not left on disk' );
}

// HTTP-equivalent success still requires an injectable trusted probe (or real video).
$http_ok = media_upload_trailer_place(
	$dir,
	'http-ok.mp4',
	$src,
	$sha,
	$tmp,
	$movie_root,
	$series_root,
	array(
		'max_bytes'       => 1024 * 1024,
		'require_ffprobe' => true,
		'ffprobe_runner'  => $probe_runner,
	)
);
assert_true( ( $http_ok['ok'] ?? false ) === true, 'HTTP-equivalent path accepts when probe confirms video' );

echo "\nseries title placement\n";
$series_dir = 'series/Chin/2025/Dream.Show';
$s_ok = media_upload_trailer_place(
	$series_dir,
	'teaser.webm',
	$mkv_src,
	$mkv_sha,
	$tmp,
	$movie_root,
	$series_root,
	array(
		'max_bytes'       => 1024 * 1024,
		'require_ffprobe' => true,
		'ffprobe_runner'  => $probe_runner,
	)
);
assert_true( ( $s_ok['ok'] ?? false ) === true, 'places under series Trailer/' );
assert_true(
	( $s_ok['path'] ?? '' ) === 'series/Chin/2025/Dream.Show/Trailer/teaser.webm',
	'series relative path'
);

echo "\nHTTP error mapping (no secrets/paths)\n";
$e = media_upload_http_error_for_code( 'already_exists' );
assert_true( $e['status'] === 409 && $e['code'] === 'already_exists', '409 conflict' );
$e = media_upload_http_error_for_code( 'too_large' );
assert_true( $e['status'] === 413, '413 too large' );
$e = media_upload_http_error_for_code( 'root_not_found' );
assert_true( $e['status'] === 500 && ! str_contains( $e['message'], '/' ), '500 has no path' );
$e = media_upload_http_error_for_code( 'absolute_path' );
assert_true( $e['status'] === 400, '400 absolute' );
$e = media_upload_http_error_for_code( 'ffprobe_missing' );
assert_true( $e['status'] === 500 && $e['code'] === 'upload_failed', 'ffprobe_missing maps to safe 500' );

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

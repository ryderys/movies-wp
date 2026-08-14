<?php
/**
 * CLI tests for media_ffprobe_inspect().
 *
 * Run: php media-server/tests/ffprobe-test.php
 *
 * Uses an injectable runner — no real ffprobe binary required.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/ffprobe.php';

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
 * @param array<string, mixed> $payload
 */
function make_runner( array $payload, int $exit = 0, string $stderr = '' ): callable {
	$json = json_encode( $payload, JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $json ) ) {
		throw new RuntimeException( 'fixture encode failed' );
	}

	return static function ( array $argv ) use ( $json, $exit, $stderr ): array {
		assert_true( in_array( '-print_format', $argv, true ), 'argv includes -print_format' );
		assert_true( in_array( 'json', $argv, true ), 'argv includes json' );
		assert_true( in_array( '-show_streams', $argv, true ), 'argv includes -show_streams' );
		assert_true( in_array( '--', $argv, true ), 'argv includes -- separator' );
		return array(
			'exit'   => $exit,
			'stdout' => $json,
			'stderr' => $stderr,
		);
	};
}

$tmp = sys_get_temp_dir() . '/ffprobe-test-' . bin2hex( random_bytes( 4 ) );
if ( ! mkdir( $tmp, 0777, true ) ) {
	fwrite( STDERR, "Could not create {$tmp}\n" );
	exit( 1 );
}
$sample = $tmp . '/Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.mkv';
file_put_contents( $sample, 'not-a-real-mkv' );

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

$believer_probe = array(
	'format'  => array(
		'duration' => '6152.48',
	),
	'streams' => array(
		array(
			'codec_type' => 'video',
			'codec_name' => 'h264',
			'width'      => 1920,
			'height'     => 1080,
		),
		array(
			'codec_type' => 'audio',
			'codec_name' => 'aac',
			'channels'   => 2,
			'tags'       => array( 'language' => 'fa' ),
		),
		array(
			'codec_type' => 'audio',
			'codec_name' => 'aac',
			'channels'   => 2,
			'tags'       => array( 'language' => 'en' ),
		),
	),
);

echo "happy path\n";
$result = media_ffprobe_inspect( $sample, array( 'bin' => '/usr/bin/ffprobe' ), make_runner( $believer_probe ) );
assert_true( ( $result['ok'] ?? false ) === true, 'ok true' );
assert_true( ( $result['duration'] ?? null ) === 6152, 'duration rounded seconds' );
assert_true( is_array( $result['video'] ?? null ), 'video present' );
assert_true( ( $result['video']['codec'] ?? null ) === 'h264', 'video codec' );
assert_true( ( $result['video']['width'] ?? null ) === 1920, 'video width' );
assert_true( ( $result['video']['height'] ?? null ) === 1080, 'video height' );
assert_true( count( $result['audio'] ?? array() ) === 2, 'two audio tracks' );
assert_true( ( $result['audio'][0]['language'] ?? null ) === 'fa', 'first audio fa' );
assert_true( ( $result['audio'][0]['codec'] ?? null ) === 'aac', 'first audio codec' );
assert_true( ( $result['audio'][0]['channels'] ?? null ) === 2, 'first audio channels' );
assert_true( ( $result['audio'][1]['language'] ?? null ) === 'en', 'second audio en' );
assert_true( ( $result['subtitles'] ?? null ) === array(), 'no embedded subs' );

echo "\nmissing language tag stays null\n";
$no_lang = array(
	'format'  => array( 'duration' => '10' ),
	'streams' => array(
		array(
			'codec_type' => 'audio',
			'codec_name' => 'aac',
			'channels'   => 2,
		),
		array(
			'codec_type' => 'subtitle',
			'codec_name' => 'subrip',
			'tags'       => array( 'language' => 'und' ),
		),
	),
);
$nol = media_ffprobe_inspect( $sample, array(), make_runner( $no_lang ) );
assert_true( ( $nol['ok'] ?? false ) === true, 'no-lang ok' );
assert_true( ( $nol['audio'][0]['language'] ?? 'x' ) === null, 'audio language null when missing' );
assert_true( ( $nol['subtitles'][0]['language'] ?? null ) === 'und', 'und kept as reported, not remapped' );
assert_true( ( $nol['video'] ?? 'x' ) === null, 'no video stream → null' );

echo "\nfirst video stream wins\n";
$two_video = array(
	'streams' => array(
		array(
			'codec_type' => 'video',
			'codec_name' => 'h264',
			'width'      => 1920,
			'height'     => 1080,
		),
		array(
			'codec_type' => 'video',
			'codec_name' => 'hevc',
			'width'      => 3840,
			'height'     => 2160,
		),
	),
);
$tv = media_ffprobe_inspect( $sample, array(), make_runner( $two_video ) );
assert_true( ( $tv['video']['codec'] ?? null ) === 'h264', 'first video only' );
assert_true( ( $tv['video']['width'] ?? null ) === 1920, 'first video dimensions' );

echo "\npath validation\n";
$rel = media_ffprobe_inspect( 'Movie/Korea/2023/Believer.2/file.mkv', array(), make_runner( $believer_probe ) );
assert_true( ( $rel['ok'] ?? true ) === false && ( $rel['code'] ?? '' ) === 'invalid_path', 'relative path rejected' );

$dots = media_ffprobe_inspect( $tmp . '/../ffprobe-escape.mkv', array(), make_runner( $believer_probe ) );
assert_true( ( $dots['ok'] ?? true ) === false && ( $dots['code'] ?? '' ) === 'invalid_path', '.. segment rejected' );

$missing = media_ffprobe_inspect( $tmp . '/does-not-exist.mkv', array(), make_runner( $believer_probe ) );
assert_true( ( $missing['ok'] ?? true ) === false && ( $missing['code'] ?? '' ) === 'invalid_path', 'missing file rejected' );

echo "\nrunner failures\n";
$failed = media_ffprobe_inspect(
	$sample,
	array(),
	static function ( array $argv ): array {
		return array(
			'exit'   => 1,
			'stdout' => '',
			'stderr' => 'Invalid data found when processing input',
		);
	}
);
assert_true( ( $failed['ok'] ?? true ) === false && ( $failed['code'] ?? '' ) === 'ffprobe_failed', 'non-zero exit → ffprobe_failed' );

$bad_json = media_ffprobe_inspect(
	$sample,
	array(),
	static function ( array $argv ): array {
		return array(
			'exit'   => 0,
			'stdout' => 'not-json{',
			'stderr' => '',
		);
	}
);
assert_true( ( $bad_json['ok'] ?? true ) === false && ( $bad_json['code'] ?? '' ) === 'ffprobe_bad_json', 'bad json' );

$oversize = media_ffprobe_inspect(
	$sample,
	array( 'max_output_bytes' => 32 ),
	static function ( array $argv ): array {
		return array(
			'exit'   => 0,
			'stdout' => str_repeat( 'x', 64 ),
			'stderr' => '',
		);
	}
);
assert_true(
	( $oversize['ok'] ?? true ) === false && ( $oversize['code'] ?? '' ) === 'ffprobe_output_too_large',
	'oversize stdout'
);

$timeout = media_ffprobe_inspect(
	$sample,
	array(),
	static function ( array $argv ): array {
		return array(
			'exit'    => -1,
			'stdout'  => '',
			'stderr'  => '',
			'code'    => 'ffprobe_timeout',
			'message' => 'ffprobe timed out after 15 seconds.',
		);
	}
);
assert_true( ( $timeout['ok'] ?? true ) === false && ( $timeout['code'] ?? '' ) === 'ffprobe_timeout', 'timeout code passthrough' );

echo "\nmissing bin without runner\n";
$missing_bin = media_ffprobe_inspect(
	$sample,
	array( 'bin' => '/definitely/not/ffprobe-' . bin2hex( random_bytes( 4 ) ) )
);
assert_true(
	( $missing_bin['ok'] ?? true ) === false && ( $missing_bin['code'] ?? '' ) === 'ffprobe_missing',
	'missing bin → ffprobe_missing'
);

$cleanup();

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

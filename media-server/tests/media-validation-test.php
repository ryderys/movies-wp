<?php
/**
 * CLI tests for media_validate_video_file().
 *
 * Run: php media-server/tests/media-validation-test.php
 *
 * Pure unit tests — no filesystem, ffprobe, HTTP, WordPress, or Streamit.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/media-validation.php';

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
 * @param list<array{code: string}> $warnings
 * @return list<string>
 */
function codes( array $warnings ): array {
	return array_values( array_column( $warnings, 'code' ) );
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function base_video( array $overrides = array() ): array {
	$file = array(
		'kind'             => 'video',
		'name'             => 'Sample.1080p.WEB-DL.H.264.mkv',
		'quality'          => '1080p',
		'source_type'      => 'WEB-DL',
		'video_codec'      => 'H.264',
		'audio_codec'      => null,
		'audio_languages'  => array(),
		'audio_label'      => null,
		'audio_confidence' => 'unknown',
		'probe'            => array(
			'ok'        => true,
			'duration'  => 6152,
			'video'     => array(
				'codec'  => 'h264',
				'width'  => 1920,
				'height' => 1080,
			),
			'audio'     => array(
				array(
					'language' => 'fa',
					'codec'    => 'aac',
					'channels' => 2,
				),
			),
			'subtitles' => array(),
		),
	);

	foreach ( $overrides as $key => $value ) {
		if ( $key === 'probe' && is_array( $value ) && is_array( $file['probe'] ) ) {
			$file['probe'] = array_replace_recursive( $file['probe'], $value );
			continue;
		}
		$file[ $key ] = $value;
	}

	return $file;
}

echo "matching 1080p + h264\n";
$v = media_validate_video_file( base_video() );
assert_true( $v['errors'] === array(), 'no errors' );
assert_true( ! in_array( 'filename_probe_resolution_mismatch', codes( $v['warnings'] ), true ), 'no resolution mismatch' );
assert_true( ! in_array( 'filename_probe_video_codec_mismatch', codes( $v['warnings'] ), true ), 'no codec mismatch' );
assert_true( ( $v['facts']['probe_resolution']['quality_class'] ?? null ) === '1080p', 'resolution fact 1080p' );
assert_true( ( $v['facts']['probe_audio_languages'] ?? null ) === array( 'fa' ), 'audio langs in facts' );
assert_true( ! in_array( 'probe_audio_language_detected', codes( $v['warnings'] ), true ), 'probe lang fact is not a user-facing warning' );

echo "\nH.264 vs hevc mismatch\n";
$v = media_validate_video_file(
	base_video(
		array(
			'probe' => array(
				'video' => array( 'codec' => 'hevc', 'width' => 1920, 'height' => 1080 ),
			),
		)
	)
);
assert_true( in_array( 'filename_probe_video_codec_mismatch', codes( $v['warnings'] ), true ), 'codec mismatch warned' );
$codec_w = null;
foreach ( $v['warnings'] as $w ) {
	if ( ( $w['code'] ?? '' ) === 'filename_probe_video_codec_mismatch' ) {
		$codec_w = $w;
		break;
	}
}
assert_true( is_array( $codec_w ) && ( $codec_w['filename_value'] ?? null ) === 'H.264', 'filename_value preserved' );
assert_true( is_array( $codec_w ) && ( $codec_w['probe_value'] ?? null ) === 'hevc', 'probe_value preserved' );

echo "\ncodec aliases match\n";
foreach ( array( 'H264', 'AVC', 'h264' ) as $alias ) {
	$v = media_validate_video_file( base_video( array( 'video_codec' => $alias ) ) );
	assert_true(
		! in_array( 'filename_probe_video_codec_mismatch', codes( $v['warnings'] ), true ),
		"alias {$alias} matches h264"
	);
}
$v = media_validate_video_file(
	base_video(
		array(
			'video_codec' => 'H.265',
			'probe'       => array( 'video' => array( 'codec' => 'hevc', 'width' => 1920, 'height' => 1080 ) ),
		)
	)
);
assert_true( ! in_array( 'filename_probe_video_codec_mismatch', codes( $v['warnings'] ), true ), 'H.265 vs hevc same' );

echo "\nresolution mismatch\n";
$v = media_validate_video_file(
	base_video(
		array(
			'quality' => '1080p',
			'probe'   => array( 'video' => array( 'codec' => 'h264', 'width' => 1280, 'height' => 720 ) ),
		)
	)
);
assert_true( in_array( 'filename_probe_resolution_mismatch', codes( $v['warnings'] ), true ), '1080p vs 720p mismatch' );
assert_true( ( $v['facts']['probe_resolution']['quality_class'] ?? null ) === '720p', 'class is 720p' );

$v = media_validate_video_file(
	base_video(
		array(
			'quality' => '720p',
			'probe'   => array( 'video' => array( 'codec' => 'h264', 'width' => 1920, 'height' => 1080 ) ),
		)
	)
);
assert_true( in_array( 'filename_probe_resolution_mismatch', codes( $v['warnings'] ), true ), '720p vs 1080p mismatch' );

echo "\nnull quality is not an error\n";
$v = media_validate_video_file( base_video( array( 'quality' => null ) ) );
assert_true( ! in_array( 'filename_probe_resolution_mismatch', codes( $v['warnings'] ), true ), 'no mismatch without filename quality' );
assert_true( in_array( 'probe_resolution_detected', codes( $v['warnings'] ), true ), 'probe_resolution_detected informational' );
assert_true( ( $v['facts']['probe_resolution']['quality_class'] ?? null ) === '1080p', 'fact still present' );

echo "\naudio: Persian Dub vs fa consistent\n";
$v = media_validate_video_file(
	base_video(
		array(
			'audio_languages'  => array( 'fa' ),
			'audio_label'      => 'Persian Dub',
			'audio_confidence' => 'high',
		)
	)
);
assert_true( ! in_array( 'filename_probe_audio_mismatch', codes( $v['warnings'] ), true ), 'Persian Dub + fa consistent' );
assert_true( ! in_array( 'probe_audio_language_detected', codes( $v['warnings'] ), true ), 'no detected-info when filename known' );

echo "\naudio: Persian Dub vs only en mismatch\n";
$v = media_validate_video_file(
	base_video(
		array(
			'audio_languages'  => array( 'fa' ),
			'audio_label'      => 'Persian Dub',
			'audio_confidence' => 'high',
			'probe'            => array(
				'audio' => array(
					array( 'language' => 'en', 'codec' => 'aac', 'channels' => 2 ),
				),
			),
		)
	)
);
assert_true( in_array( 'filename_probe_audio_mismatch', codes( $v['warnings'] ), true ), 'Persian Dub vs en mismatch' );

echo "\naudio: English vs only fa mismatch\n";
$v = media_validate_video_file(
	base_video(
		array(
			'audio_languages'  => array( 'en' ),
			'audio_label'      => 'English',
			'audio_confidence' => 'high',
			'probe'            => array(
				'audio' => array(
					array( 'language' => 'fa', 'codec' => 'aac', 'channels' => 2 ),
				),
			),
		)
	)
);
assert_true( in_array( 'filename_probe_audio_mismatch', codes( $v['warnings'] ), true ), 'English vs fa mismatch' );

echo "\naudio: Dual Audio with two tagged tracks\n";
$v = media_validate_video_file(
	base_video(
		array(
			'audio_languages'  => array(),
			'audio_label'      => 'Dual Audio',
			'audio_confidence' => 'high',
			'probe'            => array(
				'audio' => array(
					array( 'language' => 'fa', 'codec' => 'aac', 'channels' => 2 ),
					array( 'language' => 'en', 'codec' => 'aac', 'channels' => 2 ),
				),
			),
		)
	)
);
assert_true( ! in_array( 'filename_probe_audio_mismatch', codes( $v['warnings'] ), true ), 'Dual Audio + 2 tracks ok' );

echo "\naudio: Dual Audio with one track mismatch\n";
$v = media_validate_video_file(
	base_video(
		array(
			'audio_label'      => 'Dual Audio',
			'audio_confidence' => 'high',
			'probe'            => array(
				'audio' => array(
					array( 'language' => 'en', 'codec' => 'aac', 'channels' => 2 ),
				),
			),
		)
	)
);
assert_true( in_array( 'filename_probe_audio_mismatch', codes( $v['warnings'] ), true ), 'Dual Audio + 1 track mismatch' );

echo "\naudio: und is unknown\n";
$v = media_validate_video_file(
	base_video(
		array(
			'probe' => array(
				'audio' => array(
					array( 'language' => 'und', 'codec' => 'aac', 'channels' => 2 ),
					array( 'language' => null, 'codec' => 'aac', 'channels' => 2 ),
				),
			),
		)
	)
);
assert_true( ( $v['facts']['probe_audio_languages'] ?? null ) === array(), 'und/null not listed as languages' );
assert_true( in_array( 'probe_audio_language_unknown', codes( $v['warnings'] ), true ), 'probe_audio_language_unknown' );

echo "\nmissing video stream\n";
$v = media_validate_video_file(
	base_video(
		array(
			'probe' => array(
				'video' => null,
				'audio' => array(),
			),
		)
	)
);
assert_true( in_array( 'probe_missing_video_stream', codes( $v['warnings'] ), true ), 'probe_missing_video_stream' );

echo "\nfailed probe → no comparisons\n";
$v = media_validate_video_file(
	base_video(
		array(
			'probe' => array(
				'ok'      => false,
				'code'    => 'ffprobe_failed',
				'message' => 'boom',
				'video'   => null,
				'audio'   => array(),
			),
		)
	)
);
assert_true( $v['warnings'] === array(), 'no validation warnings when probe failed' );
assert_true( $v['facts'] === array(), 'no facts when probe failed' );

echo "\nno mutation\n";
$original = base_video(
	array(
		'video_codec'      => 'H.264',
		'audio_confidence' => 'unknown',
	)
);
$before = $original;
media_validate_video_file( $original );
assert_true( $original === $before, 'input file not mutated' );
assert_true( ( $original['video_codec'] ?? null ) === 'H.264', 'video_codec unchanged' );
assert_true( ( $original['audio_languages'] ?? null ) === array(), 'audio_languages unchanged' );

echo "\nnon-video skipped\n";
$v = media_validate_video_file(
	array(
		'kind'          => 'subtitle',
		'subtitle_lang' => 'fa',
		'probe'         => array( 'ok' => true, 'video' => null, 'audio' => array() ),
	)
);
assert_true( $v === array( 'errors' => array(), 'warnings' => array(), 'facts' => array() ), 'subtitle not validated' );

echo "\nAAC2.0 vs aac is not a video-codec warning\n";
$v = media_validate_video_file(
	base_video(
		array(
			'audio_codec' => 'AAC2.0',
			'probe'       => array(
				'audio' => array(
					array( 'language' => 'fa', 'codec' => 'aac', 'channels' => 2 ),
				),
			),
		)
	)
);
assert_true( ! in_array( 'filename_probe_video_codec_mismatch', codes( $v['warnings'] ), true ), 'audio_codec not compared as video' );

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

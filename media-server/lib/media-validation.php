<?php
/**
 * Read-only validation: compare filename-parser hints with nested ffprobe facts.
 *
 * Does not mutate the input file. Does not choose a winner. Does not call TMDb.
 * Does not rewrite quality / codecs / audio fields on the detected file.
 *
 * @package movies-wp
 */

declare(strict_types=1);

/**
 * Validate one video file object (lister + parser + nested probe).
 *
 * @param array<string, mixed> $file Detected video file. Must not be mutated.
 * @return array{
 *   errors: list<array{code: string, message: string, filename_value?: mixed, probe_value?: mixed}>,
 *   warnings: list<array{code: string, message: string, filename_value?: mixed, probe_value?: mixed}>,
 *   facts: array<string, mixed>
 * }
 */
function media_validate_video_file( array $file ): array {
	$result = array(
		'errors'   => array(),
		'warnings' => array(),
		'facts'    => array(),
	);

	if ( ( $file['kind'] ?? '' ) !== 'video' ) {
		return $result;
	}

	$probe = isset( $file['probe'] ) && is_array( $file['probe'] ) ? $file['probe'] : null;

	if ( $probe === null || ( $probe['ok'] ?? false ) !== true ) {
		// No successful probe → no filename/probe comparisons. Keep empty facts.
		return $result;
	}

	$video = isset( $probe['video'] ) && is_array( $probe['video'] ) ? $probe['video'] : null;
	$audio = isset( $probe['audio'] ) && is_array( $probe['audio'] ) ? $probe['audio'] : array();

	if ( $video === null ) {
		$result['warnings'][] = media_validation_issue(
			'probe_missing_video_stream',
			'Probe succeeded but no video stream was found.'
		);
	} else {
		media_validation_resolution( $file, $video, $result );
		media_validation_video_codec( $file, $video, $result );
	}

	media_validation_audio( $file, $audio, $result );

	return $result;
}

/**
 * @param array{codec?: mixed, width?: mixed, height?: mixed} $video
 * @param array{errors: list, warnings: list, facts: array}   $result
 */
function media_validation_resolution( array $file, array $video, array &$result ): void {
	$width  = media_validation_positive_int( $video['width'] ?? null );
	$height = media_validation_positive_int( $video['height'] ?? null );

	if ( $width === null && $height === null ) {
		return;
	}

	$quality_class = media_validation_quality_class( $width, $height );
	$result['facts']['probe_resolution'] = array(
		'width'         => $width,
		'height'        => $height,
		'quality_class' => $quality_class,
	);

	$filename_quality = isset( $file['quality'] ) && is_string( $file['quality'] ) && $file['quality'] !== ''
		? $file['quality']
		: null;

	if ( $filename_quality === null ) {
		if ( $quality_class !== null ) {
			$result['warnings'][] = media_validation_issue(
				'probe_resolution_detected',
				'Filename has no quality token; probe resolution class is available as a fact only.',
				null,
				$quality_class
			);
		}
		return;
	}

	if ( $quality_class === null ) {
		return;
	}

	if ( $filename_quality !== $quality_class ) {
		$result['warnings'][] = media_validation_issue(
			'filename_probe_resolution_mismatch',
			'Filename quality and probed resolution class differ.',
			$filename_quality,
			$quality_class
		);
	}
}

/**
 * Conservative resolution → quality class for comparison only.
 */
function media_validation_quality_class( ?int $width, ?int $height ): ?string {
	$w = $width ?? 0;
	$h = $height ?? 0;

	if ( $w <= 0 && $h <= 0 ) {
		return null;
	}

	if ( $w >= 3840 || $h >= 2160 ) {
		return '2160p';
	}
	if ( $w >= 1920 || $h >= 1080 ) {
		return '1080p';
	}
	if ( $w >= 1280 || $h >= 720 ) {
		return '720p';
	}
	if ( $w >= 854 || $h >= 480 ) {
		return '480p';
	}
	if ( $w >= 640 || $h >= 360 ) {
		return '360p';
	}

	return null;
}

/**
 * @param array{codec?: mixed, width?: mixed, height?: mixed} $video
 * @param array{errors: list, warnings: list, facts: array}   $result
 */
function media_validation_video_codec( array $file, array $video, array &$result ): void {
	$filename_codec = isset( $file['video_codec'] ) && is_string( $file['video_codec'] ) && $file['video_codec'] !== ''
		? $file['video_codec']
		: null;
	$probe_codec = isset( $video['codec'] ) && is_string( $video['codec'] ) && $video['codec'] !== ''
		? $video['codec']
		: null;

	if ( $probe_codec !== null ) {
		$result['facts']['probe_video_codec'] = $probe_codec;
	}

	if ( $filename_codec === null || $probe_codec === null ) {
		return;
	}

	$fn = media_validation_normalize_video_codec( $filename_codec );
	$pv = media_validation_normalize_video_codec( $probe_codec );

	if ( $fn === null || $pv === null ) {
		// Unknown labels — do not invent a mismatch.
		return;
	}

	if ( $fn !== $pv ) {
		$result['warnings'][] = media_validation_issue(
			'filename_probe_video_codec_mismatch',
			'Filename codec and probed video codec differ.',
			$filename_codec,
			$probe_codec
		);
	}
}

/**
 * Normalize video codec identity for comparison only.
 */
function media_validation_normalize_video_codec( string $codec ): ?string {
	$key = strtolower( preg_replace( '/[^a-z0-9]/i', '', $codec ) ?? '' );

	$map = array(
		'h264'  => 'h264',
		'avc'   => 'h264',
		'avc1'  => 'h264',
		'x264'  => 'h264',
		'h265'  => 'hevc',
		'hevc'  => 'hevc',
		'x265'  => 'hevc',
		'av1'   => 'av1',
		'vp9'   => 'vp9',
		'vp8'   => 'vp8',
		'mpeg4' => 'mpeg4',
		'mpeg2' => 'mpeg2',
	);

	return $map[ $key ] ?? null;
}

/**
 * @param list<array<string, mixed>>             $audio
 * @param array{errors: list, warnings: list, facts: array} $result
 */
function media_validation_audio( array $file, array $audio, array &$result ): void {
	$probe_langs = media_validation_probe_audio_languages( $audio );
	$result['facts']['probe_audio_languages'] = $probe_langs;
	$result['facts']['probe_audio_track_count'] = count( $audio );

	$confidence = isset( $file['audio_confidence'] ) ? (string) $file['audio_confidence'] : 'unknown';
	$filename_langs = media_validation_filename_audio_languages( $file );
	$label = isset( $file['audio_label'] ) && is_string( $file['audio_label'] ) && $file['audio_label'] !== ''
		? $file['audio_label']
		: null;

	if ( count( $audio ) > 0 && $probe_langs === array() ) {
		$result['warnings'][] = media_validation_issue(
			'probe_audio_language_unknown',
			'Probe reports audio track(s) without language tags.'
		);
	}

	if ( $confidence === 'unknown' || ( $filename_langs === array() && $label === null ) ) {
		// Probe languages stay in facts only. This is not a user-facing import warning:
		// filename audio remains null; probe may still report kor/etc.
		return;
	}

	// Dual / Multi: require multiple audio tracks; languages optional.
	if ( $label !== null && media_validation_is_multi_audio_label( $label ) ) {
		if ( count( $audio ) < 2 ) {
			$result['warnings'][] = media_validation_issue(
				'filename_probe_audio_mismatch',
				'Filename claims multiple audio tracks but probe reports fewer than two.',
				$label,
				count( $audio )
			);
		}
		return;
	}

	if ( $filename_langs === array() ) {
		return;
	}

	if ( $probe_langs === array() ) {
		// Filename claims a language; probe has no tags — informational mismatch.
		$result['warnings'][] = media_validation_issue(
			'filename_probe_audio_mismatch',
			'Filename claims audio language(s) but probe has no tagged audio languages.',
			$filename_langs,
			$probe_langs
		);
		return;
	}

	sort( $filename_langs );
	$probe_sorted = $probe_langs;
	sort( $probe_sorted );

	// Consistent if every filename-claimed language appears in probe tags.
	// Extra probe languages are allowed (e.g. Persian Dub + en/fa tracks).
	$missing = array_values( array_diff( $filename_langs, $probe_sorted ) );
	if ( $missing !== array() ) {
		$result['warnings'][] = media_validation_issue(
			'filename_probe_audio_mismatch',
			'Filename audio language claim is not present in probed audio language tags.',
			$filename_langs,
			$probe_langs
		);
	}
}

/**
 * @param list<array<string, mixed>> $audio
 * @return list<string>
 */
function media_validation_probe_audio_languages( array $audio ): array {
	$out = array();
	foreach ( $audio as $track ) {
		if ( ! is_array( $track ) ) {
			continue;
		}
		$lang = $track['language'] ?? null;
		if ( ! is_string( $lang ) ) {
			continue;
		}
		$lang = strtolower( trim( $lang ) );
		if ( $lang === '' || $lang === 'und' ) {
			// und stays unknown — never mapped to a real language.
			continue;
		}
		if ( ! preg_match( '/^[a-z]{2,3}(-[a-z0-9]+)?$/', $lang ) ) {
			continue;
		}
		$out[] = $lang;
	}

	return array_values( array_unique( $out ) );
}

/**
 * @param array<string, mixed> $file
 * @return list<string>
 */
function media_validation_filename_audio_languages( array $file ): array {
	$raw = isset( $file['audio_languages'] ) && is_array( $file['audio_languages'] )
		? $file['audio_languages']
		: array();

	$out = array();
	foreach ( $raw as $lang ) {
		if ( ! is_string( $lang ) ) {
			continue;
		}
		$lang = strtolower( trim( $lang ) );
		if ( $lang === '' ) {
			continue;
		}
		$out[] = $lang;
	}

	return array_values( array_unique( $out ) );
}

function media_validation_is_multi_audio_label( string $label ): bool {
	$key = strtolower( preg_replace( '/\s+/', '', $label ) ?? '' );
	return in_array( $key, array( 'dualaudio', 'multi', 'multiaudio' ), true );
}

function media_validation_positive_int( mixed $value ): ?int {
	if ( is_int( $value ) && $value > 0 ) {
		return $value;
	}
	if ( is_float( $value ) && $value > 0 ) {
		return (int) round( $value );
	}
	if ( is_string( $value ) && is_numeric( $value ) ) {
		$n = (int) round( (float) $value );
		return $n > 0 ? $n : null;
	}
	return null;
}

/**
 * @param mixed $filename_value
 * @param mixed $probe_value
 * @return array{code: string, message: string, filename_value?: mixed, probe_value?: mixed}
 */
function media_validation_issue( string $code, string $message, mixed $filename_value = null, mixed $probe_value = null ): array {
	$issue = array(
		'code'    => $code,
		'message' => $message,
	);

	if ( func_num_args() >= 3 ) {
		$issue['filename_value'] = $filename_value;
	}
	if ( func_num_args() >= 4 ) {
		$issue['probe_value'] = $probe_value;
	}

	return $issue;
}

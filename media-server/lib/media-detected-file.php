<?php
/**
 * Pure normalization of an enriched scan file into a stable DetectedMediaFile contract.
 *
 * Input: one entry from media_scan_movie_dir()['files'] (lister + parser + probe + validation).
 * No filesystem, ffprobe, HTTP, WordPress, or Streamit access.
 * Does not mutate the input. Does not invent values. Does not choose winners.
 *
 * @package movies-wp
 */

declare(strict_types=1);

/**
 * Normalize one enriched scan file into the internal media-file contract.
 *
 * @param array<string, mixed> $file Enriched lister/parser/probe/validation file.
 * @return array<string, mixed>
 */
function media_normalize_detected_file( array $file ): array {
	$kind = isset( $file['kind'] ) ? (string) $file['kind'] : '';

	if ( $kind === 'subtitle' ) {
		return media_normalize_detected_subtitle( $file );
	}

	if ( $kind === 'video' ) {
		return media_normalize_detected_video( $file );
	}

	// Ignored / directory / unknown — pass through a minimal non-authoritative shell.
	return array(
		'kind'       => $kind !== '' ? $kind : 'unknown',
		'name'       => media_normalize_string_or_null( $file['name'] ?? null ),
		'media_path' => media_normalize_string_or_null( $file['media_path'] ?? null ),
		'extension'  => media_normalize_string_or_null( $file['extension'] ?? null ),
		'size_bytes' => media_normalize_int_or_null( $file['size_bytes'] ?? null ),
		'size_label' => media_normalize_string_or_null( $file['size_label'] ?? null ),
	);
}

/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function media_normalize_detected_video( array $file ): array {
	$probe = isset( $file['probe'] ) && is_array( $file['probe'] ) ? $file['probe'] : null;
	$probe_ok = is_array( $probe ) && ( $probe['ok'] ?? false ) === true;

	$probe_video = ( $probe_ok && isset( $probe['video'] ) && is_array( $probe['video'] ) )
		? $probe['video']
		: null;
	$probe_audio = ( $probe_ok && isset( $probe['audio'] ) && is_array( $probe['audio'] ) )
		? $probe['audio']
		: array();
	$probe_subs = ( $probe_ok && isset( $probe['subtitles'] ) && is_array( $probe['subtitles'] ) )
		? $probe['subtitles']
		: array();

	$validation = isset( $file['validation'] ) && is_array( $file['validation'] )
		? media_normalize_validation_block( $file['validation'] )
		: array(
			'errors'   => array(),
			'warnings' => array(),
			'facts'    => array(),
		);

	$filename_quality = media_normalize_string_or_null( $file['quality'] ?? null );
	$filename_codec   = media_normalize_string_or_null( $file['video_codec'] ?? null );
	$probe_codec      = $probe_video !== null
		? media_normalize_string_or_null( $probe_video['codec'] ?? null )
		: null;

	$width  = $probe_video !== null ? media_normalize_int_or_null( $probe_video['width'] ?? null ) : null;
	$height = $probe_video !== null ? media_normalize_int_or_null( $probe_video['height'] ?? null ) : null;
	$duration = $probe_ok ? media_normalize_int_or_null( $probe['duration'] ?? null ) : null;

	$audio_tracks = array();
	foreach ( $probe_audio as $track ) {
		if ( ! is_array( $track ) ) {
			continue;
		}
		$audio_tracks[] = array(
			'language' => media_normalize_probe_language( $track['language'] ?? null ),
			'codec'    => media_normalize_string_or_null( $track['codec'] ?? null ),
			'channels' => media_normalize_int_or_null( $track['channels'] ?? null ),
		);
	}

	$embedded = array();
	foreach ( $probe_subs as $sub ) {
		if ( ! is_array( $sub ) ) {
			continue;
		}
		$embedded[] = array(
			'language' => media_normalize_probe_language( $sub['language'] ?? null ),
			'codec'    => media_normalize_string_or_null( $sub['codec'] ?? null ),
		);
	}

	$confidence = isset( $file['audio_confidence'] ) && is_string( $file['audio_confidence'] ) && $file['audio_confidence'] !== ''
		? $file['audio_confidence']
		: 'unknown';

	$filename_audio_langs = array();
	if ( isset( $file['audio_languages'] ) && is_array( $file['audio_languages'] ) ) {
		foreach ( $file['audio_languages'] as $lang ) {
			if ( is_string( $lang ) && $lang !== '' ) {
				$filename_audio_langs[] = $lang;
			}
		}
	}

	return array(
		'kind'       => 'video',
		'name'       => media_normalize_string_or_null( $file['name'] ?? null ),
		'media_path' => media_normalize_string_or_null( $file['media_path'] ?? null ),
		'extension'  => media_normalize_string_or_null( $file['extension'] ?? null ),
		'size_bytes' => media_normalize_int_or_null( $file['size_bytes'] ?? null ),
		'size_label' => media_normalize_string_or_null( $file['size_label'] ?? null ),

		'identity' => array(
			'quality' => array(
				'value'  => $filename_quality,
				'source' => 'filename',
			),
			'source_type' => array(
				'value'  => media_normalize_string_or_null( $file['source_type'] ?? null ),
				'source' => 'filename',
			),
			'provider' => array(
				'value'  => media_normalize_string_or_null( $file['provider'] ?? null ),
				'source' => 'filename',
			),
		),

		'video' => array(
			// Factual codec from ffprobe when available.
			'codec' => array(
				'value'  => $probe_codec,
				'source' => 'ffprobe',
			),
			// Filename hint kept separately for comparison/debugging — never overwritten.
			'codec_filename' => array(
				'value'  => $filename_codec,
				'source' => 'filename',
			),
			'width'    => $width,
			'height'   => $height,
			'duration' => $duration,
			'probe_ok' => $probe_ok,
		),

		'audio' => array(
			'tracks'              => $audio_tracks,
			'label'               => media_normalize_string_or_null( $file['audio_label'] ?? null ),
			'confidence'          => $confidence,
			'languages_filename'  => $filename_audio_langs,
			'codec_filename'      => media_normalize_string_or_null( $file['audio_codec'] ?? null ),
		),

		// Sidecar association is a later phase — videos only carry embedded probe subs.
		'subtitles' => array(
			'embedded' => $embedded,
			'sidecar'  => null,
		),

		'release' => array(
			'encoder'       => media_normalize_string_or_null( $file['encoder'] ?? null ),
			'release_group' => media_normalize_string_or_null( $file['release_group'] ?? null ),
			'group_hint'    => media_normalize_string_or_null( $file['group_hint'] ?? null ),
			'unclassified'  => media_normalize_string_list( $file['unclassified'] ?? array() ),
		),

		'validation' => $validation,

		'hints' => array(
			'title' => media_normalize_string_or_null( $file['title_hint'] ?? null ),
			'year'  => media_normalize_int_or_null( $file['year_hint'] ?? null ),
		),

		'warnings' => media_normalize_warning_list( $file['warnings'] ?? array() ),
	);
}

/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function media_normalize_detected_subtitle( array $file ): array {
	$lang = media_normalize_string_or_null( $file['subtitle_lang'] ?? null );
	$confidence = isset( $file['subtitle_confidence'] ) && is_string( $file['subtitle_confidence'] ) && $file['subtitle_confidence'] !== ''
		? $file['subtitle_confidence']
		: 'unknown';
	$format = media_normalize_string_or_null( $file['extension'] ?? null );

	return array(
		'kind'       => 'subtitle',
		'name'       => media_normalize_string_or_null( $file['name'] ?? null ),
		'media_path' => media_normalize_string_or_null( $file['media_path'] ?? null ),
		'extension'  => $format,
		'size_bytes' => media_normalize_int_or_null( $file['size_bytes'] ?? null ),
		'size_label' => media_normalize_string_or_null( $file['size_label'] ?? null ),

		'subtitle' => array(
			'language' => array(
				'value'  => $lang,
				'source' => 'filename',
			),
			'confidence' => $confidence,
			'format'     => $format,
		),

		// Explicitly no audio inheritance / no video association in this phase.
		'audio' => null,
		'video' => null,

		'release' => array(
			'encoder'       => media_normalize_string_or_null( $file['encoder'] ?? null ),
			'release_group' => media_normalize_string_or_null( $file['release_group'] ?? null ),
			'group_hint'    => media_normalize_string_or_null( $file['group_hint'] ?? null ),
			'unclassified'  => media_normalize_string_list( $file['unclassified'] ?? array() ),
		),

		'hints' => array(
			'title' => media_normalize_string_or_null( $file['title_hint'] ?? null ),
			'year'  => media_normalize_int_or_null( $file['year_hint'] ?? null ),
		),

		'warnings' => media_normalize_warning_list( $file['warnings'] ?? array() ),
	);
}

/**
 * @param array<string, mixed> $validation
 * @return array{errors: list, warnings: list, facts: array}
 */
function media_normalize_validation_block( array $validation ): array {
	return array(
		'errors'   => media_normalize_warning_list( $validation['errors'] ?? array() ),
		'warnings' => media_normalize_warning_list( $validation['warnings'] ?? array() ),
		'facts'    => isset( $validation['facts'] ) && is_array( $validation['facts'] )
			? $validation['facts']
			: array(),
	);
}

/**
 * @param mixed $raw
 * @return list<array<string, mixed>>
 */
function media_normalize_warning_list( mixed $raw ): array {
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$out = array();
	foreach ( $raw as $row ) {
		if ( is_array( $row ) ) {
			$out[] = $row;
		}
	}
	return $out;
}

/**
 * @param mixed $raw
 * @return list<string>
 */
function media_normalize_string_list( mixed $raw ): array {
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$out = array();
	foreach ( $raw as $item ) {
		if ( is_string( $item ) && $item !== '' ) {
			$out[] = $item;
		}
	}
	return $out;
}

function media_normalize_string_or_null( mixed $value ): ?string {
	if ( ! is_string( $value ) ) {
		return null;
	}
	$value = trim( $value );
	return $value === '' ? null : $value;
}

function media_normalize_int_or_null( mixed $value ): ?int {
	if ( is_int( $value ) ) {
		return $value;
	}
	if ( is_float( $value ) ) {
		return (int) round( $value );
	}
	if ( is_string( $value ) && is_numeric( $value ) ) {
		return (int) round( (float) $value );
	}
	return null;
}

/**
 * Probe language tags: keep as-is except empty → null. Never map und to a real language.
 */
function media_normalize_probe_language( mixed $value ): ?string {
	if ( ! is_string( $value ) ) {
		return null;
	}
	$value = strtolower( trim( $value ) );
	if ( $value === '' ) {
		return null;
	}
	// Preserve "und" as the literal tag (still unknown for automation decisions).
	return $value;
}

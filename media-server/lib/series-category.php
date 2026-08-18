<?php
/**
 * Deterministic release/category classifier for Series title directories.
 *
 * Immediate child directory names only.
 *
 * @package movies-wp
 */

declare(strict_types=1);

/** @var list<string> */
const MEDIA_SERIES_QUALITY_TOKENS = array( '360p', '480p', '540p', '720p', '1080p', '2160p' );

/** @var list<string> */
const MEDIA_SERIES_SOURCE_TOKENS = array(
	'web-dl',
	'webdl',
	'web-rip',
	'webrip',
	'bluray',
	'blu-ray',
	'bdrip',
	'brrip',
	'hdtv',
	'hdrip',
	'dvdrip',
	'soft sub',
	'softsub',
);

/**
 * Classify one immediate child directory under a Series title directory.
 *
 * @return array{
 *   type: 'VIDEO_RELEASE'|'SUBTITLE'|'SUPPLEMENTARY'|'UNKNOWN',
 *   name: string,
 *   quality_hint: string|null,
 *   language_hint: string|null,
 *   warnings: list<array{code: string, message: string}>
 * }
 */
function media_classify_series_category( string $name ): array {
	$trimmed = trim( $name );
	$result  = array(
		'type'          => 'UNKNOWN',
		'name'          => $trimmed,
		'quality_hint'  => null,
		'language_hint' => null,
		'warnings'      => array(),
	);

	if ( $trimmed === '' ) {
		$result['warnings'][] = array(
			'code'    => 'unknown_category',
			'message' => 'Empty category directory name.',
		);
		return $result;
	}

	if ( strcasecmp( $trimmed, 'OST' ) === 0 ) {
		$result['type'] = 'SUPPLEMENTARY';
		return $result;
	}

	if ( media_series_category_is_subtitle( $trimmed, $result ) ) {
		return $result;
	}

	if ( media_series_category_is_video_release( $trimmed, $result ) ) {
		return $result;
	}

	$result['warnings'][] = array(
		'code'    => 'unknown_category',
		'message' => 'Unrecognized release/category directory: ' . $trimmed,
	);
	return $result;
}

/**
 * @param array{type: string, name: string, quality_hint: string|null, language_hint: string|null, warnings: list<array{code: string, message: string}>} $result
 */
function media_series_category_is_subtitle( string $name, array &$result ): bool {
	if ( ! preg_match( '/^SUB(?:[._-]([A-Za-z]{2,3}))?$/i', $name, $match ) ) {
		return false;
	}

	$result['type'] = 'SUBTITLE';
	if ( isset( $match[1] ) && $match[1] !== '' ) {
		$lang = strtoupper( $match[1] );
		if ( preg_match( '/^[A-Z]{2,3}$/', $lang ) === 1 ) {
			$result['language_hint'] = $lang;
		} else {
			$result['type']     = 'UNKNOWN';
			$result['warnings'][] = array(
				'code'    => 'unknown_category',
				'message' => 'Unrecognized subtitle category suffix: ' . $name,
			);
		}
	}
	return true;
}

/**
 * @param array{type: string, name: string, quality_hint: string|null, language_hint: string|null, warnings: list<array{code: string, message: string}>} $result
 */
function media_series_category_is_video_release( string $name, array &$result ): bool {
	$normalized = media_series_category_normalize_name( $name );

	$quality = null;
	foreach ( MEDIA_SERIES_QUALITY_TOKENS as $token ) {
		if ( str_contains( $normalized, $token ) ) {
			$quality = $token;
			break;
		}
	}

	$has_source = false;
	foreach ( MEDIA_SERIES_SOURCE_TOKENS as $token ) {
		if ( str_contains( $normalized, $token ) ) {
			$has_source = true;
			break;
		}
	}

	if ( $quality !== null && $has_source ) {
		$result['type']         = 'VIDEO_RELEASE';
		$result['quality_hint'] = $quality;
		return true;
	}

	return false;
}

function media_series_category_normalize_name( string $name ): string {
	$normalized = strtolower( str_replace( array( '.', '_' ), ' ', $name ) );
	return preg_replace( '/\s+/', ' ', $normalized ) ?? $normalized;
}

function media_series_category_extract_quality_hint( string $name ): ?string {
	$normalized = media_series_category_normalize_name( $name );
	foreach ( MEDIA_SERIES_QUALITY_TOKENS as $token ) {
		if ( str_contains( $normalized, $token ) ) {
			return $token;
		}
	}
	return null;
}

function media_series_filename_has_540p( string $filename ): bool {
	return (bool) preg_match( '/(?<![0-9])540p(?![0-9])/i', $filename );
}

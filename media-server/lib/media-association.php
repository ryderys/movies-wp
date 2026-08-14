<?php
/**
 * Conservative association of sidecar subtitle files to video files.
 *
 * Pure: no filesystem, ffprobe, HTTP, WordPress, or Streamit.
 * Does not mutate the input file list or its elements.
 * Does not invent matches from directory co-location alone.
 *
 * @package movies-wp
 */

declare(strict_types=1);

/**
 * Associate subtitle files with video files using conservative filename signals.
 *
 * @param list<array<string, mixed>> $files Enriched scan files (may include `detected`).
 * @return array{
 *   files: list<array<string, mixed>>,
 *   associations: list<array{subtitle: string, video: string, confidence: string, reason: string}>,
 *   unassociated_subtitles: list<string>,
 *   warnings: list<array{code: string, message: string, subtitle?: string, candidates?: list<string>}>
 * }
 */
function media_associate_movie_files( array $files ): array {
	$videos    = array();
	$subtitles = array();

	foreach ( $files as $file ) {
		if ( ! is_array( $file ) ) {
			continue;
		}
		$kind = media_association_kind( $file );
		$path = media_association_media_path( $file );
		if ( $path === null ) {
			continue;
		}
		if ( $kind === 'video' ) {
			$videos[] = $file;
		} elseif ( $kind === 'subtitle' ) {
			$subtitles[] = $file;
		}
	}

	$associations = array();
	$unassociated = array();
	$warnings     = array();

	foreach ( $subtitles as $subtitle ) {
		$sub_path = media_association_media_path( $subtitle );
		if ( $sub_path === null ) {
			continue;
		}

		$candidates = array();
		foreach ( $videos as $video ) {
			$video_path = media_association_media_path( $video );
			if ( $video_path === null ) {
				continue;
			}
			if ( media_association_is_match( $video, $subtitle ) ) {
				$candidates[] = $video_path;
			}
		}

		$candidates = array_values( array_unique( $candidates ) );

		if ( count( $candidates ) === 1 ) {
			$associations[] = array(
				'subtitle'   => $sub_path,
				'video'      => $candidates[0],
				'confidence' => 'high',
				'reason'     => 'exact_stem',
			);
			continue;
		}

		$unassociated[] = $sub_path;

		if ( count( $candidates ) > 1 ) {
			$warnings[] = array(
				'code'       => 'ambiguous_subtitle_match',
				'message'    => 'Subtitle matches more than one video; left unassociated.',
				'subtitle'   => $sub_path,
				'candidates' => $candidates,
			);
		}
	}

	return array(
		'files'                   => $files,
		'associations'            => $associations,
		'unassociated_subtitles'  => $unassociated,
		'warnings'                => $warnings,
	);
}

/**
 * @param array<string, mixed> $video
 * @param array<string, mixed> $subtitle
 */
function media_association_is_match( array $video, array $subtitle ): bool {
	$video_stem = media_association_stem( media_association_name( $video ) );
	$sub_stem   = media_association_stem( media_association_name( $subtitle ) );

	if ( $video_stem === null || $sub_stem === null ) {
		return false;
	}

	$version_stem = media_association_strip_subtitle_language_suffix( $sub_stem );
	if ( $version_stem === null || $version_stem === '' ) {
		return false;
	}

	if ( ! media_association_stems_equal( $video_stem, $version_stem ) ) {
		return false;
	}

	// Extra rejectors when both sides declare the same field and they conflict.
	if ( ! media_association_fields_compatible( $video, $subtitle ) ) {
		return false;
	}

	return true;
}

/**
 * @param array<string, mixed> $video
 * @param array<string, mixed> $subtitle
 */
function media_association_fields_compatible( array $video, array $subtitle ): bool {
	$pairs = array(
		array( media_association_quality( $video ), media_association_quality( $subtitle ) ),
		array( media_association_source_type( $video ), media_association_source_type( $subtitle ) ),
		array( media_association_provider( $video ), media_association_provider( $subtitle ) ),
		array( media_association_release_group( $video ), media_association_release_group( $subtitle ) ),
	);

	foreach ( $pairs as $pair ) {
		$left  = $pair[0];
		$right = $pair[1];
		if ( $left === null || $right === null ) {
			continue; // missing on one side → do not reject
		}
		if ( strcasecmp( $left, $right ) !== 0 ) {
			return false;
		}
	}

	return true;
}

/**
 * @param array<string, mixed> $file
 */
function media_association_kind( array $file ): string {
	if ( isset( $file['kind'] ) && is_string( $file['kind'] ) && $file['kind'] !== '' ) {
		return $file['kind'];
	}
	if ( isset( $file['detected']['kind'] ) && is_string( $file['detected']['kind'] ) ) {
		return $file['detected']['kind'];
	}
	return '';
}

/**
 * @param array<string, mixed> $file
 */
function media_association_media_path( array $file ): ?string {
	if ( isset( $file['media_path'] ) && is_string( $file['media_path'] ) && $file['media_path'] !== '' ) {
		return $file['media_path'];
	}
	if ( isset( $file['detected']['media_path'] ) && is_string( $file['detected']['media_path'] ) && $file['detected']['media_path'] !== '' ) {
		return $file['detected']['media_path'];
	}
	return null;
}

/**
 * @param array<string, mixed> $file
 */
function media_association_name( array $file ): ?string {
	if ( isset( $file['name'] ) && is_string( $file['name'] ) && $file['name'] !== '' ) {
		return $file['name'];
	}
	if ( isset( $file['detected']['name'] ) && is_string( $file['detected']['name'] ) && $file['detected']['name'] !== '' ) {
		return $file['detected']['name'];
	}
	$path = media_association_media_path( $file );
	if ( $path === null ) {
		return null;
	}
	$base = basename( str_replace( '\\', '/', $path ) );
	return $base !== '' ? $base : null;
}

/**
 * @param array<string, mixed> $file
 */
function media_association_quality( array $file ): ?string {
	if ( isset( $file['detected']['identity']['quality']['value'] ) && is_string( $file['detected']['identity']['quality']['value'] ) ) {
		$v = trim( $file['detected']['identity']['quality']['value'] );
		return $v !== '' ? $v : null;
	}
	if ( isset( $file['quality'] ) && is_string( $file['quality'] ) && $file['quality'] !== '' ) {
		return $file['quality'];
	}
	return null;
}

/**
 * @param array<string, mixed> $file
 */
function media_association_source_type( array $file ): ?string {
	if ( isset( $file['detected']['identity']['source_type']['value'] ) && is_string( $file['detected']['identity']['source_type']['value'] ) ) {
		$v = trim( $file['detected']['identity']['source_type']['value'] );
		return $v !== '' ? $v : null;
	}
	if ( isset( $file['source_type'] ) && is_string( $file['source_type'] ) && $file['source_type'] !== '' ) {
		return $file['source_type'];
	}
	return null;
}

/**
 * @param array<string, mixed> $file
 */
function media_association_provider( array $file ): ?string {
	if ( isset( $file['detected']['identity']['provider']['value'] ) && is_string( $file['detected']['identity']['provider']['value'] ) ) {
		$v = trim( $file['detected']['identity']['provider']['value'] );
		return $v !== '' ? $v : null;
	}
	if ( isset( $file['provider'] ) && is_string( $file['provider'] ) && $file['provider'] !== '' ) {
		return $file['provider'];
	}
	return null;
}

/**
 * @param array<string, mixed> $file
 */
function media_association_release_group( array $file ): ?string {
	if ( isset( $file['detected']['release']['release_group'] ) && is_string( $file['detected']['release']['release_group'] ) ) {
		$v = trim( $file['detected']['release']['release_group'] );
		return $v !== '' ? $v : null;
	}
	if ( isset( $file['release_group'] ) && is_string( $file['release_group'] ) && $file['release_group'] !== '' ) {
		return $file['release_group'];
	}
	return null;
}

function media_association_stem( ?string $name ): ?string {
	if ( $name === null || $name === '' ) {
		return null;
	}
	$name = str_replace( '\\', '/', $name );
	$base = basename( $name );
	$stem = pathinfo( $base, PATHINFO_FILENAME );
	if ( ! is_string( $stem ) || $stem === '' ) {
		return null;
	}
	return $stem;
}

/**
 * Remove a single trailing subtitle-language token (parser language keys only).
 */
function media_association_strip_subtitle_language_suffix( string $stem ): ?string {
	// Prefer splitting on '.' only so we don't destroy H.264-MARK style tokens.
	$dot_parts = explode( '.', $stem );
	if ( count( $dot_parts ) < 2 ) {
		return $stem;
	}

	$last = strtolower( (string) end( $dot_parts ) );
	if ( media_association_is_subtitle_language_token( $last ) ) {
		array_pop( $dot_parts );
		$joined = implode( '.', $dot_parts );
		return $joined !== '' ? $joined : null;
	}

	return $stem;
}

function media_association_is_subtitle_language_token( string $key ): bool {
	$key = strtolower( trim( $key ) );
	if ( $key === '' ) {
		return false;
	}

	// Same explicit codes/names as media_parse_detect_subtitle_language() — comparison only.
	static $keys = array(
		'fa'      => true,
		'fair'    => true,
		'farsi'   => true,
		'persian' => true,
		'en'      => true,
		'eng'     => true,
		'english' => true,
		'ko'      => true,
		'kor'     => true,
		'korean'  => true,
		'zh'      => true,
		'chi'     => true,
		'chinese' => true,
		'hi'      => true,
		'hin'     => true,
		'hindi'   => true,
		'fr'      => true,
		'fre'     => true,
		'french'  => true,
		'ja'      => true,
		'jpn'     => true,
		'es'      => true,
		'spa'     => true,
		'de'      => true,
		'ger'     => true,
		'ar'      => true,
		'ara'     => true,
		'ru'      => true,
		'rus'     => true,
		'tr'      => true,
		'tur'     => true,
	);

	return isset( $keys[ $key ] );
}

function media_association_stems_equal( string $a, string $b ): bool {
	return strcasecmp( $a, $b ) === 0;
}

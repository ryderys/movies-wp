<?php
/**
 * Series scan composer: resolve → list → S/E identity → parse → probe → group.
 *
 * Read-only. No HTTP, WordPress, or Streamit writes.
 *
 * @package movies-wp
 */

declare(strict_types=1);

require_once __DIR__ . '/series-list.php';
require_once __DIR__ . '/series-episode-identity.php';
require_once __DIR__ . '/series-subtitle-association.php';
require_once __DIR__ . '/filename-parser.php';
require_once __DIR__ . '/ffprobe.php';
require_once __DIR__ . '/media-validation.php';
require_once __DIR__ . '/media-detected-file.php';

/**
 * Scan a relative series directory into the Series media preview contract.
 *
 * @param array{
 *   ffprobe_runner?: callable,
 *   ffprobe_config?: array{bin?: string, timeout_seconds?: int, max_output_bytes?: int}
 * } $options
 * @return array<string, mixed>
 */
function media_scan_series_dir( string $relative, string $media_root, string $series_root, array $options = array() ): array {
	$listed = media_list_series_dir( $relative, $media_root, $series_root );
	if ( ( $listed['ok'] ?? false ) !== true ) {
		return $listed;
	}

	$ffprobe_runner = isset( $options['ffprobe_runner'] ) && is_callable( $options['ffprobe_runner'] )
		? $options['ffprobe_runner']
		: null;
	$ffprobe_config = isset( $options['ffprobe_config'] ) && is_array( $options['ffprobe_config'] )
		? $options['ffprobe_config']
		: array();

	$abs_dir = media_series_scan_absolute_dir( $media_root, (string) $listed['directory'] );
	$errors  = array();
	$warnings = isset( $listed['warnings'] ) && is_array( $listed['warnings'] ) ? $listed['warnings'] : array();
	$files   = array();

	foreach ( $listed['files'] as $file ) {
		if ( ! is_array( $file ) ) {
			continue;
		}
		$enriched = media_series_scan_enrich_file( $file, $abs_dir, $ffprobe_config, $ffprobe_runner );
		if ( ( $enriched['ok'] ?? true ) === false ) {
			$errors[] = array(
				'code'    => (string) ( $enriched['code'] ?? 'missing_episode_identity' ),
				'message' => (string) ( $enriched['message'] ?? 'Episode identity error.' ),
				'name'    => (string) ( $file['name'] ?? '' ),
			);
			continue;
		}
		unset( $enriched['ok'] );
		$files[] = $enriched;
	}

	$association = media_associate_series_subtitles( $files );
	foreach ( $association['warnings'] as $warning ) {
		$warnings[] = $warning;
	}

	$episodes = media_series_scan_group_episodes( $files, $association['subtitles_by_episode'] );
	$stats    = media_series_scan_stats( $files, $episodes, $errors, $warnings );
	$ready    = $errors === array();

	return array(
		'ok'       => true,
		'kind'     => 'series',
		'contract' => array(
			'kind'        => 'series_scan',
			'version'     => 1,
			'read_only'   => true,
			'description' => 'Read-only Series filesystem scan. No Streamit metadata is mutated.',
		),
		'input'    => array(
			'dir' => $relative,
		),
		'directory' => array(
			'path'         => $listed['directory'],
			'country'      => $listed['country'],
			'year'         => $listed['year'],
			'series_title' => $listed['series_title'],
		),
		'categories' => $listed['categories'],
		'files'      => $files,
		'episodes'   => $episodes,
		'warnings'   => $warnings,
		'errors'     => $errors,
		'stats'      => $stats,
		'ready'      => $ready,
	);
}

function media_series_scan_absolute_dir( string $media_root, string $directory ): ?string {
	$real_media = realpath( $media_root );
	if ( $real_media === false ) {
		return null;
	}
	$real_media = media_movie_dir_normalize_root( $real_media );
	$directory  = trim( str_replace( '\\', '/', $directory ), '/' );
	if ( $directory === '' || str_contains( $directory, "\0" ) ) {
		return null;
	}
	foreach ( explode( '/', $directory ) as $segment ) {
		if ( $segment === '' || $segment === '.' || $segment === '..' ) {
			return null;
		}
	}
	$candidate = $real_media . '/' . $directory;
	$real      = realpath( $candidate );
	if ( $real === false || ! is_dir( $real ) ) {
		return null;
	}
	$real = media_movie_dir_normalize_root( $real );
	if ( ! media_movie_dir_is_inside( $real, $real_media ) ) {
		return null;
	}
	return $real;
}

/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function media_series_scan_enrich_file( array $file, ?string $abs_dir, array $ffprobe_config, ?callable $ffprobe_runner ): array {
	$name = isset( $file['name'] ) ? (string) $file['name'] : '';
	$identity = media_parse_series_episode_identity( $name );
	if ( ( $identity['ok'] ?? false ) !== true ) {
		return array(
			'ok'      => false,
			'code'    => (string) ( $identity['code'] ?? 'missing_episode_identity' ),
			'message' => (string) ( $identity['message'] ?? 'Episode identity missing.' ),
		);
	}

	$file['episode'] = array(
		'season_number'  => $identity['season_number'],
		'episode_number' => $identity['episode_number'],
		'token'          => $identity['token'],
	);
	if ( ! empty( $identity['warnings'] ) && is_array( $identity['warnings'] ) ) {
		$file['warnings'] = media_series_scan_merge_warnings( $file, $identity['warnings'] );
	}

	$parse_name = (string) $identity['sanitized_filename'];
	try {
		$parsed = media_parse_filename( $parse_name );
	} catch ( Throwable $e ) {
		$file['warnings'] = media_series_scan_append_warning( $file, 'parse_failed', 'Filename parser threw: ' . $e->getMessage() );
		$parsed = media_parse_empty_result( media_parse_normalize( $parse_name ) );
	}

	if ( is_array( $parsed ) ) {
		$skip = array( 'kind' => true, 'input' => true, 'format' => true );
		foreach ( $parsed as $key => $value ) {
			if ( isset( $skip[ $key ] ) || str_starts_with( (string) $key, '_' ) ) {
				continue;
			}
			$file[ $key ] = $value;
		}
	}

	$quality = media_series_scan_resolve_quality( $name, $file );
	$file['quality'] = $quality['quality'];
	if ( $quality['warning'] !== null ) {
		$file['warnings'] = media_series_scan_append_warning(
			$file,
			(string) $quality['warning']['code'],
			(string) $quality['warning']['message']
		);
	}

	if ( ( $file['kind'] ?? '' ) === 'video' ) {
		$file = media_series_scan_attach_probe( $file, $abs_dir, $ffprobe_config, $ffprobe_runner );
		$file['validation'] = media_validate_video_file( $file );
	}

	if ( ( $file['kind'] ?? '' ) === 'subtitle' ) {
		$file['subtitle'] = media_series_scan_subtitle_meta( $file );
		$subtitle_extension = isset( $file['extension'] ) ? strtolower( (string) $file['extension'] ) : '';
		if ( ! media_series_subtitle_playback_supported( $subtitle_extension ) ) {
			$file['warnings'] = media_series_scan_append_warning(
				$file,
				'subtitle_playback_unsupported',
				'Subtitle format is storable but not supported by the current playback endpoint.'
			);
		}
	}

	$file['detected'] = media_normalize_detected_file( $file );
	return $file;
}

/**
 * @param array<string, mixed> $file
 * @return array{quality: ?string, warning: ?array{code: string, message: string}}
 */
function media_series_scan_resolve_quality( string $original_name, array $file ): array {
	$filename_quality = isset( $file['quality'] ) && is_string( $file['quality'] ) && $file['quality'] !== ''
		? $file['quality']
		: null;

	if ( $filename_quality === null && media_series_filename_has_540p( $original_name ) ) {
		$filename_quality = '540p';
	}

	$category_hint = isset( $file['quality_hint'] ) && is_string( $file['quality_hint'] ) && $file['quality_hint'] !== ''
		? $file['quality_hint']
		: null;

	if ( $filename_quality !== null ) {
		if ( $category_hint !== null && $category_hint !== $filename_quality ) {
			return array(
				'quality' => $filename_quality,
				'warning' => array(
					'code'    => 'category_filename_quality_conflict',
					'message' => 'Filename quality (' . $filename_quality . ') differs from category hint (' . $category_hint . ').',
				),
			);
		}
		return array( 'quality' => $filename_quality, 'warning' => null );
	}

	if ( $category_hint !== null ) {
		return array( 'quality' => $category_hint, 'warning' => null );
	}

	return array(
		'quality' => null,
		'warning' => array(
			'code'    => 'unsupported_quality',
			'message' => 'No supported quality token found.',
		),
	);
}

/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function media_series_scan_subtitle_meta( array $file ): array {
	$extension = isset( $file['extension'] ) ? strtolower( (string) $file['extension'] ) : '';
	$srclang   = isset( $file['subtitle_lang'] ) && is_string( $file['subtitle_lang'] ) && $file['subtitle_lang'] !== ''
		? strtolower( $file['subtitle_lang'] )
		: '';

	if ( $srclang === '' && isset( $file['language_hint'] ) && is_string( $file['language_hint'] ) ) {
		$srclang = strtolower( $file['language_hint'] );
	}

	$format = media_series_subtitle_format_from_extension( $extension );
	return array(
		'label'   => media_series_subtitle_format_label( $srclang ),
		'srclang' => $srclang,
		'url'     => (string) ( $file['media_path'] ?? '' ),
		'default' => 0,
		'format'  => $format,
	);
}

/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function media_series_scan_attach_probe( array $file, ?string $abs_dir, array $ffprobe_config, ?callable $ffprobe_runner ): array {
	$name = isset( $file['name'] ) ? (string) $file['name'] : '';
	$category_path = isset( $file['category_path'] ) ? (string) $file['category_path'] : '';

	if ( $abs_dir === null || $name === '' || str_contains( $name, '/' ) || str_contains( $name, '\\' ) ) {
		$probe = array(
			'ok'        => false,
			'code'      => 'invalid_path',
			'message'   => 'Could not resolve a safe absolute path for ffprobe.',
			'duration'  => null,
			'video'     => null,
			'audio'     => array(),
			'subtitles' => array(),
		);
		$file['probe'] = $probe;
		$file['warnings'] = media_series_scan_append_warning( $file, 'probe_failed', $probe['message'] );
		return $file;
	}

	$absolute = $abs_dir . '/' . str_replace( '\\', '/', $category_path ) . '/' . $name;
	$probe    = media_ffprobe_inspect( $absolute, $ffprobe_config, $ffprobe_runner );
	$file['probe'] = $probe;

	if ( ( $probe['ok'] ?? false ) !== true ) {
		$message = isset( $probe['message'] ) && is_string( $probe['message'] ) && $probe['message'] !== ''
			? $probe['message']
			: 'ffprobe failed for this video file.';
		$file['warnings'] = media_series_scan_append_warning( $file, 'probe_failed', $message );
	}

	return $file;
}

/**
 * @param list<array<string, mixed>> $files
 * @param array<string, list<array<string, mixed>>> $subtitles_by_episode
 * @return list<array<string, mixed>>
 */
function media_series_scan_group_episodes( array $files, array $subtitles_by_episode ): array {
	$groups = array();

	foreach ( $files as $file ) {
		if ( ! is_array( $file ) || ( $file['kind'] ?? '' ) !== 'video' ) {
			continue;
		}
		if ( ! isset( $file['episode'] ) || ! is_array( $file['episode'] ) ) {
			continue;
		}
		$key = (string) $file['episode']['season_number'] . ':' . (string) $file['episode']['episode_number'];
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array(
				'season_number'  => (string) $file['episode']['season_number'],
				'episode_number' => (string) $file['episode']['episode_number'],
				'token'          => (string) ( $file['episode']['token'] ?? '' ),
				'sources'        => array(),
				'subtitles'      => array(),
			);
		}
		$groups[ $key ]['sources'][] = $file;
	}

	foreach ( $subtitles_by_episode as $key => $subtitle_files ) {
		if ( ! isset( $groups[ $key ] ) ) {
			$parts = explode( ':', $key, 2 );
			$groups[ $key ] = array(
				'season_number'  => $parts[0] ?? '',
				'episode_number' => $parts[1] ?? '',
				'token'          => '',
				'sources'        => array(),
				'subtitles'      => array(),
			);
		}
		$groups[ $key ]['subtitles'] = $subtitle_files;
	}

	$episodes = array_values( $groups );
	usort(
		$episodes,
		static function ( array $a, array $b ): int {
			$season_cmp = (int) $a['season_number'] <=> (int) $b['season_number'];
			if ( $season_cmp !== 0 ) {
				return $season_cmp;
			}
			return (int) $a['episode_number'] <=> (int) $b['episode_number'];
		}
	);

	foreach ( $episodes as &$episode ) {
		$episode['source_count']   = count( $episode['sources'] );
		$episode['subtitle_count'] = count( $episode['subtitles'] );
	}
	unset( $episode );

	return $episodes;
}

/**
 * @param list<array<string, mixed>> $files
 * @param list<array<string, mixed>> $episodes
 * @param list<array<string, mixed>> $errors
 * @param list<array<string, mixed>> $warnings
 * @return array<string, int>
 */
function media_series_scan_stats( array $files, array $episodes, array $errors, array $warnings ): array {
	$video = 0;
	$subtitle = 0;
	foreach ( $files as $file ) {
		if ( ! is_array( $file ) ) {
			continue;
		}
		if ( ( $file['kind'] ?? '' ) === 'video' ) {
			++$video;
		} elseif ( ( $file['kind'] ?? '' ) === 'subtitle' ) {
			++$subtitle;
		}
	}

	$source_count = 0;
	foreach ( $episodes as $episode ) {
		$source_count += count( $episode['sources'] ?? array() );
	}

	return array(
		'file_count'      => count( $files ),
		'video_count'     => $video,
		'subtitle_count'  => $subtitle,
		'episode_count'   => count( $episodes ),
		'source_count'    => $source_count,
		'warning_count'   => count( $warnings ),
		'error_count'     => count( $errors ),
	);
}

/**
 * @param array<string, mixed> $file
 * @param list<array{code: string, message: string}> $extra
 * @return list<array{code: string, message: string}>
 */
function media_series_scan_merge_warnings( array $file, array $extra ): array {
	$warnings = isset( $file['warnings'] ) && is_array( $file['warnings'] ) ? $file['warnings'] : array();
	return array_merge( $warnings, $extra );
}

/**
 * @param array<string, mixed> $file
 * @return list<array{code: string, message: string}>
 */
function media_series_scan_append_warning( array $file, string $code, string $message ): array {
	$warnings = isset( $file['warnings'] ) && is_array( $file['warnings'] ) ? $file['warnings'] : array();
	$warnings[] = array(
		'code'    => $code,
		'message' => $message,
	);
	return $warnings;
}

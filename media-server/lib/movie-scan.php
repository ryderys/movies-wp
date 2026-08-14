<?php
/**
 * Thin composer: resolve → list → parse → probe (videos) → validate → normalize → merge.
 *
 * No HTTP, WordPress, Streamit, or TMDb.
 * Does not duplicate resolver, listing, filename-parser, ffprobe, validation, or normalize logic.
 * Raw enriched fields stay at the top level; the stable contract is nested under `detected`.
 *
 * @package movies-wp
 */

declare(strict_types=1);

require_once __DIR__ . '/movie-list.php';
require_once __DIR__ . '/filename-parser.php';
require_once __DIR__ . '/ffprobe.php';
require_once __DIR__ . '/media-validation.php';
require_once __DIR__ . '/media-detected-file.php';

/**
 * Scan a relative movie directory into a combined result.
 *
 * @param string $relative   Relative directory, e.g. Movie/Korea/2018/Vapor.
 * @param string $media_root MEDIA_ROOT.
 * @param string $movie_root MOVIE_ROOT.
 * @param array{
 *   ffprobe_runner?: callable,
 *   ffprobe_config?: array{bin?: string, timeout_seconds?: int, max_output_bytes?: int}
 * } $options Optional. `ffprobe_runner` is for tests only (same callable shape as media_ffprobe_inspect).
 * @return array{
 *   ok: true,
 *   kind: 'movie',
 *   directory: string,
 *   country: string,
 *   year: int,
 *   movie_name: string,
 *   warnings: list<array{code: string, message: string, name?: string}>,
 *   files: list<array<string, mixed>>
 * }|array{ok: false, code: string, message: string}
 */
function media_scan_movie_dir( string $relative, string $media_root, string $movie_root, array $options = array() ): array {
	$resolved = media_resolve_movie_dir( $relative, $media_root, $movie_root );
	if ( ( $resolved['ok'] ?? false ) !== true ) {
		return $resolved;
	}

	$listed = media_list_movie_dir( $relative, $media_root, $movie_root );
	if ( ( $listed['ok'] ?? false ) !== true ) {
		return $listed;
	}

	$ffprobe_runner = null;
	if ( isset( $options['ffprobe_runner'] ) && is_callable( $options['ffprobe_runner'] ) ) {
		$ffprobe_runner = $options['ffprobe_runner'];
	}

	$ffprobe_config = array();
	if ( isset( $options['ffprobe_config'] ) && is_array( $options['ffprobe_config'] ) ) {
		$ffprobe_config = $options['ffprobe_config'];
	}

	$abs_dir = media_scan_absolute_movie_dir( $media_root, (string) $resolved['directory'] );

	$files = array();
	foreach ( $listed['files'] as $file ) {
		$files[] = media_scan_enrich_file( $file, $abs_dir, $ffprobe_config, $ffprobe_runner );
	}

	return array(
		'ok'         => true,
		'kind'       => $resolved['kind'],
		'directory'  => $resolved['directory'],
		'country'    => $resolved['country'],
		'year'       => $resolved['year'],
		'movie_name' => $resolved['movie_name'],
		'warnings'   => $listed['warnings'],
		'files'      => $files,
	);
}

/**
 * Absolute path of a resolved movie directory under MEDIA_ROOT.
 *
 * @return string|null Absolute directory path, or null if unavailable.
 */
function media_scan_absolute_movie_dir( string $media_root, string $directory ): ?string {
	$real_media = realpath( $media_root );
	if ( $real_media === false ) {
		return null;
	}

	$real_media = media_movie_dir_normalize_root( $real_media );
	$directory  = str_replace( '\\', '/', $directory );
	$directory  = trim( $directory, '/' );

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
 * Merge parser metadata (and video probe/validation) into one lister entry, then
 * attach a nested normalized contract under `detected`. Top-level fields stay as-is.
 * Lister fields win on overlap with the parser. Probe stays nested under "probe".
 * Parser/probe failures become file warnings; the scan continues.
 *
 * @param array<string, mixed>                                                 $file
 * @param string|null                                                          $abs_dir Absolute movie directory, or null.
 * @param array{bin?: string, timeout_seconds?: int, max_output_bytes?: int}  $ffprobe_config
 * @param callable|null                                                        $ffprobe_runner
 * @return array<string, mixed>
 */
function media_scan_enrich_file( array $file, ?string $abs_dir = null, array $ffprobe_config = array(), ?callable $ffprobe_runner = null ): array {
	$kind = $file['kind'] ?? '';
	if ( $kind !== 'video' && $kind !== 'subtitle' ) {
		return $file;
	}

	$name = isset( $file['name'] ) ? (string) $file['name'] : '';

	try {
		$parsed = media_parse_filename( $name );
	} catch ( Throwable $e ) {
		$file['warnings'] = array(
			array(
				'code'    => 'parse_failed',
				'message' => 'Filename parser threw: ' . $e->getMessage(),
			),
		);
		return $file;
	}

	if ( ! is_array( $parsed ) ) {
		$file['warnings'] = array(
			array(
				'code'    => 'parse_failed',
				'message' => 'Filename parser returned a non-array result.',
			),
		);
		return $file;
	}

	$skip = array( 'kind' => true, 'input' => true, 'format' => true );

	foreach ( $parsed as $key => $value ) {
		if ( isset( $skip[ $key ] ) || str_starts_with( (string) $key, '_' ) ) {
			continue;
		}
		$file[ $key ] = $value;
	}

	if ( $kind === 'video' ) {
		$file = media_scan_attach_probe( $file, $abs_dir, $ffprobe_config, $ffprobe_runner );
		// Nested report only — never overwrites parser or probe fields.
		$file['validation'] = media_validate_video_file( $file );
	}

	// Additive contract: keep all raw fields; nest the stable model under detected.
	$file['detected'] = media_normalize_detected_file( $file );

	return $file;
}

/**
 * Attach nested ffprobe result for one video file. Never flattens into parser fields.
 *
 * @param array<string, mixed>                                                $file
 * @param string|null                                                         $abs_dir
 * @param array{bin?: string, timeout_seconds?: int, max_output_bytes?: int} $ffprobe_config
 * @param callable|null                                                       $ffprobe_runner
 * @return array<string, mixed>
 */
function media_scan_attach_probe( array $file, ?string $abs_dir, array $ffprobe_config, ?callable $ffprobe_runner ): array {
	$name = isset( $file['name'] ) ? (string) $file['name'] : '';

	if ( $abs_dir === null || $name === '' || str_contains( $name, '/' ) || str_contains( $name, '\\' ) || $name === '.' || $name === '..' ) {
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
		$file['warnings'] = media_scan_append_warning(
			$file,
			'probe_failed',
			$probe['message']
		);
		return $file;
	}

	$absolute = $abs_dir . '/' . $name;
	$probe    = media_ffprobe_inspect( $absolute, $ffprobe_config, $ffprobe_runner );
	$file['probe'] = $probe;

	if ( ( $probe['ok'] ?? false ) !== true ) {
		$message = isset( $probe['message'] ) && is_string( $probe['message'] ) && $probe['message'] !== ''
			? $probe['message']
			: 'ffprobe failed for this video file.';
		$file['warnings'] = media_scan_append_warning( $file, 'probe_failed', $message );
	}

	return $file;
}

/**
 * @param array<string, mixed> $file
 * @return list<array{code: string, message: string}>
 */
function media_scan_append_warning( array $file, string $code, string $message ): array {
	$warnings = isset( $file['warnings'] ) && is_array( $file['warnings'] ) ? $file['warnings'] : array();
	$warnings[] = array(
		'code'    => $code,
		'message' => $message,
	);
	return $warnings;
}

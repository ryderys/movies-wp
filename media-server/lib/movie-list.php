<?php
/**
 * Non-recursive listing of a validated movie directory.
 *
 * Classifies direct children by extension only. Does not parse quality,
 * source type, encoder, audio, or subtitle language.
 *
 * @package movies-wp
 */

declare(strict_types=1);

require_once __DIR__ . '/movie-dir.php';

/** @var list<string> */
const MEDIA_MOVIE_VIDEO_EXTENSIONS = array( 'mkv', 'mp4', 'avi' );

/** @var list<string> */
const MEDIA_MOVIE_SUBTITLE_EXTENSIONS = array( 'srt', 'ass', 'ssa', 'vtt', 'sub' );

/** @var list<string> */
const MEDIA_MOVIE_IGNORED_EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'webp', 'nfo', 'txt' );

/**
 * List direct children of a relative movie directory.
 *
 * Always runs media_resolve_movie_dir() first. Does not recurse.
 *
 * @param string $relative   Relative directory, e.g. Movie/Chin/2016/Bounty.Hunters.
 * @param string $media_root MEDIA_ROOT.
 * @param string $movie_root MOVIE_ROOT.
 * @return array{
 *   ok: true,
 *   kind: 'movie',
 *   directory: string,
 *   country: string,
 *   year: int,
 *   movie_name: string,
 *   warnings: list<array{code: string, message: string, name: string}>,
 *   files: list<array{
 *     name: string,
 *     media_path: string,
 *     extension: string,
 *     kind: 'video'|'subtitle'|'ignored'|'directory',
 *     size_bytes: int|null,
 *     size_label: string|null
 *   }>
 * }|array{ok: false, code: string, message: string}
 */
function media_list_movie_dir( string $relative, string $media_root, string $movie_root ): array {
	$resolved = media_resolve_movie_dir( $relative, $media_root, $movie_root );
	if ( ( $resolved['ok'] ?? false ) !== true ) {
		return $resolved;
	}

	$real_media = realpath( $media_root );
	if ( $real_media === false ) {
		return media_movie_dir_error( 'root_not_found', 'MEDIA_ROOT does not exist or is not a directory.' );
	}

	$real_media = media_movie_dir_normalize_root( $real_media );
	$abs_dir    = $real_media . '/' . $resolved['directory'];
	$real_dir   = realpath( $abs_dir );

	if ( $real_dir === false || ! is_dir( $real_dir ) ) {
		return media_movie_dir_error( 'not_found', 'Movie directory does not exist.' );
	}

	$real_dir = media_movie_dir_normalize_root( $real_dir );

	if ( ! media_movie_dir_is_inside( $real_dir, $real_media ) ) {
		return media_movie_dir_error( 'outside_media_root', 'Resolved path is outside MEDIA_ROOT.' );
	}

	$entries = @scandir( $real_dir );
	if ( ! is_array( $entries ) ) {
		return media_movie_dir_error( 'list_failed', 'Could not read movie directory.' );
	}

	$files    = array();
	$warnings = array();

	foreach ( $entries as $name ) {
		if ( $name === '.' || $name === '..' ) {
			continue;
		}

		if ( str_contains( $name, "\0" ) ) {
			$warnings[] = media_movie_list_warning( 'invalid_name', 'Skipped entry with a null byte.', $name );
			continue;
		}

		$child = $real_dir . '/' . $name;

		if ( is_link( $child ) ) {
			$target = realpath( $child );
			if ( $target === false ) {
				$files[]    = media_movie_list_entry( $resolved['directory'], $name, 'ignored', '', null );
				$warnings[] = media_movie_list_warning( 'broken_symlink', 'Skipped broken symlink.', $name );
				continue;
			}

			$target = media_movie_dir_normalize_root( $target );
			if ( ! media_movie_dir_is_inside( $target, $real_dir ) ) {
				$files[]    = media_movie_list_entry( $resolved['directory'], $name, 'ignored', '', null );
				$warnings[] = media_movie_list_warning(
					'symlink_outside',
					'Skipped symlink that points outside the movie directory.',
					$name
				);
				continue;
			}

			$stat_path = $target;
		} else {
			$stat_path = $child;
		}

		if ( is_dir( $stat_path ) ) {
			$files[]    = media_movie_list_entry( $resolved['directory'], $name, 'directory', '', null );
			$warnings[] = media_movie_list_warning(
				'unexpected_subdirectory',
				'Unexpected subdirectory; not scanned.',
				$name
			);
			continue;
		}

		if ( ! is_file( $stat_path ) ) {
			$files[]    = media_movie_list_entry( $resolved['directory'], $name, 'ignored', '', null );
			$warnings[] = media_movie_list_warning( 'unreadable', 'Skipped unreadable entry.', $name );
			continue;
		}

		$size = filesize( $stat_path );
		if ( $size === false ) {
			$files[]    = media_movie_list_entry( $resolved['directory'], $name, 'ignored', media_movie_list_extension( $name ), null );
			$warnings[] = media_movie_list_warning( 'size_failed', 'Could not read file size.', $name );
			continue;
		}

		$size      = (int) $size;
		$extension = media_movie_list_extension( $name );
		$kind      = media_movie_list_kind( $name, $extension );

		$files[] = media_movie_list_entry(
			$resolved['directory'],
			$name,
			$kind,
			$extension,
			$size
		);

		if ( $kind === 'video' ) {
			continue;
		}

		if ( media_movie_list_is_sample_or_trailer( $name ) && in_array( $extension, MEDIA_MOVIE_VIDEO_EXTENSIONS, true ) ) {
			$warnings[] = media_movie_list_warning(
				'sample_or_trailer',
				'Flagged as sample/trailer; not treated as a movie file.',
				$name
			);
			continue;
		}

		if ( $kind === 'ignored' && $extension !== '' && ! in_array( $extension, MEDIA_MOVIE_IGNORED_EXTENSIONS, true ) && ! in_array( $extension, MEDIA_MOVIE_VIDEO_EXTENSIONS, true ) ) {
			$warnings[] = media_movie_list_warning(
				'unrecognized_extension',
				'Unrecognized file extension.',
				$name
			);
		}
	}

	usort(
		$files,
		static function ( array $a, array $b ): int {
			return strcasecmp( $a['name'], $b['name'] );
		}
	);

	return array(
		'ok'         => true,
		'kind'       => 'movie',
		'directory'  => $resolved['directory'],
		'country'    => $resolved['country'],
		'year'       => $resolved['year'],
		'movie_name' => $resolved['movie_name'],
		'warnings'   => $warnings,
		'files'      => $files,
	);
}

/**
 * Human-readable size. 2523456789 → "2.35 GB" (1024-based).
 */
function media_format_size_label( int $bytes ): string {
	if ( $bytes < 1024 ) {
		return $bytes . ' B';
	}

	$units = array( 'KB', 'MB', 'GB', 'TB', 'PB' );
	$value = (float) $bytes;
	$index = -1;

	do {
		$value /= 1024;
		$index++;
	} while ( $value >= 1024 && $index < count( $units ) - 1 );

	return sprintf( '%.2f %s', $value, $units[ $index ] );
}

/**
 * @return array{
 *   name: string,
 *   media_path: string,
 *   extension: string,
 *   kind: 'video'|'subtitle'|'ignored'|'directory',
 *   size_bytes: int|null,
 *   size_label: string|null
 * }
 */
function media_movie_list_entry( string $directory, string $name, string $kind, string $extension, ?int $size_bytes ): array {
	return array(
		'name'       => $name,
		'media_path' => $directory . '/' . $name,
		'extension'  => $extension,
		'kind'       => $kind,
		'size_bytes' => $size_bytes,
		'size_label' => null === $size_bytes ? null : media_format_size_label( $size_bytes ),
	);
}

/**
 * @return array{code: string, message: string, name: string}
 */
function media_movie_list_warning( string $code, string $message, string $name ): array {
	return array(
		'code'    => $code,
		'message' => $message,
		'name'    => $name,
	);
}

function media_movie_list_extension( string $name ): string {
	$ext = pathinfo( $name, PATHINFO_EXTENSION );
	return is_string( $ext ) ? strtolower( $ext ) : '';
}

/**
 * @return 'video'|'subtitle'|'ignored'
 */
function media_movie_list_kind( string $name, string $extension ): string {
	if ( in_array( $extension, MEDIA_MOVIE_VIDEO_EXTENSIONS, true ) ) {
		if ( media_movie_list_is_sample_or_trailer( $name ) ) {
			return 'ignored';
		}
		return 'video';
	}

	if ( in_array( $extension, MEDIA_MOVIE_SUBTITLE_EXTENSIONS, true ) ) {
		return 'subtitle';
	}

	return 'ignored';
}

function media_movie_list_is_sample_or_trailer( string $name ): bool {
	$base = pathinfo( $name, PATHINFO_FILENAME );
	if ( ! is_string( $base ) || $base === '' ) {
		return false;
	}

	$tokens = preg_split( '/[.\s_\-]+/', strtolower( $base ) );
	if ( ! is_array( $tokens ) ) {
		return false;
	}

	return in_array( 'sample', $tokens, true ) || in_array( 'trailer', $tokens, true );
}

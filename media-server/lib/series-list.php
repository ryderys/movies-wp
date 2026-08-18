<?php
/**
 * Controlled listing of a validated Series title directory.
 *
 * Traverses immediate release/category directories with category-specific rules.
 *
 * @package movies-wp
 */

declare(strict_types=1);

require_once __DIR__ . '/series-dir.php';
require_once __DIR__ . '/series-category.php';

/** @var list<string> */
const MEDIA_SERIES_VIDEO_EXTENSIONS = array( 'mkv', 'mp4', 'avi' );

/** @var list<string> */
const MEDIA_SERIES_SUBTITLE_EXTENSIONS = array( 'srt', 'ass', 'ssa', 'vtt', 'sub' );

/**
 * @return array{code: string, message: string, name?: string, category?: string}
 */
function media_series_list_warning( string $code, string $message, string $name = '', string $category = '' ): array {
	$row = array(
		'code'    => $code,
		'message' => $message,
	);
	if ( $name !== '' ) {
		$row['name'] = $name;
	}
	if ( $category !== '' ) {
		$row['category'] = $category;
	}
	return $row;
}

/**
 * List categorized files under a relative series directory.
 *
 * @return array<string, mixed>
 */
function media_list_series_dir( string $relative, string $media_root, string $series_root ): array {
	$resolved = media_resolve_series_dir( $relative, $media_root, $series_root );
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
		return media_movie_dir_error( 'not_found', 'Series directory does not exist.' );
	}
	$real_dir = media_movie_dir_normalize_root( $real_dir );

	$entries = @scandir( $real_dir );
	if ( ! is_array( $entries ) ) {
		return media_movie_dir_error( 'list_failed', 'Could not read series directory.' );
	}

	$categories = array();
	$files      = array();
	$warnings   = array();

	foreach ( $entries as $name ) {
		if ( $name === '.' || $name === '..' ) {
			continue;
		}

		$child = $real_dir . '/' . $name;

		if ( is_file( $child ) || ( is_link( $child ) && is_file( realpath( $child ) ?: '' ) ) ) {
			$warnings[] = media_series_list_warning(
				'unexpected_series_root_file',
				'File directly under the series title directory is skipped.',
				$name
			);
			continue;
		}

		if ( ! is_dir( $child ) && ! ( is_link( $child ) && is_dir( realpath( $child ) ?: '' ) ) ) {
			continue;
		}

		$classification = media_classify_series_category( $name );
		$categories[]   = $classification;

		if ( $classification['type'] === 'UNKNOWN' ) {
			continue;
		}

		if ( $classification['type'] === 'SUPPLEMENTARY' ) {
			$warnings[] = media_series_list_warning(
				'supplementary_skipped',
				'Supplementary content was intentionally skipped.',
				$name,
				$name
			);
			continue;
		}

		$category_abs = realpath( $child );
		if ( $category_abs === false || ! is_dir( $category_abs ) ) {
			continue;
		}
		$category_abs = media_movie_dir_normalize_root( $category_abs );

		if ( ! media_movie_dir_is_inside( $category_abs, $real_dir ) ) {
			$warnings[] = media_series_list_warning(
				'symlink_outside',
				'Skipped category symlink pointing outside the series directory.',
				$name,
				$name
			);
			continue;
		}

		if ( $classification['type'] === 'VIDEO_RELEASE' ) {
			media_series_list_video_category(
				$resolved['directory'],
				$name,
				$classification,
				$category_abs,
				$real_dir,
				$files,
				$warnings
			);
			continue;
		}

		if ( $classification['type'] === 'SUBTITLE' ) {
			media_series_list_subtitle_category(
				$resolved['directory'],
				$name,
				$classification,
				$category_abs,
				$real_dir,
				$files,
				$warnings
			);
		}
	}

	usort(
		$categories,
		static function ( array $a, array $b ): int {
			return strcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
		}
	);

	usort(
		$files,
		static function ( array $a, array $b ): int {
			return strcasecmp( (string) ( $a['media_path'] ?? '' ), (string) ( $b['media_path'] ?? '' ) );
		}
	);

	return array(
		'ok'         => true,
		'kind'       => 'series',
		'directory'  => $resolved['directory'],
		'country'    => $resolved['country'],
		'year'       => $resolved['year'],
		'series_title' => $resolved['series_title'],
		'categories' => $categories,
		'warnings'   => $warnings,
		'files'      => $files,
	);
}

/**
 * @param list<array<string, mixed>> $files
 * @param list<array<string, mixed>> $warnings
 */
function media_series_list_video_category(
	string $series_directory,
	string $category_name,
	array $classification,
	string $category_abs,
	string $series_abs,
	array &$files,
	array &$warnings
): void {
	$entries = @scandir( $category_abs );
	if ( ! is_array( $entries ) ) {
		return;
	}

	foreach ( $entries as $name ) {
		if ( $name === '.' || $name === '..' ) {
			continue;
		}

		$child = $category_abs . '/' . $name;
		if ( is_dir( $child ) || ( is_link( $child ) && is_dir( realpath( $child ) ?: '' ) ) ) {
			$warnings[] = media_series_list_warning(
				'unexpected_video_subdirectory',
				'Nested directory inside a video release category is skipped.',
				$name,
				$category_name
			);
			continue;
		}

		media_series_list_add_file(
			$series_directory,
			$category_name,
			$classification,
			'video',
			$child,
			$series_abs,
			$files,
			$warnings
		);
	}
}

/**
 * @param list<array<string, mixed>> $files
 * @param list<array<string, mixed>> $warnings
 */
function media_series_list_subtitle_category(
	string $series_directory,
	string $category_name,
	array $classification,
	string $category_abs,
	string $series_abs,
	array &$files,
	array &$warnings
): void {
	$entries = @scandir( $category_abs );
	if ( ! is_array( $entries ) ) {
		return;
	}

	foreach ( $entries as $name ) {
		if ( $name === '.' || $name === '..' ) {
			continue;
		}

		$child = $category_abs . '/' . $name;

		if ( is_file( $child ) || ( is_link( $child ) && is_file( realpath( $child ) ?: '' ) ) ) {
			media_series_list_add_file(
				$series_directory,
				$category_name,
				$classification,
				'subtitle',
				$child,
				$series_abs,
				$files,
				$warnings
			);
			continue;
		}

		if ( ! is_dir( $child ) && ! ( is_link( $child ) && is_dir( realpath( $child ) ?: '' ) ) ) {
			continue;
		}

		$release_abs = realpath( $child );
		if ( $release_abs === false || ! is_dir( $release_abs ) ) {
			continue;
		}
		$release_abs = media_movie_dir_normalize_root( $release_abs );

		if ( ! media_movie_dir_is_inside( $release_abs, $category_abs ) ) {
			$warnings[] = media_series_list_warning(
				'symlink_outside',
				'Skipped subtitle subdirectory symlink pointing outside the category.',
				$name,
				$category_name
			);
			continue;
		}

		$release_entries = @scandir( $release_abs );
		if ( ! is_array( $release_entries ) ) {
			continue;
		}

		foreach ( $release_entries as $release_name ) {
			if ( $release_name === '.' || $release_name === '..' ) {
				continue;
			}
			$release_child = $release_abs . '/' . $release_name;
			if ( is_dir( $release_child ) || ( is_link( $release_child ) && is_dir( realpath( $release_child ) ?: '' ) ) ) {
				$warnings[] = media_series_list_warning(
					'excessive_subtitle_nesting',
					'Excessive subtitle nesting is skipped.',
					$category_name . '/' . $name . '/' . $release_name,
					$category_name
				);
				continue;
			}

			media_series_list_add_file(
				$series_directory,
				$category_name . '/' . $name,
				$classification,
				'subtitle',
				$release_child,
				$series_abs,
				$files,
				$warnings
			);
		}
	}
}

/**
 * @param list<array<string, mixed>> $files
 * @param list<array<string, mixed>> $warnings
 */
function media_series_list_add_file(
	string $series_directory,
	string $category_path,
	array $classification,
	string $expected_kind,
	string $absolute_path,
	string $series_abs,
	array &$files,
	array &$warnings
): void {
	$real = realpath( $absolute_path );
	if ( $real === false || ! is_file( $real ) ) {
		return;
	}
	$real = media_movie_dir_normalize_root( $real );

	if ( ! media_movie_dir_is_inside( $real, $series_abs ) ) {
		$warnings[] = media_series_list_warning(
			'symlink_outside',
			'Skipped file symlink pointing outside the series directory.',
			basename( $real )
		);
		return;
	}

	$name      = basename( $real );
	$extension = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
	$kind      = media_series_list_kind( $extension );

	if ( $expected_kind === 'video' && $kind !== 'video' ) {
		if ( $kind === 'subtitle' ) {
			$warnings[] = media_series_list_warning(
				'unsupported_extension',
				'Subtitle file found inside a video release category.',
				$name,
				$category_path
			);
		} else {
			$warnings[] = media_series_list_warning(
				'unsupported_extension',
				'Unsupported file extension in video release category.',
				$name,
				$category_path
			);
		}
		return;
	}

	if ( $expected_kind === 'subtitle' && $kind !== 'subtitle' ) {
		$warnings[] = media_series_list_warning(
			'unsupported_extension',
			'Non-subtitle file found inside a subtitle category.',
			$name,
			$category_path
		);
		return;
	}

	$size = filesize( $real );
	if ( $size === false ) {
		$warnings[] = media_series_list_warning( 'size_failed', 'Could not read file size.', $name, $category_path );
		return;
	}

	$relative_from_series = substr( $real, strlen( $series_abs ) + 1 );
	$media_path           = $series_directory . '/' . str_replace( '\\', '/', $relative_from_series );

	$files[] = array(
		'name'             => $name,
		'media_path'       => $media_path,
		'category'         => basename( str_replace( '\\', '/', $category_path ) ),
		'category_path'    => $category_path,
		'category_type'    => $classification['type'],
		'quality_hint'     => $classification['quality_hint'] ?? null,
		'language_hint'    => $classification['language_hint'] ?? null,
		'extension'        => $extension,
		'kind'             => $kind,
		'size_bytes'       => (int) $size,
		'size_label'       => media_format_size_label( (int) $size ),
	);
}

function media_series_list_kind( string $extension ): string {
	if ( in_array( $extension, MEDIA_SERIES_VIDEO_EXTENSIONS, true ) ) {
		return 'video';
	}
	if ( in_array( $extension, MEDIA_SERIES_SUBTITLE_EXTENSIONS, true ) ) {
		return 'subtitle';
	}
	return 'other';
}

require_once __DIR__ . '/movie-list.php';

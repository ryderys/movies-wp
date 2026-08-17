<?php
/**
 * Pure series-directory resolver/validator.
 *
 * Expected relative layout:
 *   Series/{country}/{year}/{title}
 *
 * @package movies-wp
 */

declare(strict_types=1);

require_once __DIR__ . '/movie-dir.php';

/**
 * Load MEDIA_ROOT / SERIES_ROOT from the environment.
 *
 * @return array{media_root: string, series_root: string}
 */
function media_series_dir_roots(): array {
	$media = getenv( 'MEDIA_ROOT' );
	if ( ! is_string( $media ) || $media === '' ) {
		$alias = getenv( 'MEDIA_DATA_ROOT' );
		$media = ( is_string( $alias ) && $alias !== '' ) ? $alias : '/data';
	}

	$media = media_movie_dir_normalize_root( $media );

	$series = getenv( 'SERIES_ROOT' );
	if ( ! is_string( $series ) || $series === '' ) {
		$series = $media . '/Series';
	}

	return array(
		'media_root'  => $media,
		'series_root' => media_movie_dir_normalize_root( $series ),
	);
}

/**
 * Resolve and validate a relative series directory.
 *
 * @param string $relative   Relative directory, e.g. Series/korea/2024/Marry.My.Husband.
 * @param string $media_root MEDIA_ROOT (must exist).
 * @param string $series_root SERIES_ROOT (must exist and be inside MEDIA_ROOT).
 * @return array{
 *   ok: true,
 *   kind: 'series',
 *   directory: string,
 *   country: string,
 *   year: int,
 *   series_title: string
 * }|array{ok: false, code: string, message: string}
 */
function media_resolve_series_dir( string $relative, string $media_root, string $series_root ): array {
	$syntax = media_series_dir_parse_relative( $relative );
	if ( $syntax['ok'] === false ) {
		return $syntax;
	}

	$real_media  = realpath( $media_root );
	$real_series = realpath( $series_root );

	if ( $real_media === false || ! is_dir( $real_media ) ) {
		return media_movie_dir_error( 'root_not_found', 'MEDIA_ROOT does not exist or is not a directory.' );
	}
	if ( $real_series === false || ! is_dir( $real_series ) ) {
		return media_movie_dir_error( 'root_not_found', 'SERIES_ROOT does not exist or is not a directory.' );
	}

	$real_media  = media_movie_dir_normalize_root( $real_media );
	$real_series = media_movie_dir_normalize_root( $real_series );

	if ( ! media_movie_dir_is_inside( $real_series, $real_media ) ) {
		return media_movie_dir_error( 'invalid_roots', 'SERIES_ROOT must be inside MEDIA_ROOT.' );
	}
	if ( $real_series === $real_media ) {
		return media_movie_dir_error( 'invalid_roots', 'SERIES_ROOT must be a subdirectory of MEDIA_ROOT.' );
	}

	$prefix          = substr( $real_series, strlen( $real_media ) + 1 );
	$prefix_segments = explode( '/', $prefix );
	$expected        = array_merge( $prefix_segments, $syntax['tail'] );

	if ( $syntax['segments'] !== $expected ) {
		return media_movie_dir_error(
			'invalid_structure',
			'Path must be ' . $prefix . '/{country}/{year}/{title}.'
		);
	}

	$candidate = $real_media . '/' . implode( '/', $syntax['segments'] );
	$real      = realpath( $candidate );

	if ( $real === false ) {
		if ( is_file( $candidate ) ) {
			return media_movie_dir_error( 'not_directory', 'Path is a file, not a directory.' );
		}
		return media_movie_dir_error( 'not_found', 'Series directory does not exist.' );
	}

	$real = media_movie_dir_normalize_root( $real );

	if ( ! is_dir( $real ) ) {
		return media_movie_dir_error( 'not_directory', 'Path is a file, not a directory.' );
	}

	if ( ! media_movie_dir_is_inside( $real, $real_media ) ) {
		return media_movie_dir_error( 'outside_media_root', 'Resolved path is outside MEDIA_ROOT.' );
	}

	if ( ! media_movie_dir_is_inside( $real, $real_series ) ) {
		return media_movie_dir_error( 'outside_series_root', 'Resolved path is outside SERIES_ROOT.' );
	}

	$directory = substr( $real, strlen( $real_media ) + 1 );
	$parts     = explode( '/', $directory );
	$n         = count( $parts );

	return array(
		'ok'           => true,
		'kind'         => 'series',
		'directory'    => $directory,
		'country'      => $parts[ $n - 3 ],
		'year'         => (int) $parts[ $n - 2 ],
		'series_title' => $parts[ $n - 1 ],
	);
}

/**
 * Syntax-only checks (no filesystem).
 *
 * @return array{ok: true, segments: list<string>, tail: array{0: string, 1: string, 2: string}}|array{ok: false, code: string, message: string}
 */
function media_series_dir_parse_relative( string $relative ): array {
	if ( $relative === '' || trim( $relative ) === '' ) {
		return media_movie_dir_error( 'empty_path', 'Path is empty.' );
	}

	if ( str_contains( $relative, "\0" ) ) {
		return media_movie_dir_error( 'invalid_path', 'Path contains a null byte.' );
	}

	$normalized = str_replace( '\\', '/', $relative );
	$normalized = trim( $normalized );

	if ( $normalized === '' ) {
		return media_movie_dir_error( 'empty_path', 'Path is empty.' );
	}

	if ( media_movie_dir_is_absolute( $normalized ) ) {
		return media_movie_dir_error( 'absolute_path', 'Path must be relative.' );
	}

	$normalized = trim( $normalized, '/' );
	if ( $normalized === '' ) {
		return media_movie_dir_error( 'empty_path', 'Path is empty.' );
	}

	$segments = explode( '/', $normalized );
	foreach ( $segments as $segment ) {
		if ( $segment === '' || $segment === '.' || $segment === '..' ) {
			return media_movie_dir_error( 'invalid_segment', 'Path must not contain empty, . or .. segments.' );
		}
	}

	if ( count( $segments ) < 4 ) {
		return media_movie_dir_error(
			'invalid_structure',
			'Path must be Series/{country}/{year}/{title}.'
		);
	}

	$tail = array_slice( $segments, -3 );
	$year = $tail[1];

	if ( ! preg_match( '/^(19|20)\d{2}$/', $year ) ) {
		return media_movie_dir_error( 'invalid_year', 'Year must be a 19xx or 20xx calendar year.' );
	}

	return array(
		'ok'       => true,
		'segments' => $segments,
		'tail'     => array( $tail[0], $tail[1], $tail[2] ),
	);
}

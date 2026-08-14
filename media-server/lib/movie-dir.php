<?php
/**
 * Pure movie-directory resolver/validator.
 *
 * Independent of HTTP, WordPress, Streamit, TMDb, filename parsing,
 * subtitles, and ffprobe. Roots are injected; /data is never assumed
 * inside the resolver.
 *
 * Expected relative layout:
 *   {movieRootBasename}/{country}/{year}/{movie}
 * e.g. Movie/Chin/2016/Bounty.Hunters
 *
 * @package movies-wp
 */

declare(strict_types=1);

/**
 * Load MEDIA_ROOT / MOVIE_ROOT from the environment.
 *
 * Defaults (used only when env vars are unset):
 *   MEDIA_ROOT = /data
 *   MOVIE_ROOT = {MEDIA_ROOT}/Movie
 *
 * MEDIA_DATA_ROOT is accepted as an alias of MEDIA_ROOT (verify.php).
 *
 * @return array{media_root: string, movie_root: string}
 */
function media_movie_dir_roots(): array {
	$media = getenv( 'MEDIA_ROOT' );
	if ( ! is_string( $media ) || $media === '' ) {
		$alias = getenv( 'MEDIA_DATA_ROOT' );
		$media = ( is_string( $alias ) && $alias !== '' ) ? $alias : '/data';
	}

	$media = media_movie_dir_normalize_root( $media );

	$movie = getenv( 'MOVIE_ROOT' );
	if ( ! is_string( $movie ) || $movie === '' ) {
		$movie = $media . '/Movie';
	}

	return array(
		'media_root' => $media,
		'movie_root' => media_movie_dir_normalize_root( $movie ),
	);
}

/**
 * Resolve and validate a relative movie directory.
 *
 * @param string $relative   Relative directory, e.g. Movie/Chin/2016/Bounty.Hunters.
 * @param string $media_root MEDIA_ROOT (must exist).
 * @param string $movie_root MOVIE_ROOT (must exist and be inside MEDIA_ROOT).
 * @return array{
 *   ok: true,
 *   kind: 'movie',
 *   directory: string,
 *   country: string,
 *   year: int,
 *   movie_name: string
 * }|array{ok: false, code: string, message: string}
 */
function media_resolve_movie_dir( string $relative, string $media_root, string $movie_root ): array {
	$syntax = media_movie_dir_parse_relative( $relative );
	if ( $syntax['ok'] === false ) {
		return $syntax;
	}

	$real_media = realpath( $media_root );
	$real_movie = realpath( $movie_root );

	if ( $real_media === false || ! is_dir( $real_media ) ) {
		return media_movie_dir_error( 'root_not_found', 'MEDIA_ROOT does not exist or is not a directory.' );
	}
	if ( $real_movie === false || ! is_dir( $real_movie ) ) {
		return media_movie_dir_error( 'root_not_found', 'MOVIE_ROOT does not exist or is not a directory.' );
	}

	$real_media = media_movie_dir_normalize_root( $real_media );
	$real_movie = media_movie_dir_normalize_root( $real_movie );

	if ( ! media_movie_dir_is_inside( $real_movie, $real_media ) ) {
		return media_movie_dir_error( 'invalid_roots', 'MOVIE_ROOT must be inside MEDIA_ROOT.' );
	}
	if ( $real_movie === $real_media ) {
		return media_movie_dir_error( 'invalid_roots', 'MOVIE_ROOT must be a subdirectory of MEDIA_ROOT.' );
	}

	$prefix = substr( $real_movie, strlen( $real_media ) + 1 );
	$prefix_segments = explode( '/', $prefix );
	$expected = array_merge( $prefix_segments, $syntax['tail'] );

	if ( $syntax['segments'] !== $expected ) {
		return media_movie_dir_error(
			'invalid_structure',
			'Path must be ' . $prefix . '/{country}/{year}/{movie}.'
		);
	}

	$candidate = $real_media . '/' . implode( '/', $syntax['segments'] );
	$real      = realpath( $candidate );

	if ( $real === false ) {
		if ( is_file( $candidate ) ) {
			return media_movie_dir_error( 'not_directory', 'Path is a file, not a directory.' );
		}
		return media_movie_dir_error( 'not_found', 'Movie directory does not exist.' );
	}

	$real = media_movie_dir_normalize_root( $real );

	if ( ! is_dir( $real ) ) {
		return media_movie_dir_error( 'not_directory', 'Path is a file, not a directory.' );
	}

	if ( ! media_movie_dir_is_inside( $real, $real_media ) ) {
		return media_movie_dir_error( 'outside_media_root', 'Resolved path is outside MEDIA_ROOT.' );
	}

	if ( ! media_movie_dir_is_inside( $real, $real_movie ) ) {
		return media_movie_dir_error( 'outside_movie_root', 'Resolved path is outside MOVIE_ROOT.' );
	}

	// Canonical relative path from the real filesystem (preserves on-disk names).
	$directory = substr( $real, strlen( $real_media ) + 1 );
	$parts     = explode( '/', $directory );
	$n         = count( $parts );

	return array(
		'ok'         => true,
		'kind'       => 'movie',
		'directory'  => $directory,
		'country'    => $parts[ $n - 3 ],
		'year'       => (int) $parts[ $n - 2 ],
		'movie_name' => $parts[ $n - 1 ],
	);
}

/**
 * Syntax-only checks (no filesystem). Rejects traversal and absolute paths.
 *
 * @return array{ok: true, segments: list<string>, tail: array{0: string, 1: string, 2: string}}|array{ok: false, code: string, message: string}
 */
function media_movie_dir_parse_relative( string $relative ): array {
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

	// {prefix...}/{country}/{year}/{movie} — prefix length is known after roots resolve.
	// Require at least country/year/movie (3) plus one prefix segment (Movie).
	if ( count( $segments ) < 4 ) {
		return media_movie_dir_error(
			'invalid_structure',
			'Path must be Movie/{country}/{year}/{movie}.'
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

/**
 * @return array{ok: false, code: string, message: string}
 */
function media_movie_dir_error( string $code, string $message ): array {
	return array(
		'ok'      => false,
		'code'    => $code,
		'message' => $message,
	);
}

function media_movie_dir_normalize_root( string $path ): string {
	$path = str_replace( '\\', '/', $path );
	return rtrim( $path, '/' );
}

function media_movie_dir_is_absolute( string $path ): bool {
	if ( str_starts_with( $path, '/' ) ) {
		return true;
	}
	// Windows drive or UNC.
	if ( preg_match( '#^[A-Za-z]:/#', $path ) === 1 ) {
		return true;
	}
	if ( str_starts_with( $path, '//' ) ) {
		return true;
	}
	return false;
}

function media_movie_dir_is_inside( string $path, string $root ): bool {
	$path = media_movie_dir_normalize_root( $path );
	$root = media_movie_dir_normalize_root( $root );

	return $path === $root || str_starts_with( $path, $root . '/' );
}

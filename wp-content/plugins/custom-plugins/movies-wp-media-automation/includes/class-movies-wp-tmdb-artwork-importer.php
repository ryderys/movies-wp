<?php
/**
 * Sideload a capped TMDb poster and backdrop gallery.
 *
 * Callers supply the download. This class does not talk to TMDb or WordPress.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Tmdb_Artwork_Importer {

	public const POSTER_META   = '_tmdb_poster_ids';
	public const BACKDROP_META = '_tmdb_backdrop_ids';
	public const LIMIT         = 10;

	/**
	 * @param mixed $gallery
	 * @return array{posters:string[],backdrops:string[]}
	 */
	public static function paths( $gallery ) {
		$gallery = is_array( $gallery ) ? $gallery : array();
		return array(
			'posters'   => self::clean_paths( $gallery['posters'] ?? array() ),
			'backdrops' => self::clean_paths( $gallery['backdrops'] ?? array() ),
		);
	}

	/**
	 * Positive attachment IDs already stored for one gallery list.
	 *
	 * @param mixed $raw
	 * @return int[]
	 */
	public static function existing_ids( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$ids = array();
		foreach ( $raw as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * @param string $path TMDb file path.
	 * @param string $role posters|backdrops
	 */
	public static function source_url( $path, $role ) {
		$size = 'posters' === $role ? 'w780' : 'w1280';
		$path = '/' . ltrim( (string) $path, '/' );
		if ( function_exists( 'streamit_get_tmdb_image_url' ) ) {
			$url = streamit_get_tmdb_image_url( $path, $size );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}
		return 'https://image.tmdb.org/t/p/' . $size . $path;
	}

	/**
	 * Keep a non-empty stored list. Otherwise download the planned paths.
	 *
	 * A partial success is stored. A total failure is not, so a later import can retry.
	 *
	 * @param mixed    $gallery
	 * @param array    $existing posters and backdrops meta values.
	 * @param callable $download function(string $path, string $role): int|WP_Error
	 * @return array<string, mixed>
	 */
	public static function import( $gallery, array $existing, callable $download ) {
		$paths  = self::paths( $gallery );
		$result = array( 'warnings' => array() );

		foreach ( array( 'posters', 'backdrops' ) as $role ) {
			$kept = self::existing_ids( $existing[ $role ] ?? array() );
			if ( $kept !== array() ) {
				$result[ $role ] = array(
					'action' => 'keep_existing',
					'ids'    => $kept,
				);
				continue;
			}
			if ( $paths[ $role ] === array() ) {
				$result[ $role ] = array(
					'action' => 'skip_empty',
					'ids'    => array(),
				);
				continue;
			}

			$ids = array();
			foreach ( $paths[ $role ] as $path ) {
				$attachment_id = $download( $path, $role );
				if ( is_wp_error( $attachment_id ) || (int) $attachment_id <= 0 ) {
					$result['warnings'][] = array(
						'role' => $role,
						'path' => $path,
					);
					continue;
				}
				$ids[] = (int) $attachment_id;
			}

			$result[ $role ] = array(
				'action' => $ids === array() ? 'skip_empty' : 'set',
				'ids'    => $ids,
			);
		}

		return $result;
	}

	/**
	 * @param mixed $paths
	 * @return string[]
	 */
	private static function clean_paths( $paths ) {
		if ( ! is_array( $paths ) ) {
			return array();
		}
		$clean = array();
		$seen  = array();
		foreach ( $paths as $path ) {
			if ( ! is_string( $path ) ) {
				continue;
			}
			$path = trim( $path );
			if ( '' === $path ) {
				continue;
			}
			if ( '/' !== $path[0] ) {
				$path = '/' . ltrim( $path, '/' );
			}
			if ( isset( $seen[ $path ] ) ) {
				continue;
			}
			$seen[ $path ] = true;
			$clean[]       = $path;
			if ( count( $clean ) >= self::LIMIT ) {
				break;
			}
		}
		return $clean;
	}
}

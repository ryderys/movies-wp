<?php
/**
 * Pick at most ten unique TMDb poster and backdrop paths.
 *
 * Pure: no HTTP, no WordPress, no sideload.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Tmdb_Artwork_Selector {

	public const LIMIT = 10;

	/**
	 * @param array<string, mixed> $images  TMDb `images` object (`posters`, `backdrops`).
	 * @param array{
	 *   exclude_paths?: string[],
	 *   languages?: string[]|null
	 * } $options exclude_paths skips primary art; languages defaults to textless + en + fa.
	 * @return array{posters:string[],backdrops:string[]}
	 */
	public static function select( array $images, array $options = array() ) {
		$exclude = array();
		foreach ( isset( $options['exclude_paths'] ) && is_array( $options['exclude_paths'] ) ? $options['exclude_paths'] : array() as $path ) {
			$path = self::normalize_path( $path );
			if ( null !== $path ) {
				$exclude[ $path ] = true;
			}
		}

		$languages = array_key_exists( 'languages', $options ) ? $options['languages'] : array( '', 'en', 'fa' );

		return array(
			'posters'   => self::paths( $images['posters'] ?? array(), $exclude, $languages ),
			'backdrops' => self::paths( $images['backdrops'] ?? array(), $exclude, $languages ),
		);
	}

	/**
	 * @param mixed             $entries
	 * @param array<string,bool> $exclude
	 * @param string[]|null     $languages null = any language
	 * @return string[]
	 */
	private static function paths( $entries, array $exclude, $languages ) {
		$ranked = self::rank_entries( $entries, $exclude, $languages );
		if ( $ranked === array() && null !== $languages ) {
			$ranked = self::rank_entries( $entries, $exclude, null );
		}

		$paths = array();
		$seen  = array();
		foreach ( $ranked as $row ) {
			if ( isset( $seen[ $row['path'] ] ) ) {
				continue;
			}
			$seen[ $row['path'] ] = true;
			$paths[]              = $row['path'];
			if ( count( $paths ) >= self::LIMIT ) {
				break;
			}
		}

		return $paths;
	}

	/**
	 * @param mixed              $entries
	 * @param array<string,bool> $exclude
	 * @param string[]|null      $languages
	 * @return list<array{path:string,vote:float,index:int}>
	 */
	private static function rank_entries( $entries, array $exclude, $languages ) {
		if ( ! is_array( $entries ) ) {
			return array();
		}

		$allowed = null;
		if ( is_array( $languages ) ) {
			$allowed = array();
			foreach ( $languages as $lang ) {
				$allowed[ strtolower( trim( (string) $lang ) ) ] = true;
			}
		}

		$ranked = array();
		$index  = 0;
		foreach ( $entries as $entry ) {
			$position = $index++;
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$path = self::normalize_path( $entry['file_path'] ?? null );
			if ( null === $path || isset( $exclude[ $path ] ) ) {
				continue;
			}
			if ( is_array( $allowed ) ) {
				$lang = isset( $entry['iso_639_1'] ) ? strtolower( trim( (string) $entry['iso_639_1'] ) ) : '';
				if ( ! isset( $allowed[ $lang ] ) ) {
					continue;
				}
			}
			$vote     = isset( $entry['vote_average'] ) && is_numeric( $entry['vote_average'] ) ? (float) $entry['vote_average'] : 0.0;
			$ranked[] = array(
				'path'  => $path,
				'vote'  => $vote,
				'index' => $position,
			);
		}

		usort(
			$ranked,
			static function ( $a, $b ) {
				if ( $a['vote'] === $b['vote'] ) {
					return $a['index'] <=> $b['index'];
				}
				return ( $a['vote'] < $b['vote'] ) ? 1 : -1;
			}
		);

		return $ranked;
	}

	/**
	 * @param mixed $path
	 * @return string|null
	 */
	private static function normalize_path( $path ) {
		if ( ! is_string( $path ) ) {
			return null;
		}
		$path = trim( $path );
		if ( '' === $path ) {
			return null;
		}
		if ( '/' !== $path[0] ) {
			$path = '/' . ltrim( $path, '/' );
		}
		return $path;
	}
}

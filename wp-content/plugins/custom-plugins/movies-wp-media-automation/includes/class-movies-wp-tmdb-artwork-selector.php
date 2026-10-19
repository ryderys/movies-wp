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
	 * @param array<string, mixed> $images TMDb `images` object (`posters`, `backdrops`).
	 * @return array{posters:string[],backdrops:string[]}
	 */
	public static function select( array $images ) {
		return array(
			'posters'   => self::paths( $images['posters'] ?? array() ),
			'backdrops' => self::paths( $images['backdrops'] ?? array() ),
		);
	}

	/**
	 * @param mixed $entries
	 * @return string[]
	 */
	private static function paths( $entries ) {
		if ( ! is_array( $entries ) ) {
			return array();
		}

		$ranked = array();
		$index  = 0;
		foreach ( $entries as $entry ) {
			$position = $index++;
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$path = isset( $entry['file_path'] ) ? trim( (string) $entry['file_path'] ) : '';
			if ( '' === $path ) {
				continue;
			}
			if ( '/' !== $path[0] ) {
				$path = '/' . ltrim( $path, '/' );
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
}

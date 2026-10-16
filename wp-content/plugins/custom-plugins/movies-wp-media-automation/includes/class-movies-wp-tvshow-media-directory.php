<?php
/**
 * Validate and backfill a TV show's `_media_directory`.
 *
 * The directory is series/{country}/{year}/{title}. It is never built from a title or slug.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Tvshow_Media_Directory {

	/**
	 * @param mixed $directory
	 * @return string|WP_Error
	 */
	public static function validate_title_directory( $directory ) {
		if ( ! is_string( $directory ) ) {
			return self::invalid( 'The media directory must be a relative series path.' );
		}
		$normalized = str_replace( '\\', '/', trim( $directory ) );
		if ( '' === $normalized || str_contains( $normalized, "\0" ) ) {
			return self::invalid( 'The media directory must be a relative series path.' );
		}
		if ( self::is_forbidden_path( $normalized ) ) {
			return self::invalid( 'The media directory must be a relative series path.' );
		}

		$normalized = trim( $normalized, '/' );
		$segments   = explode( '/', $normalized );
		if ( 4 !== count( $segments ) ) {
			return self::invalid( 'The media directory must be series/{country}/{year}/{title}.' );
		}
		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return self::invalid( 'The media directory must be a relative series path.' );
			}
		}
		if ( 'series' !== $segments[0] || ! preg_match( '/^(19|20)\d{2}$/', $segments[2] ) ) {
			return self::invalid( 'The media directory must be series/{country}/{year}/{title}.' );
		}

		return $normalized;
	}

	/**
	 * Recover one series directory from episode source rows.
	 *
	 * @param array<int, array<string, mixed>> $source_rows
	 * @return string|WP_Error
	 */
	public static function prefix_from_sources( array $source_rows ) {
		$usable = array();
		foreach ( $source_rows as $row ) {
			if ( ! is_array( $row ) ) {
				return self::invalid( 'Episode source row is malformed.' );
			}
			foreach ( array( 'link', 'download_content' ) as $key ) {
				$raw = isset( $row[ $key ] ) ? trim( (string) $row[ $key ] ) : '';
				if ( '' === $raw ) {
					continue;
				}
				$prefix = self::prefix_from_episode_path( $raw );
				if ( is_wp_error( $prefix ) ) {
					return $prefix;
				}
				$usable[ $prefix ] = true;
			}
		}
		if ( array() === $usable ) {
			return new WP_Error( 'tvshow_media_directory_missing_paths', __( 'No usable episode media paths were found.', 'movies-wp' ) );
		}
		if ( count( $usable ) > 1 ) {
			return new WP_Error( 'tvshow_media_directory_conflict', __( 'Episode media paths use more than one series directory.', 'movies-wp' ) );
		}
		return (string) array_key_first( $usable );
	}

	/**
	 * @param array<int, array<string, mixed>> $shows Each item: tvshow_id, media_directory, sources.
	 * @param array<string, mixed>             $options dry_run (default true), update_tvshow_meta.
	 * @return array{dry_run:bool,written:int,rows:list<array<string, mixed>>}
	 */
	public static function backfill( array $shows, array $options = array() ) {
		$dry_run = ! isset( $options['dry_run'] ) || ! empty( $options['dry_run'] );
		$rows    = array();
		$written = 0;

		foreach ( $shows as $show ) {
			if ( ! is_array( $show ) ) {
				continue;
			}
			$tvshow_id = (int) ( $show['tvshow_id'] ?? 0 );
			$existing  = isset( $show['media_directory'] ) ? trim( (string) $show['media_directory'] ) : '';
			if ( $tvshow_id <= 0 ) {
				$rows[] = self::skipped( $tvshow_id, 'invalid_id' );
				continue;
			}
			if ( '' !== $existing ) {
				$rows[] = self::skipped( $tvshow_id, 'already_set' );
				continue;
			}

			$prefix = self::prefix_from_sources( is_array( $show['sources'] ?? null ) ? $show['sources'] : array() );
			if ( is_wp_error( $prefix ) ) {
				$rows[] = self::skipped( $tvshow_id, $prefix->get_error_code() );
				continue;
			}

			if ( $dry_run ) {
				$rows[] = array(
					'tvshow_id' => $tvshow_id,
					'action'    => 'would_write',
					'directory' => $prefix,
				);
				continue;
			}

			$saved = Movies_WP_Streamit_TV_Adapter::store_media_directory( $tvshow_id, $prefix, $options );
			if ( is_wp_error( $saved ) ) {
				$rows[] = self::skipped( $tvshow_id, $saved->get_error_code() );
				continue;
			}
			++$written;
			$rows[] = array(
				'tvshow_id' => $tvshow_id,
				'action'    => 'written',
				'directory' => $saved,
			);
		}

		return array(
			'dry_run' => $dry_run,
			'written' => $written,
			'rows'    => $rows,
		);
	}

	/**
	 * @param string $path Episode link or download_content.
	 * @return string|WP_Error
	 */
	private static function prefix_from_episode_path( $path ) {
		$normalized = str_replace( '\\', '/', trim( $path ) );
		if ( '' === $normalized || str_contains( $normalized, "\0" ) || self::is_forbidden_path( $normalized ) ) {
			return self::invalid( 'Episode media path is not a relative series path.' );
		}
		$normalized = trim( $normalized, '/' );
		$segments   = explode( '/', $normalized );
		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return self::invalid( 'Episode media path is not a relative series path.' );
			}
		}
		if ( count( $segments ) < 4 ) {
			return self::invalid( 'Episode media path is not under series/{country}/{year}/{title}.' );
		}
		$prefix = implode( '/', array_slice( $segments, 0, 4 ) );
		return self::validate_title_directory( $prefix );
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	private static function is_forbidden_path( $path ) {
		if ( str_starts_with( $path, '/' ) || preg_match( '#^[A-Za-z]:/#', $path ) || str_starts_with( $path, '//' ) ) {
			return true;
		}
		return 1 === preg_match( '#^(https?:)?//#i', $path )
			|| 1 === preg_match( '#^(https?://|/data(?:/|$)|/v(?:/|$)|/d(?:/|$))#i', $path )
			|| str_contains( $path, '../' )
			|| str_contains( $path, '/..' );
	}

	/**
	 * @param string $message
	 * @return WP_Error
	 */
	private static function invalid( $message ) {
		return new WP_Error( 'tvshow_media_directory_invalid', __( $message, 'movies-wp' ) );
	}

	/**
	 * @param int    $tvshow_id
	 * @param string $reason
	 * @return array{tvshow_id:int,action:string,reason:string}
	 */
	private static function skipped( $tvshow_id, $reason ) {
		return array(
			'tvshow_id' => (int) $tvshow_id,
			'action'    => 'skipped',
			'reason'    => (string) $reason,
		);
	}
}

<?php
/**
 * Discover and assign local trailer paths from media-server scan results.
 *
 * Selection rule (documented): among eligible files under the validated title
 * directory, prefer the folder name Trailer over Trailers (case-insensitive),
 * then order by the full relative path using strcasecmp. Every eligible file
 * is kept. Only direct children of those folders with extensions mp4, webm,
 * or mkv are eligible. Discovery never uploads, moves, renames, or deletes
 * files. A non-empty `_name_trailer_link` is left as the primary playback
 * URL. The ordered paths are merged into `_name_trailer_links` without
 * removing paths already stored.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Trailer_Discovery {

	/** @var list<string> */
	const EXTENSIONS = array( 'mp4', 'webm', 'mkv' );

	const PRIMARY_META_KEY = '_name_trailer_link';

	const LINKS_META_KEY = '_name_trailer_links';

	/**
	 * Eligible trailer paths in playback order.
	 *
	 * @param string      $title_directory Validated Movie/... or series/... directory.
	 * @param list<mixed> $candidates      Relative paths or scan file rows with media_path.
	 * @return list<string>
	 */
	public static function select_paths( $title_directory, array $candidates ) {
		$dir = self::normalize_directory( $title_directory );
		if ( '' === $dir ) {
			return array();
		}

		$eligible = array();
		foreach ( $candidates as $candidate ) {
			$path = self::candidate_path( $candidate );
			if ( null === $path ) {
				continue;
			}
			if ( ! self::is_trailer_path( $dir, $path ) ) {
				continue;
			}
			$eligible[ $path ] = true;
		}

		if ( empty( $eligible ) ) {
			return array();
		}

		$paths = array_keys( $eligible );
		usort(
			$paths,
			static function ( $left, $right ) {
				$left_folder  = self::folder_rank( $left );
				$right_folder = self::folder_rank( $right );
				if ( $left_folder !== $right_folder ) {
					return $left_folder <=> $right_folder;
				}
				return strcasecmp( (string) $left, (string) $right );
			}
		);

		return array_map( 'strval', $paths );
	}

	/**
	 * @param string               $title_directory Validated Movie/... or series/... directory.
	 * @param list<mixed>          $candidates      Relative paths or scan file rows with media_path.
	 * @return string|null First selected relative path, or null when none match.
	 */
	public static function select_path( $title_directory, array $candidates ) {
		$paths = self::select_paths( $title_directory, $candidates );
		return $paths[0] ?? null;
	}

	/**
	 * Collect eligible trailer paths from scan file rows.
	 *
	 * @param string      $title_directory
	 * @param list<mixed> $files
	 * @return list<string>
	 */
	public static function candidates_from_files( $title_directory, array $files ) {
		$dir = self::normalize_directory( $title_directory );
		if ( '' === $dir ) {
			return array();
		}

		$out = array();
		foreach ( $files as $file ) {
			$path = self::candidate_path( $file );
			if ( null === $path ) {
				continue;
			}
			if ( is_array( $file ) && isset( $file['kind'] ) && 'trailer' === $file['kind'] ) {
				if ( self::is_trailer_path( $dir, $path ) ) {
					$out[] = $path;
				}
				continue;
			}
			if ( self::is_trailer_path( $dir, $path ) ) {
				$out[] = $path;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Whether a relative path is a direct trailer file under the title directory.
	 *
	 * @param string $title_directory
	 * @param string $path
	 * @return bool
	 */
	public static function is_trailer_path( $title_directory, $path ) {
		$dir  = self::normalize_directory( $title_directory );
		$path = self::normalize_path( $path );
		if ( '' === $dir || '' === $path ) {
			return false;
		}

		$prefix_trailer  = $dir . '/Trailer/';
		$prefix_trailers = $dir . '/Trailers/';
		$lower_path      = strtolower( $path );
		$under_trailer   = str_starts_with( $lower_path, strtolower( $prefix_trailer ) );
		$under_trailers  = str_starts_with( $lower_path, strtolower( $prefix_trailers ) );
		if ( ! $under_trailer && ! $under_trailers ) {
			return false;
		}

		$prefix_len = $under_trailer ? strlen( $prefix_trailer ) : strlen( $prefix_trailers );
		$rest       = substr( $path, $prefix_len );
		if ( false === $rest || '' === $rest || false !== strpos( $rest, '/' ) ) {
			return false;
		}

		if ( str_starts_with( $rest, '.' ) ) {
			return false;
		}

		$ext = strtolower( (string) pathinfo( $rest, PATHINFO_EXTENSION ) );
		return in_array( $ext, self::EXTENSIONS, true );
	}

	/**
	 * Set `_name_trailer_link` when empty and merge every selected path into `_name_trailer_links`.
	 * Missing trailers and write failures are non-fatal.
	 *
	 * @param string      $kind      movie|tvshow
	 * @param int         $title_id
	 * @param string      $directory Validated title directory.
	 * @param list<mixed> $candidates
	 * @param array       $options   get/update meta hooks for tests.
	 * @return array{status:string,path?:string,paths?:list<string>,message?:string}
	 */
	public static function assign_if_empty( $kind, $title_id, $directory, array $candidates, array $options = array() ) {
		$kind     = (string) $kind;
		$title_id = (int) $title_id;
		if ( $title_id <= 0 || ! in_array( $kind, array( 'movie', 'tvshow' ), true ) ) {
			return array(
				'status'  => 'invalid',
				'message' => 'Invalid title for trailer discovery.',
			);
		}

		$dir = self::normalize_directory( $directory );
		if ( '' === $dir ) {
			return array(
				'status'  => 'invalid',
				'message' => 'Invalid media directory for trailer discovery.',
			);
		}

		$selected = self::select_paths( $dir, $candidates );
		$existing = self::read_meta( $kind, $title_id, self::PRIMARY_META_KEY, $options );
		$primary  = is_string( $existing ) ? trim( $existing ) : '';
		if ( empty( $selected ) ) {
			if ( '' !== $primary ) {
				return array(
					'status' => 'preserved',
					'path'   => $primary,
				);
			}
			return array( 'status' => 'missing' );
		}

		$wrote = false;
		if ( '' === $primary ) {
			$primary = $selected[0];
			$written = self::write_meta( $kind, $title_id, self::PRIMARY_META_KEY, $primary, $options );
			if ( ! $written ) {
				return array(
					'status'  => 'failed',
					'path'    => $primary,
					'paths'   => $selected,
					'message' => 'Failed to save discovered trailer link.',
				);
			}
			$wrote = true;
		}

		$stored = self::stored_paths( self::read_meta( $kind, $title_id, self::LINKS_META_KEY, $options ) );
		$merged = $stored;
		foreach ( $selected as $path ) {
			if ( ! in_array( $path, $merged, true ) ) {
				$merged[] = $path;
			}
		}

		if ( $merged !== $stored ) {
			$written = self::write_meta( $kind, $title_id, self::LINKS_META_KEY, $merged, $options );
			if ( ! $written ) {
				return array(
					'status'  => 'failed',
					'path'    => $primary,
					'paths'   => $selected,
					'message' => 'Failed to save discovered trailer links.',
				);
			}
			$wrote = true;
		}

		if ( ! $wrote ) {
			return array(
				'status' => 'preserved',
				'path'   => $primary,
				'paths'  => $merged,
			);
		}

		return array(
			'status' => 'assigned',
			'path'   => $primary,
			'paths'  => $merged,
		);
	}

	/**
	 * Plan payload fragment used by movie and series import plans.
	 *
	 * @param string      $title_directory
	 * @param list<mixed> $files
	 * @return array{media_path:?string,media_paths:list<string>,candidates:list<string>,selection_rule:string}
	 */
	public static function plan_from_files( $title_directory, array $files ) {
		$candidates = self::candidates_from_files( $title_directory, $files );
		$paths      = self::select_paths( $title_directory, $candidates );
		return array(
			'media_path'     => $paths[0] ?? null,
			'media_paths'    => $paths,
			'candidates'     => $candidates,
			'selection_rule' => 'Store every direct mp4/webm/mkv child of Trailer/ and Trailers/. Order prefers Trailer/ over Trailers/ (case-insensitive), then strcasecmp. The first path fills an empty _name_trailer_link; the full ordered list is merged into _name_trailer_links.',
		);
	}

	/**
	 * @param mixed $value
	 * @return list<string>
	 */
	private static function stored_paths( $value ) {
		if ( is_string( $value ) ) {
			$value = array( $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out  = array();
		$seen = array();
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}
			$path = self::normalize_path( $item );
			if ( '' === $path || isset( $seen[ $path ] ) ) {
				continue;
			}
			$seen[ $path ] = true;
			$out[]         = $path;
		}
		return $out;
	}

	/**
	 * @param mixed $candidate
	 * @return string|null
	 */
	private static function candidate_path( $candidate ) {
		if ( is_string( $candidate ) ) {
			$path = self::normalize_path( $candidate );
			return '' === $path ? null : $path;
		}
		if ( ! is_array( $candidate ) ) {
			return null;
		}
		if ( isset( $candidate['media_path'] ) ) {
			$path = self::normalize_path( (string) $candidate['media_path'] );
			return '' === $path ? null : $path;
		}
		return null;
	}

	/**
	 * @param string $path
	 * @return int 0 for Trailer, 1 for Trailers, 2 otherwise
	 */
	private static function folder_rank( $path ) {
		$parts = explode( '/', self::normalize_path( $path ) );
		$count = count( $parts );
		if ( $count < 2 ) {
			return 2;
		}
		$folder = $parts[ $count - 2 ];
		if ( 0 === strcasecmp( $folder, 'Trailer' ) ) {
			return 0;
		}
		if ( 0 === strcasecmp( $folder, 'Trailers' ) ) {
			return 1;
		}
		return 2;
	}

	/**
	 * @param string $directory
	 * @return string
	 */
	private static function normalize_directory( $directory ) {
		$dir = self::normalize_path( $directory );
		return rtrim( $dir, '/' );
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private static function normalize_path( $path ) {
		$path = str_replace( '\\', '/', trim( (string) $path ) );
		$path = ltrim( $path, '/' );
		if ( '' === $path || false !== strpos( $path, "\0" ) ) {
			return '';
		}
		$parts = explode( '/', $path );
		foreach ( $parts as $part ) {
			if ( '' === $part || '.' === $part || '..' === $part ) {
				return '';
			}
		}
		return implode( '/', $parts );
	}

	/**
	 * @param string $kind
	 * @param int    $id
	 * @param string $key
	 * @param array  $options
	 * @return mixed
	 */
	private static function read_meta( $kind, $id, $key, array $options ) {
		if ( 'tvshow' === $kind && isset( $options['get_tvshow_meta'] ) && is_callable( $options['get_tvshow_meta'] ) ) {
			return call_user_func( $options['get_tvshow_meta'], (int) $id, (string) $key );
		}
		if ( 'movie' === $kind && isset( $options['get_movie_meta'] ) && is_callable( $options['get_movie_meta'] ) ) {
			return call_user_func( $options['get_movie_meta'], (int) $id, (string) $key );
		}
		if ( isset( $options['get_meta'] ) && is_callable( $options['get_meta'] ) ) {
			return call_user_func( $options['get_meta'], (string) $kind, (int) $id, (string) $key );
		}
		if ( class_exists( 'Movies_WP_Trailer_Upload_Service', false ) ) {
			return Movies_WP_Trailer_Upload_Service::get_meta( $kind, $id, $key );
		}
		return '';
	}

	/**
	 * @param string $kind
	 * @param int    $id
	 * @param string $key
	 * @param mixed  $value
	 * @param array  $options
	 * @return bool
	 */
	private static function write_meta( $kind, $id, $key, $value, array $options ) {
		if ( 'tvshow' === $kind && isset( $options['update_tvshow_meta'] ) && is_callable( $options['update_tvshow_meta'] ) ) {
			$result = call_user_func( $options['update_tvshow_meta'], (int) $id, (string) $key, $value );
		} elseif ( 'movie' === $kind && isset( $options['update_movie_meta'] ) && is_callable( $options['update_movie_meta'] ) ) {
			$result = call_user_func( $options['update_movie_meta'], (int) $id, (string) $key, $value );
		} elseif ( isset( $options['update_meta'] ) && is_callable( $options['update_meta'] ) ) {
			$result = call_user_func( $options['update_meta'], (string) $kind, (int) $id, (string) $key, $value );
		} elseif ( class_exists( 'Movies_WP_Trailer_Upload_Service', false ) ) {
			$result = Movies_WP_Trailer_Upload_Service::update_meta( $kind, $id, $key, $value );
		} else {
			return false;
		}

		if ( is_wp_error( $result ) || false === $result ) {
			return false;
		}
		return true;
	}
}

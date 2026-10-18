<?php
/**
 * Reuse stored Streamit title/summary when reimporting with a blank local title.
 *
 * New imports still require an operator title. Updates matched by TMDb ID can
 * omit title/summary so adding trailers, episodes, or subtitles stays light.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Import_Existing_Local_Fields {

	/**
	 * @param array{
	 *   find_by_tmdb?: callable(int): (array{ids?: list<int>}|list<int>),
	 *   get_movie?: callable(int): (object|null)
	 * } $options
	 * @return array{title: string, summary: string}|WP_Error
	 */
	public static function fill_movie( $tmdb_id, $title, $summary, array $options = array() ) {
		return self::fill(
			(int) $tmdb_id,
			(string) $title,
			(string) $summary,
			'movie',
			$options
		);
	}

	/**
	 * @param array{
	 *   find_series_by_tmdb?: callable(int): (array{ids?: list<int>}|list<int>|WP_Error),
	 *   get_tvshow?: callable(int): (object|null)
	 * } $options
	 * @return array{title: string, summary: string}|WP_Error
	 */
	public static function fill_series( $tmdb_id, $title, $summary, array $options = array() ) {
		return self::fill(
			(int) $tmdb_id,
			(string) $title,
			(string) $summary,
			'series',
			$options
		);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array{title: string, summary: string}|WP_Error
	 */
	private static function fill( $tmdb_id, $title, $summary, $kind, array $options ) {
		$title   = trim( $title );
		$summary = trim( $summary );

		if ( '' !== $title ) {
			return array(
				'title'   => $title,
				'summary' => $summary,
			);
		}

		if ( $tmdb_id <= 0 ) {
			return self::title_required_error( $kind );
		}

		$ids = 'series' === $kind
			? self::lookup_series_ids( $tmdb_id, $options )
			: self::lookup_movie_ids( $tmdb_id, $options );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		$count = count( $ids );
		if ( 0 === $count ) {
			return self::title_required_error( $kind );
		}
		if ( $count > 1 ) {
			return new WP_Error(
				'import_existing_local_fields_duplicate',
				sprintf(
					/* translators: 1: TMDb ID, 2: comma-separated Streamit IDs */
					__( 'Multiple Streamit titles share TMDb ID %1$d (%2$s). Resolve duplicates before import.', 'movies-wp' ),
					$tmdb_id,
					implode( ', ', $ids )
				)
			);
		}

		$existing_id = (int) $ids[0];
		$object      = 'series' === $kind
			? self::load_tvshow( $existing_id, $options )
			: self::load_movie( $existing_id, $options );
		if ( ! $object ) {
			return self::title_required_error( $kind );
		}

		$stored_title   = self::object_title( $object );
		$stored_summary = self::object_summary( $object );
		if ( '' === $stored_title ) {
			return self::title_required_error( $kind );
		}

		return array(
			'title'   => $stored_title,
			'summary' => '' !== $summary ? $summary : $stored_summary,
		);
	}

	/**
	 * @return WP_Error
	 */
	private static function title_required_error( $kind ) {
		if ( 'series' === $kind ) {
			return new WP_Error(
				'import_existing_local_fields_title_required',
				__( 'Persian / local title is required for new series imports. Leave it blank only when updating an existing series by TMDb ID.', 'movies-wp' )
			);
		}

		return new WP_Error(
			'import_existing_local_fields_title_required',
			__( 'Persian / local title is required for new movie imports. Leave it blank only when updating an existing movie by TMDb ID.', 'movies-wp' )
		);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<int>
	 */
	private static function lookup_movie_ids( $tmdb_id, array $options ) {
		if ( isset( $options['find_by_tmdb'] ) && is_callable( $options['find_by_tmdb'] ) ) {
			return self::normalize_ids( call_user_func( $options['find_by_tmdb'], (int) $tmdb_id ) );
		}

		$ids = array();
		if ( ! function_exists( 'streamit_get_movies' ) ) {
			return $ids;
		}

		$result = streamit_get_movies(
			array(
				'per_page'    => 20,
				'paged'       => 1,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'meta_query'  => array(
					array(
						'key'     => '_tmdb_id',
						'value'   => (string) (int) $tmdb_id,
						'compare' => '=',
					),
				),
			)
		);
		if ( is_object( $result ) && ! empty( $result->results ) && is_array( $result->results ) ) {
			foreach ( $result->results as $movie ) {
				if ( is_object( $movie ) && method_exists( $movie, 'get_id' ) ) {
					$ids[] = (int) $movie->get_id();
				}
			}
		}

		return self::normalize_ids( $ids );
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<int>|WP_Error
	 */
	private static function lookup_series_ids( $tmdb_id, array $options ) {
		if ( isset( $options['find_series_by_tmdb'] ) && is_callable( $options['find_series_by_tmdb'] ) ) {
			$found = call_user_func( $options['find_series_by_tmdb'], (int) $tmdb_id );
			if ( is_wp_error( $found ) ) {
				return $found;
			}
			return self::normalize_ids( $found );
		}

		if ( ! function_exists( 'streamit_get_tvshows' ) ) {
			return array();
		}

		$result = streamit_get_tvshows(
			array(
				'per_page'    => -1,
				'post_status' => array( 'all' ),
				'meta_query'  => array(
					array(
						'key'     => '_tmdb_id',
						'value'   => (string) (int) $tmdb_id,
						'compare' => '=',
					),
				),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$ids = array();
		if ( is_object( $result ) && ! empty( $result->results ) && is_array( $result->results ) ) {
			foreach ( $result->results as $tvshow ) {
				if ( is_object( $tvshow ) && method_exists( $tvshow, 'get_id' ) ) {
					$ids[] = (int) $tvshow->get_id();
				}
			}
		}

		return self::normalize_ids( $ids );
	}

	/**
	 * @param mixed $found
	 * @return list<int>
	 */
	private static function normalize_ids( $found ) {
		$raw = array();
		if ( is_array( $found ) && isset( $found['ids'] ) && is_array( $found['ids'] ) ) {
			$raw = $found['ids'];
		} elseif ( is_array( $found ) ) {
			$raw = $found;
		}

		$ids = array();
		foreach ( $raw as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		$ids = array_values( array_unique( $ids ) );
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return object|null
	 */
	private static function load_movie( $movie_id, array $options ) {
		if ( isset( $options['get_movie'] ) && is_callable( $options['get_movie'] ) ) {
			$movie = call_user_func( $options['get_movie'], (int) $movie_id );
			return is_object( $movie ) ? $movie : null;
		}
		if ( ! function_exists( 'streamit_get_movie' ) ) {
			return null;
		}
		$movie = streamit_get_movie( (int) $movie_id );
		return is_object( $movie ) ? $movie : null;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return object|null
	 */
	private static function load_tvshow( $tvshow_id, array $options ) {
		if ( isset( $options['get_tvshow'] ) && is_callable( $options['get_tvshow'] ) ) {
			$tvshow = call_user_func( $options['get_tvshow'], (int) $tvshow_id );
			return is_object( $tvshow ) ? $tvshow : null;
		}
		if ( ! function_exists( 'streamit_get_tvshow' ) ) {
			return null;
		}
		$tvshow = streamit_get_tvshow( (int) $tvshow_id );
		return is_object( $tvshow ) ? $tvshow : null;
	}

	/**
	 * @param object $object
	 */
	private static function object_title( $object ) {
		if ( method_exists( $object, 'get_post_title' ) ) {
			return trim( (string) $object->get_post_title() );
		}
		if ( method_exists( $object, 'get_name' ) ) {
			return trim( (string) $object->get_name() );
		}
		return '';
	}

	/**
	 * @param object $object
	 */
	private static function object_summary( $object ) {
		if ( method_exists( $object, 'get_post_content' ) ) {
			return trim( (string) $object->get_post_content() );
		}
		return '';
	}
}

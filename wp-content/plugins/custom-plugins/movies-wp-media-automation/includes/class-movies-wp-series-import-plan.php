<?php
/**
 * Read-only Series Import Plan.
 *
 * Resolves Series, season, episode, and image actions without writing data.
 * A future adapter must execute these decisions without rediscovering identity.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Import_Plan {

	/**
	 * Build a deterministic plan from a normalized Series preview.
	 *
	 * @param array<string, mixed> $preview Series preview payload.
	 * @param array{
	 *   find_series_by_tmdb?: callable(int): mixed,
	 *   get_seasons?: callable(int): mixed,
	 *   find_episodes?: callable(int): mixed,
	 *   get_episode_meta?: callable(int,string): mixed
	 * } $options Deterministic test hooks. Production uses read-only Streamit helpers.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function build( array $preview, array $options = array() ) {
		if (
			empty( $preview['ok'] )
			|| 'series' !== ( $preview['type'] ?? '' )
			|| empty( $preview['ready_to_import'] )
		) {
			return new WP_Error(
				'series_import_plan_invalid_preview',
				__( 'Series preview payload is not ready for import.', 'movies-wp' )
			);
		}

		$input  = isset( $preview['input'] ) && is_array( $preview['input'] ) ? $preview['input'] : array();
		$series = isset( $preview['series'] ) && is_array( $preview['series'] ) ? $preview['series'] : array();

		$input_tmdb_id  = isset( $input['tmdb_id'] ) ? absint( $input['tmdb_id'] ) : 0;
		$series_tmdb_id = isset( $series['tmdb_id'] ) ? absint( $series['tmdb_id'] ) : 0;
		$title          = isset( $input['title'] ) ? trim( (string) $input['title'] ) : '';

		if ( $input_tmdb_id <= 0 || $series_tmdb_id <= 0 || $input_tmdb_id !== $series_tmdb_id || '' === $title ) {
			return new WP_Error(
				'series_import_plan_invalid_preview',
				__( 'Series preview is missing required identity fields.', 'movies-wp' )
			);
		}

		$series_ids = self::find_series_ids( $series_tmdb_id, $options );
		if ( is_wp_error( $series_ids ) ) {
			return $series_ids;
		}
		if ( count( $series_ids ) > 1 ) {
			return new WP_Error(
				'series_import_duplicate_identity',
				__( 'Multiple Streamit series share this TMDb ID. Resolve duplicates before importing.', 'movies-wp' ),
				array(
					'tmdb_id' => $series_tmdb_id,
					'ids'     => $series_ids,
				)
			);
		}

		$existing_series_id = 1 === count( $series_ids ) ? $series_ids[0] : null;
		$series_action      = null === $existing_series_id ? 'create' : 'update';
		$season_index       = array();
		$episode_index      = array(
			'by_id'   => array(),
			'by_tmdb' => array(),
			'by_se'   => array(),
		);

		if ( null !== $existing_series_id ) {
			$episode_rows = self::load_episode_rows( $existing_series_id, $options );
			if ( is_wp_error( $episode_rows ) ) {
				return $episode_rows;
			}
			$episode_index = self::index_episode_rows( $episode_rows );

			$raw_seasons = self::load_seasons( $existing_series_id, $options );
			if ( is_wp_error( $raw_seasons ) ) {
				return $raw_seasons;
			}
			$season_index = self::index_existing_seasons( $raw_seasons, $episode_index['by_id'], $options );
			if ( is_wp_error( $season_index ) ) {
				return $season_index;
			}
		}

		$season_plans       = array();
		$planned_seasons    = array();
		$planned_tmdb_ids   = array();
		$planned_se_numbers = array();
		$claimed_episode_ids = array();

		foreach ( isset( $series['seasons'] ) && is_array( $series['seasons'] ) ? $series['seasons'] : array() as $season ) {
			if ( ! is_array( $season ) ) {
				return new WP_Error(
					'series_import_plan_invalid_season',
					__( 'A Series season is missing its numeric season identity.', 'movies-wp' )
				);
			}

			$season_number = self::nonnegative_integer( $season['season_number'] ?? null );
			if ( null === $season_number ) {
				return new WP_Error(
					'series_import_plan_invalid_season',
					__( 'A Series season is missing its numeric season identity.', 'movies-wp' )
				);
			}
			if ( isset( $planned_seasons[ $season_number ] ) ) {
				return new WP_Error(
					'series_import_duplicate_season',
					__( 'The Series preview contains duplicate season numbers.', 'movies-wp' ),
					array( 'season_number' => $season_number )
				);
			}
			$planned_seasons[ $season_number ] = true;

			$existing_season = $season_index[ $season_number ] ?? null;
			$season_action   = null === $existing_season ? 'create' : 'update';
			$episode_plans   = array();
			$existing_episode_ids = null !== $existing_season
				? self::normalize_ids( isset( $existing_season['episodes'] ) && is_array( $existing_season['episodes'] ) ? $existing_season['episodes'] : array() )
				: array();

			foreach ( isset( $season['episodes'] ) && is_array( $season['episodes'] ) ? $season['episodes'] : array() as $episode ) {
				if ( ! is_array( $episode ) ) {
					return new WP_Error(
						'series_import_plan_invalid_episode',
						__( 'A Series episode has invalid identity fields.', 'movies-wp' )
					);
				}

				$episode_plan = self::plan_episode(
					$episode,
					$season_number,
					$episode_index,
					$claimed_episode_ids,
					$planned_tmdb_ids,
					$planned_se_numbers
				);
				if ( is_wp_error( $episode_plan ) ) {
					return $episode_plan;
				}
				$episode_plans[] = $episode_plan;
			}

			$planned_episode_ids = array();
			foreach ( $episode_plans as $episode_plan ) {
				if ( ! empty( $episode_plan['existing_episode_id'] ) ) {
					$planned_episode_ids[] = (int) $episode_plan['existing_episode_id'];
				}
			}
			$planned_episode_ids = self::normalize_ids( $planned_episode_ids );
			$unmatched_existing_episode_ids = array_values(
				array_diff( $existing_episode_ids, $planned_episode_ids )
			);

			$season_plans[] = array(
				'action'                         => $season_action,
				// Emit digit string "0" (not int 0). Adapters must still avoid empty()/?: — both collapse "0" in PHP.
				'season_number'                  => (string) $season_number,
				'existing_slot_index'            => null !== $existing_season ? $existing_season['slot_index'] : null,
				'identity_source'                => null !== $existing_season ? $existing_season['identity_source'] : 'preview_explicit',
				'name'                           => isset( $season['name'] ) ? (string) $season['name'] : '',
				'air_date'                       => isset( $season['air_date'] ) ? (string) $season['air_date'] : '',
				'overview'                       => isset( $season['overview'] ) ? (string) $season['overview'] : '',
				'existing_episode_ids'           => $existing_episode_ids,
				'unmatched_existing_episode_ids' => $unmatched_existing_episode_ids,
				'preserve_unmatched_episode_ids' => true,
				'image'                          => self::image_action(
					'season_poster',
					$season['poster_path'] ?? null,
					$season['poster_url'] ?? null,
					$season_action,
					'_seasons.image_id'
				),
				'episodes'                       => $episode_plans,
			);
		}

		usort(
			$season_plans,
			static function ( $left, $right ) {
				return (int) $left['season_number'] <=> (int) $right['season_number'];
			}
		);

		$admin_summary = isset( $input['summary'] ) ? (string) $input['summary'] : '';
		$summary       = '' !== trim( $admin_summary ) ? $admin_summary : (string) ( $series['overview'] ?? '' );

		return array(
			'ok'       => true,
			'type'     => 'series',
			'contract' => array(
				'kind'      => 'series_import_plan',
				'version'   => 1,
				'read_only' => true,
			),
			'identity' => array(
				'action'             => $series_action,
				'existing_series_id' => $existing_series_id,
				'match_by'           => '_tmdb_id',
				'match_count'        => count( $series_ids ),
			),
			'series'   => array(
				'tmdb_id'             => $series_tmdb_id,
				'title'               => $title,
				'summary'             => $summary,
				'summary_source'      => '' !== trim( $admin_summary ) ? 'admin' : 'tmdb',
				'tmdb_title'          => isset( $series['name'] ) ? (string) $series['name'] : '',
				'tmdb_original_title' => isset( $series['original_name'] ) ? (string) $series['original_name'] : '',
				'imdb_id'             => isset( $series['imdb_id'] ) ? (string) $series['imdb_id'] : '',
				'first_air_date'      => isset( $series['first_air_date'] ) ? (string) $series['first_air_date'] : '',
				'rating'              => $series['rating'] ?? null,
				'original_language'   => isset( $series['original_language'] ) ? (string) $series['original_language'] : '',
				'origin_country'      => isset( $series['origin_country'] ) && is_array( $series['origin_country'] ) ? $series['origin_country'] : array(),
				'genres'              => isset( $series['genres'] ) && is_array( $series['genres'] ) ? $series['genres'] : array(),
				'cast'                => isset( $series['cast'] ) && is_array( $series['cast'] ) ? $series['cast'] : array(),
				'crew'                => isset( $series['crew'] ) && is_array( $series['crew'] ) ? $series['crew'] : array(),
			),
			'images'   => array(
				'poster'   => self::image_action(
					'poster',
					$series['poster_path'] ?? null,
					$series['poster_url'] ?? null,
					$series_action,
					'_portrait_thumbmail'
				),
				'backdrop' => self::image_action(
					'backdrop',
					$series['backdrop_path'] ?? null,
					$series['backdrop_url'] ?? null,
					$series_action,
					'thumbnail_id'
				),
			),
			'sources_policy'  => array(
				'episode_meta_key' => '_sources',
				'mutate'           => false,
				'actions'          => array(),
				'rule'             => 'Series Import Plan never creates, updates, merges, or deletes episode _sources.',
			),
			'seasons'         => $season_plans,
			'warnings'        => isset( $preview['validation']['warnings'] ) && is_array( $preview['validation']['warnings'] )
				? $preview['validation']['warnings']
				: array(),
			'errors'          => array(),
			'ready_to_import' => true,
			'notes'           => array(
				'This plan is read-only. No Streamit or WordPress writes were performed.',
				'Season identity is an explicit digit-string season_number (including "0"); array index and position are never identity. Adapters must compare with === / !== \'\' — never empty() or ?: — because PHP treats "0" as falsy.',
				'Legacy season identity is inferred only when every linked episode has the same valid _season_number.',
				'Episode identity is scoped to the matched TV show: TMDb ID first, then season plus episode number.',
				'Existing season episode ID lists are preserved; unmatched IDs remain listed for the adapter to keep.',
				'Episode _sources are never planned and must remain untouched by any later Series persistence step.',
			),
		);
	}

	/**
	 * @return list<int>|WP_Error
	 */
	private static function find_series_ids( $tmdb_id, array $options ) {
		if ( isset( $options['find_series_by_tmdb'] ) && is_callable( $options['find_series_by_tmdb'] ) ) {
			$found = call_user_func( $options['find_series_by_tmdb'], (int) $tmdb_id );
			if ( is_wp_error( $found ) ) {
				return $found;
			}
			$ids = is_array( $found ) && isset( $found['ids'] ) && is_array( $found['ids'] )
				? $found['ids']
				: $found;
			if ( ! is_array( $ids ) ) {
				return new WP_Error(
					'series_import_discovery_failed',
					__( 'Series identity discovery returned invalid data.', 'movies-wp' )
				);
			}
			return self::normalize_ids( $ids );
		}

		if ( ! function_exists( 'streamit_get_tvshows' ) ) {
			return new WP_Error(
				'series_import_discovery_unavailable',
				__( 'Streamit Series discovery helpers are not available.', 'movies-wp' )
			);
		}

		$result = streamit_get_tvshows(
			array(
				'per_page'    => -1,
				'post_status' => array( 'all' ),
				'meta_query'  => array(
					array(
						'key'     => '_tmdb_id',
						'value'   => (string) $tmdb_id,
						'compare' => '=',
					),
				),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_object( $result ) || ! isset( $result->results ) || ! is_array( $result->results ) ) {
			return new WP_Error(
				'series_import_discovery_failed',
				__( 'Series identity discovery returned invalid data.', 'movies-wp' )
			);
		}
		return self::ids_from_query_result( $result );
	}

	/**
	 * @return list<array<string, mixed>>|WP_Error
	 */
	private static function load_episode_rows( $series_id, array $options ) {
		if ( isset( $options['find_episodes'] ) && is_callable( $options['find_episodes'] ) ) {
			$rows = call_user_func( $options['find_episodes'], (int) $series_id );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			if ( is_array( $rows ) && isset( $rows['episodes'] ) && is_array( $rows['episodes'] ) ) {
				$rows = $rows['episodes'];
			}
			if ( ! is_array( $rows ) ) {
				return new WP_Error(
					'series_import_discovery_failed',
					__( 'Episode identity discovery returned invalid data.', 'movies-wp' )
				);
			}
			return self::normalize_episode_rows( $rows, $series_id );
		}

		if ( ! function_exists( 'streamit_get_episodes' ) ) {
			return new WP_Error(
				'series_import_discovery_unavailable',
				__( 'Streamit episode discovery helpers are not available.', 'movies-wp' )
			);
		}

		/*
		 * Streamit episode queries use one SQL alias for all meta_query clauses.
		 * Query once by tvshow_id, then read identity through the episode object/meta
		 * helpers instead of attempting an unsafe multi-key meta query.
		 */
		$result = streamit_get_episodes(
			array(
				'per_page'    => -1,
				'post_status' => array( 'all' ),
				'meta_query'  => array(
					array(
						'key'     => 'tvshow_id',
						'value'   => (string) $series_id,
						'compare' => '=',
					),
				),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_object( $result ) || ! isset( $result->results ) || ! is_array( $result->results ) ) {
			return new WP_Error(
				'series_import_discovery_failed',
				__( 'Episode identity discovery returned invalid data.', 'movies-wp' )
			);
		}

		$rows = array();
		foreach ( self::query_results( $result ) as $episode ) {
			if ( ! is_object( $episode ) || ! method_exists( $episode, 'get_id' ) ) {
				continue;
			}
			$id = absint( $episode->get_id() );
			if ( $id <= 0 ) {
				continue;
			}
			$rows[] = array(
				'id'             => $id,
				'tvshow_id'      => $series_id,
				'tmdb_id'        => self::object_or_helper_meta( $episode, $id, '_tmdb_id' ),
				'season_number'  => self::object_or_helper_meta( $episode, $id, '_season_number' ),
				'episode_number' => self::object_or_helper_meta( $episode, $id, '_episode_number' ),
			);
		}
		return self::normalize_episode_rows( $rows, $series_id );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function normalize_episode_rows( array $rows, $series_id ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( is_object( $row ) && method_exists( $row, 'get_id' ) ) {
				$id  = absint( $row->get_id() );
				$row = array(
					'id'             => $id,
					'tvshow_id'      => $series_id,
					'tmdb_id'        => self::object_or_helper_meta( $row, $id, '_tmdb_id' ),
					'season_number'  => self::object_or_helper_meta( $row, $id, '_season_number' ),
					'episode_number' => self::object_or_helper_meta( $row, $id, '_episode_number' ),
				);
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $id <= 0 ) {
				continue;
			}
			$row_tvshow = isset( $row['tvshow_id'] ) ? absint( $row['tvshow_id'] ) : (int) $series_id;
			if ( $row_tvshow !== (int) $series_id ) {
				continue;
			}
			$out[] = array(
				'id'             => $id,
				'tvshow_id'      => (int) $series_id,
				'tmdb_id'        => isset( $row['tmdb_id'] ) ? absint( $row['tmdb_id'] ) : 0,
				'season_number'  => self::nonnegative_integer( $row['season_number'] ?? null ),
				'episode_number' => self::positive_episode_number( $row['episode_number'] ?? null ),
			);
		}
		return $out;
	}

	/**
	 * @return array{by_id:array,by_tmdb:array,by_se:array}
	 */
	private static function index_episode_rows( array $rows ) {
		$index = array(
			'by_id'   => array(),
			'by_tmdb' => array(),
			'by_se'   => array(),
		);
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			$index['by_id'][ $id ] = $row;
			if ( $row['tmdb_id'] > 0 ) {
				$index['by_tmdb'][ $row['tmdb_id'] ][] = $id;
			}
			if ( null !== $row['season_number'] && null !== $row['episode_number'] ) {
				$index['by_se'][ $row['season_number'] . ':' . $row['episode_number'] ][] = $id;
			}
		}
		foreach ( array( 'by_tmdb', 'by_se' ) as $bucket ) {
			foreach ( $index[ $bucket ] as $key => $ids ) {
				$index[ $bucket ][ $key ] = self::normalize_ids( $ids );
			}
		}
		return $index;
	}

	/**
	 * @return list<array<string, mixed>>|WP_Error
	 */
	private static function load_seasons( $series_id, array $options ) {
		if ( isset( $options['get_seasons'] ) && is_callable( $options['get_seasons'] ) ) {
			$raw = call_user_func( $options['get_seasons'], (int) $series_id );
		} elseif ( function_exists( 'streamit_get_tvshow_meta' ) ) {
			$raw = streamit_get_tvshow_meta( (int) $series_id, '_seasons', true );
		} else {
			return new WP_Error(
				'series_import_discovery_unavailable',
				__( 'Streamit season discovery helpers are not available.', 'movies-wp' )
			);
		}

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		if ( is_string( $raw ) && function_exists( 'maybe_unserialize' ) ) {
			$raw = maybe_unserialize( $raw );
		}
		if ( null === $raw || false === $raw || '' === $raw ) {
			return array();
		}
		if ( ! is_array( $raw ) ) {
			return new WP_Error(
				'series_import_discovery_failed',
				__( 'Existing Streamit seasons could not be read.', 'movies-wp' )
			);
		}
		return array_values( $raw );
	}

	/**
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function index_existing_seasons( array $seasons, array $episodes_by_id, array $options ) {
		$index = array();
		foreach ( $seasons as $slot_index => $season ) {
			if ( ! is_array( $season ) ) {
				return self::legacy_season_error(
					'series_import_legacy_season_ambiguous',
					__( 'An existing Streamit season row is invalid and has no safe numeric identity.', 'movies-wp' ),
					$slot_index
				);
			}

			$explicit = array_key_exists( 'season_number', $season )
				? self::nonnegative_integer( $season['season_number'] )
				: null;
			if ( array_key_exists( 'season_number', $season ) && null === $explicit ) {
				return self::legacy_season_error(
					'series_import_legacy_season_ambiguous',
					__( 'An existing Streamit season has an invalid season_number.', 'movies-wp' ),
					$slot_index
				);
			}

			if ( null !== $explicit ) {
				$season_number  = $explicit;
				$identity_source = 'explicit_season_number';
			} else {
				$inferred = self::infer_legacy_season_number( $season, $slot_index, $episodes_by_id, $options );
				if ( is_wp_error( $inferred ) ) {
					return $inferred;
				}
				$season_number   = $inferred;
				$identity_source = 'linked_episode_meta';
			}

			if ( isset( $index[ $season_number ] ) ) {
				return new WP_Error(
					'series_import_duplicate_season',
					__( 'Streamit contains duplicate season rows with the same numeric season identity.', 'movies-wp' ),
					array( 'season_number' => $season_number )
				);
			}

			$season['season_number']  = $season_number;
			$season['slot_index']     = (int) $slot_index;
			$season['identity_source'] = $identity_source;
			$index[ $season_number ]  = $season;
		}
		return $index;
	}

	/**
	 * Infer a legacy row only when every linked episode agrees.
	 *
	 * @return int|WP_Error
	 */
	private static function infer_legacy_season_number( array $season, $slot_index, array $episodes_by_id, array $options ) {
		$episode_ids = isset( $season['episodes'] ) && is_array( $season['episodes'] )
			? self::normalize_ids( $season['episodes'] )
			: array();
		if ( array() === $episode_ids ) {
			return self::legacy_season_error(
				'series_import_legacy_season_empty',
				__( 'An existing legacy season has no linked episodes, so its season number cannot be inferred safely.', 'movies-wp' ),
				$slot_index
			);
		}

		$numbers = array();
		foreach ( $episode_ids as $episode_id ) {
			$value = isset( $episodes_by_id[ $episode_id ] )
				? $episodes_by_id[ $episode_id ]['season_number']
				: self::get_episode_meta( $episode_id, '_season_number', $options );
			$number = self::nonnegative_integer( $value );
			if ( null === $number ) {
				return self::legacy_season_error(
					'series_import_legacy_season_ambiguous',
					__( 'An existing legacy season has a linked episode without a valid _season_number.', 'movies-wp' ),
					$slot_index,
					array( 'episode_id' => $episode_id )
				);
			}
			$numbers[ $number ] = true;
		}

		if ( 1 !== count( $numbers ) ) {
			return self::legacy_season_error(
				'series_import_legacy_season_conflict',
				__( 'Linked episodes disagree about the legacy season number. Resolve the conflict before importing.', 'movies-wp' ),
				$slot_index,
				array( 'season_numbers' => array_map( 'intval', array_keys( $numbers ) ) )
			);
		}
		return (int) array_key_first( $numbers );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function plan_episode(
		array $episode,
		$parent_season_number,
		array $index,
		array &$claimed_ids,
		array &$planned_tmdb_ids,
		array &$planned_se_numbers
	) {
		$tmdb_id       = isset( $episode['tmdb_id'] ) ? absint( $episode['tmdb_id'] ) : 0;
		$season_number = self::nonnegative_integer( $episode['season_number'] ?? null );
		$episode_number = self::positive_episode_number( $episode['episode_number'] ?? null );
		if ( $tmdb_id <= 0 || null === $season_number || null === $episode_number || $season_number !== (int) $parent_season_number ) {
			return new WP_Error(
				'series_import_plan_invalid_episode',
				__( 'A Series episode has invalid identity fields.', 'movies-wp' )
			);
		}

		$se_key = $season_number . ':' . $episode_number;
		if ( isset( $planned_tmdb_ids[ $tmdb_id ] ) || isset( $planned_se_numbers[ $se_key ] ) ) {
			return new WP_Error(
				'series_import_duplicate_episode',
				__( 'The Series preview contains duplicate episode identities.', 'movies-wp' )
			);
		}
		$planned_tmdb_ids[ $tmdb_id ]   = true;
		$planned_se_numbers[ $se_key ] = true;

		$tmdb_matches = $index['by_tmdb'][ $tmdb_id ] ?? array();
		$se_matches   = $index['by_se'][ $se_key ] ?? array();
		if ( count( $tmdb_matches ) > 1 || count( $se_matches ) > 1 ) {
			return new WP_Error(
				'series_import_duplicate_episode',
				__( 'Multiple Streamit episodes match the same Series episode identity.', 'movies-wp' ),
				array(
					'tmdb_matches'           => $tmdb_matches,
					'season_episode_matches' => $se_matches,
				)
			);
		}

		$existing_id = null;
		$match_by    = null;
		if ( 1 === count( $tmdb_matches ) ) {
			$existing_id = $tmdb_matches[0];
			$match_by    = 'tvshow_id+_tmdb_id';
			if ( 1 === count( $se_matches ) && $se_matches[0] !== $existing_id ) {
				return new WP_Error(
					'series_import_episode_identity_conflict',
					__( 'TMDb and season/episode identity point to different Streamit episodes.', 'movies-wp' ),
					array(
						'tmdb_episode_id'           => $tmdb_matches[0],
						'season_episode_id'         => $se_matches[0],
						'season_number'             => $season_number,
						'episode_number'            => $episode_number,
					)
				);
			}
		} elseif ( 1 === count( $se_matches ) ) {
			$existing_id = $se_matches[0];
			$match_by    = 'tvshow_id+_season_number+_episode_number';
		}

		if ( null !== $existing_id && isset( $claimed_ids[ $existing_id ] ) ) {
			return new WP_Error(
				'series_import_duplicate_episode',
				__( 'Two TMDb episodes matched the same Streamit episode.', 'movies-wp' )
			);
		}
		if ( null !== $existing_id ) {
			$claimed_ids[ $existing_id ] = true;
		}

		$action = null === $existing_id ? 'create' : 'update';
		return array(
			'action'              => $action,
			'existing_episode_id' => $existing_id,
			'match_by'            => $match_by,
			'tmdb_id'             => $tmdb_id,
			'season_number'       => (string) $season_number,
			'episode_number'      => $episode_number,
			'name'                => isset( $episode['name'] ) ? (string) $episode['name'] : '',
			'overview'            => isset( $episode['overview'] ) ? (string) $episode['overview'] : '',
			'air_date'            => isset( $episode['air_date'] ) ? (string) $episode['air_date'] : '',
			'runtime'             => $episode['runtime'] ?? null,
			'sources_action'      => 'keep_existing_untouched',
			'image'               => self::image_action(
				'still',
				$episode['still_path'] ?? null,
				$episode['still_url'] ?? null,
				$action,
				'thumbnail_id'
			),
		);
	}

	/**
	 * @return array{role:string,action:string,path:?string,url:?string,target:string}
	 */
	private static function image_action( $role, $path, $url, $owner_action, $target ) {
		$path = is_string( $path ) && '' !== trim( $path ) ? trim( $path ) : null;
		$url  = is_string( $url ) && '' !== trim( $url ) ? trim( $url ) : null;
		if ( null !== $path || null !== $url ) {
			$action = 'set';
		} elseif ( 'update' === $owner_action ) {
			$action = 'keep_existing';
		} else {
			$action = 'skip_missing';
		}
		return array(
			'role'   => (string) $role,
			'action' => $action,
			'path'   => $path,
			'url'    => $url,
			'target' => (string) $target,
		);
	}

	private static function get_episode_meta( $episode_id, $key, array $options ) {
		if ( isset( $options['get_episode_meta'] ) && is_callable( $options['get_episode_meta'] ) ) {
			return call_user_func( $options['get_episode_meta'], (int) $episode_id, (string) $key );
		}
		if ( function_exists( 'streamit_get_episode_meta' ) ) {
			return streamit_get_episode_meta( (int) $episode_id, (string) $key, true );
		}
		return null;
	}

	private static function object_or_helper_meta( $object, $id, $key ) {
		if ( is_object( $object ) && method_exists( $object, 'get_meta' ) ) {
			return $object->get_meta( $key );
		}
		if ( function_exists( 'streamit_get_episode_meta' ) ) {
			return streamit_get_episode_meta( (int) $id, (string) $key, true );
		}
		return null;
	}

	/**
	 * Accept integer values and canonical digit strings, including zero.
	 */
	private static function nonnegative_integer( $value ) {
		if ( is_int( $value ) ) {
			return $value >= 0 ? $value : null;
		}
		if ( is_string( $value ) && preg_match( '/^\d+$/', trim( $value ) ) ) {
			return (int) trim( $value );
		}
		return null;
	}

	private static function positive_episode_number( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}
		if ( is_string( $value ) && preg_match( '/^(?:E)?(\d+)$/i', trim( $value ), $matches ) ) {
			$number = (int) $matches[1];
			return $number > 0 ? $number : null;
		}
		return null;
	}

	/**
	 * @return list<int>
	 */
	private static function normalize_ids( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}

	/**
	 * @return list<int>
	 */
	private static function ids_from_query_result( $result ) {
		$ids = array();
		foreach ( self::query_results( $result ) as $object ) {
			if ( is_object( $object ) && method_exists( $object, 'get_id' ) ) {
				$ids[] = (int) $object->get_id();
			} elseif ( is_object( $object ) && isset( $object->ID ) ) {
				$ids[] = (int) $object->ID;
			}
		}
		return self::normalize_ids( $ids );
	}

	/**
	 * @return list<mixed>
	 */
	private static function query_results( $result ) {
		return is_object( $result ) && isset( $result->results ) && is_array( $result->results )
			? $result->results
			: array();
	}

	private static function legacy_season_error( $code, $message, $slot_index, array $data = array() ) {
		$data['slot_index'] = (int) $slot_index;
		return new WP_Error( $code, $message, $data );
	}
}

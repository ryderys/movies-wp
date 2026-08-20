<?php
/**
 * Streamit TV adapter for an approved Series Import Plan.
 *
 * The plan owns identity decisions. This class only executes them and never
 * calls Streamit's partial-row update wrappers.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-movies-wp-series-import-profiler.php';

class Movies_WP_Streamit_TV_Adapter {

	/**
	 * Apply a Series Import Plan.
	 *
	 * @param array<string, mixed> $plan    Series plan.
	 * @param array<string, mixed> $options Injectable test hooks.
	 * @return array<string, mixed>
	 */
	public static function apply( array $plan, array $options = array() ) {
		$gate = self::validate_plan( $plan );
		if ( true !== $gate ) {
			return $gate;
		}

		$action       = (string) $plan['identity']['action'];
		$series_snap  = Movies_WP_Series_Import_Profiler::phase_start( 'series_create' );
		$series       = self::persist_series( $plan, $options );
		Movies_WP_Series_Import_Profiler::phase_end( 'series_create', $series_snap, 1, (string) $action );
		if ( empty( $series['ok'] ) ) {
			$partial_series_id = absint( $series['series_id'] ?? 0 );
			return self::result(
				false,
				$partial_series_id > 0 ? $partial_series_id : null,
				$action,
				$series,
				array(),
				array(),
				array(),
				array( $series['error'] )
			);
		}
		Movies_WP_Series_Import_Profiler::progress( 'SERIES CREATED id=' . (int) $series['series_id'] );

		$series_id = (int) $series['series_id'];
		$people    = self::apply_people_phase( $series_id, $plan, $options );
		if ( empty( $people['ok'] ) ) {
			return self::result( false, $series_id, $action, $series, array(), array(), array(), array( $people['error'] ) );
		}
		$seasons   = self::load_tvshow_meta( $series_id, '_seasons', $options );
		if ( is_wp_error( $seasons ) || ! self::empty_meta_value( $seasons ) && ! is_array( $seasons ) ) {
			$error = self::err(
				'series_tv_adapter_seasons_unreadable',
				__( 'The complete existing Streamit season list could not be loaded safely.', 'movies-wp' )
			);
			return self::result( false, $series_id, $action, $series, array(), array(), array(), array( $error ) );
		}
		$seasons = is_array( $seasons ) ? array_values( $seasons ) : array();

		$image_snap    = Movies_WP_Series_Import_Profiler::phase_start( 'series_images' );
		$image_results = self::persist_series_images( $series_id, $plan['images'], $options );
		Movies_WP_Series_Import_Profiler::phase_end( 'series_images', $image_snap, count( $image_results ), 'poster_backdrop' );
		Movies_WP_Series_Import_Profiler::progress( 'SERIES IMAGES DONE' );
		$episode_rows  = array();
		$season_rows   = array();
		$errors        = array();

		foreach ( $image_results as $image_result ) {
			if ( empty( $image_result['ok'] ) ) {
				$errors[] = $image_result['error'];
			}
		}

		$episode_ids_by_season = array();
		foreach ( $plan['seasons'] as $season_plan ) {
			$season_number = self::season_number_string( $season_plan['season_number'] ?? null );
			$episode_ids_by_season[ $season_number ] = array();

			foreach ( $season_plan['episodes'] as $episode_plan ) {
				$episode_snap   = Movies_WP_Series_Import_Profiler::phase_start( 'episode_create' );
				$episode_result = self::persist_episode( $series_id, $episode_plan, $options );
				$episode_label  = sprintf( 'S%02dE%02d', (int) ( $episode_plan['season_number'] ?? 0 ), (int) ( $episode_plan['episode_number'] ?? 0 ) );
				Movies_WP_Series_Import_Profiler::phase_end( 'episode_create', $episode_snap, 1, $episode_label );
				$episode_rows[] = $episode_result;

				if ( ! empty( $episode_result['episode_id'] ) ) {
					$episode_ids_by_season[ $season_number ][] = (int) $episode_result['episode_id'];
				}
				if ( empty( $episode_result['ok'] ) ) {
					$errors[] = $episode_result['error'];
				}
			}
		}

		foreach ( $plan['seasons'] as $season_plan ) {
			$season_snap   = Movies_WP_Series_Import_Profiler::phase_start( 'season_create' );
			$season_result = self::upsert_season(
				$series_id,
				$seasons,
				$season_plan,
				$episode_ids_by_season[ self::season_number_string( $season_plan['season_number'] ?? null ) ],
				$options
			);
			Movies_WP_Series_Import_Profiler::phase_end( 'season_create', $season_snap, count( $season_plan['episodes'] ?? array() ), (string) ( $season_plan['season_number'] ?? '' ) );
			$season_rows[] = $season_result;
			if ( empty( $season_result['ok'] ) ) {
				$errors[] = $season_result['error'];
			} else {
				$seasons = $season_result['seasons'];
				Movies_WP_Series_Import_Profiler::progress( 'SEASON CREATED ' . (string) ( $season_plan['season_number'] ?? '' ) );
			}
		}

		if ( array() !== $season_rows ) {
			$written = self::write_tvshow_meta( $series_id, '_seasons', $seasons, $options );
			if ( true !== $written ) {
				$error        = self::err( 'series_tv_adapter_seasons_failed', __( 'Failed to save Streamit seasons.', 'movies-wp' ) );
				$errors[]     = $error;
				$season_rows[] = array(
					'ok'     => false,
					'action' => 'write',
					'error'  => $error,
				);
			}
		}

		return self::result(
			array() === $errors,
			$series_id,
			$action,
			$series,
			$season_rows,
			$episode_rows,
			$image_results,
			$errors
		);
	}

	private static function validate_plan( array $plan ) {
		if (
			empty( $plan['ok'] )
			|| empty( $plan['ready_to_import'] )
			|| ! empty( $plan['errors'] )
			|| 'series_import_plan' !== ( $plan['contract']['kind'] ?? '' )
		) {
			return self::result(
				false,
				null,
				null,
				array(),
				array(),
				array(),
				array(),
				array( self::err( 'series_tv_adapter_invalid_plan', __( 'Series Import Plan is not ready to apply.', 'movies-wp' ) ) )
			);
		}

		$action = $plan['identity']['action'] ?? '';
		if ( ! in_array( $action, array( 'create', 'update' ), true ) ) {
			return self::result(
				false,
				null,
				null,
				array(),
				array(),
				array(),
				array(),
				array( self::err( 'series_tv_adapter_invalid_identity', __( 'Series plan identity action must be create or update.', 'movies-wp' ) ) )
			);
		}
		if ( empty( $plan['series']['title'] ) || ! isset( $plan['series']['summary'] ) || ! is_array( $plan['seasons'] ?? null ) ) {
			return self::result(
				false,
				null,
				$action,
				array(),
				array(),
				array(),
				array(),
				array( self::err( 'series_tv_adapter_missing_data', __( 'Series plan is missing required data.', 'movies-wp' ) ) )
			);
		}
		if ( 'update' === $action && absint( $plan['identity']['existing_series_id'] ?? 0 ) <= 0 ) {
			return self::result(
				false,
				null,
				$action,
				array(),
				array(),
				array(),
				array(),
				array( self::err( 'series_tv_adapter_missing_id', __( 'Series update plan is missing its existing Streamit ID.', 'movies-wp' ) ) )
			);
		}
		if (
			! isset( $plan['sources_policy'] )
			|| ! is_array( $plan['sources_policy'] )
			|| false !== ( $plan['sources_policy']['mutate'] ?? null )
			|| array() !== ( $plan['sources_policy']['actions'] ?? null )
		) {
			return self::result(
				false,
				null,
				$action,
				array(),
				array(),
				array(),
				array(),
				array( self::err( 'series_tv_adapter_sources_policy', __( 'Series plan must explicitly forbid episode source mutations.', 'movies-wp' ) ) )
			);
		}
		foreach ( $plan['seasons'] as $season ) {
			$number = self::season_number_string( $season['season_number'] ?? null );
			if ( null === $number ) {
				return self::result(
					false,
					null,
					$action,
					array(),
					array(),
					array(),
					array(),
					array( self::err( 'series_tv_adapter_invalid_season_number', __( 'Every Series season requires an explicit digit-string season_number.', 'movies-wp' ) ) )
				);
			}
			foreach ( is_array( $season['episodes'] ?? null ) ? $season['episodes'] : array() as $episode ) {
				if (
					$number !== self::season_number_string( $episode['season_number'] ?? null )
					|| 'keep_existing_untouched' !== ( $episode['sources_action'] ?? null )
				) {
					return self::result(
						false,
						null,
						$action,
						array(),
						array(),
						array(),
						array(),
						array( self::err( 'series_tv_adapter_invalid_episode_policy', __( 'Series episode identity or source policy is invalid.', 'movies-wp' ) ) )
					);
				}
			}
		}
		return true;
	}

	/**
	 * Persist the Streamit series row and identity metadata only.
	 *
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public static function apply_series_phase( array $plan, array $options = array() ) {
		$gate = self::validate_plan( $plan );
		if ( true !== $gate ) {
			return is_array( $gate ) ? $gate : array( 'ok' => false, 'error' => self::err( 'series_tv_adapter_invalid_plan', __( 'Series Import Plan is not ready to apply.', 'movies-wp' ) ) );
		}
		return self::persist_series( $plan, $options );
	}

	/**
	 * @param int                  $series_id
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public static function apply_people_phase( $series_id, array $plan, array $options = array() ) {
		$people = self::persist_series_people( (int) $series_id, is_array( $plan['series'] ?? null ) ? $plan['series'] : array(), $options );
		if ( true !== $people ) {
			return array(
				'ok'        => false,
				'series_id' => (int) $series_id,
				'error'     => $people,
			);
		}
		return array(
			'ok'        => true,
			'series_id' => (int) $series_id,
		);
	}

	/**
	 * @param int                  $series_id
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public static function apply_images_phase( $series_id, array $plan, array $options = array() ) {
		$images  = isset( $plan['images'] ) && is_array( $plan['images'] ) ? $plan['images'] : array();
		$results = self::persist_series_images( (int) $series_id, $images, $options );
		$errors  = array();
		foreach ( $results as $image_result ) {
			if ( empty( $image_result['ok'] ) && isset( $image_result['error'] ) ) {
				$errors[] = $image_result['error'];
			}
		}
		return array(
			'ok'        => array() === $errors,
			'continue'  => true,
			'series_id' => (int) $series_id,
			'images'    => $results,
			'error'     => isset( $errors[0] ) ? $errors[0] : null,
			'warnings'  => $errors,
		);
	}

	/**
	 * @param int                  $series_id
	 * @param array<string, mixed> $episode_plan
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public static function apply_episode_phase( $series_id, array $episode_plan, array $options = array() ) {
		return self::persist_episode( (int) $series_id, $episode_plan, $options );
	}

	/**
	 * @param int                  $series_id
	 * @param array<string, mixed> $plan
	 * @param array<string, array<int, int>> $episode_ids_by_season
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public static function apply_seasons_phase( $series_id, array $plan, array $episode_ids_by_season, array $options = array() ) {
		$seasons = self::load_tvshow_meta( $series_id, '_seasons', $options );
		if ( is_wp_error( $seasons ) || ( ! self::empty_meta_value( $seasons ) && ! is_array( $seasons ) ) ) {
			return array(
				'ok'     => false,
				'error'  => self::err( 'series_tv_adapter_seasons_unreadable', __( 'The complete existing Streamit season list could not be loaded safely.', 'movies-wp' ) ),
			);
		}
		$seasons     = is_array( $seasons ) ? array_values( $seasons ) : array();
		$season_rows = array();
		$errors      = array();
		foreach ( isset( $plan['seasons'] ) && is_array( $plan['seasons'] ) ? $plan['seasons'] : array() as $season_plan ) {
			$key           = self::season_number_string( $season_plan['season_number'] ?? null );
			$ids           = isset( $episode_ids_by_season[ $key ] ) && is_array( $episode_ids_by_season[ $key ] ) ? $episode_ids_by_season[ $key ] : array();
			$season_result = self::upsert_season( $series_id, $seasons, $season_plan, $ids, $options );
			$season_rows[] = $season_result;
			if ( empty( $season_result['ok'] ) ) {
				$errors[] = $season_result['error'];
				continue;
			}
			$seasons = $season_result['seasons'];
		}
		if ( array() !== $season_rows ) {
			$written = self::write_tvshow_meta( $series_id, '_seasons', $seasons, $options );
			if ( true !== $written ) {
				$errors[] = self::err( 'series_tv_adapter_seasons_failed', __( 'Failed to save Streamit seasons.', 'movies-wp' ) );
			}
		}
		return array(
			'ok'      => array() === $errors,
			'seasons' => $season_rows,
			'error'   => isset( $errors[0] ) ? $errors[0] : null,
			'warnings'=> $errors,
		);
	}

	private static function persist_series( array $plan, array $options ) {
		$action = (string) $plan['identity']['action'];
		$data   = $plan['series'];

		$row_snap = Movies_WP_Series_Import_Profiler::phase_start( 'series_row' );
		if ( 'create' === $action ) {
			$row = self::new_row(
				(string) $data['title'],
				(string) $data['summary'],
				'tvshow',
				$options
			);
			$created = isset( $options['create_tvshow'] ) && is_callable( $options['create_tvshow'] )
				? call_user_func( $options['create_tvshow'], $row )
				: ( function_exists( 'streamit_add_tvshow' ) ? streamit_add_tvshow( $row ) : new WP_Error( 'missing_api', 'streamit_add_tvshow() is not available.' ) );
			if ( is_wp_error( $created ) || absint( $created ) <= 0 ) {
				Movies_WP_Series_Import_Profiler::phase_end( 'series_row', $row_snap, 0, 'failed' );
				return array(
					'ok'     => false,
					'action' => $action,
					'error'  => self::external_error( $created, 'series_tv_adapter_create_failed', __( 'Streamit series creation failed.', 'movies-wp' ) ),
				);
			}
			$series_id = absint( $created );
		} else {
			$series_id = absint( $plan['identity']['existing_series_id'] );
			$tvshow    = isset( $options['get_tvshow'] ) && is_callable( $options['get_tvshow'] )
				? call_user_func( $options['get_tvshow'], $series_id )
				: ( function_exists( 'streamit_get_tvshow' ) ? streamit_get_tvshow( $series_id ) : null );
			$row       = self::row_from_object( $tvshow, $series_id, 'tvshow' );
			if ( is_wp_error( $row ) ) {
				Movies_WP_Series_Import_Profiler::phase_end( 'series_row', $row_snap, 0, 'failed' );
				return array( 'ok' => false, 'action' => $action, 'error' => self::err( $row->get_error_code(), $row->get_error_message() ) );
			}

			// Existing local title is authoritative on update.
			$row['post_content']      = (string) $data['summary'];
			$row['post_modified']     = self::now( false, $options );
			$row['post_modified_gmt'] = self::now( true, $options );
			$updated = isset( $options['update_tvshow_row'] ) && is_callable( $options['update_tvshow_row'] )
				? call_user_func( $options['update_tvshow_row'], $series_id, $row )
				: ( class_exists( 'Streamit_Tvshow' ) ? ( new Streamit_Tvshow() )->update( $series_id, $row ) : new WP_Error( 'missing_api', 'Streamit_Tvshow is not available.' ) );
			if ( is_wp_error( $updated ) || false === $updated || null === $updated ) {
				Movies_WP_Series_Import_Profiler::phase_end( 'series_row', $row_snap, 0, 'failed' );
				return array(
					'ok'     => false,
					'action' => $action,
					'error'  => self::external_error( $updated, 'series_tv_adapter_update_failed', __( 'Streamit series update failed.', 'movies-wp' ) ),
				);
			}
		}
		Movies_WP_Series_Import_Profiler::phase_end( 'series_row', $row_snap, 1, $action );

		$meta = array(
			'_tmdb_id'              => (string) absint( $data['tmdb_id'] ?? 0 ),
			'_tmdb_title'           => trim( (string) ( $data['tmdb_title'] ?? '' ) ),
			'_tmdb_original_title'  => trim( (string) ( $data['tmdb_original_title'] ?? '' ) ),
			'_imdb_id'              => trim( (string) ( $data['imdb_id'] ?? '' ) ),
			'name_custom_imdb_rating' => self::rating( $data['rating'] ?? null ),
		);
		$meta_snap = Movies_WP_Series_Import_Profiler::phase_start( 'series_meta' );
		foreach ( $meta as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			if ( true !== self::write_tvshow_meta( $series_id, $key, $value, $options ) ) {
				Movies_WP_Series_Import_Profiler::phase_end( 'series_meta', $meta_snap, 0, $key );
				return array(
					'ok'        => false,
					'action'    => $action,
					'series_id' => $series_id,
					'error'     => self::err( 'series_tv_adapter_meta_failed', sprintf( __( 'Failed to save Series metadata %s.', 'movies-wp' ), $key ) ),
				);
			}
		}

		$enrichment = self::persist_series_enrichment( $series_id, $data, $options );
		Movies_WP_Series_Import_Profiler::phase_end( 'series_meta', $meta_snap, count( $meta ), 'identity_language_country_genres' );
		if ( true !== $enrichment ) {
			return array( 'ok' => false, 'action' => $action, 'series_id' => $series_id, 'error' => $enrichment );
		}

		return array( 'ok' => true, 'action' => $action, 'series_id' => $series_id );
	}

	private static function persist_series_enrichment( $series_id, array $data, array $options ) {
		$language = trim( (string) ( $data['original_language'] ?? '' ) );
		if ( '' !== $language ) {
			$label = strtoupper( $language );
			if ( function_exists( 'streamit_get_language_mapping' ) ) {
				$mapped = array_search( $language, streamit_get_language_mapping(), true );
				if ( false !== $mapped ) {
					$label = $mapped;
				}
			}
			$value = array( 'slugs' => array( $language ), 'labels' => array( $label ) );
			$value = function_exists( 'maybe_serialize' ) ? maybe_serialize( $value ) : serialize( $value );
			if ( true !== self::write_tvshow_meta( $series_id, '_language', $value, $options ) ) {
				return self::err( 'series_tv_adapter_language_failed', __( 'Failed to save Series language.', 'movies-wp' ) );
			}
		}

		$countries = isset( $data['origin_country'] ) && is_array( $data['origin_country'] ) ? $data['origin_country'] : array();
		if ( array() !== $countries ) {
			if ( isset( $options['save_country'] ) && is_callable( $options['save_country'] ) ) {
				$saved = call_user_func( $options['save_country'], $series_id, $countries );
			} elseif ( function_exists( 'streamit_save_country_meta' ) ) {
				streamit_save_country_meta( 'tvshow', $series_id, $countries );
				$saved = true;
			} else {
				$saved = new WP_Error( 'series_tv_adapter_country_api_missing', __( 'Streamit country persistence API is unavailable.', 'movies-wp' ) );
			}
			if ( is_wp_error( $saved ) || false === $saved ) {
				return self::err( 'series_tv_adapter_country_failed', __( 'Failed to save Series countries.', 'movies-wp' ) );
			}
		}

		$genres = isset( $data['genres'] ) && is_array( $data['genres'] ) ? $data['genres'] : array();
		if ( array() !== $genres ) {
			if ( isset( $options['save_genres'] ) && is_callable( $options['save_genres'] ) ) {
				$saved = call_user_func( $options['save_genres'], $series_id, $genres, 'tvshow_genre' );
			} elseif ( function_exists( 'streamit_child_save_tmdb_genres' ) ) {
				$saved = streamit_child_save_tmdb_genres( $series_id, $genres, 'tvshow_genre' );
			} else {
				$saved = self::save_genres_with_streamit_terms( $series_id, $genres );
			}
			// The child helper returns false when manual genres already exist.
			if ( is_wp_error( $saved ) ) {
				return self::err( $saved->get_error_code(), $saved->get_error_message() );
			}
		}

		return true;
	}

	private static function persist_series_people( $series_id, array $data, array $options ) {
		$people_count = 0;
		$people_snap  = Movies_WP_Series_Import_Profiler::phase_start( 'people' );
		foreach ( array( 'cast', 'crew' ) as $type ) {
			$credits = isset( $data[ $type ] ) && is_array( $data[ $type ] ) ? $data[ $type ] : array();
			if ( array() === $credits ) {
				continue;
			}
			$people_count += count( $credits );
			$saved         = self::persist_people( $series_id, $type, $credits, $options );
			if ( is_wp_error( $saved ) ) {
				Movies_WP_Series_Import_Profiler::phase_end( 'people', $people_snap, $people_count, 'failed' );
				return self::err( $saved->get_error_code(), $saved->get_error_message() );
			}
		}
		Movies_WP_Series_Import_Profiler::phase_end( 'people', $people_snap, $people_count, 'cast_crew' );
		return true;
	}

	private static function persist_people( $series_id, $type, array $credits, array $options ) {
		$meta_key         = 'cast' === $type ? '_cast' : '_crew';
		$relation_key     = 'cast' === $type ? '_tvshow_cast' : '_tvshow_crew';
		$existing         = self::load_tvshow_meta( $series_id, $meta_key, $options );
		$relationships    = is_array( $existing ) ? array_values( $existing ) : array();
		$relationship_map = array();
		foreach ( $relationships as $index => $relationship ) {
			if ( is_array( $relationship ) && absint( $relationship['id'] ?? 0 ) > 0 ) {
				$relationship_map[ absint( $relationship['id'] ) ] = (int) $index;
			}
		}

		foreach ( $credits as $position => $credit ) {
			if ( ! is_array( $credit ) || '' === trim( (string) ( $credit['name'] ?? '' ) ) ) {
				continue;
			}
			$person_started = Movies_WP_Series_Import_Profiler::begin( 'person_resolve' );
			$person_id      = self::resolve_person( $credit, $options );
			Movies_WP_Series_Import_Profiler::end( 'person_resolve', $person_started, (string) ( $credit['name'] ?? '' ) );
			Movies_WP_Series_Import_Profiler::progress( 'PERSON ' . $type . ' ' . (string) ( $credit['name'] ?? '' ) );
			if ( is_wp_error( $person_id ) ) {
				return $person_id;
			}
			if ( $person_id <= 0 ) {
				continue;
			}

			$relationship = array( 'id' => $person_id );
			if ( 'cast' === $type ) {
				$relationship['character'] = self::clean_text( $credit['character'] ?? '' );
				$relationship['position']  = (string) ( isset( $credit['order'] ) ? (int) $credit['order'] : $position );
			} else {
				$relationship['job'] = self::clean_text( $credit['job'] ?? '' );
			}

			if ( isset( $relationship_map[ $person_id ] ) ) {
				$index                   = $relationship_map[ $person_id ];
				$relationships[ $index ] = array_merge( $relationships[ $index ], $relationship );
			} else {
				$relationship_map[ $person_id ] = count( $relationships );
				$relationships[]                = $relationship;
			}

			$related = isset( $options['add_person_relation'] ) && is_callable( $options['add_person_relation'] )
				? call_user_func( $options['add_person_relation'], $person_id, $relation_key, (int) $series_id )
				: ( function_exists( 'streamit_add_person_relation' ) ? streamit_add_person_relation( $person_id, $relation_key, (int) $series_id ) : new WP_Error( 'missing_api', 'streamit_add_person_relation() is not available.' ) );
			if (
				is_wp_error( $related )
				|| (
					false === $related
					&& ! in_array( (int) $series_id, self::normalize_ids( (array) self::load_person_meta( $person_id, $relation_key, $options ) ), true )
				)
			) {
				return is_wp_error( $related )
					? $related
					: new WP_Error( 'series_tv_adapter_person_relation_failed', __( 'Failed to save a Streamit person relationship.', 'movies-wp' ) );
			}
		}

		if ( true !== self::write_tvshow_meta( $series_id, $meta_key, $relationships, $options ) ) {
			return new WP_Error( 'series_tv_adapter_people_meta_failed', __( 'Failed to save Streamit cast or crew metadata.', 'movies-wp' ) );
		}
		return true;
	}

	private static function resolve_person( array $credit, array $options ) {
		$tmdb_id = absint( $credit['tmdb_id'] ?? 0 );
		$name    = self::clean_text( $credit['name'] ?? '' );
		$id      = 0;
		$matched_by_name = false;

		if ( $tmdb_id > 0 ) {
			if ( isset( $options['find_person_by_tmdb'] ) && is_callable( $options['find_person_by_tmdb'] ) ) {
				$id = absint( call_user_func( $options['find_person_by_tmdb'], $tmdb_id ) );
			} else {
				global $wpdb;
				$id = absint(
					$wpdb->get_var(
						$wpdb->prepare(
							"SELECT streamit_person_id FROM {$wpdb->streamit_personmeta} WHERE meta_key = '_tmdb_id' AND meta_value = %s LIMIT 1",
							(string) $tmdb_id
						)
					)
				);
			}
		}

		if ( $id <= 0 ) {
			if ( isset( $options['find_person_by_name'] ) && is_callable( $options['find_person_by_name'] ) ) {
				$id = absint( call_user_func( $options['find_person_by_name'], $name ) );
			} else {
				global $wpdb;
				$id = absint(
					$wpdb->get_var(
						$wpdb->prepare(
							"SELECT ID FROM {$wpdb->streamit_person} WHERE post_title = %s ORDER BY ID DESC LIMIT 1",
							$name
						)
					)
				);
			}
			$matched_by_name = $id > 0;
		}

		if ( $matched_by_name && $tmdb_id > 0 ) {
			$existing_tmdb_id = absint( self::load_person_meta( $id, '_tmdb_id', $options ) );
			if ( $existing_tmdb_id > 0 && $existing_tmdb_id !== $tmdb_id ) {
				$id = 0;
			}
		}

		if ( $id <= 0 ) {
			$row     = self::new_row( $name, '', 'person', $options );
			$created = isset( $options['create_person'] ) && is_callable( $options['create_person'] )
				? call_user_func( $options['create_person'], $row )
				: ( function_exists( 'streamit_add_person' ) ? streamit_add_person( $row ) : new WP_Error( 'missing_api', 'streamit_add_person() is not available.' ) );
			if ( is_wp_error( $created ) || absint( $created ) <= 0 ) {
				return is_wp_error( $created )
					? $created
					: new WP_Error( 'series_tv_adapter_person_create_failed', __( 'Failed to create a Streamit person.', 'movies-wp' ) );
			}
			$id = absint( $created );
		}

		if ( $tmdb_id > 0 ) {
			if ( true !== self::write_person_meta( $id, '_tmdb_id', (string) $tmdb_id, $options ) ) {
				return new WP_Error( 'series_tv_adapter_person_meta_failed', __( 'Failed to save Streamit person metadata.', 'movies-wp' ) );
			}
		}
		return $id;
	}

	private static function save_genres_with_streamit_terms( $series_id, array $genres ) {
		if ( ! function_exists( 'streamit_get_term' ) || ! function_exists( 'streamit_add_term' ) || ! function_exists( 'streamit_insert_term_relationships' ) ) {
			return new WP_Error( 'series_tv_adapter_genre_api_missing', __( 'Streamit genre persistence APIs are unavailable.', 'movies-wp' ) );
		}
		if ( function_exists( 'streamit_get_term_relationships' ) ) {
			$existing = streamit_get_term_relationships( $series_id, 'tvshow_genre' );
			if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
				return true;
			}
		}
		$ids = array();
		foreach ( $genres as $genre ) {
			$name = trim( (string) ( $genre['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$slug = function_exists( 'sanitize_title' ) ? sanitize_title( $name ) : strtolower( str_replace( ' ', '-', $name ) );
			$term = streamit_get_term( $slug, 'tvshow_genre' );
			if ( ! is_wp_error( $term ) && method_exists( $term, 'get_term_id' ) ) {
				$ids[] = (int) $term->get_term_id();
			} else {
				$added = streamit_add_term( array( 'term_name' => $name, 'term_slug' => $slug, 'taxonomy' => 'tvshow_genre' ) );
				if ( ! is_wp_error( $added ) ) {
					$ids[] = (int) $added;
				}
			}
		}
		return array() === $ids ? true : streamit_insert_term_relationships( $series_id, array_values( array_unique( $ids ) ), 'tvshow_genre' );
	}

	private static function persist_series_images( $series_id, array $images, array $options ) {
		$results = array();
		foreach ( array( 'poster' => '_portrait_thumbmail', 'backdrop' => 'thumbnail_id' ) as $role => $target ) {
			$image = isset( $images[ $role ] ) && is_array( $images[ $role ] ) ? $images[ $role ] : array( 'action' => 'skip_missing' );
			$results[] = self::persist_image( 'tvshow', $series_id, $role, $target, $image, $options );
		}
		return $results;
	}

	private static function persist_episode( $series_id, array $plan, array $options ) {
		$action         = (string) $plan['action'];
		$season_string  = self::season_number_string( $plan['season_number'] ?? null );
		$season_number  = (int) $season_string;
		$episode_number = (int) $plan['episode_number'];
		$title          = sprintf( 'S%02dE%02d - %s', $season_number, $episode_number, (string) $plan['name'] );
		$episode_t0     = microtime( true );
		$episode_q0     = Movies_WP_Series_Import_Profiler::queries();
		$episode_http0  = Movies_WP_Series_Import_Profiler::http_count();
		$episode_httpms0 = Movies_WP_Series_Import_Profiler::http_total_ms();
		$insert_ms      = 0;
		$meta_ms        = 0;
		$still_ms       = 0;

		$mark = static function () use ( $season_number, $episode_number, $episode_t0, $episode_q0, $episode_http0, $episode_httpms0, &$insert_ms, &$meta_ms, &$still_ms, $action ) {
			Movies_WP_Series_Import_Profiler::close_episode_meta_window();
			$report = Movies_WP_Series_Import_Profiler::last_episode_report();
			Movies_WP_Series_Import_Profiler::mark_episode_created(
				$season_number,
				$episode_number,
				array(
					'elapsed_ms'              => (int) round( ( microtime( true ) - $episode_t0 ) * 1000 ),
					'insert_ms'               => $insert_ms,
					'meta_ms'                 => $meta_ms,
					'still_ms'                => $still_ms,
					'dq'                      => Movies_WP_Series_Import_Profiler::queries() - $episode_q0,
					'person_lookups'          => 0,
					'action'                  => $action,
					'streamit_add_episode_ms' => (int) ( $report['add_episode_ms'] ?? 0 ),
					'insert_span_ms'          => (int) ( $report['create_hook_span_ms'] ?? $insert_ms ),
					'add_metadata_ms'         => (int) ( $report['add_metadata_ms'] ?? 0 ),
					'child_invalidation_ms'   => (int) ( $report['child_invalidate_ms'] ?? 0 ),
					'http_ms'                 => Movies_WP_Series_Import_Profiler::http_total_ms() - $episode_httpms0,
					'http_count'              => Movies_WP_Series_Import_Profiler::http_count() - $episode_http0,
				)
			);
			Movies_WP_Series_Import_Profiler::reset_episode_observers();
		};

		$insert_snap       = Movies_WP_Series_Import_Profiler::phase_start( 'episode_insert' );
		$retry_created_id = absint( $plan['retry_created_episode_id'] ?? ( $options['retry_created_episode_id'] ?? 0 ) );
		if ( 'create' === $action && $retry_created_id > 0 ) {
			$action                     = 'update';
			$plan['action']             = 'update';
			$plan['existing_episode_id'] = $retry_created_id;
			$plan['match_by']           = 'retry_created_id';
		}
		if ( 'create' === $action ) {
			$live_id = self::find_live_episode_id( $series_id, $plan, $options );
			if ( is_wp_error( $live_id ) ) {
				$insert_ms = (int) round( ( microtime( true ) - $insert_snap['t'] ) * 1000 );
				Movies_WP_Series_Import_Profiler::phase_end( 'episode_insert', $insert_snap, 0, 'identity_conflict' );
				$mark();
				return self::episode_failure( $plan, self::err( $live_id->get_error_code(), $live_id->get_error_message() ) );
			}
			if ( $live_id > 0 ) {
				$action                     = 'update';
				$plan['action']             = 'update';
				$plan['existing_episode_id'] = $live_id;
				$plan['match_by']           = $plan['match_by'] ?? 'live_identity';
			}
		}
		if ( 'create' === $action ) {
			$row                = self::new_row( $title, (string) $plan['overview'], 'episode', $options );
			$row['menu_order']  = $episode_number;
			$row['post_parent'] = (int) $series_id;
			$created = isset( $options['create_episode'] ) && is_callable( $options['create_episode'] )
				? call_user_func( $options['create_episode'], $row )
				: ( function_exists( 'streamit_add_episode' ) ? streamit_add_episode( $row ) : new WP_Error( 'missing_api', 'streamit_add_episode() is not available.' ) );
			$insert_ms = (int) round( ( microtime( true ) - $insert_snap['t'] ) * 1000 );
			if ( is_wp_error( $created ) || absint( $created ) <= 0 ) {
				Movies_WP_Series_Import_Profiler::phase_end( 'episode_insert', $insert_snap, 0, 'failed' );
				$mark();
				return self::episode_failure( $plan, self::external_error( $created, 'series_tv_adapter_episode_create_failed', __( 'Streamit episode creation failed.', 'movies-wp' ) ) );
			}
			$episode_id = absint( $created );
			if ( isset( $options['remember_created_episode'] ) && is_callable( $options['remember_created_episode'] ) ) {
				$remembered = call_user_func( $options['remember_created_episode'], $episode_id, $plan );
				if ( true !== $remembered ) {
					$insert_ms = (int) round( ( microtime( true ) - $insert_snap['t'] ) * 1000 );
					Movies_WP_Series_Import_Profiler::phase_end( 'episode_insert', $insert_snap, 0, 'remember_failed' );
					$mark();
					return self::episode_failure(
						$plan,
						self::external_error( $remembered, 'series_tv_adapter_episode_remember_failed', __( 'Failed to persist the created episode id for retry.', 'movies-wp' ) ),
						$episode_id
					);
				}
			}
			$identity     = self::episode_identity_meta( $series_id, $plan, $season_string, $episode_number, true );
			$identity_err = self::persist_episode_meta_keys( $episode_id, $identity, $plan, $options );
			if ( is_array( $identity_err ) ) {
				$insert_ms = (int) round( ( microtime( true ) - $insert_snap['t'] ) * 1000 );
				Movies_WP_Series_Import_Profiler::phase_end( 'episode_insert', $insert_snap, 0, 'identity_meta_failed' );
				$mark();
				return $identity_err;
			}
		} else {
			$episode_id = absint( $plan['existing_episode_id'] ?? 0 );
			$episode    = isset( $options['get_episode'] ) && is_callable( $options['get_episode'] )
				? call_user_func( $options['get_episode'], $episode_id )
				: ( function_exists( 'streamit_get_episode' ) ? streamit_get_episode( $episode_id ) : null );
			$row        = self::row_from_object( $episode, $episode_id, 'episode' );
			if ( is_wp_error( $row ) ) {
				$insert_ms = (int) round( ( microtime( true ) - $insert_snap['t'] ) * 1000 );
				Movies_WP_Series_Import_Profiler::phase_end( 'episode_insert', $insert_snap, 0, 'failed' );
				$mark();
				return self::episode_failure( $plan, self::err( $row->get_error_code(), $row->get_error_message() ) );
			}

			$existing_tvshow_id = self::load_episode_meta( $episode_id, 'tvshow_id', $options );
			$allow_parent       = 'retry_created_id' === ( $plan['match_by'] ?? '' );
			if ( ! self::episode_belongs_to_series( $episode_id, $series_id, $row, $existing_tvshow_id, $allow_parent ) ) {
				$owner_id  = is_wp_error( $existing_tvshow_id ) ? absint( $row['post_parent'] ?? 0 ) : absint( $existing_tvshow_id );
				$insert_ms = (int) round( ( microtime( true ) - $insert_snap['t'] ) * 1000 );
				Movies_WP_Series_Import_Profiler::phase_end( 'episode_insert', $insert_snap, 0, 'ownership_conflict' );
				$mark();
				return self::episode_failure(
					$plan,
					self::err(
						'series_tv_adapter_episode_ownership_conflict',
						sprintf(
							__( 'Episode %1$d belongs to TV show %2$d and cannot be updated for TV show %3$d.', 'movies-wp' ),
							$episode_id,
							$owner_id,
							(int) $series_id
						)
					),
					$episode_id
				);
			}

			$row['post_title']        = $title;
			$row['post_content']      = (string) $plan['overview'];
			$row['menu_order']        = $episode_number;
			$row['post_modified']     = self::now( false, $options );
			$row['post_modified_gmt'] = self::now( true, $options );
			$updated = isset( $options['update_episode_row'] ) && is_callable( $options['update_episode_row'] )
				? call_user_func( $options['update_episode_row'], $episode_id, $row )
				: ( class_exists( 'Streamit_Episode' ) ? ( new Streamit_Episode() )->update( $episode_id, $row ) : new WP_Error( 'missing_api', 'Streamit_Episode is not available.' ) );
			$insert_ms = (int) round( ( microtime( true ) - $insert_snap['t'] ) * 1000 );
			if ( is_wp_error( $updated ) || false === $updated || null === $updated ) {
				Movies_WP_Series_Import_Profiler::phase_end( 'episode_insert', $insert_snap, 0, 'failed' );
				$mark();
				return self::episode_failure( $plan, self::external_error( $updated, 'series_tv_adapter_episode_update_failed', __( 'Streamit episode update failed.', 'movies-wp' ) ), $episode_id );
			}
		}
		$insert_ms = (int) round( ( microtime( true ) - $insert_snap['t'] ) * 1000 );
		Movies_WP_Series_Import_Profiler::phase_end( 'episode_insert', $insert_snap, 1, $action );

		$meta = array();
		if ( 'create' !== $action ) {
			$meta = self::episode_identity_meta( $series_id, $plan, $season_string, $episode_number, false );
			if ( ! isset( $meta['_tmdb_id'] ) ) {
				$meta['_tmdb_id'] = (string) absint( $plan['tmdb_id'] ?? 0 );
			}
		} elseif ( absint( $plan['tmdb_id'] ?? 0 ) <= 0 ) {
			$meta['_tmdb_id'] = '0';
		}
		$air_date = trim( (string) ( $plan['air_date'] ?? '' ) );
		if ( '' !== $air_date ) {
			$meta['_episode_release_date'] = $air_date;
		}
		if ( is_numeric( $plan['runtime'] ?? null ) && (int) $plan['runtime'] > 0 ) {
			$runtime                   = (int) $plan['runtime'];
			$meta['_episode_run_time'] = sprintf( '%d:%02d', intdiv( $runtime, 60 ), $runtime % 60 );
		}

		$meta_snap = Movies_WP_Series_Import_Profiler::phase_start( 'episode_meta' );
		$meta_err  = self::persist_episode_meta_keys( $episode_id, $meta, $plan, $options );
		if ( is_array( $meta_err ) ) {
			$meta_ms = (int) round( ( microtime( true ) - $meta_snap['t'] ) * 1000 );
			Movies_WP_Series_Import_Profiler::phase_end( 'episode_meta', $meta_snap, 0, 'failed' );
			$mark();
			return $meta_err;
		}
		$meta_ms = (int) round( ( microtime( true ) - $meta_snap['t'] ) * 1000 );
		Movies_WP_Series_Import_Profiler::phase_end( 'episode_meta', $meta_snap, count( $meta ), 'episode_meta' );

		$still_snap = Movies_WP_Series_Import_Profiler::phase_start( 'episode_still' );
		$image      = self::persist_image( 'episode', $episode_id, 'still', 'thumbnail_id', $plan['image'], $options );
		$still_ms   = (int) round( ( microtime( true ) - $still_snap['t'] ) * 1000 );
		Movies_WP_Series_Import_Profiler::phase_end( 'episode_still', $still_snap, 1, (string) ( $plan['image']['action'] ?? '' ) );
		Movies_WP_Series_Import_Profiler::mark_still( $season_string, $episode_number, (string) ( $plan['image']['action'] ?? '' ) );
		$mark();
		if ( empty( $image['ok'] ) ) {
			return self::episode_failure( $plan, $image['error'], $episode_id, $image );
		}

		return array(
			'ok'             => true,
			'action'         => $action,
			'episode_id'     => $episode_id,
			'season_number'  => $season_string,
			'episode_number' => $episode_number,
			'match_by'       => $plan['match_by'] ?? null,
			'image'          => $image,
		);
	}

	private static function upsert_season( $series_id, array $seasons, array $plan, array $episode_ids, array $options ) {
		$number = self::season_number_string( $plan['season_number'] ?? null );
		$slot   = null;
		foreach ( $seasons as $index => $season ) {
			if (
				is_array( $season )
				&& array_key_exists( 'season_number', $season )
				&& $number === self::season_number_string( $season['season_number'] )
			) {
				$slot = (int) $index;
				break;
			}
		}
		if (
			null === $slot
			&& 'linked_episode_meta' === ( $plan['identity_source'] ?? null )
			&& null !== ( $plan['existing_slot_index'] ?? null )
			&& isset( $seasons[ (int) $plan['existing_slot_index'] ] )
			&& is_array( $seasons[ (int) $plan['existing_slot_index'] ] )
			&& ! array_key_exists( 'season_number', $seasons[ (int) $plan['existing_slot_index'] ] )
		) {
			$slot = (int) $plan['existing_slot_index'];
		}

		$row               = null === $slot || ! is_array( $seasons[ $slot ] ?? null ) ? array() : $seasons[ $slot ];
		$existing_episodes = isset( $row['episodes'] ) && is_array( $row['episodes'] ) ? $row['episodes'] : array();
		$planned_existing  = isset( $plan['existing_episode_ids'] ) && is_array( $plan['existing_episode_ids'] )
			? $plan['existing_episode_ids']
			: array();
		$row['episodes'] = self::normalize_ids( array_merge( $existing_episodes, $planned_existing, $episode_ids ) );

		$name = trim( (string) ( $plan['name'] ?? '' ) );
		if ( '' !== $name || ! array_key_exists( 'name', $row ) ) {
			$row['name'] = $name;
		}
		$year = self::year( $plan['air_date'] ?? '' );
		if ( '' !== $year || ! array_key_exists( 'season_year', $row ) ) {
			$row['season_year'] = $year;
		}
		$description = (string) ( $plan['overview'] ?? '' );
		if ( '' !== trim( $description ) || ! array_key_exists( 'season_description', $row ) ) {
			$row['season_description'] = $description;
		}
		$row['sesion_upcoming_status']   = $row['sesion_upcoming_status'] ?? '';
		$row['season_upcoming_datetime'] = $row['season_upcoming_datetime'] ?? '';
		$row['season_number']            = $number;

		$season_snap = Movies_WP_Series_Import_Profiler::phase_start( 'season_image' );
		$image       = isset( $plan['image'] ) && is_array( $plan['image'] ) ? $plan['image'] : array( 'action' => 'skip_missing' );
		if ( 'set' === ( $image['action'] ?? '' ) ) {
			$download = self::download_image( $image, 'season_poster', $options );
			Movies_WP_Series_Import_Profiler::phase_end( 'season_image', $season_snap, 1, 'season_poster' );
			Movies_WP_Series_Import_Profiler::progress( 'IMAGE season_poster action=set' );
			if ( is_wp_error( $download ) ) {
				return array(
					'ok'            => false,
					'action'        => (string) $plan['action'],
					'season_number' => $number,
					'error'         => self::err( $download->get_error_code(), $download->get_error_message() ),
				);
			}
			$row['image_id'] = (int) $download;
		} else {
			Movies_WP_Series_Import_Profiler::phase_end( 'season_image', $season_snap, 0, (string) ( $image['action'] ?? 'skip_missing' ) );
			if ( ! array_key_exists( 'image_id', $row ) ) {
				$row['image_id'] = '';
			}
		}

		if ( null === $slot ) {
			$seasons[] = $row;
			$slot      = count( $seasons ) - 1;
		} else {
			$seasons[ $slot ] = $row;
		}

		return array(
			'ok'            => true,
			'action'        => (string) $plan['action'],
			'season_number' => $number,
			'slot_index'    => $slot,
			'episode_ids'   => $row['episodes'],
			'seasons'       => array_values( $seasons ),
			'series_id'     => (int) $series_id,
		);
	}

	private static function persist_image( $owner_type, $owner_id, $role, $target, array $image, array $options ) {
		$action = (string) ( $image['action'] ?? 'skip_missing' );
		if ( 'set' !== $action ) {
			return array( 'ok' => true, 'role' => $role, 'action' => $action, 'target' => $target, 'attachment_id' => null );
		}
		$download_started = Movies_WP_Series_Import_Profiler::begin( 'image_sideload.' . $role );
		$attachment       = self::download_image( $image, $role, $options );
		Movies_WP_Series_Import_Profiler::end( 'image_sideload.' . $role, $download_started, (string) ( $image['url'] ?? $image['path'] ?? $role ) );
		Movies_WP_Series_Import_Profiler::progress( 'IMAGE ' . $role . ' action=set' );
		if ( is_wp_error( $attachment ) ) {
			return array(
				'ok'     => false,
				'role'   => $role,
				'action' => $action,
				'target' => $target,
				'error'  => self::err( $attachment->get_error_code(), $attachment->get_error_message() ),
			);
		}
		$written = 'episode' === $owner_type
			? self::write_episode_meta( $owner_id, $target, (int) $attachment, $options )
			: self::write_tvshow_meta( $owner_id, $target, (int) $attachment, $options );
		if ( true !== $written ) {
			return array(
				'ok'     => false,
				'role'   => $role,
				'action' => $action,
				'target' => $target,
				'error'  => self::err( 'series_tv_adapter_image_meta_failed', __( 'Failed to attach a Series image.', 'movies-wp' ) ),
			);
		}
		return array( 'ok' => true, 'role' => $role, 'action' => $action, 'target' => $target, 'attachment_id' => (int) $attachment );
	}

	private static function download_image( array $image, $role, array $options ) {
		$url = trim( (string) ( $image['url'] ?? '' ) );
		if ( '' === $url && ! empty( $image['path'] ) && function_exists( 'streamit_get_tmdb_image_url' ) ) {
			$size = 'poster' === $role ? 'w780' : ( 'still' === $role ? 'w500' : 'original' );
			$url  = streamit_get_tmdb_image_url( (string) $image['path'], $size );
		}
		if ( '' === $url ) {
			return new WP_Error( 'series_tv_adapter_image_url_missing', __( 'A planned Series image has no source URL.', 'movies-wp' ) );
		}
		if ( isset( $options['download_image'] ) && is_callable( $options['download_image'] ) ) {
			return call_user_func( $options['download_image'], $url, $role );
		}

		global $wpdb;
		$source_url = function_exists( 'remove_query_arg' ) ? remove_query_arg( '_streamit_image_role', $url ) : $url;
		$escaped    = function_exists( 'esc_url_raw' ) ? esc_url_raw( $source_url ) : esc_url( $source_url );
		$found = isset( $options['find_attachment_by_source_url'] ) && is_callable( $options['find_attachment_by_source_url'] )
			? call_user_func( $options['find_attachment_by_source_url'], $escaped )
			: $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_streamit_tmdb_source_url' AND meta_value = %s LIMIT 1",
				$escaped
			)
		);
		if ( absint( $found ) > 0 ) {
			return absint( $found );
		}
		$path = self::image_sideload_name( $image, $source_url );

		if ( isset( $options['sideload_image'] ) && is_callable( $options['sideload_image'] ) ) {
			$id = call_user_func( $options['sideload_image'], $source_url, $path, $role );
		} else {
			if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! function_exists( 'media_handle_sideload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$tmp = download_url( $source_url );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			$file = array(
				'name'     => $path,
				'tmp_name' => $tmp,
			);
			$id = media_handle_sideload( $file, 0 );
			if ( is_wp_error( $id ) ) {
				@unlink( $tmp );
				return $id;
			}
		}
		if ( is_wp_error( $id ) || absint( $id ) <= 0 ) {
			return is_wp_error( $id ) ? $id : new WP_Error( 'series_tv_adapter_image_sideload_failed', __( 'Failed to create a Streamit image attachment.', 'movies-wp' ) );
		}
		if ( isset( $options['update_attachment_source_url'] ) && is_callable( $options['update_attachment_source_url'] ) ) {
			$recorded = call_user_func( $options['update_attachment_source_url'], absint( $id ), $escaped );
		} else {
			$recorded = update_post_meta( $id, '_streamit_tmdb_source_url', $escaped );
		}
		if ( false === $recorded ) {
			$current = isset( $options['get_attachment_source_url'] ) && is_callable( $options['get_attachment_source_url'] )
				? call_user_func( $options['get_attachment_source_url'], absint( $id ) )
				: ( function_exists( 'get_post_meta' ) ? get_post_meta( absint( $id ), '_streamit_tmdb_source_url', true ) : null );
			if ( $escaped !== $current ) {
				return new WP_Error( 'series_tv_adapter_image_source_meta_failed', __( 'Failed to record the TMDb source URL for a Streamit attachment.', 'movies-wp' ) );
			}
		}
		return absint( $id );
	}

	/**
	 * TMDb images are often proxied through admin-ajax.php; WordPress rejects a .php upload name.
	 */
	private static function image_sideload_name( array $image, $source_url ) {
		$candidates = array();
		if ( ! empty( $image['path'] ) ) {
			$candidates[] = wp_basename( (string) $image['path'] );
		}
		$query = array();
		parse_str( (string) parse_url( (string) $source_url, PHP_URL_QUERY ), $query );
		if ( ! empty( $query['path'] ) ) {
			$candidates[] = wp_basename( (string) $query['path'] );
		}
		$candidates[] = wp_basename( (string) parse_url( (string) $source_url, PHP_URL_PATH ) );
		foreach ( $candidates as $name ) {
			$name = function_exists( 'sanitize_file_name' ) ? sanitize_file_name( (string) $name ) : preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name );
			if ( '' === $name || preg_match( '/\.php[0-9]*$/i', $name ) ) {
				continue;
			}
			if ( preg_match( '/\.(jpe?g|png|gif|webp|bmp)$/i', $name ) ) {
				return $name;
			}
		}
		return 'tmdb-image.jpg';
	}

	private static function write_tvshow_meta( $id, $key, $value, array $options ) {
		$result = isset( $options['update_tvshow_meta'] ) && is_callable( $options['update_tvshow_meta'] )
			? call_user_func( $options['update_tvshow_meta'], (int) $id, $key, $value )
			: ( function_exists( 'streamit_update_tvshow_meta' ) ? streamit_update_tvshow_meta( (int) $id, $key, $value ) : new WP_Error( 'missing_api', 'streamit_update_tvshow_meta() is not available.' ) );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		return false !== $result || self::values_equal( self::load_tvshow_meta( $id, $key, $options ), $value );
	}

	/**
	 * Identity keys used to find a live episode after a crash: tvshow_id, season, episode, TMDb id.
	 *
	 * @param array<string, mixed> $plan
	 * @return array<string, string>
	 */
	private static function episode_identity_meta( $series_id, array $plan, $season_string, $episode_number, $include_tvshow ) {
		$meta = array();
		if ( $include_tvshow ) {
			$meta['tvshow_id'] = (string) (int) $series_id;
		}
		$meta['_season_number']  = (string) $season_string;
		$meta['_episode_number'] = sprintf( 'E%02d', (int) $episode_number );
		$tmdb                    = absint( $plan['tmdb_id'] ?? 0 );
		if ( $tmdb > 0 ) {
			$meta['_tmdb_id'] = (string) $tmdb;
		}
		return $meta;
	}

	/**
	 * Ownership for updates: tvshow_id must match. A job-scoped retry of an ID we just
	 * created may use post_parent when tvshow_id has not been written yet.
	 *
	 * @param array<string, mixed> $row
	 * @param mixed                $existing_tvshow_id
	 */
	private static function episode_belongs_to_series( $episode_id, $series_id, array $row, $existing_tvshow_id, $allow_parent_if_meta_empty ) {
		unset( $episode_id );
		$series_id = (int) $series_id;
		if ( ! is_wp_error( $existing_tvshow_id ) && absint( $existing_tvshow_id ) === $series_id ) {
			return true;
		}
		if ( ! is_wp_error( $existing_tvshow_id ) && absint( $existing_tvshow_id ) > 0 ) {
			return false;
		}
		if ( $allow_parent_if_meta_empty && self::empty_meta_value( is_wp_error( $existing_tvshow_id ) ? null : $existing_tvshow_id ) ) {
			return absint( $row['post_parent'] ?? 0 ) === $series_id;
		}
		return false;
	}

	private static function persist_episode_meta_keys( $episode_id, array $meta, array $plan, array $options ) {
		foreach ( $meta as $key => $value ) {
			if ( true !== self::write_episode_meta( $episode_id, $key, $value, $options ) ) {
				return self::episode_failure(
					$plan,
					self::err( 'series_tv_adapter_episode_meta_failed', sprintf( __( 'Failed to save episode metadata %s.', 'movies-wp' ), $key ) ),
					$episode_id
				);
			}
		}
		return null;
	}

	private static function write_episode_meta( $id, $key, $value, array $options ) {
		$result = isset( $options['update_episode_meta'] ) && is_callable( $options['update_episode_meta'] )
			? call_user_func( $options['update_episode_meta'], (int) $id, $key, $value )
			: ( function_exists( 'streamit_update_episode_meta' ) ? streamit_update_episode_meta( (int) $id, $key, $value ) : new WP_Error( 'missing_api', 'streamit_update_episode_meta() is not available.' ) );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		return false !== $result || self::values_equal( self::load_episode_meta( $id, $key, $options ), $value );
	}

	private static function load_tvshow_meta( $id, $key, array $options ) {
		if ( isset( $options['get_tvshow_meta'] ) && is_callable( $options['get_tvshow_meta'] ) ) {
			return call_user_func( $options['get_tvshow_meta'], (int) $id, $key );
		}
		return function_exists( 'streamit_get_tvshow_meta' ) ? streamit_get_tvshow_meta( (int) $id, $key, true ) : null;
	}

	private static function load_episode_meta( $id, $key, array $options ) {
		if ( isset( $options['get_episode_meta'] ) && is_callable( $options['get_episode_meta'] ) ) {
			return call_user_func( $options['get_episode_meta'], (int) $id, $key );
		}
		return function_exists( 'streamit_get_episode_meta' ) ? streamit_get_episode_meta( (int) $id, $key, true ) : null;
	}

	private static function write_person_meta( $id, $key, $value, array $options ) {
		$result = isset( $options['update_person_meta'] ) && is_callable( $options['update_person_meta'] )
			? call_user_func( $options['update_person_meta'], (int) $id, $key, $value )
			: ( function_exists( 'streamit_update_person_meta' ) ? streamit_update_person_meta( (int) $id, $key, $value ) : new WP_Error( 'missing_api', 'streamit_update_person_meta() is not available.' ) );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		return false !== $result || self::values_equal( self::load_person_meta( $id, $key, $options ), $value );
	}

	private static function load_person_meta( $id, $key, array $options ) {
		if ( isset( $options['get_person_meta'] ) && is_callable( $options['get_person_meta'] ) ) {
			return call_user_func( $options['get_person_meta'], (int) $id, $key );
		}
		return function_exists( 'streamit_get_person_meta' ) ? streamit_get_person_meta( (int) $id, $key, true ) : null;
	}

	private static function row_from_object( $object, $id, $type ) {
		if ( ! is_object( $object ) ) {
			return new WP_Error( 'series_tv_adapter_object_missing', __( 'A Streamit TV object could not be loaded for update.', 'movies-wp' ) );
		}
		$getters = array(
			'post_author' => 'get_post_author', 'post_date' => 'get_post_date', 'post_date_gmt' => 'get_post_date_gmt',
			'post_content' => 'get_post_content', 'post_title' => 'get_post_title', 'post_excerpt' => 'get_post_excerpt',
			'post_status' => 'get_post_status', 'comment_status' => 'get_comment_status', 'ping_status' => 'get_ping_status',
			'post_password' => 'get_post_password', 'post_name' => 'get_post_name', 'to_ping' => 'get_to_ping',
			'pinged' => 'get_pinged', 'post_modified' => 'get_post_modified', 'post_modified_gmt' => 'get_post_modified_gmt',
			'post_content_filtered' => 'get_post_content_filtered', 'post_parent' => 'get_post_parent', 'guid' => 'get_guid',
			'menu_order' => 'get_menu_order', 'post_type' => 'get_post_type', 'post_mime_type' => 'get_post_mime_type',
			'comment_count' => 'get_comment_count',
		);
		$row = array( 'ID' => (int) $id );
		foreach ( $getters as $field => $getter ) {
			if ( ! method_exists( $object, $getter ) ) {
				return new WP_Error(
					'series_tv_adapter_incomplete_object',
					sprintf( __( 'The Streamit %1$s object is missing %2$s.', 'movies-wp' ), $type, $getter )
				);
			}
			$row[ $field ] = $object->{$getter}();
		}
		return $row;
	}

	private static function new_row( $title, $content, $type, array $options ) {
		return array(
			'post_author' => isset( $options['current_user_id'] ) ? (int) $options['current_user_id'] : ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 ),
			'post_date' => self::now( false, $options ), 'post_date_gmt' => self::now( true, $options ),
			'post_content' => $content, 'post_title' => $title, 'post_excerpt' => '', 'post_status' => 'publish',
			'comment_status' => 'open', 'ping_status' => 'open', 'post_password' => '',
			'post_name' => function_exists( 'sanitize_title' ) ? sanitize_title( $title ) : strtolower( str_replace( ' ', '-', $title ) ),
			'to_ping' => '', 'pinged' => '', 'post_modified' => self::now( false, $options ),
			'post_modified_gmt' => self::now( true, $options ), 'post_content_filtered' => '', 'post_parent' => 0,
			'guid' => '', 'menu_order' => 0, 'post_type' => $type, 'post_mime_type' => '', 'comment_count' => 0,
		);
	}

	private static function episode_failure( array $plan, array $error, $id = null, array $image = array() ) {
		return array(
			'ok'             => false,
			'action'         => (string) $plan['action'],
			'episode_id'     => null === $id ? null : (int) $id,
			'season_number'  => self::season_number_string( $plan['season_number'] ?? null ),
			'episode_number' => (int) $plan['episode_number'],
			'image'          => $image,
			'error'          => $error,
		);
	}

	private static function result( $ok, $series_id, $action, array $series, array $seasons, array $episodes, array $images, array $errors ) {
		return array(
			'ok'              => (bool) $ok,
			'type'            => 'series',
			'series_id'       => null === $series_id ? null : (int) $series_id,
			'identity_action' => $action,
			'series'          => $series,
			'seasons'         => array_values( $seasons ),
			'episodes'        => array_values( $episodes ),
			'images'          => array_values( $images ),
			'errors'          => array_values( $errors ),
			'partial'         => ! $ok && null !== $series_id,
		);
	}

	private static function external_error( $value, $code, $fallback ) {
		return is_wp_error( $value )
			? self::err( $value->get_error_code(), $value->get_error_message() )
			: self::err( $code, $fallback );
	}

	private static function err( $code, $message ) {
		return array( 'code' => (string) $code, 'message' => (string) $message );
	}

	private static function normalize_ids( array $ids ) {
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private static function values_equal( $left, $right ) {
		$serialize = function_exists( 'maybe_serialize' ) ? 'maybe_serialize' : 'serialize';
		return call_user_func( $serialize, $left ) === call_user_func( $serialize, $right );
	}

	private static function empty_meta_value( $value ) {
		return null === $value || false === $value || '' === $value;
	}

	private static function now( $gmt, array $options ) {
		$key = $gmt ? 'now_gmt' : 'now_local';
		if ( isset( $options[ $key ] ) && is_string( $options[ $key ] ) ) {
			return $options[ $key ];
		}
		return function_exists( 'current_time' ) ? current_time( 'mysql', $gmt ? 1 : 0 ) : gmdate( 'Y-m-d H:i:s' );
	}

	private static function rating( $value ) {
		return is_numeric( $value ) ? (string) $value : '';
	}

	private static function year( $date ) {
		return preg_match( '/^(\d{4})-/', (string) $date, $matches ) ? $matches[1] : '';
	}

	/**
	 * Live episode identity: tvshow_id + TMDb episode ID, then season + episode number.
	 * Uses the same Streamit listing primitive as the import plan (`streamit_get_episodes` / find_episodes).
	 *
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return int|WP_Error
	 */
	public static function find_live_episode_id( $series_id, array $plan, array $options = array() ) {
		$series_id = (int) $series_id;
		if ( $series_id <= 0 ) {
			return 0;
		}
		$rows = null;
		if ( isset( $options['find_episodes'] ) && is_callable( $options['find_episodes'] ) ) {
			$rows = call_user_func( $options['find_episodes'], $series_id );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			if ( is_array( $rows ) && isset( $rows['episodes'] ) && is_array( $rows['episodes'] ) ) {
				$rows = $rows['episodes'];
			}
		} elseif ( function_exists( 'streamit_get_episodes' ) ) {
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
			$rows = array();
			if ( is_object( $result ) && isset( $result->results ) && is_array( $result->results ) ) {
				foreach ( $result->results as $episode ) {
					if ( ! is_object( $episode ) || ! method_exists( $episode, 'get_id' ) ) {
						continue;
					}
					$id = absint( $episode->get_id() );
					if ( $id <= 0 ) {
						continue;
					}
					$tmdb = method_exists( $episode, 'get_meta' ) ? $episode->get_meta( '_tmdb_id' ) : 0;
					$sn   = method_exists( $episode, 'get_meta' ) ? $episode->get_meta( '_season_number' ) : null;
					$en   = method_exists( $episode, 'get_meta' ) ? $episode->get_meta( '_episode_number' ) : null;
					$show = method_exists( $episode, 'get_meta' ) ? $episode->get_meta( 'tvshow_id' ) : 0;
					$rows[] = array(
						'id'             => $id,
						'tvshow_id'      => absint( $show ),
						'tmdb_id'        => absint( $tmdb ),
						'season_number'  => $sn,
						'episode_number' => $en,
					);
				}
			}
		} else {
			return 0;
		}
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$want_tmdb = absint( $plan['tmdb_id'] ?? 0 );
		$want_se   = self::season_number_string( $plan['season_number'] ?? null );
		$want_ep   = self::canonical_episode_int( $plan['episode_number'] ?? null );
		$by_tmdb   = array();
		$by_se     = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = absint( $row['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$row_show = array_key_exists( 'tvshow_id', $row ) ? absint( $row['tvshow_id'] ) : 0;
			if ( $row_show <= 0 ) {
				$loaded = self::load_episode_meta( $id, 'tvshow_id', $options );
				$row_show = is_wp_error( $loaded ) ? 0 : absint( $loaded );
			}
			if ( $row_show !== $series_id ) {
				continue;
			}
			$tmdb = absint( $row['tmdb_id'] ?? 0 );
			$se   = self::season_number_string( $row['season_number'] ?? null );
			$ep   = self::canonical_episode_int( $row['episode_number'] ?? null );
			if ( $want_tmdb > 0 && $tmdb === $want_tmdb ) {
				$by_tmdb[] = $id;
			}
			if ( null !== $want_se && null !== $want_ep && $se === $want_se && $ep === $want_ep ) {
				$by_se[] = $id;
			}
		}
		$by_tmdb = array_values( array_unique( $by_tmdb ) );
		$by_se   = array_values( array_unique( $by_se ) );
		if ( count( $by_tmdb ) > 1 || count( $by_se ) > 1 ) {
			return new WP_Error(
				'series_tv_adapter_duplicate_episode_identity',
				__( 'Multiple Streamit episodes match the same Series episode identity.', 'movies-wp' )
			);
		}
		if ( 1 === count( $by_tmdb ) ) {
			if ( 1 === count( $by_se ) && $by_se[0] !== $by_tmdb[0] ) {
				return new WP_Error(
					'series_tv_adapter_episode_identity_conflict',
					__( 'TMDb and season/episode identity point to different Streamit episodes.', 'movies-wp' )
				);
			}
			return (int) $by_tmdb[0];
		}
		if ( 1 === count( $by_se ) ) {
			return (int) $by_se[0];
		}
		return 0;
	}

	private static function canonical_episode_int( $value ) {
		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		}
		if ( is_string( $value ) && preg_match( '/^(?:E)?0*([1-9]\d*)$/i', $value, $matches ) ) {
			return (int) $matches[1];
		}
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			return (int) $value;
		}
		return null;
	}

	private static function season_number_string( $value ) {
		if ( is_int( $value ) && $value >= 0 ) {
			return (string) $value;
		}
		if ( is_string( $value ) && preg_match( '/^\d+$/', $value ) ) {
			return (string) (int) $value;
		}
		return null;
	}

	private static function clean_text( $value ) {
		return function_exists( 'sanitize_text_field' )
			? sanitize_text_field( (string) $value )
			: trim( (string) $value );
	}
}

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

		$action = (string) $plan['identity']['action'];
		$series = self::persist_series( $plan, $options );
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

		$series_id = (int) $series['series_id'];
		$seasons   = self::load_tvshow_meta( $series_id, '_seasons', $options );
		if ( is_wp_error( $seasons ) || ! self::empty_meta_value( $seasons ) && ! is_array( $seasons ) ) {
			$error = self::err(
				'series_tv_adapter_seasons_unreadable',
				__( 'The complete existing Streamit season list could not be loaded safely.', 'movies-wp' )
			);
			return self::result( false, $series_id, $action, $series, array(), array(), array(), array( $error ) );
		}
		$seasons = is_array( $seasons ) ? array_values( $seasons ) : array();

		$image_results = self::persist_series_images( $series_id, $plan['images'], $options );
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
				$episode_result = self::persist_episode( $series_id, $episode_plan, $options );
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
			$season_result = self::upsert_season(
				$series_id,
				$seasons,
				$season_plan,
				$episode_ids_by_season[ self::season_number_string( $season_plan['season_number'] ?? null ) ],
				$options
			);
			$season_rows[] = $season_result;
			if ( empty( $season_result['ok'] ) ) {
				$errors[] = $season_result['error'];
			} else {
				$seasons = $season_result['seasons'];
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

	private static function persist_series( array $plan, array $options ) {
		$action = (string) $plan['identity']['action'];
		$data   = $plan['series'];

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
				return array(
					'ok'     => false,
					'action' => $action,
					'error'  => self::external_error( $updated, 'series_tv_adapter_update_failed', __( 'Streamit series update failed.', 'movies-wp' ) ),
				);
			}
		}

		$meta = array(
			'_tmdb_id'              => (string) absint( $data['tmdb_id'] ?? 0 ),
			'_tmdb_title'           => trim( (string) ( $data['tmdb_title'] ?? '' ) ),
			'_tmdb_original_title'  => trim( (string) ( $data['tmdb_original_title'] ?? '' ) ),
			'_imdb_id'              => trim( (string) ( $data['imdb_id'] ?? '' ) ),
			'name_custom_imdb_rating' => self::rating( $data['rating'] ?? null ),
		);
		foreach ( $meta as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			if ( true !== self::write_tvshow_meta( $series_id, $key, $value, $options ) ) {
				return array(
					'ok'        => false,
					'action'    => $action,
					'series_id' => $series_id,
					'error'     => self::err( 'series_tv_adapter_meta_failed', sprintf( __( 'Failed to save Series metadata %s.', 'movies-wp' ), $key ) ),
				);
			}
		}

		$enrichment = self::persist_series_enrichment( $series_id, $data, $options );
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

		foreach ( array( 'cast', 'crew' ) as $type ) {
			$credits = isset( $data[ $type ] ) && is_array( $data[ $type ] ) ? $data[ $type ] : array();
			if ( array() === $credits ) {
				continue;
			}
			$saved = self::persist_people( $series_id, $type, $credits, $options );
			if ( is_wp_error( $saved ) ) {
				return self::err( $saved->get_error_code(), $saved->get_error_message() );
			}
		}
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
			$person_id = self::resolve_person( $credit, $options );
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

		if ( 'create' === $action ) {
			$row                = self::new_row( $title, (string) $plan['overview'], 'episode', $options );
			$row['menu_order']  = $episode_number;
			$row['post_parent'] = (int) $series_id;
			$created = isset( $options['create_episode'] ) && is_callable( $options['create_episode'] )
				? call_user_func( $options['create_episode'], $row )
				: ( function_exists( 'streamit_add_episode' ) ? streamit_add_episode( $row ) : new WP_Error( 'missing_api', 'streamit_add_episode() is not available.' ) );
			if ( is_wp_error( $created ) || absint( $created ) <= 0 ) {
				return self::episode_failure( $plan, self::external_error( $created, 'series_tv_adapter_episode_create_failed', __( 'Streamit episode creation failed.', 'movies-wp' ) ) );
			}
			$episode_id = absint( $created );
		} else {
			$episode_id = absint( $plan['existing_episode_id'] ?? 0 );
			$episode    = isset( $options['get_episode'] ) && is_callable( $options['get_episode'] )
				? call_user_func( $options['get_episode'], $episode_id )
				: ( function_exists( 'streamit_get_episode' ) ? streamit_get_episode( $episode_id ) : null );
			$row        = self::row_from_object( $episode, $episode_id, 'episode' );
			if ( is_wp_error( $row ) ) {
				return self::episode_failure( $plan, self::err( $row->get_error_code(), $row->get_error_message() ) );
			}

			$existing_tvshow_id = self::load_episode_meta( $episode_id, 'tvshow_id', $options );
			if ( is_wp_error( $existing_tvshow_id ) || absint( $existing_tvshow_id ) !== (int) $series_id ) {
				$owner_id = is_wp_error( $existing_tvshow_id ) ? 0 : absint( $existing_tvshow_id );
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
			if ( is_wp_error( $updated ) || false === $updated || null === $updated ) {
				return self::episode_failure( $plan, self::external_error( $updated, 'series_tv_adapter_episode_update_failed', __( 'Streamit episode update failed.', 'movies-wp' ) ), $episode_id );
			}
		}

		$meta = array(
			'_season_number'  => $season_string,
			'_episode_number' => sprintf( 'E%02d', $episode_number ),
			'_tmdb_id'        => (string) absint( $plan['tmdb_id'] ?? 0 ),
		);
		if ( 'create' === $action ) {
			$meta = array( 'tvshow_id' => (string) $series_id ) + $meta;
		}
		$air_date = trim( (string) ( $plan['air_date'] ?? '' ) );
		if ( '' !== $air_date ) {
			$meta['_episode_release_date'] = $air_date;
		}
		if ( is_numeric( $plan['runtime'] ?? null ) && (int) $plan['runtime'] > 0 ) {
			$runtime                   = (int) $plan['runtime'];
			$meta['_episode_run_time'] = sprintf( '%d:%02d', intdiv( $runtime, 60 ), $runtime % 60 );
		}

		foreach ( $meta as $key => $value ) {
			if ( true !== self::write_episode_meta( $episode_id, $key, $value, $options ) ) {
				return self::episode_failure(
					$plan,
					self::err( 'series_tv_adapter_episode_meta_failed', sprintf( __( 'Failed to save episode metadata %s.', 'movies-wp' ), $key ) ),
					$episode_id
				);
			}
		}

		$image = self::persist_image( 'episode', $episode_id, 'still', 'thumbnail_id', $plan['image'], $options );
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

		$image = isset( $plan['image'] ) && is_array( $plan['image'] ) ? $plan['image'] : array( 'action' => 'skip_missing' );
		if ( 'set' === ( $image['action'] ?? '' ) ) {
			$download = self::download_image( $image, 'season_poster', $options );
			if ( is_wp_error( $download ) ) {
				return array(
					'ok'            => false,
					'action'        => (string) $plan['action'],
					'season_number' => $number,
					'error'         => self::err( $download->get_error_code(), $download->get_error_message() ),
				);
			}
			$row['image_id'] = (int) $download;
		} elseif ( ! array_key_exists( 'image_id', $row ) ) {
			$row['image_id'] = '';
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
		$attachment = self::download_image( $image, $role, $options );
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
		$path = wp_basename( (string) parse_url( $source_url, PHP_URL_PATH ) );

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
				'name'     => sanitize_file_name( '' !== $path ? $path : 'tmdb-image.jpg' ),
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

	private static function write_tvshow_meta( $id, $key, $value, array $options ) {
		$result = isset( $options['update_tvshow_meta'] ) && is_callable( $options['update_tvshow_meta'] )
			? call_user_func( $options['update_tvshow_meta'], (int) $id, $key, $value )
			: ( function_exists( 'streamit_update_tvshow_meta' ) ? streamit_update_tvshow_meta( (int) $id, $key, $value ) : new WP_Error( 'missing_api', 'streamit_update_tvshow_meta() is not available.' ) );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		return false !== $result || self::values_equal( self::load_tvshow_meta( $id, $key, $options ), $value );
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

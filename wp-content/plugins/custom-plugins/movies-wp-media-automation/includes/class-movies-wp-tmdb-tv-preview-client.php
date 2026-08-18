<?php
/**
 * Read-only TMDb TV fetch and normalization for Series Automation.
 *
 * Reuses the same Streamit API key and proxy host as Movie Automation.
 * This class never writes WordPress or Streamit data.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Tmdb_TV_Preview_Client {

	/**
	 * Fetch and normalize one TV series, all listed seasons, and their episodes.
	 *
	 * @param int   $tmdb_id TMDb TV series ID.
	 * @param array $options Test hooks. Supports `fetch_json`.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_series( $tmdb_id, array $options = array() ) {
		$tmdb_id = absint( $tmdb_id );
		if ( $tmdb_id <= 0 ) {
			return new WP_Error( 'series_preview_invalid_tmdb_response', __( 'TMDb Series ID is invalid.', 'movies-wp' ) );
		}

		$api_key = self::api_key( $options );
		if ( '' === $api_key ) {
			return new WP_Error( 'series_preview_tmdb_error', __( 'TMDb is not configured.', 'movies-wp' ) );
		}

		$host = self::host();
		$base = sprintf(
			'https://%s/3/tv/%d',
			$host,
			$tmdb_id
		);
		$query = '?api_key=' . rawurlencode( $api_key ) . '&language=' . rawurlencode( 'en-US' );

		$details = self::fetch_json( $base . $query, $options );
		if ( is_wp_error( $details ) ) {
			return $details;
		}

		$external = self::fetch_json( $base . '/external_ids?api_key=' . rawurlencode( $api_key ), $options );
		if ( is_wp_error( $external ) ) {
			return $external;
		}

		$credits = self::fetch_json( $base . '/credits' . $query, $options );
		if ( is_wp_error( $credits ) ) {
			return $credits;
		}

		$season_summaries = isset( $details['seasons'] ) && is_array( $details['seasons'] ) ? $details['seasons'] : array();
		$top_level_episodes = isset( $details['episodes'] ) && is_array( $details['episodes'] ) ? $details['episodes'] : array();
		if ( $season_summaries === array() && $top_level_episodes === array() && absint( $details['number_of_episodes'] ?? 0 ) > 0 ) {
			$season_summaries = array(
				array(
					'season_number'   => 1,
					'identity_source' => 'tmdb_unseasoned_episode_catalog',
					'unseasoned'      => true,
				),
			);
		}

		$seasons = array();
		foreach ( $season_summaries as $season_summary ) {
			if ( ! is_array( $season_summary ) || ! array_key_exists( 'season_number', $season_summary ) ) {
				continue;
			}

			$season_number = (int) $season_summary['season_number'];
			if ( $season_number < 0 ) {
				continue;
			}

			$season_body = self::fetch_json(
				$base . '/season/' . $season_number . $query,
				$options
			);
			if ( is_wp_error( $season_body ) ) {
				return new WP_Error(
					'series_preview_tmdb_season_error',
					sprintf(
						/* translators: %d: season number */
						__( 'Could not load Season %d from TMDb.', 'movies-wp' ),
						$season_number
					),
					array( 'season_number' => $season_number )
				);
			}

			// Keep the raw TMDb season payload. normalize_series() is the single normalization pass.
			$seasons[] = array_merge( $season_summary, is_array( $season_body ) ? $season_body : array() );
		}

		$details['external_ids'] = $external;
		$details['credits']      = $credits;
		$details['seasons']      = $seasons;

		return self::normalize_series( $details );
	}

	/**
	 * Normalize a TMDb TV details payload.
	 *
	 * Public for deterministic unit tests; performs no I/O.
	 *
	 * @param array<string, mixed> $body TMDb TV payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function normalize_series( array $body ) {
		$tmdb_id = isset( $body['id'] ) ? absint( $body['id'] ) : 0;
		$name    = isset( $body['name'] ) ? trim( (string) $body['name'] ) : '';
		if ( $tmdb_id <= 0 || '' === $name ) {
			return new WP_Error( 'series_preview_invalid_tmdb_response', __( 'TMDb series response was missing required fields.', 'movies-wp' ) );
		}

		$genres = array();
		foreach ( isset( $body['genres'] ) && is_array( $body['genres'] ) ? $body['genres'] : array() as $genre ) {
			if ( ! is_array( $genre ) || empty( $genre['name'] ) ) {
				continue;
			}
			$genres[] = array(
				'id'   => isset( $genre['id'] ) ? absint( $genre['id'] ) : 0,
				'name' => trim( (string) $genre['name'] ),
			);
		}

		$countries = array();
		foreach ( isset( $body['origin_country'] ) && is_array( $body['origin_country'] ) ? $body['origin_country'] : array() as $country ) {
			$country = strtoupper( trim( (string) $country ) );
			if ( '' !== $country ) {
				$countries[] = $country;
			}
		}

		$seasons = array();
		foreach ( isset( $body['seasons'] ) && is_array( $body['seasons'] ) ? $body['seasons'] : array() as $season ) {
			if ( ! is_array( $season ) ) {
				continue;
			}
			if ( isset( $season['episodes'] ) && array_key_exists( 'season_number', $season ) ) {
				$normalized = self::normalize_season( $season );
				if ( ! is_wp_error( $normalized ) ) {
					$seasons[] = $normalized;
				}
			}
		}

		$unseasoned_episodes = isset( $body['unseasoned_episodes'] ) && is_array( $body['unseasoned_episodes'] )
			? $body['unseasoned_episodes']
			: ( isset( $body['episodes'] ) && is_array( $body['episodes'] ) ? $body['episodes'] : array() );
		$uses_unseasoned_catalog = false;
		if ( $seasons === array() && $unseasoned_episodes !== array() ) {
			$unseasoned_season = isset( $body['unseasoned_season'] ) && is_array( $body['unseasoned_season'] )
				? $body['unseasoned_season']
				: array();
			$unseasoned_season['season_number']  = 1;
			$unseasoned_season['name']           = __( 'Season 1', 'movies-wp' );
			$unseasoned_season['episodes']       = $unseasoned_episodes;
			$unseasoned_season['identity_source'] = 'tmdb_unseasoned_episode_catalog';
			$unseasoned_season['unseasoned']      = true;
			$normalized = self::normalize_season( $unseasoned_season );
			if ( ! is_wp_error( $normalized ) ) {
				$seasons[] = $normalized;
				$uses_unseasoned_catalog = true;
			}
		}

		usort(
			$seasons,
			static function ( $a, $b ) {
				return (int) $a['season_number'] <=> (int) $b['season_number'];
			}
		);

		$poster_path   = self::optional_path( $body['poster_path'] ?? null );
		$backdrop_path = self::optional_path( $body['backdrop_path'] ?? null );
		$external      = isset( $body['external_ids'] ) && is_array( $body['external_ids'] ) ? $body['external_ids'] : array();
		$credits       = isset( $body['credits'] ) && is_array( $body['credits'] ) ? $body['credits'] : array();

		$number_of_seasons = isset( $body['number_of_seasons'] ) ? absint( $body['number_of_seasons'] ) : count( array_filter( $seasons, static fn( $season ) => 0 !== (int) $season['season_number'] ) );
		foreach ( $seasons as $season ) {
			if ( ! empty( $season['unseasoned'] ) ) {
				$uses_unseasoned_catalog = true;
				break;
			}
		}
		if ( $uses_unseasoned_catalog && $number_of_seasons < 1 ) {
			$number_of_seasons = 1;
		}

		return array(
			'tmdb_id'            => $tmdb_id,
			'name'               => $name,
			'original_name'      => isset( $body['original_name'] ) ? trim( (string) $body['original_name'] ) : '',
			'overview'           => isset( $body['overview'] ) ? (string) $body['overview'] : '',
			'first_air_date'     => isset( $body['first_air_date'] ) ? trim( (string) $body['first_air_date'] ) : '',
			'rating'             => isset( $body['vote_average'] ) && is_numeric( $body['vote_average'] ) ? (float) $body['vote_average'] : null,
			'poster_path'        => $poster_path,
			'poster_url'         => self::image_url( $poster_path, 'w500' ),
			'backdrop_path'      => $backdrop_path,
			'backdrop_url'       => self::image_url( $backdrop_path, 'original' ),
			'genres'             => $genres,
			'origin_country'     => array_values( array_unique( $countries ) ),
			'original_language'  => isset( $body['original_language'] ) ? trim( (string) $body['original_language'] ) : '',
			'imdb_id'            => isset( $external['imdb_id'] ) ? trim( (string) $external['imdb_id'] ) : '',
			'number_of_seasons'  => $number_of_seasons,
			'number_of_episodes' => isset( $body['number_of_episodes'] ) ? absint( $body['number_of_episodes'] ) : array_sum( array_map( static fn( $season ) => count( $season['episodes'] ), $seasons ) ),
			'seasons'            => $seasons,
			'episode_catalog'    => $uses_unseasoned_catalog ? 'unseasoned_season_1' : ( $seasons !== array() ? 'seasoned' : 'unavailable' ),
			'cast'               => self::normalize_people( $credits['cast'] ?? array(), 'cast' ),
			'crew'               => self::normalize_people( $credits['crew'] ?? array(), 'crew' ),
		);
	}

	/**
	 * Normalize one TMDb season payload, including Season 0.
	 *
	 * @param array<string, mixed> $body TMDb season payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function normalize_season( array $body ) {
		if ( ! array_key_exists( 'season_number', $body ) || ! is_numeric( $body['season_number'] ) ) {
			return new WP_Error( 'series_preview_invalid_season', __( 'TMDb season response was missing its season number.', 'movies-wp' ) );
		}

		$season_number = (int) $body['season_number'];
		if ( $season_number < 0 ) {
			return new WP_Error( 'series_preview_invalid_season', __( 'TMDb season number must not be negative.', 'movies-wp' ) );
		}

		$episodes = array();
		foreach ( isset( $body['episodes'] ) && is_array( $body['episodes'] ) ? $body['episodes'] : array() as $episode ) {
			if ( ! is_array( $episode ) ) {
				continue;
			}
			$episode['season_number'] = $season_number;
			$normalized               = self::normalize_episode( $episode );
			if ( is_wp_error( $normalized ) ) {
				return $normalized;
			}
			$episodes[] = $normalized;
		}

		usort(
			$episodes,
			static function ( $a, $b ) {
				return (int) $a['episode_number'] <=> (int) $b['episode_number'];
			}
		);

		$poster_path = self::optional_path( $body['poster_path'] ?? null );

		return array(
			'season_number'   => $season_number,
			'name'            => isset( $body['name'] ) && '' !== trim( (string) $body['name'] )
				? trim( (string) $body['name'] )
				: ( 0 === $season_number ? __( 'Specials', 'movies-wp' ) : sprintf( __( 'Season %d', 'movies-wp' ), $season_number ) ),
			'air_date'        => isset( $body['air_date'] ) ? trim( (string) $body['air_date'] ) : '',
			'overview'        => isset( $body['overview'] ) ? (string) $body['overview'] : '',
			'poster_path'     => $poster_path,
			'poster_url'      => self::image_url( $poster_path, 'w500' ),
			'episode_count'   => isset( $body['episode_count'] ) ? absint( $body['episode_count'] ) : count( $episodes ),
			'episodes'        => $episodes,
			'identity_source' => isset( $body['identity_source'] ) ? (string) $body['identity_source'] : 'tmdb_season',
			'unseasoned'      => ! empty( $body['unseasoned'] ),
		);
	}

	/**
	 * Normalize one TMDb episode payload.
	 *
	 * @param array<string, mixed> $body TMDb episode payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function normalize_episode( array $body ) {
		$tmdb_id = isset( $body['id'] ) ? absint( $body['id'] ) : ( isset( $body['tmdb_id'] ) ? absint( $body['tmdb_id'] ) : 0 );
		if ( $tmdb_id <= 0 || ! isset( $body['season_number'] ) || ! is_numeric( $body['season_number'] ) || ! isset( $body['episode_number'] ) || ! is_numeric( $body['episode_number'] ) ) {
			return new WP_Error( 'series_preview_invalid_episode', __( 'TMDb episode response was missing required identity fields.', 'movies-wp' ) );
		}

		$season_number  = (int) $body['season_number'];
		$episode_number = (int) $body['episode_number'];
		if ( $season_number < 0 || $episode_number <= 0 ) {
			return new WP_Error( 'series_preview_invalid_episode', __( 'TMDb episode identity fields are invalid.', 'movies-wp' ) );
		}

		$still_path = self::optional_path( $body['still_path'] ?? null );

		return array(
			'tmdb_id'        => $tmdb_id,
			'season_number'  => $season_number,
			'episode_number' => $episode_number,
			'name'           => isset( $body['name'] ) ? trim( (string) $body['name'] ) : '',
			'overview'       => isset( $body['overview'] ) ? (string) $body['overview'] : '',
			'air_date'       => isset( $body['air_date'] ) ? trim( (string) $body['air_date'] ) : '',
			'runtime'        => isset( $body['runtime'] ) && is_numeric( $body['runtime'] ) && (int) $body['runtime'] > 0 ? (int) $body['runtime'] : null,
			'still_path'     => $still_path,
			'still_url'      => self::image_url( $still_path, 'w500' ),
		);
	}

	/**
	 * @param string $url
	 * @param array  $options
	 * @return array<string, mixed>|WP_Error
	 */
	private static function fetch_json( $url, array $options ) {
		if ( isset( $options['fetch_json'] ) && is_callable( $options['fetch_json'] ) ) {
			$result = call_user_func( $options['fetch_json'], $url );
			return is_array( $result ) || is_wp_error( $result )
				? $result
				: new WP_Error( 'series_preview_tmdb_error', __( 'TMDb request returned invalid data.', 'movies-wp' ) );
		}

		$response = self::remote_get( $url );
		if ( self::is_transient_tmdb_failure( $response ) ) {
			$response = self::remote_get( $url );
		}
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'series_preview_tmdb_error', __( 'TMDb request failed.', 'movies-wp' ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 404 === $status ) {
			return new WP_Error( 'series_preview_tmdb_not_found', __( 'TMDb series was not found.', 'movies-wp' ) );
		}
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) ) {
			return new WP_Error( 'series_preview_tmdb_error', __( 'TMDb request failed.', 'movies-wp' ) );
		}
		return $body;
	}

	/**
	 * @param string $url
	 * @return array|WP_Error
	 */
	private static function remote_get( $url ) {
		return wp_remote_get(
			$url,
			array(
				'timeout'     => 40,
				'redirection' => 0,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);
	}

	/**
	 * @param array|WP_Error $response
	 */
	private static function is_transient_tmdb_failure( $response ) {
		if ( is_wp_error( $response ) ) {
			return true;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $status || $status >= 500 ) {
			return true;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		return $status >= 200 && $status < 300 && '' === trim( $body );
	}

	private static function host() {
		return defined( 'STREAMIT_TMDB_PROXY_HOST' ) ? STREAMIT_TMDB_PROXY_HOST : 'tmdb.asiastars.ir';
	}

	private static function api_key( array $options ) {
		if ( isset( $options['api_key'] ) && is_string( $options['api_key'] ) ) {
			return trim( $options['api_key'] );
		}
		$raw      = get_option( 'streamit_content_import_settings' );
		$settings = is_string( $raw ) ? @unserialize( $raw ) : $raw;
		$key      = is_array( $settings ) ? ( $settings['tmdb']['api_key'] ?? '' ) : '';
		return is_string( $key ) ? trim( $key ) : '';
	}

	private static function optional_path( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		return '' === $value ? null : $value;
	}

	private static function image_url( $path, $size ) {
		if ( ! is_string( $path ) || '' === trim( $path ) ) {
			return null;
		}
		if ( function_exists( 'streamit_tmdb_direct_image_url' ) ) {
			$url = streamit_tmdb_direct_image_url( $path, $size );
		} else {
			$url = 'https://image.tmdb.org/t/p/' . $size . '/' . ltrim( $path, '/' );
		}
		if ( function_exists( 'streamit_tmdb_image_proxy_url' ) && is_string( $url ) && '' !== $url ) {
			return streamit_tmdb_image_proxy_url( $url );
		}
		return is_string( $url ) && '' !== $url ? $url : null;
	}

	private static function normalize_people( $people, $type ) {
		$out = array();
		foreach ( is_array( $people ) ? $people : array() as $person ) {
			if ( ! is_array( $person ) || empty( $person['name'] ) ) {
				continue;
			}
			$row = array(
				'tmdb_id'     => isset( $person['id'] ) ? absint( $person['id'] ) : 0,
				'name'        => trim( (string) $person['name'] ),
				'profile_path'=> self::optional_path( $person['profile_path'] ?? null ),
			);
			if ( 'cast' === $type ) {
				$row['character'] = isset( $person['character'] ) ? trim( (string) $person['character'] ) : '';
				$row['order']     = isset( $person['order'] ) ? (int) $person['order'] : count( $out );
			} else {
				$row['job'] = isset( $person['job'] ) ? trim( (string) $person['job'] ) : '';
			}
			$out[] = $row;
		}
		return $out;
	}
}

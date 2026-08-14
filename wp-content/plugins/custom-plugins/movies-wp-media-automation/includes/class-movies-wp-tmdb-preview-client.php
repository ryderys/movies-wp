<?php
/**
 * Read-only TMDb fetch for import preview.
 *
 * Reuses Streamit's stored API key and the child-theme proxy host.
 * Does not call Streamit's importer and does not write any data.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Tmdb_Preview_Client {

	/**
	 * Fetch TMDb movie details for preview.
	 *
	 * @param int $tmdb_id TMDb movie ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_movie( $tmdb_id ) {
		$tmdb_id = absint( $tmdb_id );
		if ( $tmdb_id <= 0 ) {
			return new WP_Error( 'media_preview_invalid_tmdb_response', 'TMDb ID is invalid.' );
		}

		$api_key = self::api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'media_preview_tmdb_error', 'TMDb is not configured.' );
		}

		$host = defined( 'STREAMIT_TMDB_PROXY_HOST' )
			? STREAMIT_TMDB_PROXY_HOST
			: 'tmdb.youssefi-ashkan-ys.workers.dev';

		$url = sprintf(
			'https://%s/3/movie/%d?api_key=%s&language=%s&append_to_response=%s',
			$host,
			$tmdb_id,
			rawurlencode( $api_key ),
			rawurlencode( 'en-US' ),
			rawurlencode( 'credits' )
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 40,
				'redirection' => 0,
				'headers'     => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'media_preview_tmdb_error', 'TMDb request failed.' );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 404 === $status ) {
			return new WP_Error( 'media_preview_tmdb_error', 'TMDb movie not found.' );
		}

		if ( $status < 200 || $status >= 300 || ! is_array( $body ) ) {
			return new WP_Error( 'media_preview_tmdb_error', 'TMDb request failed.' );
		}

		if ( empty( $body['id'] ) ) {
			return new WP_Error( 'media_preview_invalid_tmdb_response', 'TMDb response was missing required fields.' );
		}

		return self::normalize_movie( $body );
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 */
	private static function normalize_movie( array $body ) {
		$release = isset( $body['release_date'] ) ? (string) $body['release_date'] : '';
		$year    = null;
		if ( preg_match( '/^(19|20)\d{2}/', $release, $m ) ) {
			$year = (int) substr( $release, 0, 4 );
		}

		$genres = array();
		if ( ! empty( $body['genres'] ) && is_array( $body['genres'] ) ) {
			foreach ( $body['genres'] as $genre ) {
				if ( ! is_array( $genre ) || empty( $genre['name'] ) ) {
					continue;
				}
				$genres[] = array(
					'id'   => isset( $genre['id'] ) ? (int) $genre['id'] : 0,
					'name' => (string) $genre['name'],
				);
			}
		}

		$countries = array();
		if ( ! empty( $body['production_countries'] ) && is_array( $body['production_countries'] ) ) {
			foreach ( $body['production_countries'] as $country ) {
				if ( ! is_array( $country ) ) {
					continue;
				}
				$iso  = isset( $country['iso_3166_1'] ) ? (string) $country['iso_3166_1'] : '';
				$name = isset( $country['name'] ) ? (string) $country['name'] : '';
				if ( '' === $iso && '' === $name ) {
					continue;
				}
				$countries[] = array(
					'iso'  => $iso,
					'name' => $name,
				);
			}
		}

		$cast = array();
		if ( ! empty( $body['credits']['cast'] ) && is_array( $body['credits']['cast'] ) ) {
			foreach ( $body['credits']['cast'] as $person ) {
				if ( ! is_array( $person ) || empty( $person['name'] ) ) {
					continue;
				}
				$cast[] = array(
					'name'      => (string) $person['name'],
					'character' => isset( $person['character'] ) ? (string) $person['character'] : '',
				);
				if ( count( $cast ) >= 15 ) {
					break;
				}
			}
		}

		$poster_path    = isset( $body['poster_path'] ) ? (string) $body['poster_path'] : '';
		$backdrop_path  = isset( $body['backdrop_path'] ) ? (string) $body['backdrop_path'] : '';

		return array(
			'id'                => (int) $body['id'],
			'original_title'    => isset( $body['original_title'] ) ? (string) $body['original_title'] : '',
			'title'             => isset( $body['title'] ) ? (string) $body['title'] : '',
			'overview'          => isset( $body['overview'] ) ? (string) $body['overview'] : '',
			'release_date'      => $release,
			'year'              => $year,
			'runtime'           => isset( $body['runtime'] ) ? (int) $body['runtime'] : null,
			'original_language' => isset( $body['original_language'] ) ? (string) $body['original_language'] : '',
			'genres'            => $genres,
			'countries'         => $countries,
			'cast'              => $cast,
			'poster_path'       => $poster_path !== '' ? $poster_path : null,
			'poster_url'        => self::image_url( $poster_path, 'w500' ),
			'backdrop_path'     => $backdrop_path !== '' ? $backdrop_path : null,
			'backdrop_url'      => self::image_url( $backdrop_path, 'original' ),
		);
	}

	private static function image_url( $path, $size ) {
		$path = is_string( $path ) ? trim( $path ) : '';
		if ( '' === $path ) {
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

		return $url !== '' ? $url : null;
	}

	private static function api_key() {
		$raw = get_option( 'streamit_content_import_settings' );
		if ( is_string( $raw ) ) {
			$settings = @unserialize( $raw );
		} else {
			$settings = $raw;
		}

		if ( ! is_array( $settings ) ) {
			return '';
		}

		$key = $settings['tmdb']['api_key'] ?? '';
		return is_string( $key ) ? $key : '';
	}
}

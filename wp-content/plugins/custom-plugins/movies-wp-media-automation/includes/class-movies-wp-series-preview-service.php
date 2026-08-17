<?php
/**
 * Side-effect-free Series Automation preview service.
 *
 * Combines operator input with normalized TMDb TV metadata. It does not scan
 * media files and does not write WordPress or Streamit data.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Preview_Service {

	/**
	 * @param array<string, mixed> $input   Operator input.
	 * @param array<string, mixed> $options Test hooks passed to the TMDb client.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function build( array $input, array $options = array() ) {
		$normalized = self::normalize_input( $input );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		if ( isset( $options['get_series'] ) && is_callable( $options['get_series'] ) ) {
			$series = call_user_func( $options['get_series'], $normalized['tmdb_id'] );
		} else {
			$series = Movies_WP_Tmdb_TV_Preview_Client::get_series( $normalized['tmdb_id'], $options );
		}
		if ( is_wp_error( $series ) ) {
			return $series;
		}

		$validation = self::validate( $series );

		return array(
			'ok'              => true,
			'type'            => 'series',
			'input'           => $normalized,
			'series'          => $series,
			'validation'      => $validation,
			'ready_to_import' => empty( $validation['errors'] ),
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{tmdb_id:int,title:string,summary:string}|WP_Error
	 */
	private static function normalize_input( array $input ) {
		$tmdb_id = isset( $input['tmdb_id'] ) ? absint( $input['tmdb_id'] ) : 0;
		$title   = isset( $input['title'] ) ? trim( (string) $input['title'] ) : '';
		$summary = isset( $input['summary'] ) ? trim( (string) $input['summary'] ) : '';

		if ( $tmdb_id <= 0 ) {
			return new WP_Error( 'series_preview_invalid_input', __( 'TMDb Series ID must be a positive number.', 'movies-wp' ) );
		}
		if ( '' === $title ) {
			return new WP_Error( 'series_preview_invalid_input', __( 'Persian / local series title is required.', 'movies-wp' ) );
		}

		return array(
			'tmdb_id' => $tmdb_id,
			'title'   => $title,
			'summary' => $summary,
		);
	}

	/**
	 * Missing optional TMDb data is visible but does not block import.
	 *
	 * @param array<string, mixed> $series
	 * @return array{errors:list<array<string,string>>,warnings:list<array<string,string>>}
	 */
	private static function validate( array $series ) {
		$errors   = array();
		$warnings = array();

		if ( empty( $series['tmdb_id'] ) || empty( $series['name'] ) ) {
			$errors[] = self::issue( 'series_required_data_missing', __( 'Required TMDb series data is missing.', 'movies-wp' ) );
		}
		if ( empty( $series['poster_path'] ) ) {
			$warnings[] = self::issue( 'series_poster_missing', __( 'TMDb has no poster for this series.', 'movies-wp' ) );
		}
		if ( empty( $series['backdrop_path'] ) ) {
			$warnings[] = self::issue( 'series_backdrop_missing', __( 'TMDb has no backdrop for this series.', 'movies-wp' ) );
		}
		if ( empty( $series['seasons'] ) ) {
			$warnings[] = self::issue( 'series_seasons_missing', __( 'TMDb returned no seasons for this series.', 'movies-wp' ) );
		}

		foreach ( isset( $series['seasons'] ) && is_array( $series['seasons'] ) ? $series['seasons'] : array() as $season ) {
			if ( ! is_array( $season ) ) {
				continue;
			}
			foreach ( isset( $season['episodes'] ) && is_array( $season['episodes'] ) ? $season['episodes'] : array() as $episode ) {
				if ( ! is_array( $episode ) ) {
					continue;
				}
				$code = sprintf( 'S%02dE%02d', (int) ( $episode['season_number'] ?? 0 ), (int) ( $episode['episode_number'] ?? 0 ) );
				if ( empty( $episode['still_path'] ) ) {
					$warnings[] = self::issue(
						'series_episode_still_missing',
						sprintf(
							/* translators: %s: technical episode code, for example S01E02 */
							__( '%s has no episode still on TMDb.', 'movies-wp' ),
							$code
						)
					);
				}
			}
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	private static function issue( $code, $message ) {
		return array(
			'code'    => (string) $code,
			'message' => (string) $message,
		);
	}
}

<?php
/**
 * Read-only Series media import plan.
 *
 * Translates a Series media preview into deterministic _sources/_subtitles
 * operations only. Never plans metadata or player fields.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Media_Import_Plan {

	/**
	 * @param array<string, mixed> $preview
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|WP_Error
	 */
	public static function build( array $preview, array $options = array() ) {
		if ( empty( $preview['ok'] ) || ( $preview['type'] ?? '' ) !== 'series_media' ) {
			return new WP_Error( 'series_media_import_plan_invalid_preview', __( 'Series media preview payload is invalid.', 'movies-wp' ) );
		}

		$input    = isset( $preview['input'] ) && is_array( $preview['input'] ) ? $preview['input'] : array();
		$tvshow_id = absint( $input['tvshow_id'] ?? 0 );
		$directory = (string) ( $input['series_directory'] ?? '' );
		if ( $tvshow_id <= 0 || '' === $directory ) {
			return new WP_Error( 'series_media_import_plan_invalid_preview', __( 'Series media preview input is incomplete.', 'movies-wp' ) );
		}

		$errors   = array();
		$warnings = array();
		self::merge_issues( $errors, $warnings, $preview['validation'] ?? array() );

		$episodes = array();
		foreach ( isset( $preview['episodes'] ) && is_array( $preview['episodes'] ) ? $preview['episodes'] : array() as $episode ) {
			if ( ! is_array( $episode ) ) {
				continue;
			}
			if ( ( $episode['status'] ?? '' ) !== 'matched' ) {
				if ( ! empty( $episode['status'] ) ) {
					$errors[] = self::issue(
						(string) $episode['status'],
						sprintf(
							__( 'Episode S%1$sE%2$s is not ready for media import.', 'movies-wp' ),
							(string) ( $episode['season_number'] ?? '' ),
							(string) ( $episode['episode_number'] ?? '' )
						)
					);
				}
				continue;
			}

			$episode_id = absint( $episode['episode_id'] ?? 0 );
			if ( $episode_id <= 0 ) {
				continue;
			}

			$season  = Movies_WP_Series_Media_Preview_Service::canonical_season_string( $episode['season_number'] ?? null );
			$number  = Movies_WP_Series_Media_Preview_Service::canonical_episode_string( $episode['episode_number'] ?? null );
			if ( null === $season || null === $number ) {
				$errors[] = self::issue( 'invalid_episode_identity', __( 'Episode identity is invalid.', 'movies-wp' ) );
				continue;
			}

			$existing_sources   = self::load_sources( $episode_id, $options );
			$existing_subtitles = self::load_subtitles( $episode_id, $options );

			$source_ops = self::plan_sources( $episode['sources'] ?? array(), $existing_sources );
			$sub_ops    = self::plan_subtitles( $episode['subtitles'] ?? array(), $existing_subtitles, $season, $number );

			foreach ( $source_ops['warnings'] as $warning ) {
				$warnings[] = $warning;
			}
			foreach ( $sub_ops['warnings'] as $warning ) {
				$warnings[] = $warning;
			}
			foreach ( $source_ops['errors'] as $error ) {
				$errors[] = $error;
			}
			foreach ( $sub_ops['errors'] as $error ) {
				$errors[] = $error;
			}

			$episodes[] = array(
				'episode_id'     => $episode_id,
				'tvshow_id'      => $tvshow_id,
				'season_number'  => $season,
				'episode_number' => $number,
				'tmdb_id'        => absint( $episode['tmdb_id'] ?? 0 ),
				'ownership'      => array(
					'tvshow_id'      => $tvshow_id,
					'season_number'  => $season,
					'episode_number' => $number,
					'tmdb_id'        => absint( $episode['tmdb_id'] ?? 0 ),
				),
				'operations'     => array(
					'_sources'   => $source_ops['operations'],
					'_subtitles' => $sub_ops['operations'],
				),
			);
		}

		$ready = empty( $errors );

		return array(
			'ok'              => true,
			'type'            => 'series_media',
			'ready_to_import' => $ready,
			'contract'        => array(
				'kind'      => 'series_media_import_plan',
				'version'   => 1,
				'read_only' => true,
			),
			'identity'        => array(
				'tvshow_id'         => $tvshow_id,
				'series_directory'  => $directory,
				'expected_tmdb_id'  => absint( $input['expected_tmdb_id'] ?? 0 ),
			),
			'policy'          => array(
				'preserve_order'      => true,
				'delete_unmatched'    => false,
				'allowed_meta_keys'   => array( '_sources', '_subtitles' ),
			),
			'episodes'        => $episodes,
			'errors'          => $errors,
			'warnings'        => $warnings,
		);
	}

	/**
	 * @param list<array<string, mixed>> $sources
	 * @param list<array<string, mixed>> $existing
	 * @return array{operations:list<array>,warnings:list<array>,errors:list<array>}
	 */
	private static function plan_sources( array $sources, array $existing ) {
		$operations = array();
		$warnings   = array();
		$errors     = array();
		$seen       = array();

		foreach ( $sources as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$path = self::normalize_series_path( (string) ( $source['media_path'] ?? '' ) );
			if ( is_wp_error( $path ) ) {
				$errors[] = self::issue( 'invalid_media_path', $path->get_error_message() );
				continue;
			}
			if ( isset( $seen[ $path ] ) ) {
				$warnings[] = self::issue( 'duplicate_media_path', __( 'Duplicate source media path in scan results.', 'movies-wp' ) );
				continue;
			}
			$seen[ $path ] = true;

			$quality = isset( $source['quality'] ) && is_string( $source['quality'] ) ? $source['quality'] : '';
			$operations[] = array(
				'action' => 'upsert',
				'path'   => $path,
				'row'    => array(
					'name'             => '',
					'link'             => $path,
					'is_affiliate'     => '0',
					'quality'          => $quality,
					'language'         => '',
					'player'           => '',
					'date_added'       => '{{import_date}}',
					'download_content' => $path,
					'file_size'        => (string) ( $source['size_label'] ?? '' ),
				),
			);
		}

		foreach ( $existing as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$path = self::normalize_series_path( (string) ( $row['link'] ?? $row['download_content'] ?? '' ) );
			if ( is_wp_error( $path ) ) {
				continue;
			}
			if ( ! isset( $seen[ $path ] ) ) {
				$operations[] = array(
					'action' => 'preserve',
					'path'   => $path,
					'row'    => $row,
				);
			}
		}

		return array(
			'operations' => $operations,
			'warnings'   => $warnings,
			'errors'     => $errors,
		);
	}

	/**
	 * @param list<array<string, mixed>> $subtitles
	 * @param list<array<string, mixed>> $existing
	 * @return array{operations:list<array>,warnings:list<array>,errors:list<array>}
	 */
	private static function plan_subtitles( array $subtitles, array $existing, $season, $episode ) {
		$operations = array();
		$warnings   = array();
		$errors     = array();
		$seen       = array();

		foreach ( $subtitles as $subtitle ) {
			if ( ! is_array( $subtitle ) ) {
				continue;
			}
			$path = self::normalize_series_path( (string) ( $subtitle['media_path'] ?? $subtitle['subtitle']['url'] ?? '' ) );
			if ( is_wp_error( $path ) ) {
				$errors[] = self::issue( 'invalid_media_path', $path->get_error_message() );
				continue;
			}
			if ( isset( $seen[ $path ] ) ) {
				$warnings[] = self::issue( 'duplicate_media_path', __( 'Duplicate subtitle media path in scan results.', 'movies-wp' ) );
				continue;
			}
			$seen[ $path ] = true;

			$meta = isset( $subtitle['subtitle'] ) && is_array( $subtitle['subtitle'] ) ? $subtitle['subtitle'] : array();
			$row  = array(
				'label'   => (string) ( $meta['label'] ?? 'SUB' ),
				'srclang' => (string) ( $meta['srclang'] ?? '' ),
				'url'     => $path,
				'default' => 0,
				'format'  => (string) ( $meta['format'] ?? 'SRT' ),
			);

			$extension = strtolower( (string) ( $subtitle['extension'] ?? pathinfo( $path, PATHINFO_EXTENSION ) ) );
			if ( ! in_array( $extension, array( 'srt', 'vtt' ), true ) ) {
				$warnings[] = self::issue( 'subtitle_playback_unsupported', __( 'Subtitle format may not be supported for playback.', 'movies-wp' ) );
			}

			$operations[] = array(
				'action' => 'upsert',
				'path'   => $path,
				'row'    => $row,
			);
		}

		foreach ( $existing as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$path = self::normalize_series_path( (string) ( $row['url'] ?? '' ) );
			if ( is_wp_error( $path ) ) {
				continue;
			}
			if ( ! isset( $seen[ $path ] ) ) {
				$operations[] = array(
					'action' => 'preserve',
					'path'   => $path,
					'row'    => $row,
				);
			}
		}

		unset( $season, $episode );
		return array(
			'operations' => $operations,
			'warnings'   => $warnings,
			'errors'     => $errors,
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function load_sources( $episode_id, array $options ) {
		if ( isset( $options['get_sources'] ) && is_callable( $options['get_sources'] ) ) {
			$raw = call_user_func( $options['get_sources'], (int) $episode_id );
		} elseif ( function_exists( 'streamit_get_episode_meta' ) ) {
			$raw = streamit_get_episode_meta( (int) $episode_id, '_sources', true );
		} else {
			return array();
		}
		if ( is_string( $raw ) ) {
			$raw = maybe_unserialize( $raw );
		}
		return is_array( $raw ) ? array_values( $raw ) : array();
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function load_subtitles( $episode_id, array $options ) {
		if ( isset( $options['get_subtitles'] ) && is_callable( $options['get_subtitles'] ) ) {
			$raw = call_user_func( $options['get_subtitles'], (int) $episode_id );
		} elseif ( function_exists( 'streamit_get_episode_meta' ) ) {
			$raw = streamit_get_episode_meta( (int) $episode_id, '_subtitles', true );
		} else {
			return array();
		}
		if ( is_string( $raw ) ) {
			$raw = maybe_unserialize( $raw );
		}
		return is_array( $raw ) ? array_values( $raw ) : array();
	}

	/**
	 * @return string|WP_Error
	 */
	public static function normalize_series_path( $path ) {
		$path = str_replace( '\\', '/', trim( (string) $path ) );
		$path = preg_replace( '#/+#', '/', $path ) ?? $path;
		$path = ltrim( $path, '/' );
		if ( '' === $path || str_contains( $path, "\0" ) || str_contains( $path, '..' ) ) {
			return new WP_Error( 'invalid_media_path', __( 'Invalid series media path.', 'movies-wp' ) );
		}
		if ( ! str_starts_with( $path, 'Series/' ) ) {
			return new WP_Error( 'invalid_media_path', __( 'Series media paths must begin with Series/.', 'movies-wp' ) );
		}
		if ( preg_match( '#^(https?://|/data/|/v/|/d/)#i', $path ) ) {
			return new WP_Error( 'invalid_media_path', __( 'Signed or absolute paths are not allowed.', 'movies-wp' ) );
		}
		return $path;
	}

	/**
	 * @param list<array<string,mixed>> $errors
	 * @param list<array<string,mixed>> $warnings
	 * @param mixed $validation
	 */
	private static function merge_issues( array &$errors, array &$warnings, $validation ) {
		if ( ! is_array( $validation ) ) {
			return;
		}
		foreach ( array( 'errors', 'warnings' ) as $bucket ) {
			if ( ! isset( $validation[ $bucket ] ) || ! is_array( $validation[ $bucket ] ) ) {
				continue;
			}
			foreach ( $validation[ $bucket ] as $issue ) {
				if ( is_array( $issue ) ) {
					${$bucket}[] = $issue;
				}
			}
		}
	}

	private static function issue( $code, $message ) {
		return array(
			'code'    => (string) $code,
			'message' => (string) $message,
		);
	}
}

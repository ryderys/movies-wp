<?php
/**
 * Thin orchestration layer for the unified Series Automation workflow.
 *
 * Preview composes the existing TMDb metadata stack with the existing Series
 * filesystem scanner. Import keeps persistence firewalled: metadata is applied
 * first, then the media preview and plan are rebuilt against live episode IDs.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Orchestrator {

	/**
	 * Build the combined, read-only Series preview.
	 *
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $options Deterministic test hooks.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function build_preview( array $input, array $options = array() ) {
		$normalized = self::normalize_input( $input );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$metadata_input = array(
			'tmdb_id' => $normalized['tmdb_id'],
			'title'   => $normalized['title'],
			'summary' => $normalized['summary'],
		);
		$metadata_preview = isset( $options['metadata_preview_build'] ) && is_callable( $options['metadata_preview_build'] )
			? call_user_func( $options['metadata_preview_build'], $metadata_input )
			: Movies_WP_Series_Preview_Service::build( $metadata_input );
		if ( is_wp_error( $metadata_preview ) ) {
			return $metadata_preview;
		}
		if ( ! is_array( $metadata_preview ) ) {
			return new WP_Error( 'series_automation_invalid_metadata_preview', __( 'Series metadata preview returned invalid data.', 'movies-wp' ) );
		}

		$metadata_plan = isset( $options['metadata_plan_build'] ) && is_callable( $options['metadata_plan_build'] )
			? call_user_func( $options['metadata_plan_build'], $metadata_preview )
			: Movies_WP_Series_Import_Plan::build( $metadata_preview );
		if ( is_wp_error( $metadata_plan ) ) {
			return $metadata_plan;
		}
		if ( ! is_array( $metadata_plan ) ) {
			return new WP_Error( 'series_automation_invalid_metadata_plan', __( 'Series metadata plan returned invalid data.', 'movies-wp' ) );
		}

		$scan = isset( $options['scan_series'] ) && is_callable( $options['scan_series'] )
			? call_user_func( $options['scan_series'], $normalized['series_directory'] )
			: Movies_WP_Series_Media_Api_Client::scan_series_directory( $normalized['series_directory'] );
		if ( is_wp_error( $scan ) ) {
			return $scan;
		}
		if ( ! is_array( $scan ) || empty( $scan['ok'] ) || 'series' !== ( $scan['kind'] ?? '' ) ) {
			return new WP_Error( 'series_automation_invalid_media_scan', __( 'Series media scan returned invalid data.', 'movies-wp' ) );
		}

		$errors   = array();
		$warnings = array();
		self::append_issues( $errors, $metadata_plan['errors'] ?? array() );
		self::append_issues( $warnings, $metadata_plan['warnings'] ?? array() );
		self::append_issues( $errors, $scan['errors'] ?? array() );
		self::append_issues( $warnings, $scan['warnings'] ?? array() );

		$tmdb_index = self::index_tmdb_episodes( $metadata_preview, $metadata_plan, $errors );
		$scan_index = self::index_scan_episodes( $scan, $errors );
		$episodes   = array();

		foreach ( $tmdb_index as $key => $tmdb_episode ) {
			$media = $scan_index[ $key ] ?? null;
			unset( $scan_index[ $key ] );
			$source_count   = is_array( $media ) ? count( self::list_value( $media['sources'] ?? array() ) ) : 0;
			$subtitle_count = is_array( $media ) ? count( self::list_value( $media['subtitles'] ?? array() ) ) : 0;

			$episodes[] = array_merge(
				$tmdb_episode,
				array(
					'status'         => ( $source_count + $subtitle_count ) > 0 ? 'metadata_and_media' : 'metadata_only',
					'token'          => is_array( $media ) ? (string) ( $media['token'] ?? '' ) : '',
					'sources'        => is_array( $media ) ? self::list_value( $media['sources'] ?? array() ) : array(),
					'subtitles'      => is_array( $media ) ? self::list_value( $media['subtitles'] ?? array() ) : array(),
					'source_count'   => $source_count,
					'subtitle_count' => $subtitle_count,
				)
			);
		}

		foreach ( $scan_index as $media ) {
			$source_count   = count( self::list_value( $media['sources'] ?? array() ) );
			$subtitle_count = count( self::list_value( $media['subtitles'] ?? array() ) );
			if ( 0 === $source_count && 0 === $subtitle_count ) {
				continue;
			}
			$season  = (string) ( $media['season_number'] ?? '' );
			$episode = (string) ( $media['episode_number'] ?? '' );
			$errors[] = self::issue(
				'series_automation_media_without_tmdb_episode',
				sprintf(
					/* translators: 1: season number, 2: episode number */
					__( 'Media exists for season %1$s episode %2$s, but TMDb has no matching episode. No episode will be created from the filename.', 'movies-wp' ),
					$season,
					$episode
				),
				$season,
				$episode
			);
			$episodes[] = array(
				'season_number'  => $season,
				'episode_number' => $episode,
				'tmdb_id'        => 0,
				'name'           => '',
				'action'         => null,
				'status'         => 'media_without_tmdb',
				'token'          => (string) ( $media['token'] ?? '' ),
				'sources'        => self::list_value( $media['sources'] ?? array() ),
				'subtitles'      => self::list_value( $media['subtitles'] ?? array() ),
				'source_count'   => count( self::list_value( $media['sources'] ?? array() ) ),
				'subtitle_count' => count( self::list_value( $media['subtitles'] ?? array() ) ),
			);
		}

		usort(
			$episodes,
			static function ( array $left, array $right ) {
				return array( (int) $left['season_number'], (int) $left['episode_number'] )
					<=> array( (int) $right['season_number'], (int) $right['episode_number'] );
			}
		);

		$ready = true === ( $metadata_plan['ready_to_import'] ?? null )
			&& false !== ( $scan['ready'] ?? true )
			&& array() === $errors;

		return array(
			'ok'              => true,
			'type'            => 'series_automation',
			'input'           => $normalized,
			'series'          => is_array( $metadata_preview['series'] ?? null ) ? $metadata_preview['series'] : array(),
			'metadata'        => $metadata_preview,
			'metadata_plan'   => $metadata_plan,
			'media'           => $scan,
			'episodes'        => $episodes,
			'validation'      => array(
				'errors'   => array_values( $errors ),
				'warnings' => array_values( $warnings ),
			),
			'ready_to_import' => $ready,
		);
	}

	/**
	 * Rebuild and execute metadata, then rebuild and execute episode media.
	 *
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $options Deterministic test hooks.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute( array $input, array $options = array() ) {
		$preview = self::build_preview( $input, $options );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		if ( true !== ( $preview['ready_to_import'] ?? null ) ) {
			return new WP_Error( 'series_automation_not_ready', __( 'Series Automation preview contains errors and is not ready to import.', 'movies-wp' ) );
		}

		$metadata_plan   = $preview['metadata_plan'];
		$metadata_result = isset( $options['metadata_import_execute'] ) && is_callable( $options['metadata_import_execute'] )
			? call_user_func( $options['metadata_import_execute'], $metadata_plan )
			: Movies_WP_Series_Import_Service::execute( $metadata_plan );
		if ( ! is_array( $metadata_result ) ) {
			return self::result(
				array(),
				null,
				self::issue( 'series_automation_invalid_metadata_result', __( 'Series metadata import returned invalid data.', 'movies-wp' ) )
			);
		}
		if ( empty( $metadata_result['ok'] ) ) {
			return self::result( $metadata_result, null );
		}

		$series_id = absint( $metadata_result['series_id'] ?? 0 );
		if ( $series_id <= 0 ) {
			return self::result(
				$metadata_result,
				null,
				self::issue( 'series_automation_missing_series_id', __( 'Series metadata import succeeded without a valid Streamit Series ID.', 'movies-wp' ) )
			);
		}

		$media_input = array(
			'tvshow_id'         => $series_id,
			'expected_tmdb_id'  => absint( $preview['input']['tmdb_id'] ?? 0 ),
			'series_directory'  => (string) ( $preview['input']['series_directory'] ?? '' ),
		);
		$media_preview = isset( $options['media_preview_build'] ) && is_callable( $options['media_preview_build'] )
			? call_user_func( $options['media_preview_build'], $media_input )
			: Movies_WP_Series_Media_Preview_Service::build( $media_input );
		if ( is_wp_error( $media_preview ) ) {
			return self::result( $metadata_result, null, self::wp_error_issue( $media_preview, 'series_automation_media_rebuild_failed' ) );
		}
		if ( ! is_array( $media_preview ) ) {
			return self::result(
				$metadata_result,
				null,
				self::issue( 'series_automation_invalid_media_preview', __( 'Series media preview returned invalid data after metadata import.', 'movies-wp' ) )
			);
		}

		$media_plan = isset( $options['media_plan_build'] ) && is_callable( $options['media_plan_build'] )
			? call_user_func( $options['media_plan_build'], $media_preview )
			: Movies_WP_Series_Media_Import_Plan::build( $media_preview );
		if ( is_wp_error( $media_plan ) ) {
			return self::result( $metadata_result, null, self::wp_error_issue( $media_plan, 'series_automation_media_plan_failed' ) );
		}
		if ( ! is_array( $media_plan ) || true !== ( $media_plan['ready_to_import'] ?? null ) ) {
			return self::result(
				$metadata_result,
				null,
				self::issue( 'series_automation_media_not_ready', __( 'Series metadata was imported, but the rebuilt media plan is not safe to execute.', 'movies-wp' ) )
			);
		}

		$media_result = isset( $options['media_import_execute'] ) && is_callable( $options['media_import_execute'] )
			? call_user_func( $options['media_import_execute'], $media_plan )
			: Movies_WP_Series_Media_Import_Service::execute( $media_plan );
		if ( ! is_array( $media_result ) ) {
			return self::result(
				$metadata_result,
				null,
				self::issue( 'series_automation_invalid_media_result', __( 'Series media import returned invalid data.', 'movies-wp' ) )
			);
		}
		if ( absint( $media_result['tvshow_id'] ?? 0 ) !== $series_id ) {
			return self::result(
				$metadata_result,
				$media_result,
				self::issue( 'series_automation_media_identity_mismatch', __( 'Series media import returned an unexpected TV show identity.', 'movies-wp' ) )
			);
		}

		return self::result( $metadata_result, $media_result );
	}

	/**
	 * @return array{tmdb_id:int,title:string,summary:string,series_directory:string}|WP_Error
	 */
	private static function normalize_input( array $input ) {
		$tmdb_id = absint( $input['tmdb_id'] ?? 0 );
		$title   = trim( (string) ( $input['title'] ?? '' ) );
		$summary = trim( (string) ( $input['summary'] ?? '' ) );
		if ( $tmdb_id <= 0 || '' === $title ) {
			return new WP_Error( 'series_automation_invalid_input', __( 'TMDb Series ID and local title are required.', 'movies-wp' ) );
		}
		$directory = Movies_WP_Series_Media_Api_Client::normalize_directory( (string) ( $input['series_directory'] ?? '' ) );
		if ( is_wp_error( $directory ) ) {
			return new WP_Error( 'series_automation_invalid_input', __( 'A valid relative Series directory is required.', 'movies-wp' ) );
		}
		return array(
			'tmdb_id'          => $tmdb_id,
			'title'            => $title,
			'summary'          => $summary,
			'series_directory' => $directory,
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function index_tmdb_episodes( array $preview, array $plan, array &$errors ) {
		$actions = array();
		foreach ( self::list_value( $plan['seasons'] ?? array() ) as $season_plan ) {
			foreach ( self::list_value( is_array( $season_plan ) ? ( $season_plan['episodes'] ?? array() ) : array() ) as $episode_plan ) {
				if ( ! is_array( $episode_plan ) ) {
					continue;
				}
				$key = self::episode_key( $episode_plan['season_number'] ?? null, $episode_plan['episode_number'] ?? null );
				if ( null !== $key ) {
					$actions[ $key ] = (string) ( $episode_plan['action'] ?? '' );
				}
			}
		}

		$index = array();
		$series = is_array( $preview['series'] ?? null ) ? $preview['series'] : array();
		foreach ( self::list_value( $series['seasons'] ?? array() ) as $season ) {
			if ( ! is_array( $season ) ) {
				continue;
			}
			foreach ( self::list_value( $season['episodes'] ?? array() ) as $episode ) {
				if ( ! is_array( $episode ) ) {
					continue;
				}
				$key = self::episode_key( $episode['season_number'] ?? null, $episode['episode_number'] ?? null );
				if ( null === $key ) {
					$errors[] = self::issue( 'series_automation_invalid_tmdb_episode_identity', __( 'TMDb returned an invalid episode identity.', 'movies-wp' ) );
					continue;
				}
				if ( isset( $index[ $key ] ) ) {
					$errors[] = self::issue( 'series_automation_duplicate_tmdb_episode', __( 'TMDb preview contains a duplicate season/episode identity.', 'movies-wp' ) );
					continue;
				}
				list( $season_number, $episode_number ) = explode( ':', $key, 2 );
				$index[ $key ] = array(
					'season_number'  => $season_number,
					'episode_number' => $episode_number,
					'tmdb_id'        => absint( $episode['tmdb_id'] ?? 0 ),
					'name'           => (string) ( $episode['name'] ?? '' ),
					'action'         => $actions[ $key ] ?? '',
				);
			}
		}
		return $index;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function index_scan_episodes( array $scan, array &$errors ) {
		$index = array();
		foreach ( self::list_value( $scan['episodes'] ?? array() ) as $episode ) {
			if ( ! is_array( $episode ) ) {
				continue;
			}
			$key = self::episode_key( $episode['season_number'] ?? null, $episode['episode_number'] ?? null );
			if ( null === $key ) {
				$errors[] = self::issue( 'series_automation_invalid_scan_episode_identity', __( 'Series scan returned an invalid episode identity.', 'movies-wp' ) );
				continue;
			}
			if ( isset( $index[ $key ] ) ) {
				$errors[] = self::issue( 'series_automation_ambiguous_scan_episode', __( 'Series scan returned duplicate groups for the same season/episode identity.', 'movies-wp' ) );
				continue;
			}
			list( $season_number, $episode_number ) = explode( ':', $key, 2 );
			$episode['season_number']  = $season_number;
			$episode['episode_number'] = $episode_number;
			$index[ $key ] = $episode;
		}
		return $index;
	}

	private static function episode_key( $season, $episode ) {
		$season_number  = Movies_WP_Series_Media_Preview_Service::canonical_season_string( $season );
		$episode_number = Movies_WP_Series_Media_Preview_Service::canonical_episode_string( $episode );
		return null === $season_number || null === $episode_number ? null : $season_number . ':' . $episode_number;
	}

	private static function result( array $metadata, $media, $extra_error = null ) {
		$media_result = is_array( $media ) ? $media : null;
		$errors       = array_merge(
			self::list_value( $metadata['errors'] ?? array() ),
			self::list_value( is_array( $media_result ) ? ( $media_result['errors'] ?? array() ) : array() )
		);
		if ( is_array( $extra_error ) ) {
			$errors[] = $extra_error;
		}
		$warnings = array_merge(
			self::list_value( $metadata['warnings'] ?? array() ),
			self::list_value( is_array( $media_result ) ? ( $media_result['warnings'] ?? array() ) : array() )
		);
		$metadata_ok = ! empty( $metadata['ok'] );
		$media_ok    = is_array( $media_result ) && ! empty( $media_result['ok'] );
		$ok          = $metadata_ok && $media_ok && null === $extra_error;

		return array(
			'ok'             => $ok,
			'partial'        => ! $ok && ( $metadata_ok || ! empty( $metadata['partial'] ) ),
			'type'           => 'series_automation',
			'series_id'      => isset( $metadata['series_id'] ) ? $metadata['series_id'] : null,
			'action'         => $metadata['action'] ?? null,
			'completed'      => (int) ( is_array( $media_result ) ? ( $media_result['completed'] ?? 0 ) : 0 ),
			'metadata'       => $metadata,
			'media'          => $media_result,
			'series'         => is_array( $metadata['series'] ?? null ) ? $metadata['series'] : array(),
			'seasons'        => self::list_value( $metadata['seasons'] ?? array() ),
			'episodes'       => self::list_value( $metadata['episodes'] ?? array() ),
			'media_episodes' => self::list_value( is_array( $media_result ) ? ( $media_result['episodes'] ?? array() ) : array() ),
			'images'         => self::list_value( $metadata['images'] ?? array() ),
			'errors'         => array_values( $errors ),
			'warnings'       => array_values( $warnings ),
			'stages'         => array(
				'metadata' => $metadata_ok ? 'completed' : 'failed',
				'media'    => ! $metadata_ok ? 'skipped' : ( $media_ok ? 'completed' : 'failed' ),
			),
		);
	}

	private static function wp_error_issue( $error, $fallback ) {
		$code = (string) $error->get_error_code();
		return self::issue( '' !== $code ? $code : $fallback, $error->get_error_message() );
	}

	private static function append_issues( array &$target, $issues ) {
		foreach ( self::list_value( $issues ) as $issue ) {
			$target[] = $issue;
		}
	}

	private static function issue( $code, $message, $season = '', $episode = '' ) {
		return array(
			'code'           => (string) $code,
			'message'        => (string) $message,
			'season_number'  => (string) $season,
			'episode_number' => (string) $episode,
		);
	}

	private static function list_value( $value ) {
		return is_array( $value ) ? array_values( $value ) : array();
	}
}

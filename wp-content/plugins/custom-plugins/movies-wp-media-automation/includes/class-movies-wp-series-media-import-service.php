<?php
/**
 * Series media import service.
 *
 * Validates the server-generated Series media import plan and invokes the
 * episode media adapter exactly once.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Media_Import_Service {

	/**
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public static function execute( array $plan, array $options = array() ) {
		$error = self::validate_plan( $plan );
		if ( null !== $error ) {
			return self::failure( $error, $plan );
		}

		$adapter = isset( $options['adapter_apply'] ) && is_callable( $options['adapter_apply'] )
			? call_user_func( $options['adapter_apply'], $plan )
			: Movies_WP_Streamit_Episode_Media_Adapter::apply( $plan, $options );

		if ( ! is_array( $adapter ) ) {
			return self::failure(
				array(
					'code'    => 'series_media_import_service_invalid_adapter_result',
					'message' => __( 'Episode media adapter returned an invalid result.', 'movies-wp' ),
				),
				$plan
			);
		}

		return self::present_adapter_result( $adapter, $plan );
	}

	/**
	 * @return array{code:string,message:string,path?:string}|null
	 */
	private static function validate_plan( array $plan ) {
		if (
			true !== ( $plan['ok'] ?? null )
			|| true !== ( $plan['ready_to_import'] ?? null )
			|| 'series_media' !== ( $plan['type'] ?? null )
			|| 'series_media_import_plan' !== ( $plan['contract']['kind'] ?? null )
			|| 1 !== ( $plan['contract']['version'] ?? null )
		) {
			return self::invalid( 'contract', 'series_media_import_service_invalid_contract', __( 'Series media import plan contract is invalid or not ready.', 'movies-wp' ) );
		}

		if ( ! empty( $plan['errors'] ) ) {
			return self::invalid( 'errors', 'series_media_import_service_plan_has_errors', __( 'Series media import plan contains errors.', 'movies-wp' ) );
		}

		$identity = $plan['identity'] ?? null;
		if ( ! is_array( $identity ) || absint( $identity['tvshow_id'] ?? 0 ) <= 0 ) {
			return self::invalid( 'identity.tvshow_id', 'series_media_import_service_invalid_identity', __( 'Series media identity is invalid.', 'movies-wp' ) );
		}

		$directory = Movies_WP_Series_Media_Api_Client::normalize_directory( (string) ( $identity['series_directory'] ?? '' ) );
		if ( is_wp_error( $directory ) ) {
			return self::invalid( 'identity.series_directory', 'series_media_import_service_invalid_path', __( 'Series directory is invalid.', 'movies-wp' ) );
		}

		$allowed = array( '_sources', '_subtitles' );
		foreach ( self::list_value( $plan['episodes'] ?? array() ) as $index => $episode ) {
			if ( ! is_array( $episode ) ) {
				continue;
			}
			$season = Movies_WP_Series_Media_Preview_Service::canonical_season_string( $episode['season_number'] ?? null );
			if ( null === $season ) {
				return self::invalid( 'episodes.' . $index . '.season_number', 'series_media_import_service_invalid_season_number', __( 'Season number must be a canonical digit string.', 'movies-wp' ) );
			}
			$episode_no = Movies_WP_Series_Media_Preview_Service::canonical_episode_string( $episode['episode_number'] ?? null );
			if ( null === $episode_no ) {
				return self::invalid( 'episodes.' . $index . '.episode_number', 'series_media_import_service_invalid_episode_number', __( 'Episode number is invalid.', 'movies-wp' ) );
			}
			$operations = $episode['operations'] ?? array();
			if ( ! is_array( $operations ) ) {
				return self::invalid( 'episodes.' . $index . '.operations', 'series_media_import_service_invalid_operations', __( 'Episode operations are invalid.', 'movies-wp' ) );
			}
			foreach ( array_keys( $operations ) as $meta_key ) {
				if ( ! in_array( $meta_key, $allowed, true ) ) {
					return self::invalid( 'episodes.' . $index . '.operations.' . $meta_key, 'series_media_import_service_forbidden_meta_key', __( 'Only _sources and _subtitles may be planned.', 'movies-wp' ) );
				}
			}
			foreach ( $allowed as $meta_key ) {
				foreach ( self::list_value( $operations[ $meta_key ] ?? array() ) as $op_index => $operation ) {
					if ( ! is_array( $operation ) ) {
						continue;
					}
					$path = Movies_WP_Series_Media_Import_Plan::normalize_series_path( (string) ( $operation['path'] ?? '' ) );
					if ( is_wp_error( $path ) ) {
						return self::invalid(
							'episodes.' . $index . '.operations.' . $meta_key . '.' . $op_index . '.path',
							'series_media_import_service_invalid_path',
							$path->get_error_message()
						);
					}
				}
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $adapter
	 * @param array<string, mixed> $plan
	 * @return array<string, mixed>
	 */
	private static function present_adapter_result( array $adapter, array $plan ) {
		return array(
			'ok'        => ! empty( $adapter['ok'] ),
			'partial'   => ! empty( $adapter['partial'] ),
			'tvshow_id' => absint( $plan['identity']['tvshow_id'] ?? 0 ),
			'completed' => (int) ( $adapter['completed'] ?? 0 ),
			'episodes'  => self::list_value( $adapter['episodes'] ?? array() ),
			'errors'    => self::list_value( $adapter['errors'] ?? array() ),
			'warnings'  => array_merge(
				self::list_value( $plan['warnings'] ?? array() ),
				self::list_value( $adapter['warnings'] ?? array() )
			),
		);
	}

	/**
	 * @param array<string, mixed> $plan
	 * @return array<string, mixed>
	 */
	private static function failure( array $error, array $plan ) {
		return array(
			'ok'        => false,
			'partial'   => false,
			'tvshow_id' => absint( $plan['identity']['tvshow_id'] ?? 0 ),
			'completed' => 0,
			'episodes'  => array(),
			'errors'    => array( $error ),
			'warnings'  => self::list_value( $plan['warnings'] ?? array() ),
		);
	}

	private static function invalid( $path, $code, $message ) {
		return array(
			'code'    => (string) $code,
			'message' => (string) $message,
			'path'    => (string) $path,
		);
	}

	/**
	 * @return list<mixed>
	 */
	private static function list_value( $value ) {
		return is_array( $value ) ? array_values( $value ) : array();
	}
}

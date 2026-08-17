<?php
/**
 * Series Import Service.
 *
 * Validates the approved Series Import Plan contract, invokes the Streamit TV
 * adapter exactly once, and normalizes its result for a future admin layer.
 * Identity belongs to the plan; persistence belongs to the adapter.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Import_Service {

	/**
	 * Execute an approved, read-only Series Import Plan.
	 *
	 * @param array<string, mixed> $plan    Series Import Plan.
	 * @param array{
	 *   adapter_apply?: callable(array): mixed
	 * } $options Deterministic test hooks.
	 * @return array<string, mixed>
	 */
	public static function execute( array $plan, array $options = array() ) {
		$warnings = self::list_value( $plan['warnings'] ?? array() );
		$action   = is_array( $plan['identity'] ?? null )
			? (string) ( $plan['identity']['action'] ?? '' )
			: '';
		$error    = self::validate_plan( $plan );

		if ( null !== $error ) {
			return self::failure( $error, $action, $warnings );
		}

		$adapter = isset( $options['adapter_apply'] ) && is_callable( $options['adapter_apply'] )
			? call_user_func( $options['adapter_apply'], $plan )
			: Movies_WP_Streamit_TV_Adapter::apply( $plan );

		if ( ! is_array( $adapter ) ) {
			return self::failure(
				self::invalid(
					'adapter',
					'series_import_service_invalid_adapter_result',
					'Series TV adapter returned an invalid result.'
				),
				$action,
				$warnings
			);
		}

		return self::normalize_adapter_result( $adapter, $action, $warnings );
	}

	/**
	 * Validate contract shape only. This deliberately does not rediscover or
	 * reinterpret any identity chosen by the Series Import Plan.
	 *
	 * @return array{code:string,message:string,path:string}|null
	 */
	private static function validate_plan( array $plan ) {
		if (
			true !== ( $plan['ok'] ?? null )
			|| true !== ( $plan['ready_to_import'] ?? null )
			|| 'series' !== ( $plan['type'] ?? null )
			|| 'series_import_plan' !== ( $plan['contract']['kind'] ?? null )
			|| 1 !== ( $plan['contract']['version'] ?? null )
			|| true !== ( $plan['contract']['read_only'] ?? null )
		) {
			return self::invalid( 'contract', 'series_import_service_invalid_contract', 'Series Import Plan contract is invalid or not ready.' );
		}
		if ( ! is_array( $plan['errors'] ?? null ) || array() !== $plan['errors'] ) {
			return self::invalid( 'errors', 'series_import_service_plan_has_errors', 'Series Import Plan contains errors.' );
		}

		$identity = $plan['identity'] ?? null;
		if ( ! is_array( $identity ) ) {
			return self::invalid( 'identity', 'series_import_service_invalid_identity', 'Series identity is missing.' );
		}
		$action = $identity['action'] ?? null;
		if ( ! in_array( $action, array( 'create', 'update' ), true ) || '_tmdb_id' !== ( $identity['match_by'] ?? null ) ) {
			return self::invalid( 'identity.action', 'series_import_service_invalid_identity', 'Series identity action or match key is invalid.' );
		}
		if ( 'update' === $action && absint( $identity['existing_series_id'] ?? 0 ) <= 0 ) {
			return self::invalid( 'identity.existing_series_id', 'series_import_service_missing_series_id', 'Series update requires an existing Series ID.' );
		}
		if ( 'create' === $action && null !== ( $identity['existing_series_id'] ?? null ) ) {
			return self::invalid( 'identity.existing_series_id', 'series_import_service_invalid_identity', 'Series create must not contain an existing Series ID.' );
		}

		$series = $plan['series'] ?? null;
		if (
			! is_array( $series )
			|| absint( $series['tmdb_id'] ?? 0 ) <= 0
			|| '' === trim( (string) ( $series['title'] ?? '' ) )
			|| ! array_key_exists( 'summary', $series )
		) {
			return self::invalid( 'series', 'series_import_service_invalid_series', 'Series persistence data is incomplete.' );
		}
		foreach ( array( 'origin_country', 'genres', 'cast', 'crew' ) as $key ) {
			if ( isset( $series[ $key ] ) && ! is_array( $series[ $key ] ) ) {
				return self::invalid( 'series.' . $key, 'series_import_service_invalid_series', 'Series enrichment data has an invalid type.' );
			}
		}

		if ( ! is_array( $plan['images'] ?? null ) ) {
			return self::invalid( 'images', 'series_import_service_invalid_images', 'Series image actions are missing.' );
		}
		$error = self::validate_image( $plan['images']['poster'] ?? null, 'poster', '_portrait_thumbmail', 'images.poster' );
		if ( null !== $error ) {
			return $error;
		}
		$error = self::validate_image( $plan['images']['backdrop'] ?? null, 'backdrop', 'thumbnail_id', 'images.backdrop' );
		if ( null !== $error ) {
			return $error;
		}

		$sources = $plan['sources_policy'] ?? null;
		if (
			! is_array( $sources )
			|| '_sources' !== ( $sources['episode_meta_key'] ?? null )
			|| false !== ( $sources['mutate'] ?? null )
			|| ! is_array( $sources['actions'] ?? null )
			|| array() !== $sources['actions']
		) {
			return self::invalid( 'sources_policy', 'series_import_service_invalid_sources_policy', 'Series source policy must explicitly forbid all source mutations.' );
		}

		if ( ! is_array( $plan['seasons'] ?? null ) ) {
			return self::invalid( 'seasons', 'series_import_service_invalid_seasons', 'Series season operations are missing.' );
		}
		foreach ( $plan['seasons'] as $season_index => $season ) {
			$error = self::validate_season( $season, (int) $season_index );
			if ( null !== $error ) {
				return $error;
			}
		}

		return null;
	}

	/**
	 * @param mixed $season
	 * @return array{code:string,message:string,path:string}|null
	 */
	private static function validate_season( $season, $index ) {
		$path = 'seasons.' . $index;
		if ( ! is_array( $season ) ) {
			return self::invalid( $path, 'series_import_service_invalid_season', 'Season operation must be an array.' );
		}
		if ( ! in_array( $season['action'] ?? null, array( 'create', 'update' ), true ) ) {
			return self::invalid( $path . '.action', 'series_import_service_invalid_season', 'Season action must be create or update.' );
		}
		$number = self::season_number( $season['season_number'] ?? null );
		if ( null === $number || $number !== ( $season['season_number'] ?? null ) ) {
			return self::invalid( $path . '.season_number', 'series_import_service_invalid_season_number', 'Season number must be a canonical digit string.' );
		}
		if (
			! array_key_exists( 'name', $season )
			|| ! array_key_exists( 'air_date', $season )
			|| ! array_key_exists( 'overview', $season )
			|| ! is_array( $season['existing_episode_ids'] ?? null )
			|| ! is_array( $season['unmatched_existing_episode_ids'] ?? null )
			|| true !== ( $season['preserve_unmatched_episode_ids'] ?? null )
		) {
			return self::invalid( $path, 'series_import_service_invalid_season', 'Season persistence or preservation data is incomplete.' );
		}

		$error = self::validate_image( $season['image'] ?? null, 'season_poster', '_seasons.image_id', $path . '.image' );
		if ( null !== $error ) {
			return $error;
		}
		if ( ! is_array( $season['episodes'] ?? null ) ) {
			return self::invalid( $path . '.episodes', 'series_import_service_invalid_episodes', 'Season episode operations are missing.' );
		}
		foreach ( $season['episodes'] as $episode_index => $episode ) {
			$error = self::validate_episode( $episode, $number, $path . '.episodes.' . (int) $episode_index );
			if ( null !== $error ) {
				return $error;
			}
		}
		return null;
	}

	/**
	 * @param mixed $episode
	 * @return array{code:string,message:string,path:string}|null
	 */
	private static function validate_episode( $episode, $season_number, $path ) {
		if ( ! is_array( $episode ) ) {
			return self::invalid( $path, 'series_import_service_invalid_episode', 'Episode operation must be an array.' );
		}
		$action = $episode['action'] ?? null;
		if ( ! in_array( $action, array( 'create', 'update' ), true ) ) {
			return self::invalid( $path . '.action', 'series_import_service_invalid_episode', 'Episode action must be create or update.' );
		}
		if (
			self::season_number( $episode['season_number'] ?? null ) !== $season_number
			|| absint( $episode['tmdb_id'] ?? 0 ) <= 0
			|| ! is_int( $episode['episode_number'] ?? null )
			|| $episode['episode_number'] <= 0
			|| ! array_key_exists( 'name', $episode )
			|| ! array_key_exists( 'overview', $episode )
		) {
			return self::invalid( $path, 'series_import_service_invalid_episode_identity', 'Episode identity or persistence data is incomplete.' );
		}

		if ( 'update' === $action ) {
			if (
				absint( $episode['existing_episode_id'] ?? 0 ) <= 0
				|| ! in_array(
					$episode['match_by'] ?? null,
					array( 'tvshow_id+_tmdb_id', 'tvshow_id+_season_number+_episode_number' ),
					true
				)
			) {
				return self::invalid( $path . '.existing_episode_id', 'series_import_service_invalid_episode_identity', 'Episode update identity is incomplete.' );
			}
		} elseif ( null !== ( $episode['existing_episode_id'] ?? null ) || null !== ( $episode['match_by'] ?? null ) ) {
			return self::invalid( $path . '.existing_episode_id', 'series_import_service_invalid_episode_identity', 'Episode create must not contain an existing identity.' );
		}

		if ( 'keep_existing_untouched' !== ( $episode['sources_action'] ?? null ) ) {
			return self::invalid( $path . '.sources_action', 'series_import_service_invalid_sources_policy', 'Episode source action must preserve existing sources.' );
		}
		return self::validate_image( $episode['image'] ?? null, 'still', 'thumbnail_id', $path . '.image' );
	}

	/**
	 * @param mixed $image
	 * @return array{code:string,message:string,path:string}|null
	 */
	private static function validate_image( $image, $role, $target, $path ) {
		if (
			! is_array( $image )
			|| $role !== ( $image['role'] ?? null )
			|| $target !== ( $image['target'] ?? null )
			|| ! in_array( $image['action'] ?? null, array( 'set', 'keep_existing', 'skip_missing' ), true )
		) {
			return self::invalid( $path, 'series_import_service_invalid_image', 'Image role, action, or target is invalid.' );
		}
		if (
			'set' === $image['action']
			&& '' === trim( (string) ( $image['url'] ?? '' ) )
			&& '' === trim( (string) ( $image['path'] ?? '' ) )
		) {
			return self::invalid( $path, 'series_import_service_invalid_image', 'Set image action requires a source URL or path.' );
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $adapter
	 * @param list<mixed>          $plan_warnings
	 * @return array<string, mixed>
	 */
	private static function normalize_adapter_result( array $adapter, $action, array $plan_warnings ) {
		$adapter_warnings = self::list_value( $adapter['warnings'] ?? array() );
		return array(
			'ok'         => (bool) ( $adapter['ok'] ?? false ),
			'partial'    => (bool) ( $adapter['partial'] ?? false ),
			'type'       => 'series',
			'series_id'  => isset( $adapter['series_id'] ) && null !== $adapter['series_id']
				? (int) $adapter['series_id']
				: null,
			'action'     => (string) $action,
			'warnings'   => array_values( array_merge( $plan_warnings, $adapter_warnings ) ),
			'errors'     => self::list_value( $adapter['errors'] ?? array() ),
			'series'     => is_array( $adapter['series'] ?? null ) ? $adapter['series'] : array(),
			'seasons'    => self::list_value( $adapter['seasons'] ?? array() ),
			'episodes'   => self::list_value( $adapter['episodes'] ?? array() ),
			'images'     => self::list_value( $adapter['images'] ?? array() ),
			'adapter'    => $adapter,
		);
	}

	/**
	 * @param array{code:string,message:string,path:string} $error
	 * @param list<mixed>                                  $warnings
	 * @return array<string, mixed>
	 */
	private static function failure( array $error, $action, array $warnings ) {
		return array(
			'ok'        => false,
			'partial'   => false,
			'type'      => 'series',
			'series_id' => null,
			'action'    => in_array( $action, array( 'create', 'update' ), true ) ? $action : null,
			'warnings'  => array_values( $warnings ),
			'errors'    => array( $error ),
			'series'    => array(),
			'seasons'   => array(),
			'episodes'  => array(),
			'images'    => array(),
			'adapter'   => null,
		);
	}

	/**
	 * @return array{code:string,message:string,path:string}
	 */
	private static function invalid( $path, $code, $message ) {
		return array(
			'code'    => (string) $code,
			'message' => (string) $message,
			'path'    => (string) $path,
		);
	}

	/**
	 * @param mixed $value
	 * @return list<mixed>
	 */
	private static function list_value( $value ) {
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/**
	 * Preserve Season "0" with explicit canonical string checks.
	 *
	 * @param mixed $value
	 * @return string|null
	 */
	private static function season_number( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d+$/', $value ) ) {
			return null;
		}
		return (string) (int) $value;
	}
}

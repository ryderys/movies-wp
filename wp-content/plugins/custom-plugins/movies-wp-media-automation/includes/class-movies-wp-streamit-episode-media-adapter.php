<?php
/**
 * Episode media persistence adapter for Series media automation.
 *
 * The only component allowed to mutate episode _sources and _subtitles.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-movies-wp-series-import-profiler.php';

class Movies_WP_Streamit_Episode_Media_Adapter {

	/**
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public static function apply( array $plan, array $options = array() ) {
		$now_local = isset( $options['now_local'] ) ? (string) $options['now_local'] : current_time( 'mysql' );
		$results   = array();
		$errors    = array();
		$warnings  = self::list_value( $plan['warnings'] ?? array() );
		$partial   = false;
		$completed = 0;

		foreach ( self::list_value( $plan['episodes'] ?? array() ) as $episode_plan ) {
			if ( ! is_array( $episode_plan ) ) {
				continue;
			}
			$episode_snap = Movies_WP_Series_Import_Profiler::phase_start( 'episode_media' );
			$result       = self::apply_episode( $episode_plan, $now_local, $options );
			Movies_WP_Series_Import_Profiler::phase_end(
				'episode_media',
				$episode_snap,
				1,
				sprintf( 'S%sE%s', (string) ( $episode_plan['season_number'] ?? '' ), (string) ( $episode_plan['episode_number'] ?? '' ) )
			);
			$results[] = $result;
			if ( ! empty( $result['ok'] ) ) {
				++$completed;
			} else {
				$partial = true;
				if ( ! empty( $result['error'] ) && is_array( $result['error'] ) ) {
					$errors[] = $result['error'];
				}
			}
			if ( ! empty( $result['warnings'] ) && is_array( $result['warnings'] ) ) {
				$warnings = array_merge( $warnings, $result['warnings'] );
			}
			if ( isset( $options['lease_heartbeat'] ) && is_callable( $options['lease_heartbeat'] ) ) {
				if ( ! call_user_func( $options['lease_heartbeat'] ) ) {
					$partial  = true;
					$errors[] = array(
						'code'    => 'series_import_job_busy',
						'message' => __( 'This Series import job is already running.', 'movies-wp' ),
					);
					break;
				}
			}
		}

		return array(
			'ok'        => ! $partial,
			'partial'   => $partial,
			'completed' => $completed,
			'episodes'  => $results,
			'errors'    => $errors,
			'warnings'  => $warnings,
		);
	}

	/**
	 * @param array<string, mixed> $episode_plan
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function apply_episode( array $episode_plan, string $now_local, array $options ) {
		$episode_id = absint( $episode_plan['episode_id'] ?? 0 );
		$tvshow_id  = absint( $episode_plan['tvshow_id'] ?? 0 );
		$season     = Movies_WP_Series_Media_Preview_Service::canonical_season_string( $episode_plan['season_number'] ?? null );
		$episode_no = Movies_WP_Series_Media_Preview_Service::canonical_episode_string( $episode_plan['episode_number'] ?? null );

		if ( $episode_id <= 0 || $tvshow_id <= 0 || null === $season || null === $episode_no ) {
			return self::episode_fail( $episode_id, 'episode_media_invalid_plan', __( 'Episode media plan is incomplete.', 'movies-wp' ) );
		}

		$ownership = self::verify_ownership( $episode_id, $tvshow_id, $season, $episode_no, $episode_plan, $options );
		if ( is_wp_error( $ownership ) ) {
			return self::episode_fail( $episode_id, $ownership->get_error_code(), $ownership->get_error_message() );
		}

		$operations = isset( $episode_plan['operations'] ) && is_array( $episode_plan['operations'] )
			? $episode_plan['operations']
			: array();

		$source_snap   = Movies_WP_Series_Import_Profiler::phase_start( 'episode_sources_write' );
		$source_result = self::apply_sources(
			$episode_id,
			self::list_value( $operations['_sources'] ?? array() ),
			$now_local,
			$options
		);
		Movies_WP_Series_Import_Profiler::phase_end(
			'episode_sources_write',
			$source_snap,
			count( self::list_value( $operations['_sources'] ?? array() ) ),
			(string) $episode_id
		);
		$subtitle_snap   = Movies_WP_Series_Import_Profiler::phase_start( 'episode_subtitles_write' );
		$subtitle_result = self::apply_subtitles(
			$episode_id,
			self::list_value( $operations['_subtitles'] ?? array() ),
			$options
		);
		Movies_WP_Series_Import_Profiler::phase_end(
			'episode_subtitles_write',
			$subtitle_snap,
			count( self::list_value( $operations['_subtitles'] ?? array() ) ),
			(string) $episode_id
		);

		$ok      = ! empty( $source_result['ok'] ) && ! empty( $subtitle_result['ok'] );
		$partial = ( ! empty( $source_result['ok'] ) xor ! empty( $subtitle_result['ok'] ) ) || ( ! $ok && ( ! empty( $source_result['written'] ) || ! empty( $subtitle_result['written'] ) ) );

		return array(
			'ok'          => $ok,
			'partial'     => $partial,
			'episode_id'  => $episode_id,
			'tvshow_id'   => $tvshow_id,
			'season_number'  => $season,
			'episode_number' => $episode_no,
			'sources'     => $source_result,
			'subtitles'   => $subtitle_result,
			'error'       => $ok ? null : array(
				'code'    => 'episode_media_partial_failure',
				'message' => __( 'Episode media import completed with failures.', 'movies-wp' ),
			),
			'warnings'    => array_merge(
				self::list_value( $source_result['warnings'] ?? array() ),
				self::list_value( $subtitle_result['warnings'] ?? array() )
			),
		);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return true|WP_Error
	 */
	private static function verify_ownership( $episode_id, $tvshow_id, $season, $episode_no, array $episode_plan, array $options ) {
		$episode = self::get_episode( $episode_id, $options );
		if ( empty( $episode ) ) {
			return new WP_Error( 'episode_media_not_found', __( 'Episode was not found.', 'movies-wp' ) );
		}

		$actual_tvshow = absint( self::get_episode_meta( $episode_id, 'tvshow_id', $options ) );
		$actual_season = Movies_WP_Series_Media_Preview_Service::canonical_season_string(
			self::get_episode_meta( $episode_id, '_season_number', $options )
		);
		$actual_episode = Movies_WP_Series_Media_Preview_Service::canonical_episode_string(
			self::get_episode_meta( $episode_id, '_episode_number', $options )
		);

		if ( $actual_tvshow !== $tvshow_id || $actual_season !== $season || $actual_episode !== $episode_no ) {
			return new WP_Error(
				'episode_ownership_conflict',
				sprintf(
					__( 'Episode %1$d ownership mismatch. Expected tvshow %2$d S%3$sE%4$s.', 'movies-wp' ),
					$episode_id,
					$tvshow_id,
					$season,
					$episode_no
				)
			);
		}

		$expected_tmdb = absint( $episode_plan['ownership']['tmdb_id'] ?? $episode_plan['tmdb_id'] ?? 0 );
		if ( $expected_tmdb > 0 ) {
			$actual_tmdb = absint( self::get_episode_meta( $episode_id, '_tmdb_id', $options ) );
			if ( $actual_tmdb > 0 && $actual_tmdb !== $expected_tmdb ) {
				return new WP_Error( 'episode_ownership_conflict', __( 'Episode TMDb ownership snapshot mismatch.', 'movies-wp' ) );
			}
		}

		return true;
	}

	/**
	 * @param list<array<string,mixed>> $operations
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function apply_sources( $episode_id, array $operations, string $now_local, array $options ) {
		$existing = self::normalize_source_rows( self::get_episode_meta( $episode_id, '_sources', $options ) );
		$merged   = self::merge_source_rows( $existing, $operations, $now_local );
		if ( is_wp_error( $merged ) ) {
			return array(
				'ok'       => false,
				'written'  => false,
				'warnings' => array(),
				'error'    => array(
					'code'    => $merged->get_error_code(),
					'message' => $merged->get_error_message(),
				),
			);
		}

		$write = self::update_episode_meta( $episode_id, '_sources', $merged['rows'], $options );
		$readback = self::normalize_source_rows( self::get_episode_meta( $episode_id, '_sources', $options ) );

		return array(
			'ok'       => $write && self::rows_equal( $merged['rows'], $readback ),
			'written'  => $write,
			'count'    => count( $merged['rows'] ),
			'warnings' => $merged['warnings'],
			'readback' => $readback,
		);
	}

	/**
	 * @param list<array<string,mixed>> $operations
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function apply_subtitles( $episode_id, array $operations, array $options ) {
		$existing = self::normalize_subtitle_rows( self::get_episode_meta( $episode_id, '_subtitles', $options ) );
		$merged   = self::merge_subtitle_rows( $existing, $operations );
		if ( is_wp_error( $merged ) ) {
			return array(
				'ok'       => false,
				'written'  => false,
				'warnings' => array(),
				'error'    => array(
					'code'    => $merged->get_error_code(),
					'message' => $merged->get_error_message(),
				),
			);
		}

		$write = self::update_episode_meta( $episode_id, '_subtitles', $merged['rows'], $options );
		$readback = self::normalize_subtitle_rows( self::get_episode_meta( $episode_id, '_subtitles', $options ) );

		return array(
			'ok'       => $write && self::rows_equal( $merged['rows'], $readback ),
			'written'  => $write,
			'count'    => count( $merged['rows'] ),
			'warnings' => $merged['warnings'],
			'readback' => $readback,
		);
	}

	/**
	 * @param list<array<string,mixed>> $existing
	 * @param list<array<string,mixed>> $operations
	 * @return array{rows:list<array>,warnings:list<array>}|WP_Error
	 */
	private static function merge_source_rows( array $existing, array $operations, string $now_local ) {
		$warnings = array();
		$rows     = array();
		$index    = array();

		foreach ( $existing as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$path = self::source_path( $row );
			if ( $path === '' ) {
				$rows[] = $row;
				continue;
			}
			if ( isset( $index[ $path ] ) ) {
				return new WP_Error( 'duplicate_existing_source_path', __( 'Existing _sources contains duplicate normalized paths.', 'movies-wp' ) );
			}
			$index[ $path ] = count( $rows );
			$rows[] = $row;
		}

		foreach ( $operations as $operation ) {
			if ( ! is_array( $operation ) ) {
				continue;
			}
			$action = (string) ( $operation['action'] ?? '' );
			$path   = Movies_WP_Series_Media_Import_Plan::normalize_series_path( (string) ( $operation['path'] ?? '' ) );
			if ( is_wp_error( $path ) ) {
				return $path;
			}

			if ( 'preserve' === $action ) {
				if ( ! isset( $index[ $path ] ) ) {
					$row = isset( $operation['row'] ) && is_array( $operation['row'] ) ? $operation['row'] : array();
					$index[ $path ] = count( $rows );
					$rows[] = $row;
				}
				continue;
			}

			$new_row = isset( $operation['row'] ) && is_array( $operation['row'] ) ? $operation['row'] : array();
			if ( isset( $new_row['date_added'] ) && '{{import_date}}' === $new_row['date_added'] ) {
				$new_row['date_added'] = $now_local;
			}

			Movies_WP_Series_Import_Profiler::mark_source( $path );

			if ( isset( $index[ $path ] ) ) {
				$rows[ $index[ $path ] ] = self::merge_preserving_unknown( $rows[ $index[ $path ] ], $new_row );
			} else {
				$index[ $path ] = count( $rows );
				$rows[] = $new_row;
			}
		}

		return array(
			'rows'     => $rows,
			'warnings' => $warnings,
		);
	}

	/**
	 * @param list<array<string,mixed>> $existing
	 * @param list<array<string,mixed>> $operations
	 * @return array{rows:list<array>,warnings:list<array>}|WP_Error
	 */
	private static function merge_subtitle_rows( array $existing, array $operations ) {
		$rows  = array();
		$index = array();

		foreach ( $existing as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$path = self::subtitle_path( $row );
			if ( $path === '' ) {
				$rows[] = $row;
				continue;
			}
			$index[ $path ] = count( $rows );
			$rows[] = $row;
		}

		foreach ( $operations as $operation ) {
			if ( ! is_array( $operation ) ) {
				continue;
			}
			$action = (string) ( $operation['action'] ?? '' );
			$path   = Movies_WP_Series_Media_Import_Plan::normalize_series_path( (string) ( $operation['path'] ?? '' ) );
			if ( is_wp_error( $path ) ) {
				return $path;
			}

			if ( 'preserve' === $action ) {
				if ( ! isset( $index[ $path ] ) ) {
					$row = isset( $operation['row'] ) && is_array( $operation['row'] ) ? $operation['row'] : array();
					$index[ $path ] = count( $rows );
					$rows[] = $row;
				}
				continue;
			}

			Movies_WP_Series_Import_Profiler::mark_subtitle( $path );

			$new_row = isset( $operation['row'] ) && is_array( $operation['row'] ) ? $operation['row'] : array();
			if ( isset( $index[ $path ] ) ) {
				$merged = self::merge_preserving_unknown( $rows[ $index[ $path ] ], $new_row );
				if ( ! empty( $rows[ $index[ $path ] ]['default'] ) ) {
					$merged['default'] = $rows[ $index[ $path ] ]['default'];
				}
				$rows[ $index[ $path ] ] = $merged;
			} else {
				$index[ $path ] = count( $rows );
				$rows[] = $new_row;
			}
		}

		return array(
			'rows'     => $rows,
			'warnings' => array(),
		);
	}

	private static function merge_preserving_unknown( array $existing, array $incoming ) {
		$merged = $existing;
		foreach ( $incoming as $key => $value ) {
			if ( $value === '' && array_key_exists( $key, $existing ) && $existing[ $key ] !== '' ) {
				continue;
			}
			$merged[ $key ] = $value;
		}
		return $merged;
	}

	private static function source_path( array $row ) {
		$path = (string) ( $row['link'] ?? '' );
		if ( $path === '' ) {
			$path = (string) ( $row['download_content'] ?? '' );
		}
		$normalized = Movies_WP_Series_Media_Import_Plan::normalize_series_path( $path );
		return is_wp_error( $normalized ) ? '' : $normalized;
	}

	private static function subtitle_path( array $row ) {
		$normalized = Movies_WP_Series_Media_Import_Plan::normalize_series_path( (string) ( $row['url'] ?? '' ) );
		return is_wp_error( $normalized ) ? '' : $normalized;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private static function normalize_source_rows( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = maybe_unserialize( $raw );
		}
		return is_array( $raw ) ? array_values( $raw ) : array();
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private static function normalize_subtitle_rows( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = maybe_unserialize( $raw );
		}
		return is_array( $raw ) ? array_values( $raw ) : array();
	}

	private static function rows_equal( array $expected, array $actual ) {
		return $expected === $actual || maybe_serialize( $expected ) === maybe_serialize( $actual );
	}

	private static function get_episode( $episode_id, array $options ) {
		if ( isset( $options['get_episode'] ) && is_callable( $options['get_episode'] ) ) {
			return call_user_func( $options['get_episode'], (int) $episode_id );
		}
		return function_exists( 'streamit_get_episode' ) ? streamit_get_episode( (int) $episode_id ) : null;
	}

	private static function get_episode_meta( $episode_id, $key, array $options ) {
		if ( isset( $options['get_episode_meta'] ) && is_callable( $options['get_episode_meta'] ) ) {
			return call_user_func( $options['get_episode_meta'], (int) $episode_id, (string) $key );
		}
		return function_exists( 'streamit_get_episode_meta' )
			? streamit_get_episode_meta( (int) $episode_id, (string) $key, true )
			: null;
	}

	private static function update_episode_meta( $episode_id, $key, $value, array $options ) {
		if ( isset( $options['update_episode_meta'] ) && is_callable( $options['update_episode_meta'] ) ) {
			return (bool) call_user_func( $options['update_episode_meta'], (int) $episode_id, (string) $key, $value );
		}
		if ( ! function_exists( 'streamit_update_episode_meta' ) ) {
			return false;
		}
		$result = streamit_update_episode_meta( (int) $episode_id, (string) $key, $value );
		if ( false === $result ) {
			$readback = streamit_get_episode_meta( (int) $episode_id, (string) $key, true );
			return maybe_serialize( $readback ) === maybe_serialize( $value );
		}
		return true;
	}

	private static function episode_fail( $episode_id, $code, $message ) {
		return array(
			'ok'         => false,
			'partial'    => false,
			'episode_id' => (int) $episode_id,
			'error'      => array(
				'code'    => (string) $code,
				'message' => (string) $message,
			),
		);
	}

	/**
	 * @return list<mixed>
	 */
	private static function list_value( $value ) {
		return is_array( $value ) ? array_values( $value ) : array();
	}
}

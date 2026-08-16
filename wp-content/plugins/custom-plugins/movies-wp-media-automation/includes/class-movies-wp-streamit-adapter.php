<?php
/**
 * Streamit Adapter — executes an approved Import Plan against Streamit.
 *
 * Import Plan decides. This adapter persists. It must not rescan, reparse,
 * re-probe, re-associate, or recompute ready_to_import.
 *
 * Never call streamit_update_movie() (wp_parse_args defaults are unsafe).
 * Subtitle rows store relative Movie/... paths only; never mint signed URLs here.
 * Never add media_path to _source rows.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Streamit_Adapter {

	/**
	 * Apply an Import Plan. Read decisions from the plan only.
	 *
	 * @param array<string, mixed> $plan Import Plan from Movies_WP_Media_Import_Plan::build().
	 * @param array{
	 *   create_from_tmdb?: callable(int,array): mixed,
	 *   get_movie?: callable(int): mixed,
	 *   update_movie_row?: callable(int,array): mixed,
	 *   get_sources?: callable(int): mixed,
	 *   update_sources?: callable(int,array): mixed,
	 *   update_meta?: callable(int,string,mixed): mixed,
	 *   today?: string
	 * } $options Test hooks. Production uses Streamit APIs.
	 * @return array<string, mixed>
	 */
	public static function apply( array $plan, array $options = array() ) {
		$gate = self::validate_plan( $plan );
		if ( true !== $gate ) {
			return $gate;
		}

		$identity_action = (string) $plan['identity']['action'];
		$completed       = array();
		$movie_id        = null;

		// 1) Identity: create or update.
		if ( 'create' === $identity_action ) {
			$created = self::step_create( $plan, $options );
			if ( empty( $created['ok'] ) ) {
				return self::failure_result( null, $identity_action, $completed, 'movie', $created['error'] );
			}
			$movie_id     = (int) $created['movie_id'];
			$completed[]  = 'movie';
		} else {
			$movie_id = (int) $plan['identity']['existing_movie_id'];
			if ( $movie_id <= 0 ) {
				return self::failure_result(
					null,
					$identity_action,
					$completed,
					'movie',
					self::err( 'media_adapter_missing_movie_id', __( 'Update plan is missing existing_movie_id.', 'movies-wp' ) )
				);
			}
			$completed[] = 'movie';
		}

		// 2) Title / summary overlay (load-merge-write).
		$meta = self::step_metadata( $movie_id, $plan, $options );
		if ( empty( $meta['ok'] ) ) {
			return self::failure_result( $movie_id, $identity_action, $completed, 'metadata', $meta['error'] );
		}
		$completed[] = 'metadata';

		// 3) Source merge.
		$sources = self::step_sources( $movie_id, $plan, $options );
		if ( empty( $sources['ok'] ) ) {
			return self::failure_result( $movie_id, $identity_action, $completed, 'sources', $sources['error'] );
		}
		$completed[] = 'sources';

		// 4) Default Streamit player meta from first merged video link.
		$stream = self::step_default_stream( $movie_id, isset( $sources['merged'] ) && is_array( $sources['merged'] ) ? $sources['merged'] : array(), $options );
		if ( empty( $stream['ok'] ) ) {
			return self::failure_result( $movie_id, $identity_action, $completed, 'default_stream', $stream['error'] );
		}
		if ( empty( $stream['skipped'] ) ) {
			$completed[] = 'default_stream';
		}

		// 5) Media directory meta.
		$dir = self::step_media_directory( $movie_id, $plan, $options );
		if ( empty( $dir['ok'] ) ) {
			return self::failure_result( $movie_id, $identity_action, $completed, 'media_directory', $dir['error'] );
		}
		$completed[] = 'media_directory';

		// 6) Subtitles — relative paths only (render-time signing elsewhere).
		$subs = self::step_subtitles( $movie_id, $plan, $options );
		if ( empty( $subs['ok'] ) ) {
			return self::failure_result( $movie_id, $identity_action, $completed, 'subtitles', $subs['error'] );
		}
		$completed[] = 'subtitles';

		return array(
			'ok'              => true,
			'movie_id'        => $movie_id,
			'identity_action' => $identity_action,
			'completed'       => $completed,
			'deferred'        => array(),
			'source_stats'    => isset( $sources['stats'] ) ? $sources['stats'] : array(),
			'subtitle_stats'  => isset( $subs['stats'] ) ? $subs['stats'] : array(),
			'warnings'        => array(),
		);
	}

	/**
	 * @param array<string, mixed> $plan
	 * @return true|array<string, mixed>
	 */
	private static function validate_plan( array $plan ) {
		if ( empty( $plan['ok'] ) ) {
			return self::failure_result( null, null, array(), 'validate', self::err( 'media_adapter_invalid_plan', __( 'Plan ok flag is missing or false.', 'movies-wp' ) ) );
		}
		if ( empty( $plan['ready_to_import'] ) ) {
			return self::failure_result( null, null, array(), 'validate', self::err( 'media_adapter_not_ready', __( 'Plan ready_to_import is false.', 'movies-wp' ) ) );
		}
		if ( ! empty( $plan['errors'] ) && is_array( $plan['errors'] ) && $plan['errors'] !== array() ) {
			return self::failure_result( null, null, array(), 'validate', self::err( 'media_adapter_plan_has_errors', __( 'Plan contains errors and must not be applied.', 'movies-wp' ) ) );
		}
		if ( empty( $plan['contract']['kind'] ) || 'import_plan' !== $plan['contract']['kind'] ) {
			return self::failure_result( null, null, array(), 'validate', self::err( 'media_adapter_bad_contract', __( 'Plan is missing the import_plan contract.', 'movies-wp' ) ) );
		}
		if ( empty( $plan['identity']['action'] ) || ! in_array( $plan['identity']['action'], array( 'create', 'update' ), true ) ) {
			return self::failure_result( null, null, array(), 'validate', self::err( 'media_adapter_bad_identity', __( 'Plan identity.action must be create or update.', 'movies-wp' ) ) );
		}
		if ( empty( $plan['metadata']['title'] ) || ! is_string( $plan['metadata']['title'] ) ) {
			return self::failure_result( null, null, array(), 'validate', self::err( 'media_adapter_missing_title', __( 'Plan metadata.title is required.', 'movies-wp' ) ) );
		}
		if ( ! isset( $plan['metadata']['summary'] ) || ! is_string( $plan['metadata']['summary'] ) ) {
			return self::failure_result( null, null, array(), 'validate', self::err( 'media_adapter_missing_summary', __( 'Plan metadata.summary is required.', 'movies-wp' ) ) );
		}
		if ( empty( $plan['movie']['media_directory'] ) || ! is_string( $plan['movie']['media_directory'] ) ) {
			return self::failure_result( null, null, array(), 'validate', self::err( 'media_adapter_missing_directory', __( 'Plan movie.media_directory is required.', 'movies-wp' ) ) );
		}
		// Relative-path subtitle persistence is required (signed URLs remain render-time only).
		if ( empty( $plan['subtitle_persistence'] ) || ! is_array( $plan['subtitle_persistence'] ) ) {
			return self::failure_result( null, null, array(), 'validate', self::err( 'media_adapter_subtitles_not_ready', __( 'Plan must declare subtitle_persistence with ready=true.', 'movies-wp' ) ) );
		}
		if ( empty( $plan['subtitle_persistence']['ready'] ) ) {
			return self::failure_result( null, null, array(), 'validate', self::err( 'media_adapter_subtitles_not_ready', __( 'Plan subtitle_persistence.ready must be true to persist relative _subtitles paths.', 'movies-wp' ) ) );
		}
		$dir = str_replace( '\\', '/', (string) $plan['movie']['media_directory'] );
		if ( str_starts_with( $dir, '/' ) || str_contains( $dir, '/data' ) || ! str_starts_with( $dir, 'Movie/' ) ) {
			return self::failure_result( null, null, array(), 'validate', self::err( 'media_adapter_bad_directory', 'media_directory must be a relative Movie/... path.' ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return array{ok:bool,movie_id?:int,error?:array}
	 */
	private static function step_create( array $plan, array $options ) {
		$tmdb_id = isset( $plan['movie']['tmdb_id'] ) ? absint( $plan['movie']['tmdb_id'] ) : 0;
		if ( $tmdb_id <= 0 ) {
			return array( 'ok' => false, 'error' => self::err( 'media_adapter_missing_tmdb_id', __( 'Create plan is missing movie.tmdb_id.', 'movies-wp' ) ) );
		}

		// Test hook: skip Streamit file load / settings.
		if ( isset( $options['create_from_tmdb'] ) && is_callable( $options['create_from_tmdb'] ) ) {
			$args   = isset( $options['tmdb_create_args'] ) && is_array( $options['tmdb_create_args'] )
				? $options['tmdb_create_args']
				: array();
			$result = call_user_func( $options['create_from_tmdb'], $tmdb_id, $args );
		} else {
			// Streamit only loads this file on its Content Import REST routes.
			$loaded = self::ensure_tmdb_movie_importer();
			if ( true !== $loaded ) {
				return array( 'ok' => false, 'error' => $loaded );
			}

			$args = self::tmdb_create_args();
			if ( '' === $args['api_key'] ) {
				return array(
					'ok'    => false,
					'error' => self::err( 'media_adapter_tmdb_api_key_missing', __( 'Please add a TMDb API key.', 'movies-wp' ) ),
				);
			}

			$result = insert_movie_tmdb_to_streamit( $tmdb_id, $args );
		}

		if ( is_wp_error( $result ) ) {
			return array(
				'ok'    => false,
				'error' => self::err(
					$result->get_error_code(),
					self::external_error_message( $result->get_error_code(), $result->get_error_message() )
				),
			);
		}
		if ( ! is_array( $result ) || empty( $result['status'] ) ) {
			$message = is_array( $result ) && isset( $result['message'] ) ? (string) $result['message'] : __( 'TMDb movie creation failed.', 'movies-wp' );
			return array(
				'ok'    => false,
				'error' => self::err(
					'media_adapter_create_failed',
					self::external_error_message( 'media_adapter_create_failed', $message )
				),
			);
		}

		$movie_id = isset( $result['data'] ) ? absint( $result['data'] ) : 0;
		if ( $movie_id <= 0 ) {
			return array( 'ok' => false, 'error' => self::err( 'media_adapter_create_no_id', __( 'TMDb movie creation returned no movie ID.', 'movies-wp' ) ) );
		}

		return array( 'ok' => true, 'movie_id' => $movie_id );
	}

	/**
	 * Load-merge-write title/summary and non-empty TMDb titles.
	 * Never calls streamit_update_movie().
	 *
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return array{ok:bool,error?:array}
	 */
	private static function step_metadata( $movie_id, array $plan, array $options ) {
		$title   = (string) $plan['metadata']['title'];
		$summary = (string) $plan['metadata']['summary'];

		if ( isset( $options['get_movie'] ) && is_callable( $options['get_movie'] ) ) {
			$movie = call_user_func( $options['get_movie'], (int) $movie_id );
		} else {
			if ( ! function_exists( 'streamit_get_movie' ) ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_get_movie_missing', 'streamit_get_movie() is not available.' ) );
			}
			$movie = streamit_get_movie( (int) $movie_id );
		}

		if ( ! $movie || ! is_object( $movie ) ) {
			return array( 'ok' => false, 'error' => self::err( 'media_adapter_movie_not_found', __( 'The Streamit movie could not be loaded for metadata update.', 'movies-wp' ) ) );
		}

		$payload = self::movie_row_from_object( $movie, (int) $movie_id );
		if ( is_wp_error( $payload ) ) {
			return array( 'ok' => false, 'error' => self::err( $payload->get_error_code(), $payload->get_error_message() ) );
		}

		$payload['post_title']         = $title;
		$payload['post_content']       = $summary;
		$payload['post_modified']      = self::now_local( $options );
		$payload['post_modified_gmt']  = self::now_gmt( $options );

		if ( isset( $options['update_movie_row'] ) && is_callable( $options['update_movie_row'] ) ) {
			$updated = call_user_func( $options['update_movie_row'], (int) $movie_id, $payload );
		} else {
			if ( ! class_exists( 'Streamit_Movie' ) ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_streamit_movie_missing', __( 'The Streamit_Movie class is not available.', 'movies-wp' ) ) );
			}
			// Intentionally NOT streamit_update_movie().
			$updated = ( new Streamit_Movie() )->update( (int) $movie_id, $payload );
		}

		if ( is_wp_error( $updated ) ) {
			return array( 'ok' => false, 'error' => self::err( $updated->get_error_code(), $updated->get_error_message() ) );
		}
		if ( false === $updated || null === $updated ) {
			return array( 'ok' => false, 'error' => self::err( 'media_adapter_metadata_failed', __( 'Movie metadata update failed.', 'movies-wp' ) ) );
		}

		$tmdb_titles = array(
			'_tmdb_title'          => isset( $plan['movie']['tmdb_title'] ) ? trim( (string) $plan['movie']['tmdb_title'] ) : '',
			'_tmdb_original_title' => isset( $plan['movie']['tmdb_original_title'] ) ? trim( (string) $plan['movie']['tmdb_original_title'] ) : '',
		);

		foreach ( $tmdb_titles as $key => $value ) {
			// Missing/empty plan values never erase an existing title.
			if ( '' === $value ) {
				continue;
			}

			if ( isset( $options['update_meta'] ) && is_callable( $options['update_meta'] ) ) {
				$written = call_user_func( $options['update_meta'], (int) $movie_id, $key, $value );
			} else {
				if ( ! function_exists( 'streamit_update_movie_meta' ) ) {
					return array( 'ok' => false, 'error' => self::err( 'media_adapter_update_meta_missing', 'streamit_update_movie_meta() is not available.' ) );
				}
				$written = streamit_update_movie_meta( (int) $movie_id, $key, $value );
			}

			if ( is_wp_error( $written ) ) {
				return array( 'ok' => false, 'error' => self::err( $written->get_error_code(), $written->get_error_message() ) );
			}
			if ( ! self::meta_write_succeeded( $written, $movie_id, $key, $value, $options ) ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_metadata_failed', sprintf( __( 'Failed to save %s.', 'movies-wp' ), $key ) ) );
			}
		}

		return array( 'ok' => true );
	}

	/**
	 * Merge plan sources into _source. Never deletes unmatched rows. Never writes media_path.
	 *
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return array{ok:bool,stats?:array,error?:array}
	 */
	private static function step_sources( $movie_id, array $plan, array $options ) {
		$existing = self::load_sources( (int) $movie_id, $options );
		$planned  = isset( $plan['sources'] ) && is_array( $plan['sources'] ) ? $plan['sources'] : array();

		$plan_by_key = array();
		foreach ( $planned as $idx => $src ) {
			if ( ! is_array( $src ) ) {
				continue;
			}
			$key = self::plan_source_identity( $src );
			if ( null === $key ) {
				continue;
			}
			if ( ! isset( $plan_by_key[ $key ] ) ) {
				$plan_by_key[ $key ] = $src;
			}
		}

		$merged   = array();
		$consumed = array();
		$stats    = array(
			'updated' => 0,
			'kept'    => 0,
			'added'   => 0,
		);

		foreach ( $existing as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = self::normalize_source_identity( $row );
			if ( null !== $key && isset( $plan_by_key[ $key ] ) ) {
				$psrc = $plan_by_key[ $key ];
				$action = isset( $psrc['action'] ) ? (string) $psrc['action'] : 'update';
				if ( 'keep_existing' === $action ) {
					$merged[] = $row;
					$stats['kept']++;
				} else {
					$merged[] = self::merge_source_row( $row, $psrc );
					$stats['updated']++;
				}
				$consumed[ $key ] = true;
			} else {
				// Unmatched existing — never delete.
				$merged[] = $row;
				$stats['kept']++;
			}
		}

		foreach ( $planned as $psrc ) {
			if ( ! is_array( $psrc ) ) {
				continue;
			}
			if ( ( $psrc['action'] ?? '' ) !== 'add' ) {
				continue;
			}
			$key = self::plan_source_identity( $psrc );
			if ( null === $key || isset( $consumed[ $key ] ) ) {
				continue;
			}
			$merged[] = self::build_new_source_row( $psrc, $options );
			$stats['added']++;
			$consumed[ $key ] = true;
		}

		$merged = array_values( $merged );

		if ( isset( $options['update_sources'] ) && is_callable( $options['update_sources'] ) ) {
			$written = call_user_func( $options['update_sources'], (int) $movie_id, $merged );
			if ( is_wp_error( $written ) ) {
				return array( 'ok' => false, 'error' => self::err( $written->get_error_code(), $written->get_error_message() ) );
			}
			if ( false === $written ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_sources_failed', __( 'Failed to save _source.', 'movies-wp' ) ) );
			}
		} else {
			if ( ! function_exists( 'streamit_update_movie_meta' ) ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_meta_missing', 'streamit_update_movie_meta() is not available.' ) );
			}
			$written = streamit_update_movie_meta( (int) $movie_id, '_source', $merged );
			if ( is_wp_error( $written ) ) {
				return array( 'ok' => false, 'error' => self::err( $written->get_error_code(), $written->get_error_message() ) );
			}
			// WP update_metadata() returns false when the value is unchanged.
			if ( ! self::meta_write_succeeded( $written, $movie_id, '_source', $merged, $options ) ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_sources_failed', __( 'Failed to save _source.', 'movies-wp' ) ) );
			}
		}

		return array( 'ok' => true, 'stats' => $stats, 'merged' => $merged );
	}

	/**
	 * Set Streamit default player to the first merged relative Movie/... link.
	 * Does not modify _source. Does not mint signed URLs or absolute /data paths.
	 *
	 * @param list<array<string, mixed>> $merged
	 * @param array<string, mixed>       $options
	 * @return array{ok:bool,skipped?:bool,error?:array}
	 */
	private static function step_default_stream( $movie_id, array $merged, array $options ) {
		$first_video_link = self::first_relative_movie_link( $merged );
		if ( null === $first_video_link ) {
			return array( 'ok' => true, 'skipped' => true );
		}

		$writes = array(
			'_movie_choice'   => 'movie_url',
			'_movie_url_link' => $first_video_link,
		);

		foreach ( $writes as $key => $value ) {
			if ( isset( $options['update_meta'] ) && is_callable( $options['update_meta'] ) ) {
				$written = call_user_func( $options['update_meta'], (int) $movie_id, $key, $value );
				if ( is_wp_error( $written ) ) {
					return array( 'ok' => false, 'error' => self::err( $written->get_error_code(), $written->get_error_message() ) );
				}
				if ( false === $written ) {
					return array(
						'ok'    => false,
						'error' => self::err( 'media_adapter_default_stream_failed', sprintf( __( 'Failed to save %s.', 'movies-wp' ), $key ) ),
					);
				}
			} else {
				if ( ! function_exists( 'streamit_update_movie_meta' ) ) {
					return array( 'ok' => false, 'error' => self::err( 'media_adapter_meta_missing', 'streamit_update_movie_meta() is not available.' ) );
				}
				$written = streamit_update_movie_meta( (int) $movie_id, $key, $value );
				if ( is_wp_error( $written ) ) {
					return array( 'ok' => false, 'error' => self::err( $written->get_error_code(), $written->get_error_message() ) );
				}
				if ( ! self::meta_write_succeeded( $written, $movie_id, $key, $value, $options ) ) {
					return array(
						'ok'    => false,
						'error' => self::err( 'media_adapter_default_stream_failed', sprintf( __( 'Failed to save %s.', 'movies-wp' ), $key ) ),
					);
				}
			}
		}

		return array( 'ok' => true );
	}

	/**
	 * First merged _source link that is a relative Movie/... path.
	 *
	 * @param list<array<string, mixed>> $merged
	 * @return string|null
	 */
	private static function first_relative_movie_link( array $merged ) {
		foreach ( $merged as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$link = isset( $row['link'] ) ? str_replace( '\\', '/', trim( (string) $row['link'] ) ) : '';
			$link = ltrim( $link, '/' );
			if ( '' === $link ) {
				continue;
			}
			if ( str_starts_with( $link, '/' ) || str_contains( $link, '/data' ) ) {
				continue;
			}
			if ( ! str_starts_with( $link, 'Movie/' ) ) {
				continue;
			}
			return $link;
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return array{ok:bool,error?:array}
	 */
	private static function step_media_directory( $movie_id, array $plan, array $options ) {
		$dir = str_replace( '\\', '/', (string) $plan['movie']['media_directory'] );
		$dir = trim( $dir );
		$dir = ltrim( $dir, '/' );

		if ( isset( $options['update_meta'] ) && is_callable( $options['update_meta'] ) ) {
			$written = call_user_func( $options['update_meta'], (int) $movie_id, '_media_directory', $dir );
			if ( is_wp_error( $written ) ) {
				return array( 'ok' => false, 'error' => self::err( $written->get_error_code(), $written->get_error_message() ) );
			}
			if ( false === $written ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_directory_failed', __( 'Failed to save _media_directory.', 'movies-wp' ) ) );
			}
		} else {
			if ( ! function_exists( 'streamit_update_movie_meta' ) ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_meta_missing', 'streamit_update_movie_meta() is not available.' ) );
			}
			$written = streamit_update_movie_meta( (int) $movie_id, '_media_directory', $dir );
			if ( is_wp_error( $written ) ) {
				return array( 'ok' => false, 'error' => self::err( $written->get_error_code(), $written->get_error_message() ) );
			}
			if ( ! self::meta_write_succeeded( $written, $movie_id, '_media_directory', $dir, $options ) ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_directory_failed', __( 'Failed to save _media_directory.', 'movies-wp' ) ) );
			}
		}

		return array( 'ok' => true );
	}

	/**
	 * Persist relative Movie/... subtitle paths on `_subtitles`.
	 * Never mints signed URLs. Never deletes unmatched existing rows.
	 *
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return array{ok:bool,stats?:array,error?:array}
	 */
	private static function step_subtitles( $movie_id, array $plan, array $options ) {
		$planned  = isset( $plan['subtitles'] ) && is_array( $plan['subtitles'] ) ? $plan['subtitles'] : array();
		$existing = self::load_subtitles( (int) $movie_id, $options );

		$plan_by_path = array();
		foreach ( $planned as $sub ) {
			if ( ! is_array( $sub ) ) {
				continue;
			}
			$path = self::normalize_subtitle_storage_path(
				isset( $sub['media_path'] ) ? $sub['media_path'] : ( $sub['url_plan']['value'] ?? null )
			);
			if ( null === $path ) {
				continue;
			}
			if ( ! isset( $plan_by_path[ $path ] ) ) {
				$plan_by_path[ $path ] = $sub;
			}
		}

		$merged   = array();
		$consumed = array();
		$stats    = array(
			'added'   => 0,
			'updated' => 0,
			'kept'    => 0,
		);

		foreach ( $existing as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = self::normalize_subtitle_storage_path( $row['url'] ?? null );
			if ( null !== $key && isset( $plan_by_path[ $key ] ) ) {
				$merged[]         = self::merge_subtitle_row( $row, $plan_by_path[ $key ], $key );
				$stats['updated']++;
				$consumed[ $key ] = true;
			} else {
				$merged[] = $row;
				$stats['kept']++;
			}
		}

		foreach ( $plan_by_path as $path => $psub ) {
			if ( isset( $consumed[ $path ] ) ) {
				continue;
			}
			$built = self::build_new_subtitle_row( $psub, $path );
			if ( null === $built ) {
				continue;
			}
			$merged[] = $built;
			$stats['added']++;
		}

		$merged = array_values( $merged );

		if ( isset( $options['update_meta'] ) && is_callable( $options['update_meta'] ) ) {
			$written = call_user_func( $options['update_meta'], (int) $movie_id, '_subtitles', $merged );
			if ( is_wp_error( $written ) ) {
				return array( 'ok' => false, 'error' => self::err( $written->get_error_code(), $written->get_error_message() ) );
			}
			if ( false === $written ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_subtitles_failed', __( 'Failed to save _subtitles.', 'movies-wp' ) ) );
			}
		} else {
			if ( ! function_exists( 'streamit_update_movie_meta' ) ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_meta_missing', 'streamit_update_movie_meta() is not available.' ) );
			}
			$written = streamit_update_movie_meta( (int) $movie_id, '_subtitles', $merged );
			if ( is_wp_error( $written ) ) {
				return array( 'ok' => false, 'error' => self::err( $written->get_error_code(), $written->get_error_message() ) );
			}
			if ( ! self::meta_write_succeeded( $written, $movie_id, '_subtitles', $merged, $options ) ) {
				return array( 'ok' => false, 'error' => self::err( 'media_adapter_subtitles_failed', __( 'Failed to save _subtitles.', 'movies-wp' ) ) );
			}
		}

		return array( 'ok' => true, 'stats' => $stats );
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<array<string, mixed>>
	 */
	private static function load_subtitles( $movie_id, array $options ) {
		if ( isset( $options['get_subtitles'] ) && is_callable( $options['get_subtitles'] ) ) {
			$raw = call_user_func( $options['get_subtitles'], (int) $movie_id );
		} elseif ( isset( $options['get_meta'] ) && is_callable( $options['get_meta'] ) ) {
			$raw = call_user_func( $options['get_meta'], (int) $movie_id, '_subtitles' );
		} elseif ( function_exists( 'streamit_get_movie_meta' ) ) {
			$raw = streamit_get_movie_meta( (int) $movie_id, '_subtitles', true );
		} else {
			$raw = array();
		}

		if ( is_string( $raw ) && function_exists( 'maybe_unserialize' ) ) {
			$raw = maybe_unserialize( $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Relative Movie/... subtitle path only. Rejects /data, http(s), and signed tokens.
	 *
	 * @param mixed $path
	 * @return string|null
	 */
	private static function normalize_subtitle_storage_path( $path ) {
		if ( ! is_string( $path ) && ! is_numeric( $path ) ) {
			return null;
		}
		$path = str_replace( '\\', '/', trim( (string) $path ) );
		$path = ltrim( $path, '/' );
		if ( '' === $path ) {
			return null;
		}
		if ( str_starts_with( $path, 'data/' ) ) {
			$path = substr( $path, 5 );
		}
		if ( str_contains( $path, '/data/' ) || str_starts_with( $path, '/' ) ) {
			return null;
		}
		if ( preg_match( '#^https?://#i', $path ) ) {
			return null;
		}
		if ( str_contains( $path, '/v/' ) || str_contains( $path, '/d/' ) || str_contains( $path, 'token=' ) ) {
			return null;
		}
		if ( ! str_starts_with( $path, 'Movie/' ) ) {
			return null;
		}
		return $path;
	}

	/**
	 * @param array<string, mixed> $existing
	 * @param array<string, mixed> $plan_sub
	 * @return array<string, mixed>
	 */
	private static function merge_subtitle_row( array $existing, array $plan_sub, $path ) {
		$row = $existing;
		$row['url'] = $path;

		$plan_lang = isset( $plan_sub['language'] ) ? $plan_sub['language'] : null;
		if ( is_string( $plan_lang ) && '' !== trim( $plan_lang ) ) {
			$lang            = strtolower( trim( $plan_lang ) );
			$row['srclang']  = $lang;
			$existing_label  = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
			if ( '' === $existing_label ) {
				$row['label'] = strtoupper( $lang );
			}
		}
		// Plan language null/empty → preserve existing srclang/label (manual edits).

		if ( isset( $plan_sub['format'] ) && is_string( $plan_sub['format'] ) && '' !== trim( $plan_sub['format'] ) ) {
			$row['format'] = strtoupper( trim( $plan_sub['format'] ) );
		} elseif ( ! isset( $row['format'] ) ) {
			$row['format'] = '';
		}

		if ( ! isset( $row['default'] ) ) {
			$row['default'] = 0;
		}
		if ( ! isset( $row['label'] ) ) {
			$row['label'] = '';
		}
		if ( ! isset( $row['srclang'] ) ) {
			$row['srclang'] = '';
		}

		return $row;
	}

	/**
	 * @param array<string, mixed> $plan_sub
	 * @return array<string, mixed>|null
	 */
	private static function build_new_subtitle_row( array $plan_sub, $path ) {
		$path = self::normalize_subtitle_storage_path( $path );
		if ( null === $path ) {
			return null;
		}

		$lang = '';
		if ( isset( $plan_sub['language'] ) && is_string( $plan_sub['language'] ) && '' !== trim( $plan_sub['language'] ) ) {
			$lang = strtolower( trim( $plan_sub['language'] ) );
		}

		$format = '';
		if ( isset( $plan_sub['format'] ) && is_string( $plan_sub['format'] ) && '' !== trim( $plan_sub['format'] ) ) {
			$format = strtoupper( trim( $plan_sub['format'] ) );
		}

		return array(
			'label'   => '' !== $lang ? strtoupper( $lang ) : '',
			'srclang' => $lang,
			'url'     => $path,
			'default' => 0,
			'format'  => $format,
		);
	}

	/**
	 * @param array<string, mixed> $existing
	 * @param array<string, mixed> $plan_src
	 * @return array<string, mixed>
	 */
	private static function merge_source_row( array $existing, array $plan_src ) {
		$row = $existing;

		$path = self::plan_source_identity( $plan_src );
		if ( null !== $path ) {
			$row['link']             = $path;
			$row['download_content'] = $path;
		}

		if ( array_key_exists( 'quality', $plan_src ) ) {
			$row['quality'] = null === $plan_src['quality'] ? '' : (string) $plan_src['quality'];
		}

		// language remains empty/null per plan — Streamit stores string.
		$row['language'] = '';

		if ( array_key_exists( 'file_size', $plan_src ) ) {
			$row['file_size'] = null === $plan_src['file_size'] ? '' : (string) $plan_src['file_size'];
		}

		$plan_name = isset( $plan_src['name'] ) ? trim( (string) $plan_src['name'] ) : '';
		if ( '' !== $plan_name ) {
			$row['name'] = $plan_name;
		} else {
			// Preserve existing manual name when plan encoder/name is empty.
			if ( ! isset( $row['name'] ) ) {
				$row['name'] = '';
			}
		}

		if ( ! isset( $row['is_affiliate'] ) ) {
			$row['is_affiliate'] = '0';
		}
		if ( ! isset( $row['player'] ) ) {
			$row['player'] = '';
		}
		if ( ! isset( $row['date_added'] ) ) {
			$row['date_added'] = '';
		}

		// Never persist media_path / provider / codecs / release_group on _source.
		unset( $row['media_path'], $row['provider'], $row['source_type'], $row['video_codec'], $row['audio_codec'], $row['release_group'], $row['group_hint'] );

		return $row;
	}

	/**
	 * @param array<string, mixed> $plan_src
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function build_new_source_row( array $plan_src, array $options ) {
		$path = self::plan_source_identity( $plan_src );
		$name = isset( $plan_src['name'] ) ? trim( (string) $plan_src['name'] ) : '';

		return array(
			'name'             => $name,
			'link'             => null !== $path ? $path : '',
			'is_affiliate'     => '0',
			'quality'          => isset( $plan_src['quality'] ) && null !== $plan_src['quality'] ? (string) $plan_src['quality'] : '',
			'language'         => '',
			'player'           => '',
			'date_added'       => self::today( $options ),
			'download_content' => null !== $path ? $path : '',
			'file_size'        => isset( $plan_src['file_size'] ) && null !== $plan_src['file_size'] ? (string) $plan_src['file_size'] : '',
		);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<array<string, mixed>>
	 */
	private static function load_sources( $movie_id, array $options ) {
		if ( isset( $options['get_sources'] ) && is_callable( $options['get_sources'] ) ) {
			$raw = call_user_func( $options['get_sources'], (int) $movie_id );
		} elseif ( function_exists( 'streamit_get_movie_meta' ) ) {
			$raw = streamit_get_movie_meta( (int) $movie_id, '_source', true );
		} else {
			$raw = array();
		}

		if ( is_string( $raw ) && function_exists( 'maybe_unserialize' ) ) {
			$raw = maybe_unserialize( $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $src
	 */
	private static function plan_source_identity( array $src ) {
		if ( isset( $src['identity_key'] ) && is_string( $src['identity_key'] ) && $src['identity_key'] !== '' ) {
			return self::normalize_path_key( $src['identity_key'] );
		}
		if ( isset( $src['media_path'] ) && is_string( $src['media_path'] ) && $src['media_path'] !== '' ) {
			return self::normalize_path_key( $src['media_path'] );
		}
		return self::normalize_source_identity( $src );
	}

	/**
	 * Identity: normalized link, else normalized download_content.
	 * Signed absolute URLs are not media-path identities.
	 *
	 * @param array<string, mixed> $row
	 */
	private static function normalize_source_identity( array $row ) {
		$link = isset( $row['link'] ) ? trim( (string) $row['link'] ) : '';
		$dl   = isset( $row['download_content'] ) ? trim( (string) $row['download_content'] ) : '';
		$raw  = '' !== $link ? $link : $dl;
		if ( '' === $raw ) {
			return null;
		}
		if ( preg_match( '#^https?://#i', $raw ) || str_contains( $raw, '/v/' ) || str_contains( $raw, '/d/' ) ) {
			return null;
		}
		return self::normalize_path_key( $raw );
	}

	private static function normalize_path_key( $path ) {
		$path = str_replace( '\\', '/', (string) $path );
		$path = trim( $path );
		$path = ltrim( $path, '/' );
		if ( str_starts_with( $path, 'data/' ) ) {
			$path = substr( $path, 5 );
		}
		return '' === $path ? null : $path;
	}

	/**
	 * WordPress update_metadata() returns false both on hard failure and when the
	 * new value is identical to the stored value. Treat identical as success.
	 *
	 * @param mixed                $written  Return from update_metadata / test hook.
	 * @param array<string, mixed> $options
	 */
	private static function meta_write_succeeded( $written, $movie_id, $key, $intended, array $options ) {
		if ( false !== $written ) {
			return true;
		}

		$current = null;
		if ( '_source' === $key ) {
			$current = self::load_sources( (int) $movie_id, $options );
		} elseif ( isset( $options['get_meta'] ) && is_callable( $options['get_meta'] ) ) {
			$current = call_user_func( $options['get_meta'], (int) $movie_id, (string) $key );
		} elseif ( function_exists( 'streamit_get_movie_meta' ) ) {
			$current = streamit_get_movie_meta( (int) $movie_id, (string) $key, true );
		}

		return self::meta_values_equal( $current, $intended );
	}

	/**
	 * @param mixed $a
	 * @param mixed $b
	 */
	private static function meta_values_equal( $a, $b ) {
		if ( function_exists( 'maybe_serialize' ) ) {
			return maybe_serialize( $a ) === maybe_serialize( $b );
		}
		return serialize( $a ) === serialize( $b );
	}

	/**
	 * Build a complete Streamit movie row from an existing object (load-merge-write).
	 *
	 * @param object $movie
	 * @return array<string, mixed>|WP_Error
	 */
	private static function movie_row_from_object( $movie, $movie_id ) {
		$getters = array(
			'post_author'           => 'get_post_author',
			'post_date'             => 'get_post_date',
			'post_date_gmt'         => 'get_post_date_gmt',
			'post_content'          => 'get_post_content',
			'post_title'            => 'get_post_title',
			'post_excerpt'          => 'get_post_excerpt',
			'post_status'           => 'get_post_status',
			'comment_status'        => 'get_comment_status',
			'ping_status'           => 'get_ping_status',
			'post_password'         => 'get_post_password',
			'post_name'             => 'get_post_name',
			'to_ping'               => 'get_to_ping',
			'pinged'                => 'get_pinged',
			'post_modified'         => 'get_post_modified',
			'post_modified_gmt'     => 'get_post_modified_gmt',
			'post_content_filtered' => 'get_post_content_filtered',
			'post_parent'           => 'get_post_parent',
			'guid'                  => 'get_guid',
			'menu_order'            => 'get_menu_order',
			'post_type'             => 'get_post_type',
			'post_mime_type'        => 'get_post_mime_type',
			'comment_count'         => 'get_comment_count',
		);

		$row = array( 'ID' => (int) $movie_id );
		foreach ( $getters as $field => $method ) {
			if ( ! method_exists( $movie, $method ) ) {
				return new WP_Error( 'media_adapter_incomplete_movie', sprintf( __( 'The Streamit movie object is missing %s.', 'movies-wp' ), $method ) );
			}
			$row[ $field ] = $movie->{$method}();
		}

		return $row;
	}

	/**
	 * Streamit only includes the TMDb movie importer on its own import REST routes.
	 * Load it on demand for create plans.
	 *
	 * @return true|array{code:string,message:string}
	 */
	private static function ensure_tmdb_movie_importer() {
		if ( function_exists( 'insert_movie_tmdb_to_streamit' ) ) {
			return true;
		}
		if ( ! defined( 'STREAMIT_PLUGIN_PATH' ) ) {
			return self::err( 'media_adapter_tmdb_importer_missing', __( 'The Streamit plugin path is not available.', 'movies-wp' ) );
		}
		$file = STREAMIT_PLUGIN_PATH . 'admin/content-import/streamit-tmdb_movie-function.php';
		if ( ! is_readable( $file ) ) {
			return self::err( 'media_adapter_tmdb_importer_missing', __( 'insert_movie_tmdb_to_streamit() is not available.', 'movies-wp' ) );
		}
		require_once $file;
		if ( ! function_exists( 'insert_movie_tmdb_to_streamit' ) ) {
			return self::err( 'media_adapter_tmdb_importer_missing', __( 'insert_movie_tmdb_to_streamit() is not available.', 'movies-wp' ) );
		}
		return true;
	}

	/**
	 * Args for insert_movie_tmdb_to_streamit() — same source as Streamit Content Import.
	 *
	 * @return array{api_key:string,language:string}
	 */
	private static function tmdb_create_args() {
		$api_key  = '';
		$language = 'en-US';

		if ( function_exists( 'get_option' ) ) {
			$raw = get_option( 'streamit_content_import_settings' );
			if ( is_string( $raw ) ) {
				$settings = @unserialize( $raw );
			} else {
				$settings = $raw;
			}
			if ( is_array( $settings ) ) {
				$key = $settings['tmdb']['api_key'] ?? '';
				if ( is_string( $key ) ) {
					$api_key = $key;
				}
				$lang = $settings['tmdb']['language'] ?? '';
				if ( is_string( $lang ) && '' !== $lang ) {
					$language = $lang;
				}
			}
		}

		return array(
			'api_key'  => $api_key,
			'language' => $language,
		);
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function today( array $options ) {
		if ( isset( $options['today'] ) && is_string( $options['today'] ) && $options['today'] !== '' ) {
			return $options['today'];
		}
		return gmdate( 'Y-m-d' );
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function now_local( array $options ) {
		if ( isset( $options['now_local'] ) && is_string( $options['now_local'] ) ) {
			return $options['now_local'];
		}
		if ( function_exists( 'current_time' ) ) {
			return current_time( 'mysql' );
		}
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function now_gmt( array $options ) {
		if ( isset( $options['now_gmt'] ) && is_string( $options['now_gmt'] ) ) {
			return $options['now_gmt'];
		}
		if ( function_exists( 'current_time' ) ) {
			return current_time( 'mysql', 1 );
		}
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * @return array{code:string,message:string}
	 */
	private static function err( $code, $message ) {
		return array(
			'code'    => (string) $code,
			'message' => (string) $message,
		);
	}

	/**
	 * Add a localized explanation to errors returned by the native Streamit importer.
	 *
	 * The original message is retained as a technical detail because it may come
	 * from Streamit or an upstream API.
	 *
	 * @param mixed $code    External error code.
	 * @param mixed $message External error message.
	 * @return string
	 */
	private static function external_error_message( $code, $message ) {
		$code    = (string) $code;
		$message = trim( (string) $message );
		$summary = 'api_error' === $code
			? __( 'Could not connect to TMDb.', 'movies-wp' )
			: __( 'TMDb movie creation failed.', 'movies-wp' );

		if ( '' === $message || $message === $summary ) {
			return $summary;
		}

		return $summary . ' ' . sprintf(
			/* translators: %s: technical error returned by Streamit or TMDb */
			__( 'Error details: %s', 'movies-wp' ),
			$message
		);
	}

	/**
	 * @param list<string>             $completed
	 * @param array{code:string,message:string} $error
	 * @return array<string, mixed>
	 */
	private static function failure_result( $movie_id, $identity_action, array $completed, $failed_step, array $error ) {
		return array(
			'ok'              => false,
			'movie_id'        => null !== $movie_id ? (int) $movie_id : null,
			'identity_action' => $identity_action,
			'completed'       => array_values( $completed ),
			'failed_step'     => (string) $failed_step,
			'error'           => $error,
			'deferred'        => array(),
		);
	}
}

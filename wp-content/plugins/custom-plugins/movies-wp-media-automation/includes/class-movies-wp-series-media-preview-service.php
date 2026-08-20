<?php
/**
 * Read-only Series media preview service.
 *
 * Validates explicit TV show identity, scans the Series directory, and resolves
 * episodes strictly by tvshow_id + season_number + episode_number.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-movies-wp-series-import-profiler.php';

class Movies_WP_Series_Media_Preview_Service {

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|WP_Error
	 */
	public static function build( array $input, array $options = array() ) {
		$normalized = self::normalize_input( $input );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$tvshow = self::load_tvshow( $normalized['tvshow_id'], $options );
		if ( is_wp_error( $tvshow ) ) {
			return $tvshow;
		}

		if ( $normalized['expected_tmdb_id'] > 0 ) {
			$actual = self::tvshow_meta( $normalized['tvshow_id'], '_tmdb_id', $options );
			if ( absint( $actual ) !== $normalized['expected_tmdb_id'] ) {
				return new WP_Error(
					'series_media_preview_tmdb_mismatch',
					__( 'The selected TV show does not match the expected TMDb ID.', 'movies-wp' )
				);
			}
		}

		$scan_snap = Movies_WP_Series_Import_Profiler::phase_start( 'media_scan' );
		if ( isset( $options['scan'] ) && is_array( $options['scan'] ) ) {
			$scan = $options['scan'];
		} else {
			$scan = self::scan_series( $normalized['series_directory'], $options );
		}
		$scan_eps  = is_array( $scan ) ? count( $scan['episodes'] ?? array() ) : 0;
		Movies_WP_Series_Import_Profiler::phase_end( 'media_scan', $scan_snap, $scan_eps, 'media_preview_scan' );
		if ( is_wp_error( $scan ) ) {
			return $scan;
		}

		$lookup_snap   = Movies_WP_Series_Import_Profiler::phase_start( 'episode_lookup' );
		$episode_index = self::index_episodes( $normalized['tvshow_id'], $options );
		$lookup_count  = ( ! is_wp_error( $episode_index ) && is_array( $episode_index ) ) ? count( $episode_index['by_id'] ?? array() ) : 0;
		Movies_WP_Series_Import_Profiler::phase_end( 'episode_lookup', $lookup_snap, $lookup_count, 'index_episodes' );
		if ( is_wp_error( $episode_index ) ) {
			return $episode_index;
		}

		$errors   = array();
		$warnings = self::collect_scan_warnings( $scan );
		$matches  = array();
		$resolution = self::resolve_scan_episode_groups(
			isset( $scan['episodes'] ) && is_array( $scan['episodes'] ) ? $scan['episodes'] : array(),
			array_values( $episode_index['by_id'] )
		);
		$errors = $resolution['errors'];
		$scan['episodes'] = $resolution['episodes'];

		foreach ( $scan['episodes'] as $episode_group ) {
			if ( ! is_array( $episode_group ) ) {
				continue;
			}
			$season  = self::canonical_season_string( $episode_group['season_number'] ?? null );
			$episode = self::canonical_episode_string( $episode_group['episode_number'] ?? null );
			if ( null === $season || null === $episode ) {
				continue;
			}

			$key    = $season . ':' . $episode;
			$lookup = $episode_index['by_se'][ $key ] ?? array();
			$match  = array(
				'season_number'  => $season,
				'episode_number' => $episode,
				'token'          => (string) ( $episode_group['token'] ?? '' ),
				'sources'        => $episode_group['sources'] ?? array(),
				'subtitles'      => $episode_group['subtitles'] ?? array(),
				'source_count'   => count( $episode_group['sources'] ?? array() ),
				'subtitle_count' => count( $episode_group['subtitles'] ?? array() ),
			);

			if ( count( $lookup ) === 0 ) {
				$match['status'] = 'missing_episode';
				$errors[] = self::issue(
					'missing_episode',
					sprintf(
						/* translators: 1: season number, 2: episode number */
						__( 'No Streamit episode exists for season %1$s episode %2$s.', 'movies-wp' ),
						$season,
						$episode
					),
					$season,
					$episode
				);
			} elseif ( count( $lookup ) > 1 ) {
				$match['status'] = 'ambiguous_episode';
				$match['candidate_episode_ids'] = $lookup;
				$errors[] = self::issue(
					'ambiguous_episode',
					sprintf(
						/* translators: 1: season number, 2: episode number */
						__( 'Multiple Streamit episodes match season %1$s episode %2$s.', 'movies-wp' ),
						$season,
						$episode
					),
					$season,
					$episode
				);
			} else {
				$episode_id = (int) $lookup[0];
				$row        = $episode_index['by_id'][ $episode_id ] ?? null;
				$match['status'] = 'matched';
				$match['episode_id'] = $episode_id;
				$match['tvshow_id'] = $normalized['tvshow_id'];
				if ( is_array( $row ) ) {
					$match['tmdb_id'] = (int) ( $row['tmdb_id'] ?? 0 );
				}
			}

			$matches[] = $match;
		}

		$ready = empty( $errors ) && ! empty( $scan['ready'] );

		return array(
			'ok'              => true,
			'type'            => 'series_media',
			'input'           => $normalized,
			'tvshow'          => $tvshow,
			'media'           => $scan,
			'episodes'        => $matches,
			'validation'      => array(
				'errors'   => $errors,
				'warnings' => $warnings,
			),
			'ready_to_import' => $ready,
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{tvshow_id:int,expected_tmdb_id:int,series_directory:string}|WP_Error
	 */
	private static function normalize_input( array $input ) {
		$tvshow_id = isset( $input['tvshow_id'] ) ? absint( $input['tvshow_id'] ) : 0;
		$expected  = isset( $input['expected_tmdb_id'] ) ? absint( $input['expected_tmdb_id'] ) : 0;
		$directory = isset( $input['series_directory'] ) ? (string) $input['series_directory'] : '';

		if ( $tvshow_id <= 0 ) {
			return new WP_Error( 'series_media_preview_invalid_input', __( 'TV show ID must be a positive number.', 'movies-wp' ) );
		}

		$normalized_dir = Movies_WP_Series_Media_Api_Client::normalize_directory( $directory );
		if ( is_wp_error( $normalized_dir ) ) {
			return new WP_Error( 'series_media_preview_invalid_input', __( 'Invalid series directory.', 'movies-wp' ) );
		}

		return array(
			'tvshow_id'         => $tvshow_id,
			'expected_tmdb_id'  => $expected,
			'series_directory'  => $normalized_dir,
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function load_tvshow( $tvshow_id, array $options ) {
		if ( isset( $options['get_tvshow'] ) && is_callable( $options['get_tvshow'] ) ) {
			$tvshow = call_user_func( $options['get_tvshow'], (int) $tvshow_id );
		} elseif ( function_exists( 'streamit_get_tvshow' ) ) {
			$tvshow = streamit_get_tvshow( (int) $tvshow_id );
		} else {
			return new WP_Error( 'series_media_preview_unavailable', __( 'Streamit TV show helpers are not available.', 'movies-wp' ) );
		}

		if ( empty( $tvshow ) ) {
			return new WP_Error( 'series_media_preview_tvshow_not_found', __( 'The selected TV show was not found.', 'movies-wp' ) );
		}

		return is_object( $tvshow ) ? array( 'id' => (int) $tvshow_id, 'object' => $tvshow ) : (array) $tvshow;
	}

	private static function tvshow_meta( $tvshow_id, $key, array $options ) {
		if ( isset( $options['get_tvshow_meta'] ) && is_callable( $options['get_tvshow_meta'] ) ) {
			return call_user_func( $options['get_tvshow_meta'], (int) $tvshow_id, $key );
		}
		if ( function_exists( 'streamit_get_tvshow_meta' ) ) {
			return streamit_get_tvshow_meta( (int) $tvshow_id, (string) $key, true );
		}
		return null;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function scan_series( $directory, array $options ) {
		if ( isset( $options['scan_series'] ) && is_callable( $options['scan_series'] ) ) {
			$scan = call_user_func( $options['scan_series'], (string) $directory );
		} else {
			$scan = Movies_WP_Series_Media_Api_Client::scan_series_directory( (string) $directory );
		}
		return $scan;
	}

	/**
	 * @return array{by_id:array<int,array>,by_se:array<string,list<int>>}|WP_Error
	 */
	private static function index_episodes( $tvshow_id, array $options ) {
		if ( isset( $options['find_episodes'] ) && is_callable( $options['find_episodes'] ) ) {
			$rows = call_user_func( $options['find_episodes'], (int) $tvshow_id );
		} elseif ( function_exists( 'streamit_get_episodes' ) ) {
			$result = streamit_get_episodes(
				array(
					'per_page'    => -1,
					'post_status' => array( 'all' ),
					'meta_query'  => array(
						array(
							'key'     => 'tvshow_id',
							'value'   => (string) $tvshow_id,
							'compare' => '=',
						),
					),
				)
			);
			$rows = array();
			if ( is_object( $result ) && isset( $result->results ) && is_array( $result->results ) ) {
				foreach ( $result->results as $episode ) {
					if ( ! is_object( $episode ) || ! method_exists( $episode, 'get_id' ) ) {
						continue;
					}
					$id = absint( $episode->get_id() );
					$rows[] = array(
						'id'             => $id,
						'tvshow_id'      => (int) $tvshow_id,
						'tmdb_id'        => absint( $episode->get_meta( '_tmdb_id' ) ),
						'season_number'  => $episode->get_meta( '_season_number' ),
						'episode_number' => $episode->get_meta( '_episode_number' ),
					);
				}
			}
		} else {
			return new WP_Error( 'series_media_preview_unavailable', __( 'Streamit episode helpers are not available.', 'movies-wp' ) );
		}

		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'series_media_preview_episode_lookup_failed', __( 'Episode lookup returned invalid data.', 'movies-wp' ) );
		}

		$index = array(
			'by_id' => array(),
			'by_se' => array(),
		);

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = absint( $row['id'] ?? 0 );
			if ( $id <= 0 || absint( $row['tvshow_id'] ?? $tvshow_id ) !== (int) $tvshow_id ) {
				continue;
			}
			$season  = self::canonical_season_string( $row['season_number'] ?? null );
			$episode = self::canonical_episode_string( $row['episode_number'] ?? null );
			$index['by_id'][ $id ] = array(
				'id'             => $id,
				'tvshow_id'      => (int) $tvshow_id,
				'tmdb_id'        => absint( $row['tmdb_id'] ?? 0 ),
				'season_number'  => $season,
				'episode_number' => $episode,
			);
			if ( null !== $season && null !== $episode ) {
				$key = $season . ':' . $episode;
				if ( ! isset( $index['by_se'][ $key ] ) ) {
					$index['by_se'][ $key ] = array();
				}
				$index['by_se'][ $key ][] = $id;
			}
		}

		return $index;
	}

	/**
	 * Resolve EPxx groups only when one authoritative season has that episode.
	 *
	 * @param list<array<string, mixed>> $groups
	 * @param list<array<string, mixed>> $authoritative_episodes
	 * @return array{episodes:list<array<string,mixed>>,errors:list<array<string,mixed>>}
	 */
	public static function resolve_scan_episode_groups( array $groups, array $authoritative_episodes ) {
		$resolved = array();
		$errors   = array();

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$episode = self::canonical_episode_string( $group['episode_number'] ?? null );
			$season  = self::canonical_season_string( $group['season_number'] ?? null );
			if ( null === $episode ) {
				$errors[] = self::issue(
					'invalid_scan_episode_identity',
					__( 'Series scan returned an invalid episode identity.', 'movies-wp' )
				);
				continue;
			}

			if ( null === $season ) {
				if ( 'episode_only' !== ( $group['identity_type'] ?? '' ) ) {
					$errors[] = self::issue(
						'invalid_scan_episode_identity',
						__( 'Series scan returned an invalid episode identity.', 'movies-wp' ),
						'',
						$episode
					);
					continue;
				}

				$candidate_seasons = array();
				foreach ( $authoritative_episodes as $candidate ) {
					if ( ! is_array( $candidate ) || self::canonical_episode_string( $candidate['episode_number'] ?? null ) !== $episode ) {
						continue;
					}
					$candidate_season = self::canonical_season_string( $candidate['season_number'] ?? null );
					if ( null !== $candidate_season ) {
						$candidate_seasons[ $candidate_season ] = true;
					}
				}
				$candidate_seasons = array_keys( $candidate_seasons );
				sort( $candidate_seasons, SORT_NUMERIC );

				if ( count( $candidate_seasons ) === 0 ) {
					$errors[] = self::issue(
						'episode_only_without_authoritative_match',
						sprintf(
							/* translators: %s: episode number */
							__( 'Episode EP%s has no authoritative season/episode match. No episode will be created from the filename.', 'movies-wp' ),
							$episode
						),
						'',
						$episode
					);
					continue;
				}
				if ( count( $candidate_seasons ) > 1 ) {
					$errors[] = array_merge(
						self::issue(
							'episode_only_ambiguous_season',
							sprintf(
								/* translators: 1: episode number, 2: candidate season numbers */
								__( 'Episode EP%1$s matches multiple authoritative seasons (%2$s); its season cannot be resolved safely.', 'movies-wp' ),
								$episode,
								implode( ', ', $candidate_seasons )
							),
							'',
							$episode
						),
						array( 'candidate_seasons' => $candidate_seasons )
					);
					continue;
				}
				$season = $candidate_seasons[0];
				$group['resolved_from'] = 'episode_only';
			}

			$group['identity_type']  = 'season_episode';
			$group['season_number']  = $season;
			$group['episode_number'] = $episode;
			$key = $season . ':' . $episode;
			if ( ! isset( $resolved[ $key ] ) ) {
				$resolved[ $key ] = $group;
				continue;
			}

			$existing_episode_only = 'episode_only' === ( $resolved[ $key ]['resolved_from'] ?? '' );
			$current_episode_only  = 'episode_only' === ( $group['resolved_from'] ?? '' );
			if ( $existing_episode_only === $current_episode_only ) {
				$resolved[ $key . '#' . count( $resolved ) ] = $group;
				continue;
			}

			$resolved[ $key ]['sources'] = array_merge(
				is_array( $resolved[ $key ]['sources'] ?? null ) ? $resolved[ $key ]['sources'] : array(),
				is_array( $group['sources'] ?? null ) ? $group['sources'] : array()
			);
			$resolved[ $key ]['subtitles'] = array_merge(
				is_array( $resolved[ $key ]['subtitles'] ?? null ) ? $resolved[ $key ]['subtitles'] : array(),
				is_array( $group['subtitles'] ?? null ) ? $group['subtitles'] : array()
			);
			$resolved[ $key ]['source_count'] = count( $resolved[ $key ]['sources'] );
			$resolved[ $key ]['subtitle_count'] = count( $resolved[ $key ]['subtitles'] );
			if ( 'episode_only' !== ( $group['resolved_from'] ?? '' ) && ! empty( $group['token'] ) ) {
				$resolved[ $key ]['token'] = (string) $group['token'];
			}
		}

		return array(
			'episodes' => array_values( $resolved ),
			'errors'   => $errors,
		);
	}

	/**
	 * @param array<string, mixed> $scan
	 * @return list<array<string, mixed>>
	 */
	private static function collect_scan_warnings( array $scan ) {
		$warnings = array();
		foreach ( array( 'warnings', 'errors' ) as $bucket ) {
			if ( ! isset( $scan[ $bucket ] ) || ! is_array( $scan[ $bucket ] ) ) {
				continue;
			}
			foreach ( $scan[ $bucket ] as $issue ) {
				if ( is_array( $issue ) ) {
					$warnings[] = $issue;
				}
			}
		}
		return $warnings;
	}

	/**
	 * @param mixed $value
	 */
	public static function canonical_season_string( $value ) {
		if ( is_int( $value ) ) {
			return $value >= 0 ? (string) $value : null;
		}
		if ( is_string( $value ) && preg_match( '/^\d+$/', trim( $value ) ) ) {
			return (string) (int) trim( $value );
		}
		return null;
	}

	/**
	 * @param mixed $value
	 */
	public static function canonical_episode_string( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0 ? (string) $value : null;
		}
		if ( is_string( $value ) && preg_match( '/^(?:E)?(\d+)$/i', trim( $value ), $matches ) ) {
			$number = (int) $matches[1];
			return $number > 0 ? (string) $number : null;
		}
		return null;
	}

	private static function issue( $code, $message, $season = '', $episode = '' ) {
		return array(
			'code'           => (string) $code,
			'message'        => (string) $message,
			'season_number'  => (string) $season,
			'episode_number' => (string) $episode,
		);
	}
}

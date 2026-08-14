<?php
/**
 * Read-only Import Plan: deterministic translation of scan/preview facts into
 * what a future importer WOULD write — not an importer itself.
 *
 * Flow: scan facts → Preview → Import Plan → (future) Import executor.
 * The future importer must execute this plan; it must not rediscover
 * filesystem / parser / ffprobe facts.
 *
 * Source identity (Streamit `_source` merge key):
 *   normalized `link` OR, if empty, normalized `download_content`.
 * Never quality / provider / encoder / source_type / basename alone.
 * Never emit a `delete` action for existing source rows.
 *
 * Safety: never mutates `$preview` / file objects; never writes WordPress or
 * Streamit; never mints signed URLs; never invents audio language or encoder.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Media_Import_Plan {

	/**
	 * Build an import plan from a preview payload.
	 *
	 * @param array<string, mixed> $preview Preview Service result (ok/input/tmdb/media/validation).
	 * @param array{
	 *   find_by_tmdb?: callable(int): array{ids: list<int>},
	 *   get_sources?: callable(int): mixed,
	 *   associate?: callable(list<array>): array
	 * } $options Test hooks / overrides. Defaults use read-only Streamit APIs + media-server association.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function build( array $preview, array $options = array() ) {
		if ( empty( $preview['ok'] ) || ! is_array( $preview ) ) {
			return new WP_Error( 'media_import_plan_invalid_preview', 'Preview payload is invalid.' );
		}

		$input = isset( $preview['input'] ) && is_array( $preview['input'] ) ? $preview['input'] : array();
		$tmdb  = isset( $preview['tmdb'] ) && is_array( $preview['tmdb'] ) ? $preview['tmdb'] : array();
		$media = isset( $preview['media'] ) && is_array( $preview['media'] ) ? $preview['media'] : array();

		$tmdb_id = isset( $input['tmdb_id'] ) ? absint( $input['tmdb_id'] ) : 0;
		$title   = isset( $input['title'] ) ? trim( (string) $input['title'] ) : '';
		$summary = isset( $input['summary'] ) ? (string) $input['summary'] : '';
		$dir     = isset( $input['media_directory'] ) ? (string) $input['media_directory'] : '';

		if ( $tmdb_id <= 0 || '' === $title || '' === $dir ) {
			return new WP_Error( 'media_import_plan_invalid_preview', 'Preview input is missing required fields.' );
		}

		$files = isset( $media['files'] ) && is_array( $media['files'] ) ? $media['files'] : array();

		$errors   = array();
		$warnings = array();

		// Preview Service owns the canonical user-facing warning list.
		self::merge_issues( $errors, $warnings, isset( $preview['validation'] ) ? $preview['validation'] : array() );

		// Carry scan-level media warnings only when Preview did not already include them
		// (Preview aggregation is preferred; this is a safety net for minimal fixtures).
		if ( empty( $preview['validation']['warnings'] ) && isset( $media['warnings'] ) && is_array( $media['warnings'] ) ) {
			foreach ( $media['warnings'] as $warning ) {
				if ( ! is_array( $warning ) ) {
					continue;
				}
				$code = isset( $warning['code'] ) ? (string) $warning['code'] : 'media_warning';
				if ( self::is_internal_warning_code( $code ) ) {
					continue;
				}
				$warnings[] = self::issue(
					$code,
					isset( $warning['message'] ) ? (string) $warning['message'] : 'Media scan warning.',
					isset( $warning['name'] ) ? (string) $warning['name'] : ( isset( $warning['file'] ) ? (string) $warning['file'] : '' )
				);
			}
		}

		// Per-file validation: always merge blocking errors.
		// Merge non-internal validation warnings once (deduped later against Preview).
		// Do not also merge detected.validation (duplicate of file.validation).
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			$name = isset( $file['name'] ) ? (string) $file['name'] : '';
			if ( isset( $file['validation'] ) && is_array( $file['validation'] ) ) {
				if ( isset( $file['validation']['errors'] ) && is_array( $file['validation']['errors'] ) ) {
					foreach ( $file['validation']['errors'] as $issue ) {
						if ( is_array( $issue ) ) {
							$errors[] = self::normalize_issue( $issue, $name );
						}
					}
				}
				if ( isset( $file['validation']['warnings'] ) && is_array( $file['validation']['warnings'] ) ) {
					foreach ( $file['validation']['warnings'] as $issue ) {
						if ( ! is_array( $issue ) ) {
							continue;
						}
						$code = isset( $issue['code'] ) ? (string) $issue['code'] : '';
						if ( self::is_internal_warning_code( $code ) ) {
							continue;
						}
						$warnings[] = self::normalize_issue( $issue, $name );
					}
				}
			}

			// Parser file.warnings when Preview aggregation was empty (fixtures / edge paths).
			if ( empty( $preview['validation']['warnings'] ) && ! empty( $file['warnings'] ) && is_array( $file['warnings'] ) ) {
				foreach ( $file['warnings'] as $warning ) {
					if ( ! is_array( $warning ) ) {
						continue;
					}
					$code = isset( $warning['code'] ) ? (string) $warning['code'] : 'unclassified_tokens';
					if ( self::is_internal_warning_code( $code ) ) {
						continue;
					}
					$warnings[] = self::issue(
						$code,
						isset( $warning['message'] ) ? (string) $warning['message'] : 'Filename warning.',
						$name
					);
				}
			}
		}

		$identity = self::resolve_identity( $tmdb_id, $options );
		if ( is_wp_error( $identity ) ) {
			return $identity;
		}
		if ( ! empty( $identity['error'] ) && is_array( $identity['error'] ) ) {
			$errors[] = $identity['error'];
		}

		$association = self::run_association( $files, $options );
		if ( is_wp_error( $association ) ) {
			return $association;
		}

		foreach ( $association['warnings'] as $warning ) {
			if ( is_array( $warning ) ) {
				$warnings[] = self::issue(
					isset( $warning['code'] ) ? (string) $warning['code'] : 'association_warning',
					isset( $warning['message'] ) ? (string) $warning['message'] : 'Association warning.',
					isset( $warning['subtitle'] ) ? (string) $warning['subtitle'] : ''
				);
			}
		}

		$existing_sources = array();
		if ( 'update' === $identity['action'] && $identity['existing_movie_id'] ) {
			$existing_sources = self::load_existing_sources( (int) $identity['existing_movie_id'], $options );
		}

		$sources = self::plan_sources( $files, $existing_sources );
		$subs    = self::plan_subtitles( $files, $association );

		$tmdb_overview = isset( $tmdb['overview'] ) ? (string) $tmdb['overview'] : '';
		$tmdb_title    = isset( $tmdb['title'] ) ? (string) $tmdb['title'] : '';

		$plan_summary = '' !== trim( $summary ) ? $summary : $tmdb_overview;

		$ready = empty( $errors );

		return array(
			'ok'   => true,
			'contract' => array(
				'kind'        => 'import_plan',
				'version'     => 1,
				'read_only'   => true,
				'description' => 'Deterministic translation plan, not an importer. Future Import must execute this plan without rediscovering media facts.',
			),
			'movie' => array(
				'tmdb_id'          => $tmdb_id,
				'title'            => $title,
				'summary'          => $plan_summary,
				'media_directory'  => $dir,
				'tmdb_title'       => $tmdb_title,
				'tmdb_overview'    => $tmdb_overview,
				// TMDb original title is metadata only — not a Streamit field to write.
				'tmdb_original_title' => isset( $tmdb['original_title'] ) ? (string) $tmdb['original_title'] : null,
			),
			'identity' => array(
				'existing_movie_id' => $identity['existing_movie_id'],
				'action'            => $identity['action'],
				'match_count'       => $identity['match_count'],
				'match_by'          => '_tmdb_id',
			),
			'metadata' => array(
				'title'   => $title,
				'summary' => $plan_summary,
				'tmdb_id' => $tmdb_id,
				'summary_source' => '' !== trim( $summary ) ? 'admin' : 'tmdb',
				'title_source'   => 'admin',
			),
			'sources'                 => $sources,
			'subtitles'               => $subs['associated'],
			'unassociated_subtitles'  => $subs['unassociated'],
			'associations'            => $association['associations'],
			'subtitle_persistence'    => array(
				'ready'  => true,
				'status' => 'relative_path',
				'reason' => 'Persist relative Movie/... subtitle paths on _subtitles.url. Signed /v/ and /d/ URLs are minted only at render time by streamit_child_resolve_subtitle_url().',
			),
			'source_identity'         => array(
				'key'     => 'normalized_link_or_download_content',
				'rule'    => 'Identity is normalized link, else normalized download_content (same as media_path for planned rows). Never quality/provider/encoder/source_type.',
				'actions' => array( 'add', 'update', 'keep_existing' ),
				'delete'  => false,
			),
			'language_decision'       => array(
				'streamit_source_language' => null,
				'status'                   => 'deferred',
				'reason'                   => 'Do not map ffprobe tracks, country, title, or subtitle language into Streamit source.language yet. Audio facts live under source.audio.',
			),
			'warnings'                => self::unique_issues( $warnings ),
			'errors'                  => self::unique_issues( $errors ),
			'ready_to_import'         => $ready,
			'notes'                   => array(
				'This plan is read-only. No Streamit or WordPress writes were performed.',
				'Source language is deferred (null). Audio facts remain under source.audio.',
				'Subtitle rows store relative media_path on _subtitles.url; signed URLs are render-time only.',
				'Encoder/name uses the parser allowlist only (YIFY/YTS/RARBG); otherwise "".',
				'On update, an empty detected encoder preserves an existing manual Streamit name.',
				'Unmatched existing _source rows are keep_existing — never delete.',
			),
		);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array{existing_movie_id: int|null, action: string, match_count: int, error?: array}|WP_Error
	 */
	private static function resolve_identity( $tmdb_id, array $options ) {
		$finder = null;
		if ( isset( $options['find_by_tmdb'] ) && is_callable( $options['find_by_tmdb'] ) ) {
			$finder = $options['find_by_tmdb'];
		}

		$ids = array();
		if ( $finder ) {
			$found = call_user_func( $finder, (int) $tmdb_id );
			if ( is_array( $found ) && isset( $found['ids'] ) && is_array( $found['ids'] ) ) {
				foreach ( $found['ids'] as $id ) {
					$ids[] = (int) $id;
				}
			}
		} else {
			$ids = self::find_movie_ids_by_tmdb( (int) $tmdb_id );
		}

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		sort( $ids, SORT_NUMERIC );
		$count = count( $ids );

		if ( $count > 1 ) {
			return array(
				'existing_movie_id' => null,
				'action'            => 'blocked',
				'match_count'       => $count,
				'error'             => self::issue(
					'duplicate_tmdb_id',
					sprintf(
						'Multiple Streamit movies share TMDb ID %d (%s). Resolve duplicates before import.',
						(int) $tmdb_id,
						implode( ', ', $ids )
					)
				),
			);
		}

		if ( 1 === $count ) {
			return array(
				'existing_movie_id' => $ids[0],
				'action'            => 'update',
				'match_count'       => 1,
			);
		}

		return array(
			'existing_movie_id' => null,
			'action'            => 'create',
			'match_count'       => 0,
		);
	}

	/**
	 * Read-only TMDb identity lookup via Streamit query API.
	 *
	 * @param int $tmdb_id
	 * @return list<int>
	 */
	private static function find_movie_ids_by_tmdb( $tmdb_id ) {
		$ids = array();
		if ( ! function_exists( 'streamit_get_movies' ) ) {
			return $ids;
		}

		$result = streamit_get_movies(
			array(
				'per_page'    => 20,
				'paged'       => 1,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'meta_query'  => array(
					array(
						'key'     => '_tmdb_id',
						'value'   => (string) $tmdb_id,
						'compare' => '=',
					),
				),
			)
		);

		if ( is_object( $result ) && ! empty( $result->results ) && is_array( $result->results ) ) {
			foreach ( $result->results as $movie ) {
				if ( is_object( $movie ) && method_exists( $movie, 'get_id' ) ) {
					$ids[] = (int) $movie->get_id();
				}
			}
		}

		return $ids;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<array<string, mixed>>
	 */
	private static function load_existing_sources( $movie_id, array $options ) {
		if ( isset( $options['get_sources'] ) && is_callable( $options['get_sources'] ) ) {
			$raw = call_user_func( $options['get_sources'], (int) $movie_id );
		} elseif ( function_exists( 'streamit_get_movie_meta' ) ) {
			$raw = streamit_get_movie_meta( (int) $movie_id, '_source', true );
		} else {
			$raw = array();
		}

		if ( is_string( $raw ) ) {
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
	 * @param list<array<string, mixed>> $files
	 * @param array<string, mixed>       $options
	 * @return array{associations: list, unassociated_subtitles: list, warnings: list}|WP_Error
	 */
	private static function run_association( array $files, array $options ) {
		if ( isset( $options['associate'] ) && is_callable( $options['associate'] ) ) {
			$result = call_user_func( $options['associate'], $files );
			if ( ! is_array( $result ) ) {
				return new WP_Error( 'media_import_plan_association_failed', 'Association hook returned an invalid result.' );
			}
			return array(
				'associations'           => isset( $result['associations'] ) && is_array( $result['associations'] ) ? $result['associations'] : array(),
				'unassociated_subtitles' => isset( $result['unassociated_subtitles'] ) && is_array( $result['unassociated_subtitles'] ) ? $result['unassociated_subtitles'] : array(),
				'warnings'               => isset( $result['warnings'] ) && is_array( $result['warnings'] ) ? $result['warnings'] : array(),
			);
		}

		self::ensure_association_loaded();
		if ( ! function_exists( 'media_associate_movie_files' ) ) {
			return new WP_Error(
				'media_import_plan_association_unavailable',
				'Subtitle association module is not available.'
			);
		}

		$result = media_associate_movie_files( $files );
		return array(
			'associations'           => isset( $result['associations'] ) && is_array( $result['associations'] ) ? $result['associations'] : array(),
			'unassociated_subtitles' => isset( $result['unassociated_subtitles'] ) && is_array( $result['unassociated_subtitles'] ) ? $result['unassociated_subtitles'] : array(),
			'warnings'               => isset( $result['warnings'] ) && is_array( $result['warnings'] ) ? $result['warnings'] : array(),
		);
	}

	/**
	 * Load media-server association module from the monorepo (read-only PHP, no HTTP).
	 */
	private static function ensure_association_loaded() {
		if ( function_exists( 'media_associate_movie_files' ) ) {
			return;
		}

		$candidates = array();
		if ( defined( 'MOVIES_WP_MEDIA_AUTOMATION_DIR' ) ) {
			// includes/ → plugin → custom-plugins → plugins → wp-content → repo root
			$candidates[] = dirname( MOVIES_WP_MEDIA_AUTOMATION_DIR, 5 ) . '/media-server/lib/media-association.php';
			$candidates[] = dirname( MOVIES_WP_MEDIA_AUTOMATION_DIR, 4 ) . '/media-server/lib/media-association.php';
		}
		$candidates[] = dirname( __DIR__, 5 ) . '/media-server/lib/media-association.php';
		$candidates[] = dirname( __DIR__, 6 ) . '/media-server/lib/media-association.php';

		foreach ( $candidates as $path ) {
			if ( is_string( $path ) && is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}

	/**
	 * Plan Streamit `_source` rows from scanned videos + existing meta (read-only).
	 *
	 * Identity key: normalized link, else normalized download_content.
	 * Planned scanned rows use media_path for both link and download_content.
	 * Actions: add | update | keep_existing. Never delete.
	 *
	 * @param list<array<string, mixed>> $files
	 * @param list<array<string, mixed>> $existing_sources
	 * @return list<array<string, mixed>>
	 */
	private static function plan_sources( array $files, array $existing_sources ) {
		$existing_by_path = array();
		foreach ( $existing_sources as $row ) {
			$path = self::normalize_source_path( $row );
			if ( null !== $path ) {
				// First wins if duplicates share the same identity key.
				if ( ! isset( $existing_by_path[ $path ] ) ) {
					$existing_by_path[ $path ] = $row;
				}
			}
		}

		$planned = array();
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) || ( $file['kind'] ?? '' ) !== 'video' ) {
				continue;
			}

			$detected = isset( $file['detected'] ) && is_array( $file['detected'] ) ? $file['detected'] : array();
			$path     = self::detected_media_path( $file, $detected );
			if ( null === $path ) {
				continue;
			}

			$quality = self::detected_identity_value( $detected, 'quality' );
			if ( null === $quality && isset( $file['quality'] ) ) {
				$quality = self::string_or_null( $file['quality'] );
			}

			$source_type = self::detected_identity_value( $detected, 'source_type' );
			if ( null === $source_type && isset( $file['source_type'] ) ) {
				$source_type = self::string_or_null( $file['source_type'] );
			}

			$provider = self::detected_identity_value( $detected, 'provider' );
			if ( null === $provider && isset( $file['provider'] ) ) {
				$provider = self::string_or_null( $file['provider'] );
			}

			$encoder = null;
			if ( isset( $detected['release']['encoder'] ) ) {
				$encoder = self::string_or_null( $detected['release']['encoder'] );
			} elseif ( isset( $file['encoder'] ) ) {
				$encoder = self::string_or_null( $file['encoder'] );
			}

			$size_label = null;
			if ( isset( $detected['size_label'] ) ) {
				$size_label = self::string_or_null( $detected['size_label'] );
			} elseif ( isset( $file['size_label'] ) ) {
				$size_label = self::string_or_null( $file['size_label'] );
			}

			$audio = array(
				'tracks'             => isset( $detected['audio']['tracks'] ) && is_array( $detected['audio']['tracks'] )
					? $detected['audio']['tracks']
					: array(),
				'label'              => isset( $detected['audio']['label'] ) ? self::string_or_null( $detected['audio']['label'] ) : self::string_or_null( $file['audio_label'] ?? null ),
				'confidence'         => isset( $detected['audio']['confidence'] )
					? (string) $detected['audio']['confidence']
					: (string) ( $file['audio_confidence'] ?? 'unknown' ),
				'languages_filename' => isset( $detected['audio']['languages_filename'] ) && is_array( $detected['audio']['languages_filename'] )
					? $detected['audio']['languages_filename']
					: ( isset( $file['audio_languages'] ) && is_array( $file['audio_languages'] ) ? $file['audio_languages'] : array() ),
			);

			// Do NOT map ffprobe tracks (or country/title/subs) into Streamit language yet.
			$language = null;

			$existing_row = null;
			$action       = 'add';
			$action_reason = 'new_media_path_not_in_existing_sources';
			if ( isset( $existing_by_path[ $path ] ) ) {
				$action        = 'update';
				$action_reason = 'identity_match_normalized_link_or_download_content';
				$existing_row  = $existing_by_path[ $path ];
				unset( $existing_by_path[ $path ] );
			}

			/*
			 * Streamit name = allowlisted encoder only.
			 * On update: if detected encoder is empty, preserve existing manual name
			 * (do not wipe a human-entered encoder with "").
			 * On add: empty encoder → name "".
			 * Never use release_group / group_hint / provider / SS / codecs as name.
			 */
			$name            = null !== $encoder ? $encoder : '';
			$name_source     = null !== $encoder ? 'detected_encoder_allowlist' : 'empty_unknown_encoder';
			$preserved_name  = false;
			if ( 'update' === $action && null === $encoder && is_array( $existing_row ) ) {
				$existing_name = isset( $existing_row['name'] ) ? trim( (string) $existing_row['name'] ) : '';
				if ( '' !== $existing_name ) {
					$name           = $existing_name;
					$name_source    = 'preserved_existing_manual_name';
					$preserved_name = true;
				}
			}

			$planned[] = array(
				'media_path'       => $path,
				'identity_key'     => $path,
				'action'           => $action,
				'action_reason'    => $action_reason,
				'quality'          => $quality,
				'source_type'      => $source_type,
				'provider'         => $provider,
				'encoder'          => $encoder,
				'name'             => $name,
				'link'             => $path,
				'download_content' => $path,
				'file_size'        => $size_label,
				'language'         => $language,
				'audio'            => $audio,
				'release'          => array(
					'release_group' => isset( $detected['release']['release_group'] )
						? self::string_or_null( $detected['release']['release_group'] )
						: self::string_or_null( $file['release_group'] ?? null ),
					'group_hint'    => isset( $detected['release']['group_hint'] )
						? self::string_or_null( $detected['release']['group_hint'] )
						: self::string_or_null( $file['group_hint'] ?? null ),
					'unclassified'  => isset( $detected['release']['unclassified'] ) && is_array( $detected['release']['unclassified'] )
						? $detected['release']['unclassified']
						: ( isset( $file['unclassified'] ) && is_array( $file['unclassified'] ) ? $file['unclassified'] : array() ),
				),
				'detected'         => $detected !== array() ? $detected : null,
				'existing_row'     => $existing_row,
				'field_sources'    => array(
					'quality'          => 'detected.identity.quality (filename)',
					'source_type'      => 'detected.identity.source_type (filename)',
					'provider'         => 'detected.identity.provider (filename)',
					'name'             => $name_source,
					'link'             => 'detected.media_path (filesystem)',
					'download_content' => 'detected.media_path (filesystem)',
					'file_size'        => 'detected.size_label (filesystem)',
					'language'         => 'deferred_null',
					'audio.tracks'     => 'detected.audio.tracks (ffprobe)',
					'audio.label'      => 'detected.audio.label (filename)',
					'audio.confidence' => 'detected.audio.confidence (filename)',
				),
				'streamit_safe'    => array(
					'quality'          => $quality,
					'name'             => $name,
					'link'             => $path,
					'download_content' => $path,
					'file_size'        => $size_label,
					'language'         => null,
					'is_affiliate'     => '0',
					'player'           => '',
				),
				'deferred'         => array(
					'language'              => true,
					'source_type_as_meta'   => true,
					'provider_as_meta'      => true,
					'release_group_as_name' => true,
				),
				'name_preserved_from_existing' => $preserved_name,
			);
		}

		// Existing rows whose identity key was not seen in the scan — preserve (never delete).
		foreach ( $existing_by_path as $path => $row ) {
			$planned[] = array(
				'media_path'       => $path,
				'identity_key'     => $path,
				'action'           => 'keep_existing',
				'action_reason'    => 'existing_source_identity_not_present_in_scan',
				'quality'          => isset( $row['quality'] ) ? self::string_or_null( $row['quality'] ) : null,
				'source_type'      => null,
				'provider'         => null,
				'encoder'          => null,
				'name'             => isset( $row['name'] ) ? (string) $row['name'] : '',
				'link'             => isset( $row['link'] ) ? (string) $row['link'] : $path,
				'download_content' => isset( $row['download_content'] ) ? (string) $row['download_content'] : $path,
				'file_size'        => isset( $row['file_size'] ) ? self::string_or_null( $row['file_size'] ) : null,
				'language'         => isset( $row['language'] ) ? self::string_or_null( $row['language'] ) : null,
				'audio'            => null,
				'release'          => null,
				'detected'         => null,
				'existing_row'     => $row,
				'field_sources'    => array(
					'all' => 'existing_streamit_source_row_unchanged',
				),
				'streamit_safe'    => array(
					'preserve_row' => true,
				),
				'deferred'         => array(),
				'name_preserved_from_existing' => true,
			);
		}

		return $planned;
	}

	/**
	 * Plan subtitle rows from association results.
	 * Associated rows stay as-is. Unassociated movie-directory subtitles with a
	 * valid relative Movie/... path are also planned for _subtitles persistence.
	 * Signed URLs are never planned/generated here.
	 *
	 * @param list<array<string, mixed>> $files
	 * @param array{associations: list, unassociated_subtitles: list} $association
	 * @return array{associated: list, unassociated: list}
	 */
	private static function plan_subtitles( array $files, array $association ) {
		$by_path = array();
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) || ( $file['kind'] ?? '' ) !== 'subtitle' ) {
				continue;
			}
			$path = isset( $file['media_path'] ) ? (string) $file['media_path'] : '';
			if ( '' !== $path ) {
				$by_path[ $path ] = $file;
				$norm             = self::normalize_subtitle_media_path( $path );
				if ( null !== $norm && ! isset( $by_path[ $norm ] ) ) {
					$by_path[ $norm ] = $file;
				}
			}
		}

		$assoc_map = array();
		foreach ( $association['associations'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$sub = isset( $row['subtitle'] ) ? (string) $row['subtitle'] : '';
			if ( '' !== $sub ) {
				$assoc_map[ $sub ] = $row;
			}
		}

		$associated = array();
		$seen       = array();
		foreach ( $assoc_map as $sub_path => $row ) {
			$file     = isset( $by_path[ $sub_path ] ) ? $by_path[ $sub_path ] : array();
			$detected = isset( $file['detected'] ) && is_array( $file['detected'] ) ? $file['detected'] : array();

			$lang = null;
			if ( isset( $detected['subtitle']['language']['value'] ) ) {
				$lang = self::string_or_null( $detected['subtitle']['language']['value'] );
			} elseif ( isset( $file['subtitle_lang'] ) ) {
				$lang = self::string_or_null( $file['subtitle_lang'] );
			}

			$format = null;
			if ( isset( $detected['subtitle']['format'] ) ) {
				$format = self::string_or_null( $detected['subtitle']['format'] );
			} elseif ( isset( $file['extension'] ) ) {
				$format = self::string_or_null( $file['extension'] );
			}

			$associated[] = array(
				'media_path'   => $sub_path,
				'language'     => $lang,
				'format'       => $format,
				'action'       => 'add',
				'persistence'  => 'relative_path',
				'association'  => array(
					'video'      => isset( $row['video'] ) ? (string) $row['video'] : null,
					'confidence' => isset( $row['confidence'] ) ? (string) $row['confidence'] : null,
					'reason'     => isset( $row['reason'] ) ? (string) $row['reason'] : null,
				),
				'detected'     => $detected !== array() ? $detected : null,
				'url_plan'     => array(
					'storage'     => 'relative_path',
					'value'       => $sub_path,
					'signed'      => false,
					'ready'       => true,
					'render_time' => 'streamit_child_resolve_subtitle_url',
				),
				'field_sources' => array(
					'language'   => 'detected.subtitle.language (filename)',
					'format'     => 'detected.subtitle.format / extension',
					'media_path' => 'filesystem',
					'association'=> 'media_associate_movie_files',
				),
			);

			$norm = self::normalize_subtitle_media_path( $sub_path );
			if ( null !== $norm ) {
				$seen[ $norm ] = true;
			}
		}

		$unassociated = array();
		foreach ( $association['unassociated_subtitles'] as $path ) {
			$path = (string) $path;
			$file = isset( $by_path[ $path ] ) ? $by_path[ $path ] : array();
			$norm = self::normalize_subtitle_media_path( $path );
			if ( null !== $norm && isset( $by_path[ $norm ] ) ) {
				$file = $by_path[ $norm ];
			}
			$detected = isset( $file['detected'] ) && is_array( $file['detected'] ) ? $file['detected'] : array();
			$lang = null;
			if ( isset( $detected['subtitle']['language']['value'] ) ) {
				$lang = self::string_or_null( $detected['subtitle']['language']['value'] );
			} elseif ( isset( $file['subtitle_lang'] ) ) {
				$lang = self::string_or_null( $file['subtitle_lang'] );
			}

			$unassociated[] = array(
				'media_path'  => $path,
				'language'    => $lang,
				'reason'      => 'no_deterministic_video_association',
				'persistence' => 'relative_path',
				'detected'    => $detected !== array() ? $detected : null,
			);

			if ( null === $norm || isset( $seen[ $norm ] ) ) {
				continue;
			}

			$format = null;
			if ( isset( $detected['subtitle']['format'] ) ) {
				$format = self::string_or_null( $detected['subtitle']['format'] );
			} elseif ( isset( $file['extension'] ) ) {
				$format = self::string_or_null( $file['extension'] );
			}

			$associated[] = array(
				'media_path'  => $norm,
				'language'    => $lang,
				'format'      => $format,
				'action'      => 'add',
				'persistence' => 'relative_path',
				'association' => null,
				'reason'      => 'unassociated_movie_directory',
				'detected'    => $detected !== array() ? $detected : null,
				'url_plan'    => array(
					'storage'     => 'relative_path',
					'value'       => $norm,
					'signed'      => false,
					'ready'       => true,
					'render_time' => 'streamit_child_resolve_subtitle_url',
				),
				'field_sources' => array(
					'language'    => 'detected.subtitle.language (filename)',
					'format'      => 'detected.subtitle.format / extension',
					'media_path'  => 'filesystem',
					'association' => 'none',
				),
			);
			$seen[ $norm ] = true;
		}

		return array(
			'associated'   => $associated,
			'unassociated' => $unassociated,
		);
	}

	/**
	 * @param list<array> $errors
	 * @param list<array> $warnings
	 * @param array       $block
	 * @param string      $file
	 */
	private static function merge_issues( array &$errors, array &$warnings, $block, $file = '' ) {
		if ( ! is_array( $block ) ) {
			return;
		}
		if ( isset( $block['errors'] ) && is_array( $block['errors'] ) ) {
			foreach ( $block['errors'] as $issue ) {
				if ( is_array( $issue ) ) {
					$errors[] = self::normalize_issue( $issue, $file );
				}
			}
		}
		if ( isset( $block['warnings'] ) && is_array( $block['warnings'] ) ) {
			foreach ( $block['warnings'] as $issue ) {
				if ( is_array( $issue ) ) {
					$warnings[] = self::normalize_issue( $issue, $file );
				}
			}
		}
	}

	/**
	 * @param array<string, mixed> $issue
	 * @return array{code: string, message: string, file?: string}
	 */
	private static function normalize_issue( array $issue, $file = '' ) {
		$code = isset( $issue['code'] ) ? (string) $issue['code'] : 'unknown';
		$msg  = isset( $issue['message'] ) ? (string) $issue['message'] : '';
		$out  = self::issue( $code, $msg, $file );
		if ( isset( $issue['file'] ) && is_string( $issue['file'] ) && $issue['file'] !== '' ) {
			$out['file'] = $issue['file'];
		}
		if ( isset( $issue['filename_value'] ) ) {
			$out['filename_value'] = $issue['filename_value'];
		}
		if ( isset( $issue['probe_value'] ) ) {
			$out['probe_value'] = $issue['probe_value'];
		}
		return $out;
	}

	/**
	 * @return array{code: string, message: string, file?: string}
	 */
	private static function issue( $code, $message, $file = '' ) {
		$issue = array(
			'code'    => (string) $code,
			'message' => (string) $message,
		);
		if ( '' !== $file ) {
			$issue['file'] = (string) $file;
		}
		return $issue;
	}

	/**
	 * @param list<array<string, mixed>> $issues
	 * @return list<array<string, mixed>>
	 */
	private static function unique_issues( array $issues ) {
		$seen = array();
		$out  = array();
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$code = (string) ( $issue['code'] ?? '' );
			if ( self::is_internal_warning_code( $code ) ) {
				continue;
			}
			$key = $code . '|' . ( $issue['message'] ?? '' ) . '|' . ( $issue['file'] ?? '' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $issue;
		}
		return $out;
	}

	/**
	 * Informational probe/reconciliation codes — not user-facing import warnings.
	 */
	private static function is_internal_warning_code( $code ) {
		return in_array(
			(string) $code,
			array(
				'probe_audio_language_detected',
				'probe_audio_language_unknown',
				'unconfirmed_group',
			),
			true
		);
	}

	/**
	 * @param array<string, mixed> $file
	 * @param array<string, mixed> $detected
	 */
	private static function detected_media_path( array $file, array $detected ) {
		if ( isset( $detected['media_path'] ) ) {
			$path = self::string_or_null( $detected['media_path'] );
			if ( null !== $path ) {
				return self::normalize_path_key( $path );
			}
		}
		if ( isset( $file['media_path'] ) ) {
			$path = self::string_or_null( $file['media_path'] );
			if ( null !== $path ) {
				return self::normalize_path_key( $path );
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $detected
	 */
	private static function detected_identity_value( array $detected, $key ) {
		if ( isset( $detected['identity'][ $key ]['value'] ) ) {
			return self::string_or_null( $detected['identity'][ $key ]['value'] );
		}
		return null;
	}

	/**
	 * Existing `_source` identity: normalized link, else normalized download_content.
	 * Quality / provider / encoder / source_type are never identity keys.
	 *
	 * @param array<string, mixed> $row
	 */
	private static function normalize_source_path( array $row ) {
		$link = isset( $row['link'] ) ? self::string_or_null( $row['link'] ) : null;
		if ( null !== $link ) {
			return self::normalize_path_key( $link );
		}
		$dl = isset( $row['download_content'] ) ? self::string_or_null( $row['download_content'] ) : null;
		if ( null !== $dl ) {
			return self::normalize_path_key( $dl );
		}
		return null;
	}

	private static function normalize_path_key( $path ) {
		$path = str_replace( '\\', '/', (string) $path );
		$path = trim( $path );
		$path = ltrim( $path, '/' );
		return '' === $path ? null : $path;
	}

	/**
	 * Relative Movie/... subtitle path only. Rejects /data, http(s), and signed tokens.
	 *
	 * @param mixed $path
	 * @return string|null
	 */
	private static function normalize_subtitle_media_path( $path ) {
		$path = self::normalize_path_key( $path );
		if ( null === $path ) {
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

	private static function string_or_null( $value ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		return '' === $value ? null : $value;
	}
}

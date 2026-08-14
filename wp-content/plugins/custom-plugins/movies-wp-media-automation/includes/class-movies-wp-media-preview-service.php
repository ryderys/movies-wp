<?php
/**
 * Side-effect-free movie import preview.
 *
 * Combines admin input, TMDb metadata, and a media-server scan.
 * Does not write to Streamit, WordPress posts, or the media server.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Media_Preview_Service {

	/**
	 * Build a preview for the given admin input.
	 *
	 * @param array<string, mixed> $input {
	 *     @type int|string $tmdb_id
	 *     @type string     $title
	 *     @type string     $summary
	 *     @type string     $media_directory
	 * }
	 * @return array<string, mixed>|WP_Error
	 */
	public static function build( array $input ) {
		$normalized = self::normalize_input( $input );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$tmdb = Movies_WP_Tmdb_Preview_Client::get_movie( $normalized['tmdb_id'] );
		if ( is_wp_error( $tmdb ) ) {
			return $tmdb;
		}

		$media = Movies_WP_Media_Api_Client::scan_movie_directory( $normalized['media_directory'] );
		if ( is_wp_error( $media ) ) {
			return self::wrap_media_error( $media );
		}

		$validation = self::validate( $normalized, $tmdb, $media );

		return array(
			'ok'              => true,
			'input'           => $normalized,
			'tmdb'            => $tmdb,
			'media'           => array(
				'directory'  => $media['directory'] ?? $normalized['media_directory'],
				'country'    => $media['country'] ?? null,
				'year'       => $media['year'] ?? null,
				'movie_name' => $media['movie_name'] ?? null,
				'files'      => isset( $media['files'] ) && is_array( $media['files'] ) ? $media['files'] : array(),
				'warnings'   => isset( $media['warnings'] ) && is_array( $media['warnings'] ) ? $media['warnings'] : array(),
			),
			'validation'      => $validation,
			'ready_to_import' => empty( $validation['errors'] ),
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{tmdb_id: int, title: string, summary: string, media_directory: string}|WP_Error
	 */
	private static function normalize_input( array $input ) {
		$tmdb_id = isset( $input['tmdb_id'] ) ? absint( $input['tmdb_id'] ) : 0;
		$title   = isset( $input['title'] ) ? trim( (string) $input['title'] ) : '';
		$summary = isset( $input['summary'] ) ? trim( (string) $input['summary'] ) : '';
		$dir     = isset( $input['media_directory'] ) ? $input['media_directory'] : '';

		if ( $tmdb_id <= 0 ) {
			return new WP_Error( 'media_preview_invalid_input', 'TMDb ID must be a positive integer.' );
		}

		if ( '' === $title ) {
			return new WP_Error( 'media_preview_invalid_input', 'Title is required.' );
		}

		$directory = Movies_WP_Media_Api_Client::normalize_directory( $dir );
		if ( is_wp_error( $directory ) ) {
			return new WP_Error( 'media_preview_invalid_input', 'Media directory must be a relative Movie path.' );
		}

		return array(
			'tmdb_id'          => $tmdb_id,
			'title'            => $title,
			'summary'          => $summary,
			'media_directory'  => $directory,
		);
	}

	/**
	 * Codes that are internal diagnostics — never shown in the admin Warnings UI.
	 * Probe language facts remain under file validation/detected facts.
	 */
	private static function internal_warning_codes() {
		return array(
			'probe_audio_language_detected' => true,
			'probe_audio_language_unknown'  => true,
			// group_hint already stores the token; this only narrates that classification.
			'unconfirmed_group'             => true,
		);
	}

	/**
	 * Canonical user-facing warning aggregation for Scan & Preview.
	 * File-level parser warnings are the source for group/unclassified/subtitle-parser messages.
	 * Folder-level rules add quality/audio/duplicate/year issues once.
	 *
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $tmdb
	 * @param array<string, mixed> $media
	 * @return array{errors: list<array<string, mixed>>, warnings: list<array<string, mixed>>}
	 */
	private static function validate( array $input, array $tmdb, array $media ) {
		$errors   = array();
		$warnings = array();
		$files    = isset( $media['files'] ) && is_array( $media['files'] ) ? $media['files'] : array();

		$videos    = array();
		$subtitles = array();
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			$kind = $file['kind'] ?? '';
			if ( 'video' === $kind ) {
				$videos[] = $file;
			} elseif ( 'subtitle' === $kind ) {
				$subtitles[] = $file;
			}
		}

		if ( $videos === array() ) {
			$errors[] = self::issue( 'no_video_files', 'No video files were detected.' );
		}

		$quality_keys = array();
		foreach ( $videos as $file ) {
			$name = isset( $file['name'] ) ? (string) $file['name'] : '';

			$quality = isset( $file['quality'] ) ? trim( (string) $file['quality'] ) : '';
			if ( '' === $quality ) {
				$warnings[] = self::issue( 'quality_unknown', 'Quality could not be detected.', $name );
			}

			// User-facing audio_unknown only when filename is unknown AND probe has no language tags.
			$confidence   = isset( $file['audio_confidence'] ) ? (string) $file['audio_confidence'] : 'unknown';
			$probe_langs  = self::probe_audio_languages( $file );
			$filename_unknown = ( 'unknown' === $confidence || '' === $confidence );
			if ( $filename_unknown && $probe_langs === array() ) {
				$warnings[] = self::issue( 'audio_unknown', 'Audio language could not be detected.', $name );
			}

			$source = isset( $file['source_type'] ) ? trim( (string) $file['source_type'] ) : '';
			$key    = strtolower( $quality . '|' . $source );
			if ( '' !== $quality ) {
				if ( isset( $quality_keys[ $key ] ) ) {
					$warnings[] = self::issue(
						'duplicate_quality',
						'Duplicate quality detected: ' . trim( $quality . ' ' . $source ) . '.',
						$name
					);
				} else {
					$quality_keys[ $key ] = true;
				}
			}
		}

		// One user-facing subtitle warning per file (prefer folder wording).
		foreach ( $subtitles as $file ) {
			$lang = $file['subtitle_lang'] ?? null;
			if ( null === $lang || '' === $lang ) {
				$name       = isset( $file['name'] ) ? (string) $file['name'] : '';
				$warnings[] = self::issue( 'subtitle_lang_unknown', 'Subtitle language could not be detected.', $name );
			}
		}

		if ( ! empty( $media['warnings'] ) && is_array( $media['warnings'] ) ) {
			foreach ( $media['warnings'] as $warning ) {
				if ( ! is_array( $warning ) ) {
					continue;
				}
				$code = isset( $warning['code'] ) ? (string) $warning['code'] : 'media_warning';
				if ( isset( self::internal_warning_codes()[ $code ] ) ) {
					continue;
				}
				$msg  = isset( $warning['message'] ) ? (string) $warning['message'] : 'Media scan warning.';
				$name = isset( $warning['name'] ) ? (string) $warning['name'] : '';
				$warnings[] = self::issue( $code, $msg, $name );
			}
		}

		// Parser per-file warnings (unclassified, etc.). Skip subtitle_lang_unknown
		// (folder-level subtitle loop) and internal diagnostics (unconfirmed_group, probe audio).
		$skip_file_codes = array_merge(
			self::internal_warning_codes(),
			array( 'subtitle_lang_unknown' => true )
		);
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) || empty( $file['warnings'] ) || ! is_array( $file['warnings'] ) ) {
				continue;
			}
			$name = isset( $file['name'] ) ? (string) $file['name'] : '';
			foreach ( $file['warnings'] as $warning ) {
				if ( ! is_array( $warning ) ) {
					continue;
				}
				$code = isset( $warning['code'] ) ? (string) $warning['code'] : 'unclassified_tokens';
				if ( isset( $skip_file_codes[ $code ] ) ) {
					continue;
				}
				$msg = isset( $warning['message'] ) ? (string) $warning['message'] : 'Unclassified filename tokens.';
				$warnings[] = self::issue( $code, $msg, $name );
			}
		}

		// Blocking validation errors from media-server (never duplicate as warnings).
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) || empty( $file['validation']['errors'] ) || ! is_array( $file['validation']['errors'] ) ) {
				continue;
			}
			$name = isset( $file['name'] ) ? (string) $file['name'] : '';
			foreach ( $file['validation']['errors'] as $issue ) {
				if ( ! is_array( $issue ) ) {
					continue;
				}
				$code = isset( $issue['code'] ) ? (string) $issue['code'] : 'validation_error';
				$msg  = isset( $issue['message'] ) ? (string) $issue['message'] : 'Validation error.';
				$errors[] = self::issue( $code, $msg, $name );
			}
		}

		$tmdb_year  = isset( $tmdb['year'] ) ? (int) $tmdb['year'] : 0;
		$media_year = isset( $media['year'] ) ? (int) $media['year'] : 0;
		if ( $tmdb_year > 0 && $media_year > 0 && $tmdb_year !== $media_year ) {
			$warnings[] = self::issue(
				'year_mismatch',
				sprintf(
					'Media directory year (%d) differs from TMDb year (%d). TMDb ID remains authoritative.',
					$media_year,
					$tmdb_year
				)
			);
		}

		return array(
			'errors'   => self::unique_issues( $errors ),
			'warnings' => self::unique_issues( $warnings ),
		);
	}

	/**
	 * @param array<string, mixed> $file
	 * @return list<string>
	 */
	private static function probe_audio_languages( array $file ) {
		if ( isset( $file['validation']['facts']['probe_audio_languages'] ) && is_array( $file['validation']['facts']['probe_audio_languages'] ) ) {
			$out = array();
			foreach ( $file['validation']['facts']['probe_audio_languages'] as $lang ) {
				if ( is_string( $lang ) && '' !== trim( $lang ) ) {
					$out[] = strtolower( trim( $lang ) );
				}
			}
			return array_values( array_unique( $out ) );
		}

		$audio = isset( $file['probe']['audio'] ) && is_array( $file['probe']['audio'] ) ? $file['probe']['audio'] : array();
		$out   = array();
		foreach ( $audio as $track ) {
			if ( ! is_array( $track ) ) {
				continue;
			}
			$lang = isset( $track['language'] ) ? strtolower( trim( (string) $track['language'] ) ) : '';
			if ( '' === $lang || 'und' === $lang ) {
				continue;
			}
			$out[] = $lang;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Stable dedupe: code + file + message.
	 *
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
			$key = (string) ( $issue['code'] ?? '' ) . '|' . (string) ( $issue['file'] ?? '' ) . '|' . (string) ( $issue['message'] ?? '' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $issue;
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
			$issue['file'] = $file;
		}
		return $issue;
	}

	/**
	 * @param WP_Error $error
	 * @return WP_Error
	 */
	private static function wrap_media_error( $error ) {
		$code = $error->get_error_code();
		if ( 'media_api_invalid_dir' === $code ) {
			return new WP_Error( 'media_preview_invalid_input', 'Media directory must be a relative Movie path.' );
		}

		$data = $error->get_error_data();
		return new WP_Error(
			'media_preview_media_error',
			$error->get_error_message(),
			is_array( $data ) ? $data : array()
		);
	}
}

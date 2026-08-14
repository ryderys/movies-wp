<?php
/**
 * Import execution service — rebuilds Preview + Import Plan, rechecks identity,
 * then calls Movies_WP_Streamit_Adapter::apply().
 *
 * Does not write Streamit/WordPress directly. The adapter is the only persistence layer.
 * Does not trust browser-submitted plans, source rows, or ready_to_import flags.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Media_Import_Service {

	/**
	 * Execute an import from admin-submitted *inputs* (not a client plan).
	 *
	 * @param array<string, mixed> $request {
	 *     @type int|string $tmdb_id
	 *     @type string     $title
	 *     @type string     $summary
	 *     @type string     $media_directory
	 *     @type mixed      $confirm_import  Must be truthy / "1".
	 * }
	 * @param array{
	 *   preview_build?: callable(array): (array|WP_Error),
	 *   plan_build?: callable(array,array): (array|WP_Error),
	 *   find_by_tmdb?: callable(int): array{ids: list<int>},
	 *   movie_exists?: callable(int): bool,
	 *   adapter_apply?: callable(array): array
	 * } $options Test hooks.
	 * @return array<string, mixed>
	 */
	public static function execute( array $request, array $options = array() ) {
		$confirm = isset( $request['confirm_import'] ) ? $request['confirm_import'] : null;
		if ( ! self::is_confirmed( $confirm ) ) {
			return self::fail(
				'media_import_confirmation_required',
				'Import confirmation is required.'
			);
		}

		$input = self::normalize_input( $request );
		if ( is_wp_error( $input ) ) {
			return self::fail_from_wp_error( $input, 'media_import_invalid_input' );
		}

		$preview = self::build_preview( $input, $options );
		if ( is_wp_error( $preview ) ) {
			return self::fail_from_wp_error( $preview, 'media_import_invalid_input' );
		}

		$plan = self::build_plan( $preview, $options );
		if ( is_wp_error( $plan ) ) {
			return self::fail_from_wp_error( $plan, 'media_import_not_ready' );
		}

		$gate = self::verify_plan( $plan );
		if ( is_wp_error( $gate ) ) {
			return self::fail_from_wp_error( $gate, $gate->get_error_code() );
		}

		$recheck = self::recheck_identity( $plan, $options );
		if ( is_wp_error( $recheck ) ) {
			return self::fail_from_wp_error( $recheck, $recheck->get_error_code() );
		}

		$adapter_result = self::apply_adapter( $plan, $options );
		if ( ! is_array( $adapter_result ) ) {
			return self::fail(
				'media_import_execution_failed',
				'Import adapter returned an unexpected result.'
			);
		}

		return self::present_adapter_result( $adapter_result, $plan );
	}

	/**
	 * @param mixed $confirm
	 */
	private static function is_confirmed( $confirm ) {
		if ( true === $confirm || 1 === $confirm || '1' === $confirm ) {
			return true;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array{tmdb_id:int,title:string,summary:string,media_directory:string}|WP_Error
	 */
	private static function normalize_input( array $request ) {
		$tmdb_id = isset( $request['tmdb_id'] ) ? absint( $request['tmdb_id'] ) : 0;
		$title   = isset( $request['title'] ) ? trim( (string) $request['title'] ) : '';
		$summary = isset( $request['summary'] ) ? (string) $request['summary'] : '';
		$dir     = isset( $request['media_directory'] ) ? (string) $request['media_directory'] : '';

		if ( $tmdb_id <= 0 ) {
			return new WP_Error( 'media_import_invalid_input', 'TMDb Movie ID must be a positive number.' );
		}
		if ( '' === $title ) {
			return new WP_Error( 'media_import_invalid_input', 'Persian / local title is required.' );
		}

		if ( class_exists( 'Movies_WP_Media_Api_Client' ) ) {
			$normalized_dir = Movies_WP_Media_Api_Client::normalize_directory( $dir );
			if ( is_wp_error( $normalized_dir ) ) {
				return new WP_Error( 'media_import_invalid_input', 'Invalid movie directory.' );
			}
			$dir = $normalized_dir;
		} else {
			$dir = str_replace( '\\', '/', trim( $dir ) );
			$dir = ltrim( $dir, '/' );
			if ( '' === $dir || ! str_starts_with( $dir, 'Movie/' ) || str_contains( $dir, '/data' ) || str_starts_with( $dir, '/' ) ) {
				return new WP_Error( 'media_import_invalid_input', 'Invalid movie directory.' );
			}
		}

		return array(
			'tmdb_id'         => $tmdb_id,
			'title'           => $title,
			'summary'         => $summary,
			'media_directory' => $dir,
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|WP_Error
	 */
	private static function build_preview( array $input, array $options ) {
		if ( isset( $options['preview_build'] ) && is_callable( $options['preview_build'] ) ) {
			return call_user_func( $options['preview_build'], $input );
		}
		return Movies_WP_Media_Preview_Service::build( $input );
	}

	/**
	 * @param array<string, mixed> $preview
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|WP_Error
	 */
	private static function build_plan( array $preview, array $options ) {
		$plan_opts = array();
		if ( isset( $options['find_by_tmdb'] ) && is_callable( $options['find_by_tmdb'] ) ) {
			$plan_opts['find_by_tmdb'] = $options['find_by_tmdb'];
		}
		if ( isset( $options['get_sources'] ) && is_callable( $options['get_sources'] ) ) {
			$plan_opts['get_sources'] = $options['get_sources'];
		}

		if ( isset( $options['plan_build'] ) && is_callable( $options['plan_build'] ) ) {
			return call_user_func( $options['plan_build'], $preview, $plan_opts );
		}
		return Movies_WP_Media_Import_Plan::build( $preview, $plan_opts );
	}

	/**
	 * @param array<string, mixed> $plan
	 * @return true|WP_Error
	 */
	private static function verify_plan( array $plan ) {
		if ( empty( $plan['ok'] ) ) {
			return new WP_Error( 'media_import_not_ready', 'Import plan is not valid.' );
		}
		if ( empty( $plan['ready_to_import'] ) ) {
			return new WP_Error( 'media_import_not_ready', 'Import plan is not ready to import.' );
		}
		if ( ! empty( $plan['errors'] ) && is_array( $plan['errors'] ) && array() !== $plan['errors'] ) {
			return new WP_Error( 'media_import_not_ready', 'Import plan contains errors and cannot be executed.' );
		}
		$action = isset( $plan['identity']['action'] ) ? (string) $plan['identity']['action'] : '';
		if ( ! in_array( $action, array( 'create', 'update' ), true ) ) {
			return new WP_Error( 'media_import_not_ready', 'Import plan identity action must be create or update.' );
		}
		return true;
	}

	/**
	 * Final identity recheck immediately before adapter execution.
	 * Never silently switches create↔update.
	 *
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return true|WP_Error
	 */
	private static function recheck_identity( array $plan, array $options ) {
		$action  = (string) $plan['identity']['action'];
		$tmdb_id = isset( $plan['movie']['tmdb_id'] ) ? absint( $plan['movie']['tmdb_id'] ) : 0;
		$ids     = self::lookup_tmdb_ids( $tmdb_id, $options );

		if ( 'create' === $action ) {
			if ( count( $ids ) > 0 ) {
				return new WP_Error(
					'media_import_duplicate_identity',
					'A Streamit movie with this TMDb ID already exists. Import aborted to avoid creating a duplicate.'
				);
			}
			return true;
		}

		// update
		$expected = isset( $plan['identity']['existing_movie_id'] ) ? absint( $plan['identity']['existing_movie_id'] ) : 0;
		if ( $expected <= 0 ) {
			return new WP_Error( 'media_import_identity_changed', 'Update plan is missing an existing movie ID. Refresh the preview and try again.' );
		}

		if ( ! self::movie_exists( $expected, $options ) ) {
			return new WP_Error(
				'media_import_identity_changed',
				'The target Streamit movie no longer exists. Refresh the preview and try again.'
			);
		}

		if ( 0 === count( $ids ) ) {
			return new WP_Error(
				'media_import_identity_changed',
				'TMDb identity no longer matches an existing movie. Refresh the preview and try again.'
			);
		}
		if ( count( $ids ) > 1 ) {
			return new WP_Error(
				'media_import_duplicate_identity',
				'Multiple Streamit movies now share this TMDb ID. Resolve duplicates before importing.'
			);
		}
		if ( (int) $ids[0] !== $expected ) {
			return new WP_Error(
				'media_import_identity_changed',
				'The Streamit movie matched by TMDb ID changed since planning. Refresh the preview and try again.'
			);
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<int>
	 */
	private static function lookup_tmdb_ids( $tmdb_id, array $options ) {
		if ( isset( $options['find_by_tmdb'] ) && is_callable( $options['find_by_tmdb'] ) ) {
			$found = call_user_func( $options['find_by_tmdb'], (int) $tmdb_id );
			$ids   = array();
			if ( is_array( $found ) && isset( $found['ids'] ) && is_array( $found['ids'] ) ) {
				foreach ( $found['ids'] as $id ) {
					$ids[] = (int) $id;
				}
			}
			return array_values( array_unique( array_filter( $ids ) ) );
		}

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
						'value'   => (string) (int) $tmdb_id,
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
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function movie_exists( $movie_id, array $options ) {
		if ( isset( $options['movie_exists'] ) && is_callable( $options['movie_exists'] ) ) {
			return (bool) call_user_func( $options['movie_exists'], (int) $movie_id );
		}
		if ( ! function_exists( 'streamit_get_movie' ) ) {
			return false;
		}
		$movie = streamit_get_movie( (int) $movie_id );
		return (bool) $movie;
	}

	/**
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function apply_adapter( array $plan, array $options ) {
		if ( isset( $options['adapter_apply'] ) && is_callable( $options['adapter_apply'] ) ) {
			return call_user_func( $options['adapter_apply'], $plan );
		}
		return Movies_WP_Streamit_Adapter::apply( $plan );
	}

	/**
	 * @param array<string, mixed> $adapter
	 * @param array<string, mixed> $plan
	 * @return array<string, mixed>
	 */
	private static function present_adapter_result( array $adapter, array $plan ) {
		$movie_id = isset( $adapter['movie_id'] ) ? $adapter['movie_id'] : null;
		$action   = isset( $adapter['identity_action'] ) ? (string) $adapter['identity_action'] : (string) ( $plan['identity']['action'] ?? '' );
		$dir      = isset( $plan['movie']['media_directory'] ) ? self::safe_relative_path( (string) $plan['movie']['media_directory'] ) : null;
		$stats    = isset( $adapter['source_stats'] ) && is_array( $adapter['source_stats'] ) ? $adapter['source_stats'] : array(
			'added'   => 0,
			'updated' => 0,
			'kept'    => 0,
		);
		$sub_stats = isset( $adapter['subtitle_stats'] ) && is_array( $adapter['subtitle_stats'] ) ? $adapter['subtitle_stats'] : array(
			'added'   => 0,
			'updated' => 0,
			'kept'    => 0,
		);
		$completed = isset( $adapter['completed'] ) && is_array( $adapter['completed'] ) ? array_values( $adapter['completed'] ) : array();
		$deferred  = isset( $adapter['deferred'] ) && is_array( $adapter['deferred'] ) ? array_values( $adapter['deferred'] ) : array();

		if ( ! empty( $adapter['ok'] ) ) {
			return array(
				'ok'              => true,
				'partial'         => false,
				'code'            => null,
				'message'         => 'create' === $action
					? 'Movie imported successfully.'
					: 'Movie updated successfully.',
				'movie_id'        => null !== $movie_id ? (int) $movie_id : null,
				'identity_action' => $action,
				'media_directory' => $dir,
				'source_stats'    => array(
					'added'   => isset( $stats['added'] ) ? (int) $stats['added'] : 0,
					'updated' => isset( $stats['updated'] ) ? (int) $stats['updated'] : 0,
					'kept'    => isset( $stats['kept'] ) ? (int) $stats['kept'] : 0,
				),
				'subtitle_stats'  => array(
					'added'   => isset( $sub_stats['added'] ) ? (int) $sub_stats['added'] : 0,
					'updated' => isset( $sub_stats['updated'] ) ? (int) $sub_stats['updated'] : 0,
					'kept'    => isset( $sub_stats['kept'] ) ? (int) $sub_stats['kept'] : 0,
				),
				'completed'       => $completed,
				'failed_step'     => null,
				'error'           => null,
				'deferred'        => $deferred,
				'warnings'        => isset( $adapter['warnings'] ) && is_array( $adapter['warnings'] ) ? $adapter['warnings'] : array(),
				'adapter'         => $adapter,
			);
		}

		$error = isset( $adapter['error'] ) && is_array( $adapter['error'] )
			? array(
				'code'    => self::safe_text( (string) ( $adapter['error']['code'] ?? 'media_adapter_error' ) ),
				'message' => self::safe_text( (string) ( $adapter['error']['message'] ?? 'Import failed.' ) ),
			)
			: array(
				'code'    => 'media_import_execution_failed',
				'message' => 'Import failed.',
			);

		$partial = null !== $movie_id && $completed !== array();

		return array(
			'ok'              => false,
			'partial'         => $partial,
			'code'            => $partial ? 'media_import_execution_failed' : 'media_import_execution_failed',
			'message'         => $partial ? 'Import partially completed.' : 'Import failed.',
			'movie_id'        => null !== $movie_id ? (int) $movie_id : null,
			'identity_action' => $action,
			'media_directory' => $dir,
			'source_stats'    => array(
				'added'   => isset( $stats['added'] ) ? (int) $stats['added'] : 0,
				'updated' => isset( $stats['updated'] ) ? (int) $stats['updated'] : 0,
				'kept'    => isset( $stats['kept'] ) ? (int) $stats['kept'] : 0,
			),
			'subtitle_stats'  => array(
				'added'   => isset( $sub_stats['added'] ) ? (int) $sub_stats['added'] : 0,
				'updated' => isset( $sub_stats['updated'] ) ? (int) $sub_stats['updated'] : 0,
				'kept'    => isset( $sub_stats['kept'] ) ? (int) $sub_stats['kept'] : 0,
			),
			'completed'       => $completed,
			'failed_step'     => isset( $adapter['failed_step'] ) ? self::safe_text( (string) $adapter['failed_step'] ) : null,
			'error'           => $error,
			'deferred'        => $deferred,
			'warnings'        => array(),
			'adapter'         => $adapter,
		);
	}

	/**
	 * @param WP_Error $error
	 * @return array<string, mixed>
	 */
	private static function fail_from_wp_error( $error, $fallback_code ) {
		$code = $error->get_error_code();
		if ( ! is_string( $code ) || '' === $code ) {
			$code = $fallback_code;
		}
		// Normalize preview/plan codes into import codes when appropriate.
		if ( str_starts_with( $code, 'media_preview_' ) ) {
			$code = 'media_import_invalid_input';
		}
		if ( 'media_import_plan_invalid_preview' === $code ) {
			$code = 'media_import_not_ready';
		}
		return self::fail( $code, $error->get_error_message() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function fail( $code, $message ) {
		return array(
			'ok'              => false,
			'partial'         => false,
			'code'            => (string) $code,
			'message'         => self::safe_text( (string) $message ),
			'movie_id'        => null,
			'identity_action' => null,
			'media_directory' => null,
			'source_stats'    => array(
				'added'   => 0,
				'updated' => 0,
				'kept'    => 0,
			),
			'subtitle_stats'  => array(
				'added'   => 0,
				'updated' => 0,
				'kept'    => 0,
			),
			'completed'       => array(),
			'failed_step'     => null,
			'error'           => array(
				'code'    => (string) $code,
				'message' => self::safe_text( (string) $message ),
			),
			'deferred'        => array(),
			'warnings'        => array(),
			'adapter'         => null,
		);
	}

	/**
	 * Strip absolute filesystem paths /data and secrets from user-visible text.
	 */
	public static function safe_text( $text ) {
		$text = (string) $text;
		$text = preg_replace( '#(?i)(/data/[^\s]+|[A-Za-z]:\\\\[^\s]+)#', '[path]', $text );
		$text = preg_replace( '#(?i)(hmac|secret|token|authorization|bearer)\s*[:=]\s*\S+#', '$1=[redacted]', $text );
		return is_string( $text ) ? $text : '';
	}

	/**
	 * @return string|null
	 */
	public static function safe_relative_path( $path ) {
		$path = str_replace( '\\', '/', trim( (string) $path ) );
		$path = ltrim( $path, '/' );
		if ( str_starts_with( $path, 'data/' ) ) {
			$path = substr( $path, 5 );
		}
		if ( str_contains( $path, '/data' ) || str_starts_with( $path, '/' ) ) {
			return null;
		}
		if ( ! str_starts_with( $path, 'Movie/' ) ) {
			return null;
		}
		return $path;
	}
}

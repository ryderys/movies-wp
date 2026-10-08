<?php
/**
 * WordPress admin Scan & Preview + Import UI.
 *
 * Collects input, calls Preview Service / Import Service, renders results.
 * Does not write Streamit directly — only Movies_WP_Streamit_Adapter may persist
 * (invoked via Movies_WP_Media_Import_Service).
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Media_Admin {

	const CAP             = 'manage_options';
	const SLUG            = 'movies-wp-media-automation';
	const NONCE           = 'movies_wp_media_scan_preview';
	const IMPORT_NONCE    = 'movies_wp_media_import';
	const ACTION          = 'movies_wp_scan_preview';
	const IMPORT_ACTION   = 'movies_wp_import';
	const PROGRESS_NONCE  = 'movies_wp_movie_import_progress';
	const CANCEL_ACTION   = 'movies_wp_movie_import_cancel';

	/**
	 * @var string|false
	 */
	private static $page_hook = false;

	/**
	 * Notice deferred from an early mutation when redirect is not used.
	 *
	 * @var array{type:string,message:string}|null
	 */
	private static $pending_notice = null;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function register_menu() {
		self::$page_hook = add_menu_page(
			__( 'Movie Automation', 'movies-wp' ),
			__( 'Movie Automation', 'movies-wp' ),
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-video-alt3',
			58
		);
		// Import / Cancel should run before HTML output so redirects work.
		if ( is_string( self::$page_hook ) && '' !== self::$page_hook ) {
			add_action( 'load-' . self::$page_hook, array( __CLASS__, 'handle_load' ) );
		}
	}

	public static function enqueue( $hook ) {
		if ( ! self::$page_hook || self::$page_hook !== $hook ) {
			return;
		}

		$css = MOVIES_WP_MEDIA_AUTOMATION_DIR . '/assets/css/admin-scan-preview.css';
		wp_enqueue_style(
			'movies-wp-media-admin-scan',
			plugins_url( 'assets/css/admin-scan-preview.css', MOVIES_WP_MEDIA_AUTOMATION_FILE ),
			array(),
			file_exists( $css ) ? (string) filemtime( $css ) : '1.0'
		);
	}

	/**
	 * Early POST handler for mutations that redirect (Import, Cancel).
	 */
	public static function handle_load() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( ! isset( $_POST['movies_wp_media_action'] ) ) {
			return;
		}
		$post   = wp_unslash( $_POST );
		$action = sanitize_text_field( (string) $post['movies_wp_media_action'] );
		if ( self::IMPORT_ACTION === $action ) {
			self::handle_import_mutation( $post, array() );
			return;
		}
		if ( self::CANCEL_ACTION === $action ) {
			self::handle_cancel_mutation( $post, array() );
		}
	}

	public static function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'movies-wp' ) );
		}

		$values        = self::empty_values();
		$preview       = null;
		$plan          = null;
		$notice        = self::$pending_notice;
		$import_result = null;
		$recent_jobs   = array();
		self::$pending_notice = null;

		$job_token = isset( $_GET['job_token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['job_token'] ) ) : '';
		if ( '' !== $job_token && 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) && class_exists( 'Movies_WP_Movie_Import_Job_Store' ) ) {
			$job = Movies_WP_Movie_Import_Job_Store::find_by_token( $job_token );
			if ( is_array( $job ) && self::job_owned_by_current_user( $job ) ) {
				include MOVIES_WP_MEDIA_AUTOMATION_DIR . '/includes/views/movie-import-progress.php';
				return;
			}
		}

		if ( class_exists( 'Movies_WP_Movie_Import_Job_Store' ) ) {
			$recent_jobs = Movies_WP_Movie_Import_Job_Store::list_for_owner(
				self::current_user_id( array() ),
				self::current_blog_id( array() ),
				Movies_WP_Movie_Import_Job_Store::RECENT_LIMIT_DEFAULT
			);
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['movies_wp_media_action'] ) ) {
			$action = sanitize_text_field( wp_unslash( (string) $_POST['movies_wp_media_action'] ) );

			// Import is handled on load-{$hook} before output (handle_load()).
			if ( self::ACTION === $action ) {
				check_admin_referer( self::NONCE );

				if ( ! current_user_can( self::CAP ) ) {
					wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'movies-wp' ) );
				}

				$values = self::values_from_post();
				$result = Movies_WP_Media_Preview_Service::build( $values );
				if ( is_wp_error( $result ) ) {
					$notice = self::notice_for_error( $result );
				} else {
					$preview = $result;
					if ( ! empty( $result['input'] ) && is_array( $result['input'] ) ) {
						$values = array_merge( $values, $result['input'] );
					}
					$plan = Movies_WP_Media_Import_Plan::build( $preview );
					if ( is_wp_error( $plan ) ) {
						$plan    = null;
						$notice  = array(
							'type'    => 'error',
							'message' => __( 'Could not build an import plan from this preview.', 'movies-wp' ),
						);
					}
				}
			}
		}

		include MOVIES_WP_MEDIA_AUTOMATION_DIR . '/includes/views/scan-preview.php';
	}

	/**
	 * Enqueue-only Import mutation. Redirects to the progress page on success.
	 *
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $options
	 * @return void
	 */
	private static function handle_import_mutation( array $post, array $options = array() ) {
		$result = self::process_import_request( $post, $options );
		if ( is_wp_error( $result ) ) {
			$notice = array(
				'type'    => 'error',
				'message' => Movies_WP_Media_Import_Service::safe_text( $result->get_error_message() ),
			);
			self::$pending_notice = $notice;
			return;
		}
		$token = is_array( $result ) ? (string) ( $result['token'] ?? '' ) : '';
		self::redirect_to_progress( $token, $options );
	}

	/**
	 * Cancel mutation. Redirects back to progress.
	 *
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $options
	 * @return void
	 */
	private static function handle_cancel_mutation( array $post, array $options = array() ) {
		$gate = self::request_gate( $post, self::PROGRESS_NONCE, 'media_import_forbidden', 'media_import_invalid_nonce', $options );
		if ( is_wp_error( $gate ) ) {
			return;
		}
		$token = isset( $post['job_token'] ) ? sanitize_text_field( (string) $post['job_token'] ) : '';
		if ( '' === $token ) {
			return;
		}
		if ( class_exists( 'Movies_WP_Movie_Import_Job_Runner' ) ) {
			Movies_WP_Movie_Import_Job_Runner::cancel( $token, $options );
		}
		self::redirect_to_progress( $token, $options );
	}

	/**
	 * Capability + nonce gate, then Import Service.
	 * Injectable for tests. Does not write Streamit directly.
	 *
	 * @param array<string, mixed> $post
	 * @param array{
	 *   current_user_can?: callable(string): bool,
	 *   verify_nonce?: callable(string,string): bool,
	 *   import_execute?: callable(array): array
	 * } $options
	 * @return array<string, mixed>|WP_Error
	 */
	public static function process_import_request( array $post, array $options = array() ) {
		$can = isset( $options['current_user_can'] ) && is_callable( $options['current_user_can'] )
			? $options['current_user_can']
			: 'current_user_can';

		if ( ! call_user_func( $can, self::CAP ) ) {
			return new WP_Error( 'media_import_forbidden', __( 'Insufficient capability for import.', 'movies-wp' ) );
		}

		$nonce = isset( $post['_wpnonce'] ) ? (string) $post['_wpnonce'] : '';
		if ( isset( $options['verify_nonce'] ) && is_callable( $options['verify_nonce'] ) ) {
			$ok = (bool) call_user_func( $options['verify_nonce'], $nonce, self::IMPORT_NONCE );
		} else {
			$ok = (bool) wp_verify_nonce( $nonce, self::IMPORT_NONCE );
		}
		if ( ! $ok ) {
			return new WP_Error( 'media_import_invalid_nonce', __( 'Invalid import nonce.', 'movies-wp' ) );
		}

		$request = array(
			'tmdb_id'         => isset( $post['tmdb_id'] ) ? absint( $post['tmdb_id'] ) : 0,
			'title'           => isset( $post['title'] ) ? sanitize_text_field( (string) $post['title'] ) : '',
			'summary'         => isset( $post['summary'] ) ? sanitize_textarea_field( (string) $post['summary'] ) : '',
			'media_directory' => isset( $post['media_directory'] ) ? sanitize_text_field( (string) $post['media_directory'] ) : '',
			'confirm_import'  => isset( $post['confirm_import'] ) ? $post['confirm_import'] : null,
		);

		// Reject any browser-supplied plan / source payloads — they are never used.
		unset( $post['plan'], $post['sources'], $post['ready_to_import'], $post['identity_action'] );

		if ( ! self::is_confirmed( $request['confirm_import'] ?? null ) ) {
			return new WP_Error( 'media_import_confirmation_required', __( 'Import confirmation is required.', 'movies-wp' ) );
		}

		$enqueue = isset( $options['enqueue_job'] ) && is_callable( $options['enqueue_job'] )
			? $options['enqueue_job']
			: ( class_exists( 'Movies_WP_Movie_Import_Job_Runner' ) ? array( 'Movies_WP_Movie_Import_Job_Runner', 'enqueue_from_request' ) : null );
		if ( ! is_callable( $enqueue ) ) {
			return new WP_Error( 'movie_import_runner_missing', __( 'Movie import runner is not available.', 'movies-wp' ) );
		}

		return call_user_func(
			$enqueue,
			$request,
			array(
				'user_id' => self::current_user_id( $options ),
				'blog_id' => self::current_blog_id( $options ),
			),
			$options
		);
	}

	/**
	 * @param mixed $confirm
	 */
	private static function is_confirmed( $confirm ) {
		return ( true === $confirm || 1 === $confirm || '1' === $confirm );
	}

	/**
	 * Capability + nonce gate helper.
	 *
	 * @param array<string, mixed> $post
	 * @param string              $nonce_action
	 * @param string              $forbidden_code
	 * @param string              $nonce_code
	 * @param array<string, mixed> $options
	 * @return true|WP_Error
	 */
	private static function request_gate( array $post, $nonce_action, $forbidden_code, $nonce_code, array $options = array() ) {
		$can = isset( $options['current_user_can'] ) && is_callable( $options['current_user_can'] )
			? $options['current_user_can']
			: 'current_user_can';
		if ( ! call_user_func( $can, self::CAP ) ) {
			return new WP_Error( $forbidden_code, __( 'Insufficient capability for import.', 'movies-wp' ) );
		}
		$nonce = isset( $post['_wpnonce'] ) ? (string) $post['_wpnonce'] : '';
		$ok    = isset( $options['verify_nonce'] ) && is_callable( $options['verify_nonce'] )
			? (bool) call_user_func( $options['verify_nonce'], $nonce, $nonce_action )
			: (bool) wp_verify_nonce( $nonce, $nonce_action );
		if ( ! $ok ) {
			return new WP_Error( $nonce_code, __( 'Invalid import nonce.', 'movies-wp' ) );
		}
		return true;
	}

	private static function current_user_id( array $options ) {
		if ( isset( $options['current_user_id'] ) && is_callable( $options['current_user_id'] ) ) {
			return (int) call_user_func( $options['current_user_id'] );
		}
		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}

	private static function current_blog_id( array $options ) {
		if ( isset( $options['current_blog_id'] ) && is_callable( $options['current_blog_id'] ) ) {
			return (int) call_user_func( $options['current_blog_id'] );
		}
		return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
	}

	private static function job_owned_by_current_user( array $job ) {
		$uid = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$bid = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
		return ( (int) ( $job['user_id'] ?? -1 ) === $uid ) && ( (int) ( $job['blog_id'] ?? -1 ) === $bid );
	}

	public static function progress_url( $token ) {
		$token = is_string( $token ) ? $token : '';
		return admin_url( 'admin.php?page=' . self::SLUG . '&job_token=' . rawurlencode( $token ) );
	}

	private static function redirect_to_progress( $token, array $options = array() ) {
		$url = self::progress_url( (string) $token );
		if ( isset( $options['redirect'] ) && is_callable( $options['redirect'] ) ) {
			call_user_func( $options['redirect'], $url );
			return;
		}
		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $url );
		}
		exit;
	}

	public static function job_status_label( $status ) {
		$status = is_string( $status ) ? $status : '';
		$map    = array(
			'preparing' => __( 'Preparing', 'movies-wp' ),
			'queued'    => __( 'Queued', 'movies-wp' ),
			'running'   => __( 'Running', 'movies-wp' ),
			'completed' => __( 'Completed', 'movies-wp' ),
			'failed'    => __( 'Failed', 'movies-wp' ),
			'paused'    => __( 'Cancelled', 'movies-wp' ),
		);
		return $map[ $status ] ?? $status;
	}

	/**
	 * @return array{tmdb_id: int|string, title: string, summary: string, media_directory: string}
	 */
	private static function empty_values() {
		return array(
			'tmdb_id'         => '',
			'title'           => '',
			'summary'         => '',
			'media_directory' => '',
		);
	}

	/**
	 * @return array{tmdb_id: int, title: string, summary: string, media_directory: string}
	 */
	private static function values_from_post() {
		return array(
			'tmdb_id'         => isset( $_POST['tmdb_id'] ) ? absint( wp_unslash( $_POST['tmdb_id'] ) ) : 0,
			'title'           => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'summary'         => isset( $_POST['summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['summary'] ) ) : '',
			'media_directory' => isset( $_POST['media_directory'] ) ? sanitize_text_field( wp_unslash( $_POST['media_directory'] ) ) : '',
		);
	}

	/**
	 * @param array<string, mixed> $result Import Service result.
	 * @return array{type: string, message: string, details?: array<string, mixed>}
	 */
	private static function notice_for_import_result( array $result ) {
		$details = array(
			'import_result' => $result,
		);

		if ( ! empty( $result['ok'] ) ) {
			return array(
				'type'    => 'success',
				'message' => (string) ( $result['message'] ?? __( 'Movie imported successfully.', 'movies-wp' ) ),
				'details' => $details,
			);
		}

		if ( ! empty( $result['partial'] ) ) {
			return array(
				'type'    => 'warning',
				'message' => (string) ( $result['message'] ?? __( 'Import partially completed.', 'movies-wp' ) ),
				'details' => $details,
			);
		}

		$code = isset( $result['code'] ) ? (string) $result['code'] : '';
		$map  = array(
			'media_import_confirmation_required' => __( 'Please confirm that you understand this will create or update the Streamit movie.', 'movies-wp' ),
			'media_import_invalid_input'         => __( 'Please check the form fields and try again.', 'movies-wp' ),
			'media_import_not_ready'             => __( 'Import is not ready. Fix preview errors and scan again.', 'movies-wp' ),
			'media_import_duplicate_identity'    => __( 'A movie with this TMDb ID already exists. Import aborted to avoid a duplicate.', 'movies-wp' ),
			'media_import_identity_changed'      => __( 'Movie identity changed since planning. Refresh Scan & Preview, then try again.', 'movies-wp' ),
			'media_import_execution_failed'      => __( 'Import failed.', 'movies-wp' ),
		);

		$message = $map[ $code ] ?? (string) ( $result['message'] ?? __( 'Import failed.', 'movies-wp' ) );
		$message = Movies_WP_Media_Import_Service::safe_text( $message );

		return array(
			'type'    => 'error',
			'message' => $message,
			'details' => $details,
		);
	}

	/**
	 * @param WP_Error $error
	 * @return array{type: string, message: string}
	 */
	private static function notice_for_error( $error ) {
		$code = $error->get_error_code();
		$msg  = $error->get_error_message();

		if ( 'media_preview_tmdb_error' === $code ) {
			if ( false !== stripos( $msg, 'not found' ) ) {
				return array(
					'type'    => 'error',
					'message' => __( 'Could not find this movie on TMDb.', 'movies-wp' ),
				);
			}
			if ( false !== stripos( $msg, 'not configured' ) ) {
				return array(
					'type'    => 'error',
					'message' => __( 'TMDb is not configured. Check Streamit import settings.', 'movies-wp' ),
				);
			}
			return array(
				'type'    => 'error',
				'message' => __( 'Could not load this movie from TMDb. Please try again.', 'movies-wp' ),
			);
		}

		if ( 'media_preview_media_error' === $code ) {
			return array(
				'type'    => 'error',
				'message' => __( 'Could not scan the media directory. Please check the media server connection.', 'movies-wp' ),
			);
		}

		if ( 'media_preview_invalid_input' === $code ) {
			if ( false !== stripos( $msg, 'directory' ) ) {
				return array(
					'type'    => 'error',
					'message' => __( 'Invalid movie directory.', 'movies-wp' ),
				);
			}
			if ( false !== stripos( $msg, 'Title' ) ) {
				return array(
					'type'    => 'error',
					'message' => __( 'Persian / local title is required.', 'movies-wp' ),
				);
			}
			if ( false !== stripos( $msg, 'TMDb' ) ) {
				return array(
					'type'    => 'error',
					'message' => __( 'TMDb Movie ID must be a positive number.', 'movies-wp' ),
				);
			}
			return array(
				'type'    => 'error',
				'message' => __( 'Please check the form fields and try again.', 'movies-wp' ),
			);
		}

		return array(
			'type'    => 'error',
			'message' => __( 'Scan & Preview could not be completed.', 'movies-wp' ),
		);
	}

	public static function language_label( $code ) {
		if ( ! is_string( $code ) || '' === $code ) {
			return __( 'Unknown', 'movies-wp' );
		}

		$map = array(
			'fa' => __( 'Persian', 'movies-wp' ),
			'en' => __( 'English', 'movies-wp' ),
			'ko' => __( 'Korean', 'movies-wp' ),
			'zh' => __( 'Chinese', 'movies-wp' ),
			'hi' => __( 'Hindi', 'movies-wp' ),
			'fr' => __( 'French', 'movies-wp' ),
			'ja' => __( 'Japanese', 'movies-wp' ),
			'es' => __( 'Spanish', 'movies-wp' ),
			'de' => __( 'German', 'movies-wp' ),
			'ar' => __( 'Arabic', 'movies-wp' ),
			'ru' => __( 'Russian', 'movies-wp' ),
			'tr' => __( 'Turkish', 'movies-wp' ),
		);

		$key = strtolower( $code );
		return $map[ $key ] ?? strtoupper( $code );
	}

	/**
	 * Localize a known import identity action without changing its stored value.
	 *
	 * @param mixed $action Technical action value.
	 * @return string
	 */
	public static function identity_action_label( $action ) {
		$map = array(
			'create' => __( 'Create', 'movies-wp' ),
			'update' => __( 'Update', 'movies-wp' ),
		);
		$key = is_string( $action ) ? strtolower( $action ) : '';

		return $map[ $key ] ?? (string) $action;
	}

	/**
	 * Localize known adapter step names for the administrator.
	 *
	 * Unknown values remain unchanged because they are useful technical details.
	 *
	 * @param mixed $step Technical step value.
	 * @return string
	 */
	public static function import_step_label( $step ) {
		$map = array(
			'validate'        => __( 'Validation', 'movies-wp' ),
			'movie'           => __( 'Movie creation or update', 'movies-wp' ),
			'metadata'        => __( 'Movie metadata', 'movies-wp' ),
			'sources'         => __( 'Media sources', 'movies-wp' ),
			'default_stream'  => __( 'Default playback source', 'movies-wp' ),
			'media_directory' => __( 'Media directory', 'movies-wp' ),
			'subtitles'       => __( 'Subtitles', 'movies-wp' ),
		);
		$key = is_string( $step ) ? $step : '';

		return $map[ $key ] ?? (string) $step;
	}

	/**
	 * Localize a Scan & Preview issue for the administrator.
	 *
	 * Prefer stable issue codes so media-server English payloads remain
	 * technical facts while the UI shows Persian explanations.
	 *
	 * @param array<string, mixed>|string $issue Issue array or raw message.
	 * @return string
	 */
	public static function issue_message( $issue ) {
		if ( is_string( $issue ) ) {
			return $issue;
		}
		if ( ! is_array( $issue ) ) {
			return '';
		}

		$code    = isset( $issue['code'] ) ? (string) $issue['code'] : '';
		$message = isset( $issue['message'] ) ? trim( (string) $issue['message'] ) : '';
		$map     = array(
			'no_video_files'                      => __( 'No video files were detected.', 'movies-wp' ),
			'quality_unknown'                     => __( 'Quality could not be detected.', 'movies-wp' ),
			'audio_unknown'                       => __( 'Audio language could not be detected.', 'movies-wp' ),
			'subtitle_lang_unknown'               => __( 'Subtitle language could not be detected.', 'movies-wp' ),
			'source_unknown'                      => __( 'No source-type token was found.', 'movies-wp' ),
			'empty_name'                          => __( 'Filename is empty.', 'movies-wp' ),
			'ambiguous_quality_hd'                => __( 'HD is ambiguous and was normalized to 720p.', 'movies-wp' ),
			'unclassified_tokens'                 => __( 'Some filename tokens could not be classified.', 'movies-wp' ),
			'unexpected_subdirectory'             => __( 'Unexpected subdirectory; it was not scanned.', 'movies-wp' ),
			'unrecognized_extension'              => __( 'Unrecognized file extension.', 'movies-wp' ),
			'sample_or_trailer'                   => __( 'Flagged as sample/trailer; not treated as a movie file.', 'movies-wp' ),
			'broken_symlink'                      => __( 'Skipped a broken symlink.', 'movies-wp' ),
			'symlink_outside'                     => __( 'Skipped a symlink that points outside the movie directory.', 'movies-wp' ),
			'unreadable'                          => __( 'Skipped an unreadable entry.', 'movies-wp' ),
			'size_failed'                         => __( 'Could not read the file size.', 'movies-wp' ),
			'invalid_name'                        => __( 'Skipped an entry with an invalid name.', 'movies-wp' ),
			'ambiguous_subtitle_match'            => __( 'Subtitle matches more than one video and was left unassociated.', 'movies-wp' ),
			'filename_probe_resolution_mismatch'  => __( 'Filename quality and probed resolution differ.', 'movies-wp' ),
			'filename_probe_video_codec_mismatch' => __( 'Filename codec and probed video codec differ.', 'movies-wp' ),
			'filename_probe_audio_mismatch'       => __( 'Filename audio claim does not match probed audio facts.', 'movies-wp' ),
			'probe_missing_video_stream'          => __( 'Probe succeeded but no video stream was found.', 'movies-wp' ),
			'probe_resolution_detected'           => __( 'Filename has no quality token; probe resolution is available as a fact only.', 'movies-wp' ),
			'year_mismatch'                       => __( 'Media directory year differs from TMDb year. TMDb ID remains authoritative.', 'movies-wp' ),
			'duplicate_quality'                   => __( 'Duplicate quality detected.', 'movies-wp' ),
			'duplicate_tmdb_id'                   => __( 'Multiple Streamit movies share this TMDb ID. Resolve duplicates before import.', 'movies-wp' ),
			'association_module_missing'          => __( 'Subtitle association module is not available.', 'movies-wp' ),
			'media_warning'                       => __( 'Media scan warning.', 'movies-wp' ),
			'validation_error'                    => __( 'Validation error.', 'movies-wp' ),
			'parse_failed'                        => __( 'Filename parsing failed.', 'movies-wp' ),
			'ffprobe_failed'                      => __( 'Media probe failed.', 'movies-wp' ),
			'ffprobe_missing'                     => __( 'Media probe is not available on the media server.', 'movies-wp' ),
			'ffprobe_timeout'                     => __( 'Media probe timed out.', 'movies-wp' ),
			'ffprobe_bad_json'                    => __( 'Media probe returned invalid data.', 'movies-wp' ),
			'ffprobe_output_too_large'            => __( 'Media probe output was too large.', 'movies-wp' ),
			'invalid_path'                        => __( 'Media path could not be resolved safely.', 'movies-wp' ),
		);

		if ( isset( $map[ $code ] ) ) {
			$localized = $map[ $code ];
			$details   = self::issue_message_details( $code, $message );
			if ( '' !== $details ) {
				return $localized . ' ' . sprintf(
					/* translators: %s: technical details from media scan or filename parsing */
					__( 'Error details: %s', 'movies-wp' ),
					$details
				);
			}
			return $localized;
		}

		return $message;
	}

	/**
	 * Extract useful technical details from an upstream English message.
	 *
	 * @param string $code    Issue code.
	 * @param string $message Upstream message.
	 * @return string
	 */
	private static function issue_message_details( $code, $message ) {
		$message = trim( (string) $message );
		if ( '' === $message ) {
			return '';
		}

		if ( 'unclassified_tokens' === $code && preg_match( '/^Unclassified tokens:\s*(.+)$/u', $message, $m ) ) {
			return trim( $m[1] );
		}

		if ( 'duplicate_quality' === $code && preg_match( '/^Duplicate quality detected:\s*(.+)\.?$/u', $message, $m ) ) {
			return rtrim( trim( $m[1] ), '.' );
		}

		if ( 'year_mismatch' === $code && preg_match( '/\((\d+)\).*?\((\d+)\)/u', $message, $m ) ) {
			return $m[1] . ' / ' . $m[2];
		}

		if ( 'duplicate_tmdb_id' === $code && preg_match( '/\(([^)]+)\)/u', $message, $m ) ) {
			return trim( $m[1] );
		}

		return '';
	}

	public static function dash( $value ) {
		if ( null === $value || '' === $value ) {
			return '—';
		}
		return (string) $value;
	}
}

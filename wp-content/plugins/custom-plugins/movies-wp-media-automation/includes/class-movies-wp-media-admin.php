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

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Media Automation', 'movies-wp' ),
			__( 'Media Automation', 'movies-wp' ),
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-video-alt3',
			58
		);
	}

	public static function enqueue( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
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

	public static function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'movies-wp' ) );
		}

		$values        = self::empty_values();
		$preview       = null;
		$plan          = null;
		$notice        = null;
		$import_result = null;

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['movies_wp_media_action'] ) ) {
			$action = sanitize_text_field( wp_unslash( (string) $_POST['movies_wp_media_action'] ) );

			if ( self::IMPORT_ACTION === $action ) {
				$import_gate = self::process_import_request( wp_unslash( $_POST ) );
				if ( is_wp_error( $import_gate ) ) {
					if ( 'media_import_forbidden' === $import_gate->get_error_code() ) {
						wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'movies-wp' ) );
					}
					$notice = array(
						'type'    => 'error',
						'message' => Movies_WP_Media_Import_Service::safe_text( $import_gate->get_error_message() ),
					);
					$values = self::values_from_post();
				} else {
					$import_result = $import_gate;
					$notice        = self::notice_for_import_result( $import_result );
					$values        = self::values_from_post();

					// Rebuild preview for the page (authoritative inputs; not a client plan blob).
					$preview_result = Movies_WP_Media_Preview_Service::build( $values );
					if ( ! is_wp_error( $preview_result ) ) {
						$preview = $preview_result;
						if ( ! empty( $preview_result['input'] ) && is_array( $preview_result['input'] ) ) {
							$values = array_merge( $values, $preview_result['input'] );
						}
						$plan = Movies_WP_Media_Import_Plan::build( $preview );
						if ( is_wp_error( $plan ) ) {
							$plan = null;
						}
					}
				}
			} elseif ( self::ACTION === $action ) {
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
			return new WP_Error( 'media_import_forbidden', 'Insufficient capability for import.' );
		}

		$nonce = isset( $post['_wpnonce'] ) ? (string) $post['_wpnonce'] : '';
		if ( isset( $options['verify_nonce'] ) && is_callable( $options['verify_nonce'] ) ) {
			$ok = (bool) call_user_func( $options['verify_nonce'], $nonce, self::IMPORT_NONCE );
		} else {
			$ok = (bool) wp_verify_nonce( $nonce, self::IMPORT_NONCE );
		}
		if ( ! $ok ) {
			return new WP_Error( 'media_import_invalid_nonce', 'Invalid import nonce.' );
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

		if ( isset( $options['import_execute'] ) && is_callable( $options['import_execute'] ) ) {
			return call_user_func( $options['import_execute'], $request );
		}

		return Movies_WP_Media_Import_Service::execute( $request );
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

	public static function dash( $value ) {
		if ( null === $value || '' === $value ) {
			return '—';
		}
		return (string) $value;
	}
}

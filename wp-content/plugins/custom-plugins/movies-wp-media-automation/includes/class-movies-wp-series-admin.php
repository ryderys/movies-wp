<?php
/**
 * WordPress admin workflow for metadata-only Series preview and import.
 *
 * The browser submits operator inputs only. Import rebuilds the authoritative
 * preview and plan server-side, then delegates exactly once to the Series
 * Import Service. This class performs no Streamit writes.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Admin {

	const CAP            = 'manage_options';
	const SLUG           = 'movies-wp-series-automation';
	const ACTION_FIELD   = 'movies_wp_series_action';
	const PREVIEW_ACTION = 'movies_wp_series_preview';
	const IMPORT_ACTION  = 'movies_wp_series_import';
	const PREVIEW_NONCE  = 'movies_wp_series_preview';
	const IMPORT_NONCE   = 'movies_wp_series_import';

	/**
	 * @var string|false
	 */
	private static $page_hook = false;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function register_menu() {
		self::$page_hook = add_submenu_page(
			Movies_WP_Media_Admin::SLUG,
			__( 'Series Automation', 'movies-wp' ),
			__( 'Series Automation', 'movies-wp' ),
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
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

	public static function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'movies-wp' ) );
		}

		$values        = self::empty_values();
		$preview       = null;
		$plan          = null;
		$notice        = null;
		$import_result = null;

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST[ self::ACTION_FIELD ] ) ) {
			$post   = wp_unslash( $_POST );
			$action = sanitize_text_field( (string) $post[ self::ACTION_FIELD ] );
			$values = self::values_from_array( $post );

			if ( self::PREVIEW_ACTION === $action ) {
				$context = self::process_preview_request( $post );
				if ( is_wp_error( $context ) ) {
					if ( 'series_preview_forbidden' === $context->get_error_code() ) {
						wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'movies-wp' ) );
					}
					$notice = self::notice_for_preview_error( $context );
				} else {
					$values  = $context['values'];
					$preview = $context['preview'];
					$plan    = $context['plan'];
				}
			} elseif ( self::IMPORT_ACTION === $action ) {
				$result = self::process_import_request( $post );
				if ( is_wp_error( $result ) ) {
					if ( 'series_import_forbidden' === $result->get_error_code() ) {
						wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'movies-wp' ) );
					}
					$notice = array(
						'type'    => 'error',
						'message' => $result->get_error_message(),
					);
				} else {
					$import_result = $result;
					$notice        = self::notice_for_import_result( $result );
				}

				// Refresh the read-only display from authoritative operator inputs.
				$context = self::build_context( $values );
				if ( ! is_wp_error( $context ) ) {
					$preview = $context['preview'];
					$plan    = $context['plan'];
					$values  = $context['values'];
				}
			}
		}

		include MOVIES_WP_MEDIA_AUTOMATION_DIR . '/includes/views/series-preview.php';
	}

	/**
	 * Capability + nonce gate followed by read-only preview and plan creation.
	 *
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $options Test hooks.
	 * @return array{values:array,preview:array,plan:array}|WP_Error
	 */
	public static function process_preview_request( array $post, array $options = array() ) {
		$gate = self::request_gate( $post, self::PREVIEW_NONCE, 'series_preview_forbidden', 'series_preview_invalid_nonce', $options );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		return self::build_context( self::values_from_array( $post ), $options );
	}

	/**
	 * Rebuild the authoritative plan, then invoke only the Series Import Service.
	 *
	 * Browser-submitted plan, identity, episode, image, and source payloads are
	 * ignored. They cannot influence persistence.
	 *
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $options Test hooks.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function process_import_request( array $post, array $options = array() ) {
		$gate = self::request_gate( $post, self::IMPORT_NONCE, 'series_import_forbidden', 'series_import_invalid_nonce', $options );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		if ( ! self::is_confirmed( $post['confirm_import'] ?? null ) ) {
			return new WP_Error( 'series_import_confirmation_required', __( 'Series import confirmation is required.', 'movies-wp' ) );
		}

		$context = self::build_context( self::values_from_array( $post ), $options );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$result = isset( $options['import_execute'] ) && is_callable( $options['import_execute'] )
			? call_user_func( $options['import_execute'], $context['plan'] )
			: Movies_WP_Series_Import_Service::execute( $context['plan'] );

		if ( ! is_array( $result ) ) {
			return new WP_Error( 'series_import_invalid_result', __( 'Series import returned an invalid result.', 'movies-wp' ) );
		}
		return $result;
	}

	/**
	 * @param array<string, mixed> $values
	 * @param array<string, mixed> $options
	 * @return array{values:array,preview:array,plan:array}|WP_Error
	 */
	private static function build_context( array $values, array $options = array() ) {
		$preview = isset( $options['preview_build'] ) && is_callable( $options['preview_build'] )
			? call_user_func( $options['preview_build'], $values )
			: Movies_WP_Series_Preview_Service::build( $values );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		if ( ! is_array( $preview ) ) {
			return new WP_Error( 'series_preview_invalid_result', __( 'Series preview returned invalid data.', 'movies-wp' ) );
		}

		$plan = isset( $options['plan_build'] ) && is_callable( $options['plan_build'] )
			? call_user_func( $options['plan_build'], $preview )
			: Movies_WP_Series_Import_Plan::build( $preview );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		if ( ! is_array( $plan ) ) {
			return new WP_Error( 'series_import_plan_invalid_result', __( 'Series Import Plan returned invalid data.', 'movies-wp' ) );
		}

		$normalized = isset( $preview['input'] ) && is_array( $preview['input'] )
			? array_merge( $values, $preview['input'] )
			: $values;
		return array(
			'values'  => $normalized,
			'preview' => $preview,
			'plan'    => $plan,
		);
	}

	/**
	 * @return true|WP_Error
	 */
	private static function request_gate( array $post, $nonce_action, $forbidden_code, $nonce_code, array $options ) {
		$can = isset( $options['current_user_can'] ) && is_callable( $options['current_user_can'] )
			? $options['current_user_can']
			: 'current_user_can';
		if ( ! call_user_func( $can, self::CAP ) ) {
			return new WP_Error( $forbidden_code, __( 'Insufficient capability for Series Automation.', 'movies-wp' ) );
		}

		$nonce = isset( $post['_wpnonce'] ) ? (string) $post['_wpnonce'] : '';
		$valid = isset( $options['verify_nonce'] ) && is_callable( $options['verify_nonce'] )
			? (bool) call_user_func( $options['verify_nonce'], $nonce, $nonce_action )
			: (bool) wp_verify_nonce( $nonce, $nonce_action );
		if ( ! $valid ) {
			return new WP_Error( $nonce_code, __( 'Invalid Series Automation nonce.', 'movies-wp' ) );
		}
		return true;
	}

	private static function is_confirmed( $value ) {
		return true === $value || 1 === $value || '1' === $value;
	}

	private static function empty_values() {
		return array(
			'tmdb_id' => '',
			'title'   => '',
			'summary' => '',
		);
	}

	/**
	 * Whitelist and sanitize operator inputs only.
	 *
	 * @return array{tmdb_id:int,title:string,summary:string}
	 */
	private static function values_from_array( array $post ) {
		return array(
			'tmdb_id' => isset( $post['tmdb_id'] ) ? absint( $post['tmdb_id'] ) : 0,
			'title'   => isset( $post['title'] ) ? sanitize_text_field( (string) $post['title'] ) : '',
			'summary' => isset( $post['summary'] ) ? sanitize_textarea_field( (string) $post['summary'] ) : '',
		);
	}

	private static function notice_for_preview_error( $error ) {
		$code = (string) $error->get_error_code();
		$map  = array(
			'series_preview_invalid_input' => __( 'Please check the Series fields and try again.', 'movies-wp' ),
			'tmdb_tv_not_found'            => __( 'Could not find this series on TMDb.', 'movies-wp' ),
			'tmdb_tv_request_failed'       => __( 'Could not load this series from TMDb. Please try again.', 'movies-wp' ),
		);
		return array(
			'type'    => 'error',
			'message' => $map[ $code ] ?? $error->get_error_message(),
		);
	}

	private static function notice_for_import_result( array $result ) {
		$action = (string) ( $result['action'] ?? '' );
		if ( ! empty( $result['ok'] ) ) {
			$message = 'update' === $action
				? __( 'Series updated successfully.', 'movies-wp' )
				: __( 'Series imported successfully.', 'movies-wp' );
			$type = 'success';
		} elseif ( ! empty( $result['partial'] ) ) {
			$message = __( 'Series import partially completed.', 'movies-wp' );
			$type    = 'warning';
		} else {
			$message = __( 'Series import failed.', 'movies-wp' );
			$type    = 'error';
		}
		return array(
			'type'    => $type,
			'message' => $message,
		);
	}

	public static function issue_message( $issue ) {
		if ( is_array( $issue ) ) {
			return isset( $issue['message'] ) ? (string) $issue['message'] : '';
		}
		return is_string( $issue ) ? $issue : '';
	}

	public static function action_label( $action ) {
		$map = array(
			'create' => __( 'Create', 'movies-wp' ),
			'update' => __( 'Update', 'movies-wp' ),
			'set'    => __( 'Set', 'movies-wp' ),
			'keep_existing' => __( 'Keep existing', 'movies-wp' ),
			'skip_missing'  => __( 'Skip missing', 'movies-wp' ),
		);
		return $map[ (string) $action ] ?? (string) $action;
	}

	public static function dash( $value ) {
		return null === $value || '' === $value ? '—' : (string) $value;
	}
}

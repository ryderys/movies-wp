<?php
/**
 * WordPress admin workflow for unified Series metadata + media automation.
 *
 * The browser submits operator inputs for Preview and only a snapshot token
 * for Import. Import enqueues an Action Scheduler job. This class performs no
 * Streamit writes.
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
	const PROGRESS_NONCE = 'movies_wp_series_import_progress';
	const RESUME_ACTION  = 'movies_wp_series_resume';
	const CANCEL_ACTION  = 'movies_wp_series_cancel';

	/**
	 * @var string|false
	 */
	private static $page_hook = false;

	/**
	 * Notice deferred from an early Import mutation when redirect is not used.
	 *
	 * @var array{type:string,message:string}|null
	 */
	private static $pending_notice = null;

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
		// Import / Resume / Cancel must run before admin HTML output so redirects work.
		if ( is_string( self::$page_hook ) && '' !== self::$page_hook ) {
			add_action( 'load-' . self::$page_hook, array( __CLASS__, 'handle_load' ) );
		}
	}

	/**
	 * Early POST handler for mutations that redirect (Import, Resume, Cancel).
	 * Preview remains in render_page() because it must redisplay the plan.
	 */
	public static function handle_load() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::ACTION_FIELD ] ) ) {
			return;
		}
		self::handle_mutation_request( wp_unslash( $_POST ) );
	}

	/**
	 * Process Import / Resume / Cancel before page output.
	 *
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $options Test hooks.
	 * @return void
	 */
	public static function handle_mutation_request( array $post, array $options = array() ) {
		$action = isset( $post[ self::ACTION_FIELD ] ) ? sanitize_text_field( (string) $post[ self::ACTION_FIELD ] ) : '';
		if ( self::IMPORT_ACTION === $action ) {
			self::handle_import_mutation( $post, $options );
			return;
		}
		if ( self::RESUME_ACTION === $action ) {
			self::process_job_command( $post, 'resume', $options );
			return;
		}
		if ( self::CANCEL_ACTION === $action ) {
			self::process_job_command( $post, 'cancel', $options );
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

	public static function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'movies-wp' ) );
		}

		$values         = self::empty_values();
		$preview        = null;
		$plan           = null;
		$notice         = self::$pending_notice;
		$job            = null;
		$snapshot_token = '';
		self::$pending_notice = null;

		$job_token = isset( $_GET['job_token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['job_token'] ) ) : '';
		if ( '' !== $job_token && 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			$loaded = Movies_WP_Series_Import_Job_Store::find_by_token( $job_token );
			if ( is_array( $loaded ) && self::job_owned_by_current_user( $loaded ) ) {
				$job = $loaded;
				include MOVIES_WP_MEDIA_AUTOMATION_DIR . '/includes/views/series-import-progress.php';
				return;
			}
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST[ self::ACTION_FIELD ] ) ) {
			$post   = wp_unslash( $_POST );
			$action = sanitize_text_field( (string) $post[ self::ACTION_FIELD ] );
			$values = self::values_from_array( $post );

			// Import / Resume / Cancel are handled on load-{$hook} before output.
			if ( self::PREVIEW_ACTION === $action ) {
				$context = self::process_preview_request( $post );
				if ( is_wp_error( $context ) ) {
					if ( 'series_preview_forbidden' === $context->get_error_code() ) {
						wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'movies-wp' ) );
					}
					$notice = self::notice_for_preview_error( $context );
				} else {
					$values         = $context['values'];
					$preview        = $context['preview'];
					$plan           = $context['plan'];
					$snapshot_token = (string) ( $context['snapshot_token'] ?? '' );
				}
			}
		}

		include MOVIES_WP_MEDIA_AUTOMATION_DIR . '/includes/views/series-preview.php';
	}

	/**
	 * Enqueue-only Import mutation. Redirects to the progress page on success.
	 *
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $options Test hooks.
	 * @return void
	 */
	public static function handle_import_mutation( array $post, array $options = array() ) {
		$result = self::process_import_request( $post, $options );
		if ( is_wp_error( $result ) ) {
			if ( 'series_import_forbidden' === $result->get_error_code() ) {
				self::die_forbidden( $options );
				return;
			}
			$notice = array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			);
			self::$pending_notice = $notice;
			if ( isset( $options['on_notice'] ) && is_callable( $options['on_notice'] ) ) {
				call_user_func( $options['on_notice'], $notice, $result );
			}
			return;
		}
		self::redirect_to_progress( (string) ( $result['token'] ?? '' ), $options );
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
		$context = self::build_context( self::values_from_array( $post ), $options );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		if ( true === ( $context['preview']['ready_to_import'] ?? null ) ) {
			$create = isset( $options['snapshot_create'] ) && is_callable( $options['snapshot_create'] )
				? $options['snapshot_create']
				: array( 'Movies_WP_Series_Import_Snapshot_Store', 'create' );
			$snapshot = call_user_func(
				$create,
				$context['preview'],
				array(
					'user_id' => self::current_user_id( $options ),
					'blog_id' => self::current_blog_id( $options ),
				)
			);
			if ( is_wp_error( $snapshot ) ) {
				return $snapshot;
			}
			$context['snapshot_token'] = is_array( $snapshot ) ? (string) ( $snapshot['token'] ?? '' ) : '';
		}
		return $context;
	}

	/**
	 * Validate snapshot token and enqueue the Action Scheduler import job.
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

		$token = isset( $post['snapshot_token'] ) ? sanitize_text_field( (string) $post['snapshot_token'] ) : '';
		if ( '' === $token ) {
			return new WP_Error( 'series_import_snapshot_missing', __( 'Series import snapshot token is missing.', 'movies-wp' ) );
		}

		$enqueue = isset( $options['enqueue_job'] ) && is_callable( $options['enqueue_job'] )
			? $options['enqueue_job']
			: array( 'Movies_WP_Series_Import_Job_Runner', 'enqueue_from_snapshot' );
		$result  = call_user_func(
			$enqueue,
			$token,
			array(
				'user_id' => self::current_user_id( $options ),
				'blog_id' => self::current_blog_id( $options ),
			),
			$options
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) || empty( $result['token'] ) ) {
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
		$preview = isset( $options['orchestrator_preview'] ) && is_callable( $options['orchestrator_preview'] )
			? call_user_func( $options['orchestrator_preview'], $values )
			: Movies_WP_Series_Orchestrator::build_preview( $values );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		if ( ! is_array( $preview ) ) {
			return new WP_Error( 'series_preview_invalid_result', __( 'Series preview returned invalid data.', 'movies-wp' ) );
		}

		$plan = $preview['metadata_plan'] ?? null;
		if ( ! is_array( $plan ) ) {
			return new WP_Error( 'series_import_plan_invalid_result', __( 'Series Import Plan returned invalid data.', 'movies-wp' ) );
		}

		$normalized = isset( $preview['input'] ) && is_array( $preview['input'] )
			? array_merge( $values, $preview['input'] )
			: $values;
		return array(
			'values'          => $normalized,
			'preview'         => $preview,
			'plan'            => $plan,
			'snapshot_token'  => '',
		);
	}

	public static function progress_url( $job_token ) {
		if ( ! function_exists( 'admin_url' ) ) {
			return '';
		}
		return admin_url( 'admin.php?page=' . self::SLUG . '&job_token=' . rawurlencode( (string) $job_token ) );
	}

	/**
	 * Redirect to the progress page. Never exits after a failed redirect with an empty body.
	 *
	 * @param string               $job_token
	 * @param array<string, mixed> $options Test hooks (`redirect` callable receives URL + status).
	 * @return void
	 */
	public static function redirect_to_progress( $job_token, array $options = array() ) {
		$url = self::progress_url( $job_token );
		if ( isset( $options['redirect'] ) && is_callable( $options['redirect'] ) ) {
			call_user_func( $options['redirect'], $url, 302 );
			return;
		}
		if ( '' === $url ) {
			self::die_message( __( 'Series import progress URL could not be built.', 'movies-wp' ), $options );
			return;
		}
		if ( function_exists( 'wp_safe_redirect' ) ) {
			$sent = wp_safe_redirect( $url );
			if ( false !== $sent ) {
				exit;
			}
		}
		$message = sprintf(
			/* translators: %s: progress URL */
			__( 'Unable to redirect to Series import progress. Continue here: %s', 'movies-wp' ),
			'<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>'
		);
		self::die_message( $message, $options, array( 'response' => 200 ) );
	}

	/**
	 * @param array<string, mixed> $post
	 * @param string               $command resume|cancel
	 * @param array<string, mixed> $options Test hooks.
	 * @return void
	 */
	private static function process_job_command( array $post, $command, array $options = array() ) {
		$gate = self::request_gate( $post, self::PROGRESS_NONCE, 'series_import_forbidden', 'series_import_invalid_nonce', $options );
		if ( is_wp_error( $gate ) ) {
			if ( 'series_import_forbidden' === $gate->get_error_code() ) {
				self::die_forbidden( $options );
				return;
			}
			self::die_message( $gate->get_error_message(), $options );
			return;
		}
		$token = isset( $post['job_token'] ) ? sanitize_text_field( (string) $post['job_token'] ) : '';
		$find  = isset( $options['find_job'] ) && is_callable( $options['find_job'] )
			? $options['find_job']
			: array( 'Movies_WP_Series_Import_Job_Store', 'find_by_token' );
		$job   = call_user_func( $find, $token );
		if ( ! is_array( $job ) || ! self::job_owned_by_current_user( $job, $options ) ) {
			self::die_message( __( 'Series import job was not found.', 'movies-wp' ), $options );
			return;
		}
		if ( 'resume' === $command ) {
			$resume = isset( $options['resume_job'] ) && is_callable( $options['resume_job'] )
				? $options['resume_job']
				: array( 'Movies_WP_Series_Import_Job_Runner', 'resume' );
			call_user_func( $resume, $token );
		} else {
			$cancel = isset( $options['cancel_job'] ) && is_callable( $options['cancel_job'] )
				? $options['cancel_job']
				: array( 'Movies_WP_Series_Import_Job_Runner', 'cancel' );
			call_user_func( $cancel, $token );
		}
		self::redirect_to_progress( $token, $options );
	}

	/**
	 * @param array<string, mixed> $options
	 * @return void
	 */
	private static function die_forbidden( array $options = array() ) {
		self::die_message( __( 'Sorry, you are not allowed to access this page.', 'movies-wp' ), $options );
	}

	/**
	 * @param string               $message
	 * @param array<string, mixed> $options
	 * @param array<string, mixed> $args
	 * @return void
	 */
	private static function die_message( $message, array $options = array(), array $args = array() ) {
		if ( isset( $options['wp_die'] ) && is_callable( $options['wp_die'] ) ) {
			call_user_func( $options['wp_die'], $message, $args );
			return;
		}
		if ( function_exists( 'wp_die' ) ) {
			wp_die( $message, '', $args );
		}
	}

	private static function job_owned_by_current_user( array $job, array $options = array() ) {
		return (int) ( $job['user_id'] ?? 0 ) === self::current_user_id( $options )
			&& (int) ( $job['blog_id'] ?? 0 ) === self::current_blog_id( $options );
	}

	private static function current_user_id( array $options ) {
		if ( isset( $options['user_id'] ) ) {
			return (int) $options['user_id'];
		}
		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}

	private static function current_blog_id( array $options ) {
		if ( isset( $options['blog_id'] ) ) {
			return (int) $options['blog_id'];
		}
		return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
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
			'tmdb_id'          => '',
			'title'            => '',
			'summary'          => '',
			'series_directory' => '',
		);
	}

	/**
	 * Whitelist and sanitize operator inputs only.
	 *
	 * @return array{tmdb_id:int,title:string,summary:string,series_directory:string}
	 */
	private static function values_from_array( array $post ) {
		return array(
			'tmdb_id'          => isset( $post['tmdb_id'] ) ? absint( $post['tmdb_id'] ) : 0,
			'title'            => isset( $post['title'] ) ? sanitize_text_field( (string) $post['title'] ) : '',
			'summary'          => isset( $post['summary'] ) ? sanitize_textarea_field( (string) $post['summary'] ) : '',
			'series_directory' => isset( $post['series_directory'] ) ? sanitize_text_field( (string) $post['series_directory'] ) : '',
		);
	}

	private static function notice_for_preview_error( $error ) {
		$code = (string) $error->get_error_code();
		$map  = array(
			'series_preview_invalid_input'    => __( 'Please check the Series fields and try again.', 'movies-wp' ),
			'series_automation_invalid_input' => __( 'Please check the Series fields and directory, then try again.', 'movies-wp' ),
			'tmdb_tv_not_found'               => __( 'Could not find this series on TMDb.', 'movies-wp' ),
			'tmdb_tv_request_failed'          => __( 'Could not load this series from TMDb. Please try again.', 'movies-wp' ),
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

	/**
	 * Collapse repeated validation issues by code for operator-facing display.
	 * Warning payloads themselves are not mutated.
	 *
	 * @param array<int, mixed> $issues
	 * @return array<int, array{code:string,count:int,summary:string,details:array<int,string>}>
	 */
	public static function grouped_issues( array $issues ) {
		$by_code = array();
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				$issue = array(
					'code'    => '',
					'message' => (string) $issue,
				);
			}
			$code = (string) ( $issue['code'] ?? '' );
			if ( ! isset( $by_code[ $code ] ) ) {
				$by_code[ $code ] = array();
			}
			$by_code[ $code ][] = $issue;
		}

		$groups = array();
		foreach ( $by_code as $code => $items ) {
			$details = array();
			foreach ( $items as $item ) {
				$details[] = self::issue_message( $item );
			}
			$count = count( $items );
			if ( 'series_episode_still_missing' === $code ) {
				$summary = sprintf(
					/* translators: %d: number of episodes missing TMDb stills */
					__( '%d episodes have no TMDb stills. Episode still images will be skipped.', 'movies-wp' ),
					$count
				);
			} elseif ( 1 === $count ) {
				$summary = $details[0];
			} else {
				$unique = array_values( array_unique( $details ) );
				if ( 1 === count( $unique ) ) {
					$summary = sprintf(
						/* translators: 1: warning count, 2: warning message */
						__( '%1$d identical warnings: %2$s', 'movies-wp' ),
						$count,
						$unique[0]
					);
				} else {
					$summary = sprintf(
						/* translators: %d: warning count */
						__( '%d similar warnings.', 'movies-wp' ),
						$count
					);
				}
			}
			$groups[] = array(
				'code'    => $code,
				'count'   => $count,
				'summary' => $summary,
				'details' => $details,
			);
		}

		return $groups;
	}

	/**
	 * Operator-facing coverage counts for TMDb episodes that have matching media.
	 *
	 * @param array<int, mixed> $episode_matches
	 * @return array{total:int,matched:int,range:string,uniform:bool,videos_per_episode:int,subtitles_per_episode:int}
	 */
	public static function episode_coverage( array $episode_matches ) {
		$total      = 0;
		$matched    = 0;
		$codes      = array();
		$src_counts = array();
		$sub_counts = array();

		foreach ( $episode_matches as $episode ) {
			if ( ! is_array( $episode ) ) {
				continue;
			}
			if ( 'media_without_tmdb' === ( $episode['status'] ?? '' ) ) {
				continue;
			}
			++$total;
			$sources = (int) ( $episode['source_count'] ?? 0 );
			$subs    = (int) ( $episode['subtitle_count'] ?? 0 );
			if ( ( $sources + $subs ) > 0 ) {
				++$matched;
				$codes[]      = sprintf( 'S%02dE%02d', (int) ( $episode['season_number'] ?? 0 ), (int) ( $episode['episode_number'] ?? 0 ) );
				$src_counts[] = $sources;
				$sub_counts[] = $subs;
			}
		}

		$range = '';
		if ( array() !== $codes ) {
			$last  = $codes[ count( $codes ) - 1 ];
			$range = $codes[0] === $last ? $codes[0] : $codes[0] . '–' . $last;
		}

		$uniform = $matched > 0
			&& 1 === count( array_unique( $src_counts ) )
			&& 1 === count( array_unique( $sub_counts ) );

		return array(
			'total'                 => $total,
			'matched'               => $matched,
			'range'                 => $range,
			'uniform'               => $uniform,
			'videos_per_episode'    => $uniform ? (int) $src_counts[0] : 0,
			'subtitles_per_episode' => $uniform ? (int) $sub_counts[0] : 0,
		);
	}

	public static function media_status_label( $status ) {
		$map = array(
			'metadata_and_media' => __( 'Matched', 'movies-wp' ),
			'metadata_only'      => __( 'Metadata only', 'movies-wp' ),
			'media_without_tmdb' => __( 'Media without TMDb episode', 'movies-wp' ),
		);
		return $map[ (string) $status ] ?? (string) $status;
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

<?php
/**
 * WordPress admin workflow for unified Series metadata + media automation.
 *
 * The browser submits operator inputs only. Import rebuilds the authoritative
 * preview server-side, then delegates to the Series Orchestrator. This class
 * performs no Streamit writes.
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
	 * Delegate whitelisted operator inputs to the Series Orchestrator.
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

		$values = self::values_from_array( $post );
		$result = isset( $options['orchestrator_execute'] ) && is_callable( $options['orchestrator_execute'] )
			? call_user_func( $options['orchestrator_execute'], $values )
			: Movies_WP_Series_Orchestrator::execute( $values );

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

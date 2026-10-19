<?php
/**
 * Administrator page for Telegram publishing.
 *
 * Changes switches, pause, a manual retry, and one explicit test post.
 * It does not import content and does not reveal credentials.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-movies-wp-telegram-config.php';
require_once __DIR__ . '/class-movies-wp-telegram-queue.php';
require_once __DIR__ . '/class-movies-wp-telegram-client.php';
require_once __DIR__ . '/class-movies-wp-telegram-worker.php';

class Movies_WP_Telegram_Admin {

	const CAP          = 'manage_options';
	const SLUG         = 'movies-wp-telegram';
	const NONCE        = 'movies_wp_telegram_admin';
	const ACTION_FIELD = 'movies_wp_telegram_action';
	const SAVE_ACTION  = 'save_settings';
	const PAUSE_ACTION = 'pause';
	const RESUME_ACTION = 'resume';
	const TEST_ACTION  = 'test_post';
	const RETRY_ACTION = 'retry_failed';
	const CONFIRM_FIELD = 'movies_wp_telegram_confirm_test';

	/**
	 * @var string|false
	 */
	private static $page_hook = false;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu() {
		$parent = class_exists( 'Movies_WP_Media_Admin' ) ? Movies_WP_Media_Admin::SLUG : 'movies-wp-media-automation';
		self::$page_hook = add_submenu_page(
			$parent,
			__( 'Telegram', 'movies-wp' ),
			__( 'Telegram', 'movies-wp' ),
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'movies-wp' ) );
		}
		$notice = null;
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST[ self::ACTION_FIELD ] ) ) {
			$result = self::handle_post( wp_unslash( $_POST ) );
			$notice = self::notice_from_result( $result );
		}
		echo self::render_markup( self::page_state( $notice ) );
	}

	/**
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $options current_user_can, verify_nonce, http_post, schedule hooks.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_post( array $post, array $options = array() ) {
		$gate = self::request_gate( $post, $options );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$action = isset( $post[ self::ACTION_FIELD ] ) ? sanitize_key( (string) $post[ self::ACTION_FIELD ] ) : '';
		if ( self::SAVE_ACTION === $action ) {
			return self::save_settings( $post );
		}
		if ( self::PAUSE_ACTION === $action ) {
			return self::set_paused( true, $options );
		}
		if ( self::RESUME_ACTION === $action ) {
			return self::set_paused( false, $options );
		}
		if ( self::TEST_ACTION === $action ) {
			return self::send_test( $post, $options );
		}
		if ( self::RETRY_ACTION === $action ) {
			return self::retry_failed( $post, $options );
		}
		return new WP_Error( 'telegram_admin_invalid_action', __( 'Unknown Telegram admin action.', 'movies-wp' ) );
	}

	/**
	 * @param array{type:string,message:string}|null $notice
	 * @return array<string, mixed>
	 */
	public static function page_state( $notice = null ) {
		$counts = class_exists( 'Movies_WP_Telegram_Queue' )
			? Movies_WP_Telegram_Queue::counts()
			: array();
		$failures = class_exists( 'Movies_WP_Telegram_Queue' )
			? Movies_WP_Telegram_Queue::recent_failures( Movies_WP_Telegram_Queue::FAILURE_LIMIT )
			: array();
		$clean = array();
		foreach ( $failures as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean[] = array(
				'id'           => (int) ( $row['id'] ?? 0 ),
				'content_type' => sanitize_key( (string) ( $row['content_type'] ?? '' ) ),
				'post_id'      => (int) ( $row['post_id'] ?? 0 ),
				'status'       => sanitize_key( (string) ( $row['status'] ?? '' ) ),
				'last_error'   => self::public_error( $row['last_error'] ?? '' ),
			);
		}
		return array(
			'global_enabled'  => Movies_WP_Telegram_Config::global_enabled(),
			'movie_enabled'   => Movies_WP_Telegram_Config::movie_enabled(),
			'series_enabled'  => Movies_WP_Telegram_Config::series_enabled(),
			'episode_enabled' => Movies_WP_Telegram_Config::episode_enabled(),
			'paused'          => Movies_WP_Telegram_Config::paused(),
			'configured'      => Movies_WP_Telegram_Config::credentials_configured(),
			'counts'          => $counts,
			'failures'        => $clean,
			'notice'          => is_array( $notice ) ? $notice : null,
		);
	}

	/**
	 * @param array<string, mixed> $state
	 * @return string
	 */
	public static function render_markup( array $state ) {
		ob_start();
		include __DIR__ . '/views/telegram-admin.php';
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $post
	 * @return array<string, mixed>
	 */
	private static function save_settings( array $post ) {
		$switches = array(
			Movies_WP_Telegram_Config::OPT_GLOBAL  => self::is_enabled_value( $post['telegram_global'] ?? null ),
			Movies_WP_Telegram_Config::OPT_MOVIE   => self::is_enabled_value( $post['telegram_movies'] ?? null ),
			Movies_WP_Telegram_Config::OPT_SERIES  => self::is_enabled_value( $post['telegram_series'] ?? null ),
			Movies_WP_Telegram_Config::OPT_EPISODE => self::is_enabled_value( $post['telegram_episodes'] ?? null ),
		);
		foreach ( $switches as $option => $enabled ) {
			update_option( $option, $enabled ? '1' : '0', false );
		}
		return array(
			'ok'      => true,
			'notice'  => array(
				'type'    => 'success',
				'message' => __( 'Telegram settings saved.', 'movies-wp' ),
			),
		);
	}

	/**
	 * @param bool                 $paused
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function set_paused( $paused, array $options ) {
		update_option( Movies_WP_Telegram_Config::OPT_PAUSED, $paused ? '1' : '0', false );
		if ( ! $paused && class_exists( 'Movies_WP_Telegram_Worker' ) ) {
			Movies_WP_Telegram_Worker::ensure_scheduled( $options );
		}
		return array(
			'ok'     => true,
			'notice' => array(
				'type'    => 'success',
				'message' => $paused
					? __( 'Telegram delivery is paused. Imports are unchanged.', 'movies-wp' )
					: __( 'Telegram delivery can run again.', 'movies-wp' ),
			),
		);
	}

	/**
	 * Send one identified test message. Does not touch the import queue.
	 *
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|WP_Error
	 */
	private static function send_test( array $post, array $options ) {
		if ( ! self::is_enabled_value( $post[ self::CONFIRM_FIELD ] ?? null ) ) {
			return new WP_Error( 'telegram_test_unconfirmed', __( 'Confirm the test message before sending it.', 'movies-wp' ) );
		}
		if ( ! Movies_WP_Telegram_Config::credentials_configured() ) {
			return new WP_Error( 'telegram_test_unconfigured', __( 'Telegram credentials are not configured.', 'movies-wp' ) );
		}
		$before = Movies_WP_Telegram_Queue::count_rows();
		$result = Movies_WP_Telegram_Client::deliver( self::test_snapshot(), $options );
		if ( Movies_WP_Telegram_Queue::count_rows() !== $before ) {
			return new WP_Error( 'telegram_test_isolated', __( 'The test message was blocked because it touched the import queue.', 'movies-wp' ) );
		}
		if ( ! is_array( $result ) || 'sent' !== (string) ( $result['outcome'] ?? '' ) ) {
			return new WP_Error(
				'telegram_test_failed',
				self::public_error( is_array( $result ) ? ( $result['error'] ?? '' ) : '' )
			);
		}
		return array(
			'ok'     => true,
			'notice' => array(
				'type'    => 'success',
				'message' => __( 'Test message sent. No movie, series, or episode was queued.', 'movies-wp' ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function test_snapshot() {
		return array(
			'type'        => 'movie',
			'title'       => 'آزمایش اتصال تلگرام',
			'description' => 'این فقط یک پیام آزمایشی مدیریت است و به هیچ فیلم، سریال یا قسمتی متصل نیست.',
			'genres'      => array(),
			'permalink'   => '',
			'poster_url'  => '',
		);
	}

	/**
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|WP_Error
	 */
	private static function retry_failed( array $post, array $options ) {
		$id = absint( $post['queue_id'] ?? 0 );
		if ( $id <= 0 || ! Movies_WP_Telegram_Queue::requeue_failed( $id, $options ) ) {
			return new WP_Error( 'telegram_retry_ineligible', __( 'Only a failed Telegram job can be retried.', 'movies-wp' ) );
		}
		if ( class_exists( 'Movies_WP_Telegram_Worker' ) ) {
			Movies_WP_Telegram_Worker::ensure_scheduled( $options );
		}
		return array(
			'ok'     => true,
			'notice' => array(
				'type'    => 'success',
				'message' => __( 'The failed Telegram job was returned to the queue.', 'movies-wp' ),
			),
		);
	}

	/**
	 * @param array<string, mixed>|WP_Error $result
	 * @return array{type:string,message:string}
	 */
	private static function notice_from_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return array(
				'type'    => 'error',
				'message' => self::public_error( $result->get_error_message() ),
			);
		}
		if ( is_array( $result ) && isset( $result['notice'] ) && is_array( $result['notice'] ) ) {
			return array(
				'type'    => 'success' === (string) ( $result['notice']['type'] ?? '' ) ? 'success' : 'error',
				'message' => self::public_error( $result['notice']['message'] ?? '' ),
			);
		}
		return array(
			'type'    => 'error',
			'message' => __( 'Telegram admin action failed.', 'movies-wp' ),
		);
	}

	/**
	 * @param mixed $error
	 * @return string
	 */
	private static function public_error( $error ) {
		$text = class_exists( 'Movies_WP_Telegram_Queue' )
			? Movies_WP_Telegram_Queue::sanitize_error( $error )
			: '';
		$text = preg_replace( '#https?://\S+#i', '[url]', $text );
		$text = is_string( $text ) ? $text : '';
		$text = preg_replace( '#\b(?:Movie|Series)/[A-Za-z0-9._/\-]+#', '[path]', $text );
		$text = is_string( $text ) ? trim( $text ) : '';
		if ( '' === $text ) {
			return __( 'Telegram did not accept the message.', 'movies-wp' );
		}
		return $text;
	}

	/**
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $options
	 * @return true|WP_Error
	 */
	private static function request_gate( array $post, array $options ) {
		$can = isset( $options['current_user_can'] ) && is_callable( $options['current_user_can'] )
			? $options['current_user_can']
			: 'current_user_can';
		if ( ! is_callable( $can ) || ! call_user_func( $can, self::CAP ) ) {
			return new WP_Error( 'telegram_admin_forbidden', __( 'Insufficient capability for Telegram settings.', 'movies-wp' ) );
		}
		$nonce = isset( $post['_wpnonce'] ) ? (string) $post['_wpnonce'] : '';
		$valid = isset( $options['verify_nonce'] ) && is_callable( $options['verify_nonce'] )
			? (bool) call_user_func( $options['verify_nonce'], $nonce, self::NONCE )
			: ( function_exists( 'wp_verify_nonce' ) && (bool) wp_verify_nonce( $nonce, self::NONCE ) );
		if ( ! $valid ) {
			return new WP_Error( 'telegram_admin_invalid_nonce', __( 'Invalid Telegram settings nonce.', 'movies-wp' ) );
		}
		return true;
	}

	/**
	 * Only the string "1" enables a control. Missing and other values stay off.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private static function is_enabled_value( $value ) {
		return '1' === $value || 1 === $value || true === $value;
	}
}

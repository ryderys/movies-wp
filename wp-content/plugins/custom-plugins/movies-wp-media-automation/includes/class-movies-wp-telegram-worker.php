<?php
/**
 * Action Scheduler worker for the Telegram publish queue.
 *
 * Registers its hook only. It does not schedule itself from plugin
 * activation, and it does not send a message until it owns a claimed row
 * and publishing is still allowed for that content type.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-movies-wp-telegram-config.php';
require_once __DIR__ . '/class-movies-wp-telegram-queue.php';
require_once __DIR__ . '/class-movies-wp-telegram-snapshot-provider.php';
require_once __DIR__ . '/class-movies-wp-telegram-client.php';

class Movies_WP_Telegram_Worker {

	const HOOK          = 'movies_wp_telegram_publish';
	const GROUP         = 'movies-wp-telegram';
	const BATCH_DEFAULT = 5;
	const BATCH_MIN     = 1;
	const BATCH_MAX     = 10;

	/** @var bool */
	private static $running = false;

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'handle' ), 10, 0 );
	}

	/**
	 * Action Scheduler callback. Failures stay inside this process.
	 */
	public static function handle() {
		try {
			self::run( array() );
		} catch ( \Throwable $e ) {
			unset( $e );
		}
	}

	/**
	 * Claim and deliver one bounded batch.
	 *
	 * @param array<string, mixed> $options now, batch_size, deliver, telegram_snapshot, schedule hooks.
	 * @return array{processed:int,busy:bool,results:list<array<string,mixed>>}
	 */
	public static function run( array $options = array() ) {
		if ( self::$running ) {
			return array(
				'processed' => 0,
				'busy'      => true,
				'results'   => array(),
			);
		}
		self::$running = true;
		try {
			return self::process( $options );
		} catch ( \Throwable $e ) {
			unset( $e );
			return array(
				'processed' => 0,
				'busy'      => false,
				'results'   => array(),
			);
		} finally {
			self::$running = false;
		}
	}

	/**
	 * Schedule one pending worker action when delivery is allowed.
	 * An earlier pending action is left in place. A later one is replaced.
	 *
	 * @param array<string, mixed> $options schedule_action, next_scheduled, unschedule_action, now.
	 * @param int|null             $when    Unix time. Null means now.
	 * @return bool
	 */
	public static function ensure_scheduled( array $options = array(), $when = null ) {
		try {
			if ( ! self::delivery_possible() ) {
				return false;
			}
			$when = null === $when ? self::now( $options ) : (int) $when;
			if ( $when < 1 ) {
				$when = self::now( $options );
			}
			$existing = self::next_scheduled( $options );
			if ( is_int( $existing ) && $existing > 0 && $existing <= $when ) {
				return false;
			}
			if ( is_int( $existing ) && $existing > $when ) {
				self::unschedule( $options );
			}
			return self::schedule_at( $when, $options );
		} catch ( \Throwable $e ) {
			unset( $e );
			return false;
		}
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array{processed:int,busy:bool,results:list<array<string,mixed>>}
	 */
	private static function process( array $options ) {
		if ( class_exists( 'Movies_WP_Telegram_Queue' ) ) {
			Movies_WP_Telegram_Queue::release_expired( $options );
		}
		$results = array();
		if ( ! self::delivery_possible() || ! class_exists( 'Movies_WP_Telegram_Queue' ) ) {
			return array(
				'processed' => 0,
				'busy'      => false,
				'results'   => $results,
			);
		}

		$batch = self::batch_size( $options );
		while ( count( $results ) < $batch ) {
			$allowed = self::allowed_types();
			if ( array() === $allowed ) {
				break;
			}
			$claim_options                  = $options;
			$claim_options['content_types'] = $allowed;
			$row                            = Movies_WP_Telegram_Queue::claim( $claim_options );
			if ( ! is_array( $row ) ) {
				break;
			}
			$one       = self::deliver_claimed( $row, $options );
			$results[] = $one;
			if ( 'skipped' === (string) ( $one['outcome'] ?? '' ) ) {
				break;
			}
		}

		$ready_options                  = $options;
		$ready_options['content_types'] = self::allowed_types();
		$next                           = Movies_WP_Telegram_Queue::next_ready_at( $ready_options );
		if ( null !== $next ) {
			self::ensure_scheduled( $options, $next );
		}
		return array(
			'processed' => count( $results ),
			'busy'      => false,
			'results'   => $results,
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function deliver_claimed( array $row, array $options ) {
		$id    = (int) ( $row['id'] ?? 0 );
		$token = (string) ( $row['claim_token'] ?? '' );
		$type  = (string) ( $row['content_type'] ?? '' );
		$post  = (int) ( $row['post_id'] ?? 0 );
		if ( ! class_exists( 'Movies_WP_Telegram_Config' ) || ! Movies_WP_Telegram_Config::publishing_allowed( $type ) ) {
			$released = Movies_WP_Telegram_Queue::release_claim( $id, $token, $options );
			return array(
				'id'      => $id,
				'outcome' => 'skipped',
				'saved'   => $released,
			);
		}

		try {
			$built = self::snapshot( $type, $post, $options );
			if ( ! is_array( $built ) || empty( $built['ok'] ) || ! is_array( $built['snapshot'] ?? null ) ) {
				$code = is_array( $built ) ? (string) ( $built['code'] ?? '' ) : '';
				return self::store_outcome( $id, $token, self::snapshot_outcome( $code ), $options );
			}
			$result = self::deliver( $built['snapshot'], $options );
		} catch ( \Throwable $e ) {
			return self::store_outcome(
				$id,
				$token,
				array(
					'outcome'    => 'retryable',
					'message_id' => null,
					'error'      => self::safe_error( $e->getMessage() ),
					'code'       => 'worker_exception',
				),
				$options
			);
		}
		return self::store_outcome( $id, $token, $result, $options );
	}

	/**
	 * @param int                  $id
	 * @param string               $token
	 * @param array<string, mixed> $result
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function store_outcome( $id, $token, array $result, array $options ) {
		$outcome = (string) ( $result['outcome'] ?? '' );
		$error   = self::safe_error( $result['error'] ?? '' );
		$saved   = false;
		if ( 'sent' === $outcome ) {
			$message_id = isset( $result['message_id'] ) && is_string( $result['message_id'] ) ? $result['message_id'] : '';
			$saved      = Movies_WP_Telegram_Queue::mark_sent( $id, $token, $message_id, $options );
			if ( ! $saved ) {
				$ambiguous = Movies_WP_Telegram_Queue::record_ambiguous( $id, $token, 'Telegram delivery could not be recorded.', $options );
				$saved     = ! empty( $ambiguous['ok'] );
				$outcome   = 'ambiguous';
			}
		} elseif ( 'retryable' === $outcome ) {
			$stored = Movies_WP_Telegram_Queue::record_retryable( $id, $token, $error, $options );
			$saved  = ! empty( $stored['ok'] );
		} elseif ( 'permanent' === $outcome ) {
			$stored = Movies_WP_Telegram_Queue::record_permanent( $id, $token, $error, $options );
			$saved  = ! empty( $stored['ok'] );
		} else {
			$outcome = 'ambiguous';
			$stored  = Movies_WP_Telegram_Queue::record_ambiguous( $id, $token, '' !== $error ? $error : 'Telegram delivery was ambiguous.', $options );
			$saved   = ! empty( $stored['ok'] );
		}
		return array(
			'id'      => $id,
			'outcome' => $outcome,
			'saved'   => $saved,
		);
	}

	/**
	 * @param string               $type
	 * @param int                  $post_id
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function snapshot( $type, $post_id, array $options ) {
		if ( isset( $options['telegram_snapshot'] ) && is_callable( $options['telegram_snapshot'] ) ) {
			$built = call_user_func( $options['telegram_snapshot'], $type, $post_id, $options );
			return is_array( $built ) ? $built : array( 'ok' => false );
		}
		if ( ! class_exists( 'Movies_WP_Telegram_Snapshot_Provider' ) ) {
			return array( 'ok' => false, 'code' => 'unavailable' );
		}
		return Movies_WP_Telegram_Snapshot_Provider::build( $type, $post_id, $options );
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function deliver( array $snapshot, array $options ) {
		if ( isset( $options['deliver'] ) && is_callable( $options['deliver'] ) ) {
			$result = call_user_func( $options['deliver'], $snapshot, $options );
			return is_array( $result ) ? $result : array( 'outcome' => 'ambiguous', 'error' => '' );
		}
		if ( ! class_exists( 'Movies_WP_Telegram_Client' ) ) {
			return array(
				'outcome'    => 'retryable',
				'message_id' => null,
				'error'      => 'Telegram client is not available.',
				'code'       => 'client_missing',
			);
		}
		$client_options = $options;
		unset( $client_options['telegram_snapshot'], $client_options['deliver'], $client_options['schedule_action'], $client_options['next_scheduled'], $client_options['unschedule_action'] );
		return Movies_WP_Telegram_Client::deliver( $snapshot, $client_options );
	}

	/**
	 * @param string $code
	 * @return array{outcome:string,message_id:null,error:string,code:string}
	 */
	private static function snapshot_outcome( $code ) {
		$permanent = in_array( $code, array( 'invalid_type', 'invalid_post_id', 'wrong_type' ), true );
		return array(
			'outcome'    => $permanent ? 'permanent' : 'retryable',
			'message_id' => null,
			'error'      => 'Announcement snapshot was not available.',
			'code'       => '' !== $code ? $code : 'snapshot_failed',
		);
	}

	/**
	 * @return list<string>
	 */
	private static function allowed_types() {
		if ( ! class_exists( 'Movies_WP_Telegram_Config' ) ) {
			return array();
		}
		$allowed = array();
		foreach ( array( 'movie', 'series', 'episode' ) as $type ) {
			if ( Movies_WP_Telegram_Config::publishing_allowed( $type ) ) {
				$allowed[] = $type;
			}
		}
		return $allowed;
	}

	/**
	 * @return bool
	 */
	private static function delivery_possible() {
		if ( class_exists( 'Movies_WP_Telegram_Config' ) && Movies_WP_Telegram_Config::paused() ) {
			return false;
		}
		return array() !== self::allowed_types();
	}

	/**
	 * @param array<string, mixed> $options
	 * @return int
	 */
	private static function batch_size( array $options ) {
		$size = isset( $options['batch_size'] ) ? (int) $options['batch_size'] : self::BATCH_DEFAULT;
		if ( ! isset( $options['batch_size'] ) && function_exists( 'apply_filters' ) ) {
			$size = (int) apply_filters( 'movies_wp_telegram_worker_batch_size', $size );
		}
		if ( $size < self::BATCH_MIN ) {
			$size = self::BATCH_MIN;
		}
		if ( $size > self::BATCH_MAX ) {
			$size = self::BATCH_MAX;
		}
		return $size;
	}

	/**
	 * @param mixed $error
	 * @return string
	 */
	private static function safe_error( $error ) {
		$text = Movies_WP_Telegram_Queue::sanitize_error( $error );
		$text = preg_replace( '#https?://\S+#i', '[url]', $text );
		$text = is_string( $text ) ? $text : '';
		$text = preg_replace( '#\b(?:Movie|Series)/[A-Za-z0-9._/\-]+#', '[path]', $text );
		$text = is_string( $text ) ? $text : '';
		return Movies_WP_Telegram_Queue::sanitize_error( $text );
	}

	/**
	 * @param array<string, mixed> $options
	 * @return int
	 */
	private static function now( array $options ) {
		return isset( $options['now'] ) ? (int) $options['now'] : time();
	}

	/**
	 * @param array<string, mixed> $options
	 * @return int|false
	 */
	private static function next_scheduled( array $options ) {
		if ( isset( $options['next_scheduled'] ) && is_callable( $options['next_scheduled'] ) ) {
			$found = call_user_func( $options['next_scheduled'], self::HOOK, array(), self::GROUP );
			return is_int( $found ) && $found > 0 ? $found : false;
		}
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return false;
		}
		$found = as_next_scheduled_action( self::HOOK, array(), self::GROUP );
		return is_int( $found ) && $found > 0 ? $found : false;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function unschedule( array $options ) {
		if ( isset( $options['unschedule_action'] ) && is_callable( $options['unschedule_action'] ) ) {
			call_user_func( $options['unschedule_action'], self::HOOK, array(), self::GROUP );
			return;
		}
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
		}
	}

	/**
	 * @param int                  $when
	 * @param array<string, mixed> $options
	 * @return bool
	 */
	private static function schedule_at( $when, array $options ) {
		if ( isset( $options['schedule_action'] ) && is_callable( $options['schedule_action'] ) ) {
			$id = call_user_func( $options['schedule_action'], $when, self::HOOK, array(), self::GROUP, true );
		} elseif ( function_exists( 'as_schedule_single_action' ) ) {
			$id = as_schedule_single_action( $when, self::HOOK, array(), self::GROUP, true );
		} else {
			return false;
		}
		if ( is_wp_error( $id ) ) {
			return false;
		}
		return true === $id || ( is_int( $id ) && $id > 0 ) || ( is_string( $id ) && ctype_digit( $id ) && (int) $id > 0 );
	}
}

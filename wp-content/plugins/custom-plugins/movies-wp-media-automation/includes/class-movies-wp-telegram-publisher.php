<?php
/**
 * Enqueues a Telegram announcement after a successful import.
 *
 * Creates a queue row only. Delivery stays in Movies_WP_Telegram_Client
 * for a later worker. This class never calls deliver().
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-movies-wp-telegram-config.php';
require_once __DIR__ . '/class-movies-wp-telegram-queue.php';
require_once __DIR__ . '/class-movies-wp-telegram-snapshot-provider.php';
require_once __DIR__ . '/class-movies-wp-telegram-client.php';

class Movies_WP_Telegram_Publisher {

	/**
	 * Queue one announcement. Never throws and never sends a message.
	 *
	 * @param string               $type    movie, series, or episode.
	 * @param int                  $post_id Streamit post ID.
	 * @param string               $source  Import job token. This is the idempotency source.
	 * @param array<string, mixed> $options telegram_snapshot, telegram_insert, now. Test hooks.
	 * @return array{enqueued:bool,duplicate:bool,id:int}
	 */
	public static function enqueue( $type, $post_id, $source, array $options = array() ) {
		try {
			if ( ! is_string( $type ) || ! in_array( $type, array( 'movie', 'series', 'episode' ), true ) ) {
				return self::skipped();
			}
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				return self::skipped();
			}
			if ( ! class_exists( 'Movies_WP_Telegram_Config' ) || ! Movies_WP_Telegram_Config::publishing_allowed( $type ) ) {
				return self::skipped();
			}
			if (
				! class_exists( 'Movies_WP_Telegram_Snapshot_Provider' )
				|| ! class_exists( 'Movies_WP_Telegram_Formatter' )
				|| ! class_exists( 'Movies_WP_Telegram_Queue' )
				|| ! class_exists( 'Movies_WP_Telegram_Client' )
			) {
				return self::skipped();
			}

			$built = self::snapshot( $type, $post_id, $options );
			if ( ! is_array( $built ) || empty( $built['ok'] ) || ! is_array( $built['snapshot'] ?? null ) ) {
				return self::skipped();
			}
			$message = Movies_WP_Telegram_Formatter::build( $built['snapshot'] );
			if ( ! is_array( $message ) || empty( $message['ok'] ) ) {
				return self::skipped();
			}

			if ( isset( $options['telegram_insert'] ) && is_callable( $options['telegram_insert'] ) ) {
				$row = call_user_func( $options['telegram_insert'], $type, $post_id, $source, $options );
			} else {
				$row = Movies_WP_Telegram_Queue::insert( $type, $post_id, $source, $options );
			}
			if ( ! is_array( $row ) || empty( $row['ok'] ) ) {
				return self::skipped();
			}
			if ( class_exists( 'Movies_WP_Telegram_Worker' ) ) {
				Movies_WP_Telegram_Worker::ensure_scheduled( $options );
			}
			return array(
				'enqueued'  => true,
				'duplicate' => ! empty( $row['duplicate'] ),
				'id'        => (int) ( $row['id'] ?? 0 ),
			);
		} catch ( \Throwable $e ) {
			unset( $e );
			return self::skipped();
		}
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
		return Movies_WP_Telegram_Snapshot_Provider::build( $type, $post_id, $options );
	}

	/**
	 * @return array{enqueued:bool,duplicate:bool,id:int}
	 */
	private static function skipped() {
		return array(
			'enqueued'  => false,
			'duplicate' => false,
			'id'        => 0,
		);
	}
}

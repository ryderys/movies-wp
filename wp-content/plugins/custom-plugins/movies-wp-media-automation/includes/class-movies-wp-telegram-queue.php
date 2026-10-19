<?php
/**
 * Persistent Telegram publish queue.
 *
 * Idempotency key preimage (not the post ID alone):
 *   v1|{content_type}|{post_id}|{source_length}|{source}
 * The stored event_key is the SHA-256 hex of that preimage. Callers pass the
 * import job token as source, so one callback cannot insert twice and a later
 * import with a new token can.
 *
 * Claims are one conditional UPDATE, matching the series import job store.
 * That statement is atomic on InnoDB. MySQL 8.0 is the database in
 * docker-compose.yml. The statement does not use SKIP LOCKED, so it also
 * runs on MySQL 5.7 and MariaDB. Completion writes require the claim token.
 *
 * Ambiguous send timeouts are stored as status "ambiguous" and are not
 * eligible for another claim. This class does not call Telegram.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Telegram_Queue {

	const MAX_ATTEMPTS   = 5;
	const BACKOFF_BASE   = 60;
	const BACKOFF_CAP    = 3600;
	const LEASE_SECONDS  = 120;
	const FAILURE_LIMIT  = 20;
	const FAILURE_MAX    = 50;
	const ERROR_MAX_LEN  = 500;

	/**
	 * @param string $type   movie, series, or episode.
	 * @param int    $post_id
	 * @param string $source Import job token, or another caller-supplied idempotency source.
	 * @return string 64-char hex, or empty when the parts are invalid.
	 */
	public static function event_key( $type, $post_id, $source ) {
		if ( ! self::valid_type( $type ) || ! self::valid_post_id( $post_id ) || ! self::valid_source( $source ) ) {
			return '';
		}
		$source   = (string) $source;
		$preimage = 'v1|' . $type . '|' . (int) $post_id . '|' . strlen( $source ) . '|' . $source;
		return hash( 'sha256', $preimage );
	}

	/**
	 * Insert a pending event. A duplicate event_key is a no-op success.
	 *
	 * @param array<string, mixed> $options now.
	 * @return array<string, mixed>
	 */
	public static function insert( $type, $post_id, $source, array $options = array() ) {
		$key = self::event_key( $type, $post_id, $source );
		if ( '' === $key ) {
			return self::rejected( self::invalid_code( $type, $post_id, $source ) );
		}
		$now = self::mysql_time( self::now( $options ) );
		$row = array(
			'event_key'           => $key,
			'content_type'        => (string) $type,
			'post_id'             => (int) $post_id,
			'status'              => 'pending',
			'attempt_count'       => 0,
			'next_attempt_at'     => null,
			'last_attempt_at'     => null,
			'last_error'          => null,
			'telegram_message_id' => null,
			'sent_at'             => null,
			'claim_token'         => null,
			'claimed_until'       => null,
			'created_at'          => $now,
			'updated_at'          => $now,
		);
		if ( self::use_memory() ) {
			foreach ( self::$memory as $existing ) {
				if ( (string) $existing['event_key'] === $key ) {
					return array(
						'ok'        => true,
						'duplicate' => true,
						'id'        => (int) $existing['id'],
						'event_key' => $key,
					);
				}
			}
			++self::$seq;
			$row['id']                 = self::$seq;
			self::$memory[ self::$seq ] = $row;
			return array(
				'ok'        => true,
				'duplicate' => false,
				'id'        => self::$seq,
				'event_key' => $key,
			);
		}

		global $wpdb;
		if ( ! self::db_ready() ) {
			return self::rejected( 'unavailable' );
		}
		$table   = self::table();
		$sql_row = array();
		foreach ( $row as $column => $value ) {
			if ( null !== $value ) {
				$sql_row[ $column ] = $value;
			}
		}
		$ok = $wpdb->insert( $table, $sql_row );
		if ( false === $ok ) {
			$error = isset( $wpdb->last_error ) ? (string) $wpdb->last_error : '';
			if ( false !== stripos( $error, 'Duplicate' ) ) {
				$existing = self::find_by_event_key( $key );
				return array(
					'ok'        => true,
					'duplicate' => true,
					'id'        => is_array( $existing ) ? (int) $existing['id'] : 0,
					'event_key' => $key,
				);
			}
			return self::rejected( 'db_error' );
		}
		return array(
			'ok'        => true,
			'duplicate' => false,
			'id'        => (int) $wpdb->insert_id,
			'event_key' => $key,
		);
	}

	/**
	 * Claim one eligible job. Expired leases are eligible so a crashed worker can be recovered.
	 *
	 * @param array<string, mixed> $options now.
	 * @return array<string, mixed>|null
	 */
	public static function claim( array $options = array() ) {
		$types = self::content_type_filter( $options );
		if ( is_array( $types ) && array() === $types ) {
			return null;
		}
		$now   = self::now( $options );
		$token = self::claim_token();
		$until = self::mysql_time( $now + self::LEASE_SECONDS );
		$now_sql = self::mysql_time( $now );
		for ( $try = 0; $try < 5; $try++ ) {
			if ( self::use_memory() ) {
				$id = self::memory_eligible_id( $now, $types );
				if ( $id <= 0 ) {
					return null;
				}
				if ( ! self::memory_own( $id, $token, $until, $now_sql, $now ) ) {
					continue;
				}
				return self::find( $id );
			}
			$claimed = self::claim_one_sql( $now_sql, $token, $until, $types );
			if ( is_array( $claimed ) ) {
				return $claimed;
			}
		}
		return null;
	}

	/**
	 * Earliest time a pending, retry, or expired claim can be taken.
	 * Null when nothing is waiting.
	 *
	 * @param array<string, mixed> $options now.
	 * @return int|null
	 */
	public static function next_ready_at( array $options = array() ) {
		$types = self::content_type_filter( $options );
		if ( is_array( $types ) && array() === $types ) {
			return null;
		}
		$now = self::now( $options );
		if ( self::use_memory() ) {
			$soonest = null;
			foreach ( self::$memory as $row ) {
				if ( is_array( $types ) && ! in_array( (string) ( $row['content_type'] ?? '' ), $types, true ) ) {
					continue;
				}
				if ( self::row_eligible( $row, $now ) ) {
					return $now;
				}
				if ( ! in_array( (string) ( $row['status'] ?? '' ), array( 'pending', 'retry' ), true ) ) {
					continue;
				}
				$next = self::sql_time( $row['next_attempt_at'] ?? '' );
				if ( $next > $now && ( null === $soonest || $next < $soonest ) ) {
					$soonest = $next;
				}
			}
			return $soonest;
		}
		if ( ! self::db_ready() ) {
			return null;
		}
		global $wpdb;
		$table    = self::table();
		$now_sql  = self::mysql_time( $now );
		$type_sql = self::type_sql( $types );
		$due      = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE (
					( status = 'pending' AND ( next_attempt_at IS NULL OR next_attempt_at <= %s ) )
					OR ( status = 'retry' AND next_attempt_at IS NOT NULL AND next_attempt_at <= %s )
					OR ( status = 'claimed' AND claimed_until IS NOT NULL AND claimed_until < %s )
				)" . $type_sql['sql'] . ' LIMIT 1',
				...array_merge( array( $now_sql, $now_sql, $now_sql ), $type_sql['values'] )
			)
		);
		if ( $due ) {
			return $now;
		}
		$future_sql = "SELECT MIN(next_attempt_at) FROM {$table} WHERE status IN ('pending','retry') AND next_attempt_at IS NOT NULL" . $type_sql['sql'];
		$future     = array() === $type_sql['values']
			? $wpdb->get_var( $future_sql )
			: $wpdb->get_var( $wpdb->prepare( $future_sql, ...$type_sql['values'] ) );
		$parsed = self::sql_time( is_string( $future ) ? $future : '' );
		return $parsed > $now ? $parsed : null;
	}

	/**
	 * @param array<string, mixed> $options now.
	 * @return bool
	 */
	public static function mark_sent( $id, $claim_token, $message_id, array $options = array() ) {
		$id         = (int) $id;
		$message_id = is_string( $message_id ) ? $message_id : '';
		if ( $id <= 0 || ! self::valid_claim_token( $claim_token ) || 1 !== preg_match( '/^\d{1,32}$/', $message_id ) ) {
			return false;
		}
		$now = self::mysql_time( self::now( $options ) );
		$fields = array(
			'status'              => 'sent',
			'telegram_message_id' => $message_id,
			'sent_at'             => $now,
			'last_attempt_at'     => $now,
			'claim_token'         => null,
			'claimed_until'       => null,
			'updated_at'          => $now,
			'last_error'          => null,
		);
		return self::finish_owned( $id, (string) $claim_token, $fields );
	}

	/**
	 * Schedule another attempt, or record a permanent failure when the cap is reached.
	 *
	 * @param array<string, mixed> $options now, backoff_base, backoff_cap.
	 * @return array<string, mixed>
	 */
	public static function record_retryable( $id, $claim_token, $error, array $options = array() ) {
		$id = (int) $id;
		if ( $id <= 0 || ! self::valid_claim_token( $claim_token ) ) {
			return array( 'ok' => false, 'code' => 'invalid' );
		}
		$row = self::find( $id );
		if ( ! is_array( $row ) || ! self::owns( $row, $claim_token ) ) {
			return array( 'ok' => false, 'code' => 'stale_claim' );
		}
		$now     = self::now( $options );
		$attempt = (int) $row['attempt_count'] + 1;
		$clean   = self::sanitize_error( $error );
		if ( $attempt >= self::MAX_ATTEMPTS ) {
			$fields = self::terminal_fields( 'failed', $attempt, $clean, $now );
			$ok     = self::finish_owned( $id, (string) $claim_token, $fields );
			return array(
				'ok'            => $ok,
				'status'        => $ok ? 'failed' : 'claimed',
				'attempt_count' => $ok ? $attempt : (int) $row['attempt_count'],
				'next_attempt_at' => null,
			);
		}
		$delay  = self::backoff_seconds( $attempt, $options );
		$fields = array(
			'status'          => 'retry',
			'attempt_count'   => $attempt,
			'next_attempt_at' => self::mysql_time( $now + $delay ),
			'last_attempt_at' => self::mysql_time( $now ),
			'last_error'      => $clean,
			'claim_token'     => null,
			'claimed_until'   => null,
			'updated_at'      => self::mysql_time( $now ),
		);
		$ok = self::finish_owned( $id, (string) $claim_token, $fields );
		return array(
			'ok'              => $ok,
			'status'          => $ok ? 'retry' : 'claimed',
			'attempt_count'   => $ok ? $attempt : (int) $row['attempt_count'],
			'next_attempt_at' => $ok ? $fields['next_attempt_at'] : null,
			'delay_seconds'   => $ok ? $delay : 0,
		);
	}

	/**
	 * @param array<string, mixed> $options now.
	 * @return array<string, mixed>
	 */
	public static function record_permanent( $id, $claim_token, $error, array $options = array() ) {
		return self::record_terminal( $id, $claim_token, $error, 'failed', $options );
	}

	/**
	 * A send that timed out after it may have been accepted. Not claimable again.
	 *
	 * @param array<string, mixed> $options now.
	 * @return array<string, mixed>
	 */
	public static function record_ambiguous( $id, $claim_token, $error, array $options = array() ) {
		return self::record_terminal( $id, $claim_token, $error, 'ambiguous', $options );
	}

	/**
	 * Return expired claims to retry so another worker can take them.
	 *
	 * @param array<string, mixed> $options now.
	 * @return int
	 */
	public static function release_expired( array $options = array() ) {
		$now     = self::now( $options );
		$now_sql = self::mysql_time( $now );
		if ( self::use_memory() ) {
			$count = 0;
			foreach ( self::$memory as $id => $row ) {
				if ( 'claimed' !== (string) $row['status'] ) {
					continue;
				}
				$until = self::sql_time( $row['claimed_until'] ?? '' );
				if ( $until <= 0 || $until >= $now ) {
					continue;
				}
				self::$memory[ $id ]['status']          = 'retry';
				self::$memory[ $id ]['claim_token']     = null;
				self::$memory[ $id ]['claimed_until']   = null;
				self::$memory[ $id ]['next_attempt_at'] = $now_sql;
				self::$memory[ $id ]['updated_at']      = $now_sql;
				++$count;
			}
			return $count;
		}
		if ( ! self::db_ready() ) {
			return 0;
		}
		global $wpdb;
		$table = self::table();
		$sql   = $wpdb->prepare(
			"UPDATE {$table}
			SET status = %s, claim_token = NULL, claimed_until = NULL, next_attempt_at = %s, updated_at = %s
			WHERE status = %s AND claimed_until IS NOT NULL AND claimed_until < %s",
			'retry',
			$now_sql,
			$now_sql,
			'claimed',
			$now_sql
		);
		$wpdb->query( $sql );
		return (int) $wpdb->rows_affected;
	}

	/**
	 * Manually return one failed row to pending. Ambiguous rows are not eligible.
	 *
	 * @param array<string, mixed> $options now.
	 * @return bool
	 */
	public static function requeue_failed( $id, array $options = array() ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$now = self::mysql_time( self::now( $options ) );
		if ( self::use_memory() ) {
			if ( ! isset( self::$memory[ $id ] ) || 'failed' !== (string) ( self::$memory[ $id ]['status'] ?? '' ) ) {
				return false;
			}
			self::$memory[ $id ]['status']          = 'pending';
			self::$memory[ $id ]['attempt_count']   = 0;
			self::$memory[ $id ]['next_attempt_at'] = null;
			self::$memory[ $id ]['last_error']      = null;
			self::$memory[ $id ]['claim_token']     = null;
			self::$memory[ $id ]['claimed_until']   = null;
			self::$memory[ $id ]['updated_at']      = $now;
			return true;
		}
		if ( ! self::db_ready() ) {
			return false;
		}
		global $wpdb;
		$table = self::table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = %s, attempt_count = %d, next_attempt_at = NULL, last_error = NULL,
					claim_token = NULL, claimed_until = NULL, updated_at = %s
				WHERE id = %d AND status = %s",
				'pending',
				0,
				$now,
				$id,
				'failed'
			)
		);
		return 1 === (int) $wpdb->rows_affected;
	}

	/**
	 * Give a claim back without counting an attempt. The caller must own it.
	 *
	 * @param array<string, mixed> $options now.
	 * @return bool
	 */
	public static function release_claim( $id, $claim_token, array $options = array() ) {
		$id = (int) $id;
		if ( $id <= 0 || ! self::valid_claim_token( $claim_token ) ) {
			return false;
		}
		$row = self::find( $id );
		if ( ! is_array( $row ) || ! self::owns( $row, $claim_token ) ) {
			return false;
		}
		$now     = self::mysql_time( self::now( $options ) );
		$attempt = (int) ( $row['attempt_count'] ?? 0 );
		$fields  = array(
			'status'          => $attempt > 0 ? 'retry' : 'pending',
			'claim_token'     => null,
			'claimed_until'   => null,
			'updated_at'      => $now,
			'next_attempt_at' => $attempt > 0 ? $now : null,
		);
		return self::finish_owned( $id, (string) $claim_token, $fields );
	}

	/**
	 * @return array<string, int>
	 */
	public static function counts() {
		$counts = array(
			'pending'   => 0,
			'claimed'   => 0,
			'retry'     => 0,
			'sent'      => 0,
			'failed'    => 0,
			'ambiguous' => 0,
		);
		if ( self::use_memory() ) {
			foreach ( self::$memory as $row ) {
				$status = (string) $row['status'];
				if ( isset( $counts[ $status ] ) ) {
					++$counts[ $status ];
				}
			}
			return $counts;
		}
		if ( ! self::db_ready() ) {
			return $counts;
		}
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return $counts;
		}
		foreach ( $rows as $row ) {
			$status = isset( $row['status'] ) ? (string) $row['status'] : '';
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = (int) $row['n'];
			}
		}
		return $counts;
	}

	/**
	 * @param int $limit
	 * @return list<array<string, mixed>>
	 */
	public static function recent_failures( $limit = self::FAILURE_LIMIT ) {
		$limit = (int) $limit;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > self::FAILURE_MAX ) {
			$limit = self::FAILURE_MAX;
		}
		$rows = array();
		if ( self::use_memory() ) {
			foreach ( self::$memory as $row ) {
				if ( ! in_array( (string) $row['status'], array( 'failed', 'ambiguous', 'retry' ), true ) ) {
					continue;
				}
				if ( ! is_string( $row['last_error'] ?? null ) || '' === $row['last_error'] ) {
					continue;
				}
				$rows[] = $row;
			}
			usort(
				$rows,
				static function ( $a, $b ) {
					$by_time = strcmp( (string) ( $b['updated_at'] ?? '' ), (string) ( $a['updated_at'] ?? '' ) );
					if ( 0 !== $by_time ) {
						return $by_time;
					}
					return (int) $b['id'] <=> (int) $a['id'];
				}
			);
			$rows = array_slice( $rows, 0, $limit );
		} elseif ( self::db_ready() ) {
			global $wpdb;
			$table = self::table();
			$found = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, content_type, post_id, status, last_error, updated_at
					FROM {$table}
					WHERE status IN ('failed','ambiguous','retry') AND last_error IS NOT NULL AND last_error <> ''
					ORDER BY updated_at DESC, id DESC
					LIMIT %d",
					$limit
				),
				ARRAY_A
			);
			$rows = is_array( $found ) ? $found : array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'id'           => (int) $row['id'],
				'content_type' => (string) $row['content_type'],
				'post_id'      => (int) $row['post_id'],
				'status'       => (string) $row['status'],
				'last_error'   => self::sanitize_error( (string) ( $row['last_error'] ?? '' ) ),
				'updated_at'   => (string) ( $row['updated_at'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * @param int $attempt 1-based attempt that just failed.
	 * @param array<string, mixed> $options backoff_base, backoff_cap.
	 * @return int
	 */
	public static function backoff_seconds( $attempt, array $options = array() ) {
		$base = isset( $options['backoff_base'] ) ? (int) $options['backoff_base'] : self::BACKOFF_BASE;
		$cap  = isset( $options['backoff_cap'] ) ? (int) $options['backoff_cap'] : self::BACKOFF_CAP;
		if ( $base < 1 ) {
			$base = 1;
		}
		if ( $cap < 1 ) {
			$cap = 1;
		}
		$attempt = (int) $attempt;
		if ( $attempt < 1 ) {
			$attempt = 1;
		}
		$shift = $attempt - 1;
		if ( $shift > 16 ) {
			$shift = 16;
		}
		$delay = $base * ( 2 ** $shift );
		if ( $delay > $cap || $delay < 1 ) {
			return $cap;
		}
		return $delay;
	}

	/**
	 * @param mixed $error
	 * @return string
	 */
	public static function sanitize_error( $error ) {
		$text = is_string( $error ) ? $error : '';
		$text = preg_replace( '/\d{5,}:[A-Za-z0-9_-]{10,}/', '[redacted]', $text );
		$text = is_string( $text ) ? $text : '';
		$text = preg_replace( '/bot\d{5,}:[A-Za-z0-9_-]+/i', 'bot[redacted]', $text );
		$text = is_string( $text ) ? $text : '';
		$text = preg_replace( '/authorization\s*:\s*(?:bearer\s+)?\S+/i', 'authorization: [redacted]', $text );
		$text = is_string( $text ) ? $text : '';
		$text = preg_replace( '/((?:api[_-]?key|token|secret)\s*[=:]\s*)\S+/i', '$1[redacted]', $text );
		$text = is_string( $text ) ? $text : '';
		$text = str_replace( array( "\r", "\n", "\0" ), ' ', $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) ?? '' );
		if ( strlen( $text ) > self::ERROR_MAX_LEN ) {
			$text = substr( $text, 0, self::ERROR_MAX_LEN );
		}
		return $text;
	}

	/**
	 * @return bool
	 */
	public static function reset_state() {
		if ( ! self::use_memory() ) {
			return false;
		}
		self::$memory = array();
		self::$seq    = 0;
		return true;
	}

	/**
	 * @return int
	 */
	public static function count_rows() {
		if ( self::use_memory() ) {
			return count( self::$memory );
		}
		if ( ! self::db_ready() ) {
			return 0;
		}
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/** @var array<int, array<string, mixed>> */
	private static $memory = array();

	/** @var int */
	private static $seq = 0;

	/**
	 * @param string $status
	 * @param mixed  $error
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function record_terminal( $id, $claim_token, $error, $status, array $options ) {
		$id = (int) $id;
		if ( $id <= 0 || ! self::valid_claim_token( $claim_token ) ) {
			return array( 'ok' => false, 'code' => 'invalid' );
		}
		$row = self::find( $id );
		if ( ! is_array( $row ) || ! self::owns( $row, $claim_token ) ) {
			return array( 'ok' => false, 'code' => 'stale_claim' );
		}
		$now     = self::now( $options );
		$attempt = (int) $row['attempt_count'] + 1;
		$fields  = self::terminal_fields( $status, $attempt, self::sanitize_error( $error ), $now );
		$ok      = self::finish_owned( $id, (string) $claim_token, $fields );
		return array(
			'ok'            => $ok,
			'status'        => $ok ? $status : 'claimed',
			'attempt_count' => $ok ? $attempt : (int) $row['attempt_count'],
		);
	}

	/**
	 * @param string $status
	 * @param int    $attempt
	 * @param string $error
	 * @param int    $now
	 * @return array<string, mixed>
	 */
	private static function terminal_fields( $status, $attempt, $error, $now ) {
		return array(
			'status'          => $status,
			'attempt_count'   => $attempt,
			'next_attempt_at' => null,
			'last_attempt_at' => self::mysql_time( $now ),
			'last_error'      => $error,
			'claim_token'     => null,
			'claimed_until'   => null,
			'updated_at'      => self::mysql_time( $now ),
		);
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return bool
	 */
	private static function finish_owned( $id, $claim_token, array $fields ) {
		if ( self::use_memory() ) {
			if ( ! isset( self::$memory[ $id ] ) || ! self::owns( self::$memory[ $id ], $claim_token ) ) {
				return false;
			}
			foreach ( $fields as $key => $value ) {
				self::$memory[ $id ][ $key ] = $value;
			}
			return true;
		}
		if ( ! self::db_ready() ) {
			return false;
		}
		global $wpdb;
		$table  = self::table();
		$sets   = array();
		$values = array();
		foreach ( $fields as $key => $value ) {
			if ( ! self::writable_column( $key ) ) {
				continue;
			}
			if ( null === $value ) {
				$sets[] = "{$key} = NULL";
				continue;
			}
			$sets[]   = "{$key} = %s";
			$values[] = is_int( $value ) ? (string) $value : (string) $value;
		}
		$values[] = $id;
		$values[] = $claim_token;
		$values[] = 'claimed';
		$sql = $wpdb->prepare(
			'UPDATE ' . $table . ' SET ' . implode( ', ', $sets ) . ' WHERE id = %d AND claim_token = %s AND status = %s',
			...$values
		);
		$wpdb->query( $sql );
		return 1 === (int) $wpdb->rows_affected;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<string>|null Null means every content type is eligible.
	 */
	private static function content_type_filter( array $options ) {
		if ( ! array_key_exists( 'content_types', $options ) ) {
			return null;
		}
		if ( ! is_array( $options['content_types'] ) ) {
			return array();
		}
		$types = array();
		foreach ( $options['content_types'] as $type ) {
			if ( is_string( $type ) && self::valid_type( $type ) ) {
				$types[] = $type;
			}
		}
		return array_values( array_unique( $types ) );
	}

	/**
	 * @param list<string>|null $types
	 * @return array{sql:string,values:list<string>}
	 */
	private static function type_sql( $types ) {
		if ( ! is_array( $types ) ) {
			return array(
				'sql'    => '',
				'values' => array(),
			);
		}
		if ( array() === $types ) {
			return array(
				'sql'    => ' AND 1=0',
				'values' => array(),
			);
		}
		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		return array(
			'sql'    => " AND content_type IN ({$placeholders})",
			'values' => array_values( $types ),
		);
	}

	/**
	 * @param string            $now_sql
	 * @param string            $claim_token
	 * @param string            $until_sql
	 * @param list<string>|null $types
	 * @return array<string, mixed>|null
	 */
	private static function claim_one_sql( $now_sql, $claim_token, $until_sql, $types = null ) {
		global $wpdb;
		$table = self::table();
		$type_sql = self::type_sql( $types );
		$where = "(
			( status = 'pending' AND ( next_attempt_at IS NULL OR next_attempt_at <= %s ) )
			OR ( status = 'retry' AND next_attempt_at IS NOT NULL AND next_attempt_at <= %s )
			OR ( status = 'claimed' AND claimed_until IS NOT NULL AND claimed_until < %s )
		)" . $type_sql['sql'];
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT 1",
				...array_merge( array( $now_sql, $now_sql, $now_sql ), $type_sql['values'] )
			)
		);
		if ( ! $id ) {
			return null;
		}
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = %s, claim_token = %s, claimed_until = %s, updated_at = %s
				WHERE id = %d AND {$where}",
				'claimed',
				$claim_token,
				$until_sql,
				$now_sql,
				(int) $id,
				$now_sql,
				$now_sql,
				$now_sql,
				...$type_sql['values']
			)
		);
		if ( 1 !== (int) $wpdb->rows_affected ) {
			return null;
		}
		return self::find( (int) $id );
	}

	/**
	 * @param int $now
	 * @return int
	 */
	private static function memory_eligible_id( $now, $types = null ) {
		$ids = array_keys( self::$memory );
		sort( $ids );
		foreach ( $ids as $id ) {
			$row = self::$memory[ $id ];
			if ( is_array( $types ) && ! in_array( (string) ( $row['content_type'] ?? '' ), $types, true ) ) {
				continue;
			}
			if ( self::row_eligible( $row, $now ) ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/**
	 * @param int    $id
	 * @param string $token
	 * @param string $until
	 * @param string $now_sql
	 * @param int    $now
	 * @return bool
	 */
	private static function memory_own( $id, $token, $until, $now_sql, $now ) {
		if ( ! isset( self::$memory[ $id ] ) || ! self::row_eligible( self::$memory[ $id ], $now ) ) {
			return false;
		}
		self::$memory[ $id ]['status']        = 'claimed';
		self::$memory[ $id ]['claim_token']   = $token;
		self::$memory[ $id ]['claimed_until'] = $until;
		self::$memory[ $id ]['updated_at']    = $now_sql;
		return true;
	}

	/**
	 * Keep this predicate aligned with the WHERE clause in claim_one_sql().
	 *
	 * @param array<string, mixed> $row
	 * @param int                  $now
	 * @return bool
	 */
	private static function row_eligible( array $row, $now ) {
		$status = (string) ( $row['status'] ?? '' );
		if ( 'pending' === $status ) {
			$next = self::sql_time( $row['next_attempt_at'] ?? '' );
			return $next <= 0 || $next <= $now;
		}
		if ( 'retry' === $status ) {
			$next = self::sql_time( $row['next_attempt_at'] ?? '' );
			return $next > 0 && $next <= $now;
		}
		if ( 'claimed' === $status ) {
			$until = self::sql_time( $row['claimed_until'] ?? '' );
			return $until > 0 && $until < $now;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param mixed                $claim_token
	 * @return bool
	 */
	private static function owns( array $row, $claim_token ) {
		return 'claimed' === (string) ( $row['status'] ?? '' )
			&& is_string( $claim_token )
			&& '' !== $claim_token
			&& hash_equals( (string) ( $row['claim_token'] ?? '' ), $claim_token );
	}

	/**
	 * @param int $id
	 * @return array<string, mixed>|null
	 */
	private static function find( $id ) {
		if ( self::use_memory() ) {
			return isset( self::$memory[ $id ] ) ? self::$memory[ $id ] : null;
		}
		if ( ! self::db_ready() ) {
			return null;
		}
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param string $key
	 * @return array<string, mixed>|null
	 */
	private static function find_by_event_key( $key ) {
		if ( self::use_memory() ) {
			foreach ( self::$memory as $row ) {
				if ( (string) $row['event_key'] === $key ) {
					return $row;
				}
			}
			return null;
		}
		if ( ! self::db_ready() ) {
			return null;
		}
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE event_key = %s", $key ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param mixed $type
	 * @param mixed $post_id
	 * @param mixed $source
	 * @return string
	 */
	private static function invalid_code( $type, $post_id, $source ) {
		if ( ! self::valid_type( $type ) ) {
			return 'invalid_type';
		}
		if ( ! self::valid_post_id( $post_id ) ) {
			return 'invalid_post_id';
		}
		unset( $source );
		return 'invalid_source';
	}

	/**
	 * @param string $code
	 * @return array<string, mixed>
	 */
	private static function rejected( $code ) {
		return array(
			'ok'        => false,
			'duplicate' => false,
			'id'        => 0,
			'event_key' => '',
			'code'      => $code,
		);
	}

	/**
	 * @param mixed $type
	 * @return bool
	 */
	private static function valid_type( $type ) {
		return is_string( $type ) && in_array( $type, array( 'movie', 'series', 'episode' ), true );
	}

	/**
	 * @param mixed $post_id
	 * @return bool
	 */
	private static function valid_post_id( $post_id ) {
		if ( is_int( $post_id ) ) {
			return $post_id > 0;
		}
		if ( is_string( $post_id ) && 1 === preg_match( '/^[1-9]\d*$/', $post_id ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param mixed $source
	 * @return bool
	 */
	private static function valid_source( $source ) {
		if ( ! is_string( $source ) ) {
			return false;
		}
		$length = strlen( $source );
		if ( $length < 8 || $length > 200 ) {
			return false;
		}
		return 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $source );
	}

	/**
	 * @param mixed $token
	 * @return bool
	 */
	private static function valid_claim_token( $token ) {
		return is_string( $token ) && '' !== $token && strlen( $token ) <= 64;
	}

	/**
	 * @param string $column
	 * @return bool
	 */
	private static function writable_column( $column ) {
		return in_array(
			$column,
			array(
				'status',
				'attempt_count',
				'next_attempt_at',
				'last_attempt_at',
				'last_error',
				'telegram_message_id',
				'sent_at',
				'claim_token',
				'claimed_until',
				'updated_at',
			),
			true
		);
	}

	/**
	 * @return bool
	 */
	private static function use_memory() {
		return defined( 'MOVIES_WP_TELEGRAM_TEST_MEMORY' ) && MOVIES_WP_TELEGRAM_TEST_MEMORY;
	}

	/**
	 * @return bool
	 */
	private static function db_ready() {
		global $wpdb;
		return isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'query' );
	}

	/**
	 * @return string
	 */
	private static function table() {
		if ( class_exists( 'Movies_WP_Telegram_Schema' ) ) {
			return Movies_WP_Telegram_Schema::table_name();
		}
		global $wpdb;
		$prefix = ( isset( $wpdb ) && is_object( $wpdb ) && isset( $wpdb->prefix ) ) ? (string) $wpdb->prefix : 'wp_';
		return $prefix . 'movies_wp_telegram_queue';
	}

	/**
	 * @return string
	 */
	private static function claim_token() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 24, false, false );
		}
		return bin2hex( random_bytes( 12 ) );
	}

	/**
	 * @param array<string, mixed> $options
	 * @return int
	 */
	private static function now( array $options ) {
		return isset( $options['now'] ) ? (int) $options['now'] : time();
	}

	/**
	 * @param int $timestamp
	 * @return string
	 */
	private static function mysql_time( $timestamp ) {
		return gmdate( 'Y-m-d H:i:s', (int) $timestamp );
	}

	/**
	 * @param mixed $value
	 * @return int
	 */
	private static function sql_time( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return 0;
		}
		$parsed = strtotime( $value . ' UTC' );
		return false === $parsed ? 0 : (int) $parsed;
	}
}

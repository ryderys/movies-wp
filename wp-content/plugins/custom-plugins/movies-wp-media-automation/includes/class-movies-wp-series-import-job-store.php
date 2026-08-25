<?php
/**
 * MySQL store for resumable Series import jobs.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Import_Job_Store {

	const LEASE_SECONDS_DEFAULT = 180;
	const LEASE_SECONDS_MIN     = 60;
	const LEASE_SECONDS_MAX     = 600;
	const RECENT_LIMIT_DEFAULT  = 10;
	const RECENT_LIMIT_MAX      = 50;
	/**
	 * Extra inactivity beyond one lease before Resume may reclaim a running job.
	 * Recovery threshold = lease_seconds() + stall_grace_seconds() (default 2× lease).
	 */
	const STALL_GRACE_SECONDS_DEFAULT = 180;

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private static $memory = array();

	/**
	 * @var int
	 */
	private static $memory_seq = 0;

	public static function reset_memory() {
		self::$memory     = array();
		self::$memory_seq = 0;
	}

	public static function lease_seconds() {
		$size = self::LEASE_SECONDS_DEFAULT;
		if ( function_exists( 'apply_filters' ) ) {
			$size = (int) apply_filters( 'movies_wp_series_import_claim_lease_seconds', $size );
		}
		if ( $size < self::LEASE_SECONDS_MIN ) {
			$size = self::LEASE_SECONDS_MIN;
		}
		if ( $size > self::LEASE_SECONDS_MAX ) {
			$size = self::LEASE_SECONDS_MAX;
		}
		return $size;
	}

	/**
	 * Additional inactivity required (after lease expiry) before Resume may reclaim.
	 * Chosen as one lease window so recovery waits ~2× lease after last worker activity,
	 * matching the longest pre-heartbeat opaque ticks once mid-phase heartbeats are in place.
	 */
	public static function stall_grace_seconds() {
		$size = self::STALL_GRACE_SECONDS_DEFAULT;
		if ( function_exists( 'apply_filters' ) ) {
			$size = (int) apply_filters( 'movies_wp_series_import_stall_grace_seconds', $size );
		}
		if ( $size < self::LEASE_SECONDS_MIN ) {
			$size = self::LEASE_SECONDS_MIN;
		}
		if ( $size > self::LEASE_SECONDS_MAX ) {
			$size = self::LEASE_SECONDS_MAX;
		}
		return $size;
	}

	/**
	 * Inactivity age required for hard stall / Resume eligibility.
	 */
	public static function stall_recovery_seconds() {
		return self::lease_seconds() + self::stall_grace_seconds();
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array{id:int,token:string}|WP_Error
	 */
	public static function create( array $fields, array $context = array() ) {
		$token  = self::generate_token();
		$now    = self::now( $context );
		$result = is_array( $fields['result'] ?? null ) ? $fields['result'] : array();
		// Persist the opaque access token so Recent Imports can reopen Progress after the admin leaves.
		$result['access_token'] = $token;
		$row                    = array(
			'token_hash'      => Movies_WP_Series_Import_Snapshot_Store::hash_token( $token ),
			'user_id'         => (int) ( $fields['user_id'] ?? 0 ),
			'blog_id'         => (int) ( $fields['blog_id'] ?? 1 ),
			'tmdb_id'         => (int) ( $fields['tmdb_id'] ?? 0 ),
			'series_id'       => isset( $fields['series_id'] ) ? (int) $fields['series_id'] : null,
			'directory'       => (string) ( $fields['directory'] ?? '' ),
			'snapshot_id'     => (int) ( $fields['snapshot_id'] ?? 0 ),
			'status'          => 'preparing',
			'phase'           => 'series',
			'episode_done'    => 0,
			'episode_total'   => (int) ( $fields['episode_total'] ?? 0 ),
			'last_episode_id' => null,
			'last_error'      => null,
			'warnings_json'   => self::encode( array() ),
			'result_json'     => self::encode( $result ),
			'claimed_until'   => null,
			'claim_token'     => null,
			'active_slot'     => 1,
			'elapsed_ms'      => 0,
			'created_at'      => self::mysql_time( $now ),
			'updated_at'      => self::mysql_time( $now ),
		);
		$id = self::insert_row( $row );
		if ( $id < 0 ) {
			return new WP_Error( 'series_import_job_duplicate', __( 'An import job for this preview is already active.', 'movies-wp' ) );
		}
		if ( $id <= 0 ) {
			return new WP_Error( 'series_import_job_persist_failed', __( 'Could not create the Series import job.', 'movies-wp' ) );
		}
		$row['id']       = $id;
		$row['token']    = $token;
		$row['result']   = $result;
		$row['warnings'] = array();
		return $row;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function find_by_token( $raw_token ) {
		$raw = is_string( $raw_token ) ? trim( $raw_token ) : '';
		if ( '' === $raw ) {
			return null;
		}
		$row = self::find_by_hash( Movies_WP_Series_Import_Snapshot_Store::hash_token( $raw ) );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$row['token']    = $raw;
		$row['result']   = self::decode( (string) ( $row['result_json'] ?? '' ) );
		$row['warnings'] = self::decode( (string) ( $row['warnings_json'] ?? '' ) );
		if ( ! is_array( $row['result'] ) ) {
			$row['result'] = array();
		}
		if ( ! is_array( $row['warnings'] ) ) {
			$row['warnings'] = array();
		}
		return $row;
	}

	/**
	 * Atomically claim a job. Does not rely on a prior SELECT for correctness
	 * on the MySQL path; memory tests emulate the same predicate.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function claim( $raw_token, array $context = array() ) {
		$raw = is_string( $raw_token ) ? trim( $raw_token ) : '';
		if ( '' === $raw ) {
			return null;
		}
		$hash        = Movies_WP_Series_Import_Snapshot_Store::hash_token( $raw );
		$now         = self::now( $context );
		$now_sql     = self::mysql_time( $now );
		$until_sql   = self::mysql_time( $now + self::lease_seconds() );
		$claim_token = self::generate_claim_token();

		$affected = self::use_memory()
			? self::memory_claim( $hash, $now, $until_sql, $claim_token, $now_sql )
			: self::sql_claim( $hash, $now_sql, $until_sql, $claim_token );

		if ( $affected > 0 ) {
			return self::find_by_token( $raw );
		}

		$existing = self::find_by_token( $raw );
		if ( is_array( $existing ) && in_array( (string) $existing['status'], array( 'completed', 'failed', 'paused' ), true ) ) {
			return $existing;
		}
		return null;
	}

	/**
	 * Renew the lease for the worker that owns $claim_token.
	 *
	 * @return bool
	 */
	public static function heartbeat( $raw_token, $claim_token, array $context = array() ) {
		$raw   = is_string( $raw_token ) ? trim( $raw_token ) : '';
		$token = is_string( $claim_token ) ? trim( $claim_token ) : '';
		if ( '' === $raw || '' === $token ) {
			return false;
		}
		$hash      = Movies_WP_Series_Import_Snapshot_Store::hash_token( $raw );
		$now       = self::now( $context );
		$until_sql = self::mysql_time( $now + self::lease_seconds() );
		$now_sql   = self::mysql_time( $now );

		if ( self::use_memory() ) {
			if ( ! isset( self::$memory[ $hash ] ) ) {
				return false;
			}
			$row = self::$memory[ $hash ];
			if ( 'running' !== (string) ( $row['status'] ?? '' ) ) {
				return false;
			}
			if ( (string) ( $row['claim_token'] ?? '' ) !== $token ) {
				return false;
			}
			self::$memory[ $hash ]['claimed_until'] = $until_sql;
			self::$memory[ $hash ]['updated_at']    = $now_sql;
			return true;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'movies_wp_series_import_jobs';
		$sql   = $wpdb->prepare(
			"UPDATE {$table} SET claimed_until = %s, updated_at = %s WHERE token_hash = %s AND claim_token = %s AND status = %s",
			$until_sql,
			$now_sql,
			$hash,
			$token,
			'running'
		);
		$wpdb->query( $sql );
		if ( (int) $wpdb->rows_affected > 0 ) {
			return true;
		}
		// MySQL reports 0 changed rows when claimed_until/updated_at already equal this second.
		$owned = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE token_hash = %s AND claim_token = %s AND status = %s LIMIT 1",
				$hash,
				$token,
				'running'
			)
		);
		return absint( $owned ) > 0;
	}

	public static function update( $raw_token, array $fields, array $context = array() ) {
		$row = self::find_by_token( $raw_token );
		if ( ! is_array( $row ) ) {
			return false;
		}
		if ( isset( $fields['result'] ) && is_array( $fields['result'] ) ) {
			$existing_access = '';
			if ( isset( $row['result']['access_token'] ) && is_string( $row['result']['access_token'] ) ) {
				$existing_access = $row['result']['access_token'];
			} elseif ( isset( $row['token'] ) && is_string( $row['token'] ) ) {
				$existing_access = $row['token'];
			}
			if ( '' !== $existing_access && empty( $fields['result']['access_token'] ) ) {
				$fields['result']['access_token'] = $existing_access;
			}
			$fields['result_json'] = self::encode( $fields['result'] );
			unset( $fields['result'] );
		}
		if ( isset( $fields['warnings'] ) && is_array( $fields['warnings'] ) ) {
			$fields['warnings_json'] = self::encode( $fields['warnings'] );
			unset( $fields['warnings'] );
		}
		if ( isset( $fields['status'] ) ) {
			$status = (string) $fields['status'];
			if ( in_array( $status, array( 'completed', 'failed' ), true ) ) {
				$fields['active_slot'] = null;
				$fields['claim_token'] = null;
				$fields['claimed_until'] = array_key_exists( 'claimed_until', $fields ) ? $fields['claimed_until'] : null;
			} elseif ( in_array( $status, array( 'preparing', 'queued', 'running', 'paused' ), true ) && ! array_key_exists( 'active_slot', $fields ) ) {
				$fields['active_slot'] = 1;
			}
		}
		$fields['updated_at'] = self::mysql_time( self::now( $context ) );
		$hash                 = Movies_WP_Series_Import_Snapshot_Store::hash_token( $raw_token );
		if ( self::use_memory() ) {
			self::$memory[ $hash ] = array_merge( self::$memory[ $hash ], $fields );
			return true;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'movies_wp_series_import_jobs';
		unset( $fields['token'], $fields['id'] );
		return false !== $wpdb->update( $table, $fields, array( 'token_hash' => $hash ) );
	}

	public static function has_active_for_snapshot( $snapshot_id, $user_id, $blog_id ) {
		$snapshot_id = (int) $snapshot_id;
		foreach ( self::all_rows() as $row ) {
			if ( (int) $row['snapshot_id'] !== $snapshot_id ) {
				continue;
			}
			if ( (int) $row['user_id'] !== (int) $user_id || (int) $row['blog_id'] !== (int) $blog_id ) {
				continue;
			}
			if ( 1 === (int) ( $row['active_slot'] ?? 0 ) ) {
				return true;
			}
			if ( in_array( (string) $row['status'], array( 'queued', 'running', 'preparing', 'paused' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Newest jobs for one owner, ordered by updated_at DESC.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function list_for_owner( $user_id, $blog_id, $limit = self::RECENT_LIMIT_DEFAULT ) {
		$user_id = (int) $user_id;
		$blog_id = (int) $blog_id;
		$limit   = (int) $limit;
		if ( $limit < 1 ) {
			$limit = self::RECENT_LIMIT_DEFAULT;
		}
		if ( $limit > self::RECENT_LIMIT_MAX ) {
			$limit = self::RECENT_LIMIT_MAX;
		}
		if ( $user_id <= 0 ) {
			return array();
		}

		$rows = array();
		if ( self::use_memory() ) {
			foreach ( self::$memory as $row ) {
				if ( (int) ( $row['user_id'] ?? 0 ) !== $user_id || (int) ( $row['blog_id'] ?? 0 ) !== $blog_id ) {
					continue;
				}
				$rows[] = $row;
			}
		} else {
			global $wpdb;
			if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
				return array();
			}
			if ( class_exists( 'Movies_WP_Series_Import_Schema' ) && ! Movies_WP_Series_Import_Schema::tables_exist() ) {
				return array();
			}
			$table = $wpdb->prefix . 'movies_wp_series_import_jobs';
			$found = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d AND blog_id = %d ORDER BY updated_at DESC, id DESC LIMIT %d",
					$user_id,
					$blog_id,
					$limit
				),
				ARRAY_A
			);
			$rows = is_array( $found ) ? $found : array();
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				$ua = (string) ( $a['updated_at'] ?? '' );
				$ub = (string) ( $b['updated_at'] ?? '' );
				if ( $ua === $ub ) {
					return (int) ( $b['id'] ?? 0 ) <=> (int) ( $a['id'] ?? 0 );
				}
				return strcmp( $ub, $ua );
			}
		);
		$rows = array_slice( $rows, 0, $limit );

		$out = array();
		foreach ( $rows as $row ) {
			$hydrated = self::hydrate_row( $row );
			if ( is_array( $hydrated ) ) {
				$out[] = $hydrated;
			}
		}
		return $out;
	}

	/**
	 * Soft stall signal for display. Never mutates job status.
	 *
	 * Running: lease expired (worker may still be alive if updated_at is recent).
	 * Queued: no recent activity within one lease window.
	 *
	 * @param array<string, mixed> $job
	 * @param array<string, mixed> $context Optional `now` unix timestamp.
	 */
	public static function is_possibly_stalled( array $job, array $context = array() ) {
		$status = (string) ( $job['status'] ?? '' );
		if ( ! in_array( $status, array( 'queued', 'running' ), true ) ) {
			return false;
		}
		$now = self::now( $context );
		if ( 'running' === $status ) {
			return self::lease_is_expired( $job, $now ) || self::updated_at_is_older_than( $job, $now, self::lease_seconds() );
		}
		return self::updated_at_is_older_than( $job, $now, self::lease_seconds() );
	}

	/**
	 * Hard stall: safe to offer Resume / allow resume() reclaim.
	 *
	 * Running requires an expired lease AND updated_at older than lease+grace so a
	 * slow healthy worker that still heartbeats/updates cannot be reclaimed.
	 *
	 * @param array<string, mixed> $job
	 * @param array<string, mixed> $context Optional `now` unix timestamp.
	 */
	public static function is_resume_eligible_stall( array $job, array $context = array() ) {
		$status = (string) ( $job['status'] ?? '' );
		if ( ! in_array( $status, array( 'queued', 'running' ), true ) ) {
			return false;
		}
		$now        = self::now( $context );
		$inactivity = self::stall_recovery_seconds();
		if ( 'running' === $status ) {
			return self::lease_is_expired( $job, $now ) && self::updated_at_is_older_than( $job, $now, $inactivity );
		}
		return self::updated_at_is_older_than( $job, $now, $inactivity );
	}

	/**
	 * @param array<string, mixed> $job
	 */
	private static function lease_is_expired( array $job, $now ) {
		$until = (string) ( $job['claimed_until'] ?? '' );
		if ( '' === $until ) {
			return true;
		}
		$until_ts = strtotime( $until . ' UTC' );
		return false !== $until_ts && $until_ts < $now;
	}

	/**
	 * @param array<string, mixed> $job
	 */
	private static function updated_at_is_older_than( array $job, $now, $seconds ) {
		$updated = (string) ( $job['updated_at'] ?? '' );
		if ( '' === $updated ) {
			return false;
		}
		$updated_ts = strtotime( $updated . ' UTC' );
		if ( false === $updated_ts ) {
			return false;
		}
		return ( $now - $updated_ts ) > (int) $seconds;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>|null
	 */
	private static function hydrate_row( array $row ) {
		$row['result']   = self::decode( (string) ( $row['result_json'] ?? '' ) );
		$row['warnings'] = self::decode( (string) ( $row['warnings_json'] ?? '' ) );
		if ( ! is_array( $row['result'] ) ) {
			$row['result'] = array();
		}
		if ( ! is_array( $row['warnings'] ) ) {
			$row['warnings'] = array();
		}
		$token = '';
		if ( isset( $row['token'] ) && is_string( $row['token'] ) && '' !== $row['token'] ) {
			$token = $row['token'];
		} elseif ( ! empty( $row['result']['access_token'] ) && is_string( $row['result']['access_token'] ) ) {
			$token = $row['result']['access_token'];
		}
		if ( '' !== $token ) {
			$row['token'] = $token;
		} else {
			unset( $row['token'] );
		}
		// Never expose hash/claim material to list consumers as primary identifiers.
		return $row;
	}

	private static function sql_claim( $hash, $now_sql, $until_sql, $claim_token ) {
		global $wpdb;
		$table = $wpdb->prefix . 'movies_wp_series_import_jobs';
		$sql   = $wpdb->prepare(
			"UPDATE {$table}
			SET status = %s, claimed_until = %s, claim_token = %s, updated_at = %s
			WHERE token_hash = %s
			AND (
				status IN ('preparing','queued')
				OR ( status = 'running' AND ( claimed_until IS NULL OR claimed_until < %s ) )
			)",
			'running',
			$until_sql,
			$claim_token,
			$now_sql,
			$hash,
			$now_sql
		);
		$wpdb->query( $sql );
		return (int) $wpdb->rows_affected;
	}

	private static function memory_claim( $hash, $now, $until_sql, $claim_token, $now_sql ) {
		if ( ! isset( self::$memory[ $hash ] ) ) {
			return 0;
		}
		$row    = self::$memory[ $hash ];
		$status = (string) ( $row['status'] ?? '' );
		$until  = isset( $row['claimed_until'] ) && is_string( $row['claimed_until'] ) && '' !== $row['claimed_until']
			? strtotime( $row['claimed_until'] . ' UTC' )
			: 0;
		$open   = in_array( $status, array( 'preparing', 'queued' ), true )
			|| ( 'running' === $status && $until < $now );
		if ( ! $open ) {
			return 0;
		}
		self::$memory[ $hash ]['status']        = 'running';
		self::$memory[ $hash ]['claimed_until'] = $until_sql;
		self::$memory[ $hash ]['claim_token']   = $claim_token;
		self::$memory[ $hash ]['updated_at']    = $now_sql;
		return 1;
	}

	private static function all_rows() {
		if ( self::use_memory() ) {
			return array_values( self::$memory );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'movies_wp_series_import_jobs';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	private static function use_memory() {
		return defined( 'MOVIES_WP_SERIES_IMPORT_TEST_MEMORY' ) && MOVIES_WP_SERIES_IMPORT_TEST_MEMORY;
	}

	private static function insert_row( array $row ) {
		if ( self::use_memory() ) {
			foreach ( self::$memory as $existing ) {
				if ( (int) ( $existing['snapshot_id'] ?? 0 ) !== (int) $row['snapshot_id'] ) {
					continue;
				}
				if ( (int) ( $existing['user_id'] ?? 0 ) !== (int) $row['user_id'] ) {
					continue;
				}
				if ( (int) ( $existing['blog_id'] ?? 0 ) !== (int) $row['blog_id'] ) {
					continue;
				}
				if ( 1 === (int) ( $existing['active_slot'] ?? 0 ) && 1 === (int) ( $row['active_slot'] ?? 0 ) ) {
					return -1;
				}
			}
			++self::$memory_seq;
			$row['id']    = self::$memory_seq;
			$row['token'] = isset( $row['result_json'] ) ? ( self::decode( (string) $row['result_json'] )['access_token'] ?? '' ) : '';
			self::$memory[ $row['token_hash'] ] = $row;
			return self::$memory_seq;
		}
		global $wpdb;
		$ok = $wpdb->insert( $wpdb->prefix . 'movies_wp_series_import_jobs', $row );
		if ( false === $ok ) {
			$error = isset( $wpdb->last_error ) ? (string) $wpdb->last_error : '';
			if ( false !== stripos( $error, 'Duplicate' ) ) {
				return -1;
			}
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	private static function find_by_hash( $hash ) {
		if ( self::use_memory() ) {
			return isset( self::$memory[ $hash ] ) ? self::$memory[ $hash ] : null;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'movies_wp_series_import_jobs';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s", $hash ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	private static function generate_token() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 32, false, false );
		}
		return bin2hex( random_bytes( 16 ) );
	}

	private static function generate_claim_token() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 24, false, false );
		}
		return bin2hex( random_bytes( 12 ) );
	}

	private static function encode( array $data ) {
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data ) : json_encode( $data );
		return is_string( $json ) ? $json : '{}';
	}

	private static function decode( $json ) {
		$data = json_decode( (string) $json, true );
		return is_array( $data ) ? $data : array();
	}

	private static function now( array $context ) {
		return isset( $context['now'] ) ? (int) $context['now'] : time();
	}

	private static function mysql_time( $timestamp ) {
		return gmdate( 'Y-m-d H:i:s', (int) $timestamp );
	}
}

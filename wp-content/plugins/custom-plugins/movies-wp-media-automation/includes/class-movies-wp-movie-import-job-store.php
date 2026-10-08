<?php
/**
 * Option-based store for background Movie import jobs.
 *
 * Series import uses MySQL tables because it is resumable + chunked. Movie import is
 * a single execute() call, so an options-backed store is sufficient and keeps the
 * schema surface minimal.
 *
 * @package movies-wp
 */
 
defined( 'ABSPATH' ) || exit;
 
class Movies_WP_Movie_Import_Job_Store {
 
	const LEASE_SECONDS_DEFAULT = 180;
	const LEASE_SECONDS_MIN     = 60;
	const LEASE_SECONDS_MAX     = 600;
	const RECENT_LIMIT_DEFAULT  = 10;
	const RECENT_LIMIT_MAX      = 50;
 
	private static function job_option_key( $token_hash ) {
		return 'movies_wp_movie_import_job_' . (string) $token_hash;
	}
 
	private static function recent_option_key( $user_id, $blog_id ) {
		return 'movies_wp_movie_import_recent_' . (int) $blog_id . '_' . (int) $user_id;
	}
 
	public static function lease_seconds() {
		$size = self::LEASE_SECONDS_DEFAULT;
		if ( function_exists( 'apply_filters' ) ) {
			$size = (int) apply_filters( 'movies_wp_movie_import_claim_lease_seconds', $size );
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
	 * @param array<string, mixed> $request Normalized admin request inputs.
	 * @param array<string, mixed> $context user_id, blog_id, now.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create( array $request, array $context = array() ) {
		$token  = self::generate_token();
		$hash   = self::hash_token( $token );
		$now    = self::now( $context );
		$user_id = (int) ( $context['user_id'] ?? self::current_user_id() );
		$blog_id = (int) ( $context['blog_id'] ?? self::current_blog_id() );
 
		// Persist the opaque access token so Recent Imports can reopen Progress after the admin leaves.
		$result = array(
			'access_token' => $token,
		);

		$row = array(
			'token_hash'    => $hash,
			'user_id'       => $user_id,
			'blog_id'       => $blog_id,
			'tmdb_id'       => (int) ( $request['tmdb_id'] ?? 0 ),
			'directory'     => (string) ( $request['media_directory'] ?? '' ),
			'status'        => 'preparing',
			'last_error'    => null,
			'request_json'  => self::encode( $request ),
			'result_json'   => self::encode( $result ),
			'warnings_json' => self::encode( array() ),
			'action_id'     => null,
			'claimed_until' => null,
			'claim_token'   => null,
			'elapsed_ms'    => 0,
			'created_at'    => self::mysql_time( $now ),
			'updated_at'    => self::mysql_time( $now ),
		);
 
		$ok = function_exists( 'add_option' ) ? add_option( self::job_option_key( $hash ), $row, '', false ) : false;
		if ( ! $ok ) {
			// Extremely unlikely (token collision) but keep behavior deterministic.
			return new WP_Error( 'movie_import_job_persist_failed', __( 'Could not create the Movie import job.', 'movies-wp' ) );
		}
 
		self::record_recent( $hash, $user_id, $blog_id );
 
		$row['token']    = $token;
		$row['request']  = $request;
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
		$hash = self::hash_token( $raw );
		$row  = function_exists( 'get_option' ) ? get_option( self::job_option_key( $hash ), null ) : null;
		if ( ! is_array( $row ) ) {
			return null;
		}
		$row['token']    = $raw;
		$row['request']  = self::decode( (string) ( $row['request_json'] ?? '' ) );
		$row['result']   = self::decode( (string) ( $row['result_json'] ?? '' ) );
		$row['warnings'] = self::decode( (string) ( $row['warnings_json'] ?? '' ) );
		if ( ! is_array( $row['request'] ) ) {
			$row['request'] = array();
		}
		if ( ! is_array( $row['result'] ) ) {
			$row['result'] = array();
		}
		if ( ! is_array( $row['warnings'] ) ) {
			$row['warnings'] = array();
		}
		return $row;
	}
 
	/**
	 * @param array<string, mixed> $fields
	 * @param array<string, mixed> $context
	 * @return bool
	 */
	public static function update( $raw_token, array $fields, array $context = array() ) {
		$job = self::find_by_token( $raw_token );
		if ( ! is_array( $job ) ) {
			return false;
		}
		$now = self::now( $context );
		foreach ( $fields as $k => $v ) {
			if ( 'request' === $k && is_array( $v ) ) {
				$job['request_json'] = self::encode( $v );
				continue;
			}
			if ( 'result' === $k && is_array( $v ) ) {
				$job['result_json'] = self::encode( $v );
				continue;
			}
			if ( 'warnings' === $k && is_array( $v ) ) {
				$job['warnings_json'] = self::encode( $v );
				continue;
			}
			$job[ $k ] = $v;
		}
		$job['updated_at'] = self::mysql_time( $now );
 
		$hash = isset( $job['token_hash'] ) ? (string) $job['token_hash'] : '';
		if ( '' === $hash ) {
			return false;
		}

		// Never persist hydrated fields (raw token, decoded request/result arrays).
		unset( $job['token'], $job['request'], $job['result'], $job['warnings'] );

		return function_exists( 'update_option' ) ? (bool) update_option( self::job_option_key( $hash ), $job, false ) : false;
	}
 
	/**
	 * Best-effort claim. Returns null if another worker currently owns a valid lease.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function claim( $raw_token, array $context = array() ) {
		$job = self::find_by_token( $raw_token );
		if ( ! is_array( $job ) ) {
			return null;
		}
		$status = (string) ( $job['status'] ?? '' );
		if ( in_array( $status, array( 'completed', 'failed', 'paused' ), true ) ) {
			return $job;
		}
		$now = self::now( $context );
		$until = isset( $job['claimed_until'] ) ? strtotime( (string) $job['claimed_until'] . ' UTC' ) : 0;
		if ( $until && $until > $now && ! empty( $job['claim_token'] ) ) {
			return null;
		}
 
		$claim_token            = self::generate_claim_token();
		$job['claimed_until']   = self::mysql_time( $now + self::lease_seconds() );
		$job['claim_token']     = $claim_token;
		$job['status']          = 'running';
		$job['updated_at']      = self::mysql_time( $now );
 
		$hash = isset( $job['token_hash'] ) ? (string) $job['token_hash'] : '';
		if ( '' === $hash ) {
			return null;
		}
		unset( $job['token'], $job['request'], $job['result'], $job['warnings'] );
		$ok   = function_exists( 'update_option' ) ? (bool) update_option( self::job_option_key( $hash ), $job, false ) : false;
		if ( ! $ok ) {
			return null;
		}
		return self::find_by_token( $raw_token );
	}
 
	/**
	 * @return bool
	 */
	public static function heartbeat( $raw_token, $claim_token, array $context = array() ) {
		$job = self::find_by_token( $raw_token );
		if ( ! is_array( $job ) ) {
			return false;
		}
		if ( 'running' !== (string) ( $job['status'] ?? '' ) ) {
			return false;
		}
		if ( (string) ( $job['claim_token'] ?? '' ) !== (string) $claim_token ) {
			return false;
		}
		$now = self::now( $context );
		return self::update(
			$raw_token,
			array(
				'claimed_until' => self::mysql_time( $now + self::lease_seconds() ),
			),
			$context
		);
	}
 
	/**
	 * @return list<array<string, mixed>>
	 */
	public static function list_for_owner( $user_id, $blog_id, $limit = self::RECENT_LIMIT_DEFAULT ) {
		$user_id = (int) $user_id;
		$blog_id = (int) $blog_id;
		$limit   = (int) $limit;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > self::RECENT_LIMIT_MAX ) {
			$limit = self::RECENT_LIMIT_MAX;
		}
		$key  = self::recent_option_key( $user_id, $blog_id );
		$list = function_exists( 'get_option' ) ? get_option( $key, array() ) : array();
		if ( ! is_array( $list ) ) {
			return array();
		}
		$out = array();
		foreach ( $list as $hash ) {
			if ( ! is_string( $hash ) || '' === $hash ) {
				continue;
			}
			$row = function_exists( 'get_option' ) ? get_option( self::job_option_key( $hash ), null ) : null;
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row['token']    = ''; // never reveal raw token in lists.
			$row['request']  = self::decode( (string) ( $row['request_json'] ?? '' ) );
			$row['result']   = self::decode( (string) ( $row['result_json'] ?? '' ) );
			$row['warnings'] = self::decode( (string) ( $row['warnings_json'] ?? '' ) );
			$out[] = $row;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}
 
	public static function is_possibly_stalled( array $job, array $context = array() ) {
		if ( 'running' !== (string) ( $job['status'] ?? '' ) ) {
			return false;
		}
		$now   = self::now( $context );
		$until = isset( $job['claimed_until'] ) ? strtotime( (string) $job['claimed_until'] . ' UTC' ) : 0;
		if ( ! $until ) {
			return true;
		}
		// Consider stalled after one full lease without heartbeat.
		return $until + self::lease_seconds() < $now;
	}
 
	private static function record_recent( $hash, $user_id, $blog_id ) {
		$key  = self::recent_option_key( $user_id, $blog_id );
		$list = function_exists( 'get_option' ) ? get_option( $key, array() ) : array();
		$list = is_array( $list ) ? $list : array();
		$hash = (string) $hash;
		$list = array_values( array_filter( $list, static function ( $h ) use ( $hash ) {
			return is_string( $h ) && $h !== $hash;
		} ) );
		array_unshift( $list, $hash );
		$list = array_slice( $list, 0, self::RECENT_LIMIT_MAX );
		if ( function_exists( 'update_option' ) ) {
			update_option( $key, $list, false );
		}
	}
 
	public static function hash_token( $token ) {
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'nonce' ) : 'movies-wp-movie-import';
		return hash_hmac( 'sha256', (string) $token, $salt );
	}
 
	private static function generate_token() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 32, false, false );
		}
		return bin2hex( random_bytes( 16 ) );
	}
 
	private static function generate_claim_token() {
		return bin2hex( random_bytes( 16 ) );
	}
 
	private static function encode( array $payload ) {
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload );
		return is_string( $json ) ? $json : '{}';
	}
 
	private static function decode( $json ) {
		$data = json_decode( (string) $json, true );
		return is_array( $data ) ? $data : null;
	}
 
	private static function now( array $context ) {
		if ( isset( $context['now'] ) ) {
			return (int) $context['now'];
		}
		return time();
	}
 
	private static function mysql_time( $timestamp ) {
		return gmdate( 'Y-m-d H:i:s', (int) $timestamp );
	}
 
	private static function current_user_id() {
		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}
 
	private static function current_blog_id() {
		return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
	}
}


<?php
/**
 * MySQL store for Series import preview snapshots.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Import_Snapshot_Store {

	const TTL_SECONDS = 1200;

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

	/**
	 * @param array<string, mixed> $payload Combined preview.
	 * @param array<string, mixed> $context user_id, blog_id, now.
	 * @return array{id:int,token:string,expires_at:string}|WP_Error
	 */
	public static function create( array $payload, array $context = array() ) {
		$token = self::generate_token();
		$hash  = self::hash_token( $token );
		$now   = self::now( $context );
		$ttl   = isset( $context['ttl'] ) ? (int) $context['ttl'] : self::TTL_SECONDS;
		$row   = array(
			'token_hash'  => $hash,
			'user_id'     => (int) ( $context['user_id'] ?? self::current_user_id() ),
			'blog_id'     => (int) ( $context['blog_id'] ?? self::current_blog_id() ),
			'payload'     => self::encode( $payload ),
			'fingerprint' => self::fingerprint( $payload ),
			'expires_at'  => self::mysql_time( $now + $ttl ),
			'created_at'  => self::mysql_time( $now ),
			'updated_at'  => self::mysql_time( $now ),
		);
		$id = self::insert_row( $row );
		if ( $id <= 0 ) {
			return new WP_Error( 'series_import_snapshot_persist_failed', __( 'Could not save the Series import snapshot.', 'movies-wp' ) );
		}
		return array(
			'id'         => $id,
			'token'      => $token,
			'expires_at' => $row['expires_at'],
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function load_valid( $raw_token, array $context = array() ) {
		$raw = is_string( $raw_token ) ? trim( $raw_token ) : '';
		if ( '' === $raw ) {
			return new WP_Error( 'series_import_snapshot_missing', __( 'Series import snapshot token is missing.', 'movies-wp' ) );
		}
		$row = self::find_by_hash( self::hash_token( $raw ) );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'series_import_snapshot_not_found', __( 'Series import snapshot was not found.', 'movies-wp' ) );
		}
		$now = self::now( $context );
		if ( strtotime( (string) $row['expires_at'] . ' UTC' ) < $now ) {
			return new WP_Error( 'series_import_snapshot_expired', __( 'The Series import snapshot has expired. Preview the series again.', 'movies-wp' ) );
		}
		$user_id = (int) ( $context['user_id'] ?? self::current_user_id() );
		$blog_id = (int) ( $context['blog_id'] ?? self::current_blog_id() );
		if ( (int) $row['user_id'] !== $user_id ) {
			return new WP_Error( 'series_import_snapshot_user_mismatch', __( 'This Series import snapshot belongs to another user.', 'movies-wp' ) );
		}
		if ( (int) $row['blog_id'] !== $blog_id ) {
			return new WP_Error( 'series_import_snapshot_site_mismatch', __( 'This Series import snapshot belongs to another site.', 'movies-wp' ) );
		}
		$payload = self::decode( (string) $row['payload'] );
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'series_import_snapshot_invalid', __( 'Series import snapshot payload is invalid.', 'movies-wp' ) );
		}
		$row['payload'] = $payload;
		return $row;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function fingerprint( array $payload ) {
		$input = is_array( $payload['input'] ?? null ) ? $payload['input'] : array();
		$scan  = is_array( $payload['media'] ?? null ) ? $payload['media'] : array();
		$bits  = array(
			(string) ( $input['tmdb_id'] ?? '' ),
			(string) ( $input['title'] ?? '' ),
			(string) ( $input['series_directory'] ?? '' ),
		);
		foreach ( isset( $scan['episodes'] ) && is_array( $scan['episodes'] ) ? $scan['episodes'] : array() as $episode ) {
			if ( ! is_array( $episode ) ) {
				continue;
			}
			$bits[] = (string) ( $episode['season_number'] ?? '' ) . ':' . (string) ( $episode['episode_number'] ?? '' );
			foreach ( isset( $episode['sources'] ) && is_array( $episode['sources'] ) ? $episode['sources'] : array() as $source ) {
				$bits[] = is_array( $source ) ? (string) ( $source['media_path'] ?? '' ) : '';
			}
			foreach ( isset( $episode['subtitles'] ) && is_array( $episode['subtitles'] ) ? $episode['subtitles'] : array() as $sub ) {
				$bits[] = is_array( $sub ) ? (string) ( $sub['media_path'] ?? '' ) : '';
			}
		}
		return hash( 'sha256', implode( "\n", $bits ) );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function find_by_id( $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		if ( self::use_memory() ) {
			foreach ( self::$memory as $row ) {
				if ( (int) ( $row['id'] ?? 0 ) === $id ) {
					$row['payload'] = self::decode( (string) $row['payload'] );
					return is_array( $row['payload'] ) ? $row : null;
				}
			}
			return null;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'movies_wp_series_import_snapshots';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$row['payload'] = self::decode( (string) $row['payload'] );
		return is_array( $row['payload'] ) ? $row : null;
	}

	public static function hash_token( $token ) {
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'nonce' ) : 'movies-wp-series-import';
		return hash_hmac( 'sha256', (string) $token, $salt );
	}

	private static function generate_token() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 32, false, false );
		}
		return bin2hex( random_bytes( 16 ) );
	}

	private static function use_memory() {
		return defined( 'MOVIES_WP_SERIES_IMPORT_TEST_MEMORY' ) && MOVIES_WP_SERIES_IMPORT_TEST_MEMORY;
	}

	private static function insert_row( array $row ) {
		if ( self::use_memory() ) {
			++self::$memory_seq;
			$row['id']                    = self::$memory_seq;
			self::$memory[ $row['token_hash'] ] = $row;
			return self::$memory_seq;
		}
		global $wpdb;
		$ok = $wpdb->insert( $wpdb->prefix . 'movies_wp_series_import_snapshots', $row );
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	private static function find_by_hash( $hash ) {
		if ( self::use_memory() ) {
			return isset( self::$memory[ $hash ] ) ? self::$memory[ $hash ] : null;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'movies_wp_series_import_snapshots';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s", $hash ), ARRAY_A );
		return is_array( $row ) ? $row : null;
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

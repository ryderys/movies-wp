<?php
/**
 * Ongoing series eligibility (from TMDb status) and cheap media fingerprints.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Ongoing {

	const META_FINGERPRINT = '_media_sync_fingerprint';
	const META_STATUS      = '_tv_show_status';
	const META_TMDB_STATUS = '_tmdb_status';

	/**
	 * TMDb English status labels that still receive new episodes.
	 *
	 * @var list<string>
	 */
	const ONGOING_STATUSES = array(
		'Returning Series',
		'In Production',
	);

	/**
	 * @param string $status TMDb status label (English).
	 * @return bool
	 */
	public static function status_is_ongoing( $status ) {
		return in_array( trim( (string) $status ), self::ONGOING_STATUSES, true );
	}

	/**
	 * @param int                  $tvshow_id
	 * @param array<string, mixed> $options
	 * @return bool
	 */
	public static function is_ongoing( $tvshow_id, array $options = array() ) {
		$tvshow_id = (int) $tvshow_id;
		if ( $tvshow_id <= 0 ) {
			return false;
		}

		$status = trim( (string) self::get_meta( $tvshow_id, self::META_STATUS, $options ) );
		if ( '' === $status ) {
			$status = trim( (string) self::get_meta( $tvshow_id, self::META_TMDB_STATUS, $options ) );
		}

		return self::status_is_ongoing( $status );
	}

	/**
	 * @param int                  $tvshow_id
	 * @param array<string, mixed> $options
	 * @return string
	 */
	public static function get_fingerprint( $tvshow_id, array $options = array() ) {
		$value = self::get_meta( (int) $tvshow_id, self::META_FINGERPRINT, $options );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * @param int                  $tvshow_id
	 * @param string               $fingerprint
	 * @param array<string, mixed> $options
	 * @return bool
	 */
	public static function set_fingerprint( $tvshow_id, $fingerprint, array $options = array() ) {
		$tvshow_id = (int) $tvshow_id;
		if ( $tvshow_id <= 0 ) {
			return false;
		}
		return self::write_meta( $tvshow_id, self::META_FINGERPRINT, (string) $fingerprint, $options );
	}

	/**
	 * Stable hash of listed media paths + sizes (no ffprobe facts).
	 *
	 * @param array<string, mixed> $list Payload from GET /list/series.
	 * @return string
	 */
	public static function fingerprint_from_list( array $list ) {
		$files = isset( $list['files'] ) && is_array( $list['files'] ) ? $list['files'] : array();
		$bits  = array();
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			$path = str_replace( '\\', '/', trim( (string) ( $file['media_path'] ?? '' ) ) );
			if ( '' === $path ) {
				continue;
			}
			$bits[] = $path . "\t" . (string) (int) ( $file['size_bytes'] ?? 0 ) . "\t" . (string) ( $file['kind'] ?? '' );
		}
		sort( $bits, SORT_STRING );
		return hash( 'sha256', implode( "\n", $bits ) );
	}

	/**
	 * TV show IDs with an ongoing TMDb status that also have a media directory.
	 *
	 * @param array<string, mixed> $options
	 * @return list<int>
	 */
	public static function find_ongoing_ids( array $options = array() ) {
		if ( isset( $options['find_ongoing_ids'] ) && is_callable( $options['find_ongoing_ids'] ) ) {
			$found = call_user_func( $options['find_ongoing_ids'] );
			if ( ! is_array( $found ) ) {
				return array();
			}
			$ids = array();
			foreach ( $found as $id ) {
				$id = (int) $id;
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
			return array_values( array_unique( $ids ) );
		}

		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}

		$table      = $wpdb->prefix . 'streamit_tvshowmeta';
		$statuses   = self::ONGOING_STATUSES;
		$status_ph  = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$meta_keys  = array( self::META_STATUS, self::META_TMDB_STATUS );
		$key_ph     = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
		$args       = array_merge( $meta_keys, $statuses );
		$sql        = "SELECT DISTINCT streamit_tvshow_id FROM {$table} WHERE meta_key IN ({$key_ph}) AND meta_value IN ({$status_ph})";
		$prepared   = $wpdb->prepare( $sql, ...$args );
		$ids        = $wpdb->get_col( $prepared );
		if ( ! is_array( $ids ) ) {
			return array();
		}

		$out = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$dir = self::get_meta( $id, '_media_directory', $options );
			if ( ! is_string( $dir ) || '' === trim( $dir ) ) {
				continue;
			}
			$out[] = $id;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param int                  $tvshow_id
	 * @param array<string, mixed> $options
	 * @return array{tvshow_id:int,tmdb_id:int,title:string,summary:string,series_directory:string}|WP_Error
	 */
	public static function sync_input_for_tvshow( $tvshow_id, array $options = array() ) {
		$tvshow_id = (int) $tvshow_id;
		if ( $tvshow_id <= 0 ) {
			return new WP_Error( 'series_ongoing_invalid_tvshow', __( 'Invalid TV show.', 'movies-wp' ) );
		}

		$dir = self::get_meta( $tvshow_id, '_media_directory', $options );
		$dir = Movies_WP_Series_Media_Api_Client::normalize_directory( is_string( $dir ) ? $dir : '' );
		if ( is_wp_error( $dir ) ) {
			return new WP_Error( 'series_ongoing_missing_directory', __( 'Ongoing series is missing a valid media directory.', 'movies-wp' ) );
		}

		$tmdb_id = absint( self::get_meta( $tvshow_id, '_tmdb_id', $options ) );
		if ( $tmdb_id <= 0 ) {
			return new WP_Error( 'series_ongoing_missing_tmdb', __( 'Ongoing series is missing a TMDb ID.', 'movies-wp' ) );
		}

		$title = '';
		if ( isset( $options['get_tvshow'] ) && is_callable( $options['get_tvshow'] ) ) {
			$tv = call_user_func( $options['get_tvshow'], $tvshow_id );
			if ( is_object( $tv ) && isset( $tv->post_title ) ) {
				$title = (string) $tv->post_title;
			} elseif ( is_array( $tv ) && isset( $tv['post_title'] ) ) {
				$title = (string) $tv['post_title'];
			}
		} elseif ( function_exists( 'streamit_get_tvshow' ) ) {
			$tv = streamit_get_tvshow( $tvshow_id );
			if ( is_object( $tv ) && isset( $tv->post_title ) ) {
				$title = (string) $tv->post_title;
			}
		}

		// Leave summary blank so fill_series / import keeps the stored synopsis on update.
		return array(
			'tvshow_id'        => $tvshow_id,
			'tmdb_id'          => $tmdb_id,
			'title'            => $title,
			'summary'          => '',
			'series_directory' => $dir,
		);
	}

	/**
	 * @param int                  $tvshow_id
	 * @param string               $key
	 * @param array<string, mixed> $options
	 * @return mixed
	 */
	private static function get_meta( $tvshow_id, $key, array $options ) {
		if ( isset( $options['get_tvshow_meta'] ) && is_callable( $options['get_tvshow_meta'] ) ) {
			return call_user_func( $options['get_tvshow_meta'], (int) $tvshow_id, (string) $key );
		}
		if ( class_exists( 'Movies_WP_Trailer_Upload_Service', false ) ) {
			return Movies_WP_Trailer_Upload_Service::get_meta( 'tvshow', (int) $tvshow_id, (string) $key );
		}
		return '';
	}

	/**
	 * @param int                  $tvshow_id
	 * @param string               $key
	 * @param mixed                $value
	 * @param array<string, mixed> $options
	 * @return bool
	 */
	private static function write_meta( $tvshow_id, $key, $value, array $options ) {
		if ( isset( $options['update_tvshow_meta'] ) && is_callable( $options['update_tvshow_meta'] ) ) {
			$result = call_user_func( $options['update_tvshow_meta'], (int) $tvshow_id, (string) $key, $value );
			return ! is_wp_error( $result ) && false !== $result;
		}
		if ( class_exists( 'Movies_WP_Trailer_Upload_Service', false ) ) {
			$result = Movies_WP_Trailer_Upload_Service::update_meta( 'tvshow', (int) $tvshow_id, (string) $key, $value );
			return ! is_wp_error( $result ) && false !== $result;
		}
		return false;
	}
}

<?php
/**
 * Ongoing series registry and cheap media fingerprints.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Series_Ongoing {

	const META_ONGOING     = '_series_ongoing';
	const META_FINGERPRINT = '_media_sync_fingerprint';

	/**
	 * @param int                  $tvshow_id
	 * @param array<string, mixed> $options
	 * @return bool
	 */
	public static function is_ongoing( $tvshow_id, array $options = array() ) {
		$value = self::get_meta( (int) $tvshow_id, self::META_ONGOING, $options );
		return '1' === (string) $value || 1 === $value || true === $value;
	}

	/**
	 * @param int                  $tvshow_id
	 * @param bool                 $ongoing
	 * @param array<string, mixed> $options
	 * @return bool
	 */
	public static function set_ongoing( $tvshow_id, $ongoing, array $options = array() ) {
		$tvshow_id = (int) $tvshow_id;
		if ( $tvshow_id <= 0 ) {
			return false;
		}
		$value = $ongoing ? '1' : '0';
		return self::write_meta( $tvshow_id, self::META_ONGOING, $value, $options );
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
	 * TV show IDs marked ongoing that also have a media directory.
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

		$table = $wpdb->prefix . 'streamit_tvshowmeta';
		$sql   = $wpdb->prepare(
			"SELECT streamit_tvshow_id FROM {$table} WHERE meta_key = %s AND meta_value = %s",
			self::META_ONGOING,
			'1'
		);
		$ids   = $wpdb->get_col( $sql );
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

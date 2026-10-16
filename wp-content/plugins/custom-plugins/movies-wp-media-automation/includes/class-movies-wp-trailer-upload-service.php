<?php
/**
 * Orchestrates movie trailer uploads: directory resolution, replace confirmation, meta write.
 *
 * Updates only `_name_trailer_link` after a successful media-server response.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Trailer_Upload_Service {

	/**
	 * Read movie meta via Streamit helper when available.
	 *
	 * @param int    $movie_id
	 * @param string $key
	 * @return mixed
	 */
	public static function get_movie_meta( $movie_id, $key ) {
		return self::get_meta( 'movie', $movie_id, $key );
	}

	/**
	 * @param string $kind movie|tvshow
	 * @param int    $id
	 * @param string $key
	 * @return mixed
	 */
	public static function get_meta( $kind, $id, $key ) {
		$id  = (int) $id;
		$key = (string) $key;
		if ( 'tvshow' === $kind && function_exists( 'streamit_get_tvshow_meta' ) ) {
			return streamit_get_tvshow_meta( $id, $key, true );
		}
		if ( 'movie' === $kind && function_exists( 'streamit_get_movie_meta' ) ) {
			return streamit_get_movie_meta( $id, $key, true );
		}
		return get_post_meta( $id, $key, true );
	}

	/**
	 * Write movie meta via Streamit helper when available.
	 *
	 * @param int    $movie_id
	 * @param string $key
	 * @param mixed  $value
	 * @return bool|int
	 */
	public static function update_movie_meta( $movie_id, $key, $value ) {
		return self::update_meta( 'movie', $movie_id, $key, $value );
	}

	/**
	 * @param string $kind movie|tvshow
	 * @param int    $id
	 * @param string $key
	 * @param mixed  $value
	 * @return bool|int
	 */
	public static function update_meta( $kind, $id, $key, $value ) {
		$id  = (int) $id;
		$key = (string) $key;
		if ( 'tvshow' === $kind && function_exists( 'streamit_update_tvshow_meta' ) ) {
			return streamit_update_tvshow_meta( $id, $key, $value );
		}
		if ( 'movie' === $kind && function_exists( 'streamit_update_movie_meta' ) ) {
			return streamit_update_movie_meta( $id, $key, $value );
		}
		return update_post_meta( $id, $key, $value );
	}

	/**
	 * Whether existing trailer meta requires an explicit replace confirmation.
	 *
	 * @param mixed $stored
	 * @return bool
	 */
	public static function requires_replace_confirmation( $stored ) {
		$stored = trim( (string) $stored );
		return '' !== $stored;
	}

	/**
	 * Resolve and validate `_media_directory` for a movie post.
	 *
	 * @param int $movie_id
	 * @return string|WP_Error Normalized relative directory.
	 */
	public static function resolve_media_directory( $movie_id ) {
		return self::resolve_directory( 'movie', $movie_id );
	}

	/**
	 * @param string $kind movie|tvshow
	 * @param int    $id
	 * @return string|WP_Error
	 */
	public static function resolve_directory( $kind, $id ) {
		$id = (int) $id;
		if ( $id <= 0 || ! in_array( $kind, array( 'movie', 'tvshow' ), true ) ) {
			return new WP_Error( 'trailer_upload_invalid_title', __( 'Invalid title.', 'movies-wp' ) );
		}

		$raw = self::get_meta( $kind, $id, '_media_directory' );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return new WP_Error(
				'trailer_upload_missing_directory',
				__( 'This title has no media directory. Set _media_directory before uploading a trailer.', 'movies-wp' )
			);
		}

		$normalized = Movies_WP_Trailer_Upload_Client::normalize_title_directory( $raw );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}
		if ( 'tvshow' === $kind && ! str_starts_with( $normalized, 'series/' ) ) {
			return new WP_Error( 'trailer_upload_missing_directory', __( 'This title has no media directory. Set _media_directory before uploading a trailer.', 'movies-wp' ) );
		}
		if ( 'movie' === $kind && ! str_starts_with( $normalized, 'Movie/' ) ) {
			return new WP_Error( 'trailer_upload_missing_directory', __( 'This title has no media directory. Set _media_directory before uploading a trailer.', 'movies-wp' ) );
		}
		return $normalized;
	}

	/**
	 * Upload a trailer for a movie and update `_name_trailer_link` only on success.
	 *
	 * @param int    $movie_id
	 * @param string $tmp_path       Absolute path to uploaded temp file.
	 * @param string $original_name  Original client filename.
	 * @param bool   $confirm_replace
	 * @param array  $options        Passed through to the HTTP client (tests).
	 * @return array{ok:true,path:string,replaced:bool}|WP_Error
	 */
	public static function upload_for_movie( $movie_id, $tmp_path, $original_name, $confirm_replace = false, array $options = array() ) {
		return self::upload_for_title( 'movie', $movie_id, $tmp_path, $original_name, $confirm_replace, $options );
	}

	/**
	 * @param string $kind movie|tvshow
	 * @param int    $title_id
	 * @param string $tmp_path
	 * @param string $original_name
	 * @param bool   $confirm_replace
	 * @param array  $options
	 * @return array{ok:true,path:string,replaced:bool}|WP_Error
	 */
	public static function upload_for_title( $kind, $title_id, $tmp_path, $original_name, $confirm_replace = false, array $options = array() ) {
		$title_id = (int) $title_id;
		$dir      = self::resolve_directory( $kind, $title_id );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$existing = self::get_meta( $kind, $title_id, '_name_trailer_link' );
		$replaced = self::requires_replace_confirmation( $existing );
		if ( $replaced && ! $confirm_replace ) {
			return new WP_Error(
				'trailer_upload_confirm_required',
				__( 'This title already has a trailer. Confirm replacement before uploading.', 'movies-wp' ),
				array( 'status' => 409, 'requires_confirm' => true )
			);
		}

		$filename = self::normalize_upload_basename( $original_name );
		if ( is_wp_error( $filename ) ) {
			return $filename;
		}

		$result = Movies_WP_Trailer_Upload_Client::upload_trailer( $dir, $filename, $tmp_path, $options );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$path = isset( $result['path'] ) ? (string) $result['path'] : '';
		$validated = Movies_WP_Trailer_Upload_Client::validate_returned_path( $dir, $path );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$written = self::update_meta( $kind, $title_id, '_name_trailer_link', $validated );
		if ( false === $written ) {
			return new WP_Error(
				'trailer_upload_meta_failed',
				__( 'Trailer was uploaded but saving the trailer link failed.', 'movies-wp' )
			);
		}

		return array(
			'ok'       => true,
			'path'     => $validated,
			'replaced' => $replaced,
		);
	}

	/**
	 * @param string $original_name
	 * @return string|WP_Error
	 */
	public static function normalize_upload_basename( $original_name ) {
		$base = basename( str_replace( '\\', '/', (string) $original_name ) );
		return Movies_WP_Trailer_Upload_Client::normalize_filename( $base );
	}
}

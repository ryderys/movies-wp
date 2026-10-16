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
		$movie_id = (int) $movie_id;
		$key      = (string) $key;
		if ( function_exists( 'streamit_get_movie_meta' ) ) {
			return streamit_get_movie_meta( $movie_id, $key, true );
		}
		return get_post_meta( $movie_id, $key, true );
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
		$movie_id = (int) $movie_id;
		$key      = (string) $key;
		if ( function_exists( 'streamit_update_movie_meta' ) ) {
			return streamit_update_movie_meta( $movie_id, $key, $value );
		}
		return update_post_meta( $movie_id, $key, $value );
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
		$movie_id = (int) $movie_id;
		if ( $movie_id <= 0 ) {
			return new WP_Error( 'trailer_upload_invalid_movie', __( 'Invalid movie.', 'movies-wp' ) );
		}

		$raw = self::get_movie_meta( $movie_id, '_media_directory' );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return new WP_Error(
				'trailer_upload_missing_directory',
				__( 'This movie has no media directory. Set _media_directory before uploading a trailer.', 'movies-wp' )
			);
		}

		return Movies_WP_Media_Api_Client::normalize_directory( $raw );
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
		$movie_id = (int) $movie_id;
		$dir      = self::resolve_media_directory( $movie_id );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$existing = self::get_movie_meta( $movie_id, '_name_trailer_link' );
		$replaced = self::requires_replace_confirmation( $existing );
		if ( $replaced && ! $confirm_replace ) {
			return new WP_Error(
				'trailer_upload_confirm_required',
				__( 'This movie already has a trailer. Confirm replacement before uploading.', 'movies-wp' ),
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

		$written = self::update_movie_meta( $movie_id, '_name_trailer_link', $validated );
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

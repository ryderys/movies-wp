<?php
/**
 * Offload WP attachments to MinIO.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

class Streamit_Child_Minio_Offload {

	const META_OFFLOADED = '_streamit_minio_offloaded';

	/**
	 * Register attachment offload hooks.
	 */
	public static function init() {
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'offload_attachment' ), 20, 2 );
		add_filter( 'wp_get_attachment_url', array( __CLASS__, 'filter_attachment_url' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'filter_attachment_image_src' ), 10, 3 );
		add_filter( 'wp_calculate_image_srcset', array( __CLASS__, 'filter_image_srcset' ), 10, 5 );
		add_action( 'delete_attachment', array( __CLASS__, 'delete_remote_objects' ), 10, 1 );
	}

	/**
	 * Upload original + intermediate sizes after WP writes them locally.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public static function offload_attachment( $metadata, $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 || ! is_array( $metadata ) ) {
			return $metadata;
		}

		$relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( ! is_string( $relative ) || '' === $relative ) {
			return $metadata;
		}

		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return $metadata;
		}

		$client       = new Streamit_Child_Minio_Client();
		$basedir      = trailingslashit( $uploads['basedir'] );
		$remove_local = defined( 'STREAMIT_MINIO_REMOVE_LOCAL' ) && STREAMIT_MINIO_REMOVE_LOCAL;
		$uploaded     = array();

		$files = array( $relative );
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$dir = trailingslashit( dirname( $relative ) );
			if ( '.' === $dir || './' === $dir ) {
				$dir = '';
			}
			foreach ( $metadata['sizes'] as $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}
				$files[] = $dir . $size['file'];
			}
		}

		$files = array_unique( $files );

		foreach ( $files as $key ) {
			$key       = ltrim( str_replace( '\\', '/', $key ), '/' );
			$local     = $basedir . $key;
			$mime      = wp_check_filetype( $local );
			$mime_type = ! empty( $mime['type'] ) ? $mime['type'] : 'application/octet-stream';

			if ( ! is_readable( $local ) ) {
				continue;
			}

			$body = file_get_contents( $local );
			if ( false === $body ) {
				continue;
			}

			$result = $client->put_object( $key, $body, $mime_type );
			if ( is_wp_error( $result ) ) {
				error_log( 'MinIO offload failed for ' . $key . ': ' . $result->get_error_message() );
				continue;
			}

			$uploaded[] = $key;

			if ( $remove_local ) {
				@unlink( $local );
			}
		}

		if ( ! empty( $uploaded ) ) {
			update_post_meta( $attachment_id, self::META_OFFLOADED, 1 );
		}

		return $metadata;
	}

	/**
	 * @param string $url           Default URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return string
	 */
	public static function filter_attachment_url( $url, $attachment_id ) {
		if ( ! self::is_offloaded( $attachment_id ) ) {
			return $url;
		}

		$relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( ! is_string( $relative ) || '' === $relative ) {
			return $url;
		}

		$client = new Streamit_Child_Minio_Client();
		return $client->public_url( $relative );
	}

	/**
	 * @param array|false  $image         Image data.
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Size.
	 * @return array|false
	 */
	public static function filter_attachment_image_src( $image, $attachment_id, $size ) {
		if ( ! $image || ! self::is_offloaded( $attachment_id ) ) {
			return $image;
		}

		$remote = self::size_public_url( $attachment_id, $size );
		if ( $remote ) {
			$image[0] = $remote;
		}

		return $image;
	}

	/**
	 * @param array  $sources       Srcset sources.
	 * @param array  $size_array    Width/height.
	 * @param string $image_src     Image src.
	 * @param array  $image_meta    Metadata.
	 * @param int    $attachment_id Attachment ID.
	 * @return array
	 */
	public static function filter_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( ! self::is_offloaded( $attachment_id ) || ! is_array( $sources ) ) {
			return $sources;
		}

		$relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( ! is_string( $relative ) || '' === $relative ) {
			return $sources;
		}

		$dir    = trailingslashit( dirname( $relative ) );
		if ( '.' === $dir || './' === $dir ) {
			$dir = '';
		}
		$client = new Streamit_Child_Minio_Client();

		foreach ( $sources as $width => $source ) {
			if ( empty( $source['url'] ) ) {
				continue;
			}

			$basename = wp_basename( $source['url'] );
			$sources[ $width ]['url'] = $client->public_url( $dir . $basename );
		}

		return $sources;
	}

	/**
	 * Delete remote objects when an attachment is removed.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function delete_remote_objects( $attachment_id ) {
		if ( ! self::is_offloaded( $attachment_id ) ) {
			return;
		}

		$relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( ! is_string( $relative ) || '' === $relative ) {
			return;
		}

		$client = new Streamit_Child_Minio_Client();
		$meta   = wp_get_attachment_metadata( $attachment_id );
		$keys   = array( ltrim( str_replace( '\\', '/', $relative ), '/' ) );

		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			$dir = trailingslashit( dirname( $relative ) );
			if ( '.' === $dir || './' === $dir ) {
				$dir = '';
			}
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$keys[] = ltrim( $dir . $size['file'], '/' );
				}
			}
		}

		foreach ( array_unique( $keys ) as $key ) {
			$result = $client->delete_object( $key );
			if ( is_wp_error( $result ) ) {
				error_log( 'MinIO delete failed for ' . $key . ': ' . $result->get_error_message() );
			}
		}
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private static function is_offloaded( $attachment_id ) {
		return (bool) get_post_meta( (int) $attachment_id, self::META_OFFLOADED, true );
	}

	/**
	 * Resolve public URL for a registered size (or full).
	 *
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Size name or [w,h].
	 * @return string|null
	 */
	private static function size_public_url( $attachment_id, $size ) {
		$relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( ! is_string( $relative ) || '' === $relative ) {
			return null;
		}

		$client = new Streamit_Child_Minio_Client();

		if ( 'full' === $size || empty( $size ) ) {
			return $client->public_url( $relative );
		}

		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
			return $client->public_url( $relative );
		}

		$size_name = is_array( $size ) ? null : $size;
		if ( $size_name && ! empty( $meta['sizes'][ $size_name ]['file'] ) ) {
			$dir = trailingslashit( dirname( $relative ) );
			if ( '.' === $dir || './' === $dir ) {
				$dir = '';
			}
			return $client->public_url( $dir . $meta['sizes'][ $size_name ]['file'] );
		}

		return $client->public_url( $relative );
	}
}

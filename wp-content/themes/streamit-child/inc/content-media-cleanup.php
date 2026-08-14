<?php
/**
 * Delete WP media (and MinIO objects) when Streamit content is removed.
 *
 * MinIO cleanup already runs on `delete_attachment` via Streamit_Child_Minio_Offload.
 * Person profile images are never deleted here — they are shared across titles.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Image meta keys that store attachment IDs.
 *
 * @return string[]
 */
function streamit_child_content_image_meta_keys() {
	return array(
		'thumbnail_id',
		'_portrait_thumbmail',
		'_name_logo',
		'_name_trailer_img',
	);
}

/**
 * Collect attachment IDs owned by a Streamit content row.
 *
 * @param string $type movie|tvshow|episode.
 * @param int    $id   Content ID.
 * @return int[]
 */
function streamit_child_collect_content_attachment_ids( $type, $id ) {
	$id  = absint( $id );
	$ids = array();

	if ( $id <= 0 ) {
		return $ids;
	}

	$getter = null;
	if ( 'movie' === $type && function_exists( 'streamit_get_movie_meta' ) ) {
		$getter = 'streamit_get_movie_meta';
	} elseif ( 'tvshow' === $type && function_exists( 'streamit_get_tvshow_meta' ) ) {
		$getter = 'streamit_get_tvshow_meta';
	} elseif ( 'episode' === $type && function_exists( 'streamit_get_episode_meta' ) ) {
		$getter = 'streamit_get_episode_meta';
	}

	if ( ! $getter ) {
		return $ids;
	}

	foreach ( streamit_child_content_image_meta_keys() as $meta_key ) {
		$value = absint( call_user_func( $getter, $id, $meta_key, true ) );
		if ( $value > 0 ) {
			$ids[] = $value;
		}
	}

	if ( 'tvshow' === $type ) {
		$seasons = maybe_unserialize( call_user_func( $getter, $id, '_seasons', true ) );
		if ( is_array( $seasons ) ) {
			foreach ( $seasons as $season ) {
				if ( ! empty( $season['image_id'] ) ) {
					$ids[] = absint( $season['image_id'] );
				}
			}
		}
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * How many Streamit meta rows reference this attachment ID.
 *
 * @param int $attachment_id Attachment ID.
 * @return int
 */
function streamit_child_attachment_ref_count( $attachment_id ) {
	global $wpdb;

	$attachment_id = absint( $attachment_id );
	if ( $attachment_id <= 0 ) {
		return 0;
	}

	$value = (string) $attachment_id;
	$keys  = streamit_child_content_image_meta_keys();
	$in    = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
	$count = 0;

	$tables = array();
	if ( ! empty( $wpdb->streamit_moviemeta ) ) {
		$tables[] = $wpdb->streamit_moviemeta;
	}
	if ( ! empty( $wpdb->streamit_tvshowmeta ) ) {
		$tables[] = $wpdb->streamit_tvshowmeta;
	}
	if ( ! empty( $wpdb->streamit_episodemeta ) ) {
		$tables[] = $wpdb->streamit_episodemeta;
	}
	if ( ! empty( $wpdb->streamit_videometa ) ) {
		$tables[] = $wpdb->streamit_videometa;
	}
	if ( ! empty( $wpdb->streamit_personmeta ) ) {
		$tables[] = $wpdb->streamit_personmeta;
	}

	foreach ( $tables as $table ) {
		$sql    = "SELECT COUNT(*) FROM {$table} WHERE meta_key IN ({$in}) AND meta_value = %s";
		$args   = array_merge( $keys, array( $value ) );
		$count += (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
	}

	return $count;
}

/**
 * How many times this content row references an attachment.
 *
 * @param string $type           movie|tvshow|episode.
 * @param int    $id             Content ID.
 * @param int    $attachment_id  Attachment ID.
 * @return int
 */
function streamit_child_content_attachment_owned_count( $type, $id, $attachment_id ) {
	$attachment_id = absint( $attachment_id );
	$owned         = 0;

	$getter = null;
	if ( 'movie' === $type && function_exists( 'streamit_get_movie_meta' ) ) {
		$getter = 'streamit_get_movie_meta';
	} elseif ( 'tvshow' === $type && function_exists( 'streamit_get_tvshow_meta' ) ) {
		$getter = 'streamit_get_tvshow_meta';
	} elseif ( 'episode' === $type && function_exists( 'streamit_get_episode_meta' ) ) {
		$getter = 'streamit_get_episode_meta';
	}

	if ( ! $getter ) {
		return 0;
	}

	foreach ( streamit_child_content_image_meta_keys() as $meta_key ) {
		if ( absint( call_user_func( $getter, $id, $meta_key, true ) ) === $attachment_id ) {
			$owned++;
		}
	}

	if ( 'tvshow' === $type ) {
		$seasons = maybe_unserialize( call_user_func( $getter, $id, '_seasons', true ) );
		if ( is_array( $seasons ) ) {
			foreach ( $seasons as $season ) {
				if ( ! empty( $season['image_id'] ) && absint( $season['image_id'] ) === $attachment_id ) {
					$owned++;
				}
			}
		}
	}

	return $owned;
}

/**
 * Delete media attachments for a content row when they are not shared.
 *
 * @param string $type movie|tvshow|episode.
 * @param int    $id   Content ID.
 */
function streamit_child_purge_content_media( $type, $id ) {
	$type = sanitize_key( $type );
	$id   = absint( $id );

	if ( ! in_array( $type, array( 'movie', 'tvshow', 'episode' ), true ) || $id <= 0 ) {
		return;
	}

	if ( ! function_exists( 'wp_delete_attachment' ) ) {
		require_once ABSPATH . 'wp-admin/includes/post.php';
	}

	$attachment_ids = streamit_child_collect_content_attachment_ids( $type, $id );

	foreach ( $attachment_ids as $attachment_id ) {
		$owned = streamit_child_content_attachment_owned_count( $type, $id, $attachment_id );
		$refs  = streamit_child_attachment_ref_count( $attachment_id );

		// Keep shared assets (e.g. TMDB URL dedupe across titles).
		if ( $refs > $owned ) {
			continue;
		}

		wp_delete_attachment( $attachment_id, true );
	}
}

add_action( 'streamit_before_delete_movie', 'streamit_child_purge_content_media_on_movie_delete', 10, 1 );
add_action( 'streamit_before_delete_tvshow', 'streamit_child_purge_content_media_on_tvshow_delete', 10, 1 );
add_action( 'streamit_before_delete_episode', 'streamit_child_purge_content_media_on_episode_delete', 10, 1 );

/**
 * @param int $movie_id Movie ID.
 */
function streamit_child_purge_content_media_on_movie_delete( $movie_id ) {
	streamit_child_purge_content_media( 'movie', $movie_id );
}

/**
 * @param int $tvshow_id TV show ID.
 */
function streamit_child_purge_content_media_on_tvshow_delete( $tvshow_id ) {
	streamit_child_purge_content_media( 'tvshow', $tvshow_id );
}

/**
 * @param int $episode_id Episode ID.
 */
function streamit_child_purge_content_media_on_episode_delete( $episode_id ) {
	streamit_child_purge_content_media( 'episode', $episode_id );
}

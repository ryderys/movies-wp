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

		// Bypass the Media Library delete guard for attachments we determined are not shared.
		// The post/meta rows are still present at this point, so a pure ref-count based guard
		// would otherwise block this safe delete.
		if ( ! isset( $GLOBALS['streamit_child_allow_delete_attachments'] ) || ! is_array( $GLOBALS['streamit_child_allow_delete_attachments'] ) ) {
			$GLOBALS['streamit_child_allow_delete_attachments'] = array();
		}
		$GLOBALS['streamit_child_allow_delete_attachments'][ $attachment_id ] = true;
		wp_delete_attachment( $attachment_id, true );
		unset( $GLOBALS['streamit_child_allow_delete_attachments'][ $attachment_id ] );
	}
}

add_action( 'streamit_before_delete_movie', 'streamit_child_purge_content_media_on_movie_delete', 10, 1 );
add_action( 'streamit_before_delete_tvshow', 'streamit_child_purge_content_media_on_tvshow_delete', 10, 1 );
add_action( 'streamit_before_delete_episode', 'streamit_child_purge_content_media_on_episode_delete', 10, 1 );

/**
 * Fallback cleanup for installs where Streamit does not fire streamit_before_delete_* hooks.
 *
 * Streamit content is stored as WP posts (post_type: movie|tvshow|episode in templates),
 * so we hook into WordPress' permanent delete lifecycle to purge owned attachments.
 * This intentionally does NOT run on trash; it runs when the post is actually deleted.
 *
 * @param int $post_id Post ID.
 */
function streamit_child_purge_content_media_on_wp_delete_post( $post_id ) {
	$post_id = absint( $post_id );
	if ( $post_id <= 0 || ! function_exists( 'get_post_type' ) ) {
		return;
	}
	$type = sanitize_key( (string) get_post_type( $post_id ) );
	if ( ! in_array( $type, array( 'movie', 'tvshow', 'episode' ), true ) ) {
		return;
	}
	streamit_child_purge_content_media( $type, $post_id );
}

add_action( 'before_delete_post', 'streamit_child_purge_content_media_on_wp_delete_post', 10, 1 );

/**
 * Prevent accidental Media Library deletes of attachments still referenced by Streamit content.
 *
 * This blocks orphaned MinIO deletes and broken posters when an operator deletes an attachment
 * directly from the Media Library while movies/tvshows/episodes still reference its ID.
 *
 * Safe, intentional deletes performed by streamit_child_purge_content_media() are allowed via
 * $GLOBALS['streamit_child_allow_delete_attachments'].
 *
 * @param null|bool $delete      Short-circuit value. Default null.
 * @param WP_Post   $post        Attachment post object.
 * @param bool      $force_delete Whether to bypass trash.
 * @return null|bool False blocks deletion.
 */
function streamit_child_block_delete_referenced_attachment( $delete, $post, $force_delete ) {
	unset( $force_delete );
	if ( ! is_object( $post ) || empty( $post->ID ) ) {
		return $delete;
	}
	if ( 'attachment' !== (string) ( $post->post_type ?? '' ) ) {
		return $delete;
	}

	$attachment_id = absint( $post->ID );
	if ( $attachment_id <= 0 ) {
		return $delete;
	}

	// Allow explicit safe deletes from our content purge path.
	if (
		isset( $GLOBALS['streamit_child_allow_delete_attachments'] )
		&& is_array( $GLOBALS['streamit_child_allow_delete_attachments'] )
		&& ! empty( $GLOBALS['streamit_child_allow_delete_attachments'][ $attachment_id ] )
	) {
		return $delete;
	}

	// Allow override via constant / filter.
	if ( defined( 'STREAMIT_CHILD_ALLOW_DELETE_REFERENCED_ATTACHMENTS' ) && STREAMIT_CHILD_ALLOW_DELETE_REFERENCED_ATTACHMENTS ) {
		return $delete;
	}

	$refs = streamit_child_attachment_ref_count( $attachment_id );
	if ( $refs <= 0 ) {
		return $delete;
	}

	$allow = apply_filters( 'streamit_child_allow_delete_referenced_attachment', false, $attachment_id, $refs, $post );
	if ( $allow ) {
		return $delete;
	}

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
		error_log( sprintf( 'Blocked Media Library delete of attachment %d (still referenced by Streamit meta rows: %d).', $attachment_id, $refs ) );
	}

	// Show an admin notice to the operator.
	if ( function_exists( 'set_transient' ) && function_exists( 'get_current_user_id' ) ) {
		$user_id = (int) get_current_user_id();
		if ( $user_id > 0 ) {
			set_transient(
				'streamit_child_attachment_delete_blocked_' . $user_id,
				array(
					'attachment_id' => $attachment_id,
					'refs'          => $refs,
				),
				60
			);
		}
	}

	return false;
}

add_filter( 'pre_delete_attachment', 'streamit_child_block_delete_referenced_attachment', 10, 3 );

add_action(
	'admin_notices',
	static function () {
		if ( ! function_exists( 'get_current_user_id' ) || ! function_exists( 'get_transient' ) || ! function_exists( 'delete_transient' ) ) {
			return;
		}
		$user_id = (int) get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		$key  = 'streamit_child_attachment_delete_blocked_' . $user_id;
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			return;
		}
		delete_transient( $key );

		$attachment_id = isset( $data['attachment_id'] ) ? absint( $data['attachment_id'] ) : 0;
		$refs          = isset( $data['refs'] ) ? absint( $data['refs'] ) : 0;
		if ( $attachment_id <= 0 ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: attachment id 2: reference count */
			__( 'Delete blocked: media attachment %1$d is still referenced by Streamit content metadata (%2$d reference(s)). Remove it from the movie/series first, or enable the override.', 'streamit' ),
			$attachment_id,
			$refs
		);
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}
);

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

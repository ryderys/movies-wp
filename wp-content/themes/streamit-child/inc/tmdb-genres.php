<?php
/**
 * Auto-assign TMDB genres on import while preserving manual admin edits.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pending genre assignments processed on shutdown (after _tmdb_id meta exists).
 *
 * @var array<int, array{type: string, taxonomy: string}>
 */
$GLOBALS['streamit_child_pending_tmdb_genres'] = array();

/**
 * Map TMDB genres to Streamit taxonomy terms and link them to a post.
 *
 * @param int    $post_id   Movie or TV show ID.
 * @param array  $genres    TMDB genres: [ ['id'=>28,'name'=>'Action'], ... ].
 * @param string $taxonomy  movie_genre|tvshow_genre.
 * @return bool True if relationships were saved.
 */
function streamit_child_save_tmdb_genres( $post_id, $genres, $taxonomy ) {
	if ( empty( $post_id ) || empty( $genres ) || ! is_array( $genres ) ) {
		return false;
	}

	if ( ! function_exists( 'streamit_get_term' ) || ! function_exists( 'streamit_add_term' ) || ! function_exists( 'streamit_insert_term_relationships' ) ) {
		return false;
	}

	if ( function_exists( 'streamit_get_term_relationships' ) ) {
		$existing = streamit_get_term_relationships( $post_id, $taxonomy );
		if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
			return false;
		}
	}

	$term_ids = array();

	foreach ( $genres as $genre ) {
		$name = isset( $genre['name'] ) ? trim( (string) $genre['name'] ) : '';
		if ( '' === $name ) {
			continue;
		}

		$slug     = sanitize_title( $name );
		$get_term = streamit_get_term( $slug, $taxonomy );

		if ( ! is_wp_error( $get_term ) ) {
			$term_ids[] = (int) $get_term->get_term_id();
			continue;
		}

		$add_term = streamit_add_term(
			array(
				'term_name' => $name,
				'term_slug' => $slug,
				'taxonomy'  => $taxonomy,
			)
		);

		if ( ! is_wp_error( $add_term ) && ! empty( $add_term ) ) {
			$term_ids[] = (int) $add_term;
		}
	}

	$term_ids = array_values( array_unique( array_filter( $term_ids ) ) );
	if ( empty( $term_ids ) ) {
		return false;
	}

	return (bool) streamit_insert_term_relationships( $post_id, $term_ids, $taxonomy );
}

/**
 * Fetch genres from TMDB detail endpoint.
 *
 * @param int    $tmdb_id TMDB content ID.
 * @param string $type    movie|tvshow.
 * @return array<int, array{id:int, name:string}>
 */
function streamit_child_fetch_tmdb_genres( $tmdb_id, $type ) {
	$tmdb_id = absint( $tmdb_id );
	if ( ! $tmdb_id ) {
		return array();
	}

	$import_settings = @unserialize( get_option( 'streamit_content_import_settings' ) );
	$api_key         = isset( $import_settings['tmdb']['api_key'] ) ? $import_settings['tmdb']['api_key'] : '';
	if ( empty( $api_key ) ) {
		return array();
	}

	$host     = defined( 'STREAMIT_TMDB_PROXY_HOST' ) ? STREAMIT_TMDB_PROXY_HOST : 'tmdb.youssefi-ashkan-ys.workers.dev';
	$language = 'en-US';

	if ( 'tvshow' === $type ) {
		$url = sprintf( 'https://%s/3/tv/%d?api_key=%s&language=%s', $host, $tmdb_id, rawurlencode( $api_key ), rawurlencode( $language ) );
	} else {
		$url = sprintf( 'https://%s/3/movie/%d?api_key=%s&language=%s', $host, $tmdb_id, rawurlencode( $api_key ), rawurlencode( $language ) );
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 40,
			'headers' => array( 'Content-Type' => 'application/json' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array();
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return array();
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) || empty( $body['genres'] ) || ! is_array( $body['genres'] ) ) {
		return array();
	}

	return $body['genres'];
}

/**
 * Queue a post for genre assignment at shutdown.
 *
 * @param int    $post_id   Movie or TV show ID.
 * @param string $type      movie|tvshow.
 * @param string $taxonomy  movie_genre|tvshow_genre.
 */
function streamit_child_queue_tmdb_genres( $post_id, $type, $taxonomy ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		return;
	}

	if ( ! isset( $GLOBALS['streamit_child_pending_tmdb_genres'] ) ) {
		$GLOBALS['streamit_child_pending_tmdb_genres'] = array();
	}

	$GLOBALS['streamit_child_pending_tmdb_genres'][ $post_id ] = array(
		'type'     => $type,
		'taxonomy' => $taxonomy,
	);
}

/**
 * Process queued TMDB genre imports.
 */
function streamit_child_process_pending_tmdb_genres() {
	if ( empty( $GLOBALS['streamit_child_pending_tmdb_genres'] ) || ! is_array( $GLOBALS['streamit_child_pending_tmdb_genres'] ) ) {
		return;
	}

	$get_meta = static function ( $post_id, $type ) {
		if ( 'tvshow' === $type && function_exists( 'streamit_get_tvshow_meta' ) ) {
			return streamit_get_tvshow_meta( $post_id, '_tmdb_id', true );
		}
		if ( function_exists( 'streamit_get_movie_meta' ) ) {
			return streamit_get_movie_meta( $post_id, '_tmdb_id', true );
		}
		return '';
	};

	foreach ( $GLOBALS['streamit_child_pending_tmdb_genres'] as $post_id => $config ) {
		$type     = isset( $config['type'] ) ? (string) $config['type'] : 'movie';
		$taxonomy = isset( $config['taxonomy'] ) ? (string) $config['taxonomy'] : 'movie_genre';

		$tmdb_id = $get_meta( $post_id, $type );
		if ( empty( $tmdb_id ) ) {
			continue;
		}

		$genres = streamit_child_fetch_tmdb_genres( $tmdb_id, $type );
		if ( empty( $genres ) ) {
			continue;
		}

		streamit_child_save_tmdb_genres( $post_id, $genres, $taxonomy );

		if ( function_exists( 'streamit_child_flush_streamit_cache' ) ) {
			streamit_child_flush_streamit_cache();
		}
	}

	$GLOBALS['streamit_child_pending_tmdb_genres'] = array();
}

/**
 * Defer genre import until TMDB meta (including _tmdb_id) is saved.
 *
 * @param int $post_id Inserted post ID.
 */
function streamit_child_after_movie_insert_tmdb_genres( $post_id ) {
	streamit_child_queue_tmdb_genres( $post_id, 'movie', 'movie_genre' );
}
add_action( 'streamit_after_movie_insert', 'streamit_child_after_movie_insert_tmdb_genres', 20, 1 );

/**
 * Defer genre import until TMDB meta (including _tmdb_id) is saved.
 *
 * @param int $post_id Inserted post ID.
 */
function streamit_child_after_tvshow_insert_tmdb_genres( $post_id ) {
	streamit_child_queue_tmdb_genres( $post_id, 'tvshow', 'tvshow_genre' );
}
add_action( 'streamit_after_tvshow_insert', 'streamit_child_after_tvshow_insert_tmdb_genres', 20, 1 );

add_action( 'shutdown', 'streamit_child_process_pending_tmdb_genres', 20 );

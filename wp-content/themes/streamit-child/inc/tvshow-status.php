<?php
/**
 * TMDB TV show status ("Ended", "Returning Series", "In Production", ...).
 *
 * Streamit stores content in its own tables (not WP posts), so the status is kept as:
 * - a Streamit term in the `tv_status` taxonomy (used by the Main Card Slider filter);
 * - `_tv_show_status` TV show meta, usable via streamit_get_tvshows( [ 'meta_query' => ... ] ).
 *
 * Backfill existing shows: wp streamit-child sync-tv-status
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

const STREAMIT_CHILD_TV_STATUS_TAXONOMY = 'tv_status';
const STREAMIT_CHILD_TV_STATUS_META     = '_tv_show_status';

/**
 * TV show IDs waiting for status sync on shutdown (after _tmdb_id meta exists).
 *
 * @var array<int, true>
 */
$GLOBALS['streamit_child_pending_tv_status'] = array();

/**
 * Link a TV show to a single `tv_status` term and mirror the value to meta.
 *
 * @param int    $tvshow_id TV show ID.
 * @param string $status    TMDB status label, e.g. "Returning Series".
 * @return bool True if saved.
 */
function streamit_child_save_tv_status( $tvshow_id, $status ) {
	// Streamit_Term::get_term() caches "not found" per request, so remember created terms here
	// or the next show with the same status (bulk import / backfill) would insert a duplicate.
	static $term_ids = array();

	$tvshow_id = absint( $tvshow_id );
	$status    = sanitize_text_field( (string) $status );
	if ( ! $tvshow_id || '' === $status ) {
		return false;
	}

	$slug = sanitize_title( $status );

	if ( ! isset( $term_ids[ $slug ] ) ) {
		$term = streamit_get_term( $slug, STREAMIT_CHILD_TV_STATUS_TAXONOMY );

		if ( ! is_wp_error( $term ) && ! empty( $term ) ) {
			$term_id = (int) $term->get_term_id();
		} else {
			$term_id = streamit_add_term(
				array(
					'term_name' => $status,
					'term_slug' => $slug,
					'taxonomy'  => STREAMIT_CHILD_TV_STATUS_TAXONOMY,
				)
			);
			if ( is_wp_error( $term_id ) || empty( $term_id ) ) {
				return false;
			}
		}

		$term_ids[ $slug ] = (int) $term_id;
	}

	$linked = streamit_update_term_relationships( $tvshow_id, array( $term_ids[ $slug ] ), STREAMIT_CHILD_TV_STATUS_TAXONOMY );
	if ( is_wp_error( $linked ) ) {
		return false;
	}

	streamit_update_tvshow_meta( $tvshow_id, STREAMIT_CHILD_TV_STATUS_META, $status );

	return true;
}

/**
 * Fetch the current TMDB status for a TV show and save it.
 *
 * @param int $tvshow_id TV show ID.
 * @return bool True if saved.
 */
function streamit_child_sync_tv_status( $tvshow_id ) {
	if ( ! function_exists( 'streamit_update_term_relationships' ) || ! function_exists( 'streamit_child_fetch_tmdb_details' ) ) {
		return false;
	}

	$tmdb_id = streamit_get_tvshow_meta( $tvshow_id, '_tmdb_id', true );
	if ( empty( $tmdb_id ) ) {
		return false;
	}

	$details = streamit_child_fetch_tmdb_details( $tmdb_id, 'tvshow' );
	if ( empty( $details['status'] ) ) {
		return false;
	}

	return streamit_child_save_tv_status( $tvshow_id, $details['status'] );
}

/**
 * Queue a TV show for status sync (import + admin edit).
 *
 * @param int $tvshow_id TV show ID.
 */
function streamit_child_queue_tv_status( $tvshow_id ) {
	$tvshow_id = absint( $tvshow_id );
	if ( $tvshow_id ) {
		$GLOBALS['streamit_child_pending_tv_status'][ $tvshow_id ] = true;
	}
}
add_action( 'streamit_after_tvshow_insert', 'streamit_child_queue_tv_status', 20, 1 );
add_action( 'streamit_after_update_tvshow', 'streamit_child_queue_tv_status', 20, 1 );

/**
 * Process queued TV show status syncs.
 */
function streamit_child_process_pending_tv_status() {
	if ( empty( $GLOBALS['streamit_child_pending_tv_status'] ) ) {
		return;
	}

	$synced = false;
	foreach ( array_keys( $GLOBALS['streamit_child_pending_tv_status'] ) as $tvshow_id ) {
		$synced = streamit_child_sync_tv_status( $tvshow_id ) || $synced;
	}
	$GLOBALS['streamit_child_pending_tv_status'] = array();

	if ( $synced && function_exists( 'streamit_child_flush_streamit_cache' ) ) {
		streamit_child_flush_streamit_cache();
	}
}
add_action( 'shutdown', 'streamit_child_process_pending_tv_status', 20 );

/**
 * Add the "TV Show Status" filter to Main Card Slider and Top Ten Slider.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
 */
function streamit_child_register_tv_status_slider( $widgets_manager ) {
	if ( class_exists( '\Elementor\ST_Slider' ) ) {
		require_once get_stylesheet_directory() . '/inc/elementor/class-st-slider-tv-status.php';
		$widgets_manager->unregister( 'main-card-slider' );
		$widgets_manager->register( new \Elementor\ST_Slider_Tv_Status() );
	}

	if ( class_exists( '\Elementor\ST_Top_Ten' ) ) {
		require_once get_stylesheet_directory() . '/inc/elementor/class-st-top-ten-tv-status.php';
		$widgets_manager->unregister( 'top-ten-slider' );
		$widgets_manager->register( new \Elementor\ST_Top_Ten_Tv_Status() );
	}

	if ( class_exists( '\Elementor\ST_VTBenner' ) ) {
		require_once get_stylesheet_directory() . '/inc/elementor/class-st-vtbanner-popular.php';
		$widgets_manager->unregister( 'vertical-thumbnail-banner' );
		$widgets_manager->register( new \Elementor\ST_VTBanner_Popular() );
	}
}
add_action( 'elementor/widgets/register', 'streamit_child_register_tv_status_slider', 99 );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Sync TMDB status for every TV show that has a _tmdb_id.
	 */
	WP_CLI::add_command(
		'streamit-child sync-tv-status',
		static function () {
			global $wpdb;

			$ids    = $wpdb->get_col( "SELECT ID FROM {$wpdb->streamit_tvshow}" );
			$synced = 0;

			foreach ( $ids as $tvshow_id ) {
				if ( streamit_child_sync_tv_status( $tvshow_id ) ) {
					++$synced;
				} else {
					WP_CLI::warning( "TV show {$tvshow_id}: no TMDB status." );
				}
			}

			streamit_child_flush_streamit_cache();
			WP_CLI::success( sprintf( 'Synced %d of %d TV shows.', $synced, count( $ids ) ) );
		}
	);
}

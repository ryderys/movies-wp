<?php
/**
 * Re-download missing TMDB images for an imported TV show.
 * Usage: php repair-tvshow-images.php <tvshow_id>
 */
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once STREAMIT_PLUGIN_PATH . 'admin/content-import/streamit-tmdb_tvshow-function.php';

$tvshow_id = isset( $argv[1] ) ? absint( $argv[1] ) : 0;
if ( ! $tvshow_id ) {
	echo "Usage: php repair-tvshow-images.php <tvshow_id>\n";
	exit( 1 );
}

$settings = @unserialize( get_option( 'streamit_content_import_settings' ) );
$api_key  = $settings['tmdb']['api_key'] ?? '';
$tmdb_id  = streamit_get_tvshow_meta( $tvshow_id, '_tmdb_id', true );
$language = 'en-US';

if ( empty( $tmdb_id ) || empty( $api_key ) ) {
	echo "Missing tmdb_id or api_key for tvshow {$tvshow_id}\n";
	exit( 1 );
}

echo "Repairing tvshow {$tvshow_id} (TMDB {$tmdb_id})\n";

// Poster.
if ( ! streamit_get_tvshow_meta( $tvshow_id, 'thumbnail_id', true ) ) {
	$show_url  = "https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key={$api_key}&language={$language}";
	$show_resp = fetch_tmdb_tvshow_data( $show_url );
	$show_data = is_wp_error( $show_resp ) ? array() : ( $show_resp['data'] ?? $show_resp );
	if ( ! empty( $show_data['poster_path'] ) ) {
		$att = streamit_download_and_attach_tvshow_image(
			streamit_get_tmdb_image_url( $show_data['poster_path'], 'original' )
		);
		if ( ! is_wp_error( $att ) ) {
			streamit_update_tvshow_meta( $tvshow_id, 'thumbnail_id', $att );
			streamit_update_tvshow_meta( $tvshow_id, '_portrait_thumbmail', $att );
			echo "  poster: OK ({$att})\n";
		} else {
			echo '  poster: FAIL - ' . $att->get_error_message() . "\n";
		}
	}
}

// Episodes.
global $wpdb;
$seasons   = maybe_unserialize( streamit_get_tvshow_meta( $tvshow_id, '_seasons', true ) );
$show_url  = "https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key={$api_key}&language={$language}";
$show_resp = fetch_tmdb_tvshow_data( $show_url );
$show_data = is_wp_error( $show_resp ) ? array() : ( $show_resp['data'] ?? $show_resp );
$ep_fixed  = 0;

if ( is_array( $seasons ) && ! empty( $show_data['seasons'] ) ) {
	$tmdb_seasons = array();
	foreach ( $show_data['seasons'] as $season ) {
		if ( ! empty( $season['season_number'] ) ) {
			$tmdb_seasons[ (int) $season['season_number'] ] = $season;
		}
	}

	foreach ( $seasons as $season_meta ) {
		if ( empty( $season_meta['episodes'] ) || ! is_array( $season_meta['episodes'] ) ) {
			continue;
		}

		$position      = isset( $season_meta['position'] ) ? (int) $season_meta['position'] : 0;
		$season_number = null;
		if ( isset( $show_data['seasons'][ $position ]['season_number'] ) ) {
			$season_number = (int) $show_data['seasons'][ $position ]['season_number'];
		}
		if ( ! $season_number ) {
			continue;
		}

		foreach ( $season_meta['episodes'] as $episode_id ) {
			$episode_id = absint( $episode_id );
			if ( ! $episode_id || streamit_get_episode_meta( $episode_id, 'thumbnail_id', true ) ) {
				continue;
			}

			$ep_num = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT menu_order FROM {$wpdb->streamit_episode} WHERE ID = %d",
					$episode_id
				)
			);
			if ( ! $ep_num ) {
				continue;
			}

			$ep_url  = "https://api.themoviedb.org/3/tv/{$tmdb_id}/season/{$season_number}/episode/{$ep_num}?api_key={$api_key}&language={$language}";
			$ep_resp = fetch_tmdb_tvshow_data( $ep_url );
			$ep_data = is_wp_error( $ep_resp ) ? array() : ( $ep_resp['data'] ?? $ep_resp );
			if ( empty( $ep_data['still_path'] ) ) {
				continue;
			}

			$att = streamit_download_and_attach_tvshow_image(
				streamit_get_tmdb_image_url( $ep_data['still_path'], 'w500' )
			);
			if ( ! is_wp_error( $att ) ) {
				streamit_update_episode_meta( $episode_id, 'thumbnail_id', $att );
				$ep_fixed++;
			}
		}
	}
}
echo "  episodes fixed: {$ep_fixed}\n";

// Cast / crew thumbnails for persons linked to this import batch.
$cast_url  = "https://api.themoviedb.org/3/tv/{$tmdb_id}/credits?api_key={$api_key}&language={$language}";
$cast_resp = fetch_tmdb_tvshow_data( $cast_url );
$cast_body = is_wp_error( $cast_resp ) ? array() : ( $cast_resp['data'] ?? $cast_resp );
$people    = array_merge( $cast_body['cast'] ?? array(), $cast_body['crew'] ?? array() );
$per_fixed = 0;

foreach ( $people as $member ) {
	if ( empty( $member['profile_path'] ) || empty( $member['name'] ) ) {
		continue;
	}
	$list = streamit_get_persons(
		array(
			'paged'      => 1,
			'per_page'   => 1,
			'post_title' => $member['name'],
		)
	);
	if ( empty( $list->results ) ) {
		continue;
	}
	$person = $list->results[0];
	if ( streamit_get_person_meta( $person->get_id(), 'thumbnail_id', true ) ) {
		continue;
	}
	$att = streamit_download_and_attach_tvshow_image(
		streamit_get_tmdb_image_url( $member['profile_path'], 'w500' )
	);
	if ( ! is_wp_error( $att ) ) {
		streamit_update_person_meta( $person->get_id(), 'thumbnail_id', $att );
		$per_fixed++;
	}
}
echo "  person thumbnails fixed: {$per_fixed}\n";
echo "Done.\n";

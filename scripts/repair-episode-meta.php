<?php
/**
 * Backfill episode numbering, tvshow linkage, and TMDB metadata for imported TV shows.
 *
 * Usage:
 *   php repair-episode-meta.php              # all TV shows with seasons
 *   php repair-episode-meta.php <tvshow_id>  # one show
 */
require '/var/www/html/wp-load.php';

$target_tvshow_id = isset( $argv[1] ) ? absint( $argv[1] ) : 0;

if ( $target_tvshow_id ) {
	$tvshow_ids = array( $target_tvshow_id );
} else {
	global $wpdb;
	$tvshow_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->streamit_tvshow} ORDER BY ID ASC" );
}

$settings = @unserialize( get_option( 'streamit_content_import_settings' ) );
$api_key  = $settings['tmdb']['api_key'] ?? '';
$language = 'en-US';

/**
 * @param int $minutes Episode runtime in minutes.
 * @return string Hours:minutes value used by Streamit admin fields.
 */
function streamit_repair_format_runtime( $minutes ) {
	$minutes = absint( $minutes );
	if ( ! $minutes ) {
		return '';
	}

	return sprintf( '%d:%02d', (int) floor( $minutes / 60 ), $minutes % 60 );
}

/**
 * @param string $url TMDB API URL.
 * @return array|null
 */
function streamit_repair_fetch_tmdb_json( $url ) {
	if ( function_exists( 'fetch_tmdb_tvshow_data' ) ) {
		$response = fetch_tmdb_tvshow_data( $url );
		if ( ! empty( $response['status'] ) && ! empty( $response['data'] ) ) {
			return $response['data'];
		}
	}

	$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
	if ( is_wp_error( $response ) ) {
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	return is_array( $data ) ? $data : null;
}

$updated       = 0;
$meta_updated  = 0;
$shows         = 0;
$season_cache  = array();

foreach ( $tvshow_ids as $tvshow_id ) {
	$tvshow_id = absint( $tvshow_id );
	$tvshow    = function_exists( 'streamit_get_tvshow' ) ? streamit_get_tvshow( $tvshow_id ) : null;

	if ( ! $tvshow ) {
		continue;
	}

	$seasons = $tvshow->get_meta( '_seasons' );
	$tmdb_id = streamit_get_tvshow_meta( $tvshow_id, '_tmdb_id', true );
	$show_fix = 0;
	$show_meta_fix = 0;

	if ( empty( $seasons ) || ! is_array( $seasons ) ) {
		continue;
	}

	$tmdb_seasons = array();
	if ( $tmdb_id && $api_key ) {
		$show_url  = "https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key={$api_key}&language={$language}";
		$show_data = streamit_repair_fetch_tmdb_json( $show_url );
		foreach ( (array) ( $show_data['seasons'] ?? array() ) as $season_row ) {
			if ( ! empty( $season_row['season_number'] ) ) {
				$tmdb_seasons[ (int) $season_row['season_number'] ] = $season_row;
			}
		}
	}

	foreach ( $seasons as $position => $season_meta ) {
		if ( empty( $season_meta['episodes'] ) || ! is_array( $season_meta['episodes'] ) ) {
			continue;
		}

		$season_number = 0;
		if ( ! empty( $season_meta['season_number'] ) ) {
			$season_number = absint( $season_meta['season_number'] );
		} elseif ( isset( $tmdb_seasons[ $position + 1 ] ) ) {
			$season_number = (int) $tmdb_seasons[ $position + 1 ]['season_number'];
		} else {
			$season_number = $position + 1;
		}

		$tmdb_episodes = array();
		if ( $tmdb_id && $api_key && $season_number >= 0 ) {
			if ( ! isset( $season_cache[ $tmdb_id ][ $season_number ] ) ) {
				$season_url = "https://api.themoviedb.org/3/tv/{$tmdb_id}/season/{$season_number}?api_key={$api_key}&language={$language}";
				$season_data = streamit_repair_fetch_tmdb_json( $season_url );
				$indexed     = array();

				foreach ( (array) ( $season_data['episodes'] ?? array() ) as $episode_row ) {
					if ( ! empty( $episode_row['episode_number'] ) ) {
						$indexed[ (int) $episode_row['episode_number'] ] = $episode_row;
					}
				}

				$season_cache[ $tmdb_id ][ $season_number ] = $indexed;
			}

			$tmdb_episodes = $season_cache[ $tmdb_id ][ $season_number ];
		}

		foreach ( $season_meta['episodes'] as $episode_id ) {
			$episode_id = absint( $episode_id );
			if ( ! $episode_id ) {
				continue;
			}

			$episode = function_exists( 'streamit_get_episode' ) ? streamit_get_episode( $episode_id ) : null;
			if ( ! $episode ) {
				continue;
			}

			$episode_num = absint( $episode->get_menu_order() );
			if ( ! $episode_num ) {
				$existing_num = $episode->get_meta( '_episode_number' );
				if ( $existing_num && preg_match( '/(\d+)/', $existing_num, $matches ) ) {
					$episode_num = absint( $matches[1] );
				}
			}

			if ( ! $episode_num ) {
				continue;
			}

			$episode_name  = $episode->get_post_title();
			$plain_title   = preg_replace( '/^S\d+E\d+\s*-\s*/i', '', $episode_name );
			$episode_title = sprintf( 'S%02dE%02d - %s', $season_number, $episode_num, $plain_title );
			$episode_slug  = sanitize_title( sprintf( 's%02de%02d-%s-%d', $season_number, $episode_num, $plain_title, $tvshow_id ) );

			streamit_update_episode(
				$episode_id,
				array(
					'post_title' => $episode_title,
					'post_name'  => $episode_slug,
					'menu_order' => $episode_num,
				)
			);

			streamit_update_episode_meta( $episode_id, 'tvshow_id', (string) $tvshow_id );
			streamit_update_episode_meta( $episode_id, '_episode_number', sprintf( 'E%02d', $episode_num ) );
			streamit_update_episode_meta( $episode_id, '_season_number', (string) $season_number );

			if ( isset( $tmdb_episodes[ $episode_num ] ) ) {
				$tmdb_episode = $tmdb_episodes[ $episode_num ];

				if ( ! empty( $tmdb_episode['id'] ) ) {
					streamit_update_episode_meta( $episode_id, '_tmdb_id', (string) $tmdb_episode['id'] );
					$show_meta_fix++;
				}

				if ( ! empty( $tmdb_episode['air_date'] ) ) {
					streamit_update_episode_meta( $episode_id, '_episode_release_date', sanitize_text_field( $tmdb_episode['air_date'] ) );
					$show_meta_fix++;
				}

				if ( ! empty( $tmdb_episode['runtime'] ) ) {
					$runtime = streamit_repair_format_runtime( $tmdb_episode['runtime'] );
					if ( $runtime ) {
						streamit_update_episode_meta( $episode_id, '_episode_run_time', $runtime );
						$show_meta_fix++;
					}
				}
			}

			$show_fix++;
			$updated++;
		}
	}

	if ( $show_fix ) {
		$shows++;
		echo "Tvshow {$tvshow_id}: updated {$show_fix} episode(s)";
		if ( $show_meta_fix ) {
			echo ", backfilled {$show_meta_fix} TMDB meta field(s)";
		}
		echo "\n";
		$meta_updated += $show_meta_fix;
	}
}

if ( function_exists( 'streamit_child_flush_streamit_cache' ) ) {
	streamit_child_flush_streamit_cache();
} elseif ( class_exists( 'Streamit_Advanced_Cache' ) ) {
	Streamit_Advanced_Cache::flush_all_streamit_cache();
}

echo "Done. {$updated} episode(s) across {$shows} TV show(s), {$meta_updated} TMDB meta value(s) written.\n";

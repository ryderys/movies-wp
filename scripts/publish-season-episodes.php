<?php
/**
 * Publish season-linked episodes and remove duplicates.
 *
 * - Resolves duplicate episodes per TV show + season + episode number
 * - Keeps the canonical episode (listed in _seasons, then published, then lowest ID)
 * - Trashes extra copies so the frontend does not show duplicates
 * - Deduplicates episode ID lists inside _seasons
 * - Publishes remaining draft episodes referenced by _seasons
 *
 * Safe to re-run: idempotent publish + duplicate cleanup.
 *
 * Usage:
 *   php publish-season-episodes.php
 *   php publish-season-episodes.php <tvshow_id>
 */
require '/var/www/html/wp-load.php';

if ( function_exists( 'streamit_child_flush_streamit_cache' ) ) {
	streamit_child_flush_streamit_cache();
} elseif ( class_exists( 'Streamit_Advanced_Cache' ) ) {
	Streamit_Advanced_Cache::flush_all_streamit_cache();
}

$target_tvshow_id = isset( $argv[1] ) ? absint( $argv[1] ) : 0;

if ( $target_tvshow_id ) {
	$tvshow_ids = array( $target_tvshow_id );
} else {
	global $wpdb;
	$tvshow_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->streamit_tvshow} ORDER BY ID ASC" );
}

/**
 * @param int $episode_id Episode ID.
 * @return string|null Grouping key.
 */
function streamit_repair_episode_slot_key( $episode_id ) {
	$tvshow_id = streamit_get_episode_meta( $episode_id, 'tvshow_id', true );
	$season    = streamit_get_episode_meta( $episode_id, '_season_number', true );
	$episode   = streamit_get_episode_meta( $episode_id, '_episode_number', true );

	if ( '' === (string) $tvshow_id ) {
		return null;
	}

	return implode(
		'|',
		array(
			(string) $tvshow_id,
			(string) $season,
			(string) $episode,
		)
	);
}

/**
 * @param int[] $episode_ids Candidate episode IDs.
 * @param int[] $season_episode_ids Episode IDs currently referenced in _seasons.
 * @return int Canonical episode ID.
 */
function streamit_repair_pick_canonical_episode_id( array $episode_ids, array $season_episode_ids ) {
	$season_lookup = array_fill_keys( array_map( 'absint', $season_episode_ids ), true );
	$best_id       = 0;
	$best_score    = -1;

	foreach ( $episode_ids as $episode_id ) {
		$episode_id = absint( $episode_id );
		$episode    = streamit_get_episode( $episode_id );
		if ( ! $episode ) {
			continue;
		}

		$status = (string) $episode->get_post_status();
		if ( 'trash' === $status ) {
			continue;
		}

		$score = 0;
		if ( isset( $season_lookup[ $episode_id ] ) ) {
			$score += 100;
		}
		if ( 'publish' === $status ) {
			$score += 10;
		}
		$score -= $episode_id / 1000000;

		if ( $score > $best_score ) {
			$best_score = $score;
			$best_id    = $episode_id;
		}
	}

	return $best_id;
}

/**
 * @param int   $tvshow_id TV show ID.
 * @param int[] $season_episode_ids IDs referenced in _seasons.
 * @return array<int,int> Map duplicate episode ID => canonical episode ID.
 */
function streamit_repair_build_episode_alias_map( $tvshow_id, array $season_episode_ids ) {
	global $wpdb;

	$tvshow_id = absint( $tvshow_id );
	if ( ! $tvshow_id ) {
		return array();
	}

	$episode_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT streamit_episode_id
			FROM {$wpdb->streamit_episodemeta}
			WHERE meta_key = %s AND meta_value = %s",
			'tvshow_id',
			(string) $tvshow_id
		)
	);

	$groups = array();
	foreach ( $episode_ids as $episode_id ) {
		$episode_id = absint( $episode_id );
		$key        = streamit_repair_episode_slot_key( $episode_id );
		if ( null === $key ) {
			continue;
		}
		$groups[ $key ][] = $episode_id;
	}

	$alias_map = array();
	foreach ( $groups as $group_ids ) {
		$group_ids = array_values( array_unique( array_map( 'absint', $group_ids ) ) );
		if ( count( $group_ids ) < 2 ) {
			continue;
		}

		$canonical_id = streamit_repair_pick_canonical_episode_id( $group_ids, $season_episode_ids );
		if ( ! $canonical_id ) {
			continue;
		}

		foreach ( $group_ids as $episode_id ) {
			if ( $episode_id !== $canonical_id ) {
				$alias_map[ $episode_id ] = $canonical_id;
			}
		}
	}

	return $alias_map;
}

/**
 * @param array $seasons _seasons meta.
 * @param array<int,int> $alias_map Duplicate => canonical map.
 * @return array Updated seasons.
 */
function streamit_repair_apply_episode_alias_map_to_seasons( array $seasons, array $alias_map ) {
	foreach ( $seasons as $season_index => $season ) {
		$episode_ids = array();
		foreach ( (array) ( $season['episodes'] ?? array() ) as $episode_id ) {
			$episode_id = absint( $episode_id );
			if ( ! $episode_id ) {
				continue;
			}
			if ( isset( $alias_map[ $episode_id ] ) ) {
				$episode_id = absint( $alias_map[ $episode_id ] );
			}
			$episode_ids[] = (string) $episode_id;
		}

		$episode_ids = array_values( array_unique( $episode_ids ) );
		$seasons[ $season_index ]['episodes'] = $episode_ids;
	}

	return $seasons;
}

/**
 * Read episode status from DB (bypasses Streamit object/cache layers).
 *
 * @param int $episode_id Episode ID.
 * @return string|null post_status or null if missing.
 */
function streamit_repair_get_episode_post_status( $episode_id ) {
	global $wpdb;

	$episode_id = absint( $episode_id );
	if ( ! $episode_id ) {
		return null;
	}

	$status = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_status FROM {$wpdb->streamit_episode} WHERE ID = %d",
			$episode_id
		)
	);

	return is_string( $status ) ? $status : null;
}

$shows           = 0;
$published       = 0;
$skipped         = 0;
$missing         = 0;
$trashed_dupes   = 0;
$seasons_updated = 0;

foreach ( $tvshow_ids as $tvshow_id ) {
	$tvshow_id = absint( $tvshow_id );
	$tvshow    = function_exists( 'streamit_get_tvshow' ) ? streamit_get_tvshow( $tvshow_id ) : null;

	if ( ! $tvshow ) {
		continue;
	}

	$seasons = $tvshow->get_meta( '_seasons' );
	if ( is_string( $seasons ) ) {
		$seasons = maybe_unserialize( $seasons );
	}

	if ( empty( $seasons ) || ! is_array( $seasons ) ) {
		continue;
	}

	$shows++;
	$season_episode_ids = array();
	foreach ( $seasons as $season ) {
		foreach ( (array) ( $season['episodes'] ?? array() ) as $episode_id ) {
			$episode_id = absint( $episode_id );
			if ( $episode_id ) {
				$season_episode_ids[] = $episode_id;
			}
		}
	}

	$alias_map       = streamit_repair_build_episode_alias_map( $tvshow_id, $season_episode_ids );
	$seasons_cleaned = streamit_repair_apply_episode_alias_map_to_seasons( $seasons, $alias_map );

	if ( $seasons_cleaned !== $seasons ) {
		streamit_update_tvshow_meta( $tvshow_id, '_seasons', $seasons_cleaned );
		++$seasons_updated;
		$seasons = $seasons_cleaned;
	}

	foreach ( $alias_map as $duplicate_id => $canonical_id ) {
		$result = streamit_update_episode_status( $duplicate_id, 'trash' );
		if ( is_wp_error( $result ) ) {
			echo "Failed to trash duplicate episode {$duplicate_id}: " . $result->get_error_message() . PHP_EOL;
			continue;
		}
		++$trashed_dupes;
	}

	$seen    = array();
	$changed = false;

	foreach ( $seasons as $season ) {
		foreach ( (array) ( $season['episodes'] ?? array() ) as $episode_id ) {
			$episode_id = absint( $episode_id );
			if ( ! $episode_id || isset( $seen[ $episode_id ] ) ) {
				continue;
			}

			$seen[ $episode_id ] = true;

			$status = streamit_repair_get_episode_post_status( $episode_id );

			if ( null === $status ) {
				++$missing;
				continue;
			}

			if ( 'publish' === $status ) {
				++$skipped;
				continue;
			}

			if ( 'trash' === $status ) {
				++$skipped;
				continue;
			}

			$result = streamit_update_episode_status( $episode_id, 'publish' );

			if ( is_wp_error( $result ) ) {
				echo "Failed episode {$episode_id}: " . $result->get_error_message() . PHP_EOL;
				continue;
			}

			++$published;
			$changed = true;
		}
	}

	if ( $changed && class_exists( 'Streamit_Advanced_Cache' ) ) {
		$cache = new Streamit_Advanced_Cache( 'streamit_episodes' );
		$cache->invalidate_tags( array( 'episode_list' ) );
	}
}

echo "TV shows scanned: {$shows}" . PHP_EOL;
echo "Season meta deduped: {$seasons_updated}" . PHP_EOL;
echo "Duplicate episodes trashed: {$trashed_dupes}" . PHP_EOL;
echo "Episodes published: {$published}" . PHP_EOL;
echo "Already published/skipped: {$skipped}" . PHP_EOL;
echo "Missing episode IDs: {$missing}" . PHP_EOL;

if ( $published > 0 || $trashed_dupes > 0 || $seasons_updated > 0 ) {
	if ( function_exists( 'streamit_child_flush_streamit_cache' ) ) {
		streamit_child_flush_streamit_cache();
	} elseif ( class_exists( 'Streamit_Advanced_Cache' ) ) {
		Streamit_Advanced_Cache::flush_all_streamit_cache();
	}
	echo "Streamit cache flushed." . PHP_EOL;
}

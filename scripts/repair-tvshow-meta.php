<?php
/**
 * Backfill TV show metadata from TMDB for existing imports.
 *
 * Usage:
 *   php repair-tvshow-meta.php              # all TV shows with a TMDB ID
 *   php repair-tvshow-meta.php <tvshow_id>  # one show
 */
require '/var/www/html/wp-load.php';

$target_tvshow_id = isset( $argv[1] ) ? absint( $argv[1] ) : 0;

if ( $target_tvshow_id ) {
	$tvshow_ids = array( $target_tvshow_id );
} else {
	global $wpdb;
	$tvshow_ids = $wpdb->get_col(
		"SELECT streamit_tvshow_id FROM {$wpdb->streamit_tvshowmeta} WHERE meta_key = '_tmdb_id' AND meta_value <> '' GROUP BY streamit_tvshow_id"
	);
}

$settings = @unserialize( get_option( 'streamit_content_import_settings' ) );
$api_key  = $settings['tmdb']['api_key'] ?? '';
$language = 'en-US';

/**
 * @param string $url TMDB API URL.
 * @return array|null
 */
function streamit_repair_tvshow_fetch_tmdb_json( $url ) {
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

/**
 * @param string $iso TMDB ISO 639-1 code.
 * @return array|null Serialized language entry used by Streamit.
 */
function streamit_repair_tvshow_language_entry( $iso ) {
	$iso = sanitize_text_field( (string) $iso );
	if ( '' === $iso || ! function_exists( 'streamit_get_language_mapping' ) ) {
		return null;
	}

	$mapping = streamit_get_language_mapping();
	$label   = array_search( $iso, $mapping, true );
	if ( false === $label ) {
		$label = strtoupper( $iso );
	}

	return maybe_serialize(
		array(
			'slugs'  => array( $iso ),
			'labels' => array( $label ),
		)
	);
}

$updated = 0;

foreach ( $tvshow_ids as $tvshow_id ) {
	$tvshow_id = absint( $tvshow_id );
	$tvshow    = function_exists( 'streamit_get_tvshow' ) ? streamit_get_tvshow( $tvshow_id ) : null;
	$tmdb_id   = streamit_get_tvshow_meta( $tvshow_id, '_tmdb_id', true );

	if ( ! $tvshow || ! $tmdb_id || ! $api_key ) {
		continue;
	}

	$show_url  = "https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key={$api_key}&language={$language}";
	$show_data = streamit_repair_tvshow_fetch_tmdb_json( $show_url );
	if ( empty( $show_data ) ) {
		echo "Tvshow {$tvshow_id}: TMDB fetch failed\n";
		continue;
	}

	$fields_written = 0;

	if ( empty( streamit_get_tvshow_meta( $tvshow_id, 'name_custom_imdb_rating', true ) ) && isset( $show_data['vote_average'] ) ) {
		streamit_update_tvshow_meta( $tvshow_id, 'name_custom_imdb_rating', (string) $show_data['vote_average'] );
		$fields_written++;
	}

	$external_url  = "https://api.themoviedb.org/3/tv/{$tmdb_id}/external_ids?api_key={$api_key}";
	$external_data = streamit_repair_tvshow_fetch_tmdb_json( $external_url );
	if ( ! empty( $external_data['imdb_id'] ) && empty( streamit_get_tvshow_meta( $tvshow_id, '_imdb_id', true ) ) ) {
		streamit_update_tvshow_meta( $tvshow_id, '_imdb_id', sanitize_text_field( $external_data['imdb_id'] ) );
		$fields_written++;
	}

	if ( empty( streamit_get_tvshow_meta( $tvshow_id, '_language', true ) ) && ! empty( $show_data['original_language'] ) ) {
		$language_entry = streamit_repair_tvshow_language_entry( $show_data['original_language'] );
		if ( $language_entry ) {
			streamit_update_tvshow_meta( $tvshow_id, '_language', $language_entry );
			$fields_written++;
		}
	}

	if ( $fields_written ) {
		$updated++;
		echo "Tvshow {$tvshow_id}: backfilled {$fields_written} meta field(s)\n";
	}
}

if ( function_exists( 'streamit_child_flush_streamit_cache' ) ) {
	streamit_child_flush_streamit_cache();
} elseif ( class_exists( 'Streamit_Advanced_Cache' ) ) {
	Streamit_Advanced_Cache::flush_all_streamit_cache();
}

echo "Done. {$updated} TV show(s) updated.\n";

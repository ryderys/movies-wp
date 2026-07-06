<?php
/**
 * Backfill country/origin metadata from TMDB for existing movies and TV shows.
 *
 * Usage:
 *   php repair-country-meta.php
 */
require '/var/www/html/wp-load.php';

$settings = @unserialize( get_option( 'streamit_content_import_settings' ) );
$api_key  = $settings['tmdb']['api_key'] ?? '';
$language = 'en-US';

if ( ! $api_key ) {
	echo "Missing TMDB API key in streamit_content_import_settings.\n";
	exit( 1 );
}

/**
 * @param string $url TMDB API URL.
 * @return array|null
 */
function streamit_repair_country_fetch_tmdb_json( $url ) {
	if ( function_exists( 'fetch_tmdb_tvshow_data' ) ) {
		$response = fetch_tmdb_tvshow_data( $url );
		if ( ! empty( $response['status'] ) && ! empty( $response['data'] ) ) {
			return $response['data'];
		}
	}

	if ( function_exists( 'fetch_tmdb_movie_data' ) ) {
		$response = fetch_tmdb_movie_data( $url );
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

global $wpdb;

$movie_ids = $wpdb->get_col(
	"SELECT streamit_movie_id FROM {$wpdb->streamit_moviemeta} WHERE meta_key = '_tmdb_id' AND meta_value <> '' GROUP BY streamit_movie_id"
);
$tvshow_ids = $wpdb->get_col(
	"SELECT streamit_tvshow_id FROM {$wpdb->streamit_tvshowmeta} WHERE meta_key = '_tmdb_id' AND meta_value <> '' GROUP BY streamit_tvshow_id"
);

$updated_movies  = 0;
$updated_tvshows = 0;

foreach ( $movie_ids as $movie_id ) {
	$movie_id = absint( $movie_id );
	$tmdb_id  = streamit_get_movie_meta( $movie_id, '_tmdb_id', true );

	if ( ! $movie_id || ! $tmdb_id ) {
		continue;
	}

	$url  = "https://api.themoviedb.org/3/movie/{$tmdb_id}?api_key={$api_key}&language={$language}";
	$data = streamit_repair_country_fetch_tmdb_json( $url );

	if ( empty( $data['production_countries'] ) ) {
		continue;
	}

	streamit_save_country_meta( 'movie', $movie_id, $data['production_countries'] );
	$updated_movies++;
	echo "Movie {$movie_id}: country meta updated\n";
}

foreach ( $tvshow_ids as $tvshow_id ) {
	$tvshow_id = absint( $tvshow_id );
	$tmdb_id   = streamit_get_tvshow_meta( $tvshow_id, '_tmdb_id', true );

	if ( ! $tvshow_id || ! $tmdb_id ) {
		continue;
	}

	$url  = "https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key={$api_key}&language={$language}";
	$data = streamit_repair_country_fetch_tmdb_json( $url );

	if ( empty( $data['origin_country'] ) ) {
		continue;
	}

	streamit_save_country_meta( 'tvshow', $tvshow_id, $data['origin_country'] );
	$updated_tvshows++;
	echo "Tvshow {$tvshow_id}: country meta updated\n";
}

if ( function_exists( 'streamit_child_flush_streamit_cache' ) ) {
	streamit_child_flush_streamit_cache();
} elseif ( class_exists( 'Streamit_Advanced_Cache' ) ) {
	Streamit_Advanced_Cache::flush_all_streamit_cache();
}

echo "Done. {$updated_movies} movie(s), {$updated_tvshows} TV show(s) updated.\n";

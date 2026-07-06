<?php
/**
 * Repair TMDB images for an imported movie (poster, backdrop banner, attachment meta).
 * Usage: php repair-movie-images.php <movie_id>
 */
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once STREAMIT_PLUGIN_PATH . 'admin/content-import/streamit-tmdb_movie-function.php';

$movie_id = isset( $argv[1] ) ? absint( $argv[1] ) : 0;
if ( ! $movie_id ) {
	echo "Usage: php repair-movie-images.php <movie_id>\n";
	exit( 1 );
}

$settings = @unserialize( get_option( 'streamit_content_import_settings' ) );
$api_key  = $settings['tmdb']['api_key'] ?? '';
$tmdb_id  = streamit_get_movie_meta( $movie_id, '_tmdb_id', true );

if ( empty( $tmdb_id ) || empty( $api_key ) ) {
	echo "Missing tmdb_id or api_key for movie {$movie_id}\n";
	exit( 1 );
}

echo "Repairing movie {$movie_id} (TMDB {$tmdb_id})\n";

$url  = "https://api.themoviedb.org/3/movie/{$tmdb_id}?api_key={$api_key}";
$resp = fetch_tmdb_movie_data( $url );
$data = ( ! empty( $resp['status'] ) && ! empty( $resp['data'] ) ) ? $resp['data'] : array();

if ( empty( $data ) ) {
	echo "Could not fetch TMDB movie data\n";
	exit( 1 );
}

// Poster (portrait / cards).
$poster_id = 0;
if ( ! empty( $data['poster_path'] ) ) {
	$poster_id = streamit_download_and_attach_movie_image(
		streamit_get_tmdb_image_url( $data['poster_path'], 'original' )
	);
	if ( ! is_wp_error( $poster_id ) ) {
		streamit_update_movie_meta( $movie_id, '_portrait_thumbmail', $poster_id );
		echo "  poster: OK ({$poster_id})\n";
	} else {
		echo '  poster: FAIL - ' . $poster_id->get_error_message() . "\n";
	}
}

// Backdrop (hero banner on single movie page uses thumbnail_id).
if ( ! empty( $data['backdrop_path'] ) ) {
	$backdrop_id = streamit_download_and_attach_movie_image(
		streamit_get_tmdb_image_url( $data['backdrop_path'], 'original' )
	);
	if ( ! is_wp_error( $backdrop_id ) ) {
		streamit_update_movie_meta( $movie_id, 'thumbnail_id', $backdrop_id );
		echo "  backdrop: OK ({$backdrop_id})\n";
	} else {
		echo '  backdrop: FAIL - ' . $backdrop_id->get_error_message() . "\n";
	}
} elseif ( ! empty( $poster_id ) && ! is_wp_error( $poster_id ) ) {
	streamit_update_movie_meta( $movie_id, 'thumbnail_id', $poster_id );
	echo "  banner: using poster ({$poster_id})\n";
}

$thumb = streamit_get_movie_meta( $movie_id, 'thumbnail_id', true );
$port  = streamit_get_movie_meta( $movie_id, '_portrait_thumbmail', true );
echo '  banner url: ' . ( $thumb ? ( wp_get_attachment_image_url( $thumb, 'full' ) ?: 'BROKEN' ) : 'none' ) . "\n";
echo '  portrait url: ' . ( $port ? ( wp_get_attachment_image_url( $port, 'full' ) ?: 'BROKEN' ) : 'none' ) . "\n";
echo "Done.\n";

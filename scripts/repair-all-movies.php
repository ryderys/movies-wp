<?php
/**
 * Repair images and cast for all movies with a TMDB ID.
 * Usage: php repair-all-movies.php
 */
require '/var/www/html/wp-load.php';
global $wpdb;

$movie_ids = $wpdb->get_col(
	"SELECT DISTINCT streamit_movie_id FROM {$wpdb->streamit_moviemeta} WHERE meta_key = '_tmdb_id' AND meta_value != ''"
);

if ( empty( $movie_ids ) ) {
	$movie_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->streamit_movie} ORDER BY ID ASC" );
}

echo 'Movies to repair: ' . count( $movie_ids ) . "\n";

foreach ( $movie_ids as $movie_id ) {
	$movie_id = (int) $movie_id;
	echo "\n# Movie {$movie_id}\n";
	passthru( 'php ' . escapeshellarg( __DIR__ . '/repair-movie-images.php' ) . ' ' . (int) $movie_id );
	passthru( 'php ' . escapeshellarg( __DIR__ . '/repair-movie-cast.php' ) . ' ' . (int) $movie_id );
}

passthru( 'php ' . escapeshellarg( __DIR__ . '/repair-broken-attachments.php' ) . ' --all' );

if ( function_exists( 'streamit_child_flush_streamit_cache' ) ) {
	streamit_child_flush_streamit_cache();
	echo "\nCache flushed.\n";
} elseif ( class_exists( 'Streamit_Advanced_Cache' ) ) {
	Streamit_Advanced_Cache::flush_all_streamit_cache();
	echo "\nCache flushed.\n";
}

echo "All movies repaired.\n";

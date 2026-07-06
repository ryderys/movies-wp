<?php
/**
 * Set TV show banner image from TMDB backdrop (landscape hero).
 * Usage: php repair-tvshow-backdrop.php <tvshow_id>
 */
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once STREAMIT_PLUGIN_PATH . 'admin/content-import/streamit-tmdb_tvshow-function.php';

$tvshow_id = isset( $argv[1] ) ? absint( $argv[1] ) : 0;
if ( ! $tvshow_id ) {
	echo "Usage: php repair-tvshow-backdrop.php <tvshow_id>\n";
	exit( 1 );
}

$settings = @unserialize( get_option( 'streamit_content_import_settings' ) );
$api_key  = $settings['tmdb']['api_key'] ?? '';
$tmdb_id  = streamit_get_tvshow_meta( $tvshow_id, '_tmdb_id', true );

if ( empty( $tmdb_id ) || empty( $api_key ) ) {
	echo "Missing tmdb_id or api_key\n";
	exit( 1 );
}

$url  = "https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key={$api_key}";
$resp = fetch_tmdb_tvshow_data( $url );
$data = is_wp_error( $resp ) ? array() : ( $resp['data'] ?? $resp );

if ( empty( $data['backdrop_path'] ) ) {
	echo "No backdrop_path in TMDB data\n";
	exit( 0 );
}

$backdrop_url = streamit_get_tmdb_image_url( $data['backdrop_path'], 'original' );
$backdrop_id  = streamit_download_and_attach_tvshow_image( $backdrop_url );

if ( is_wp_error( $backdrop_id ) ) {
	echo 'Backdrop download failed: ' . $backdrop_id->get_error_message() . "\n";
	exit( 1 );
}

streamit_update_tvshow_meta( $tvshow_id, 'thumbnail_id', $backdrop_id );
echo "Set thumbnail_id (banner) to backdrop attachment {$backdrop_id}\n";
echo 'URL: ' . wp_get_attachment_image_url( $backdrop_id, 'full' ) . "\n";

// Keep portrait poster if missing.
if ( ! empty( $data['poster_path'] ) && ! streamit_get_tvshow_meta( $tvshow_id, '_portrait_thumbmail', true ) ) {
	$poster_id = streamit_download_and_attach_tvshow_image(
		streamit_get_tmdb_image_url( $data['poster_path'], 'original' )
	);
	if ( ! is_wp_error( $poster_id ) ) {
		streamit_update_tvshow_meta( $tvshow_id, '_portrait_thumbmail', $poster_id );
		echo "Set portrait to {$poster_id}\n";
	}
}

echo "Done.\n";

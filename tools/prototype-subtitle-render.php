<?php
/**
 * READ-ONLY design prototype: relative subtitle path → signed <track src> at render time.
 *
 * NOT loaded by WordPress. NOT wired to the player. Does not write metadata.
 *
 * Intended call at movie-page render (future child-theme change, not this phase):
 *
 *   $stored = 'Movie/Korea/2023/Believer.2/file.fa.srt';  // _subtitles[].url
 *   $src    = movies_wp_prototype_subtitle_track_src( $stored ); // /v/{token}
 *   $href   = movies_wp_prototype_subtitle_download_href( $stored ); // /d/{token}
 *
 * Storage must remain the relative path. Never persist the returned URLs.
 *
 * Existing primitives reused (no new URL scheme):
 *   movies_wp_media_normalize_relative_path()
 *   movies_wp_media_signed_url( $path, 'v' )  // stream, default TTL 6 hours
 *   movies_wp_media_signed_url( $path, 'd' )  // download, default TTL 30 minutes
 *
 * verify.php has no extension allowlist: .srt/.vtt under /data are served after HMAC.
 *
 * @package movies-wp
 */

if ( php_sapi_name() === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
	fwrite(
		STDOUT,
		"Subtitle signing prototype (documentation only).\n" .
		"This file is not a WordPress plugin and does not mint URLs by itself.\n" .
		"Render layer must call movies_wp_media_signed_url() — see docs/streamit-import-contract.md §15.5.\n"
	);
	exit( 0 );
}

/**
 * Signed URL for HTML <track src>. Uses existing stream mint (type v).
 *
 * @param string $relative_path Path under /data, e.g. Movie/Korea/2023/Believer.2/file.fa.srt
 * @return string|WP_Error
 */
function movies_wp_prototype_subtitle_track_src( $relative_path ) {
	if ( ! function_exists( 'movies_wp_media_signed_url' ) ) {
		return new WP_Error( 'media_mint_missing', 'Media signed URL mint is not loaded.' );
	}

	return movies_wp_media_signed_url( $relative_path, 'v' );
}

/**
 * Signed URL for the download-modal href. Uses existing download mint (type d).
 *
 * @param string $relative_path Path under /data.
 * @return string|WP_Error
 */
function movies_wp_prototype_subtitle_download_href( $relative_path ) {
	if ( ! function_exists( 'movies_wp_media_signed_url' ) ) {
		return new WP_Error( 'media_mint_missing', 'Media signed URL mint is not loaded.' );
	}

	return movies_wp_media_signed_url( $relative_path, 'd' );
}

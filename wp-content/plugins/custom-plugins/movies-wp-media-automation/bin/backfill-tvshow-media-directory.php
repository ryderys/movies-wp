<?php
/**
 * Backfill empty TV show `_media_directory` from episode source paths.
 *
 * Dry-run (prints what would be written; changes nothing):
 *   php wp-content/plugins/custom-plugins/movies-wp-media-automation/bin/backfill-tvshow-media-directory.php
 *
 * Write missing values only:
 *   php wp-content/plugins/custom-plugins/movies-wp-media-automation/bin/backfill-tvshow-media-directory.php --write
 *
 * @package movies-wp
 */

$wp_load = dirname( __DIR__, 5 ) . '/wp-load.php';
if ( ! is_file( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php was not found at {$wp_load}\n" );
	exit( 1 );
}
require $wp_load;

$write = in_array( '--write', $argv, true );
$shows = movies_wp_tvshow_media_directory_collect();
$result = Movies_WP_Tvshow_Media_Directory::backfill(
	$shows,
	array( 'dry_run' => ! $write )
);

echo $result['dry_run'] ? "dry-run\n" : "write\n";
foreach ( $result['rows'] as $row ) {
	$id = (int) ( $row['tvshow_id'] ?? 0 );
	if ( 'skipped' === ( $row['action'] ?? '' ) ) {
		echo "skip {$id} {$row['reason']}\n";
		continue;
	}
	echo (string) ( $row['action'] ?? '' ) . " {$id} " . (string) ( $row['directory'] ?? '' ) . "\n";
}
echo 'written=' . (int) $result['written'] . "\n";
exit( 0 );

/**
 * @return list<array{tvshow_id:int,media_directory:string,sources:list<array<string,mixed>>}>
 */
function movies_wp_tvshow_media_directory_collect() {
	global $wpdb;
	if ( ! isset( $wpdb->streamit_tvshow, $wpdb->streamit_tvshowmeta, $wpdb->streamit_episodemeta ) ) {
		fwrite( STDERR, "Streamit TV show tables are not available.\n" );
		exit( 1 );
	}

	$tvshow_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->streamit_tvshow} ORDER BY ID ASC" );
	$shows      = array();
	foreach ( $tvshow_ids as $tvshow_id ) {
		$tvshow_id = (int) $tvshow_id;
		$existing  = function_exists( 'streamit_get_tvshow_meta' )
			? streamit_get_tvshow_meta( $tvshow_id, '_media_directory', true )
			: '';
		$episode_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT streamit_episode_id FROM {$wpdb->streamit_episodemeta} WHERE meta_key = %s AND meta_value = %s",
				'tvshow_id',
				(string) $tvshow_id
			)
		);
		$sources = array();
		foreach ( $episode_ids as $episode_id ) {
			$raw = function_exists( 'streamit_get_episode_meta' )
				? streamit_get_episode_meta( (int) $episode_id, '_sources', true )
				: '';
			if ( is_string( $raw ) ) {
				$raw = maybe_unserialize( $raw );
			}
			if ( ! is_array( $raw ) ) {
				continue;
			}
			foreach ( $raw as $row ) {
				if ( is_array( $row ) ) {
					$sources[] = $row;
				}
			}
		}
		$shows[] = array(
			'tvshow_id'        => $tvshow_id,
			'media_directory'  => is_string( $existing ) ? $existing : '',
			'sources'          => $sources,
		);
	}
	return $shows;
}

<?php
/**
 * Repair cast links and person profile images for an imported TV show.
 * Usage: php repair-tvshow-cast.php <tvshow_id>
 */
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once STREAMIT_PLUGIN_PATH . 'admin/content-import/streamit-tmdb_tvshow-function.php';

$tvshow_id = isset( $argv[1] ) ? absint( $argv[1] ) : 0;
if ( ! $tvshow_id ) {
	echo "Usage: php repair-tvshow-cast.php <tvshow_id>\n";
	exit( 1 );
}

global $wpdb;

$settings = @unserialize( get_option( 'streamit_content_import_settings' ) );
$api_key  = $settings['tmdb']['api_key'] ?? '';
$tmdb_id  = streamit_get_tvshow_meta( $tvshow_id, '_tmdb_id', true );
$language = 'en-US';

if ( empty( $tmdb_id ) || empty( $api_key ) ) {
	echo "Missing tmdb_id or api_key for tvshow {$tvshow_id}\n";
	exit( 1 );
}

$cast_url  = "https://api.themoviedb.org/3/tv/{$tmdb_id}/credits?api_key={$api_key}&language={$language}";
$cast_resp = fetch_tmdb_tvshow_data( $cast_url );
$cast_body = is_wp_error( $cast_resp ) ? array() : ( $cast_resp['data'] ?? $cast_resp );
$cast_list = $cast_body['cast'] ?? array();
$crew_list = $cast_body['crew'] ?? array();

echo "Repairing cast/crew for tvshow {$tvshow_id}: cast=" . count( $cast_list ) . ' crew=' . count( $crew_list ) . "\n";

$cast_meta = array();
$thumbs    = 0;

foreach ( $cast_list as $index => $member ) {
	$name = $member['name'] ?? '';
	if ( ! $name ) {
		continue;
	}

	$person_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->streamit_person} WHERE post_title = %s ORDER BY ID DESC LIMIT 1",
			$name
		)
	);

	if ( ! $person_id && function_exists( 'streamit_add_person' ) ) {
		$person_id = streamit_add_person(
			array(
				'post_author'           => 1,
				'post_date'             => current_time( 'mysql' ),
				'post_date_gmt'         => current_time( 'mysql', 1 ),
				'post_content'          => '',
				'post_title'            => sanitize_text_field( $name ),
				'post_excerpt'          => '',
				'post_status'           => 'publish',
				'comment_status'        => 'open',
				'ping_status'           => 'open',
				'post_password'         => '',
				'post_name'             => sanitize_title( $name ),
				'post_modified'         => current_time( 'mysql' ),
				'post_modified_gmt'     => current_time( 'mysql', 1 ),
				'post_content_filtered' => '',
				'post_parent'           => 0,
				'guid'                  => '',
				'menu_order'            => 0,
				'post_type'             => 'person',
				'comment_count'         => 0,
			)
		);
		if ( is_wp_error( $person_id ) ) {
			$person_id = 0;
		}
	}

	if ( ! $person_id ) {
		continue;
	}

	$cast_meta[] = array(
		'id'        => $person_id,
		'character' => sanitize_text_field( $member['character'] ?? '' ),
		'position'  => (string) ( $member['order'] ?? $index ),
	);

	if ( ! empty( $member['profile_path'] ) && ! streamit_get_person_meta( $person_id, 'thumbnail_id', true ) ) {
		$att = streamit_download_and_attach_tvshow_image(
			streamit_get_tmdb_image_url( $member['profile_path'], 'w500' )
		);
		if ( ! is_wp_error( $att ) ) {
			streamit_update_person_meta( $person_id, 'thumbnail_id', $att );
			$thumbs++;
		}
	}

	if ( ! empty( $member['id'] ) ) {
		streamit_update_person_meta( $person_id, '_tmdb_id', (string) $member['id'] );
	}
}

if ( ! empty( $cast_meta ) ) {
	streamit_update_tvshow_meta( $tvshow_id, '_cast', $cast_meta );
	echo '  cast entries linked: ' . count( $cast_meta ) . "\n";
}

$crew_fixed = 0;
foreach ( $crew_list as $member ) {
	$name = $member['name'] ?? '';
	if ( ! $name || empty( $member['profile_path'] ) ) {
		continue;
	}

	$person_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->streamit_person} WHERE post_title = %s ORDER BY ID DESC LIMIT 1",
			$name
		)
	);

	if ( ! $person_id && function_exists( 'streamit_add_person' ) ) {
		$person_id = streamit_add_person(
			array(
				'post_author'           => 1,
				'post_date'             => current_time( 'mysql' ),
				'post_date_gmt'         => current_time( 'mysql', 1 ),
				'post_content'          => '',
				'post_title'            => sanitize_text_field( $name ),
				'post_excerpt'          => '',
				'post_status'           => 'publish',
				'comment_status'        => 'open',
				'ping_status'           => 'open',
				'post_password'         => '',
				'post_name'             => sanitize_title( $name ),
				'post_modified'         => current_time( 'mysql' ),
				'post_modified_gmt'     => current_time( 'mysql', 1 ),
				'post_content_filtered' => '',
				'post_parent'           => 0,
				'guid'                  => '',
				'menu_order'            => 0,
				'post_type'             => 'person',
				'comment_count'         => 0,
			)
		);
		if ( is_wp_error( $person_id ) ) {
			$person_id = 0;
		}
	}

	if ( ! $person_id || streamit_get_person_meta( $person_id, 'thumbnail_id', true ) ) {
		continue;
	}

	$att = streamit_download_and_attach_tvshow_image(
		streamit_get_tmdb_image_url( $member['profile_path'], 'w500' )
	);
	if ( ! is_wp_error( $att ) ) {
		streamit_update_person_meta( $person_id, 'thumbnail_id', $att );
		$crew_fixed++;
	}
}

echo "  cast thumbnails: {$thumbs}\n";
echo "  crew thumbnails: {$crew_fixed}\n";
echo "Done.\n";

<?php
/**
 * Repair cast links and person profile images for an imported movie.
 * Usage: php repair-movie-cast.php <movie_id>
 */
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once STREAMIT_PLUGIN_PATH . 'admin/content-import/streamit-tmdb_movie-function.php';

$movie_id = isset( $argv[1] ) ? absint( $argv[1] ) : 0;
if ( ! $movie_id ) {
	echo "Usage: php repair-movie-cast.php <movie_id>\n";
	exit( 1 );
}

global $wpdb;

$settings = @unserialize( get_option( 'streamit_content_import_settings' ) );
$api_key  = $settings['tmdb']['api_key'] ?? '';
$tmdb_id  = streamit_get_movie_meta( $movie_id, '_tmdb_id', true );
$language = 'en-US';

if ( empty( $tmdb_id ) || empty( $api_key ) ) {
	echo "Missing tmdb_id or api_key for movie {$movie_id}\n";
	exit( 1 );
}

$url  = "https://api.themoviedb.org/3/movie/{$tmdb_id}/credits?api_key={$api_key}&language={$language}";
$resp = fetch_tmdb_movie_data( $url );
$body = ( ! empty( $resp['status'] ) && ! empty( $resp['data'] ) ) ? $resp['data'] : array();

$cast_list = $body['cast'] ?? array();
$crew_list = $body['crew'] ?? array();

echo 'Repairing movie ' . $movie_id . ' cast=' . count( $cast_list ) . ' crew=' . count( $crew_list ) . "\n";

/**
 * Find or create a person for a TMDB credits entry.
 *
 * @param array $member TMDB cast/crew member.
 * @return int Person ID or 0.
 */
function streamit_child_get_or_create_person_for_credit( $member ) {
	global $wpdb;

	$name    = $member['name'] ?? '';
	$tmdb_id = isset( $member['id'] ) ? (string) $member['id'] : '';

	if ( ! $name ) {
		return 0;
	}

	if ( $tmdb_id ) {
		$person_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT streamit_person_id FROM {$wpdb->streamit_personmeta} WHERE meta_key = '_tmdb_id' AND meta_value = %s LIMIT 1",
				$tmdb_id
			)
		);
		if ( $person_id ) {
			return $person_id;
		}
	}

	$person_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->streamit_person} WHERE post_title = %s ORDER BY ID DESC LIMIT 1",
			$name
		)
	);

	if ( $person_id ) {
		return $person_id;
	}

	if ( ! function_exists( 'streamit_add_person' ) ) {
		return 0;
	}

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

	return is_wp_error( $person_id ) ? 0 : (int) $person_id;
}

$cast_meta = array();
$thumbs    = 0;

foreach ( $cast_list as $index => $member ) {
	$person_id = streamit_child_get_or_create_person_for_credit( $member );
	if ( ! $person_id ) {
		continue;
	}

	$cast_meta[] = array(
		'id'        => $person_id,
		'character' => sanitize_text_field( $member['character'] ?? '' ),
		'position'  => (string) ( $member['order'] ?? $index ),
	);

	if ( ! empty( $member['id'] ) ) {
		streamit_update_person_meta( $person_id, '_tmdb_id', (string) $member['id'] );
	}

	if ( ! empty( $member['profile_path'] ) && ! streamit_get_person_meta( $person_id, 'thumbnail_id', true ) ) {
		$att = streamit_download_and_attach_movie_image(
			streamit_get_tmdb_image_url( $member['profile_path'], 'w500' )
		);
		if ( ! is_wp_error( $att ) ) {
			streamit_update_person_meta( $person_id, 'thumbnail_id', $att );
			++$thumbs;
		}
	}
}

if ( ! empty( $cast_meta ) ) {
	streamit_update_movie_meta( $movie_id, '_cast', $cast_meta );
	echo '  cast entries linked: ' . count( $cast_meta ) . "\n";
}

$crew_meta  = array();
$crew_thumbs = 0;

foreach ( $crew_list as $member ) {
	$person_id = streamit_child_get_or_create_person_for_credit( $member );
	if ( ! $person_id ) {
		continue;
	}

	$crew_meta[] = array(
		'id'  => $person_id,
		'job' => sanitize_text_field( $member['job'] ?? '' ),
	);

	if ( ! empty( $member['id'] ) ) {
		streamit_update_person_meta( $person_id, '_tmdb_id', (string) $member['id'] );
	}

	if ( ! empty( $member['profile_path'] ) && ! streamit_get_person_meta( $person_id, 'thumbnail_id', true ) ) {
		$att = streamit_download_and_attach_movie_image(
			streamit_get_tmdb_image_url( $member['profile_path'], 'w500' )
		);
		if ( ! is_wp_error( $att ) ) {
			streamit_update_person_meta( $person_id, 'thumbnail_id', $att );
			++$crew_thumbs;
		}
	}
}

if ( ! empty( $crew_meta ) ) {
	streamit_update_movie_meta( $movie_id, '_crew', $crew_meta );
	echo '  crew entries linked: ' . count( $crew_meta ) . "\n";
}

echo "  cast thumbnails: {$thumbs}\n";
echo "  crew thumbnails: {$crew_thumbs}\n";
echo "Done.\n";

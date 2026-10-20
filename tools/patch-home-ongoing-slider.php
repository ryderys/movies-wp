<?php
/**
 * Homepage layout:
 * - Heading "پخش هفتگی" → "سریال های محبوب"
 * - Vertical Thumbnail Banner → most_viewed TV shows
 * - Top Ten under Continue Watching → "در حال پخش" (Returning Series status)
 *
 * Usage (inside WordPress container):
 *   php wp-cli.phar eval-file /path/to/patch-home-ongoing-slider.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

const SC_HOME_POST_ID     = 3859;
const SC_RETURNING_TERM   = '95';
const SC_POPULAR_TITLE    = 'سریال های محبوب';
const SC_ONGOING_TITLE    = 'در حال پخش';
const SC_POPULAR_COUNT    = 6;
const SC_ONGOING_COUNT    = 6;

/**
 * @param int $post_id
 * @return array<int, mixed>
 */
function sc_load_elementor( $post_id ) {
	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( is_string( $raw ) ) {
		$data = json_decode( $raw, true );
	} else {
		$data = $raw;
	}
	if ( is_string( $data ) ) {
		$data = json_decode( $data, true );
	}
	return is_array( $data ) ? $data : array();
}

/**
 * @return array<string, mixed>
 */
function sc_top_ten_ongoing_settings() {
	return array(
		'slider_title'              => SC_ONGOING_TITLE,
		'title_tag'                 => 'h4',
		'st_post_type'              => 'tvshow',
		'st_select_post_filter'     => 'status',
		'st_selected_tvshow_status' => array( SC_RETURNING_TERM ),
		'st_selected_tvshows'       => array(),
		'st_selected_tvshow_tag'    => array(),
		'st_selected_tvshow_genre'  => array(),
		'st_selected_movies'        => array(),
		'st_selected_videos'        => array(),
		'posts_per_page'            => array(
			'unit'  => '%',
			'size'  => SC_ONGOING_COUNT,
			'sizes' => array(),
		),
		'enable_premium_badges'     => 'yes',
		'_title'                    => 'Top Ten Slider',
	);
}

$live    = sc_load_elementor( SC_HOME_POST_ID );
$changes = array();

$walk = function ( array &$nodes ) use ( &$walk, &$changes ) {
	foreach ( $nodes as &$n ) {
		$type = (string) ( $n['widgetType'] ?? '' );
		$id   = (string) ( $n['id'] ?? '' );

		if ( 'heading' === $type && isset( $n['settings']['title'] ) ) {
			$title = (string) $n['settings']['title'];
			if ( in_array( $title, array( 'پخش هفتگی', SC_POPULAR_TITLE ), true ) ) {
				$n['settings']['title'] = SC_POPULAR_TITLE;
				$changes[]             = "heading:{$id}";
			}
		}

		if ( 'vertical-thumbnail-banner' === $type ) {
			if ( ! isset( $n['settings'] ) || ! is_array( $n['settings'] ) ) {
				$n['settings'] = array();
			}
			$n['settings']['st_post_filter']           = 'most_viewed';
			$n['settings']['st_select_content_type']   = 'tvshow';
			$n['settings']['st_selected_movie_genre']  = array();
			$n['settings']['st_selected_tvshow_genre'] = array();
			$n['settings']['st_selected_video_genre']  = array();
			$n['settings']['posts_per_page']           = array(
				'unit'  => '%',
				'size'  => SC_POPULAR_COUNT,
				'sizes' => array(),
			);
			$changes[] = "vtb:{$id}";
		}

		if ( 'top-ten-slider' === $type ) {
			$title = (string) ( $n['settings']['slider_title'] ?? '' );
			if ( in_array( $title, array( 'سریال های محبوب', 'در حال پخش', 'درحال پخش' ), true )
				|| 'most_viewed' === (string) ( $n['settings']['st_select_post_filter'] ?? '' )
				|| 'status' === (string) ( $n['settings']['st_select_post_filter'] ?? '' )
			) {
				$n['settings'] = sc_top_ten_ongoing_settings();
				$changes[]     = "topten:{$id}";
			}
		}

		if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
			$walk( $n['elements'] );
		}
	}
};
$walk( $live );

if ( empty( $changes ) ) {
	fwrite( STDERR, "no matching homepage widgets found\n" );
	exit( 1 );
}

$encoded = wp_json_encode( $live );
if ( ! is_string( $encoded ) || '' === $encoded ) {
	fwrite( STDERR, "json encode failed\n" );
	exit( 1 );
}

update_post_meta( SC_HOME_POST_ID, '_elementor_data', wp_slash( $encoded ) );

if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}

echo 'ok ' . implode( ',', $changes ) . "\n";
echo 'popular=' . SC_POPULAR_TITLE . " (VTB most_viewed)\n";
echo 'ongoing=' . SC_ONGOING_TITLE . " (Top Ten status " . SC_RETURNING_TERM . ")\n";

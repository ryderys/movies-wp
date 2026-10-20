<?php
/**
 * Replace homepage "در حال پخش" Main Card Slider with a Top Ten Slider
 * filtered by tv_status "Returning Series".
 *
 * Usage (inside WordPress container):
 *   php wp-cli.phar eval-file /path/to/patch-home-ongoing-slider.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

const SC_HOME_POST_ID    = 3859;
const SC_RETURNING_TERM  = '95';
const SC_SLIDER_TITLE    = 'در حال پخش';
const SC_CONTINUE_PARENT = '5006cb8';

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
		'slider_title'              => SC_SLIDER_TITLE,
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
			'size'  => 10,
			'sizes' => array(),
		),
		'enable_premium_badges'     => 'yes',
		'_title'                    => 'Top Ten Slider',
	);
}

/**
 * @param array<string, mixed> $widget
 * @return bool
 */
function sc_is_ongoing_home_widget( array $widget ) {
	$type   = (string) ( $widget['widgetType'] ?? '' );
	$title  = (string) ( $widget['settings']['slider_title'] ?? '' );
	$filter = (string) ( $widget['settings']['st_select_post_filter'] ?? '' );
	if ( ! in_array( $type, array( 'main-card-slider', 'top-ten-slider' ), true ) ) {
		return false;
	}
	return in_array( $title, array( 'در حال پخش', 'درحال پخش' ), true ) || 'status' === $filter;
}

$live   = sc_load_elementor( SC_HOME_POST_ID );
$placed = '';
$walk   = function ( array &$nodes ) use ( &$walk, &$placed ) {
	foreach ( $nodes as &$n ) {
		if ( ( $n['id'] ?? '' ) === SC_CONTINUE_PARENT && isset( $n['elements'] ) && is_array( $n['elements'] ) ) {
			foreach ( $n['elements'] as &$child ) {
				if ( ! sc_is_ongoing_home_widget( $child ) ) {
					continue;
				}
				$child['widgetType'] = 'top-ten-slider';
				$child['elType']     = 'widget';
				$child['settings']   = sc_top_ten_ongoing_settings();
				$child['elements']   = array();
				$placed              = 'replaced:' . ( $child['id'] ?? '' );
				return;
			}
			unset( $child );

			$widget = array(
				'id'         => substr( md5( 'ongoing-top-ten-slider-v1' ), 0, 7 ),
				'elType'     => 'widget',
				'settings'   => sc_top_ten_ongoing_settings(),
				'elements'   => array(),
				'widgetType' => 'top-ten-slider',
			);
			$insert_at = count( $n['elements'] );
			if ( ! empty( $n['elements'] ) && 'st_continue_watching' === ( $n['elements'][0]['widgetType'] ?? '' ) ) {
				$insert_at = 1;
			}
			array_splice( $n['elements'], $insert_at, 0, array( $widget ) );
			$placed = 'inserted:' . $widget['id'];
			return;
		}
		if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
			$walk( $n['elements'] );
			if ( '' !== $placed ) {
				return;
			}
		}
	}
};
$walk( $live );

if ( '' === $placed ) {
	fwrite( STDERR, 'could not find container ' . SC_CONTINUE_PARENT . "\n" );
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

echo "ok {$placed}\n";
echo 'title=' . SC_SLIDER_TITLE . " widget=top-ten-slider filter=status status_term=" . SC_RETURNING_TERM . "\n";

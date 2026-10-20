<?php
/**
 * Add/update homepage Main Card Slider filtered by tv_status "Returning Series".
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
const SC_SLIDER_TITLE     = 'در حال پخش';
const SC_CONTINUE_PARENT  = '5006cb8';

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
 * @param array<int, mixed> $nodes
 * @param string            $id
 * @return array<string, mixed>|null
 */
function sc_find_widget( array &$nodes, $id ) {
	foreach ( $nodes as &$n ) {
		if ( ( $n['id'] ?? '' ) === $id ) {
			return $n;
		}
		if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
			$found = sc_find_widget( $n['elements'], $id );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

$live   = sc_load_elementor( SC_HOME_POST_ID );
$draft  = sc_load_elementor( 7263 );
$latest = sc_find_widget( $live, 'ca44334' );
$old    = sc_find_widget( $draft, '8b308ac' );

if ( ! $latest || ! is_array( $latest['settings'] ?? null ) ) {
	fwrite( STDERR, "missing latest slider template ca44334\n" );
	exit( 1 );
}

$settings                                 = $latest['settings'];
$settings['slider_title']                 = SC_SLIDER_TITLE;
$settings['st_select_content_type']       = 'tvshow';
$settings['st_select_post_filter']        = 'status';
$settings['st_selected_tvshow_status']    = array( SC_RETURNING_TERM );
$settings['st_selected_tvshows']          = array();
$settings['st_selected_tvshow_tag']       = array();
$settings['st_selected_tvshow_genre']     = array();
unset( $settings['st_selected_movie_genre'], $settings['st_selected_video_genre'] );

if ( is_array( $old ) && ! empty( $old['settings'] ) && is_array( $old['settings'] ) ) {
	foreach ( array( 'show_title', 'show_view_all', 'slider_style', 'card_style', 'st_iqonic_has_box_shadow', 'space_between', 'navigation', 'pagination' ) as $k ) {
		if ( array_key_exists( $k, $old['settings'] ) ) {
			$settings[ $k ] = $old['settings'][ $k ];
		}
	}
}

$new_id = substr( md5( 'ongoing-status-slider-v1' ), 0, 7 );
$widget = array(
	'id'         => $new_id,
	'elType'     => 'widget',
	'settings'   => $settings,
	'elements'   => array(),
	'widgetType' => 'main-card-slider',
);

$placed = '';
$walk   = function ( array &$nodes ) use ( &$walk, $widget, &$placed ) {
	foreach ( $nodes as &$n ) {
		if ( ( $n['id'] ?? '' ) === SC_CONTINUE_PARENT && isset( $n['elements'] ) && is_array( $n['elements'] ) ) {
			foreach ( $n['elements'] as &$child ) {
				if ( 'main-card-slider' !== ( $child['widgetType'] ?? '' ) ) {
					continue;
				}
				$title  = (string) ( $child['settings']['slider_title'] ?? '' );
				$filter = (string) ( $child['settings']['st_select_post_filter'] ?? '' );
				if ( in_array( $title, array( 'در حال پخش', 'درحال پخش' ), true ) || 'status' === $filter ) {
					$child['settings'] = $widget['settings'];
					$placed            = 'updated:' . ( $child['id'] ?? '' );
					return;
				}
			}
			unset( $child );

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
	fwrite( STDERR, "could not find container " . SC_CONTINUE_PARENT . "\n" );
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
echo 'title=' . SC_SLIDER_TITLE . " filter=status status_term=" . SC_RETURNING_TERM . "\n";

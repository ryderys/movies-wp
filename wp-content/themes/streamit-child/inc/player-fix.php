<?php
/**
 * Plyr player tweaks: 15s skip, Persian labels, scrollable watch layout.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adjust Plyr config passed to the front-end player.
 *
 * @param array $controls Plyr options from parent theme.
 * @return array
 */
function streamit_child_media_player_controls( $controls ) {
	$controls['seekTime'] = 15;

	$controls['i18n']['rewind']      = '۱۵ ثانیه عقب';
	$controls['i18n']['fastForward'] = '۱۵ ثانیه جلو';

	return $controls;
}
add_filter( 'streamit_media_player_controls', 'streamit_child_media_player_controls' );

/**
 * Enqueue player layout CSS on watch / episode pages.
 */
function streamit_child_enqueue_player_fix_styles() {
	if ( ! is_singular( array( 'movie', 'video', 'episode', 'tvshow' ) ) ) {
		return;
	}

	wp_enqueue_style(
		'streamit-child-player-fix',
		get_stylesheet_directory_uri() . '/assets/css/player-fix.css',
		array(),
		filemtime( get_stylesheet_directory() . '/assets/css/player-fix.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'streamit_child_enqueue_player_fix_styles', 100 );

<?php
/**
 * Movie single download section data.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build the Movie download section from Streamit source and subtitle metadata.
 *
 * Each source row: quality, encoder ('' when unknown), file_size, title
 * (tooltip meta) and href (gateway URL, '' when locked).
 *
 * @param object $st_data Movie Streamit object.
 * @return array{sources: array<int, array<string, mixed>>, subtitles: array<int, array<string, mixed>>, can_download: bool}
 */
function streamit_child_build_movie_download_catalog( $st_data ) {
	$catalog = array(
		'sources'      => array(),
		'subtitles'    => array(),
		'can_download' => false,
	);

	if ( ! is_object( $st_data ) || ! method_exists( $st_data, 'get_meta' ) || ! method_exists( $st_data, 'get_id' ) ) {
		return $catalog;
	}

	$movie_id               = (int) $st_data->get_id();
	$catalog['can_download'] = streamit_child_user_can_download( $st_data, 'movie' );

	foreach ( streamit_child_get_downloadable_sources( $st_data->get_meta( '_source' ) ) as $source ) {
		$meta = streamit_child_download_source_meta_values( $source );
		$catalog['sources'][] = array(
			'quality'   => $source['quality'],
			'encoder'   => streamit_child_download_source_encoder( $source ),
			'file_size' => $source['file_size'],
			'title'     => $source['quality'] . ( empty( $meta ) ? '' : ' · ' . implode( ' · ', $meta ) ),
			'href'      => $catalog['can_download'] && function_exists( 'streamit_child_resolve_download_href' )
				? streamit_child_resolve_download_href( $source['download_content'], $movie_id, $source['source_index'] )
				: '',
		);
	}

	foreach ( streamit_child_get_subtitles( $st_data ) as $subtitle ) {
		$href = $catalog['can_download'] && function_exists( 'streamit_child_resolve_subtitle_url' )
			? streamit_child_resolve_subtitle_url( $subtitle['url'], 'd' )
			: '';

		if ( $catalog['can_download'] && '' === $href ) {
			continue;
		}

		$catalog['subtitles'][] = array(
			'label' => $subtitle['label'],
			'href'  => $href,
		);
	}

	return $catalog;
}

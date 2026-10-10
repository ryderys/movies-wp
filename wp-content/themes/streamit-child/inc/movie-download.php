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

		$source_type = isset( $subtitle['source_type'] ) && is_string( $subtitle['source_type'] ) ? trim( $subtitle['source_type'] ) : '';
		if ( '' === $source_type && function_exists( 'streamit_child_infer_subtitle_source_type' ) ) {
			$source_type = streamit_child_infer_subtitle_source_type( (string) $subtitle['url'] );
		}

		$catalog['subtitles'][] = array(
			'label'       => $subtitle['label'],
			'srclang'     => isset( $subtitle['srclang'] ) ? (string) $subtitle['srclang'] : '',
			'url'         => $subtitle['url'],
			'format'      => isset( $subtitle['format'] ) ? (string) $subtitle['format'] : '',
			'source_type' => $source_type,
			'href'        => $href,
		);
	}

	return $catalog;
}

/**
 * Groups Movie subtitles for the download UI: release type → language.
 *
 * - Uses stored `source_type` when present.
 * - Safely infers release type from legacy stored paths when missing.
 * - Never invents values; unknown release types are grouped under "سایر نسخه‌ها".
 * - Never uses duplicate numbering to distinguish subtitle files.
 *
 * @param array<int, array<string, mixed>> $subtitles Catalog subtitles rows.
 * @return array<int, array{
 *   source_type: string,
 *   label: string,
 *   count: int,
 *   languages: array<int, array{label: string, items: array<int, array{label: string, href: string, title: string}>}>
 * }>
 */
function streamit_child_movie_download_subtitle_groups( array $subtitles ) {
	$unclassified_label = __( 'سایر نسخه‌ها', 'streamit' );

	$known_priority = array(
		'WEB-DL' => 10,
		'WEBRip' => 20,
		'HDTV'   => 30,
		'BluRay' => 40,
		'BDRip'  => 50,
		'BRRip'  => 60,
		'HDRip'  => 70,
		'DVDRip' => 80,
	);

	$by_source = array();

	foreach ( $subtitles as $sub ) {
		if ( ! is_array( $sub ) ) {
			continue;
		}
		$href = isset( $sub['href'] ) ? trim( (string) $sub['href'] ) : '';

		$source_type = isset( $sub['source_type'] ) && is_string( $sub['source_type'] ) ? trim( $sub['source_type'] ) : '';
		if ( '' === $source_type && ! empty( $sub['url'] ) && function_exists( 'streamit_child_infer_subtitle_source_type' ) ) {
			$source_type = streamit_child_infer_subtitle_source_type( (string) $sub['url'] );
		}

		$group_key   = '' !== $source_type ? $source_type : '__unclassified__';
		$group_label = '' !== $source_type ? $source_type : $unclassified_label;

		if ( ! isset( $by_source[ $group_key ] ) ) {
			$by_source[ $group_key ] = array(
				'source_type' => '' !== $source_type ? $source_type : '',
				'label'       => $group_label,
				'count'       => 0,
				'languages'   => array(),
			);
		}

		$lang_label = function_exists( 'streamit_child_subtitle_language_label' )
			? streamit_child_subtitle_language_label(
				isset( $sub['label'] ) ? $sub['label'] : '',
				isset( $sub['srclang'] ) ? $sub['srclang'] : ''
			)
			: ( __( 'زیرنویس', 'streamit' ) . ( '' !== (string) ( $sub['label'] ?? '' ) ? ' ' . (string) $sub['label'] : '' ) );

		$by_source[ $group_key ]['count']++;

		if ( ! isset( $by_source[ $group_key ]['languages'][ $lang_label ] ) ) {
			$by_source[ $group_key ]['languages'][ $lang_label ] = array(
				'label' => $lang_label,
				'items' => array(),
			);
		}

		$base = '';
		if ( ! empty( $sub['url'] ) && function_exists( 'streamit_child_subtitle_display_basename' ) ) {
			$base = streamit_child_subtitle_display_basename( (string) $sub['url'] );
		}
		$title = $lang_label;
		if ( '' !== $base ) {
			$title .= ' · ' . $base;
		}

		$by_source[ $group_key ]['languages'][ $lang_label ]['items'][] = array(
			'label' => '' !== $base ? ( $lang_label . ' · ' . $base ) : $lang_label,
			'href'  => $href,
			'title' => $title,
		);
	}

	$groups = array_values( $by_source );

	// Sort release groups (known first), unclassified last.
	usort(
		$groups,
		static function ( array $a, array $b ) use ( $known_priority ): int {
			$a_type = (string) ( $a['source_type'] ?? '' );
			$b_type = (string) ( $b['source_type'] ?? '' );
			$a_weight = '' === $a_type ? 999 : ( $known_priority[ $a_type ] ?? 100 );
			$b_weight = '' === $b_type ? 999 : ( $known_priority[ $b_type ] ?? 100 );
			if ( $a_weight !== $b_weight ) {
				return $a_weight <=> $b_weight;
			}
			return strcasecmp( (string) $a['label'], (string) $b['label'] );
		}
	);

	// Normalize language arrays for output.
	foreach ( $groups as &$group ) {
		$languages = array_values( $group['languages'] );
		usort(
			$languages,
			static function ( array $a, array $b ): int {
				return strcasecmp( (string) $a['label'], (string) $b['label'] );
			}
		);
		$group['languages'] = $languages;
	}
	unset( $group );

	return $groups;
}

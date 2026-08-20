<?php
/**
 * Series single: season → episode download catalog (child-only).
 *
 * Builds UI data from TV `_seasons` + episode `_sources` / `_subtitles`.
 * Does not change the download gateway or Movie templates.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Format an episode code like S01E03 from season/episode meta.
 *
 * @param mixed $season_number Digit string or empty.
 * @param mixed $episode_number "E03", "3", etc.
 * @return string
 */
function streamit_child_format_series_episode_label( $season_number, $episode_number ) {
	$season_raw  = trim( (string) $season_number );
	$episode_raw = trim( (string) $episode_number );

	$episode_num = null;
	if ( preg_match( '/^E?0*(\d+)$/i', $episode_raw, $m ) ) {
		$episode_num = (int) $m[1];
	}

	$season_num = null;
	if ( '' !== $season_raw && preg_match( '/^\d+$/', $season_raw ) ) {
		$season_num = (int) $season_raw;
	}

	if ( null === $episode_num && null === $season_num ) {
		return '';
	}

	if ( null === $season_num ) {
		return sprintf( 'E%02d', (int) $episode_num );
	}

	if ( null === $episode_num ) {
		return sprintf( 'S%02d', $season_num );
	}

	return sprintf( 'S%02dE%02d', $season_num, $episode_num );
}

/**
 * Numeric episode ordinal for compact UI labels (دانلود قسمت N).
 *
 * Prefers the E## portion of an SxxExx label; otherwise uses $fallback.
 *
 * @param string $label    Episode code e.g. S01E03.
 * @param int    $fallback 1-based fallback when label has no episode digit.
 * @return int
 */
function streamit_child_series_download_episode_ordinal( $label, $fallback = 0 ) {
	$label = trim( (string) $label );
	if ( preg_match( '/E0*(\d+)/i', $label, $m ) ) {
		return (int) $m[1];
	}

	return max( 0, (int) $fallback );
}

/**
 * Lean episode payload for the Series download UI (JSON → shared detail panel).
 *
 * Does not change gateway URLs; only reshapes catalog rows for the frontend.
 *
 * @param array<string, mixed> $episode      Catalog episode row.
 * @param int                  $fallback_ord 1-based fallback ordinal.
 * @return array<string, mixed>
 */
function streamit_child_series_download_episode_ui_payload( array $episode, $fallback_ord = 0 ) {
	$label   = isset( $episode['label'] ) ? (string) $episode['label'] : '';
	$ordinal = streamit_child_series_download_episode_ordinal( $label, $fallback_ord );

	$sources = array();
	if ( ! empty( $episode['sources'] ) && is_array( $episode['sources'] ) ) {
		foreach ( $episode['sources'] as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$quality = isset( $source['quality'] ) ? (string) $source['quality'] : '';
			if ( '' === $quality ) {
				continue;
			}
			$meta  = function_exists( 'streamit_child_download_source_meta_values' )
				? streamit_child_download_source_meta_values( $source )
				: array();
			$title = $quality;
			if ( ! empty( $meta ) ) {
				$title .= ' · ' . implode( ' · ', $meta );
			}
			$sources[] = array(
				'quality' => $quality,
				'href'    => isset( $source['href'] ) ? (string) $source['href'] : '',
				'title'   => $title,
			);
		}
	}

	$subtitles = array();
	if ( ! empty( $episode['subtitles'] ) && is_array( $episode['subtitles'] ) ) {
		foreach ( $episode['subtitles'] as $sub ) {
			if ( ! is_array( $sub ) ) {
				continue;
			}
			$subtitles[] = array(
				'label' => isset( $sub['label'] ) ? (string) $sub['label'] : '',
				'href'  => isset( $sub['href'] ) ? (string) $sub['href'] : '',
			);
		}
	}

	return array(
		'id'           => isset( $episode['id'] ) ? (int) $episode['id'] : 0,
		'ordinal'      => $ordinal,
		'label'        => $label,
		'title'        => isset( $episode['title'] ) ? (string) $episode['title'] : '',
		'has_download' => ! empty( $episode['has_download'] ),
		'sources'      => $sources,
		'subtitles'    => $subtitles,
	);
}

/**
 * Whether the current user may download media for this TV show.
 *
 * @param object   $st_data TV show Streamit object.
 * @param int|null $user_id Optional user id.
 * @return bool
 */
function streamit_child_user_can_download_series( $st_data, $user_id = null ) {
	$user_id = null !== $user_id ? (int) $user_id : get_current_user_id();

	if ( function_exists( 'movies_wp_user_can_access_media' ) ) {
		return (bool) movies_wp_user_can_access_media( $user_id );
	}

	if ( ! $st_data || ! is_object( $st_data ) || ! method_exists( $st_data, 'get_id' ) ) {
		return false;
	}

	if ( ! function_exists( 'streamit_user_has_stream_access' ) ) {
		return true;
	}

	return (bool) streamit_user_has_stream_access( (int) $st_data->get_id(), 'tvshow', $user_id );
}

/**
 * Build season download catalog from plain data (testable).
 *
 * Episode map keys are episode post IDs. Each value:
 * - title (string)
 * - sources (raw _sources)
 * - subtitles (raw _subtitles)
 * - season_number / episode_number (optional meta)
 * - permalink (optional)
 *
 * Seasons with zero downloadable episodes are omitted.
 *
 * @param array<int, mixed>                $seasons        Raw `_seasons` rows.
 * @param array<int, array<string, mixed>> $episodes_by_id Episode payload by ID.
 * @param bool                             $can_download   Whether to mint gateway hrefs.
 * @return array{seasons: array<int, array<string, mixed>>, can_download: bool}
 */
function streamit_child_build_series_download_catalog_from_data( array $seasons, array $episodes_by_id, $can_download = true ) {
	$can_download = (bool) $can_download;
	$out_seasons  = array();

	foreach ( array_values( $seasons ) as $index => $season ) {
		if ( ! is_array( $season ) ) {
			continue;
		}

		$name          = isset( $season['name'] ) ? trim( (string) $season['name'] ) : '';
		$season_number = isset( $season['season_number'] ) ? trim( (string) $season['season_number'] ) : '';
		$episode_ids   = ( isset( $season['episodes'] ) && is_array( $season['episodes'] ) ) ? $season['episodes'] : array();

		$episode_rows         = array();
		$downloadable_count   = 0;

		foreach ( $episode_ids as $episode_id ) {
			$episode_id = (int) $episode_id;
			if ( $episode_id <= 0 || ! isset( $episodes_by_id[ $episode_id ] ) || ! is_array( $episodes_by_id[ $episode_id ] ) ) {
				continue;
			}

			$ep             = $episodes_by_id[ $episode_id ];
			$raw_sources    = isset( $ep['sources'] ) ? $ep['sources'] : array();
			$raw_subtitles  = isset( $ep['subtitles'] ) ? $ep['subtitles'] : array();
			$sources        = function_exists( 'streamit_child_get_downloadable_sources' )
				? streamit_child_get_downloadable_sources( $raw_sources )
				: array();
			$subs           = function_exists( 'streamit_child_normalize_subtitles' )
				? streamit_child_normalize_subtitles( $raw_subtitles )
				: array();

			$source_rows = array();
			foreach ( $sources as $source ) {
				$href = '';
				if ( $can_download && function_exists( 'streamit_child_resolve_download_href' ) ) {
					$href = streamit_child_resolve_download_href(
						isset( $source['download_content'] ) ? $source['download_content'] : '',
						$episode_id,
						isset( $source['source_index'] ) ? (int) $source['source_index'] : 0
					);
				}

				$row         = $source;
				$row['href'] = $href;
				$source_rows[] = $row;
			}

			$subtitle_rows = array();
			foreach ( $subs as $sub ) {
				$href = '';
				if ( $can_download && function_exists( 'streamit_child_resolve_subtitle_url' ) ) {
					$href = streamit_child_resolve_subtitle_url( isset( $sub['url'] ) ? $sub['url'] : '', 'd' );
				}

				if ( $can_download && '' === $href ) {
					continue;
				}

				$subtitle_rows[] = array(
					'label' => isset( $sub['label'] ) ? (string) $sub['label'] : '',
					'href'  => $href,
					'meta'  => function_exists( 'streamit_child_subtitle_download_meta_values' )
						? streamit_child_subtitle_download_meta_values( $sub )
						: array(),
				);
			}

			$has_download = ! empty( $source_rows ) || ! empty( $subtitle_rows );
			if ( $has_download ) {
				$downloadable_count++;
			}

			$ep_season  = isset( $ep['season_number'] ) ? $ep['season_number'] : $season_number;
			$ep_episode = isset( $ep['episode_number'] ) ? $ep['episode_number'] : '';

			$episode_rows[] = array(
				'id'           => $episode_id,
				'title'        => isset( $ep['title'] ) ? trim( (string) $ep['title'] ) : '',
				'label'        => streamit_child_format_series_episode_label( $ep_season, $ep_episode ),
				'permalink'    => isset( $ep['permalink'] ) ? (string) $ep['permalink'] : '',
				'sources'      => $source_rows,
				'subtitles'    => $subtitle_rows,
				'has_download' => $has_download,
			);
		}

		if ( $downloadable_count < 1 ) {
			continue;
		}

		$out_seasons[] = array(
			'index'                      => (int) $index,
			'name'                       => $name,
			'season_number'              => $season_number,
			'episode_count'              => count( $episode_rows ),
			'downloadable_episode_count' => $downloadable_count,
			'episodes'                   => $episode_rows,
		);
	}

	return array(
		'seasons'      => $out_seasons,
		'can_download' => $can_download,
	);
}

/**
 * Load episode payloads for catalog building.
 *
 * @param array<int, int|string> $episode_ids Episode IDs.
 * @return array<int, array<string, mixed>>
 */
function streamit_child_load_series_download_episode_map( array $episode_ids ) {
	$map = array();
	$ids = array_values(
		array_unique(
			array_filter(
				array_map( 'absint', $episode_ids )
			)
		)
	);

	if ( empty( $ids ) ) {
		return $map;
	}

	$episodes = array();
	if ( function_exists( 'streamit_get_episodes' ) ) {
		$result = streamit_get_episodes(
			array(
				'orderby'  => 'post__in',
				'include'  => $ids,
				'paged'    => 1,
				'per_page' => -1,
			)
		);
		if ( is_object( $result ) && ! empty( $result->results ) && is_array( $result->results ) ) {
			$episodes = $result->results;
		}
	}

	foreach ( $episodes as $episode ) {
		if ( ! is_object( $episode ) || ! method_exists( $episode, 'get_id' ) ) {
			continue;
		}

		$id = (int) $episode->get_id();
		if ( $id <= 0 ) {
			continue;
		}

		$title = '';
		if ( method_exists( $episode, 'get_post_title' ) ) {
			$title = (string) $episode->get_post_title();
		}

		$permalink = '';
		if ( method_exists( $episode, 'get_permalink' ) ) {
			$permalink = (string) $episode->get_permalink();
		} elseif ( function_exists( 'get_permalink' ) ) {
			$permalink = (string) get_permalink( $id );
		}

		$sources   = method_exists( $episode, 'get_meta' ) ? $episode->get_meta( '_sources' ) : array();
		$subtitles = method_exists( $episode, 'get_meta' ) ? $episode->get_meta( '_subtitles' ) : array();
		$season_n  = method_exists( $episode, 'get_meta' ) ? $episode->get_meta( '_season_number' ) : '';
		$episode_n = method_exists( $episode, 'get_meta' ) ? $episode->get_meta( '_episode_number' ) : '';

		$map[ $id ] = array(
			'title'          => $title,
			'permalink'      => $permalink,
			'sources'        => is_array( $sources ) ? $sources : array(),
			'subtitles'      => $subtitles,
			'season_number'  => $season_n,
			'episode_number' => $episode_n,
		);
	}

	return $map;
}

/**
 * Build the Series single download catalog from a TV show object.
 *
 * @param object $st_data TV show Streamit object.
 * @return array{seasons: array<int, array<string, mixed>>, can_download: bool}
 */
function streamit_child_build_series_download_catalog( $st_data ) {
	$empty = array(
		'seasons'      => array(),
		'can_download' => false,
	);

	if ( ! $st_data || ! is_object( $st_data ) || ! method_exists( $st_data, 'get_meta' ) ) {
		return $empty;
	}

	$seasons = $st_data->get_meta( '_seasons' );
	if ( ! is_array( $seasons ) || empty( $seasons ) ) {
		return $empty;
	}

	$episode_ids = array();
	foreach ( $seasons as $season ) {
		if ( ! is_array( $season ) || empty( $season['episodes'] ) || ! is_array( $season['episodes'] ) ) {
			continue;
		}
		foreach ( $season['episodes'] as $episode_id ) {
			$episode_ids[] = (int) $episode_id;
		}
	}

	$can_download = streamit_child_user_can_download_series( $st_data );
	$map          = streamit_child_load_series_download_episode_map( $episode_ids );

	return streamit_child_build_series_download_catalog_from_data( $seasons, $map, $can_download );
}

/**
 * Enqueue Series download accordion assets (call from the section template).
 */
function streamit_child_enqueue_series_download_assets() {
	$css_path = get_stylesheet_directory() . '/assets/css/series-download.css';
	$js_path  = get_stylesheet_directory() . '/assets/js/series-download.js';

	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'streamit-child-series-download',
			get_stylesheet_directory_uri() . '/assets/css/series-download.css',
			array(),
			(string) filemtime( $css_path )
		);
	}

	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'streamit-child-series-download',
			get_stylesheet_directory_uri() . '/assets/js/series-download.js',
			array(),
			(string) filemtime( $js_path ),
			true
		);
	}
}

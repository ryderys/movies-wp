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
 * Display-only TV episode file size: the stored size plus 23 MB.
 *
 * Call only when rendering — never persist the result, or the 23 MB would
 * accumulate. Parses "<number> <unit>" labels (B…PB, 1024-based like the media
 * scanner's media_format_size_label()) and re-formats them the same way
 * ("%.2f UNIT"). Empty, zero or unrecognised values are returned unchanged.
 * Movies must not use this.
 *
 * @param mixed $file_size Stored `_sources[].file_size`, e.g. "1.46 GB".
 * @return string
 */
function streamit_child_episode_display_file_size( $file_size ) {
	$raw   = (string) $file_size;
	$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );

	if ( ! preg_match( '/^\s*(\d+(?:\.\d+)?)\s*([KMGTP]?B)\s*$/i', $raw, $m ) || (float) $m[1] <= 0 ) {
		return $raw;
	}

	$power = (int) array_search( strtoupper( $m[2] ), $units, true );
	$value = (float) $m[1] * ( 1024 ** $power ) + 23 * 1024 * 1024;
	$index = 0;

	while ( $value >= 1024 && $index < count( $units ) - 1 ) {
		$value /= 1024;
		$index++;
	}

	return sprintf( '%.2f %s', $value, $units[ $index ] );
}

/**
 * Persian button label for one subtitle file.
 *
 * Automation stores the uppercased language code as the label ("ENG", "FA")
 * and "SUB" when the language is unknown; admins may type a free label.
 * Free labels are kept; code-like labels become a language name.
 *
 * @param string $label   Stored `_subtitles[].label`.
 * @param string $srclang Stored `_subtitles[].srclang`.
 * @return string
 */
function streamit_child_subtitle_language_label( $label, $srclang ) {
	$names = array(
		'fa'  => 'فارسی',
		'fas' => 'فارسی',
		'per' => 'فارسی',
		'en'  => 'انگلیسی',
		'eng' => 'انگلیسی',
		'ar'  => 'عربی',
		'ara' => 'عربی',
		'tr'  => 'ترکی',
		'tur' => 'ترکی',
		'ko'  => 'کره‌ای',
		'kor' => 'کره‌ای',
		'ja'  => 'ژاپنی',
		'jpn' => 'ژاپنی',
		'zh'  => 'چینی',
		'zho' => 'چینی',
		'chi' => 'چینی',
		'fr'  => 'فرانسوی',
		'fra' => 'فرانسوی',
		'fre' => 'فرانسوی',
		'de'  => 'آلمانی',
		'deu' => 'آلمانی',
		'ger' => 'آلمانی',
		'es'  => 'اسپانیایی',
		'spa' => 'اسپانیایی',
		'ru'  => 'روسی',
		'rus' => 'روسی',
	);
	$prefix  = __( 'زیرنویس', 'streamit' );
	$label   = trim( (string) $label );
	$lower   = strtolower( $label );
	$code    = strtolower( trim( (string) $srclang ) );
	$generic = array( '', 'sub', 'subs', 'subtitle', 'subtitles', strtolower( $prefix ) );

	$code_like = in_array( $lower, $generic, true ) || $lower === $code || preg_match( '/^[a-z]{2,3}$/', $lower );
	if ( ! $code_like ) {
		return false !== strpos( $label, $prefix ) ? $label : $prefix . ' ' . $label;
	}

	if ( '' === $code && ! in_array( $lower, $generic, true ) ) {
		$code = $lower;
	}
	if ( isset( $names[ $code ] ) ) {
		return $prefix . ' ' . $names[ $code ];
	}

	return '' !== $code ? $prefix . ' ' . strtoupper( $code ) : $prefix;
}

/**
 * Regroup one catalog season's episodes into quality variants for the UI.
 *
 * A variant is quality + encoder. Each episode appears at most once per variant
 * (first matching source wins), so a variant only lists episodes that really
 * have that file. Episodes with subtitles but no video source are collected in
 * a trailing group whose quality is ''.
 *
 * Episode rows: ordinal, label (S01E03), title, href (gateway, '' when locked),
 * file_size, subtitles [{label (Persian, unique per episode), href}].
 *
 * @param array<int, mixed> $episodes Catalog season `episodes`.
 * @return array<int, array{quality: string, encoder: string, link_count: int, episodes: array<int, array<string, mixed>>}>
 */
function streamit_child_series_download_quality_groups( array $episodes ) {
	$groups    = array();
	$subs_only = array();

	foreach ( array_values( $episodes ) as $i => $episode ) {
		if ( ! is_array( $episode ) || empty( $episode['has_download'] ) ) {
			continue;
		}

		$label = isset( $episode['label'] ) ? (string) $episode['label'] : '';

		$subtitles   = array();
		if ( ! empty( $episode['subtitles'] ) && is_array( $episode['subtitles'] ) ) {
			foreach ( $episode['subtitles'] as $sub ) {
				if ( ! is_array( $sub ) ) {
					continue;
				}
				$sub_label = streamit_child_subtitle_language_label(
					isset( $sub['label'] ) ? $sub['label'] : '',
					isset( $sub['srclang'] ) ? $sub['srclang'] : ''
				);
				$subtitles[] = array(
					'label'       => $sub_label,
					'srclang'     => isset( $sub['srclang'] ) ? (string) $sub['srclang'] : '',
					'source_type' => isset( $sub['source_type'] ) ? (string) $sub['source_type'] : '',
					'href'        => isset( $sub['href'] ) ? (string) $sub['href'] : '',
				);
			}
		}

		$base = array(
			'ordinal'   => streamit_child_series_download_episode_ordinal( $label, $i + 1 ),
			'label'     => $label,
			'title'     => isset( $episode['title'] ) ? (string) $episode['title'] : '',
			'subtitles' => $subtitles,
		);

		$seen    = array();
		$sources = ( ! empty( $episode['sources'] ) && is_array( $episode['sources'] ) ) ? $episode['sources'] : array();
		foreach ( $sources as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$quality = isset( $source['quality'] ) ? trim( (string) $source['quality'] ) : '';
			if ( '' === $quality ) {
				continue;
			}
			$encoder = streamit_child_download_source_encoder( $source );
			$key     = $quality . "\0" . $encoder;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'quality'    => $quality,
					'encoder'    => $encoder,
					'link_count' => 0,
					'episodes'   => array(),
				);
			}

			$href = isset( $source['href'] ) ? (string) $source['href'] : '';

			$groups[ $key ]['episodes'][] = $base + array(
				'href'      => $href,
				'file_size' => isset( $source['file_size'] ) ? streamit_child_episode_display_file_size( $source['file_size'] ) : '',
			);
			if ( '' !== $href ) {
				$groups[ $key ]['link_count']++;
			}
		}

		if ( empty( $seen ) && ! empty( $subtitles ) ) {
			$subs_only[] = $base + array(
				'href'      => '',
				'file_size' => '',
			);
		}
	}

	$out = array_values( $groups );
	if ( ! empty( $subs_only ) ) {
		$out[] = array(
			'quality'    => '',
			'encoder'    => '',
			'link_count' => 0,
			'episodes'   => $subs_only,
		);
	}

	return $out;
}

/**
 * Regroup one catalog season's subtitles by release type (source_type),
 * then by episode, then by language.
 *
 * Safe release types: WEB-DL, WEBRip, HDTV, BluRay, BDRip, BRRip, HDRip, DVDRip, etc.
 * Subtitles with unestablished release types are placed into an unclassified group.
 * Language labels never use duplicate numbering.
 *
 * @param array<int, mixed> $episodes Catalog season `episodes`.
 * @return array<int, array{
 *   source_type: string,
 *   label: string,
 *   count: int,
 *   episodes: array<int, array{
 *     id: int,
 *     ordinal: int,
 *     label: string,
 *     title: string,
 *     subtitles: array<int, array{label: string, srclang: string, href: string, format: string, source_type: string}>
 *   }>
 * }>
 */
function streamit_child_series_download_subtitle_groups( array $episodes ) {
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

	// Collect by source_type -> episode_id
	$by_source = array();

	foreach ( array_values( $episodes ) as $i => $episode ) {
		if ( ! is_array( $episode ) || empty( $episode['subtitles'] ) || ! is_array( $episode['subtitles'] ) ) {
			continue;
		}

		$ep_id    = isset( $episode['id'] ) ? (int) $episode['id'] : $i + 1;
		$ep_label = isset( $episode['label'] ) ? (string) $episode['label'] : '';
		$ep_ord   = streamit_child_series_download_episode_ordinal( $ep_label, $i + 1 );
		$ep_title = isset( $episode['title'] ) ? (string) $episode['title'] : '';

		foreach ( $episode['subtitles'] as $sub ) {
			if ( ! is_array( $sub ) ) {
				continue;
			}
			$href = isset( $sub['href'] ) ? trim( (string) $sub['href'] ) : '';
			// Even if href is empty (e.g. locked access), the subtitle exists in the catalog.
			$source_type = isset( $sub['source_type'] ) && is_string( $sub['source_type'] ) ? trim( $sub['source_type'] ) : '';
			if ( '' === $source_type && ! empty( $sub['url'] ) && function_exists( 'streamit_child_infer_subtitle_source_type' ) ) {
				$source_type = streamit_child_infer_subtitle_source_type( $sub['url'] );
			}

			$group_key   = '' !== $source_type ? $source_type : '__unclassified__';
			$group_label = '' !== $source_type ? $source_type : $unclassified_label;

			if ( ! isset( $by_source[ $group_key ] ) ) {
				$by_source[ $group_key ] = array(
					'source_type' => '' !== $source_type ? $source_type : '',
					'label'       => $group_label,
					'count'       => 0,
					'episodes'    => array(),
				);
			}

			$by_source[ $group_key ]['count']++;

			if ( ! isset( $by_source[ $group_key ]['episodes'][ $ep_id ] ) ) {
				$by_source[ $group_key ]['episodes'][ $ep_id ] = array(
					'id'        => $ep_id,
					'ordinal'   => $ep_ord,
					'label'     => $ep_label,
					'title'     => $ep_title,
					'subtitles' => array(),
				);
			}

			$lang_label = streamit_child_subtitle_language_label(
				isset( $sub['label'] ) ? $sub['label'] : '',
				isset( $sub['srclang'] ) ? $sub['srclang'] : ''
			);

			$format = isset( $sub['format'] ) ? trim( (string) $sub['format'] ) : '';
			if ( '' === $format && ! empty( $sub['meta']['format'] ) ) {
				$format = trim( (string) $sub['meta']['format'] );
			}

			$by_source[ $group_key ]['episodes'][ $ep_id ]['subtitles'][] = array(
				'label'       => $lang_label,
				'srclang'     => isset( $sub['srclang'] ) ? (string) $sub['srclang'] : '',
				'href'        => $href,
				'format'      => $format,
				'source_type' => $source_type,
			);
		}
	}

	if ( empty( $by_source ) ) {
		return array();
	}

	// Sort episodes within each group by ordinal
	foreach ( $by_source as &$group ) {
		$eps = array_values( $group['episodes'] );
		usort(
			$eps,
			static function ( array $a, array $b ): int {
				$ord_cmp = (int) $a['ordinal'] <=> (int) $b['ordinal'];
				if ( 0 !== $ord_cmp ) {
					return $ord_cmp;
				}
				return (int) $a['id'] <=> (int) $b['id'];
			}
		);
		$group['episodes'] = $eps;
	}
	unset( $group );

	// Sort release groups: WEB-DL first, HDTV next, etc., unclassified last
	$group_list = array_values( $by_source );
	usort(
		$group_list,
		static function ( array $a, array $b ) use ( $known_priority ): int {
			$a_type = (string) ( $a['source_type'] ?? '' );
			$b_type = (string) ( $b['source_type'] ?? '' );

			$a_weight = '' === $a_type ? 999 : ( $known_priority[ $a_type ] ?? 100 );
			$b_weight = '' === $b_type ? 999 : ( $known_priority[ $b_type ] ?? 100 );

			if ( $a_weight !== $b_weight ) {
				return $a_weight <=> $b_weight;
			}

			return strcasecmp( $a['label'], $b['label'] );
		}
	);

	return $group_list;
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
 * @return array{seasons: array<int, array<string, mixed>>, ost: array<int, array<string, mixed>>, can_download: bool}
 */
function streamit_child_build_series_download_catalog_from_data( array $seasons, array $episodes_by_id, $can_download = true, $st_data = null ) {
	$can_download = (bool) $can_download;
	$out_seasons  = array();

	foreach ( array_values( $seasons ) as $index => $season ) {
		if ( ! is_array( $season ) ) {
			continue;
		}

		$name          = isset( $season['name'] ) ? trim( (string) $season['name'] ) : '';
		$season_number = isset( $season['season_number'] ) ? trim( (string) $season['season_number'] ) : '';
		// Streamit stores this key as "sesion_" (typo retained for compatibility).
		$is_upcoming   = ! empty( $season['sesion_upcoming_status'] ) && '0' !== (string) $season['sesion_upcoming_status'];
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

				$source_type = isset( $sub['source_type'] ) && is_string( $sub['source_type'] ) ? trim( $sub['source_type'] ) : '';
				if ( '' === $source_type && ! empty( $sub['url'] ) && function_exists( 'streamit_child_infer_subtitle_source_type' ) ) {
					$source_type = streamit_child_infer_subtitle_source_type( $sub['url'] );
				}

				$subtitle_rows[] = array(
					'label'       => isset( $sub['label'] ) ? (string) $sub['label'] : '',
					'srclang'     => isset( $sub['srclang'] ) ? (string) $sub['srclang'] : '',
					'url'         => isset( $sub['url'] ) ? (string) $sub['url'] : '',
					'format'      => isset( $sub['format'] ) ? (string) $sub['format'] : '',
					'source_type' => $source_type,
					'href'        => $href,
					'meta'        => function_exists( 'streamit_child_subtitle_download_meta_values' )
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
			'is_upcoming'                => $is_upcoming,
			'episode_count'              => count( $episode_rows ),
			'downloadable_episode_count' => $downloadable_count,
			'episodes'                   => $episode_rows,
		);
	}

	$ost = array();
	if ( null !== $st_data && function_exists( 'streamit_child_build_ost_download_rows' ) ) {
		$ost = streamit_child_build_ost_download_rows( $st_data, $can_download );
	}

	return array(
		'seasons'      => $out_seasons,
		'ost'          => $ost,
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
				array_map( static function ( $id ) {
					return abs( (int) $id );
				}, $episode_ids )
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
		if ( empty( $sources ) && method_exists( $episode, 'get_meta' ) ) {
			$sources = $episode->get_meta( '_source' );
		}
		if ( empty( $sources ) && method_exists( $episode, 'get_meta' ) ) {
			$url_link = trim( (string) $episode->get_meta( 'episode_url_link' ) );
			if ( '' !== $url_link ) {
				$sources = array(
					array(
						'quality'          => 'Default',
						'download_content' => $url_link,
						'link'             => $url_link,
					),
				);
			}
		}

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
			'object'         => $episode,
		);
	}

	return $map;
}

/**
 * Check whether an episode has valid video sources.
 *
 * @param array<string, mixed> $ep Episode map entry.
 * @return bool
 */
function streamit_child_episode_has_video( array $ep ) {
	$raw_sources = isset( $ep['sources'] ) && is_array( $ep['sources'] ) ? $ep['sources'] : array();
	$sources     = function_exists( 'streamit_child_get_downloadable_sources' )
		? streamit_child_get_downloadable_sources( $raw_sources )
		: array();

	if ( ! empty( $sources ) ) {
		return true;
	}

	// Fallback if streamit_child_get_downloadable_sources is not loaded.
	foreach ( $raw_sources as $source ) {
		if ( ! is_array( $source ) ) {
			continue;
		}
		$quality  = isset( $source['quality'] ) ? trim( (string) $source['quality'] ) : '';
		$download = isset( $source['download_content'] ) ? trim( (string) $source['download_content'] ) : '';
		$link     = isset( $source['link'] ) ? trim( (string) $source['link'] ) : '';
		if ( '' !== $quality && ( '' !== $download || '' !== $link ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check whether an episode has available media (video or subtitles).
 *
 * Matches the download box rule: an episode is considered to have media
 * if it has at least one valid video source OR at least one valid subtitle.
 *
 * @param array<string, mixed> $ep Episode map entry.
 * @return bool
 */
function streamit_child_episode_has_media( array $ep ) {
	if ( streamit_child_episode_has_video( $ep ) ) {
		return true;
	}

	$raw_subtitles = isset( $ep['subtitles'] ) && is_array( $ep['subtitles'] ) ? $ep['subtitles'] : array();
	$subs          = function_exists( 'streamit_child_normalize_subtitles' )
		? streamit_child_normalize_subtitles( $raw_subtitles )
		: array();

	if ( ! empty( $subs ) ) {
		return true;
	}

	// Fallback if streamit_child_normalize_subtitles is not loaded.
	foreach ( $raw_subtitles as $sub ) {
		if ( is_array( $sub ) && ! empty( $sub['url'] ) && '' !== trim( (string) $sub['url'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Filter raw `_seasons` array based on media availability from an episode map.
 *
 * Retains only seasons that have at least one available episode.
 * In each retained season, `episodes` is filtered to contain ONLY the available episode IDs.
 * Preserves the original `season_number` and all other metadata on each season.
 * Re-indexes array sequentially (0, 1, 2...) for tab slider compatibility.
 *
 * @param array<int, mixed>                $seasons        Raw `_seasons` rows.
 * @param array<int, array<string, mixed>> $episodes_by_id Episode map by ID.
 * @param array<string, mixed>             $options        Filter options ('require_video' => bool).
 * @return array<int, array<string, mixed>> Filtered available seasons.
 */
function streamit_child_filter_available_seasons_data( array $seasons, array $episodes_by_id, array $options = array() ) {
	$require_video     = ! empty( $options['require_video'] );
	$available_seasons = array();

	foreach ( array_values( $seasons ) as $season ) {
		if ( ! is_array( $season ) ) {
			continue;
		}

		$episode_ids = isset( $season['episodes'] ) && is_array( $season['episodes'] ) ? $season['episodes'] : array();
		if ( empty( $episode_ids ) ) {
			continue;
		}

		$available_episode_ids = array();
		foreach ( $episode_ids as $episode_id ) {
			$episode_id = (int) $episode_id;
			if ( $episode_id <= 0 || ! isset( $episodes_by_id[ $episode_id ] ) ) {
				continue;
			}

			$ep = $episodes_by_id[ $episode_id ];
			if ( ! is_array( $ep ) ) {
				continue;
			}

			$has_media = $require_video
				? streamit_child_episode_has_video( $ep )
				: streamit_child_episode_has_media( $ep );

			if ( $has_media ) {
				$available_episode_ids[] = $episode_id;
			}
		}

		if ( empty( $available_episode_ids ) ) {
			continue;
		}

		$season_copy             = $season;
		$season_copy['episodes'] = $available_episode_ids;
		$available_seasons[]     = $season_copy;
	}

	return $available_seasons;
}

/**
 * Retrieve available seasons for a TV show, filtered to seasons and episodes with media.
 *
 * @param mixed                $st_data_or_id TV show Streamit object, WP_Post, or post ID.
 * @param array<string, mixed> $options       Filter options ('require_video' => bool).
 * @return array<int, array<string, mixed>> Available seasons with filtered episode IDs.
 */
function streamit_child_get_available_seasons( $st_data_or_id, array $options = array() ) {
	static $cache = array();

	$st_data = null;
	$post_id = 0;

	if ( is_numeric( $st_data_or_id ) ) {
		$post_id = (int) $st_data_or_id;
		if ( function_exists( 'streamit_get_tvshow' ) ) {
			$st_data = streamit_get_tvshow( $post_id );
		}
	} elseif ( is_object( $st_data_or_id ) ) {
		$st_data = $st_data_or_id;
		if ( method_exists( $st_data, 'get_id' ) ) {
			$post_id = (int) $st_data->get_id();
		} elseif ( isset( $st_data->ID ) ) {
			$post_id = (int) $st_data->ID;
		}
	}

	$cache_key = $post_id ? ( $post_id . ':' . ( ! empty( $options['require_video'] ) ? 'v' : 'all' ) ) : '';
	if ( $cache_key && isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	$seasons = array();
	if ( is_object( $st_data ) && method_exists( $st_data, 'get_meta' ) ) {
		$seasons = $st_data->get_meta( '_seasons' );
	} elseif ( $post_id > 0 ) {
		if ( function_exists( 'streamit_get_tvshow_meta' ) ) {
			$seasons = streamit_get_tvshow_meta( $post_id, '_seasons', true );
		}
		if ( empty( $seasons ) && function_exists( 'get_post_meta' ) ) {
			$seasons = get_post_meta( $post_id, '_seasons', true );
		}
	}

	if ( ! is_array( $seasons ) || empty( $seasons ) ) {
		return array();
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

	if ( empty( $episode_ids ) ) {
		return array();
	}

	$map     = streamit_child_load_series_download_episode_map( $episode_ids );
	$results = streamit_child_filter_available_seasons_data( $seasons, $map, $options );

	if ( $cache_key ) {
		$cache[ $cache_key ] = $results;
	}

	return $results;
}

/**
 * Get the first genuinely available playable episode for a TV show.
 *
 * Prefers regular seasons (Season 1, 2, ...) over Season 0 ("Specials")
 * so the "Start Watching" button launches the story rather than behind-the-scenes clips.
 * Falls back to Season 0 only if no regular season has playable episodes.
 * Returns null if no playable episode is found.
 *
 * @param mixed $st_data_or_id TV show Streamit object, WP_Post, or post ID.
 * @return mixed Episode object, or null.
 */
function streamit_child_get_first_available_episode( $st_data_or_id ) {
	$available_seasons = streamit_child_get_available_seasons( $st_data_or_id, array( 'require_video' => true ) );
	if ( empty( $available_seasons ) ) {
		return null;
	}

	// Separate regular seasons from Season 0.
	$regular_seasons = array();
	$specials_season = null;

	foreach ( $available_seasons as $season ) {
		$season_num = isset( $season['season_number'] ) ? trim( (string) $season['season_number'] ) : '';
		if ( '0' === $season_num || 0 === $season_num ) {
			$specials_season = $season;
		} else {
			$regular_seasons[] = $season;
		}
	}

	// Prefer the first available regular season.
	$target_season = ! empty( $regular_seasons ) ? $regular_seasons[0] : $specials_season;
	if ( ! $target_season || empty( $target_season['episodes'] ) ) {
		return null;
	}

	$first_episode_id = (int) $target_season['episodes'][0];
	if ( $first_episode_id <= 0 ) {
		return null;
	}

	if ( function_exists( 'streamit_get_episode' ) ) {
		$ep = streamit_get_episode( $first_episode_id );
		if ( ! empty( $ep ) ) {
			return $ep;
		}
	}

	return null;
}

/**
 * Build the Series single download catalog from a TV show object.
 *
 * @param object $st_data TV show Streamit object.
 * @return array{seasons: array<int, array<string, mixed>>, ost: array<int, array<string, mixed>>, can_download: bool}
 */
function streamit_child_build_series_download_catalog( $st_data ) {
	$empty = array(
		'seasons'      => array(),
		'ost'          => array(),
		'can_download' => false,
	);

	if ( ! $st_data || ! is_object( $st_data ) || ! method_exists( $st_data, 'get_meta' ) ) {
		return $empty;
	}

	$seasons = $st_data->get_meta( '_seasons' );
	if ( ! is_array( $seasons ) || empty( $seasons ) ) {
		$ost = function_exists( 'streamit_child_build_ost_download_rows' )
			? streamit_child_build_ost_download_rows( $st_data, streamit_child_user_can_download_series( $st_data ) )
			: array();
		if ( empty( $ost ) ) {
			return $empty;
		}
		$empty['ost']          = $ost;
		$empty['can_download'] = streamit_child_user_can_download_series( $st_data );
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

	return streamit_child_build_series_download_catalog_from_data( $seasons, $map, $can_download, $st_data );
}

/**
 * Enqueue styles shared by the Movie and Series download sections.
 */
function streamit_child_enqueue_download_section_styles() {
	$css_path = get_stylesheet_directory() . '/assets/css/series-download.css';

	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'streamit-child-series-download',
			get_stylesheet_directory_uri() . '/assets/css/series-download.css',
			array(),
			(string) filemtime( $css_path )
		);
	}
}

/**
 * Enqueue Series download accordion assets (call from the section template).
 */
function streamit_child_enqueue_series_download_assets() {
	streamit_child_enqueue_download_section_styles();

	$js_path = get_stylesheet_directory() . '/assets/js/series-download.js';

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

<?php
/**
 * OST / soundtrack downloads for movie and series download boxes.
 *
 * Tracks stay on the media server. Paths are validated under the title
 * `_media_directory` + `/OST/` with an allowlisted audio extension, then
 * signed via the existing mint: type `d` (download) and `v` (inline play).
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Approved soundtrack extensions → MIME types.
 *
 * @return array<string, string>
 */
function streamit_child_ost_audio_types() {
	return array(
		'mp3'  => 'audio/mpeg',
		'flac' => 'audio/flac',
		'm4a'  => 'audio/mp4',
		'aac'  => 'audio/aac',
		'wav'  => 'audio/wav',
	);
}

/**
 * Normalize a relative media path (no leading slash, no traversal).
 *
 * @param string $stored Raw path.
 * @return string Empty when invalid.
 */
function streamit_child_normalize_ost_relative_path( $stored ) {
	$stored = str_replace( '\\', '/', trim( (string) $stored ) );
	if ( '' === $stored ) {
		return '';
	}

	if ( str_starts_with( $stored, '/data/' ) || str_contains( $stored, '/data/' ) ) {
		$stored = preg_replace( '#^.*?/data/#', '', $stored );
		$stored = ltrim( (string) $stored, '/' );
	}

	$stored = ltrim( $stored, '/' );
	if ( '' === $stored || false !== strpos( $stored, "\0" ) ) {
		return '';
	}

	if ( function_exists( 'movies_wp_media_normalize_relative_path' ) ) {
		$normalized = movies_wp_media_normalize_relative_path( $stored );
		if ( is_wp_error( $normalized ) ) {
			return '';
		}
		return (string) $normalized;
	}

	$parts = explode( '/', $stored );
	foreach ( $parts as $part ) {
		if ( '' === $part || '.' === $part || '..' === $part ) {
			return '';
		}
	}

	return implode( '/', $parts );
}

/**
 * Whether a relative path is a direct OST audio file under the title directory.
 *
 * Requires: `{title_dir}/OST/{filename}` with an allowlisted extension.
 *
 * @param string $title_directory Normalized `_media_directory`.
 * @param string $relative        Candidate relative path.
 * @return bool
 */
function streamit_child_is_allowed_ost_media_path( $title_directory, $relative ) {
	$title_directory = streamit_child_normalize_ost_relative_path( $title_directory );
	$relative        = streamit_child_normalize_ost_relative_path( $relative );
	if ( '' === $title_directory || '' === $relative ) {
		return false;
	}

	$prefix = $title_directory . '/OST/';
	if ( ! str_starts_with( strtolower( $relative ), strtolower( $prefix ) ) ) {
		return false;
	}

	$rest = substr( $relative, strlen( $prefix ) );
	if ( false === $rest || '' === $rest || false !== strpos( $rest, '/' ) ) {
		return false;
	}

	if ( str_starts_with( $rest, '.' ) ) {
		return false;
	}

	$ext = function_exists( 'streamit_child_media_extension' )
		? streamit_child_media_extension( $rest )
		: strtolower( (string) pathinfo( $rest, PATHINFO_EXTENSION ) );

	$types = streamit_child_ost_audio_types();
	return '' !== $ext && isset( $types[ $ext ] );
}

/**
 * Signed media URL for one OST path, or empty when locked/invalid.
 *
 * @param string $relative_path   Relative path under media root.
 * @param string $title_directory Title `_media_directory`.
 * @param string $type            `d` (download) or `v` (stream/play).
 * @return string
 */
function streamit_child_resolve_ost_media_url( $relative_path, $title_directory, $type = 'd' ) {
	$type = strtolower( (string) $type );
	if ( ! in_array( $type, array( 'd', 'v' ), true ) ) {
		return '';
	}

	if ( ! streamit_child_is_allowed_ost_media_path( $title_directory, $relative_path ) ) {
		return '';
	}

	$relative_path = streamit_child_normalize_ost_relative_path( $relative_path );
	if ( '' === $relative_path || ! function_exists( 'movies_wp_media_signed_url' ) ) {
		return '';
	}

	$signed = movies_wp_media_signed_url( $relative_path, $type );
	if ( is_wp_error( $signed ) ) {
		return '';
	}

	$signed = trim( (string) $signed );
	if ( '' === $signed || str_contains( $signed, '/data/' ) || str_starts_with( $signed, '/data' ) ) {
		return '';
	}

	return $signed;
}

/**
 * Signed download URL for one OST path (type `d` / attachment).
 *
 * @param string $relative_path Relative path under media root.
 * @param string $title_directory Title `_media_directory`.
 * @return string
 */
function streamit_child_resolve_ost_download_url( $relative_path, $title_directory ) {
	return streamit_child_resolve_ost_media_url( $relative_path, $title_directory, 'd' );
}

/**
 * Signed stream URL for one OST path (type `v` / inline playback).
 *
 * @param string $relative_path Relative path under media root.
 * @param string $title_directory Title `_media_directory`.
 * @return string
 */
function streamit_child_resolve_ost_stream_url( $relative_path, $title_directory ) {
	return streamit_child_resolve_ost_media_url( $relative_path, $title_directory, 'v' );
}

/**
 * Display basename for an OST track (safe for UI; never a full filesystem path).
 *
 * @param string $relative_path Relative media path.
 * @return string
 */
function streamit_child_ost_display_basename( $relative_path ) {
	$relative_path = streamit_child_normalize_ost_relative_path( $relative_path );
	if ( '' === $relative_path ) {
		return '';
	}
	$base = basename( $relative_path );
	return is_string( $base ) ? $base : '';
}

/**
 * Read stored `_ost` meta rows into normalized track descriptors.
 *
 * @param mixed  $raw              Meta value.
 * @param string $title_directory  `_media_directory`.
 * @return list<array{filename:string,media_path:string,size_label:string}>
 */
function streamit_child_normalize_ost_meta_tracks( $raw, $title_directory ) {
	$out = array();
	if ( ! is_array( $raw ) ) {
		return $out;
	}

	foreach ( $raw as $row ) {
		$path = '';
		$size = '';
		if ( is_string( $row ) ) {
			$path = $row;
		} elseif ( is_array( $row ) ) {
			if ( isset( $row['media_path'] ) ) {
				$path = (string) $row['media_path'];
			} elseif ( isset( $row['path'] ) ) {
				$path = (string) $row['path'];
			} elseif ( isset( $row['url'] ) ) {
				$path = (string) $row['url'];
			}
			if ( isset( $row['size_label'] ) && is_string( $row['size_label'] ) ) {
				$size = trim( $row['size_label'] );
			}
		}

		$path = streamit_child_normalize_ost_relative_path( $path );
		if ( ! streamit_child_is_allowed_ost_media_path( $title_directory, $path ) ) {
			continue;
		}

		$filename = streamit_child_ost_display_basename( $path );
		if ( '' === $filename ) {
			continue;
		}

		$out[ $path ] = array(
			'filename'   => $filename,
			'media_path' => $path,
			'size_label' => $size,
		);
	}

	$tracks = array_values( $out );
	usort(
		$tracks,
		static function ( $a, $b ) {
			return strcasecmp( (string) ( $a['filename'] ?? '' ), (string) ( $b['filename'] ?? '' ) );
		}
	);

	return $tracks;
}

/**
 * Discover OST tracks from a media-server scan response's files list.
 *
 * @param mixed  $files
 * @param string $title_directory
 * @return list<array{filename:string,media_path:string,size_label:string}>
 */
function streamit_child_ost_tracks_from_scan_files( $files, $title_directory ) {
	$out = array();
	if ( ! is_array( $files ) ) {
		return $out;
	}

	foreach ( $files as $file ) {
		if ( ! is_array( $file ) ) {
			continue;
		}
		if ( isset( $file['kind'] ) && 'ost' !== $file['kind'] ) {
			continue;
		}
		$path = isset( $file['media_path'] ) ? (string) $file['media_path'] : '';
		$path = streamit_child_normalize_ost_relative_path( $path );
		if ( ! streamit_child_is_allowed_ost_media_path( $title_directory, $path ) ) {
			continue;
		}
		$filename = streamit_child_ost_display_basename( $path );
		if ( '' === $filename ) {
			continue;
		}
		$size = isset( $file['size_label'] ) && is_string( $file['size_label'] ) ? trim( $file['size_label'] ) : '';
		$out[ $path ] = array(
			'filename'   => $filename,
			'media_path' => $path,
			'size_label' => $size,
		);
	}

	$tracks = array_values( $out );
	usort(
		$tracks,
		static function ( $a, $b ) {
			return strcasecmp( (string) ( $a['filename'] ?? '' ), (string) ( $b['filename'] ?? '' ) );
		}
	);

	return $tracks;
}

/**
 * Action Scheduler hook that runs OST media-server discovery.
 */
const STREAMIT_CHILD_OST_DISCOVER_HOOK = 'streamit_child_ost_discover';

/**
 * Action Scheduler group for OST discovery jobs.
 */
const STREAMIT_CHILD_OST_DISCOVER_GROUP = 'streamit-child-ost';

/**
 * Positive OST result TTL for a successful scan that found tracks.
 *
 * @return int Seconds.
 */
function streamit_child_ost_positive_ttl() {
	return 15 * MINUTE_IN_SECONDS;
}

/**
 * TTL for a successful scan that found zero OST files.
 *
 * Kept short so adding an OST/ folder later is not stuck behind a long empty
 * cache. Matches the failure backoff window.
 *
 * @return int Seconds.
 */
function streamit_child_ost_empty_ttl() {
	return streamit_child_ost_negative_ttl();
}

/**
 * Negative-cache / backoff TTL after a failed or timed-out scan.
 *
 * Shorter than the positive TTL so a recovered media host is retried sooner,
 * while still blocking frontend traffic from scheduling a scan storm.
 *
 * @return int Seconds.
 */
function streamit_child_ost_negative_ttl() {
	return 2 * MINUTE_IN_SECONDS;
}

/**
 * Worker lock TTL. Covers one scan attempt (20s timeout + optional 1s retry).
 *
 * @return int Seconds.
 */
function streamit_child_ost_lock_ttl() {
	return 60;
}

/**
 * Whether a normalized directory is eligible for OST media-server discovery.
 *
 * @param string $title_directory Normalized relative path.
 * @return bool
 */
function streamit_child_ost_directory_is_scannable( $title_directory ) {
	$title_directory = streamit_child_normalize_ost_relative_path( $title_directory );
	if ( '' === $title_directory ) {
		return false;
	}
	$parts = explode( '/', $title_directory );
	$root  = strtolower( (string) ( $parts[0] ?? '' ) );
	return ( 'movie' === $root || 'series' === $root );
}

/**
 * Transient key for a successful OST discovery result.
 *
 * @param string $title_directory Normalized relative path.
 * @return string
 */
function streamit_child_ost_result_transient_key( $title_directory ) {
	return 'stc_ost_' . md5( streamit_child_normalize_ost_relative_path( $title_directory ) );
}

/**
 * Transient key that backs off scheduling after a failed discovery.
 *
 * @param string $title_directory Normalized relative path.
 * @return string
 */
function streamit_child_ost_failure_transient_key( $title_directory ) {
	return 'stc_ost_fail_' . md5( streamit_child_normalize_ost_relative_path( $title_directory ) );
}

/**
 * Option / object-cache key for the discovery worker lock.
 *
 * @param string $title_directory Normalized relative path.
 * @return string
 */
function streamit_child_ost_lock_key( $title_directory ) {
	return 'stc_ost_lock_' . md5( streamit_child_normalize_ost_relative_path( $title_directory ) );
}

/**
 * Drop cached OST discovery state for a title directory.
 *
 * Call after rematch/import so newly added OST files are eligible immediately.
 *
 * @param string $title_directory Relative Movie/... or series/... path.
 * @return void
 */
function streamit_child_ost_invalidate_discovery( $title_directory ) {
	$title_directory = streamit_child_normalize_ost_relative_path( $title_directory );
	if ( '' === $title_directory || ! function_exists( 'delete_transient' ) ) {
		return;
	}
	delete_transient( streamit_child_ost_result_transient_key( $title_directory ) );
	delete_transient( streamit_child_ost_failure_transient_key( $title_directory ) );
}

/**
 * Acquire a short-lived discovery lock for one directory.
 *
 * Prefers atomic wp_cache_add when an external object cache is present.
 * Falls back to add_option(), which fails when the option already exists.
 *
 * @param string $title_directory Normalized relative path.
 * @return bool True when this caller owns the lock.
 */
function streamit_child_ost_acquire_discovery_lock( $title_directory ) {
	if ( ! streamit_child_ost_directory_is_scannable( $title_directory ) ) {
		return false;
	}
	$key = streamit_child_ost_lock_key( $title_directory );
	$ttl = streamit_child_ost_lock_ttl();

	if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_add' ) ) {
		return (bool) wp_cache_add( $key, 1, 'streamit_child_ost', $ttl );
	}

	if ( ! function_exists( 'add_option' ) || ! function_exists( 'get_option' ) || ! function_exists( 'delete_option' ) ) {
		return true;
	}

	$existing = get_option( $key, false );
	if ( false === $existing ) {
		return (bool) add_option( $key, time(), '', 'no' );
	}

	$set_at = is_numeric( $existing ) ? (int) $existing : 0;
	if ( $set_at > 0 && ( time() - $set_at ) > $ttl ) {
		delete_option( $key );
		return (bool) add_option( $key, time(), '', 'no' );
	}

	return false;
}

/**
 * Release the discovery lock for one directory.
 *
 * @param string $title_directory Normalized relative path.
 */
function streamit_child_ost_release_discovery_lock( $title_directory ) {
	$key = streamit_child_ost_lock_key( $title_directory );

	if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_delete' ) ) {
		wp_cache_delete( $key, 'streamit_child_ost' );
	}

	if ( function_exists( 'delete_option' ) ) {
		delete_option( $key );
	}
}

/**
 * Run the authenticated media-server OST scan and normalize tracks.
 *
 * Never logs directories, tokens, or WP_Error messages.
 *
 * @param string $title_directory Relative Movie/... or series/... path.
 * @return array{ok: bool, tracks: list<array{filename:string,media_path:string,size_label:string}>}
 */
function streamit_child_ost_scan_directory( $title_directory ) {
	$title_directory = streamit_child_normalize_ost_relative_path( $title_directory );
	$empty           = array(
		'ok'     => false,
		'tracks' => array(),
	);

	if ( ! streamit_child_ost_directory_is_scannable( $title_directory ) ) {
		return $empty;
	}

	/**
	 * Short-circuit the media-server scan (tests / local fakes).
	 *
	 * Return array{ok:bool,tracks:array} to skip the HTTP client.
	 *
	 * @param array{ok:bool,tracks:array}|null $pre
	 * @param string                           $title_directory
	 */
	$pre = apply_filters( 'streamit_child_ost_pre_scan', null, $title_directory );
	if ( is_array( $pre ) && array_key_exists( 'ok', $pre ) ) {
		$tracks = isset( $pre['tracks'] ) && is_array( $pre['tracks'] ) ? $pre['tracks'] : array();
		return array(
			'ok'     => ! empty( $pre['ok'] ),
			'tracks' => array_values( $tracks ),
		);
	}

	$parts = explode( '/', $title_directory );
	$root  = strtolower( (string) ( $parts[0] ?? '' ) );
	$scan  = null;
	if ( 'movie' === $root && class_exists( 'Movies_WP_Media_Api_Client', false ) ) {
		$scan = Movies_WP_Media_Api_Client::scan_movie_directory( $title_directory );
	} elseif ( 'series' === $root && class_exists( 'Movies_WP_Series_Media_Api_Client', false ) ) {
		$scan = Movies_WP_Series_Media_Api_Client::scan_series_directory( $title_directory );
	}

	if ( is_wp_error( $scan ) || ! is_array( $scan ) || empty( $scan['ok'] ) ) {
		return $empty;
	}

	$files  = isset( $scan['files'] ) && is_array( $scan['files'] ) ? $scan['files'] : array();
	$tracks = streamit_child_ost_tracks_from_scan_files( $files, $title_directory );

	return array(
		'ok'     => true,
		'tracks' => $tracks,
	);
}

/**
 * Call the media scan API for a title directory when available.
 *
 * Kept for compatibility. Frontend rendering must not call this.
 *
 * @param string $title_directory Relative Movie/... or series/... path.
 * @return list<array{filename:string,media_path:string,size_label:string}>
 */
function streamit_child_fetch_ost_tracks_via_scan( $title_directory ) {
	$result = streamit_child_ost_scan_directory( $title_directory );
	return ! empty( $result['ok'] ) && isset( $result['tracks'] ) && is_array( $result['tracks'] )
		? $result['tracks']
		: array();
}

/**
 * Schedule background OST discovery for one directory.
 *
 * Uses Action Scheduler when available. Dedupes pending/running jobs for the
 * same directory args via as_has_scheduled_action(). AS unique=true is not used
 * because WooCommerce AS 3.9.3 uniqueness ignores args (hook+group only).
 *
 * @param string $title_directory Normalized or raw relative path.
 * @return bool True when a new job was scheduled.
 */
function streamit_child_ost_schedule_discovery( $title_directory ) {
	$title_directory = streamit_child_normalize_ost_relative_path( $title_directory );
	if ( ! streamit_child_ost_directory_is_scannable( $title_directory ) ) {
		return false;
	}

	if ( function_exists( 'get_transient' ) && false !== get_transient( streamit_child_ost_failure_transient_key( $title_directory ) ) ) {
		return false;
	}

	if ( function_exists( 'get_transient' ) ) {
		$cached = get_transient( streamit_child_ost_result_transient_key( $title_directory ) );
		if ( is_array( $cached ) ) {
			return false;
		}
	}

	/**
	 * Short-circuit scheduling (tests). Return true/false to skip Action Scheduler.
	 *
	 * @param bool|null $pre
	 * @param string    $title_directory
	 */
	$pre = apply_filters( 'streamit_child_ost_pre_schedule_discovery', null, $title_directory );
	if ( null !== $pre ) {
		return (bool) $pre;
	}

	if ( ! function_exists( 'as_schedule_single_action' ) ) {
		return false;
	}

	$hook  = STREAMIT_CHILD_OST_DISCOVER_HOOK;
	$group = STREAMIT_CHILD_OST_DISCOVER_GROUP;
	$args  = array( $title_directory );

	if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args, $group ) ) {
		return false;
	}

	$id = as_schedule_single_action( time(), $hook, $args, $group, false );

	return ( is_int( $id ) && $id > 0 ) || ( is_string( $id ) && ctype_digit( $id ) && (int) $id > 0 );
}

/**
 * Background worker: scan once under a lock and write the result transient.
 *
 * @param string $title_directory Relative Movie/... or series/... path.
 */
function streamit_child_ost_run_discovery( $title_directory ) {
	$title_directory = streamit_child_normalize_ost_relative_path( $title_directory );
	if ( ! streamit_child_ost_directory_is_scannable( $title_directory ) ) {
		return;
	}

	if ( ! streamit_child_ost_acquire_discovery_lock( $title_directory ) ) {
		return;
	}

	try {
		$result_key = streamit_child_ost_result_transient_key( $title_directory );
		if ( function_exists( 'get_transient' ) ) {
			$existing = get_transient( $result_key );
			if ( is_array( $existing ) ) {
				return;
			}
		}

		$scan = streamit_child_ost_scan_directory( $title_directory );
		if ( ! empty( $scan['ok'] ) ) {
			$tracks = isset( $scan['tracks'] ) && is_array( $scan['tracks'] ) ? $scan['tracks'] : array();
			$ttl    = empty( $tracks )
				? streamit_child_ost_empty_ttl()
				: streamit_child_ost_positive_ttl();
			if ( function_exists( 'set_transient' ) ) {
				set_transient( $result_key, $tracks, $ttl );
			}
			if ( function_exists( 'delete_transient' ) ) {
				delete_transient( streamit_child_ost_failure_transient_key( $title_directory ) );
			}
			return;
		}

		if ( function_exists( 'set_transient' ) ) {
			set_transient( streamit_child_ost_failure_transient_key( $title_directory ), 1, streamit_child_ost_negative_ttl() );
		}
	} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		if ( function_exists( 'set_transient' ) ) {
			set_transient( streamit_child_ost_failure_transient_key( $title_directory ), 1, streamit_child_ost_negative_ttl() );
		}
	} finally {
		streamit_child_ost_release_discovery_lock( $title_directory );
	}
}

/**
 * Action Scheduler callback.
 *
 * @param string $title_directory Relative directory from the scheduled args.
 */
function streamit_child_ost_discover_action( $title_directory = '' ) {
	streamit_child_ost_run_discovery( $title_directory );
}

/**
 * Resolve OST tracks for a title without blocking on media-server discovery.
 *
 * Order: `_ost` meta → positive result transient (including cached `[]`) →
 * schedule one background job on miss → return `[]`.
 *
 * Never exposes absolute filesystem paths.
 *
 * @param object $st_data Movie or TV show Streamit object with get_meta().
 * @return list<array{filename:string,media_path:string,size_label:string}>
 */
function streamit_child_get_ost_tracks( $st_data ) {
	if ( ! is_object( $st_data ) || ! method_exists( $st_data, 'get_meta' ) ) {
		return array();
	}

	$raw_directory = $st_data->get_meta( '_media_directory' );
	$title_directory = streamit_child_normalize_ost_relative_path( is_string( $raw_directory ) ? $raw_directory : '' );
	if ( '' === $title_directory ) {
		return array();
	}

	$from_meta = streamit_child_normalize_ost_meta_tracks( $st_data->get_meta( '_ost' ), $title_directory );
	if ( ! empty( $from_meta ) ) {
		return $from_meta;
	}

	if ( function_exists( 'get_transient' ) ) {
		$cached = get_transient( streamit_child_ost_result_transient_key( $title_directory ) );
		if ( is_array( $cached ) ) {
			return streamit_child_normalize_ost_meta_tracks( $cached, $title_directory );
		}

		if ( false !== get_transient( streamit_child_ost_failure_transient_key( $title_directory ) ) ) {
			return array();
		}
	}

	streamit_child_ost_schedule_discovery( $title_directory );

	return array();
}

if ( function_exists( 'add_action' ) ) {
	add_action( STREAMIT_CHILD_OST_DISCOVER_HOOK, 'streamit_child_ost_discover_action', 10, 1 );
}

/**
 * Catalog rows for the download UI (filename + optional size + signed href).
 *
 * @param object $st_data
 * @param bool   $can_download
 * @return list<array{filename:string,size_label:string,title:string,href:string}>
 */
function streamit_child_build_ost_download_rows( $st_data, $can_download = true ) {
	$can_download    = (bool) $can_download;
	$title_directory = '';
	if ( is_object( $st_data ) && method_exists( $st_data, 'get_meta' ) ) {
		$title_directory = streamit_child_normalize_ost_relative_path( (string) $st_data->get_meta( '_media_directory' ) );
	}

	$rows = array();
	foreach ( streamit_child_get_ost_tracks( $st_data ) as $track ) {
		$path     = isset( $track['media_path'] ) ? (string) $track['media_path'] : '';
		$filename = isset( $track['filename'] ) ? (string) $track['filename'] : streamit_child_ost_display_basename( $path );
		$size     = isset( $track['size_label'] ) ? (string) $track['size_label'] : '';
		if ( '' === $filename || '' === $path ) {
			continue;
		}

		$href        = '';
		$stream_href = '';
		if ( $can_download ) {
			$href = streamit_child_resolve_ost_download_url( $path, $title_directory );
			if ( '' === $href ) {
				continue;
			}
			$stream_href = streamit_child_resolve_ost_stream_url( $path, $title_directory );
		}

		$title = $filename;
		if ( '' !== $size ) {
			$title .= ' · ' . $size;
		}

		$rows[] = array(
			'filename'    => $filename,
			'size_label'  => $size,
			'title'       => $title,
			'href'        => $href,
			'stream_href' => $stream_href,
		);
	}

	return $rows;
}

/**
 * Render the «موسیقی متن» block inside an existing download box.
 *
 * @param array<int, array<string, mixed>> $ost_rows
 * @param bool                             $can_download
 */
function streamit_child_render_ost_download_section( array $ost_rows, $can_download = true ) {
	if ( empty( $ost_rows ) ) {
		return;
	}

	$can_download = (bool) $can_download;
	$count        = count( $ost_rows );

	if ( function_exists( 'streamit_child_enqueue_ost_player_assets' ) ) {
		streamit_child_enqueue_ost_player_assets();
	}
	?>
	<details class="stc-dl-series-subs stc-dl-ost" data-stc-ost>
		<summary class="stc-dl-series-subs__toggle">
			<span class="stc-dl-series-subs__meta">
				<span class="stc-dl-series-subs__title">
					<?php esc_html_e( 'موسیقی متن', 'streamit' ); ?>
				</span>
				<span class="stc-dl-series-subs__count">
					<?php
					/* translators: %d: number of soundtrack files */
					echo esc_html( sprintf( _n( '%d فایل صوتی', '%d فایل صوتی', $count, 'streamit' ), $count ) );
					?>
				</span>
			</span>
			<span class="stc-dl-chevron stc-dl-chevron--sm" aria-hidden="true"></span>
		</summary>

		<div class="stc-dl-series-subs__panel">
			<?php if ( $can_download ) : ?>
				<div class="stc-dl-ost-player" data-stc-ost-player hidden>
					<div class="stc-dl-ost-player__meta">
						<span class="stc-dl-ost-player__now"><?php esc_html_e( 'در حال پخش:', 'streamit' ); ?></span>
						<bdi class="stc-dl-ost-player__title" data-stc-ost-title></bdi>
					</div>
					<div class="stc-dl-ost-player__transport">
						<button
							type="button"
							class="btn btn-secondary border stc-dl-btn stc-dl-ost-player__btn"
							data-stc-ost-toggle
							data-label-play="<?php echo esc_attr( __( 'پخش', 'streamit' ) ); ?>"
							data-label-pause="<?php echo esc_attr( __( 'توقف', 'streamit' ) ); ?>"
							aria-label="<?php esc_attr_e( 'پخش', 'streamit' ); ?>"
						>
							<span class="stc-dl__icon" aria-hidden="true"><?php echo st_get_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span data-stc-ost-toggle-text><?php esc_html_e( 'پخش', 'streamit' ); ?></span>
						</button>
						<button
							type="button"
							class="btn btn-secondary border stc-dl-btn stc-dl-ost-player__btn"
							data-stc-ost-stop
							aria-label="<?php esc_attr_e( 'قطع', 'streamit' ); ?>"
						>
							<span class="stc-dl__icon stc-dl-ost-player__stop-icon" aria-hidden="true"></span>
							<span><?php esc_html_e( 'قطع', 'streamit' ); ?></span>
						</button>
						<span class="stc-dl-ost-player__time" data-stc-ost-current>0:00</span>
						<label class="stc-dl-ost-player__seek-wrap">
							<span class="screen-reader-text"><?php esc_html_e( 'موقعیت پخش', 'streamit' ); ?></span>
							<input
								type="range"
								class="stc-dl-ost-player__seek"
								min="0"
								max="0"
								value="0"
								step="0.1"
								data-stc-ost-seek
								disabled
							/>
						</label>
						<span class="stc-dl-ost-player__time" data-stc-ost-duration>0:00</span>
					</div>
				</div>
			<?php endif; ?>

			<ul class="stc-dl__rows stc-dl-ost__rows">
				<?php foreach ( $ost_rows as $track ) : ?>
					<?php
					$filename       = isset( $track['filename'] ) ? (string) $track['filename'] : '';
					$size_label     = isset( $track['size_label'] ) ? (string) $track['size_label'] : '';
					$title          = isset( $track['title'] ) ? (string) $track['title'] : $filename;
					$href           = isset( $track['href'] ) ? (string) $track['href'] : '';
					$stream_href    = isset( $track['stream_href'] ) ? (string) $track['stream_href'] : '';
					/* translators: %s: soundtrack filename */
					$download_label = sprintf( __( 'دانلود %s', 'streamit' ), $filename );
					/* translators: %s: soundtrack filename */
					$play_label     = sprintf( __( 'پخش %s', 'streamit' ), $filename );
					?>
					<li class="stc-dl-row stc-dl-row--ost">
						<div class="stc-dl-row__cell stc-dl-row__cell--quality">
							<span class="stc-dl-row__label"><?php esc_html_e( 'فایل:', 'streamit' ); ?></span>
							<bdi class="stc-dl-row__quality"><?php echo esc_html( $filename ); ?></bdi>
						</div>
						<div class="stc-dl-row__cell stc-dl-row__cell--meta">
							<?php if ( '' !== $size_label ) : ?>
								<span class="stc-dl-row__field">
									<span class="stc-dl-row__label"><?php esc_html_e( 'حجم:', 'streamit' ); ?></span>
									<bdi class="stc-dl-row__value"><?php echo esc_html( $size_label ); ?></bdi>
								</span>
							<?php endif; ?>
						</div>
						<div class="stc-dl-row__actions">
							<?php if ( $can_download && '' !== $stream_href ) : ?>
								<button
									type="button"
									class="btn btn-secondary border stc-dl-btn stc-dl-ost__play"
									data-stc-ost-play
									data-src="<?php echo esc_url( $stream_href ); ?>"
									data-title="<?php echo esc_attr( $filename ); ?>"
									title="<?php echo esc_attr( $play_label ); ?>"
									aria-label="<?php echo esc_attr( $play_label ); ?>"
								>
									<span class="stc-dl__icon" aria-hidden="true"><?php echo st_get_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span><?php esc_html_e( 'پخش', 'streamit' ); ?></span>
								</button>
							<?php elseif ( ! $can_download ) : ?>
								<button
									type="button"
									class="btn btn-secondary border stc-dl-btn stc-dl-ost__play"
									title="<?php echo esc_attr( $play_label ); ?>"
									aria-label="<?php echo esc_attr( $play_label ); ?>"
									data-bs-toggle="modal"
									data-bs-target="#subscribeRequiredModal"
								>
									<span class="stc-dl__icon" aria-hidden="true"><?php echo st_get_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span><?php esc_html_e( 'پخش', 'streamit' ); ?></span>
								</button>
							<?php endif; ?>

							<?php if ( $can_download && '' !== $href ) : ?>
								<a
									class="btn btn-primary stc-dl-btn"
									href="<?php echo esc_url( $href ); ?>"
									title="<?php echo esc_attr( $title ); ?>"
									aria-label="<?php echo esc_attr( $download_label ); ?>"
									download
								>
									<span class="stc-dl__icon" aria-hidden="true"><?php echo st_get_icon( 'download-2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span><?php esc_html_e( 'دانلود', 'streamit' ); ?></span>
								</a>
							<?php elseif ( ! $can_download ) : ?>
								<button
									type="button"
									class="btn btn-primary stc-dl-btn"
									title="<?php echo esc_attr( $title ); ?>"
									aria-label="<?php echo esc_attr( $download_label ); ?>"
									data-bs-toggle="modal"
									data-bs-target="#subscribeRequiredModal"
								>
									<span class="stc-dl__icon" aria-hidden="true"><?php echo st_get_icon( 'download-2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span><?php esc_html_e( 'دانلود', 'streamit' ); ?></span>
								</button>
							<?php else : ?>
								<span class="stc-dl-btn stc-dl-btn--disabled" title="<?php esc_attr_e( 'فایل در دسترس نیست', 'streamit' ); ?>">
									<?php esc_html_e( 'در دسترس نیست', 'streamit' ); ?>
								</span>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</details>
	<?php
}

/**
 * Enqueue OST inline player script (call when the OST section is rendered).
 */
function streamit_child_enqueue_ost_player_assets() {
	$js_path = get_stylesheet_directory() . '/assets/js/ost-player.js';

	if ( ! file_exists( $js_path ) ) {
		return;
	}

	wp_enqueue_script(
		'streamit-child-ost-player',
		get_stylesheet_directory_uri() . '/assets/js/ost-player.js',
		array(),
		(string) filemtime( $js_path ),
		true
	);
}

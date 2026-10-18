<?php
/**
 * OST / soundtrack downloads for movie and series download boxes.
 *
 * Tracks stay on the media server. Paths are validated under the title
 * `_media_directory` + `/OST/` with an allowlisted audio extension, then
 * signed with type `d` (attachment) via the existing mint.
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
 * Signed download URL for one OST path, or empty when locked/invalid.
 *
 * Always mints type `d` so verify.php sends Content-Disposition: attachment.
 *
 * @param string $relative_path Relative path under media root.
 * @param string $title_directory Title `_media_directory`.
 * @return string
 */
function streamit_child_resolve_ost_download_url( $relative_path, $title_directory ) {
	if ( ! streamit_child_is_allowed_ost_media_path( $title_directory, $relative_path ) ) {
		return '';
	}

	$relative_path = streamit_child_normalize_ost_relative_path( $relative_path );
	if ( '' === $relative_path || ! function_exists( 'movies_wp_media_signed_url' ) ) {
		return '';
	}

	$signed = movies_wp_media_signed_url( $relative_path, 'd' );
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
 * Call the media scan API for a title directory when available.
 *
 * @param string $title_directory Relative Movie/... or series/... path.
 * @return list<array{filename:string,media_path:string,size_label:string}>
 */
function streamit_child_fetch_ost_tracks_via_scan( $title_directory ) {
	$title_directory = streamit_child_normalize_ost_relative_path( $title_directory );
	if ( '' === $title_directory ) {
		return array();
	}

	$parts = explode( '/', $title_directory );
	$root  = strtolower( (string) ( $parts[0] ?? '' ) );

	$scan = null;
	if ( 'movie' === $root && class_exists( 'Movies_WP_Media_Api_Client', false ) ) {
		$scan = Movies_WP_Media_Api_Client::scan_movie_directory( $title_directory );
	} elseif ( 'series' === $root && class_exists( 'Movies_WP_Series_Media_Api_Client', false ) ) {
		$scan = Movies_WP_Series_Media_Api_Client::scan_series_directory( $title_directory );
	}

	if ( is_wp_error( $scan ) || ! is_array( $scan ) || empty( $scan['ok'] ) ) {
		return array();
	}

	$files = isset( $scan['files'] ) && is_array( $scan['files'] ) ? $scan['files'] : array();
	return streamit_child_ost_tracks_from_scan_files( $files, $title_directory );
}

/**
 * Resolve OST tracks for a title: meta first, then cached scan discovery.
 *
 * Never exposes absolute filesystem paths. On failure returns an empty list.
 *
 * @param object $st_data Movie or TV show Streamit object with get_meta().
 * @return list<array{filename:string,media_path:string,size_label:string}>
 */
function streamit_child_get_ost_tracks( $st_data ) {
	if ( ! is_object( $st_data ) || ! method_exists( $st_data, 'get_meta' ) ) {
		return array();
	}

	$title_directory = streamit_child_normalize_ost_relative_path( (string) $st_data->get_meta( '_media_directory' ) );
	if ( '' === $title_directory ) {
		return array();
	}

	$from_meta = streamit_child_normalize_ost_meta_tracks( $st_data->get_meta( '_ost' ), $title_directory );
	if ( ! empty( $from_meta ) ) {
		return $from_meta;
	}

	$cache_key = 'stc_ost_' . md5( $title_directory );
	if ( function_exists( 'get_transient' ) ) {
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return streamit_child_normalize_ost_meta_tracks( $cached, $title_directory );
		}
	}

	$discovered = streamit_child_fetch_ost_tracks_via_scan( $title_directory );
	if ( function_exists( 'set_transient' ) ) {
		set_transient( $cache_key, $discovered, 15 * MINUTE_IN_SECONDS );
	}

	return $discovered;
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

		$href = '';
		if ( $can_download ) {
			$href = streamit_child_resolve_ost_download_url( $path, $title_directory );
			if ( '' === $href ) {
				continue;
			}
		}

		$title = $filename;
		if ( '' !== $size ) {
			$title .= ' · ' . $size;
		}

		$rows[] = array(
			'filename'   => $filename,
			'size_label' => $size,
			'title'      => $title,
			'href'       => $href,
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
	?>
	<details class="stc-dl-series-subs stc-dl-ost" open>
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
			<ul class="stc-dl__rows stc-dl-ost__rows">
				<?php foreach ( $ost_rows as $track ) : ?>
					<?php
					$filename       = isset( $track['filename'] ) ? (string) $track['filename'] : '';
					$size_label     = isset( $track['size_label'] ) ? (string) $track['size_label'] : '';
					$title          = isset( $track['title'] ) ? (string) $track['title'] : $filename;
					$href           = isset( $track['href'] ) ? (string) $track['href'] : '';
					/* translators: %s: soundtrack filename */
					$download_label = sprintf( __( 'دانلود %s', 'streamit' ), $filename );
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

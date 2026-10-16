<?php
/**
 * Secure trailer upload placement (POST /upload/trailer core).
 *
 * Writes only under {title}/Trailer/. Never touches feature movie/episode dirs.
 * Callers must authenticate (HMAC) before invoking these helpers.
 *
 * @package movies-wp
 */

declare(strict_types=1);

require_once __DIR__ . '/movie-dir.php';
require_once __DIR__ . '/series-dir.php';
require_once __DIR__ . '/ffprobe.php';
require_once __DIR__ . '/scan-auth.php';

const MEDIA_UPLOAD_TRAILER_PATH           = '/upload/trailer';
const MEDIA_TRAILER_UPLOAD_DEFAULT_MAX    = 134217728; // 128 MiB
const MEDIA_TRAILER_ALLOWED_EXTENSIONS    = array( 'mp4', 'webm', 'mkv' );

/**
 * Upload config (extends scan auth with size + nonce dir).
 *
 * @return array{
 *   api_key: string,
 *   hmac_secret: string,
 *   allowed_ips: list<string>,
 *   timestamp_skew: int,
 *   media_root: string,
 *   movie_root: string,
 *   series_root: string,
 *   max_bytes: int,
 *   nonce_dir: string
 * }
 */
function media_upload_trailer_config(): array {
	$base = media_scan_http_config();

	$file = array();
	$config_file = '/etc/asiastarx-media.env.php';
	if ( is_readable( $config_file ) ) {
		$loaded = include $config_file;
		if ( is_array( $loaded ) ) {
			$file = $loaded;
		}
	}

	$max_raw = media_scan_config_string( 'MEDIA_TRAILER_UPLOAD_MAX_BYTES', $file );
	$max     = (int) $max_raw;
	if ( $max <= 0 ) {
		$max = MEDIA_TRAILER_UPLOAD_DEFAULT_MAX;
	}

	$nonce = media_scan_config_string( 'MEDIA_UPLOAD_NONCE_DIR', $file );
	if ( $nonce === '' ) {
		$nonce = rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' ) . '/media-upload-nonces';
	}

	return array_merge(
		$base,
		array(
			'max_bytes' => $max,
			'nonce_dir' => media_movie_dir_normalize_root( $nonce ),
		)
	);
}

/**
 * Canonical body params for POST HMAC (sorted keys, RFC 3986).
 */
function media_upload_trailer_canonical_query( string $dir, string $filename, string $sha256 ): string {
	$sha256 = strtolower( trim( $sha256 ) );
	$parts  = array(
		'dir=' . rawurlencode( $dir ),
		'filename=' . rawurlencode( $filename ),
		'sha256=' . rawurlencode( $sha256 ),
	);
	sort( $parts, SORT_STRING );
	return implode( '&', $parts );
}

/**
 * POST-specific canonical string (method + path + timestamp + integrity params).
 */
function media_upload_trailer_canonical_request(
	string $timestamp,
	string $dir,
	string $filename,
	string $sha256
): string {
	return $timestamp
		. "\nPOST\n"
		. MEDIA_UPLOAD_TRAILER_PATH
		. "\n"
		. media_upload_trailer_canonical_query( $dir, $filename, $sha256 );
}

/**
 * Consume a one-time nonce keyed by the canonical request hash.
 * Returns true on first use, false if replayed within TTL.
 *
 * @param callable|null $store Test hook: fn(string $key, int $ttl): bool
 */
function media_upload_nonce_consume( string $canonical, int $ttl_seconds, string $nonce_dir, ?callable $store = null ): bool {
	$key = hash( 'sha256', $canonical );
	$ttl = max( 1, $ttl_seconds );

	if ( $store !== null ) {
		return (bool) $store( $key, $ttl );
	}

	if ( ! is_dir( $nonce_dir ) && ! mkdir( $nonce_dir, 0700, true ) && ! is_dir( $nonce_dir ) ) {
		return false;
	}

	$path = $nonce_dir . '/' . $key;
	$now  = time();

	// Best-effort prune of expired sibling files (bounded).
	media_upload_nonce_prune( $nonce_dir, $ttl, 32 );

	$fh = @fopen( $path, 'c+' );
	if ( $fh === false ) {
		return false;
	}

	try {
		if ( ! flock( $fh, LOCK_EX ) ) {
			return false;
		}

		$raw = stream_get_contents( $fh );
		if ( is_string( $raw ) && $raw !== '' ) {
			$created = (int) trim( $raw );
			if ( $created > 0 && ( $now - $created ) <= $ttl ) {
				return false; // replay
			}
		}

		ftruncate( $fh, 0 );
		rewind( $fh );
		fwrite( $fh, (string) $now );
		fflush( $fh );
		return true;
	} finally {
		flock( $fh, LOCK_UN );
		fclose( $fh );
	}
}

/**
 * @param int $limit Max files to inspect per call.
 */
function media_upload_nonce_prune( string $nonce_dir, int $ttl, int $limit = 32 ): void {
	$dh = @opendir( $nonce_dir );
	if ( $dh === false ) {
		return;
	}
	$now   = time();
	$seen  = 0;
	while ( $seen < $limit && ( $name = readdir( $dh ) ) !== false ) {
		if ( $name === '.' || $name === '..' || strlen( $name ) !== 64 ) {
			continue;
		}
		++$seen;
		$path = $nonce_dir . '/' . $name;
		$mtime = @filemtime( $path );
		if ( $mtime !== false && ( $now - $mtime ) > $ttl ) {
			@unlink( $path );
		}
	}
	closedir( $dh );
}

/**
 * Safe trailer basename only (no path separators).
 *
 * @return array{ok: true, filename: string, extension: string}|array{ok: false, code: string, message: string}
 */
function media_upload_trailer_validate_filename( string $filename ): array {
	$filename = trim( $filename );
	if ( $filename === '' ) {
		return media_movie_dir_error( 'invalid_filename', 'Filename is empty.' );
	}
	if ( str_contains( $filename, "\0" ) ) {
		return media_movie_dir_error( 'invalid_filename', 'Filename contains a null byte.' );
	}
	if ( str_contains( $filename, '/' ) || str_contains( $filename, '\\' ) ) {
		return media_movie_dir_error( 'invalid_filename', 'Filename must not contain path separators.' );
	}
	if ( $filename !== basename( $filename ) ) {
		return media_movie_dir_error( 'invalid_filename', 'Filename must be a bare name.' );
	}

	if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,180}\.(mp4|webm|mkv)$/i', $filename ) ) {
		return media_movie_dir_error( 'invalid_filename', 'Filename must be a safe name with an allowed extension.' );
	}

	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, MEDIA_TRAILER_ALLOWED_EXTENSIONS, true ) ) {
		return media_movie_dir_error( 'invalid_type', 'Trailer type is not allowed.' );
	}

	// Canonicalize extension casing on disk.
	$base = substr( $filename, 0, -strlen( $ext ) );
	return array(
		'ok'        => true,
		'filename'  => $base . $ext,
		'extension' => $ext,
	);
}

/**
 * Resolve movie or series title directory from a relative path.
 *
 * @return array{ok: true, kind: string, directory: string}|array{ok: false, code: string, message: string}
 */
function media_upload_resolve_title_dir(
	string $relative,
	string $media_root,
	string $movie_root,
	string $series_root
): array {
	$normalized = str_replace( '\\', '/', trim( $relative ) );
	$normalized = trim( $normalized, '/' );
	$first      = explode( '/', $normalized )[0] ?? '';

	if ( strcasecmp( $first, 'series' ) === 0 ) {
		return media_resolve_series_dir( $relative, $media_root, $series_root );
	}

	return media_resolve_movie_dir( $relative, $media_root, $movie_root );
}

/**
 * Magic-byte sniff for allowed trailer containers.
 *
 * @return array{ok: true, sniffed: string}|array{ok: false, code: string, message: string}
 */
function media_upload_trailer_sniff( string $absolute_path, string $expected_ext ): array {
	$fh = @fopen( $absolute_path, 'rb' );
	if ( $fh === false ) {
		return media_movie_dir_error( 'read_failed', 'Could not read uploaded file.' );
	}
	$head = fread( $fh, 32 );
	fclose( $fh );

	if ( ! is_string( $head ) || strlen( $head ) < 8 ) {
		return media_movie_dir_error( 'invalid_type', 'Uploaded file is too small or unreadable.' );
	}

	$sniffed = '';
	// EBML (Matroska / WebM)
	if ( substr( $head, 0, 4 ) === "\x1A\x45\xDF\xA3" ) {
		$sniffed = ( $expected_ext === 'webm' ) ? 'webm' : 'mkv';
		if ( $expected_ext === 'mp4' ) {
			return media_movie_dir_error( 'invalid_type', 'File content does not match declared type.' );
		}
		if ( $expected_ext !== $sniffed && ! ( $expected_ext === 'mkv' || $expected_ext === 'webm' ) ) {
			return media_movie_dir_error( 'invalid_type', 'File content does not match declared type.' );
		}
		// webm vs mkv both EBML; accept either when extension is webm or mkv.
		$sniffed = $expected_ext;
		return array( 'ok' => true, 'sniffed' => $sniffed );
	}

	// ISO BMFF / MP4: ....ftyp
	if ( strlen( $head ) >= 8 && substr( $head, 4, 4 ) === 'ftyp' ) {
		if ( $expected_ext !== 'mp4' ) {
			return media_movie_dir_error( 'invalid_type', 'File content does not match declared type.' );
		}
		return array( 'ok' => true, 'sniffed' => 'mp4' );
	}

	return media_movie_dir_error( 'invalid_type', 'Unrecognized or disallowed media container.' );
}

/**
 * Place a trailer under {title}/Trailer/ after validation.
 *
 * @param array{
 *   max_bytes?: int,
 *   require_ffprobe?: bool,
 *   ffprobe_config?: array<string, mixed>,
 *   ffprobe_runner?: callable|null
 * } $options
 * @return array{ok: true, path: string}|array{ok: false, code: string, message: string}
 */
function media_upload_trailer_place(
	string $dir,
	string $filename,
	string $source_path,
	string $expected_sha256,
	string $media_root,
	string $movie_root,
	string $series_root,
	array $options = array()
): array {
	$max_bytes = isset( $options['max_bytes'] ) ? (int) $options['max_bytes'] : MEDIA_TRAILER_UPLOAD_DEFAULT_MAX;
	if ( $max_bytes <= 0 ) {
		$max_bytes = MEDIA_TRAILER_UPLOAD_DEFAULT_MAX;
	}

	$name = media_upload_trailer_validate_filename( $filename );
	if ( ( $name['ok'] ?? false ) !== true ) {
		return $name;
	}
	$safe_name = $name['filename'];
	$ext       = $name['extension'];

	$sha = strtolower( trim( $expected_sha256 ) );
	if ( $sha === '' || ! preg_match( '/^[a-f0-9]{64}$/', $sha ) ) {
		return media_movie_dir_error( 'invalid_sha256', 'Missing or invalid content sha256.' );
	}

	if ( $source_path === '' || ! is_file( $source_path ) || ! is_readable( $source_path ) ) {
		return media_movie_dir_error( 'upload_missing', 'Uploaded file is missing.' );
	}

	$size = filesize( $source_path );
	if ( $size === false || $size < 1 ) {
		return media_movie_dir_error( 'invalid_size', 'Uploaded file is empty.' );
	}
	if ( $size > $max_bytes ) {
		return media_movie_dir_error( 'too_large', 'Uploaded file exceeds the maximum allowed size.' );
	}

	$actual = hash_file( 'sha256', $source_path );
	if ( ! is_string( $actual ) || ! hash_equals( $sha, $actual ) ) {
		return media_movie_dir_error( 'checksum_mismatch', 'Uploaded content does not match sha256.' );
	}

	$resolved = media_upload_resolve_title_dir( $dir, $media_root, $movie_root, $series_root );
	if ( ( $resolved['ok'] ?? false ) !== true ) {
		return $resolved;
	}

	$title_rel = (string) $resolved['directory'];
	$real_media = realpath( $media_root );
	if ( $real_media === false ) {
		return media_movie_dir_error( 'root_not_found', 'MEDIA_ROOT does not exist or is not a directory.' );
	}
	$real_media = media_movie_dir_normalize_root( $real_media );
	$title_abs  = $real_media . '/' . $title_rel;
	$title_real = realpath( $title_abs );
	if ( $title_real === false || ! is_dir( $title_real ) ) {
		return media_movie_dir_error( 'not_found', 'Title directory does not exist.' );
	}
	$title_real = media_movie_dir_normalize_root( $title_real );
	if ( ! media_movie_dir_is_inside( $title_real, $real_media ) ) {
		return media_movie_dir_error( 'outside_media_root', 'Resolved path is outside MEDIA_ROOT.' );
	}

	$trailer_dir = $title_real . '/Trailer';
	if ( ! is_dir( $trailer_dir ) ) {
		if ( ! mkdir( $trailer_dir, 0755, true ) && ! is_dir( $trailer_dir ) ) {
			return media_movie_dir_error( 'mkdir_failed', 'Could not create Trailer directory.' );
		}
	}
	$trailer_real = realpath( $trailer_dir );
	if ( $trailer_real === false || ! is_dir( $trailer_real ) ) {
		return media_movie_dir_error( 'mkdir_failed', 'Could not create Trailer directory.' );
	}
	$trailer_real = media_movie_dir_normalize_root( $trailer_real );
	if ( ! media_movie_dir_is_inside( $trailer_real, $title_real ) ) {
		return media_movie_dir_error( 'outside_title', 'Trailer path escaped the title directory.' );
	}

	$final_abs = $trailer_real . '/' . $safe_name;
	if ( file_exists( $final_abs ) ) {
		return media_movie_dir_error( 'already_exists', 'A trailer with this name already exists.' );
	}

	$temp_abs = $trailer_real . '/.upload-' . bin2hex( random_bytes( 16 ) ) . '.tmp';
	$cleanup  = static function () use ( &$temp_abs ): void {
		if ( is_string( $temp_abs ) && $temp_abs !== '' && is_file( $temp_abs ) ) {
			@unlink( $temp_abs );
		}
	};

	try {
		if ( ! media_upload_stream_copy( $source_path, $temp_abs, $max_bytes ) ) {
			$cleanup();
			return media_movie_dir_error( 'write_failed', 'Could not store uploaded file.' );
		}

		$sniff = media_upload_trailer_sniff( $temp_abs, $ext );
		if ( ( $sniff['ok'] ?? false ) !== true ) {
			$cleanup();
			return $sniff;
		}

		$require_ffprobe = ! empty( $options['require_ffprobe'] );
		$runner          = $options['ffprobe_runner'] ?? null;
		$ff_cfg          = isset( $options['ffprobe_config'] ) && is_array( $options['ffprobe_config'] )
			? $options['ffprobe_config']
			: array();

		$bin = $ff_cfg['bin'] ?? MEDIA_FFPROBE_DEFAULT_BIN;
		$can_probe = $runner !== null || media_ffprobe_bin_usable( is_string( $bin ) ? $bin : MEDIA_FFPROBE_DEFAULT_BIN );

		if ( $require_ffprobe || $can_probe ) {
			if ( ! $can_probe ) {
				$cleanup();
				return media_movie_dir_error( 'ffprobe_missing', 'Media inspection is not available.' );
			}
			$probe = media_ffprobe_inspect( $temp_abs, $ff_cfg, is_callable( $runner ) ? $runner : null );
			if ( ( $probe['ok'] ?? false ) !== true ) {
				$cleanup();
				return media_movie_dir_error( 'invalid_media', 'Uploaded file failed media inspection.' );
			}
			if ( ! isset( $probe['video'] ) || ! is_array( $probe['video'] ) ) {
				$cleanup();
				return media_movie_dir_error( 'invalid_media', 'Uploaded file has no video stream.' );
			}
		}

		$published = media_upload_trailer_publish_exclusive( $temp_abs, $final_abs );
		if ( ( $published['ok'] ?? false ) !== true ) {
			$cleanup();
			return $published;
		}
		$temp_abs = ''; // hard-linked into place; temp unlinked by publish helper

		$rel = $title_rel . '/Trailer/' . $safe_name;
		return array(
			'ok'   => true,
			'path' => $rel,
		);
	} catch ( Throwable $e ) {
		$cleanup();
		return media_movie_dir_error( 'write_failed', 'Could not store uploaded file.' );
	}
}

/**
 * Publish temp → final without ever overwriting an existing destination.
 *
 * Uses link(2): fails with EEXIST if the destination already exists, so concurrent
 * uploads racing the same final path cannot replace each other. Never deletes or
 * modifies $final_abs on failure. Unlinks $temp_abs only after a successful link.
 *
 * @return array{ok: true}|array{ok: false, code: string, message: string}
 */
function media_upload_trailer_publish_exclusive( string $temp_abs, string $final_abs ): array {
	if ( $temp_abs === '' || $final_abs === '' || ! is_file( $temp_abs ) ) {
		return media_movie_dir_error( 'write_failed', 'Could not finalize uploaded file.' );
	}

	if ( file_exists( $final_abs ) ) {
		return media_movie_dir_error( 'already_exists', 'A trailer with this name already exists.' );
	}

	if ( ! function_exists( 'link' ) ) {
		return media_movie_dir_error( 'write_failed', 'Could not finalize uploaded file.' );
	}

	// Hard-link is atomic and exclusive on the destination name (no replace).
	if ( @link( $temp_abs, $final_abs ) ) {
		@unlink( $temp_abs );
		return array( 'ok' => true );
	}

	// Lost a race (destination appeared) or link failed for another reason.
	if ( file_exists( $final_abs ) ) {
		return media_movie_dir_error( 'already_exists', 'A trailer with this name already exists.' );
	}

	return media_movie_dir_error( 'write_failed', 'Could not finalize uploaded file.' );
}

/**
 * Stream copy with size cap. Returns false on failure or oversize mid-copy.
 */
function media_upload_stream_copy( string $source, string $dest, int $max_bytes ): bool {
	$in = @fopen( $source, 'rb' );
	if ( $in === false ) {
		return false;
	}
	$out = @fopen( $dest, 'wb' );
	if ( $out === false ) {
		fclose( $in );
		return false;
	}

	$written = 0;
	$ok      = true;
	try {
		while ( ! feof( $in ) ) {
			$chunk = fread( $in, 1024 * 1024 );
			if ( $chunk === false ) {
				$ok = false;
				break;
			}
			if ( $chunk === '' ) {
				break;
			}
			$written += strlen( $chunk );
			if ( $written > $max_bytes ) {
				$ok = false;
				break;
			}
			if ( fwrite( $out, $chunk ) === false ) {
				$ok = false;
				break;
			}
		}
	} finally {
		fclose( $in );
		fclose( $out );
	}

	if ( ! $ok ) {
		@unlink( $dest );
		return false;
	}
	return is_file( $dest );
}

/**
 * Map placement/auth codes to safe HTTP responses (no absolute paths).
 *
 * @return array{status: int, code: string, message: string}
 */
function media_upload_http_error_for_code( string $code ): array {
	$map = array(
		'unauthorized'          => array( 401, 'unauthorized', 'Authentication required.' ),
		'forbidden'             => array( 403, 'forbidden', 'Forbidden.' ),
		'method_not_allowed'    => array( 405, 'method_not_allowed', 'Only POST is allowed.' ),
		'replay'                => array( 403, 'forbidden', 'Forbidden.' ),
		'stale'                 => array( 403, 'forbidden', 'Forbidden.' ),
		'invalid_signature'     => array( 403, 'forbidden', 'Forbidden.' ),
		'empty_path'            => array( 400, 'invalid_dir', 'Missing or invalid dir.' ),
		'invalid_path'          => array( 400, 'invalid_dir', 'Missing or invalid dir.' ),
		'absolute_path'         => array( 400, 'invalid_dir', 'dir must be a relative title directory.' ),
		'invalid_segment'       => array( 400, 'invalid_dir', 'dir contains invalid path segments.' ),
		'invalid_filename'      => array( 400, 'invalid_filename', 'Missing or invalid filename.' ),
		'invalid_sha256'        => array( 400, 'invalid_sha256', 'Missing or invalid content sha256.' ),
		'upload_missing'        => array( 400, 'upload_missing', 'Uploaded file is missing.' ),
		'invalid_size'          => array( 400, 'invalid_size', 'Uploaded file is empty.' ),
		'too_large'             => array( 413, 'too_large', 'Uploaded file exceeds the maximum allowed size.' ),
		'checksum_mismatch'     => array( 400, 'checksum_mismatch', 'Uploaded content does not match sha256.' ),
		'invalid_type'          => array( 415, 'invalid_type', 'Trailer type is not allowed.' ),
		'invalid_media'         => array( 415, 'invalid_media', 'Uploaded file failed media inspection.' ),
		'ffprobe_missing'       => array( 500, 'upload_failed', 'Upload is not available.' ),
		'not_found'             => array( 404, 'not_found', 'Title directory not found.' ),
		'invalid_structure'     => array( 422, 'invalid_dir', 'dir is not a valid title directory.' ),
		'invalid_year'          => array( 422, 'invalid_dir', 'dir is not a valid title directory.' ),
		'not_directory'         => array( 422, 'invalid_dir', 'dir is not a valid title directory.' ),
		'outside_media_root'    => array( 422, 'invalid_dir', 'dir is not a valid title directory.' ),
		'outside_movie_root'    => array( 422, 'invalid_dir', 'dir is not a valid title directory.' ),
		'outside_series_root'   => array( 422, 'invalid_dir', 'dir is not a valid title directory.' ),
		'outside_title'         => array( 422, 'invalid_dir', 'dir is not a valid title directory.' ),
		'already_exists'        => array( 409, 'already_exists', 'A trailer with this name already exists.' ),
		'mkdir_failed'          => array( 500, 'upload_failed', 'Upload is not available.' ),
		'write_failed'          => array( 500, 'upload_failed', 'Upload is not available.' ),
		'read_failed'           => array( 500, 'upload_failed', 'Upload is not available.' ),
		'root_not_found'        => array( 500, 'upload_failed', 'Upload is not available.' ),
		'invalid_roots'         => array( 500, 'upload_failed', 'Upload is not available.' ),
	);

	if ( ! isset( $map[ $code ] ) ) {
		return array(
			'status'  => 400,
			'code'    => 'invalid_request',
			'message' => 'Invalid upload request.',
		);
	}

	return array(
		'status'  => $map[ $code ][0],
		'code'    => $map[ $code ][1],
		'message' => $map[ $code ][2],
	);
}

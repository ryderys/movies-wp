<?php
/**
 * Controlled, read-only ffprobe inspection.
 *
 * No HTTP, WordPress, Streamit, directory listing, or filename parsing.
 * Callers must pass an already-jailed absolute path. Never builds a shell
 * command string; uses argv + proc_open(bypass_shell) or an injectable runner.
 *
 * @package movies-wp
 */

declare(strict_types=1);

const MEDIA_FFPROBE_DEFAULT_BIN             = '/usr/bin/ffprobe';
const MEDIA_FFPROBE_DEFAULT_TIMEOUT_SECONDS = 15;
const MEDIA_FFPROBE_DEFAULT_MAX_OUTPUT      = 1048576; // 1 MiB

/**
 * Inspect one media file with ffprobe.
 *
 * @param string        $absolute_path Existing absolute file path.
 * @param array{
 *   bin?: string,
 *   timeout_seconds?: int,
 *   max_output_bytes?: int
 * }                    $config
 * @param callable|null $runner        Test hook:
 *                                     fn(array $argv): array{exit:int,stdout:string,stderr:string}
 * @return array{
 *   ok: bool,
 *   code?: string,
 *   message?: string,
 *   duration: int|null,
 *   video: array{codec: string|null, width: int|null, height: int|null}|null,
 *   audio: list<array{language: string|null, codec: string|null, channels: int|null}>,
 *   subtitles: list<array{language: string|null, codec: string|null}>
 * }
 */
function media_ffprobe_inspect( string $absolute_path, array $config = array(), ?callable $runner = null ): array {
	$empty = media_ffprobe_empty_result();

	$path = media_ffprobe_validate_path( $absolute_path );
	if ( is_array( $path ) && ( $path['ok'] ?? true ) === false ) {
		return array_merge( $empty, $path );
	}

	$cfg = media_ffprobe_normalize_config( $config );
	$bin = $cfg['bin'];

	if ( $runner === null && ! media_ffprobe_bin_usable( $bin ) ) {
		return array_merge(
			$empty,
			array(
				'ok'      => false,
				'code'    => 'ffprobe_missing',
				'message' => 'ffprobe executable is missing or not usable.',
			)
		);
	}

	$argv = array(
		$bin,
		'-v',
		'error',
		'-hide_banner',
		'-print_format',
		'json',
		'-show_format',
		'-show_streams',
		'--',
		$path,
	);

	if ( $runner === null ) {
		$runner = static function ( array $argv ) use ( $cfg ): array {
			return media_ffprobe_run_proc( $argv, $cfg['timeout_seconds'], $cfg['max_output_bytes'] );
		};
	}

	try {
		$raw = $runner( $argv );
	} catch ( Throwable $e ) {
		return array_merge(
			$empty,
			array(
				'ok'      => false,
				'code'    => 'ffprobe_failed',
				'message' => 'ffprobe runner threw: ' . $e->getMessage(),
			)
		);
	}

	if ( ! is_array( $raw ) ) {
		return array_merge(
			$empty,
			array(
				'ok'      => false,
				'code'    => 'ffprobe_failed',
				'message' => 'ffprobe runner returned a non-array result.',
			)
		);
	}

	$exit   = isset( $raw['exit'] ) ? (int) $raw['exit'] : -1;
	$stdout = isset( $raw['stdout'] ) ? (string) $raw['stdout'] : '';
	$stderr = isset( $raw['stderr'] ) ? (string) $raw['stderr'] : '';

	if ( isset( $raw['code'] ) && is_string( $raw['code'] ) && $raw['code'] !== '' ) {
		return array_merge(
			$empty,
			array(
				'ok'      => false,
				'code'    => $raw['code'],
				'message' => isset( $raw['message'] ) ? (string) $raw['message'] : 'ffprobe failed.',
			)
		);
	}

	if ( strlen( $stdout ) > $cfg['max_output_bytes'] ) {
		return array_merge(
			$empty,
			array(
				'ok'      => false,
				'code'    => 'ffprobe_output_too_large',
				'message' => 'ffprobe stdout exceeded the configured size limit.',
			)
		);
	}

	if ( $exit !== 0 ) {
		$detail = trim( $stderr );
		return array_merge(
			$empty,
			array(
				'ok'      => false,
				'code'    => 'ffprobe_failed',
				'message' => $detail !== ''
					? 'ffprobe exited with status ' . $exit . ': ' . $detail
					: 'ffprobe exited with status ' . $exit . '.',
			)
		);
	}

	$decoded = json_decode( $stdout, true );
	if ( ! is_array( $decoded ) ) {
		return array_merge(
			$empty,
			array(
				'ok'      => false,
				'code'    => 'ffprobe_bad_json',
				'message' => 'ffprobe returned invalid JSON.',
			)
		);
	}

	return media_ffprobe_normalize_payload( $decoded );
}

/**
 * @return array{
 *   ok: true,
 *   duration: null,
 *   video: null,
 *   audio: list<empty>,
 *   subtitles: list<empty>
 * }
 */
function media_ffprobe_empty_result(): array {
	return array(
		'ok'         => true,
		'duration'   => null,
		'video'      => null,
		'audio'      => array(),
		'subtitles'  => array(),
	);
}

/**
 * @param array{
 *   bin?: string,
 *   timeout_seconds?: int,
 *   max_output_bytes?: int
 * } $config
 * @return array{bin: string, timeout_seconds: int, max_output_bytes: int}
 */
function media_ffprobe_normalize_config( array $config ): array {
	$bin = isset( $config['bin'] ) ? trim( (string) $config['bin'] ) : MEDIA_FFPROBE_DEFAULT_BIN;
	if ( $bin === '' ) {
		$bin = MEDIA_FFPROBE_DEFAULT_BIN;
	}

	$timeout = isset( $config['timeout_seconds'] )
		? (int) $config['timeout_seconds']
		: MEDIA_FFPROBE_DEFAULT_TIMEOUT_SECONDS;
	if ( $timeout < 1 ) {
		$timeout = MEDIA_FFPROBE_DEFAULT_TIMEOUT_SECONDS;
	}

	$max = isset( $config['max_output_bytes'] )
		? (int) $config['max_output_bytes']
		: MEDIA_FFPROBE_DEFAULT_MAX_OUTPUT;
	if ( $max < 1024 ) {
		$max = MEDIA_FFPROBE_DEFAULT_MAX_OUTPUT;
	}

	return array(
		'bin'              => $bin,
		'timeout_seconds'  => $timeout,
		'max_output_bytes' => $max,
	);
}

/**
 * @return string|array{ok:false,code:string,message:string}
 */
function media_ffprobe_validate_path( string $absolute_path ) {
	$path = str_replace( '\\', '/', $absolute_path );
	$path = trim( $path );

	if ( $path === '' ) {
		return array(
			'ok'      => false,
			'code'    => 'invalid_path',
			'message' => 'Media path is empty.',
		);
	}

	if ( str_contains( $path, "\0" ) ) {
		return array(
			'ok'      => false,
			'code'    => 'invalid_path',
			'message' => 'Media path contains invalid characters.',
		);
	}

	// Require an absolute path (Unix or Windows drive).
	$is_unix_abs = str_starts_with( $path, '/' );
	$is_win_abs  = (bool) preg_match( '/^[A-Za-z]:\//', $path );
	if ( ! $is_unix_abs && ! $is_win_abs ) {
		return array(
			'ok'      => false,
			'code'    => 'invalid_path',
			'message' => 'Media path must be absolute.',
		);
	}

	foreach ( explode( '/', $path ) as $segment ) {
		if ( $segment === '..' ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_path',
				'message' => 'Media path must not contain .. segments.',
			);
		}
	}

	$real = realpath( $absolute_path );
	if ( $real === false || ! is_file( $real ) || ! is_readable( $real ) ) {
		return array(
			'ok'      => false,
			'code'    => 'invalid_path',
			'message' => 'Media path is not a readable regular file.',
		);
	}

	return $real;
}

function media_ffprobe_bin_usable( string $bin ): bool {
	$bin = trim( $bin );
	if ( $bin === '' ) {
		return false;
	}

	$is_unix_abs = str_starts_with( $bin, '/' );
	$is_win_abs  = (bool) preg_match( '/^[A-Za-z]:[\\\\\\/]/', $bin );
	if ( ! $is_unix_abs && ! $is_win_abs ) {
		return false;
	}

	return is_file( $bin ) && is_executable( $bin );
}

/**
 * @param list<string> $argv
 * @return array{exit:int,stdout:string,stderr:string,code?:string,message?:string}
 */
function media_ffprobe_run_proc( array $argv, int $timeout_seconds, int $max_output_bytes ): array {
	$descriptors = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);

	$process = @proc_open(
		$argv,
		$descriptors,
		$pipes,
		null,
		null,
		array( 'bypass_shell' => true )
	);

	if ( ! is_resource( $process ) ) {
		return array(
			'exit'    => -1,
			'stdout'  => '',
			'stderr'  => '',
			'code'    => 'ffprobe_failed',
			'message' => 'Failed to start ffprobe process.',
		);
	}

	fclose( $pipes[0] );
	stream_set_blocking( $pipes[1], false );
	stream_set_blocking( $pipes[2], false );

	$stdout    = '';
	$stderr    = '';
	$timed_out = false;
	$too_large = false;
	$deadline  = microtime( true ) + $timeout_seconds;

	while ( true ) {
		$status = proc_get_status( $process );
		$stdout .= (string) stream_get_contents( $pipes[1] );
		$stderr .= (string) stream_get_contents( $pipes[2] );

		if ( strlen( $stdout ) > $max_output_bytes || strlen( $stderr ) > $max_output_bytes ) {
			$too_large = true;
			proc_terminate( $process );
			break;
		}

		if ( ! $status['running'] ) {
			break;
		}

		if ( microtime( true ) >= $deadline ) {
			$timed_out = true;
			proc_terminate( $process );
			break;
		}

		usleep( 20000 );
	}

	// Drain remaining output after exit/terminate.
	$stdout .= (string) stream_get_contents( $pipes[1] );
	$stderr .= (string) stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );

	$exit = proc_close( $process );
	if ( isset( $status ) && is_array( $status ) && ! $status['running'] && isset( $status['exitcode'] ) && (int) $status['exitcode'] >= 0 ) {
		$exit = (int) $status['exitcode'];
	}

	if ( $timed_out ) {
		return array(
			'exit'    => -1,
			'stdout'  => '',
			'stderr'  => '',
			'code'    => 'ffprobe_timeout',
			'message' => 'ffprobe timed out after ' . $timeout_seconds . ' seconds.',
		);
	}

	if ( $too_large || strlen( $stdout ) > $max_output_bytes ) {
		return array(
			'exit'    => -1,
			'stdout'  => '',
			'stderr'  => '',
			'code'    => 'ffprobe_output_too_large',
			'message' => 'ffprobe stdout exceeded the configured size limit.',
		);
	}

	return array(
		'exit'   => (int) $exit,
		'stdout' => $stdout,
		'stderr' => $stderr,
	);
}

/**
 * Normalize ffprobe JSON into the automation probe shape.
 *
 * @param array<string, mixed> $decoded
 * @return array{
 *   ok: true,
 *   duration: int|null,
 *   video: array{codec: string|null, width: int|null, height: int|null}|null,
 *   audio: list<array{language: string|null, codec: string|null, channels: int|null}>,
 *   subtitles: list<array{language: string|null, codec: string|null}>
 * }
 */
function media_ffprobe_normalize_payload( array $decoded ): array {
	$duration = null;
	if ( isset( $decoded['format'] ) && is_array( $decoded['format'] ) && isset( $decoded['format']['duration'] ) ) {
		$duration = media_ffprobe_int_or_null( $decoded['format']['duration'] );
	}

	$video     = null;
	$audio     = array();
	$subtitles = array();

	$streams = isset( $decoded['streams'] ) && is_array( $decoded['streams'] ) ? $decoded['streams'] : array();
	foreach ( $streams as $stream ) {
		if ( ! is_array( $stream ) ) {
			continue;
		}

		$type = isset( $stream['codec_type'] ) ? strtolower( (string) $stream['codec_type'] ) : '';
		if ( $type === 'video' ) {
			if ( $video !== null ) {
				continue; // First video stream wins.
			}
			$video = array(
				'codec'  => media_ffprobe_string_or_null( $stream['codec_name'] ?? null ),
				'width'  => media_ffprobe_int_or_null( $stream['width'] ?? null ),
				'height' => media_ffprobe_int_or_null( $stream['height'] ?? null ),
			);
			continue;
		}

		if ( $type === 'audio' ) {
			$audio[] = array(
				'language' => media_ffprobe_language_tag( $stream ),
				'codec'    => media_ffprobe_string_or_null( $stream['codec_name'] ?? null ),
				'channels' => media_ffprobe_int_or_null( $stream['channels'] ?? null ),
			);
			continue;
		}

		if ( $type === 'subtitle' ) {
			$subtitles[] = array(
				'language' => media_ffprobe_language_tag( $stream ),
				'codec'    => media_ffprobe_string_or_null( $stream['codec_name'] ?? null ),
			);
		}
	}

	return array(
		'ok'        => true,
		'duration'  => $duration,
		'video'     => $video,
		'audio'     => $audio,
		'subtitles' => $subtitles,
	);
}

/**
 * @param array<string, mixed> $stream
 */
function media_ffprobe_language_tag( array $stream ): ?string {
	if ( ! isset( $stream['tags'] ) || ! is_array( $stream['tags'] ) ) {
		return null;
	}

	$tags = $stream['tags'];
	$raw  = null;
	foreach ( array( 'language', 'LANGUAGE', 'lang' ) as $key ) {
		if ( isset( $tags[ $key ] ) && is_scalar( $tags[ $key ] ) ) {
			$raw = trim( (string) $tags[ $key ] );
			break;
		}
	}

	if ( $raw === null || $raw === '' ) {
		return null;
	}

	// Keep the tag as reported. Do not map und→en/fa.
	$raw = strtolower( $raw );
	if ( ! preg_match( '/^[a-z]{2,3}(-[a-z0-9]+)?$/i', $raw ) ) {
		return null;
	}

	return $raw;
}

function media_ffprobe_string_or_null( mixed $value ): ?string {
	if ( ! is_scalar( $value ) ) {
		return null;
	}
	$text = trim( (string) $value );
	return $text === '' ? null : $text;
}

function media_ffprobe_int_or_null( mixed $value ): ?int {
	if ( is_int( $value ) ) {
		return $value;
	}
	if ( is_float( $value ) ) {
		return (int) round( $value );
	}
	if ( is_string( $value ) && is_numeric( $value ) ) {
		return (int) round( (float) $value );
	}
	return null;
}

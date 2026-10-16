<?php
/**
 * CLI tests for trailer upload client + service orchestration.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-trailer-upload-client-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-trailer-upload-test/' );
}
define( 'MEDIA_SCAN_API_URL', 'https://media.example.test' );
define( 'MEDIA_SCAN_API_KEY', 'test-key' );
define( 'MEDIA_SCAN_HMAC_SECRET', 'test-secret' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			$this->data    = $data;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
		public function get_error_data() {
			return $this->data;
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' );
	}
}

$GLOBALS['trailer_meta_store']  = array();
$GLOBALS['trailer_meta_writes'] = array();

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		unset( $single );
		$store = $GLOBALS['trailer_meta_store'][ (int) $post_id ] ?? array();
		return $store[ (string) $key ] ?? '';
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		unset( $prev_value );
		$GLOBALS['trailer_meta_store'][ (int) $post_id ][ (string) $meta_key ] = $meta_value;
		$GLOBALS['trailer_meta_writes'][]                                      = array(
			'post_id' => (int) $post_id,
			'key'     => (string) $meta_key,
			'value'   => $meta_value,
		);
		return true;
	}
}

// Minimal WordPress stubs for AJAX handler gate tests (no full WP bootstrap).
$GLOBALS['trailer_ajax_nonce_ok']   = true;
$GLOBALS['trailer_ajax_can_edit']   = true;
$GLOBALS['trailer_ajax_post_types'] = array( 42 => 'movie', 99 => 'post' );
$GLOBALS['trailer_ajax_last']       = null;

if ( ! class_exists( 'Trailer_Upload_Json_Exit', false ) ) {
	class Trailer_Upload_Json_Exit extends Exception {
	}
}
if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action, $query_arg = false, $stop = true ) {
		unset( $action, $query_arg );
		$ok = ! empty( $GLOBALS['trailer_ajax_nonce_ok'] );
		if ( ! $ok && $stop ) {
			wp_die( 'bad nonce' );
		}
		return $ok;
	}
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '' ) {
		throw new Trailer_Upload_Json_Exit( 'wp_die:' . (string) $message );
	}
}
if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, $status_code = null ) {
		$GLOBALS['trailer_ajax_last'] = array(
			'success' => false,
			'data'    => $data,
			'status'  => $status_code,
		);
		throw new Trailer_Upload_Json_Exit( 'json_error' );
	}
}
if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null, $status_code = null ) {
		$GLOBALS['trailer_ajax_last'] = array(
			'success' => true,
			'data'    => $data,
			'status'  => $status_code,
		);
		throw new Trailer_Upload_Json_Exit( 'json_success' );
	}
}
if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $post = null ) {
		$id = (int) $post;
		return $GLOBALS['trailer_ajax_post_types'][ $id ] ?? false;
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		unset( $capability, $args );
		return ! empty( $GLOBALS['trailer_ajax_can_edit'] );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ?? '' );
	}
}
if ( ! function_exists( 'streamit_get_tvshow_meta' ) ) {
	function streamit_get_tvshow_meta( $id, $key, $single = true ) {
		unset( $single );
		return get_post_meta( $id, $key, true );
	}
}
if ( ! function_exists( 'streamit_update_tvshow_meta' ) ) {
	function streamit_update_tvshow_meta( $id, $key, $value ) {
		return update_post_meta( $id, $key, $value );
	}
}
if ( ! function_exists( 'streamit_get_tvshow' ) ) {
	function streamit_get_tvshow( $id ) {
		return 7 === (int) $id ? (object) array( 'id' => 7 ) : null;
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-media-api-client.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-media-api-client.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-trailer-upload-client.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-trailer-upload-service.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-trailer-upload-admin.php';

$failures = 0;
function tu_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

echo "POST HMAC canonical (matches media-server contract)\n";
$dir  = 'Movie/Chin/2016/Bounty.Hunters';
$file = 'official.mp4';
$sha  = str_repeat( 'a', 64 );
$ts   = '1700000000';
$canon = Movies_WP_Trailer_Upload_Client::canonical_request( $ts, $dir, $file, $sha );
tu_assert( str_starts_with( $canon, $ts . "\nPOST\n/upload/trailer\n" ), 'POST method and path' );
tu_assert( ! str_contains( $canon, "\nGET\n" ), 'not GET scan canonical' );
$q = substr( $canon, strlen( $ts . "\nPOST\n/upload/trailer\n" ) );
tu_assert( str_contains( $q, 'dir=' ) && str_contains( $q, 'filename=' ) && str_contains( $q, 'sha256=' ), 'includes dir/filename/sha256' );

$sig = Movies_WP_Trailer_Upload_Client::signature( $canon, 'test-secret' );
tu_assert( 64 === strlen( $sig ), 'hmac hex length' );
$tampered = Movies_WP_Trailer_Upload_Client::canonical_request( $ts, $dir, 'evil.mp4', $sha );
tu_assert( Movies_WP_Trailer_Upload_Client::signature( $tampered, 'test-secret' ) !== $sig, 'filename change alters signature' );
$get_scan = $ts . "\nGET\n/scan/movie\ndir=" . rawurlencode( $dir );
tu_assert( Movies_WP_Trailer_Upload_Client::signature( $get_scan, 'test-secret' ) !== $sig, 'GET scan signature differs' );

echo "\nfilename / returned path validation\n";
tu_assert( ! is_wp_error( Movies_WP_Trailer_Upload_Client::normalize_filename( 'A.Trailer.MP4' ) ), 'accepts mp4' );
tu_assert( is_wp_error( Movies_WP_Trailer_Upload_Client::normalize_filename( '../x.mp4' ) ), 'rejects traversal filename' );
tu_assert( is_wp_error( Movies_WP_Trailer_Upload_Client::normalize_filename( 'x.exe' ) ), 'rejects bad extension' );
tu_assert(
	Movies_WP_Trailer_Upload_Client::validate_returned_path( $dir, $dir . '/Trailer/official.mp4' ) === $dir . '/Trailer/official.mp4',
	'accepts Trailer/ path under dir'
);
tu_assert( is_wp_error( Movies_WP_Trailer_Upload_Client::validate_returned_path( $dir, $dir . '/Feature/official.mp4' ) ), 'rejects non-Trailer path' );
tu_assert( is_wp_error( Movies_WP_Trailer_Upload_Client::validate_returned_path( $dir, 'Movie/Other/2016/X/Trailer/a.mp4' ) ), 'rejects other title path' );

echo "\ndirectory resolution + replace confirmation\n";
$GLOBALS['trailer_meta_store'] = array(
	42 => array(
		'_media_directory'   => 'Movie/Chin/2016/Bounty.Hunters',
		'_name_trailer_link' => '',
	),
	43 => array(
		'_media_directory'   => '',
		'_name_trailer_link' => 'https://youtube.com/watch?v=1',
	),
	44 => array(
		'_media_directory'   => 'Movie/Chin/2016/Bounty.Hunters',
		'_name_trailer_link' => 'Movie/Chin/2016/Bounty.Hunters/Trailer/old.mp4',
	),
);

tu_assert( Movies_WP_Trailer_Upload_Service::resolve_media_directory( 42 ) === $dir, 'resolves stored _media_directory' );
tu_assert( is_wp_error( Movies_WP_Trailer_Upload_Service::resolve_media_directory( 43 ) ), 'rejects missing directory' );
tu_assert( Movies_WP_Trailer_Upload_Service::requires_replace_confirmation( 'https://x.test' ), 'external URL needs confirm' );
tu_assert( Movies_WP_Trailer_Upload_Service::requires_replace_confirmation( 'Movie/a/Trailer/b.mp4' ), 'local path needs confirm' );
tu_assert( ! Movies_WP_Trailer_Upload_Service::requires_replace_confirmation( '' ), 'empty does not need confirm' );

$tmp = sys_get_temp_dir() . '/trailer-upload-cli-' . bin2hex( random_bytes( 4 ) ) . '.mp4';
file_put_contents( $tmp, "\x00\x00\x00\x18" . 'ftypisom' . str_repeat( "\x00", 16 ) );

echo "\nserver errors + invalid paths (no meta write)\n";
$GLOBALS['trailer_meta_writes'] = array();
$fail_upload = Movies_WP_Trailer_Upload_Service::upload_for_movie(
	42,
	$tmp,
	'official.mp4',
	false,
	array(
		'transport' => static function () {
			return array(
				'status' => 403,
				'body'   => wp_json_encode_safe( array( 'ok' => false, 'code' => 'forbidden', 'message' => 'Forbidden.' ) ),
			);
		},
	)
);
tu_assert( is_wp_error( $fail_upload ), 'server error returns WP_Error' );
tu_assert( $GLOBALS['trailer_meta_writes'] === array(), 'meta not written on server error' );

$bad_path = Movies_WP_Trailer_Upload_Service::upload_for_movie(
	42,
	$tmp,
	'official.mp4',
	false,
	array(
		'transport' => static function () use ( $dir ) {
			return array(
				'status' => 200,
				'body'   => wp_json_encode_safe( array( 'ok' => true, 'path' => $dir . '/Feature/sneaky.mp4' ) ),
			);
		},
	)
);
tu_assert( is_wp_error( $bad_path ) && 'trailer_upload_invalid_path' === $bad_path->get_error_code(), 'invalid response path rejected' );
tu_assert( $GLOBALS['trailer_meta_writes'] === array(), 'meta not written on invalid path' );

$confirm_needed = Movies_WP_Trailer_Upload_Service::upload_for_movie( 44, $tmp, 'official.mp4', false );
tu_assert(
	is_wp_error( $confirm_needed ) && 'trailer_upload_confirm_required' === $confirm_needed->get_error_code(),
	'existing trailer requires confirmation'
);
tu_assert( $GLOBALS['trailer_meta_writes'] === array(), 'meta not written when confirm missing' );

echo "\nsuccess updates only _name_trailer_link\n";
$GLOBALS['trailer_meta_writes'] = array();
$success_path = $dir . '/Trailer/official.mp4';
$ok = Movies_WP_Trailer_Upload_Service::upload_for_movie(
	42,
	$tmp,
	'official.mp4',
	false,
	array(
		'transport' => static function ( array $req ) use ( $success_path, $dir ) {
			tu_assert( isset( $req['canonical'] ) && str_contains( (string) $req['canonical'], "\nPOST\n/upload/trailer\n" ), 'transport receives POST canonical' );
			tu_assert( (string) $req['dir'] === $dir, 'transport dir matches movie directory' );
			tu_assert( preg_match( '/^[a-f0-9]{64}$/', (string) $req['sha256'] ) === 1, 'transport receives sha256' );
			return array(
				'status' => 200,
				'body'   => wp_json_encode_safe( array( 'ok' => true, 'path' => $success_path ) ),
			);
		},
	)
);
tu_assert( ! is_wp_error( $ok ) && ( $ok['path'] ?? '' ) === $success_path, 'upload_for_movie returns relative path' );
tu_assert( ( $GLOBALS['trailer_meta_store'][42]['_name_trailer_link'] ?? '' ) === $success_path, 'meta updated after success' );
tu_assert( 1 === count( $GLOBALS['trailer_meta_writes'] ), 'single meta write on success' );
tu_assert( '_name_trailer_link' === $GLOBALS['trailer_meta_writes'][0]['key'], 'only trailer link meta key written' );

$GLOBALS['trailer_meta_writes'] = array();
$replaced = Movies_WP_Trailer_Upload_Service::upload_for_movie(
	44,
	$tmp,
	'new.mp4',
	true,
	array(
		'transport' => static function () use ( $dir ) {
			return array(
				'status' => 200,
				'body'   => wp_json_encode_safe( array( 'ok' => true, 'path' => $dir . '/Trailer/new.mp4' ) ),
			);
		},
	)
);
tu_assert( ! is_wp_error( $replaced ) && ! empty( $replaced['replaced'] ), 'confirmed replace reports replaced' );
tu_assert( ( $GLOBALS['trailer_meta_store'][44]['_name_trailer_link'] ?? '' ) === $dir . '/Trailer/new.mp4', 'confirmed replace updates link' );

echo "\nAJAX handler gates (nonce, capability, post type)\n";
tu_assert( 'movies_wp_upload_trailer' === Movies_WP_Trailer_Upload_Admin::NONCE_ACTION, 'nonce action constant' );
tu_assert( 'movies_wp_upload_trailer' === Movies_WP_Trailer_Upload_Admin::AJAX_ACTION, 'ajax action constant' );

/**
 * @return array{success:bool,data:mixed,status:mixed}|null
 */
function tu_run_ajax_handler(): ?array {
	$GLOBALS['trailer_ajax_last'] = null;
	try {
		Movies_WP_Trailer_Upload_Admin::handle_ajax();
	} catch ( Trailer_Upload_Json_Exit $e ) {
		unset( $e );
		return $GLOBALS['trailer_ajax_last'];
	}
	return $GLOBALS['trailer_ajax_last'];
}

$_POST  = array( 'nonce' => 'test', 'post_id' => '42', 'confirm_replace' => '0' );
$_FILES = array();

$GLOBALS['trailer_ajax_nonce_ok'] = false;
$GLOBALS['trailer_ajax_can_edit'] = true;
$resp = tu_run_ajax_handler();
tu_assert( is_array( $resp ) && empty( $resp['success'] ), 'rejected nonce returns error JSON' );
tu_assert( is_array( $resp['data'] ?? null ) && 'trailer_upload_bad_nonce' === ( $resp['data']['code'] ?? '' ), 'rejected nonce code' );
tu_assert( 403 === (int) ( $resp['status'] ?? 0 ), 'rejected nonce HTTP 403' );

$GLOBALS['trailer_ajax_nonce_ok'] = true;
$GLOBALS['trailer_ajax_can_edit'] = false;
$_POST['post_id'] = '42';
$resp = tu_run_ajax_handler();
tu_assert( is_array( $resp ) && empty( $resp['success'] ), 'insufficient capability returns error JSON' );
tu_assert( is_array( $resp['data'] ?? null ) && 'trailer_upload_forbidden' === ( $resp['data']['code'] ?? '' ), 'insufficient capability code' );
tu_assert( 403 === (int) ( $resp['status'] ?? 0 ), 'insufficient capability HTTP 403' );

$GLOBALS['trailer_ajax_can_edit'] = true;
$_POST['post_id'] = '99'; // mapped to post type "post"
$resp = tu_run_ajax_handler();
tu_assert( is_array( $resp ) && empty( $resp['success'] ), 'invalid post type returns error JSON' );
tu_assert( is_array( $resp['data'] ?? null ) && 'trailer_upload_invalid_movie' === ( $resp['data']['code'] ?? '' ), 'invalid post type code' );

echo "\nTV show series directory\n";
$series_dir = 'series/korea/2024/Marry.My.Husband';
$GLOBALS['trailer_meta_store'][7] = array(
	'_media_directory'   => $series_dir,
	'_name_trailer_link' => '',
);
tu_assert( Movies_WP_Trailer_Upload_Client::normalize_title_directory( $series_dir ) === $series_dir, 'series directory accepted' );
tu_assert( is_wp_error( Movies_WP_Trailer_Upload_Service::resolve_directory( 'movie', 7 ) ), 'movie upload rejects series directory' );
tu_assert( Movies_WP_Trailer_Upload_Service::resolve_directory( 'tvshow', 7 ) === $series_dir, 'tvshow resolves series directory' );

$GLOBALS['trailer_meta_writes'] = array();
$tv_upload = Movies_WP_Trailer_Upload_Service::upload_for_title(
	'tvshow',
	7,
	$tmp,
	'official.mp4',
	false,
	array(
		'transport' => static function () use ( $series_dir ) {
			return array(
				'status' => 200,
				'body'   => wp_json_encode_safe( array( 'ok' => true, 'path' => $series_dir . '/Trailer/official.mp4' ) ),
			);
		},
	)
);
tu_assert( ! is_wp_error( $tv_upload ), 'tvshow upload succeeds' );
tu_assert( ( $GLOBALS['trailer_meta_store'][7]['_name_trailer_link'] ?? '' ) === $series_dir . '/Trailer/official.mp4', 'tvshow trailer link saved' );

$_POST['post_id']      = '8';
$_POST['content_type'] = 'tvshow';
$resp = tu_run_ajax_handler();
tu_assert( is_array( $resp ) && empty( $resp['success'] ), 'unknown tvshow rejected' );
tu_assert( is_array( $resp['data'] ?? null ) && 'trailer_upload_invalid_tvshow' === ( $resp['data']['code'] ?? '' ), 'unknown tvshow code' );

@unlink( $tmp );

/**
 * json_encode without depending on WordPress wp_json_encode.
 *
 * @param mixed $data
 * @return string
 */
function wp_json_encode_safe( $data ): string {
	$json = json_encode( $data );
	return is_string( $json ) ? $json : '{}';
}

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}
echo "\nAll trailer upload client/service tests passed.\n";
exit( 0 );

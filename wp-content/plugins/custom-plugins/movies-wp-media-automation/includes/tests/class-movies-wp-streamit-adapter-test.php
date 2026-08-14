<?php
/**
 * CLI tests for Movies_WP_Streamit_Adapter (plan executor).
 *
 * Run: php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-streamit-adapter-test.php
 *
 * Uses injectable stubs for Streamit APIs. Does not write real Streamit data,
 * call the media server, ffprobe, or filename parser.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-streamit-adapter-test/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		private $code;
		/** @var string */
		private $message;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'maybe_serialize' ) ) {
	function maybe_serialize( $data ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			return serialize( $data );
		}
		if ( is_string( $data ) && is_serialized( $data ) ) {
			return serialize( $data );
		}
		return $data;
	}
}

if ( ! function_exists( 'is_serialized' ) ) {
	function is_serialized( $data, $strict = true ) {
		unset( $strict );
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		return 'N;' === $data || preg_match( '/^([adObis]):/', $data ) === 1;
	}
}

/** @var array<int, array<string, mixed>> */
$GLOBALS['adapter_movie_meta'] = array();

if ( ! function_exists( 'streamit_get_movie_meta' ) ) {
	function streamit_get_movie_meta( $movie_id, $key = '', $single = false ) {
		$store = $GLOBALS['adapter_movie_meta'][ (int) $movie_id ] ?? array();
		if ( '' === $key ) {
			return $store;
		}
		if ( ! array_key_exists( $key, $store ) ) {
			return $single ? '' : array();
		}
		return $store[ $key ];
	}
}

if ( ! function_exists( 'streamit_update_movie_meta' ) ) {
	function streamit_update_movie_meta( $movie_id, $meta_key, $meta_value, $prev_value = '' ) {
		unset( $prev_value );
		$movie_id = (int) $movie_id;
		if ( ! isset( $GLOBALS['adapter_movie_meta'][ $movie_id ] ) ) {
			$GLOBALS['adapter_movie_meta'][ $movie_id ] = array();
		}
		$current = $GLOBALS['adapter_movie_meta'][ $movie_id ][ $meta_key ] ?? null;
		if ( maybe_serialize( $current ) === maybe_serialize( $meta_value ) ) {
			return false; // WP unchanged semantics.
		}
		$GLOBALS['adapter_movie_meta'][ $movie_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

/** @var array<string,int> */
$GLOBALS['adapter_forbidden_calls'] = array(
	'streamit_update_movie' => 0,
	'media_scan_movie_dir'  => 0,
	'media_ffprobe_inspect' => 0,
	'media_parse_filename'  => 0,
	'signed_subtitle_url'   => 0,
	'signed_media_url'      => 0,
	'media_api'             => 0,
);

if ( ! function_exists( 'streamit_update_movie' ) ) {
	function streamit_update_movie( $movie_id, $movie_data = array() ) {
		$GLOBALS['adapter_forbidden_calls']['streamit_update_movie']++;
		return new WP_Error( 'forbidden', 'streamit_update_movie must never be called' );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		// Adapter must use streamit_update_movie_meta / injected update_meta — not raw update_post_meta.
		$GLOBALS['adapter_forbidden_calls']['media_api']++;
		return false;
	}
}

if ( ! function_exists( 'media_scan_movie_dir' ) ) {
	function media_scan_movie_dir( $directory ) {
		$GLOBALS['adapter_forbidden_calls']['media_scan_movie_dir']++;
		return array();
	}
}

if ( ! function_exists( 'media_ffprobe_inspect' ) ) {
	function media_ffprobe_inspect( $path ) {
		$GLOBALS['adapter_forbidden_calls']['media_ffprobe_inspect']++;
		return array();
	}
}

if ( ! function_exists( 'media_parse_filename' ) ) {
	function media_parse_filename( $name ) {
		$GLOBALS['adapter_forbidden_calls']['media_parse_filename']++;
		return array();
	}
}

if ( ! function_exists( 'movies_wp_media_signed_url' ) ) {
	function movies_wp_media_signed_url( $path, $purpose = 'v' ) {
		$GLOBALS['adapter_forbidden_calls']['signed_media_url']++;
		return 'https://media.example/signed?token=fake&path=' . rawurlencode( (string) $path );
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-streamit-adapter.php';

$failures = 0;

function assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
}

function assert_eq( $expected, $actual, string $label ): void {
	assert_true( $expected === $actual, $label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' );
}

/**
 * Minimal Streamit movie stub with getters for load-merge-write.
 */
class Adapter_Test_Movie {
	/** @var array<string,mixed> */
	public $fields;

	public function __construct( array $fields ) {
		$this->fields = $fields;
	}

	public function get_post_author() {
		return $this->fields['post_author'];
	}
	public function get_post_date() {
		return $this->fields['post_date'];
	}
	public function get_post_date_gmt() {
		return $this->fields['post_date_gmt'];
	}
	public function get_post_content() {
		return $this->fields['post_content'];
	}
	public function get_post_title() {
		return $this->fields['post_title'];
	}
	public function get_post_excerpt() {
		return $this->fields['post_excerpt'];
	}
	public function get_post_status() {
		return $this->fields['post_status'];
	}
	public function get_comment_status() {
		return $this->fields['comment_status'];
	}
	public function get_ping_status() {
		return $this->fields['ping_status'];
	}
	public function get_post_password() {
		return $this->fields['post_password'];
	}
	public function get_post_name() {
		return $this->fields['post_name'];
	}
	public function get_to_ping() {
		return $this->fields['to_ping'];
	}
	public function get_pinged() {
		return $this->fields['pinged'];
	}
	public function get_post_modified() {
		return $this->fields['post_modified'];
	}
	public function get_post_modified_gmt() {
		return $this->fields['post_modified_gmt'];
	}
	public function get_post_content_filtered() {
		return $this->fields['post_content_filtered'];
	}
	public function get_post_parent() {
		return $this->fields['post_parent'];
	}
	public function get_guid() {
		return $this->fields['guid'];
	}
	public function get_menu_order() {
		return $this->fields['menu_order'];
	}
	public function get_post_type() {
		return $this->fields['post_type'];
	}
	public function get_post_mime_type() {
		return $this->fields['post_mime_type'];
	}
	public function get_comment_count() {
		return $this->fields['comment_count'];
	}
}

/**
 * @return array<string,mixed>
 */
function base_movie_fields(): array {
	return array(
		'post_author'           => 1,
		'post_date'             => '2020-01-01 10:00:00',
		'post_date_gmt'         => '2020-01-01 06:30:00',
		'post_content'          => 'Old summary',
		'post_title'            => 'Old Title',
		'post_excerpt'          => 'excerpt',
		'post_status'           => 'publish',
		'comment_status'        => 'closed',
		'ping_status'           => 'closed',
		'post_password'         => '',
		'post_name'             => 'old-slug',
		'to_ping'               => '',
		'pinged'                => '',
		'post_modified'         => '2020-01-02 10:00:00',
		'post_modified_gmt'     => '2020-01-02 06:30:00',
		'post_content_filtered' => '',
		'post_parent'           => 0,
		'guid'                  => 'guid-1',
		'menu_order'            => 0,
		'post_type'             => 'movie',
		'post_mime_type'        => '',
		'comment_count'         => 0,
	);
}

/**
 * @param array<string,mixed> $overrides
 * @return array<string,mixed>
 */
function make_plan( array $overrides = array() ): array {
	$plan = array(
		'ok'               => true,
		'ready_to_import'  => true,
		'errors'           => array(),
		'warnings'         => array(),
		'contract'         => array(
			'kind'    => 'import_plan',
			'version' => 1,
		),
		'identity'         => array(
			'action'            => 'update',
			'existing_movie_id' => 42,
			'tmdb_id'           => 999,
		),
		'metadata'         => array(
			'title'   => 'Admin Title',
			'summary' => 'Admin Summary',
		),
		'movie'            => array(
			'tmdb_id'          => 999,
			'media_directory'  => 'Movie/Iran/2024/some-slug',
		),
		'sources'          => array(),
		'subtitles'        => array(
			array(
				'media_path' => 'Movie/Iran/2024/some-slug/file.fa.srt',
				'language'   => 'fa',
			),
		),
		'subtitle_persistence' => array(
			'ready'  => true,
			'status' => 'relative_path',
			'reason' => 'relative_path',
		),
	);

	foreach ( $overrides as $key => $value ) {
		$plan[ $key ] = $value;
	}

	return $plan;
}

/**
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function harness( array &$state ): array {
	$state += array(
		'create_calls'   => 0,
		'update_rows'    => array(),
		'sources_written'=> null,
		'meta_written'   => array(),
		'existing_sources' => array(),
		'movie_fields'   => base_movie_fields(),
		'create_result'  => array( 'status' => true, 'data' => 100 ),
		'create_fail'    => false,
		'update_fail'    => false,
		'sources_fail'   => false,
		'meta_fail'      => false,
	);

	return array(
		'today'     => '2026-08-12',
		'now_local' => '2026-08-12 12:00:00',
		'now_gmt'   => '2026-08-12 08:30:00',
		'create_from_tmdb' => static function ( $tmdb_id, $args ) use ( &$state ) {
			$state['create_calls']++;
			$state['create_tmdb_id'] = $tmdb_id;
			if ( ! empty( $state['create_fail'] ) ) {
				return array( 'status' => false, 'message' => 'create boom' );
			}
			return $state['create_result'];
		},
		'get_movie' => static function ( $movie_id ) use ( &$state ) {
			$state['loaded_movie_id'] = $movie_id;
			return new Adapter_Test_Movie( $state['movie_fields'] );
		},
		'update_movie_row' => static function ( $movie_id, $payload ) use ( &$state ) {
			$state['update_rows'][] = array( 'id' => $movie_id, 'payload' => $payload );
			if ( ! empty( $state['update_fail'] ) ) {
				return new WP_Error( 'update_boom', 'update failed' );
			}
			return $movie_id;
		},
		'get_sources' => static function ( $movie_id ) use ( &$state ) {
			return $state['existing_sources'];
		},
		'update_sources' => static function ( $movie_id, $merged ) use ( &$state ) {
			if ( ! empty( $state['sources_fail'] ) ) {
				return false;
			}
			$state['sources_written'] = $merged;
			$state['sources_movie_id'] = $movie_id;
			return true;
		},
		'update_meta' => static function ( $movie_id, $key, $value ) use ( &$state ) {
			if ( ! empty( $state['meta_fail'] ) && '_media_directory' === $key ) {
				return false;
			}
			if ( ! empty( $state['subtitles_fail'] ) && '_subtitles' === $key ) {
				return false;
			}
			$state['meta_written'][ $key ] = array( 'movie_id' => $movie_id, 'value' => $value );
			return true;
		},
		'get_subtitles' => static function ( $movie_id ) use ( &$state ) {
			if ( isset( $state['meta_written']['_subtitles']['value'] ) && is_array( $state['meta_written']['_subtitles']['value'] ) ) {
				return $state['meta_written']['_subtitles']['value'];
			}
			return isset( $state['existing_subtitles'] ) && is_array( $state['existing_subtitles'] )
				? $state['existing_subtitles']
				: array();
		},
	);
}

function assert_forbidden_never_called( string $suffix = '' ): void {
	$c = $GLOBALS['adapter_forbidden_calls'];
	assert_eq( 0, $c['streamit_update_movie'], 'never streamit_update_movie' . $suffix );
	assert_eq( 0, $c['media_scan_movie_dir'], 'never media_scan_movie_dir' . $suffix );
	assert_eq( 0, $c['media_ffprobe_inspect'], 'never media_ffprobe_inspect' . $suffix );
	assert_eq( 0, $c['media_parse_filename'], 'never media_parse_filename' . $suffix );
	assert_eq( 0, $c['signed_subtitle_url'], 'never signed subtitle URL' . $suffix );
	assert_eq( 0, $c['signed_media_url'], 'never signed media URL' . $suffix );
	assert_eq( 0, $c['media_api'], 'never media API' . $suffix );
}

echo "Movies_WP_Streamit_Adapter tests\n";

// ---------------------------------------------------------------------------
// 1–3. Validation / rejection
// ---------------------------------------------------------------------------
echo "\n[reject]\n";
{
	$state = array();
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply( make_plan( array( 'ok' => false ) ), $opts );
	assert_true( empty( $r['ok'] ), '1. invalid plan rejected' );
	assert_eq( 'validate', $r['failed_step'] ?? null, '1. failed_step=validate' );
}
{
	$state = array();
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply( make_plan( array( 'ready_to_import' => false ) ), $opts );
	assert_true( empty( $r['ok'] ), '2. ready_to_import=false rejected' );
}
{
	$state = array();
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply(
		make_plan(
			array(
				'errors' => array( array( 'code' => 'x', 'message' => 'blocked' ) ),
			)
		),
		$opts
	);
	assert_true( empty( $r['ok'] ), '3. plan errors rejected' );
	assert_eq( 'media_adapter_plan_has_errors', $r['error']['code'] ?? null, '3. error code' );
}

// ---------------------------------------------------------------------------
// 4. Create path
// ---------------------------------------------------------------------------
echo "\n[create]\n";
{
	$state = array();
	$opts  = harness( $state );
	$plan  = make_plan(
		array(
			'identity' => array(
				'action'  => 'create',
				'tmdb_id' => 999,
			),
			'sources'  => array(
				array(
					'action'           => 'add',
					'identity_key'     => 'Movie/Iran/2024/some-slug/video.mkv',
					'media_path'       => 'Movie/Iran/2024/some-slug/video.mkv',
					'name'             => 'AirenTeam',
					'quality'          => '1080p',
					'file_size'        => '1.2 GB',
					'language'         => null,
					'link'             => 'Movie/Iran/2024/some-slug/video.mkv',
					'download_content' => 'Movie/Iran/2024/some-slug/video.mkv',
				),
			),
		)
	);
	$before = json_encode( $plan );
	$r      = Movies_WP_Streamit_Adapter::apply( $plan, $opts );
	assert_true( ! empty( $r['ok'] ), '4. create path ok' );
	assert_eq( 100, $r['movie_id'] ?? null, '4. movie_id from create' );
	assert_eq( 'create', $r['identity_action'] ?? null, '4. identity_action=create' );
	assert_eq( 1, $state['create_calls'], '4. insert_movie_tmdb called once' );
	assert_eq( 999, $state['create_tmdb_id'] ?? null, '4. tmdb id passed' );
	assert_eq( $before, json_encode( $plan ), '36. no plan mutation on create' );
	assert_true( in_array( 'movie', $r['completed'], true ), '41. completed includes movie' );
	assert_true( in_array( 'metadata', $r['completed'], true ), '41. completed includes metadata' );
	assert_true( in_array( 'sources', $r['completed'], true ), '41. completed includes sources' );
	assert_true( in_array( 'media_directory', $r['completed'], true ), '41. completed includes media_directory' );
	assert_true( in_array( 'subtitles', $r['completed'], true ), '41. completed includes subtitles' );
	assert_eq( array(), $r['deferred'] ?? null, '32. no deferred steps on success' );
}

// ---------------------------------------------------------------------------
// 5–10. Update path + full-row overlay
// ---------------------------------------------------------------------------
echo "\n[update]\n";
{
	$state = array();
	$opts  = harness( $state );
	$plan  = make_plan(
		array(
			'sources' => array(
				array(
					'action'           => 'keep_existing',
					'identity_key'     => 'Movie/Iran/2024/some-slug/old.mkv',
					'media_path'       => 'Movie/Iran/2024/some-slug/old.mkv',
					'name'             => 'Manual',
					'quality'          => '720p',
					'file_size'        => '800 MB',
					'language'         => null,
					'link'             => 'Movie/Iran/2024/some-slug/old.mkv',
					'download_content' => 'Movie/Iran/2024/some-slug/old.mkv',
				),
			),
		)
	);
	$state['existing_sources'] = array(
		array(
			'name'             => 'Manual',
			'link'             => 'Movie/Iran/2024/some-slug/old.mkv',
			'is_affiliate'     => '0',
			'quality'          => '720p',
			'language'         => 'English',
			'player'           => '',
			'date_added'       => '2024-01-01',
			'download_content' => 'Movie/Iran/2024/some-slug/old.mkv',
			'file_size'        => '800 MB',
			'custom_extra'     => 'keep-me',
		),
	);
	$r = Movies_WP_Streamit_Adapter::apply( $plan, $opts );
	assert_true( ! empty( $r['ok'] ), '5. update path ok' );
	assert_eq( 42, $r['movie_id'] ?? null, '5. existing movie id' );
	assert_eq( 0, $state['create_calls'], '5. create not called on update' );
	assert_eq( 1, count( $state['update_rows'] ), '7. full-row update called once' );
	$payload = $state['update_rows'][0]['payload'];
	assert_eq( 'Admin Title', $payload['post_title'], '8. title overlay' );
	assert_eq( 'Admin Summary', $payload['post_content'], '9. summary overlay' );
	assert_eq( 'old-slug', $payload['post_name'], '10. post_name preserved' );
	assert_eq( 'publish', $payload['post_status'], '10. post_status preserved' );
	assert_eq( '2020-01-01 10:00:00', $payload['post_date'], '10. post_date preserved' );
	assert_eq( 1, $payload['post_author'], '10. post_author preserved' );
	assert_eq( 'excerpt', $payload['post_excerpt'], '10. excerpt preserved' );
	assert_eq( '2026-08-12 12:00:00', $payload['post_modified'], '10. post_modified updated' );
}

// ---------------------------------------------------------------------------
// 6. Duplicate identity is a plan concern — adapter trusts ready_to_import
// ---------------------------------------------------------------------------
echo "\n[duplicate-identity-plan]\n";
{
	// Plan already blocked → adapter rejects via ready/errors, does not invent lookup.
	$state = array();
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply(
		make_plan(
			array(
				'ok'              => false,
				'ready_to_import' => false,
				'errors'          => array(
					array( 'code' => 'duplicate_tmdb_id', 'message' => 'duplicates' ),
				),
				'identity'        => array(
					'action' => 'blocked',
				),
			)
		),
		$opts
	);
	assert_true( empty( $r['ok'] ), '6. duplicate identity rejected before adapter apply' );
	assert_eq( 0, $state['create_calls'], '6. no create on blocked plan' );
}

// ---------------------------------------------------------------------------
// 11–26. Source merge behaviors
// ---------------------------------------------------------------------------
echo "\n[sources]\n";
{
	$path_a = 'Movie/Iran/2024/some-slug/a.mkv';
	$path_b = 'Movie/Iran/2024/some-slug/b.mkv';
	$path_c = 'Movie/Iran/2024/some-slug/c.mkv';

	$state = array();
	$opts  = harness( $state );
	$state['existing_sources'] = array(
		array(
			'name'             => 'ManualName',
			'link'             => $path_a,
			'is_affiliate'     => '1',
			'quality'          => '720p',
			'language'         => 'English',
			'player'           => 'old-player',
			'date_added'       => '2023-01-01',
			'download_content' => $path_a,
			'file_size'        => '500 MB',
			'custom_extra'     => 'preserve-me',
			'another_extra'    => 123,
		),
		array(
			'name'             => 'KeepMe',
			'link'             => $path_c,
			'is_affiliate'     => '0',
			'quality'          => '480p',
			'language'         => '',
			'player'           => '',
			'date_added'       => '2023-02-01',
			'download_content' => $path_c,
			'file_size'        => '200 MB',
			'orphan_flag'      => true,
		),
		// Same quality as path_a candidate — identity must NOT use quality.
		array(
			'name'             => 'DifferentPathSameQuality',
			'link'             => 'Movie/Iran/2024/other/x.mkv',
			'is_affiliate'     => '0',
			'quality'          => '1080p',
			'language'         => '',
			'player'           => '',
			'date_added'       => '2023-03-01',
			'download_content' => 'Movie/Iran/2024/other/x.mkv',
			'file_size'        => '1 GB',
		),
	);

	$plan = make_plan(
		array(
			'sources' => array(
				array(
					'action'           => 'update',
					'identity_key'     => $path_a,
					'media_path'       => $path_a,
					'name'             => '', // empty encoder → preserve ManualName
					'encoder'          => null,
					'quality'          => '1080p',
					'file_size'        => '1.5 GB',
					'language'         => null,
					'link'             => $path_a,
					'download_content' => $path_a,
					'provider'         => 'Netflix',
					'release'          => array( 'release_group' => 'SS', 'group_hint' => 'MARK' ),
				),
				array(
					'action'           => 'add',
					'identity_key'     => $path_b,
					'media_path'       => $path_b,
					'name'             => 'AirenTeam',
					'encoder'          => 'AirenTeam',
					'quality'          => '1080p',
					'file_size'        => '2.0 GB',
					'language'         => null,
					'link'             => $path_b,
					'download_content' => $path_b,
					'provider'         => 'Amazon',
				),
				array(
					'action'           => 'keep_existing',
					'identity_key'     => $path_c,
					'media_path'       => $path_c,
					'name'             => 'KeepMe',
					'quality'          => '480p',
					'file_size'        => '200 MB',
					'language'         => null,
					'link'             => $path_c,
					'download_content' => $path_c,
				),
				array(
					'action'           => 'keep_existing',
					'identity_key'     => 'Movie/Iran/2024/other/x.mkv',
					'media_path'       => 'Movie/Iran/2024/other/x.mkv',
					'name'             => 'DifferentPathSameQuality',
					'quality'          => '1080p',
					'file_size'        => '1 GB',
					'language'         => null,
					'link'             => 'Movie/Iran/2024/other/x.mkv',
					'download_content' => 'Movie/Iran/2024/other/x.mkv',
				),
			),
		)
	);

	$r = Movies_WP_Streamit_Adapter::apply( $plan, $opts );
	assert_true( ! empty( $r['ok'] ), 'sources apply ok' );
	$written = $state['sources_written'];
	assert_true( is_array( $written ), 'sources written' );
	assert_eq( 4, count( $written ), '14. unmatched existing never deleted (4 rows)' );

	$row_a = $written[0];
	assert_eq( 'ManualName', $row_a['name'], '15. manual existing name preserved when encoder empty' );
	assert_eq( '1080p', $row_a['quality'], '11/12. _source update quality from plan' );
	assert_eq( '1.5 GB', $row_a['file_size'], '23. file_size mapping' );
	assert_eq( '', $row_a['language'], '24. language remains null/empty' );
	assert_eq( 'preserve-me', $row_a['custom_extra'], '26. unknown existing _source fields preserved' );
	assert_eq( 123, $row_a['another_extra'], '26. another unknown field preserved' );
	assert_eq( '1', $row_a['is_affiliate'], 'affiliate preserved on update' );
	assert_true( ! array_key_exists( 'media_path', $row_a ), '25. media_path NOT added to _source' );
	assert_true( ! array_key_exists( 'provider', $row_a ), '18. no provider → name / not stored' );
	assert_true( ! isset( $row_a['release_group'] ), '19. no release_group → name / not stored' );

	$row_c = null;
	$row_b = null;
	$row_x = null;
	foreach ( $written as $row ) {
		if ( ( $row['link'] ?? '' ) === $path_c ) {
			$row_c = $row;
		}
		if ( ( $row['link'] ?? '' ) === $path_b ) {
			$row_b = $row;
		}
		if ( ( $row['link'] ?? '' ) === 'Movie/Iran/2024/other/x.mkv' ) {
			$row_x = $row;
		}
	}
	assert_true( is_array( $row_c ), '13. keep_existing row present' );
	assert_eq( 'KeepMe', $row_c['name'], '13. keep_existing name unchanged' );
	assert_eq( true, $row_c['orphan_flag'], '13. keep_existing extras unchanged' );

	assert_true( is_array( $row_b ), '11. _source add present' );
	assert_eq( 'AirenTeam', $row_b['name'], '16. approved encoder replaces name' );
	assert_eq( '0', $row_b['is_affiliate'], 'new row affiliate default' );
	assert_eq( '2026-08-12', $row_b['date_added'], 'new row date_added default' );
	assert_eq( $path_b, $row_b['link'], '21. source identity uses normalized link' );
	assert_eq( $path_b, $row_b['download_content'], '21. download_content = path' );
	assert_true( ! array_key_exists( 'media_path', $row_b ), '25. add without media_path key' );
	assert_true( ! array_key_exists( 'provider', $row_b ), '18. provider not written on add' );

	assert_true( is_array( $row_x ), '22. quality does not determine identity (other 1080p kept separate)' );
	assert_eq( 'DifferentPathSameQuality', $row_x['name'], '22. separate identity by path' );
}

// ---------------------------------------------------------------------------
// 16–20. Encoder / name rules
// ---------------------------------------------------------------------------
echo "\n[name-rules]\n";
{
	$path = 'Movie/Iran/2024/some-slug/n.mkv';
	$state = array();
	$opts  = harness( $state );
	$state['existing_sources'] = array(
		array(
			'name'             => 'OldManual',
			'link'             => $path,
			'download_content' => $path,
			'quality'          => '720p',
			'is_affiliate'     => '0',
			'language'         => '',
			'player'           => '',
			'date_added'       => '2020-01-01',
			'file_size'        => '1 GB',
		),
	);
	$plan = make_plan(
		array(
			'sources' => array(
				array(
					'action'           => 'update',
					'identity_key'     => $path,
					'media_path'       => $path,
					'name'             => 'tG1R0',
					'encoder'          => 'tG1R0',
					'quality'          => '1080p',
					'file_size'        => '2 GB',
					'language'         => null,
					'link'             => $path,
					'download_content' => $path,
					'provider'         => 'Hulu',
					'release'          => array( 'release_group' => 'SS', 'group_hint' => 'MARK' ),
				),
			),
		)
	);
	$r = Movies_WP_Streamit_Adapter::apply( $plan, $opts );
	assert_eq( 'tG1R0', $state['sources_written'][0]['name'] ?? null, '16. approved encoder replaces name' );
	assert_true( ( $state['sources_written'][0]['name'] ?? null ) !== 'Unknown', '17. unknown encoder never becomes Unknown' );
	assert_true( ( $state['sources_written'][0]['name'] ?? null ) !== 'Hulu', '18. no provider → name' );
	assert_true( ( $state['sources_written'][0]['name'] ?? null ) !== 'SS', '20. no SS → name' );
	assert_true( ( $state['sources_written'][0]['name'] ?? null ) !== 'MARK', '19. no release_group/hint → name' );
}
{
	$path = 'Movie/Iran/2024/some-slug/empty.mkv';
	$state = array();
	$opts  = harness( $state );
	$plan  = make_plan(
		array(
			'sources' => array(
				array(
					'action'           => 'add',
					'identity_key'     => $path,
					'media_path'       => $path,
					'name'             => '',
					'encoder'          => null,
					'quality'          => '1080p',
					'file_size'        => '1 GB',
					'language'         => null,
					'link'             => $path,
					'download_content' => $path,
					'provider'         => 'Netflix',
					'release'          => array( 'release_group' => 'SS', 'group_hint' => 'AirenTeam' ),
				),
			),
		)
	);
	Movies_WP_Streamit_Adapter::apply( $plan, $opts );
	$name = $state['sources_written'][0]['name'] ?? null;
	assert_eq( '', $name, '17. empty encoder stays empty (not Unknown)' );
	assert_true( $name !== 'Netflix' && $name !== 'SS' && $name !== 'AirenTeam', '18-20. no provider/SS/group as name on add' );
}

// ---------------------------------------------------------------------------
// 21. Identity prefers normalized link / download_content
// ---------------------------------------------------------------------------
echo "\n[identity]\n";
{
	$path = 'Movie/Iran/2024/some-slug/id.mkv';
	$state = array();
	$opts  = harness( $state );
	$state['existing_sources'] = array(
		array(
			'name'             => 'ByDownload',
			'link'             => '',
			'download_content' => '/' . $path,
			'quality'          => '720p',
			'is_affiliate'     => '0',
			'language'         => '',
			'player'           => '',
			'date_added'       => '2020-01-01',
			'file_size'        => '1 GB',
			'custom'           => 'yes',
		),
	);
	$plan = make_plan(
		array(
			'sources' => array(
				array(
					'action'           => 'update',
					'identity_key'     => $path,
					'media_path'       => $path,
					'name'             => 'Enc',
					'quality'          => '1080p',
					'file_size'        => '2 GB',
					'language'         => null,
					'link'             => $path,
					'download_content' => $path,
				),
			),
		)
	);
	Movies_WP_Streamit_Adapter::apply( $plan, $opts );
	assert_eq( 1, count( $state['sources_written'] ), '21. matched via normalized download_content' );
	assert_eq( 'Enc', $state['sources_written'][0]['name'], '21. updated matched row' );
	assert_eq( 'yes', $state['sources_written'][0]['custom'], '21. preserved after identity match' );
}

// ---------------------------------------------------------------------------
// 27–29. media_directory
// ---------------------------------------------------------------------------
echo "\n[media-directory]\n";
{
	$state = array();
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply( make_plan(), $opts );
	assert_true( ! empty( $r['ok'] ), '27. media_directory saved ok' );
	assert_eq( 'Movie/Iran/2024/some-slug', $state['meta_written']['_media_directory']['value'] ?? null, '28. relative Movie/... path preserved' );
	$val = (string) ( $state['meta_written']['_media_directory']['value'] ?? '' );
	assert_true( ! str_starts_with( $val, '/' ) && ! str_contains( $val, '/data' ), '29. no absolute /data path stored' );
}
{
	$state = array();
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply(
		make_plan( array( 'movie' => array( 'tmdb_id' => 999, 'media_directory' => '/data/Movie/x' ) ) ),
		$opts
	);
	assert_true( empty( $r['ok'] ), '29. absolute /data directory rejected' );
}

// ---------------------------------------------------------------------------
// Default stream meta (_movie_choice / _movie_url_link)
// ---------------------------------------------------------------------------
echo "\n[default-stream]\n";
{
	$first = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.1080p.KNPSK.WEB-DL.DDP5.1.x264-tG1R0.mkv';
	$second = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.480p.KNPSK.WEB-DL.mkv';
	$state  = array();
	$opts   = harness( $state );
	$plan   = make_plan(
		array(
			'sources' => array(
				array(
					'action'           => 'add',
					'media_path'       => $first,
					'identity_key'     => $first,
					'name'             => '',
					'quality'          => '1080p',
					'language'         => null,
					'link'             => $first,
					'download_content' => $first,
				),
				array(
					'action'           => 'add',
					'media_path'       => $second,
					'identity_key'     => $second,
					'name'             => '',
					'quality'          => '480p',
					'language'         => null,
					'link'             => $second,
					'download_content' => $second,
				),
			),
		)
	);
	$r = Movies_WP_Streamit_Adapter::apply( $plan, $opts );
	assert_true( ! empty( $r['ok'] ), 'default_stream apply ok' );
	assert_eq( 'movie_url', $state['meta_written']['_movie_choice']['value'] ?? null, 'default_stream _movie_choice=movie_url' );
	assert_eq( $first, $state['meta_written']['_movie_url_link']['value'] ?? null, 'default_stream _movie_url_link=first Movie/... link' );
	$link = (string) ( $state['meta_written']['_movie_url_link']['value'] ?? '' );
	assert_true( str_starts_with( $link, 'Movie/' ), 'default_stream link starts with Movie/' );
	assert_true( ! str_starts_with( $link, '/' ) && ! str_contains( $link, '/data' ), 'default_stream no /data path' );
	assert_true( ! str_starts_with( $link, 'http' ) && ! str_contains( $link, 'token=' ), 'default_stream no signed URL value' );
	assert_eq( 0, $GLOBALS['adapter_forbidden_calls']['signed_media_url'], 'default_stream never called movies_wp_media_signed_url' );
	assert_eq( '', $state['sources_written'][0]['name'] ?? null, 'default_stream does not alter _source name' );
	assert_eq( $first, $state['sources_written'][0]['link'] ?? null, 'default_stream leaves _source link unchanged' );
	assert_true( in_array( 'default_stream', $r['completed'], true ), 'default_stream completed step recorded' );
}

// ---------------------------------------------------------------------------
// Unchanged _source write (WP update_metadata false) must not fail import
// ---------------------------------------------------------------------------
echo "\n[sources-unchanged]\n";
{
	$path  = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.1080p.mkv';
	$row   = array(
		'name'             => '',
		'link'             => $path,
		'is_affiliate'     => '0',
		'quality'          => '1080p',
		'language'         => '',
		'player'           => '',
		'date_added'       => '2026-08-12',
		'download_content' => $path,
		'file_size'        => '',
	);
	$GLOBALS['adapter_movie_meta'][42] = array(
		'_source' => array( $row ),
	);
	$state = array();
	$opts  = harness( $state );
	unset( $opts['update_sources'], $opts['update_meta'], $opts['get_sources'] );
	$opts['get_sources'] = static function ( $movie_id ) {
		$raw = streamit_get_movie_meta( (int) $movie_id, '_source', true );
		return is_array( $raw ) ? $raw : array();
	};
	$plan = make_plan(
		array(
			'sources' => array(
				array(
					'action'           => 'update',
					'media_path'       => $path,
					'identity_key'     => $path,
					'name'             => '',
					'quality'          => '1080p',
					'language'         => null,
					'link'             => $path,
					'download_content' => $path,
					'file_size'        => '',
				),
			),
		)
	);
	$r = Movies_WP_Streamit_Adapter::apply( $plan, $opts );
	assert_true( ! empty( $r['ok'] ), 'unchanged _source does not fail import' );
	assert_true( in_array( 'sources', $r['completed'], true ), 'unchanged sources step completed' );
	assert_true( in_array( 'default_stream', $r['completed'], true ), 'unchanged continues to default_stream' );
	assert_eq( 'movie_url', streamit_get_movie_meta( 42, '_movie_choice', true ), 'unchanged path still sets _movie_choice' );
	assert_eq( $path, streamit_get_movie_meta( 42, '_movie_url_link', true ), 'unchanged path still sets _movie_url_link' );
}

// ---------------------------------------------------------------------------
// 30–35. Subtitles persistence (relative path only)
// ---------------------------------------------------------------------------
echo "\n[subtitles-persist]\n";
{
	$state = array();
	$opts  = harness( $state );
	$path  = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.WEB-DL.srt';
	$r     = Movies_WP_Streamit_Adapter::apply(
		make_plan(
			array(
				'subtitles' => array(
					array(
						'media_path' => $path,
						'language'   => null,
						'url_plan'   => array(
							'value'  => $path,
							'signed' => false,
							'ready'  => true,
						),
					),
				),
			)
		),
		$opts
	);
	assert_true( ! empty( $r['ok'] ), '30. persist relative subtitle ok' );
	assert_true( in_array( 'subtitles', $r['completed'], true ), '30. completed includes subtitles' );
	assert_eq( array(), $r['deferred'] ?? null, '30. deferred empty' );
	assert_true( isset( $state['meta_written']['_subtitles']['value'] ), '30. _subtitles written' );
	$rows = $state['meta_written']['_subtitles']['value'];
	assert_eq( 1, count( $rows ), '30. one subtitle row' );
	assert_eq( $path, $rows[0]['url'] ?? null, '30. relative Movie/... path stored' );
	assert_true( ! str_contains( (string) ( $rows[0]['url'] ?? '' ), '/data/' ), '30. never /data/' );
	assert_true( ! preg_match( '#https?://#i', (string) ( $rows[0]['url'] ?? '' ) ), '30. no signed/http URL' );
	assert_true( ! str_contains( (string) ( $rows[0]['url'] ?? '' ), '/v/' ), '30. no /v/ token path' );
	assert_eq( '', $rows[0]['srclang'] ?? 'x', '30. unknown language stays empty' );
	assert_eq( '', $rows[0]['label'] ?? 'x', '30. unknown label stays empty' );
	assert_eq( 0, $GLOBALS['adapter_forbidden_calls']['signed_subtitle_url'], '30. no signed URL during import' );
}
{
	$state = array();
	$opts  = harness( $state );
	$path  = 'Movie/Iran/2024/some-slug/file.fa.srt';
	$r     = Movies_WP_Streamit_Adapter::apply(
		make_plan(
			array(
				'subtitles' => array(
					array( 'media_path' => $path, 'language' => 'fa' ),
				),
			)
		),
		$opts
	);
	assert_true( ! empty( $r['ok'] ), '31. known language persist ok' );
	$row = $state['meta_written']['_subtitles']['value'][0];
	assert_eq( 'fa', $row['srclang'] ?? null, '31. known language preserved' );
	assert_eq( 'FA', $row['label'] ?? null, '31. label from language' );
	assert_eq( $path, $row['url'] ?? null, '31. relative path' );
}
{
	// Re-import: update matching path language when known; keep unmatched existing.
	$state = array(
		'existing_subtitles' => array(
			array(
				'label'   => 'EN',
				'srclang' => 'en',
				'url'     => 'Movie/Iran/2024/some-slug/file.en.srt',
				'default' => 0,
				'format'  => 'SRT',
			),
			array(
				'label'   => 'OLD',
				'srclang' => 'xx',
				'url'     => 'Movie/Iran/2024/some-slug/manual.srt',
				'default' => 0,
				'format'  => 'SRT',
			),
		),
	);
	$opts = harness( $state );
	$r    = Movies_WP_Streamit_Adapter::apply(
		make_plan(
			array(
				'subtitles' => array(
					array(
						'media_path' => 'Movie/Iran/2024/some-slug/file.en.srt',
						'language'   => 'en',
						'format'     => 'srt',
					),
					array(
						'media_path' => 'Movie/Iran/2024/some-slug/file.fa.srt',
						'language'   => 'fa',
					),
				),
			)
		),
		$opts
	);
	assert_true( ! empty( $r['ok'] ), '33. re-import merge ok' );
	$rows = $state['meta_written']['_subtitles']['value'];
	assert_eq( 3, count( $rows ), '33. updated + kept + added' );
	$by_url = array();
	foreach ( $rows as $row ) {
		$by_url[ $row['url'] ] = $row;
	}
	assert_true( isset( $by_url['Movie/Iran/2024/some-slug/manual.srt'] ), '33. unmatched existing kept' );
	assert_eq( 'xx', $by_url['Movie/Iran/2024/some-slug/manual.srt']['srclang'], '33. kept row language preserved' );
	assert_eq( 'en', $by_url['Movie/Iran/2024/some-slug/file.en.srt']['srclang'], '33. matched row updated' );
	assert_eq( 'fa', $by_url['Movie/Iran/2024/some-slug/file.fa.srt']['srclang'], '33. new row added' );
	assert_eq( 1, $r['subtitle_stats']['updated'] ?? null, '33. stats updated' );
	assert_eq( 1, $r['subtitle_stats']['kept'] ?? null, '33. stats kept' );
	assert_eq( 1, $r['subtitle_stats']['added'] ?? null, '33. stats added' );
}
{
	// Re-import with unknown plan language must not invent / wipe existing known lang.
	$state = array(
		'existing_subtitles' => array(
			array(
				'label'   => 'FA',
				'srclang' => 'fa',
				'url'     => 'Movie/Iran/2024/some-slug/file.srt',
				'default' => 0,
				'format'  => '',
			),
		),
	);
	$opts = harness( $state );
	$r    = Movies_WP_Streamit_Adapter::apply(
		make_plan(
			array(
				'subtitles' => array(
					array(
						'media_path' => 'Movie/Iran/2024/some-slug/file.srt',
						'language'   => null,
					),
				),
			)
		),
		$opts
	);
	assert_true( ! empty( $r['ok'] ), '34. unknown plan lang merge ok' );
	$row = $state['meta_written']['_subtitles']['value'][0];
	assert_eq( 'fa', $row['srclang'] ?? null, '34. existing language preserved when plan unknown' );
}
{
	$state = array( 'subtitles_fail' => true );
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply(
		make_plan(
			array(
				'subtitles' => array(
					array( 'media_path' => 'Movie/Iran/2024/some-slug/a.fa.srt', 'language' => 'fa' ),
				),
			)
		),
		$opts
	);
	assert_true( empty( $r['ok'] ), '35. subtitle persist failure' );
	assert_eq( 'subtitles', $r['failed_step'] ?? null, '35. failed_step=subtitles' );
	assert_true( in_array( 'media_directory', $r['completed'], true ), '35. prior steps completed' );
	assert_true( ! in_array( 'subtitles', $r['completed'] ?? array(), true ), '35. subtitles not falsely completed' );
	assert_eq( array(), $r['deferred'] ?? null, '35. failure does not claim deferred success' );
}
{
	$state = array();
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply(
		make_plan(
			array(
				'subtitle_persistence' => array(
					'ready'  => false,
					'status' => 'deferred',
					'reason' => 'gate_closed',
				),
			)
		),
		$opts
	);
	assert_true( empty( $r['ok'] ), '35b. gate closed rejects' );
	assert_eq( 'validate', $r['failed_step'] ?? null, '35b. failed_step=validate' );
	assert_eq( 'media_adapter_subtitles_not_ready', $r['error']['code'] ?? null, '35b. error code' );
}

echo "\n[subtitles-unassociated-plan-row]\n";
{
	$state = array();
	$opts  = harness( $state );
	$path  = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.WEB-DL.srt';
	$r     = Movies_WP_Streamit_Adapter::apply(
		make_plan(
			array(
				'subtitles' => array(
					array(
						'media_path'  => $path,
						'language'    => null,
						'format'      => 'srt',
						'action'      => 'add',
						'persistence' => 'relative_path',
						'association' => null,
						'reason'      => 'unassociated_movie_directory',
						'url_plan'    => array(
							'storage'     => 'relative_path',
							'value'       => $path,
							'signed'      => false,
							'ready'       => true,
							'render_time' => 'streamit_child_resolve_subtitle_url',
						),
					),
				),
			)
		),
		$opts
	);
	assert_true( ! empty( $r['ok'] ), 'unassociated plan row persist ok' );
	assert_true( in_array( 'subtitles', $r['completed'], true ), 'unassociated row completed subtitles' );
	$row = $state['meta_written']['_subtitles']['value'][0];
	assert_eq( $path, $row['url'] ?? null, 'unassociated relative Movie/... persisted' );
	assert_true( ! str_contains( (string) $row['url'], '/data/' ), 'unassociated no /data/' );
	assert_true( ! str_contains( (string) $row['url'], '/v/' ), 'unassociated no /v/' );
	assert_true( ! str_contains( (string) $row['url'], '/d/' ), 'unassociated no /d/' );
	assert_eq( '', $row['srclang'] ?? 'x', 'unassociated unknown language empty' );
	assert_eq( 0, $GLOBALS['adapter_forbidden_calls']['signed_subtitle_url'], 'unassociated no signed URL at import' );
}
{
	$state = array(
		'existing_subtitles' => array(
			array(
				'label'   => 'MANUAL',
				'srclang' => 'en',
				'url'     => 'Movie/Korea/2022/Decision.to.Leave/manual.en.srt',
				'default' => 0,
				'format'  => 'SRT',
			),
		),
	);
	$opts = harness( $state );
	$path = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.WEB-DL.srt';
	$r    = Movies_WP_Streamit_Adapter::apply(
		make_plan(
			array(
				'subtitles' => array(
					array(
						'media_path'  => $path,
						'language'    => null,
						'association' => null,
						'reason'      => 'unassociated_movie_directory',
					),
				),
			)
		),
		$opts
	);
	assert_true( ! empty( $r['ok'] ), 'unassociated re-import merge ok' );
	$rows = $state['meta_written']['_subtitles']['value'];
	assert_eq( 2, count( $rows ), 'existing row kept + unassociated added' );
	$by_url = array();
	foreach ( $rows as $row ) {
		$by_url[ $row['url'] ] = $row;
	}
	assert_true( isset( $by_url['Movie/Korea/2022/Decision.to.Leave/manual.en.srt'] ), 'existing subtitle preserved' );
	assert_eq( 'en', $by_url['Movie/Korea/2022/Decision.to.Leave/manual.en.srt']['srclang'], 'existing language preserved' );
	assert_eq( $path, $by_url[ $path ]['url'] ?? null, 'unassociated WEB-DL.srt added' );
	assert_eq( '', $by_url[ $path ]['srclang'] ?? 'x', 'added unknown language empty' );
}

// ---------------------------------------------------------------------------
// 37–40 / 42. Failure paths
// ---------------------------------------------------------------------------
echo "\n[failures]\n";
{
	$state = array( 'create_fail' => true );
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply(
		make_plan( array( 'identity' => array( 'action' => 'create', 'tmdb_id' => 1 ) ) ),
		$opts
	);
	assert_true( empty( $r['ok'] ), '37. create failure returned' );
	assert_eq( 'movie', $r['failed_step'] ?? null, '42. failed_step=movie' );
	assert_eq( array(), $r['completed'] ?? null, '37. no completed steps' );
}
{
	$state = array( 'update_fail' => true );
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply( make_plan(), $opts );
	assert_true( empty( $r['ok'] ), '38. update failure returned' );
	assert_eq( 'metadata', $r['failed_step'] ?? null, '42. failed_step=metadata' );
	assert_true( in_array( 'movie', $r['completed'], true ), '38. movie completed before metadata fail' );
	assert_eq( 42, $r['movie_id'] ?? null, '38. movie_id reported on failure' );
}
{
	$state = array( 'sources_fail' => true );
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply( make_plan(), $opts );
	assert_true( empty( $r['ok'] ), '39. source persistence failure returned' );
	assert_eq( 'sources', $r['failed_step'] ?? null, '42. failed_step=sources' );
	assert_true( in_array( 'metadata', $r['completed'], true ), '39. metadata completed' );
}
{
	$state = array( 'meta_fail' => true );
	$opts  = harness( $state );
	$r     = Movies_WP_Streamit_Adapter::apply( make_plan(), $opts );
	assert_true( empty( $r['ok'] ), '40. media directory failure returned' );
	assert_eq( 'media_directory', $r['failed_step'] ?? null, '42. failed_step=media_directory' );
	assert_true( in_array( 'sources', $r['completed'], true ), '40. sources completed before directory fail' );
	assert_true( ! in_array( 'subtitles', $r['completed'] ?? array(), true ), '40. subtitles not run after directory fail' );
}

// ---------------------------------------------------------------------------
// 33–36. Forbidden calls + plan immutability (final sweep)
// ---------------------------------------------------------------------------
echo "\n[no-rediscovery]\n";
assert_forbidden_never_called( ' (final)' );

// Intentionally call stubs to prove counters work — then reset expectation that adapter didn't.
media_parse_filename( 'x' );
assert_eq( 1, $GLOBALS['adapter_forbidden_calls']['media_parse_filename'], 'spy works' );
$GLOBALS['adapter_forbidden_calls']['media_parse_filename'] = 0;

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}
echo "All adapter assertions passed.\n";
exit( 0 );

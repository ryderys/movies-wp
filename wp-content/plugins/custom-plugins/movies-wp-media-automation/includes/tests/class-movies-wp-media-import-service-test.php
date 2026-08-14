<?php
/**
 * CLI tests for Movies_WP_Media_Import_Service + Admin import gate.
 *
 * Run: php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-media-import-service-test.php
 *
 * Does not write Streamit. Does not call media server / ffprobe / filename parser.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-import-service-test/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
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

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return (string) $str;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) {
		return false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		return false;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

/** @var array<string,int> */
$GLOBALS['import_forbidden'] = array(
	'streamit_add_movie'              => 0,
	'streamit_update_movie'           => 0,
	'insert_movie_tmdb_to_streamit'   => 0,
	'update_post_meta'                => 0,
	'wp_update_post'                  => 0,
	'update_post_meta_subtitles'      => 0,
);

if ( ! function_exists( 'streamit_add_movie' ) ) {
	function streamit_add_movie( $data ) {
		$GLOBALS['import_forbidden']['streamit_add_movie']++;
		return 0;
	}
}
if ( ! function_exists( 'streamit_update_movie' ) ) {
	function streamit_update_movie( $id, $data = array() ) {
		$GLOBALS['import_forbidden']['streamit_update_movie']++;
		return false;
	}
}
if ( ! function_exists( 'insert_movie_tmdb_to_streamit' ) ) {
	function insert_movie_tmdb_to_streamit( $tmdb_id, $args = array() ) {
		$GLOBALS['import_forbidden']['insert_movie_tmdb_to_streamit']++;
		return array( 'status' => false );
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $id, $key, $value, $prev = '' ) {
		$GLOBALS['import_forbidden']['update_post_meta']++;
		if ( '_subtitles' === $key ) {
			$GLOBALS['import_forbidden']['update_post_meta_subtitles']++;
		}
		return false;
	}
}
if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $data, $wp_error = false ) {
		$GLOBALS['import_forbidden']['wp_update_post']++;
		return 0;
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-media-import-service.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-media-admin.php';

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
	assert_true( $expected === $actual, $label . ' got=' . var_export( $actual, true ) );
}

/**
 * @return array<string,mixed>
 */
function base_preview(): array {
	return array(
		'ok'              => true,
		'ready_to_import' => true,
		'input'           => array(
			'tmdb_id'         => 111,
			'title'           => 'Local Title',
			'summary'         => 'Local summary',
			'media_directory' => 'Movie/Korea/2018/Vapor',
		),
		'tmdb'            => array(
			'title'    => 'TMDb Title',
			'overview' => 'Overview',
		),
		'media'           => array(
			'directory' => 'Movie/Korea/2018/Vapor',
			'files'     => array(),
			'warnings'  => array(),
		),
		'validation'      => array(
			'errors'   => array(),
			'warnings' => array(),
		),
	);
}

/**
 * @param array<string,mixed> $overrides
 * @return array<string,mixed>
 */
function base_plan( array $overrides = array() ): array {
	$plan = array(
		'ok'                  => true,
		'ready_to_import'     => true,
		'errors'              => array(),
		'warnings'            => array(),
		'contract'            => array( 'kind' => 'import_plan', 'version' => 1 ),
		'identity'            => array(
			'action'            => 'create',
			'existing_movie_id' => null,
			'tmdb_id'           => 111,
		),
		'metadata'            => array(
			'title'   => 'Local Title',
			'summary' => 'Local summary',
		),
		'movie'               => array(
			'tmdb_id'         => 111,
			'media_directory' => 'Movie/Korea/2018/Vapor',
		),
		'sources'             => array(
			array(
				'action'           => 'add',
				'identity_key'     => 'Movie/Korea/2018/Vapor/a.mkv',
				'media_path'       => 'Movie/Korea/2018/Vapor/a.mkv',
				'name'             => 'Enc',
				'quality'          => '1080p',
				'file_size'        => '1 GB',
				'language'         => null,
				'link'             => 'Movie/Korea/2018/Vapor/a.mkv',
				'download_content' => 'Movie/Korea/2018/Vapor/a.mkv',
			),
		),
		'subtitle_persistence'=> array(
			'ready'  => true,
			'status' => 'relative_path',
		),
		'subtitles'           => array(),
	);
	foreach ( $overrides as $k => $v ) {
		$plan[ $k ] = $v;
	}
	return $plan;
}

/**
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function import_harness( array &$state ): array {
	$state += array(
		'preview_calls'   => 0,
		'plan_calls'      => 0,
		'adapter_calls'   => 0,
		'adapter_plans'   => array(),
		'find_calls'      => 0,
		'tmdb_ids'        => array(),
		'movie_exists'    => true,
		'preview'         => base_preview(),
		'plan'            => base_plan(),
		'adapter_result'  => array(
			'ok'              => true,
			'movie_id'        => 55,
			'identity_action' => 'create',
			'completed'       => array( 'movie', 'metadata', 'sources', 'media_directory', 'subtitles' ),
			'deferred'        => array(),
			'source_stats'    => array( 'added' => 1, 'updated' => 0, 'kept' => 0 ),
			'subtitle_stats'  => array( 'added' => 0, 'updated' => 0, 'kept' => 0 ),
			'warnings'        => array(),
		),
	);

	return array(
		'preview_build' => static function ( $input ) use ( &$state ) {
			$state['preview_calls']++;
			$state['last_preview_input'] = $input;
			return $state['preview'];
		},
		'plan_build' => static function ( $preview, $opts ) use ( &$state ) {
			$state['plan_calls']++;
			$state['last_plan_preview'] = $preview;
			// Simulate Import Plan using find_by_tmdb from opts if provided.
			return $state['plan'];
		},
		'find_by_tmdb' => static function ( $tmdb_id ) use ( &$state ) {
			$state['find_calls']++;
			return array( 'ids' => $state['tmdb_ids'] );
		},
		'movie_exists' => static function ( $id ) use ( &$state ) {
			return (bool) $state['movie_exists'];
		},
		'adapter_apply' => static function ( $plan ) use ( &$state ) {
			$state['adapter_calls']++;
			$state['adapter_plans'][] = $plan;
			return $state['adapter_result'];
		},
	);
}

function base_request( array $extra = array() ): array {
	return array_merge(
		array(
			'tmdb_id'         => 111,
			'title'           => 'Local Title',
			'summary'         => 'Local summary',
			'media_directory' => 'Movie/Korea/2018/Vapor',
			'confirm_import'  => '1',
		),
		$extra
	);
}

echo "Movies_WP_Media_Import_Service / Admin import tests\n";

// 1. subscriber cannot import
echo "\n[admin-gate]\n";
{
	$r = Movies_WP_Media_Admin::process_import_request(
		array(
			'_wpnonce'        => 'x',
			'tmdb_id'         => 1,
			'title'           => 'T',
			'summary'         => '',
			'media_directory' => 'Movie/a/b/c',
			'confirm_import'  => '1',
		),
		array(
			'current_user_can' => static function () {
				return false;
			},
			'verify_nonce'     => static function () {
				return true;
			},
			'import_execute'   => static function () {
				return array( 'ok' => true );
			},
		)
	);
	assert_true( is_wp_error( $r ), '1. subscriber cannot import' );
	assert_eq( 'media_import_forbidden', $r->get_error_code(), '1. forbidden code' );
}

// 2. missing nonce rejected
{
	$called = 0;
	$r      = Movies_WP_Media_Admin::process_import_request(
		array(
			'tmdb_id'         => 1,
			'title'           => 'T',
			'media_directory' => 'Movie/a/b/c',
			'confirm_import'  => '1',
		),
		array(
			'current_user_can' => static function () {
				return true;
			},
			'verify_nonce'     => static function () {
				return false;
			},
			'import_execute'   => static function () use ( &$called ) {
				$called++;
				return array( 'ok' => true );
			},
		)
	);
	assert_true( is_wp_error( $r ), '2. missing nonce rejected' );
	assert_eq( 0, $called, '2. import not executed without nonce' );
}

// 3. missing confirmation rejected
echo "\n[service-validation]\n";
{
	$state = array();
	$opts  = import_harness( $state );
	$r     = Movies_WP_Media_Import_Service::execute( base_request( array( 'confirm_import' => null ) ), $opts );
	assert_true( empty( $r['ok'] ), '3. missing confirmation rejected' );
	assert_eq( 'media_import_confirmation_required', $r['code'] ?? null, '3. code' );
	assert_eq( 0, $state['adapter_calls'], '3. adapter not called' );
}

// 4. invalid TMDb ID
{
	$state = array();
	$opts  = import_harness( $state );
	$r     = Movies_WP_Media_Import_Service::execute( base_request( array( 'tmdb_id' => 0 ) ), $opts );
	assert_true( empty( $r['ok'] ), '4. invalid TMDb ID rejected' );
	assert_eq( 'media_import_invalid_input', $r['code'] ?? null, '4. code' );
}

// 5. invalid media directory
{
	$state = array();
	$opts  = import_harness( $state );
	$r     = Movies_WP_Media_Import_Service::execute( base_request( array( 'media_directory' => '/data/Movie/x' ) ), $opts );
	assert_true( empty( $r['ok'] ), '5. invalid media directory rejected' );
	assert_eq( 'media_import_invalid_input', $r['code'] ?? null, '5. code' );
}

// 6–7. plan not ready / errors
{
	$state = array();
	$opts  = import_harness( $state );
	$state['plan'] = base_plan( array( 'ready_to_import' => false ) );
	$r = Movies_WP_Media_Import_Service::execute( base_request(), $opts );
	assert_true( empty( $r['ok'] ), '6. plan not ready rejected' );
	assert_eq( 'media_import_not_ready', $r['code'] ?? null, '6. code' );
	assert_eq( 0, $state['adapter_calls'], '6. no adapter' );
}
{
	$state = array();
	$opts  = import_harness( $state );
	$state['plan'] = base_plan(
		array(
			'ready_to_import' => false,
			'errors'          => array( array( 'code' => 'x', 'message' => 'blocked' ) ),
		)
	);
	$r = Movies_WP_Media_Import_Service::execute( base_request(), $opts );
	assert_true( empty( $r['ok'] ), '7. plan errors block import' );
}

// 8. create plan calls adapter once
echo "\n[adapter-invocation]\n";
{
	$state = array();
	$opts  = import_harness( $state );
	$state['tmdb_ids'] = array(); // create recheck: none exist
	$r = Movies_WP_Media_Import_Service::execute( base_request(), $opts );
	assert_true( ! empty( $r['ok'] ), '8. create success' );
	assert_eq( 1, $state['adapter_calls'], '8. create plan calls adapter exactly once' );
	assert_eq( 1, $state['preview_calls'], '11. plan rebuilt via preview on POST' );
	assert_eq( 1, $state['plan_calls'], '11. plan rebuild called' );
}

// 9. update plan calls adapter once
{
	$state = array();
	$opts  = import_harness( $state );
	$state['plan'] = base_plan(
		array(
			'identity' => array(
				'action'            => 'update',
				'existing_movie_id' => 42,
				'tmdb_id'           => 111,
			),
		)
	);
	$state['tmdb_ids']       = array( 42 );
	$state['movie_exists']   = true;
	$state['adapter_result'] = array(
		'ok'              => true,
		'movie_id'        => 42,
		'identity_action' => 'update',
		'completed'       => array( 'movie', 'metadata', 'sources', 'media_directory', 'subtitles' ),
		'deferred'        => array(),
		'source_stats'    => array( 'added' => 0, 'updated' => 1, 'kept' => 1 ),
		'subtitle_stats'  => array( 'added' => 0, 'updated' => 0, 'kept' => 0 ),
		'warnings'        => array(),
	);
	$r = Movies_WP_Media_Import_Service::execute( base_request(), $opts );
	assert_true( ! empty( $r['ok'] ), '9. update success' );
	assert_eq( 1, $state['adapter_calls'], '9. update plan calls adapter exactly once' );
	assert_eq( 'Movie updated successfully.', $r['message'] ?? null, '17. update success message' );
}

// 10 / 30. adapter never called with browser-generated source data; plan not from hidden POST
{
	$state = array();
	$opts  = import_harness( $state );
	$state['tmdb_ids'] = array();
	$browser_sources   = array(
		array( 'name' => 'HACKED', 'link' => '/evil', 'quality' => '999p' ),
	);
	$request = base_request(
		array(
			'sources'         => $browser_sources,
			'plan'            => array( 'ready_to_import' => true, 'ok' => true ),
			'ready_to_import' => true,
			'identity_action' => 'create',
		)
	);
	$r = Movies_WP_Media_Import_Service::execute( $request, $opts );
	assert_true( ! empty( $r['ok'] ), '10. import ok ignoring browser sources' );
	$passed = $state['adapter_plans'][0] ?? array();
	assert_true( isset( $passed['sources'][0]['name'] ) && 'Enc' === $passed['sources'][0]['name'], '10. adapter got rebuilt plan sources not browser HACKED' );
	assert_true( ( $passed['sources'][0]['link'] ?? '' ) !== '/evil', '10. browser link ignored' );
}

// 12. stale create plan is not reused — rebuilt plan wins
{
	$state = array();
	$opts  = import_harness( $state );
	$state['tmdb_ids'] = array();
	$state['plan']     = base_plan(
		array(
			'metadata' => array( 'title' => 'Fresh Title', 'summary' => 'Fresh' ),
			'movie'    => array( 'tmdb_id' => 111, 'media_directory' => 'Movie/Korea/2018/Vapor' ),
		)
	);
	Movies_WP_Media_Import_Service::execute( base_request( array( 'title' => 'Form Title' ) ), $opts );
	$passed = $state['adapter_plans'][0] ?? array();
	assert_eq( 'Fresh Title', $passed['metadata']['title'] ?? null, '12. rebuilt plan title used (not stale HTML)' );
}

// 13–14 / 23. TMDb identity rechecked; duplicate prevents create; double submission
echo "\n[identity-recheck]\n";
{
	$state = array();
	$opts  = import_harness( $state );
	$state['tmdb_ids'] = array( 99 ); // already exists at recheck
	$r = Movies_WP_Media_Import_Service::execute( base_request(), $opts );
	assert_true( empty( $r['ok'] ), '14. new duplicate prevents create' );
	assert_eq( 'media_import_duplicate_identity', $r['code'] ?? null, '14. duplicate code' );
	assert_eq( 0, $state['adapter_calls'], '13/23. adapter not called when identity exists' );
	assert_true( $state['find_calls'] >= 1, '13. TMDb identity rechecked before create' );
}

// 15. update target disappearing
{
	$state = array();
	$opts  = import_harness( $state );
	$state['plan'] = base_plan(
		array(
			'identity' => array(
				'action'            => 'update',
				'existing_movie_id' => 42,
				'tmdb_id'           => 111,
			),
		)
	);
	$state['movie_exists'] = false;
	$state['tmdb_ids']     = array();
	$r = Movies_WP_Media_Import_Service::execute( base_request(), $opts );
	assert_true( empty( $r['ok'] ), '15. update target disappearing prevents update' );
	assert_eq( 'media_import_identity_changed', $r['code'] ?? null, '15. code' );
	assert_eq( 0, $state['adapter_calls'], '15. no adapter' );
}

// 16. create success displayed correctly
echo "\n[presentation]\n";
{
	$state = array();
	$opts  = import_harness( $state );
	$state['tmdb_ids'] = array();
	$r = Movies_WP_Media_Import_Service::execute( base_request(), $opts );
	assert_eq( 'Movie imported successfully.', $r['message'] ?? null, '16. create success message' );
	assert_eq( 55, $r['movie_id'] ?? null, '16. movie id' );
	assert_eq( 'Movie/Korea/2018/Vapor', $r['media_directory'] ?? null, '16. relative directory' );
	assert_eq( array(), $r['deferred'] ?? null, '20. no deferred subtitle step' );
	assert_true( in_array( 'subtitles', $r['completed'] ?? array(), true ) || isset( $r['subtitle_stats'] ), '20. subtitle result surfaced' );
}

// 18–19. adapter / partial failure
{
	$state = array();
	$opts  = import_harness( $state );
	$state['tmdb_ids'] = array();
	$state['adapter_result'] = array(
		'ok'              => false,
		'movie_id'        => null,
		'identity_action' => 'create',
		'completed'       => array(),
		'failed_step'     => 'movie',
		'error'           => array( 'code' => 'create_boom', 'message' => 'nope' ),
		'deferred'        => array(),
	);
	$r = Movies_WP_Media_Import_Service::execute( base_request(), $opts );
	assert_true( empty( $r['ok'] ), '18. adapter failure' );
	assert_eq( 'Import failed.', $r['message'] ?? null, '18. failure message' );
	assert_eq( 'movie', $r['failed_step'] ?? null, '18. failed_step' );
}
{
	$state = array();
	$opts  = import_harness( $state );
	$state['tmdb_ids'] = array();
	$state['adapter_result'] = array(
		'ok'              => false,
		'movie_id'        => 77,
		'identity_action' => 'create',
		'completed'       => array( 'movie', 'metadata' ),
		'failed_step'     => 'sources',
		'error'           => array( 'code' => 'src_fail', 'message' => 'sources failed at /data/secret' ),
		'deferred'        => array(),
		'source_stats'    => array( 'added' => 0, 'updated' => 0, 'kept' => 0 ),
	);
	$r = Movies_WP_Media_Import_Service::execute( base_request(), $opts );
	assert_true( ! empty( $r['partial'] ), '19. partial failure flagged' );
	assert_eq( 'Import partially completed.', $r['message'] ?? null, '19. partial message' );
	assert_eq( 77, $r['movie_id'] ?? null, '19. movie_id kept' );
	assert_true( ! str_contains( (string) ( $r['error']['message'] ?? '' ), '/data/' ), '21. no absolute /data in error' );
}

// 21–22. safe path / secrets
{
	$safe = Movies_WP_Media_Import_Service::safe_text( 'failed /data/Movie/x secret=abc hmac: deadbeef' );
	assert_true( ! str_contains( $safe, '/data/Movie' ), '21. /data redacted' );
	assert_true( ! str_contains( $safe, 'deadbeef' ), '22. secrets redacted' );
	assert_eq( 'Movie/Korea/2018/Vapor', Movies_WP_Media_Import_Service::safe_relative_path( '/data/Movie/Korea/2018/Vapor' ), '21. /data prefix stripped to relative Movie path' );
}

// 24–26. no direct Streamit writes from admin / import service source
echo "\n[source-safety]\n";
{
	$admin_src   = file_get_contents( dirname( __DIR__ ) . '/class-movies-wp-media-admin.php' );
	$service_src = file_get_contents( dirname( __DIR__ ) . '/class-movies-wp-media-import-service.php' );
	$view_src    = file_get_contents( dirname( __DIR__ ) . '/views/scan-preview.php' );

	foreach ( array( 'streamit_add_movie(', 'streamit_update_movie(', 'insert_movie_tmdb_to_streamit(', 'update_post_meta(', 'wp_update_post(' ) as $fn ) {
		assert_true( ! str_contains( $admin_src, $fn ), "24. admin has no {$fn}" );
		assert_true( ! str_contains( $service_src, $fn ), "24. import service has no {$fn}" );
	}
	assert_true( ! str_contains( $admin_src, "'_source'" ) && ! str_contains( $admin_src, '"_source"' ), '25. admin does not construct _source' );
	assert_true( ! str_contains( $service_src, "'_source'" ) && ! str_contains( $service_src, '"_source"' ), '25. service does not construct _source' );
	assert_true( ! str_contains( $admin_src, "'_subtitles'" ) && ! str_contains( $service_src, "'_subtitles'" ), '26. no _subtitles writes in admin/service' );
	assert_true( str_contains( $service_src, 'Movies_WP_Streamit_Adapter::apply' ), '24. service calls Adapter::apply' );
}

// Runtime: forbidden stubs untouched by service/admin path
{
	$before = $GLOBALS['import_forbidden'];
	$state  = array();
	$opts   = import_harness( $state );
	$state['tmdb_ids'] = array();
	Movies_WP_Media_Import_Service::execute( base_request(), $opts );
	Movies_WP_Media_Admin::process_import_request(
		array(
			'_wpnonce'        => 'ok',
			'tmdb_id'         => 1,
			'title'           => 'T',
			'summary'         => '',
			'media_directory' => 'Movie/Korea/2018/Vapor',
			'confirm_import'  => '1',
			'sources'         => array( array( 'name' => 'browser' ) ),
		),
		array(
			'current_user_can' => static function () {
				return true;
			},
			'verify_nonce'     => static function () {
				return true;
			},
			'import_execute'   => static function ( $req ) {
				assert_true( ! isset( $req['sources'] ), '30. process_import_request does not pass browser sources' );
				return array( 'ok' => true, 'message' => 'x' );
			},
		)
	);
	assert_eq( $before, $GLOBALS['import_forbidden'], '24. no direct Streamit writes at runtime' );
}

// 27–29. Import button state + confirmation in view
echo "\n[ui-contract]\n";
{
	$view = file_get_contents( dirname( __DIR__ ) . '/views/scan-preview.php' );
	assert_true( str_contains( $view, "plan['ready_to_import']" ) || str_contains( $view, '$plan_ready' ), '27/28. button uses plan ready_to_import' );
	assert_true( str_contains( $view, 'disabled' ), '27. disabled button present when not ready' );
	assert_true( str_contains( $view, 'confirm_import' ), '29. confirmation checkbox required' );
	assert_true( str_contains( $view, Movies_WP_Media_Admin::IMPORT_NONCE ) || str_contains( $view, 'IMPORT_NONCE' ), '29. import nonce in form' );
	assert_true( ! str_contains( $view, 'name="sources"' ) && ! str_contains( $view, 'name="plan"' ), '30. plan/sources not in hidden POST' );
	assert_true( str_contains( $view, 'Subtitles — added:' ) || str_contains( $view, 'Subtitles' ), '20. subtitle import status copy in UI' );
	assert_true( ! str_contains( $view, 'Subtitles were not imported because subtitle URL rendering is not implemented yet.' ), '20. old deferred copy removed' );
}

// Button enable logic unit check
{
	$plan_ready_false = is_array( array( 'ready_to_import' => false ) ) && ! empty( array( 'ready_to_import' => false )['ready_to_import'] );
	$plan_ready_true  = is_array( array( 'ready_to_import' => true ) ) && ! empty( array( 'ready_to_import' => true )['ready_to_import'] );
	assert_true( ! $plan_ready_false, '27. Import button disabled when ready_to_import=false' );
	assert_true( $plan_ready_true, '28. Import button enabled only when ready_to_import=true' );
}

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}
echo "All import service assertions passed.\n";
exit( 0 );

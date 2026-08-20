<?php
/**
 * Deterministic request-boundary tests for Movies_WP_Series_Admin.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-admin-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-admin-test/' );
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code, $message ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) { return $text; }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return (string) $url; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) { return (string) $text; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text ) { return (string) $text; }
}

require_once dirname( __DIR__ ) . '/class-movies-wp-series-admin.php';

$failures = 0;

function series_admin_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function series_admin_same( $expected, $actual, string $label ): void {
	series_admin_assert(
		$expected === $actual,
		$label . ' expected=' . var_export( $expected, true ) . ' got=' . var_export( $actual, true )
	);
}

function series_admin_preview(): array {
	return array(
		'ok'              => true,
		'type'            => 'series_automation',
		'input'           => array(
			'tmdb_id'          => 100,
			'title'            => 'عنوان محلی',
			'summary'          => 'خلاصه',
			'series_directory' => 'series/korea/2024/Show',
		),
		'series'          => array(
			'tmdb_id' => 100,
			'name'    => 'TMDb Series',
			'seasons' => array(),
		),
		'metadata_plan'   => series_admin_plan(),
		'media'           => array(
			'directory' => array( 'path' => 'series/korea/2024/Show' ),
			'episodes'  => array(),
		),
		'episodes'        => array(),
		'validation'      => array(
			'errors'   => array(),
			'warnings' => array(),
		),
		'ready_to_import' => true,
	);
}

function series_admin_plan(): array {
	return array(
		'ok'              => true,
		'type'            => 'series',
		'contract'        => array(
			'kind'      => 'series_import_plan',
			'version'   => 1,
			'read_only' => true,
		),
		'identity'        => array(
			'action'             => 'create',
			'existing_series_id' => null,
			'match_by'           => '_tmdb_id',
		),
		'series'          => array(
			'tmdb_id' => 100,
			'title'   => 'عنوان محلی',
		),
		'images'          => array(),
		'sources_policy'  => array(
			'episode_meta_key' => '_sources',
			'mutate'           => false,
			'actions'          => array(),
		),
		'seasons'         => array(),
		'warnings'        => array(),
		'errors'          => array(),
		'ready_to_import' => true,
	);
}

function series_admin_post( bool $confirmed = true ): array {
	return array(
		'_wpnonce'                    => 'valid',
		'tmdb_id'                    => '100',
		'title'                      => '  عنوان محلی  ',
		'summary'                    => 'خلاصه',
		'series_directory'           => 'series/korea/2024/Show',
		'confirm_import'             => $confirmed ? '1' : null,
		'snapshot_token'             => 'snap-test-token',
		'plan'                       => array( 'identity' => array( 'action' => 'update' ) ),
		'identity_action'            => 'update',
		'existing_series_id'         => 999,
		'_sources'                   => array( array( 'url' => 'malicious' ) ),
		'episodes'                   => array( array( 'existing_episode_id' => 999 ) ),
		'images'                     => array( 'poster' => array( 'action' => 'delete' ) ),
	);
}

function series_admin_options( array &$calls, ?array &$received_values = null ): array {
	return array(
		'current_user_can'    => static function ( $capability ) use ( &$calls ): bool {
			++$calls['capability'];
			return 'manage_options' === $capability;
		},
		'verify_nonce'        => static function ( $nonce, $action ) use ( &$calls ): bool {
			++$calls['nonce'];
			return 'valid' === $nonce
				&& in_array(
					$action,
					array(
						Movies_WP_Series_Admin::PREVIEW_NONCE,
						Movies_WP_Series_Admin::IMPORT_NONCE,
						Movies_WP_Series_Admin::PROGRESS_NONCE,
					),
					true
				);
		},
		'user_id'             => 7,
		'blog_id'             => 1,		'snapshot_create'     => static function ( array $preview ) use ( &$calls ): array {
			++$calls['snapshot'];
			series_admin_assert( true === ( $preview['ready_to_import'] ?? false ), 'snapshot stores ready preview only' );
			return array(
				'id'         => 1,
				'token'      => 'snap-test-token',
				'expires_at' => '2099-01-01 00:00:00',
			);
		},
		'enqueue_job'         => static function ( $token, array $context ) use ( &$calls, &$received_values ): array {
			++$calls['import'];
			$received_values = array(
				'snapshot_token' => $token,
				'user_id'        => $context['user_id'] ?? 0,
			);
			return array(
				'token'    => 'job-test-token',
				'status'   => 'queued',
				'enqueued' => true,
			);
		},
		'orchestrator_preview' => static function ( array $values ) use ( &$calls ): array {
			++$calls['preview'];
			series_admin_same( array( 'tmdb_id', 'title', 'summary', 'series_directory' ), array_keys( $values ), 'preview receives whitelisted inputs only' );
			return series_admin_preview();
		},
		'orchestrator_execute' => static function ( array $values ) use ( &$calls, &$received_values ): array {
			++$calls['import'];
			$received_values = $values;
			return array(
				'ok'        => true,
				'partial'   => false,
				'series_id' => 501,
				'action'    => 'create',
				'completed' => 1,
				'warnings'  => array(),
				'errors'    => array(),
				'series'    => array( 'ok' => true ),
				'seasons'   => array(),
				'episodes'  => array(),
				'images'    => array(),
			);
		},
	);
}

echo "Series admin request contract\n";

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0, 'snapshot' => 0 );
$received_values = null;
$preview_result = Movies_WP_Series_Admin::process_preview_request(
	series_admin_post(),
	series_admin_options( $calls, $received_values )
);
series_admin_assert( is_array( $preview_result ), 'valid preview request returns context' );
series_admin_same( 'snap-test-token', $preview_result['snapshot_token'], 'ready preview returns snapshot token' );
series_admin_same( 1, $calls['snapshot'], 'snapshot is created during preview' );
series_admin_same( 'عنوان محلی', $preview_result['values']['title'], 'preview uses normalized server result' );
series_admin_same( 'series/korea/2024/Show', $preview_result['values']['series_directory'], 'preview keeps lowercase series directory' );
series_admin_same( 1, $calls['preview'], 'orchestrator preview called once' );
series_admin_same( 0, $calls['import'], 'preview performs no import' );
series_admin_same( series_admin_plan(), $preview_result['plan'], 'admin displays metadata plan from combined preview' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0, 'snapshot' => 0 );
$received_values = null;
$import_result = Movies_WP_Series_Admin::process_import_request(
	series_admin_post(),
	series_admin_options( $calls, $received_values )
);
series_admin_assert( is_array( $import_result ) && ! empty( $import_result['enqueued'] ), 'valid import enqueues a job' );
series_admin_same( 'job-test-token', $import_result['token'], 'enqueue returns job token' );
series_admin_same( 0, $calls['preview'], 'import does not rebuild preview' );
series_admin_same( 1, $calls['import'], 'job enqueue invoked exactly once' );
series_admin_same( 'snap-test-token', $received_values['snapshot_token'], 'import submits snapshot token only' );
series_admin_assert( ! isset( $received_values['plan'] ), 'browser plan payload is discarded' );
series_admin_assert( ! isset( $received_values['_sources'] ), 'browser _sources payload is discarded' );
series_admin_assert( ! isset( $received_values['existing_series_id'] ), 'browser identity fields are discarded' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0, 'snapshot' => 0 );
$received_values = null;
$confirmation_error = Movies_WP_Series_Admin::process_import_request(
	series_admin_post( false ),
	series_admin_options( $calls, $received_values )
);
series_admin_same( 'series_import_confirmation_required', $confirmation_error->get_error_code(), 'explicit confirmation is required' );
series_admin_same( 0, $calls['preview'], 'missing confirmation blocks preview rebuild' );
series_admin_same( 0, $calls['import'], 'missing confirmation blocks import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0, 'snapshot' => 0 );
$received_values = null;
$options = series_admin_options( $calls, $received_values );
$options['current_user_can'] = static function (): bool { return false; };
$forbidden = Movies_WP_Series_Admin::process_import_request( series_admin_post(), $options );
series_admin_same( 'series_import_forbidden', $forbidden->get_error_code(), 'capability failure is deterministic' );
series_admin_same( 0, $calls['import'], 'capability failure blocks import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0, 'snapshot' => 0 );
$received_values = null;
$options = series_admin_options( $calls, $received_values );
$options['verify_nonce'] = static function (): bool { return false; };
$invalid_nonce = Movies_WP_Series_Admin::process_import_request( series_admin_post(), $options );
series_admin_same( 'series_import_invalid_nonce', $invalid_nonce->get_error_code(), 'nonce failure is deterministic' );
series_admin_same( 0, $calls['preview'], 'nonce failure blocks preview rebuild' );
series_admin_same( 0, $calls['import'], 'nonce failure blocks import' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0, 'snapshot' => 0 );
$received_values = null;
$options = series_admin_options( $calls, $received_values );
$options['orchestrator_preview'] = static function () use ( &$calls ) {
	++$calls['preview'];
	return new WP_Error( 'series_import_duplicate_identity', 'Duplicate identity.' );
};
$plan_error = Movies_WP_Series_Admin::process_preview_request( series_admin_post(), $options );
series_admin_same( 'series_import_duplicate_identity', $plan_error->get_error_code(), 'preview/plan error is preserved' );
series_admin_same( 0, $calls['import'], 'invalid preview is rejected before import' );

$view = (string) file_get_contents( dirname( __DIR__ ) . '/views/series-preview.php' );
series_admin_assert( str_contains( $view, 'name="series_directory"' ), 'Series page includes the directory field' );
series_admin_assert( str_contains( $view, 'name="snapshot_token"' ), 'import form submits snapshot token' );
series_admin_assert( ! str_contains( $view, 'name="existing_series_id"' ), 'import form does not submit series ids' );
series_admin_assert( str_contains( $view, 'What will happen' ), 'Series page leads with operator-facing import outcome' );
series_admin_assert( str_contains( $view, 'Episode coverage' ), 'Series page shows episode coverage' );
series_admin_assert( str_contains( $view, 'Episode details' ), 'Series page keeps episode matches under details' );
series_admin_assert( ! str_contains( $view, 'Import Plan summary' ), 'internal import-plan summary is not the primary heading' );
series_admin_assert( ! str_contains( $view, 'Always preserved' ), 'source preservation is not marketed as an operator feature' );
series_admin_assert( ! str_contains( $view, 'metadata_and_media' ), 'internal media status codes stay out of the view' );
series_admin_assert( ! str_contains( $view, 'keep_existing_untouched' ), 'internal source-policy values stay out of the view' );

echo "Series admin presentation helpers\n";

$still_warnings = array();
for ( $i = 1; $i <= 24; $i++ ) {
	$still_warnings[] = array(
		'code'    => 'series_episode_still_missing',
		'message' => sprintf( 'S01E%02d has no episode still on TMDb.', $i ),
	);
}
$still_groups = Movies_WP_Series_Admin::grouped_issues( $still_warnings );
series_admin_same( 1, count( $still_groups ), 'identical still warnings collapse to one group' );
series_admin_same( 24, $still_groups[0]['count'], 'still group retains the original warning count' );
series_admin_same(
	'24 episodes have no TMDb stills. Episode still images will be skipped.',
	$still_groups[0]['summary'],
	'missing stills use a single non-blocking operator summary'
);
series_admin_same( 24, count( $still_groups[0]['details'] ), 'still group keeps expandable per-episode details' );

$coverage = Movies_WP_Series_Admin::episode_coverage(
	array(
		array( 'season_number' => 1, 'episode_number' => 1, 'status' => 'metadata_and_media', 'source_count' => 3, 'subtitle_count' => 2 ),
		array( 'season_number' => 1, 'episode_number' => 24, 'status' => 'metadata_and_media', 'source_count' => 3, 'subtitle_count' => 2 ),
		array( 'season_number' => 1, 'episode_number' => 99, 'status' => 'media_without_tmdb', 'source_count' => 1, 'subtitle_count' => 0 ),
	)
);
series_admin_same( 2, $coverage['total'], 'coverage denominator counts TMDb episodes only' );
series_admin_same( 2, $coverage['matched'], 'coverage numerator counts matched TMDb episodes' );
series_admin_same( 'S01E01–S01E24', $coverage['range'], 'coverage range uses first and last matched codes' );
series_admin_same( true, $coverage['uniform'], 'uniform source counts are detected' );
series_admin_same( 3, $coverage['videos_per_episode'], 'uniform video count is exposed' );
series_admin_same( 2, $coverage['subtitles_per_episode'], 'uniform subtitle count is exposed' );
series_admin_same( 'Matched', Movies_WP_Series_Admin::media_status_label( 'metadata_and_media' ), 'internal media status is operator-facing' );

echo "Series admin early mutation redirects\n";

$admin_src = (string) file_get_contents( dirname( __DIR__ ) . '/class-movies-wp-series-admin.php' );
series_admin_assert( str_contains( $admin_src, "add_action( 'load-' . self::\$page_hook" ), 'Import mutations register on load-{$hook} before output' );
series_admin_assert( str_contains( $admin_src, 'handle_mutation_request' ), 'mutation handler is shared for Import/Resume/Cancel' );
series_admin_assert(
	preg_match( '/function render_page\(\).*?function handle_import_mutation/s', $admin_src )
	&& ! preg_match( '/function render_page\(\).*?wp_safe_redirect.*?function handle_import_mutation/s', $admin_src ),
	'render_page no longer calls wp_safe_redirect'
);

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0, 'snapshot' => 0, 'catalog' => 0 );
$received_values = null;
$redirect = null;
$options = series_admin_options( $calls, $received_values );
$options['redirect'] = static function ( $url, $status ) use ( &$redirect ): void {
	$redirect = array(
		'url'    => (string) $url,
		'status' => (int) $status,
	);
};
$options['enqueue_job'] = static function ( $token, array $context ) use ( &$calls, &$received_values ): array {
	++$calls['import'];
	$received_values = array(
		'snapshot_token' => $token,
		'user_id'        => $context['user_id'] ?? 0,
	);
	return array(
		'token'    => 'job-test-token',
		'status'   => 'queued',
		'enqueued' => true,
	);
};
$import_post = series_admin_post();
$import_post[ Movies_WP_Series_Admin::ACTION_FIELD ] = Movies_WP_Series_Admin::IMPORT_ACTION;
Movies_WP_Series_Admin::handle_mutation_request( $import_post, $options );
series_admin_assert( is_array( $redirect ), 'Import mutation issues a redirect' );
series_admin_same( 302, $redirect['status'], 'Import redirect uses HTTP 302' );
series_admin_assert(
	str_contains( (string) $redirect['url'], 'job_token=job-test-token' ),
	'Import Location contains the job token'
);
series_admin_assert(
	str_contains( (string) $redirect['url'], 'page=' . Movies_WP_Series_Admin::SLUG ),
	'Import Location targets Series Automation progress'
);
series_admin_same( 1, $calls['import'], 'Import mutation enqueues exactly once' );
series_admin_same( 0, $calls['preview'], 'Import mutation does not rebuild preview/scan' );
series_admin_same( 0, $calls['catalog'], 'Import mutation performs no catalog work in the HTTP request' );
series_admin_same( 'snap-test-token', $received_values['snapshot_token'], 'Import mutation still submits snapshot token only' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0, 'snapshot' => 0, 'resume' => 0, 'cancel' => 0 );
$redirect = null;
$options = series_admin_options( $calls, $received_values );
$options['redirect'] = static function ( $url, $status ) use ( &$redirect ): void {
	$redirect = array( 'url' => (string) $url, 'status' => (int) $status );
};
$options['find_job'] = static function ( $token ): array {
	return array(
		'token'   => (string) $token,
		'user_id' => 7,
		'blog_id' => 1,
		'status'  => 'paused',
	);
};
$options['resume_job'] = static function ( $token ) use ( &$calls ): void {
	++$calls['resume'];
	series_admin_same( 'job-resume-token', $token, 'resume receives job token' );
};
$options['cancel_job'] = static function () use ( &$calls ): void {
	++$calls['cancel'];
};
$resume_post = array(
	'_wpnonce' => 'valid',
	Movies_WP_Series_Admin::ACTION_FIELD => Movies_WP_Series_Admin::RESUME_ACTION,
	'job_token' => 'job-resume-token',
);
Movies_WP_Series_Admin::handle_mutation_request( $resume_post, $options );
series_admin_same( 1, $calls['resume'], 'Resume mutation invokes resume once' );
series_admin_same( 0, $calls['cancel'], 'Resume mutation does not cancel' );
series_admin_same( 302, $redirect['status'], 'Resume redirect uses HTTP 302' );
series_admin_assert( str_contains( (string) $redirect['url'], 'job_token=job-resume-token' ), 'Resume Location keeps job token' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0, 'snapshot' => 0, 'resume' => 0, 'cancel' => 0 );
$redirect = null;
$options = series_admin_options( $calls, $received_values );
$options['redirect'] = static function ( $url, $status ) use ( &$redirect ): void {
	$redirect = array( 'url' => (string) $url, 'status' => (int) $status );
};
$options['find_job'] = static function ( $token ): array {
	return array(
		'token'   => (string) $token,
		'user_id' => 7,
		'blog_id' => 1,
		'status'  => 'running',
	);
};
$options['resume_job'] = static function () use ( &$calls ): void {
	++$calls['resume'];
};
$options['cancel_job'] = static function ( $token ) use ( &$calls ): void {
	++$calls['cancel'];
	series_admin_same( 'job-cancel-token', $token, 'cancel receives job token' );
};
$cancel_post = array(
	'_wpnonce' => 'valid',
	Movies_WP_Series_Admin::ACTION_FIELD => Movies_WP_Series_Admin::CANCEL_ACTION,
	'job_token' => 'job-cancel-token',
);
Movies_WP_Series_Admin::handle_mutation_request( $cancel_post, $options );
series_admin_same( 1, $calls['cancel'], 'Cancel mutation invokes cancel once' );
series_admin_same( 0, $calls['resume'], 'Cancel mutation does not resume' );
series_admin_same( 302, $redirect['status'], 'Cancel redirect uses HTTP 302' );
series_admin_assert( str_contains( (string) $redirect['url'], 'job_token=job-cancel-token' ), 'Cancel Location keeps job token' );

$calls = array( 'capability' => 0, 'nonce' => 0, 'preview' => 0, 'import' => 0, 'snapshot' => 0 );
$redirect = null;
$died = null;
$options = series_admin_options( $calls, $received_values );
$options['current_user_can'] = static function (): bool { return false; };
$options['redirect'] = static function () use ( &$redirect ): void {
	$redirect = 'sent';
};
$options['wp_die'] = static function ( $message ) use ( &$died ): void {
	$died = (string) $message;
};
$import_post = series_admin_post();
$import_post[ Movies_WP_Series_Admin::ACTION_FIELD ] = Movies_WP_Series_Admin::IMPORT_ACTION;
Movies_WP_Series_Admin::handle_mutation_request( $import_post, $options );
series_admin_assert( null !== $died, 'capability failure still dies from mutation handler' );
series_admin_same( null, $redirect, 'capability failure does not redirect' );
series_admin_same( 0, $calls['import'], 'capability failure still blocks enqueue' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series admin contract tests passed.\n";
exit( $failures ? 1 : 0 );

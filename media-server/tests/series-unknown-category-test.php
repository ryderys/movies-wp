<?php
/**
 * CLI tests for unknown category warnings: listing → scan → WordPress preview.
 *
 * Run: php media-server/tests/series-unknown-category-test.php
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/series-scan.php';

$failures = 0;

function unknown_category_assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

/**
 * @param list<array<string, mixed>> $warnings
 * @return list<array<string, mixed>>
 */
function unknown_category_find_warnings( array $warnings, string $code, string $name ): array {
	return array_values(
		array_filter(
			$warnings,
			static function ( $warning ) use ( $code, $name ): bool {
				return is_array( $warning )
					&& ( $warning['code'] ?? '' ) === $code
					&& ( $warning['name'] ?? '' ) === $name;
			}
		)
	);
}

function unknown_category_ffprobe_runner(): callable {
	return static function ( array $argv ): array {
		return array(
			'exit'   => 0,
			'stdout' => json_encode(
				array(
					'format'  => array( 'duration' => '1200.0' ),
					'streams' => array(
						array(
							'codec_type' => 'video',
							'codec_name' => 'h264',
							'width'      => 1920,
							'height'     => 1080,
						),
					),
				)
			),
			'stderr' => '',
		);
	};
}

$tmp         = sys_get_temp_dir() . '/series-unknown-category-test-' . bin2hex( random_bytes( 4 ) );
$series_root = $tmp . '/series';
$relative    = 'series/korea/2024/Unknown.Category.Show';
$show_dir    = $tmp . '/' . $relative;

$files = array(
	'1080p WEB-DL/Unknown.Category.Show.S01E01.1080p.WEB-DL.mkv',
	'Extras/Unknown.Category.Show.Behind.The.Scenes.mkv',
);
foreach ( $files as $file ) {
	$path   = $show_dir . '/' . $file;
	$parent = dirname( $path );
	if ( ! is_dir( $parent ) && ! mkdir( $parent, 0777, true ) ) {
		fwrite( STDERR, "Could not create {$parent}\n" );
		exit( 1 );
	}
	file_put_contents( $path, str_repeat( 'x', 1024 ) );
}

$cleanup = static function () use ( $tmp ): void {
	if ( ! is_dir( $tmp ) ) {
		return;
	}
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $it as $f ) {
		$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
	}
	rmdir( $tmp );
};

echo "directory listing\n";
$listed = media_list_series_dir( $relative, $tmp, $series_root );
unknown_category_assert_true( ( $listed['ok'] ?? false ) === true, 'listing succeeds' );
$listed_warnings = unknown_category_find_warnings( $listed['warnings'] ?? array(), 'unknown_category', 'Extras' );
unknown_category_assert_true( count( $listed_warnings ) === 1, 'listing emits one unknown_category warning for Extras' );
unknown_category_assert_true( ( $listed_warnings[0]['category'] ?? '' ) === 'Extras', 'listing warning names the Extras category' );
unknown_category_assert_true( '' !== (string) ( $listed_warnings[0]['message'] ?? '' ), 'listing warning has a message' );
$listed_names = array_column( $listed['files'] ?? array(), 'name' );
unknown_category_assert_true( in_array( 'Unknown.Category.Show.S01E01.1080p.WEB-DL.mkv', $listed_names, true ), 'known release file is listed' );
unknown_category_assert_true( ! in_array( 'Unknown.Category.Show.Behind.The.Scenes.mkv', $listed_names, true ), 'Extras file is not listed as media' );

echo "\nseries scan\n";
$scan = media_scan_series_dir( $relative, $tmp, $series_root, array( 'ffprobe_runner' => unknown_category_ffprobe_runner() ) );
unknown_category_assert_true( ( $scan['ok'] ?? false ) === true, 'scan succeeds' );
unknown_category_assert_true( ( $scan['ready'] ?? false ) === true, 'unknown category is a warning, not a scan error' );
unknown_category_assert_true( count( unknown_category_find_warnings( $scan['warnings'] ?? array(), 'unknown_category', 'Extras' ) ) === 1, 'scan carries the unknown_category warning' );
unknown_category_assert_true( ( $scan['stats']['warning_count'] ?? 0 ) >= 1, 'scan stats count the warning' );
unknown_category_assert_true( count( $scan['episodes'] ?? array() ) === 1, 'only the S01E01 release file becomes an episode' );

$cleanup();
unknown_category_assert_true( ! is_dir( $tmp ), 'temporary directory removed' );

echo "\nWordPress preview\n";
$plugin_dir = dirname( __DIR__, 2 ) . '/wp-content/plugins/custom-plugins/movies-wp-media-automation/includes';
if ( ! is_file( $plugin_dir . '/class-movies-wp-series-orchestrator.php' ) ) {
	echo "  SKIP  movies-wp-media-automation plugin not present next to media-server\n";
} else {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', sys_get_temp_dir() . '/series-unknown-category-test-wp/' );
	}
	if ( ! defined( 'MOVIES_WP_SERIES_IMPORT_TEST_MEMORY' ) ) {
		define( 'MOVIES_WP_SERIES_IMPORT_TEST_MEMORY', true );
	}
	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			private $code;
			private $message;

			public function __construct( $code, $message ) {
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
		function is_wp_error( $value ) {
			return $value instanceof WP_Error;
		}
	}
	if ( ! function_exists( '__' ) ) {
		function __( $text ) {
			return $text;
		}
	}
	if ( ! function_exists( 'absint' ) ) {
		function absint( $value ) {
			return abs( (int) $value );
		}
	}

	require_once $plugin_dir . '/class-movies-wp-series-media-api-client.php';
	require_once $plugin_dir . '/class-movies-wp-series-media-preview-service.php';
	require_once $plugin_dir . '/class-movies-wp-series-import-plan.php';
	require_once $plugin_dir . '/class-movies-wp-series-orchestrator.php';

	// Round-trip through JSON the same way scan-series.php sends it and the API client decodes it.
	$wire_scan = json_decode( (string) json_encode( $scan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), true );

	$metadata_preview = array(
		'ok'              => true,
		'type'            => 'series',
		'ready_to_import' => true,
		'input'           => array(
			'tmdb_id' => 4242,
			'title'   => 'Unknown Category Show',
			'summary' => 'Summary',
		),
		'series'          => array(
			'tmdb_id' => 4242,
			'name'    => 'Unknown Category Show',
			'seasons' => array(
				array(
					'season_number' => 1,
					'name'          => 'Season 1',
					'episodes'      => array(
						array(
							'tmdb_id'        => 424201,
							'season_number'  => 1,
							'episode_number' => 1,
							'name'           => 'Pilot',
						),
					),
				),
			),
		),
		'validation'      => array(
			'errors'   => array(),
			'warnings' => array(),
		),
	);

	$preview = Movies_WP_Series_Orchestrator::build_preview(
		array(
			'tmdb_id'          => 4242,
			'title'            => 'Unknown Category Show',
			'summary'          => 'Summary',
			'series_directory' => $relative,
		),
		array(
			'metadata_preview_build' => static function () use ( $metadata_preview ) {
				return $metadata_preview;
			},
			'scan_series'            => static function () use ( $wire_scan ) {
				return $wire_scan;
			},
			'find_series_by_tmdb'    => static function () {
				return array();
			},
		)
	);

	unknown_category_assert_true( is_array( $preview ), 'preview builds from the real scan payload' );
	$preview_warnings = is_array( $preview ) ? ( $preview['validation']['warnings'] ?? array() ) : array();
	$preview_match    = unknown_category_find_warnings( $preview_warnings, 'unknown_category', 'Extras' );
	unknown_category_assert_true( count( $preview_match ) === 1, 'preview validation warnings include unknown_category for Extras' );
	unknown_category_assert_true( ( $preview_match[0]['category'] ?? '' ) === 'Extras', 'preview warning keeps the category name' );
	unknown_category_assert_true( is_array( $preview ) && ( $preview['ready_to_import'] ?? false ) === true, 'warning does not block import readiness' );
}

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failure(s)\n" );
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

<?php
/**
 * CLI tests for TV show media-directory validation and backfill.
 *
 * Run:
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-tvshow-media-directory-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-tvshow-media-directory-test/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '', $data = '' ) {
			unset( $data );
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
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-tvshow-media-directory.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-streamit-tv-adapter.php';

$failures = 0;
function td_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

echo "series directory prefix\n";
$same = Movies_WP_Tvshow_Media_Directory::prefix_from_sources(
	array(
		array( 'link' => 'series/korea/2024/Show/720p/S01E01.mkv', 'download_content' => 'series/korea/2024/Show/720p/S01E01.mkv' ),
		array( 'link' => 'series/korea/2024/Show/1080p/S01E02.mkv', 'download_content' => '' ),
	)
);
td_assert( 'series/korea/2024/Show' === $same, 'quality subdirectories share one series directory' );

$conflict = Movies_WP_Tvshow_Media_Directory::prefix_from_sources(
	array(
		array( 'link' => 'series/korea/2024/Show/S01E01.mkv', 'download_content' => '' ),
		array( 'link' => 'series/korea/2023/Other/S01E01.mkv', 'download_content' => '' ),
	)
);
td_assert( is_wp_error( $conflict ) && 'tvshow_media_directory_conflict' === $conflict->get_error_code(), 'conflicting series directories rejected' );

$missing = Movies_WP_Tvshow_Media_Directory::prefix_from_sources( array( array( 'link' => '', 'download_content' => '' ) ) );
td_assert( is_wp_error( $missing ) && 'tvshow_media_directory_missing_paths' === $missing->get_error_code(), 'missing episode paths rejected' );

foreach ( array(
	'series/korea/not-a-year/Show/S01E01.mkv',
	'series/korea/2024/../Show/S01E01.mkv',
	'/data/series/korea/2024/Show/S01E01.mkv',
	'https://media.example.test/v/token',
	'/v/token.mp4',
) as $bad ) {
	$parsed = Movies_WP_Tvshow_Media_Directory::prefix_from_sources( array( array( 'link' => $bad, 'download_content' => '' ) ) );
	td_assert( is_wp_error( $parsed ), 'rejected malformed path ' . $bad );
}

td_assert( is_wp_error( Movies_WP_Tvshow_Media_Directory::validate_title_directory( 'series/korea/2024/Show/720p' ) ), 'title directory cannot include a quality folder' );

echo "\nbackfill\n";
$written = array();
$report = Movies_WP_Tvshow_Media_Directory::backfill(
	array(
		array(
			'tvshow_id'       => 7,
			'media_directory' => '',
			'sources'         => array(
				array( 'link' => 'series/korea/2024/The.Husband/720p/S01E01.mkv', 'download_content' => 'series/korea/2024/The.Husband/1080p/S01E01.mkv' ),
			),
		),
		array(
			'tvshow_id'       => 8,
			'media_directory' => 'series/korea/2020/Existing',
			'sources'         => array(
				array( 'link' => 'series/korea/2024/Other/S01E01.mkv', 'download_content' => '' ),
			),
		),
		array(
			'tvshow_id'       => 9,
			'media_directory' => '',
			'sources'         => array(
				array( 'link' => 'series/korea/2024/One/S01E01.mkv', 'download_content' => '' ),
				array( 'link' => 'series/usa/2021/Two/S01E01.mkv', 'download_content' => '' ),
			),
		),
	),
	array(
		'dry_run' => true,
		'update_tvshow_meta' => static function () use ( &$written ) {
			$written[] = 'called';
			return true;
		},
	)
);
td_assert( true === $report['dry_run'], 'default report is a dry run' );
td_assert( 0 === $report['written'], 'dry run writes nothing' );
td_assert( array() === $written, 'dry run does not call meta update' );
td_assert( 'would_write' === ( $report['rows'][0]['action'] ?? '' ) && 'series/korea/2024/The.Husband' === ( $report['rows'][0]['directory'] ?? '' ), 'dry run reports the shared directory' );
td_assert( 'skipped' === ( $report['rows'][1]['action'] ?? '' ) && 'already_set' === ( $report['rows'][1]['reason'] ?? '' ), 'existing directory is left unchanged' );
td_assert( 'skipped' === ( $report['rows'][2]['action'] ?? '' ) && 'tvshow_media_directory_conflict' === ( $report['rows'][2]['reason'] ?? '' ), 'conflict is reported and skipped' );

$saved = array();
$apply = Movies_WP_Tvshow_Media_Directory::backfill(
	array(
		array(
			'tvshow_id'       => 7,
			'media_directory' => '',
			'sources'         => array(
				array( 'link' => 'series/korea/2024/The.Husband/S01E01.mkv', 'download_content' => '' ),
			),
		),
		array(
			'tvshow_id'       => 8,
			'media_directory' => 'series/korea/2020/Existing',
			'sources'         => array(),
		),
	),
	array(
		'dry_run' => false,
		'update_tvshow_meta' => static function ( $id, $key, $value ) use ( &$saved ) {
			$saved[] = array( (int) $id, (string) $key, (string) $value );
			return true;
		},
	)
);
td_assert( 1 === $apply['written'], 'write mode stores one missing directory' );
td_assert( array( array( 7, '_media_directory', 'series/korea/2024/The.Husband' ) ) === $saved, 'write mode stores only the empty show' );

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}
echo "\nAll TV show media directory tests passed.\n";
exit( 0 );

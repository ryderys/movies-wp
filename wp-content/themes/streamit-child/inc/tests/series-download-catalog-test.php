<?php
/**
 * CLI tests for Series download catalog builder.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/series-download-catalog-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-series-download-catalog-test/' );
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {
		unset( $args );
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {
		unset( $args );
		return true;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'streamit_child_normalize_subtitles' ) ) {
	/**
	 * Minimal stub matching production gate (url required).
	 *
	 * @param mixed $raw Raw subtitles.
	 * @return array<int, array{label: string, url: string, format: string}>
	 */
	function streamit_child_normalize_subtitles( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$url = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
			if ( '' === $url ) {
				continue;
			}
			$out[] = array(
				'label'  => isset( $row['label'] ) ? (string) $row['label'] : 'FA',
				'url'    => $url,
				'format' => isset( $row['format'] ) ? (string) $row['format'] : '',
			);
		}
		return $out;
	}
}

if ( ! function_exists( 'streamit_child_resolve_download_href' ) ) {
	function streamit_child_resolve_download_href( $stored, $post_id, $index = 0 ) {
		$stored = trim( (string) $stored );
		if ( '' === $stored ) {
			return '';
		}
		return 'https://example.test/dl?post=' . (int) $post_id . '&i=' . (int) $index;
	}
}

if ( ! function_exists( 'streamit_child_resolve_subtitle_url' ) ) {
	function streamit_child_resolve_subtitle_url( $stored, $type = 'v' ) {
		unset( $type );
		$stored = trim( (string) $stored );
		return '' === $stored ? '' : 'https://example.test/sub/' . rawurlencode( $stored );
	}
}

if ( ! function_exists( 'streamit_child_subtitle_download_meta_values' ) ) {
	function streamit_child_subtitle_download_meta_values( $sub ) {
		unset( $sub );
		return array();
	}
}

require_once dirname( __DIR__ ) . '/sources-download.php';
require_once dirname( __DIR__ ) . '/series-download.php';

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

echo "streamit_child_build_series_download_catalog_from_data tests\n\n";

assert_eq( 'S01E03', streamit_child_format_series_episode_label( '1', 'E03' ), 'label S01E03 from meta' );
assert_eq( 'S00E01', streamit_child_format_series_episode_label( '0', '1' ), 'label allows season 0' );
assert_eq( 'E02', streamit_child_format_series_episode_label( '', 'E02' ), 'label episode-only when season missing' );

assert_eq( 3, streamit_child_series_download_episode_ordinal( 'S01E03', 9 ), 'ordinal from SxxExx' );
assert_eq( 1, streamit_child_series_download_episode_ordinal( 'E01', 0 ), 'ordinal from E01' );
assert_eq( 7, streamit_child_series_download_episode_ordinal( '', 7 ), 'ordinal fallback when label empty' );

$path = 'Series/Show/S01/E01.1080p.mkv';

$seasons = array(
	array(
		'name'          => 'فصل ۱',
		'season_number' => '1',
		'episodes'      => array( 10, 11, 12 ),
	),
	array(
		'name'          => 'فصل ۲',
		'season_number' => '2',
		'episodes'      => array( 20 ),
	),
);

$episodes = array(
	10 => array(
		'title'          => 'Pilot One',
		'season_number'  => '1',
		'episode_number' => 'E01',
		'sources'        => array(
			array(
				'quality'          => '1080p',
				'download_content' => $path,
				'name'             => 'TeamA',
			),
			array(
				'quality'          => '720p',
				'link'             => $path,
			),
		),
		'subtitles'      => array(
			array(
				'label' => 'فارسی',
				'url'   => 'Series/Show/S01/E01.fa.srt',
			),
		),
	),
	11 => array(
		'title'          => 'Episode Two',
		'season_number'  => '1',
		'episode_number' => 'E02',
		'sources'        => array(),
		'subtitles'      => array(),
	),
	12 => array(
		'title'          => 'Episode Three',
		'season_number'  => '1',
		'episode_number' => 'E03',
		'sources'        => array(
			array(
				'quality' => '1080p',
				// missing download → excluded
			),
		),
		'subtitles'      => array(),
	),
	20 => array(
		'title'          => 'S2E1 empty',
		'season_number'  => '2',
		'episode_number' => 'E01',
		'sources'        => array(),
		'subtitles'      => array(),
	),
);

$catalog = streamit_child_build_series_download_catalog_from_data( $seasons, $episodes, true );

assert_eq( 1, count( $catalog['seasons'] ), 'season with no downloadable episodes omitted' );
assert_eq( true, $catalog['can_download'], 'can_download preserved' );
assert_eq( 'فصل ۱', $catalog['seasons'][0]['name'] ?? null, 'season name kept' );
assert_eq( 1, $catalog['seasons'][0]['downloadable_episode_count'] ?? null, 'downloadable count is episodes with media' );
assert_eq( 3, $catalog['seasons'][0]['episode_count'] ?? null, 'all season episodes listed when season kept' );

$ep0 = $catalog['seasons'][0]['episodes'][0] ?? array();
assert_eq( 'S01E01', $ep0['label'] ?? null, 'first episode label' );
assert_eq( true, $ep0['has_download'] ?? null, 'first episode has download' );
assert_eq( 2, count( $ep0['sources'] ?? array() ), 'mixed qualities included' );
assert_eq( '1080p', $ep0['sources'][0]['quality'] ?? null, '1080p quality present' );
assert_eq( '720p', $ep0['sources'][1]['quality'] ?? null, '720p from link fallback present' );
assert_true( str_contains( (string) ( $ep0['sources'][0]['href'] ?? '' ), 'post=10' ), 'gateway-style href minted when unlocked' );
assert_eq( 1, count( $ep0['subtitles'] ?? array() ), 'subtitle row included' );

$ep1 = $catalog['seasons'][0]['episodes'][1] ?? array();
assert_eq( false, $ep1['has_download'] ?? true, 'empty episode marked has_download=false' );

$ep2 = $catalog['seasons'][0]['episodes'][2] ?? array();
assert_eq( false, $ep2['has_download'] ?? true, 'quality without path excluded → no download' );

$locked = streamit_child_build_series_download_catalog_from_data( $seasons, $episodes, false );
assert_eq( false, $locked['can_download'], 'locked catalog flag' );
assert_eq( '', $locked['seasons'][0]['episodes'][0]['sources'][0]['href'] ?? 'x', 'locked catalog omits hrefs' );
assert_true( ! empty( $locked['seasons'][0]['episodes'][0]['sources'] ), 'locked still lists quality chips' );

$empty_all = streamit_child_build_series_download_catalog_from_data(
	array(
		array(
			'name'     => 'Empty',
			'episodes' => array( 99 ),
		),
	),
	array(
		99 => array(
			'title'     => 'None',
			'sources'   => array(),
			'subtitles' => array(),
		),
	),
	true
);
assert_eq( 0, count( $empty_all['seasons'] ), 'fully empty seasons → empty catalog' );

echo "\nstreamit_child_series_download_quality_groups tests\n\n";

$group_episodes = array(
	array(
		'id'           => 10,
		'label'        => 'S01E01',
		'title'        => 'Pilot',
		'permalink'    => 'https://example.test/ep/10',
		'has_download' => true,
		'sources'      => array(
			array(
				'quality'          => '1080p',
				'name'             => 'TeamA',
				'link'             => $path,
				'download_content' => $path,
				'file_size'        => '1 GB',
				'href'             => 'https://example.test/dl?post=10&i=0',
			),
			array(
				'quality'          => '720p',
				'name'             => '',
				'link'             => '',
				'download_content' => $path,
				'href'             => 'https://example.test/dl?post=10&i=1',
			),
		),
		'subtitles'    => array(
			array(
				'label' => 'فارسی',
				'href'  => 'https://example.test/sub/fa',
			),
		),
	),
	array(
		'id'           => 11,
		'label'        => 'S01E02',
		'title'        => 'Two',
		'permalink'    => 'https://example.test/ep/11',
		'has_download' => true,
		'sources'      => array(
			array(
				'quality'          => '1080p',
				'name'             => 'TeamA',
				'link'             => $path,
				'download_content' => $path,
				'href'             => 'https://example.test/dl?post=11&i=0',
			),
		),
		'subtitles'    => array(),
	),
	array(
		'id'           => 12,
		'label'        => 'S01E03',
		'title'        => 'Subs only',
		'permalink'    => 'https://example.test/ep/12',
		'has_download' => true,
		'sources'      => array(),
		'subtitles'    => array(
			array(
				'label' => 'EN',
				'href'  => 'https://example.test/sub/en',
			),
		),
	),
	array(
		'id'           => 13,
		'label'        => 'S01E04',
		'title'        => 'Empty',
		'has_download' => false,
		'sources'      => array(),
		'subtitles'    => array(),
	),
);

$groups = streamit_child_series_download_quality_groups( $group_episodes );
assert_eq( 3, count( $groups ), '1080p + 720p + subs-only groups' );
assert_eq( '1080p', $groups[0]['quality'] ?? null, 'first group is 1080p' );
assert_eq( 'TeamA', $groups[0]['encoder'] ?? null, 'encoder from name' );
assert_eq( 2, count( $groups[0]['episodes'] ?? array() ), '1080p has E01+E02 only' );
assert_eq( 2, $groups[0]['link_count'] ?? null, '1080p link_count' );
assert_eq( 'https://example.test/dl?post=10&i=0', $groups[0]['episodes'][0]['href'] ?? null, '1080p E01 keeps its own href' );
assert_true( ! array_key_exists( 'play', $groups[0]['episodes'][0] ?? array() ), 'no playback URL in episode payload' );
assert_eq( 'زیرنویس فارسی', $groups[0]['episodes'][0]['subtitles'][0]['label'] ?? null, 'free Persian label prefixed once' );
assert_eq( '720p', $groups[1]['quality'] ?? null, 'second group is 720p' );
assert_eq( 1, count( $groups[1]['episodes'] ?? array() ), '720p has only E01' );
assert_eq( 'https://example.test/dl?post=10&i=1', $groups[1]['episodes'][0]['href'] ?? null, '720p E01 keeps its own href' );
assert_eq( '', $groups[2]['quality'] ?? 'x', 'subs-only group has empty quality' );
assert_eq( 1, count( $groups[2]['episodes'] ?? array() ), 'subs-only episode included once' );
assert_eq( 'زیرنویس انگلیسی', $groups[2]['episodes'][0]['subtitles'][0]['label'] ?? null, 'EN code label → Persian language name' );

echo "\nstreamit_child_subtitle_language_label tests\n\n";

assert_eq( 'زیرنویس انگلیسی', streamit_child_subtitle_language_label( 'ENG', 'eng' ), 'ENG/eng → English' );
assert_eq( 'زیرنویس فارسی', streamit_child_subtitle_language_label( 'FA', 'fa' ), 'FA/fa → Persian' );
assert_eq( 'زیرنویس', streamit_child_subtitle_language_label( 'SUB', '' ), 'SUB with no language → generic' );
assert_eq( 'زیرنویس', streamit_child_subtitle_language_label( '', '' ), 'empty → generic' );
assert_eq( 'زیرنویس انگلیسی', streamit_child_subtitle_language_label( 'ENG', '' ), 'code-like label without srclang still mapped' );
assert_eq( 'زیرنویس XYZ', streamit_child_subtitle_language_label( 'XYZ', 'xyz' ), 'unknown code kept, not invented' );
assert_eq( 'زیرنویس هماهنگ', streamit_child_subtitle_language_label( 'هماهنگ', 'fa' ), 'admin free label kept' );

$dup_subs = streamit_child_series_download_quality_groups(
	array(
		array(
			'label'        => 'S01E01',
			'has_download' => true,
			'sources'      => array(
				array(
					'quality' => '1080p',
					'href'    => 'https://example.test/v',
				),
			),
			'subtitles'    => array(
				array(
					'label' => 'SUB',
					'href'  => 'https://example.test/s1',
				),
				array(
					'label' => 'SUB',
					'href'  => 'https://example.test/s2',
				),
				array(
					'label'   => 'ENG',
					'srclang' => 'eng',
					'href'    => 'https://example.test/s3',
				),
			),
		),
	)
);
$dup_labels = array_column( $dup_subs[0]['episodes'][0]['subtitles'] ?? array(), 'label' );
assert_eq( array( 'زیرنویس', 'زیرنویس 2', 'زیرنویس انگلیسی' ), $dup_labels, 'repeated unknown subtitles get distinct labels' );

$mixed_encoder = streamit_child_series_download_quality_groups(
	array(
		array(
			'id'           => 1,
			'label'        => 'S01E01',
			'has_download' => true,
			'sources'      => array(
				array(
					'quality' => '1080p',
					'name'    => 'A',
					'link'    => $path,
					'href'    => 'https://example.test/a',
				),
				array(
					'quality' => '1080p',
					'name'    => 'B',
					'link'    => $path,
					'href'    => 'https://example.test/b',
				),
			),
			'subtitles'    => array(),
		),
	)
);
assert_eq( 2, count( $mixed_encoder ), 'same quality different encoders stay separate' );
assert_eq( 'A', $mixed_encoder[0]['encoder'] ?? null, 'encoder A group' );
assert_eq( 'B', $mixed_encoder[1]['encoder'] ?? null, 'encoder B group' );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}

echo "All series-download catalog tests passed.\n";
exit( 0 );

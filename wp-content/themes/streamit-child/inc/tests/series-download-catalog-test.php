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

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		unset( $domain );
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( (string) $str );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return trim( (string) $url );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
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
require_once dirname( __DIR__ ) . '/subtitles.php';
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
assert_eq( array( 'زیرنویس', 'زیرنویس', 'زیرنویس انگلیسی' ), $dup_labels, 'repeated unknown subtitles get clear language labels without duplicate numbering' );

echo "\nstreamit_child_infer_subtitle_source_type tests\n\n";

assert_eq( 'WEB-DL', streamit_child_infer_subtitle_source_type( 'series/Show/SUB.ENG/WEB-DL/S01E01.srt' ), 'infer from folder WEB-DL' );
assert_eq( 'HDTV', streamit_child_infer_subtitle_source_type( 'series/Show/SUB/HDTV/S01E01.srt' ), 'infer from folder HDTV' );
assert_eq( 'WEB-DL', streamit_child_infer_subtitle_source_type( 'series/Show/SUB/Show.S01E01.WEB-DL.fa.srt' ), 'infer from filename WEB-DL' );
assert_eq( 'BluRay', streamit_child_infer_subtitle_source_type( 'series/Show/SUB/Show.S01E01.BluRay.fa.srt' ), 'infer from filename BluRay' );
assert_eq( '', streamit_child_infer_subtitle_source_type( 'series/Show/SUB/Show.S01E01.fa.srt' ), 'safe unclassified returns empty string' );

echo "\nstreamit_child_series_download_subtitle_groups tests\n\n";

$sub_test_episodes = array(
	array(
		'id'           => 101,
		'label'        => 'S01E01',
		'title'        => 'Episode 1',
		'has_download' => true,
		'sources'      => array(
			array( 'quality' => '1080p', 'href' => 'https://example.test/dl/e01' ),
		),
		'subtitles'    => array(
			array(
				'label'       => 'FA',
				'srclang'     => 'fa',
				'source_type' => 'WEB-DL',
				'href'        => 'https://example.test/sub/e01.webdl.fa',
				'format'      => 'SRT',
			),
			array(
				'label'       => 'ENG',
				'srclang'     => 'en',
				'source_type' => 'WEB-DL',
				'href'        => 'https://example.test/sub/e01.webdl.en',
				'format'      => 'SRT',
			),
			array(
				'label'       => 'FA',
				'srclang'     => 'fa',
				'source_type' => 'HDTV',
				'href'        => 'https://example.test/sub/e01.hdtv.fa',
				'format'      => 'SRT',
			),
			array(
				'label'   => 'SUB',
				'srclang' => '',
				'url'     => 'series/show/SUB/unclassified.srt',
				'href'    => 'https://example.test/sub/e01.other',
				'format'  => 'SRT',
			),
			// Previously imported subtitle with no source_type field, but path containing HDTV
			array(
				'label'   => 'ENG',
				'srclang' => 'eng',
				'url'     => 'series/show/SUB/HDTV/e01.eng.srt',
				'href'    => 'https://example.test/sub/e01.legacy.hdtv.en',
				'format'  => 'SRT',
			),
		),
	),
	array(
		'id'           => 102,
		'label'        => 'S01E02',
		'title'        => 'Episode 2',
		'has_download' => true,
		'sources'      => array(
			array( 'quality' => '1080p', 'href' => 'https://example.test/dl/e02' ),
		),
		'subtitles'    => array(
			array(
				'label'       => 'FA',
				'srclang'     => 'fa',
				'source_type' => 'WEB-DL',
				'href'        => 'https://example.test/sub/e02.webdl.fa',
				'format'      => 'SRT',
			),
		),
	),
);

$sg = streamit_child_series_download_subtitle_groups( $sub_test_episodes );
assert_eq( 3, count( $sg ), 'three subtitle release groups: WEB-DL, HDTV, and unclassified' );
assert_eq( 'WEB-DL', $sg[0]['source_type'] ?? null, 'first release group is WEB-DL' );
assert_eq( 3, $sg[0]['count'] ?? null, 'WEB-DL has 3 subtitle tracks across 2 episodes' );
assert_eq( 2, count( $sg[0]['episodes'] ?? array() ), 'WEB-DL spans 2 episodes' );
assert_eq( 'زیرنویس فارسی', $sg[0]['episodes'][0]['subtitles'][0]['label'] ?? null, 'clear Persian label without numbering' );
assert_eq( 'زیرنویس انگلیسی', $sg[0]['episodes'][0]['subtitles'][1]['label'] ?? null, 'clear English label without numbering' );

assert_eq( 'HDTV', $sg[1]['source_type'] ?? null, 'second release group is HDTV' );
assert_eq( 2, $sg[1]['count'] ?? null, 'HDTV has 2 tracks (including safely inferred legacy row)' );
assert_eq( 'زیرنویس فارسی', $sg[1]['episodes'][0]['subtitles'][0]['label'] ?? null, 'HDTV Persian label' );
assert_eq( 'زیرنویس انگلیسی', $sg[1]['episodes'][0]['subtitles'][1]['label'] ?? null, 'HDTV English label' );

assert_eq( '', $sg[2]['source_type'] ?? 'x', 'unclassified group has empty source_type' );
assert_eq( 'سایر نسخه‌ها', $sg[2]['label'] ?? null, 'unclassified group has Persian fallback label' );
assert_eq( 1, $sg[2]['count'] ?? null, 'unclassified group has 1 track' );
assert_eq( 'زیرنویس', $sg[2]['episodes'][0]['subtitles'][0]['label'] ?? null, 'unclassified generic label' );

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

echo "\nstreamit_child_episode_display_file_size tests (+23 MB, display only)\n\n";

assert_eq( '1.52 GB', streamit_child_episode_display_file_size( '1.50 GB' ), 'scanner GB label +23 MB' );
assert_eq( '823.00 MB', streamit_child_episode_display_file_size( '800 MB' ), 'MB label +23 MB keeps MB' );
assert_eq( '428.57 MB', streamit_child_episode_display_file_size( '405.57 MB' ), 'two-decimal MB label' );
assert_eq( '1.01 GB', streamit_child_episode_display_file_size( '1010 MB' ), 'crossing 1024 MB promotes to GB' );
assert_eq( '1.00 GB', streamit_child_episode_display_file_size( '1001 MB' ), 'exactly 1024 MB → 1.00 GB' );
assert_eq( '1.02 GB', streamit_child_episode_display_file_size( '1 GB' ), 'admin integer GB label' );
assert_eq( '2.12 GB', streamit_child_episode_display_file_size( '2.1GB' ), 'no space between number and unit' );
assert_eq( '1.48 GB', streamit_child_episode_display_file_size( ' 1.46 gb ' ), 'lowercase unit and padding' );
assert_eq( '23.49 MB', streamit_child_episode_display_file_size( '500 KB' ), 'KB label converts up' );
assert_eq( '', streamit_child_episode_display_file_size( '' ), 'missing size stays empty' );
assert_eq( '', streamit_child_episode_display_file_size( null ), 'null size stays empty' );
assert_eq( 'نامشخص', streamit_child_episode_display_file_size( 'نامشخص' ), 'unknown text unchanged' );
assert_eq( '1,5 GB', streamit_child_episode_display_file_size( '1,5 GB' ), 'unrecognised format unchanged' );
assert_eq( '1.5 GiB', streamit_child_episode_display_file_size( '1.5 GiB' ), 'unsupported unit unchanged' );
assert_eq( '0 B', streamit_child_episode_display_file_size( '0 B' ), 'zero size unchanged' );

$size_seasons  = array(
	array(
		'name'          => 'فصل ۱',
		'season_number' => '1',
		'episodes'      => array( 30, 31 ),
	),
);
$size_episodes = array(
	30 => array(
		'title'          => 'Sized',
		'season_number'  => '1',
		'episode_number' => 'E01',
		'sources'        => array(
			array(
				'quality'          => '1080p',
				'download_content' => $path,
				'file_size'        => '1.50 GB',
			),
		),
		'subtitles'      => array(),
	),
	31 => array(
		'title'          => 'Unsized',
		'season_number'  => '1',
		'episode_number' => 'E02',
		'sources'        => array(
			array(
				'quality'          => '1080p',
				'download_content' => $path,
			),
		),
		'subtitles'      => array(),
	),
);
$size_episodes_before = $size_episodes;

$size_catalog = streamit_child_build_series_download_catalog_from_data( $size_seasons, $size_episodes, true );
assert_eq( '1.50 GB', $size_catalog['seasons'][0]['episodes'][0]['sources'][0]['file_size'] ?? null, 'catalog keeps the stored size (adjusted only at render)' );

$size_season_episodes = $size_catalog['seasons'][0]['episodes'];
$size_groups          = streamit_child_series_download_quality_groups( $size_season_episodes );
assert_eq( '1.52 GB', $size_groups[0]['episodes'][0]['file_size'] ?? null, 'series download row shows stored size +23 MB exactly once' );
assert_eq( '', $size_groups[0]['episodes'][1]['file_size'] ?? null, 'episode without a size stays empty' );

$size_groups_again = streamit_child_series_download_quality_groups( $size_season_episodes );
assert_eq( $size_groups, $size_groups_again, 'rebuilding the rows (reload/rerender) gives the same sizes' );
assert_eq( $size_episodes_before, $size_episodes, 'underlying episode data unchanged' );
assert_eq( '1.50 GB', $size_season_episodes[0]['sources'][0]['file_size'] ?? null, 'catalog rows unchanged after grouping' );

$movie_source = array(
	'quality'          => '1080p',
	'download_content' => 'Movie/Film/Film.1080p.mkv',
	'file_size'        => '2.1 GB',
);
$movie_rows   = streamit_child_get_downloadable_sources( array( $movie_source ) );
assert_eq( '2.1 GB', $movie_rows[0]['file_size'] ?? null, 'movie source size not adjusted' );
assert_true( in_array( '2.1 GB', streamit_child_download_source_meta_values( $movie_rows[0] ), true ), 'movie modal meta shows the stored size' );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}

echo "All series-download catalog tests passed.\n";
exit( 0 );

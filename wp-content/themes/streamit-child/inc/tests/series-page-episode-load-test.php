<?php
/**
 * CLI tests: first-season slider + download catalog share episode rows.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/series-page-episode-load-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-series-page-episode-load-test/' );
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

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}

if ( ! function_exists( 'streamit_child_resolve_download_href' ) ) {
	function streamit_child_resolve_download_href( $stored, $post_id, $index = 0 ) {
		$stored = trim( (string) $stored );
		return '' === $stored ? '' : 'https://example.test/dl?post=' . (int) $post_id . '&i=' . (int) $index;
	}
}

if ( ! function_exists( 'streamit_child_resolve_subtitle_url' ) ) {
	function streamit_child_resolve_subtitle_url( $stored, $type = 'v' ) {
		unset( $type );
		$stored = trim( (string) $stored );
		return '' === $stored ? '' : 'https://example.test/sub/' . rawurlencode( $stored );
	}
}

$GLOBALS['spel_queries']     = array();
$GLOBALS['spel_episode_map'] = array();

/**
 * In-memory episodemeta for media flags.
 */
class Streamit_Child_Page_Load_Test_Wpdb {
	public $prefix               = 'wp_';
	public $streamit_episodemeta = 'wp_streamit_episodemeta';
	public $last_error           = '';

	public function prepare( $query, ...$args ) {
		$offset = 0;
		return preg_replace_callback(
			'/%[dfs]/',
			static function ( $match ) use ( &$args, &$offset ) {
				$value = $args[ $offset ] ?? '';
				$offset++;
				if ( '%d' === $match[0] || '%f' === $match[0] ) {
					return (string) (int) $value;
				}
				return "'" . str_replace( "'", "''", (string) $value ) . "'";
			},
			$query
		);
	}

	public function get_results( $sql ) {
		unset( $sql );
		$this->last_error = '';
		$rows             = array();
		$meta_id          = 1;
		foreach ( $GLOBALS['spel_episode_map'] as $id => $data ) {
			if ( ! empty( $data['sources'] ) ) {
				$rows[] = (object) array(
					'streamit_episode_id' => (int) $id,
					'meta_id'             => $meta_id++,
					'meta_key'            => '_sources',
					'meta_value'          => serialize( $data['sources'] ),
				);
			}
			if ( ! empty( $data['subtitles'] ) ) {
				$rows[] = (object) array(
					'streamit_episode_id' => (int) $id,
					'meta_id'             => $meta_id++,
					'meta_key'            => '_subtitles',
					'meta_value'          => serialize( $data['subtitles'] ),
				);
			}
		}
		return $rows;
	}
}

$GLOBALS['wpdb'] = new Streamit_Child_Page_Load_Test_Wpdb();

require_once dirname( __DIR__ ) . '/sources-download.php';
require_once dirname( __DIR__ ) . '/subtitles.php';
require_once dirname( __DIR__ ) . '/series-download.php';

if ( ! function_exists( 'streamit_get_episodes' ) ) {
	function streamit_get_episodes( $args ) {
		$include = isset( $args['include'] ) && is_array( $args['include'] ) ? $args['include'] : array();
		$GLOBALS['spel_queries'][] = array_map( 'intval', $include );
		$objs                      = array();
		foreach ( $include as $id ) {
			$id = (int) $id;
			if ( ! isset( $GLOBALS['spel_episode_map'][ $id ] ) ) {
				continue;
			}
			$data   = $GLOBALS['spel_episode_map'][ $id ];
			$objs[] = new class( $id, $data ) {
				private $id;
				private $data;
				public function __construct( $id, $data ) {
					$this->id   = $id;
					$this->data = $data;
				}
				public function get_id() {
					return $this->id;
				}
				public function get_post_title() {
					return $this->data['title'] ?? '';
				}
				public function get_permalink() {
					return $this->data['permalink'] ?? ( 'https://example.test/episode-' . $this->id );
				}
				public function get_meta( $key ) {
					if ( '_sources' === $key ) {
						return $this->data['sources'] ?? array();
					}
					if ( '_subtitles' === $key ) {
						return $this->data['subtitles'] ?? array();
					}
					if ( '_season_number' === $key ) {
						return $this->data['season_number'] ?? '';
					}
					if ( '_episode_number' === $key ) {
						return $this->data['episode_number'] ?? '';
					}
					return null;
				}
			};
		}
		return (object) array( 'results' => $objs );
	}
}

$failures = 0;

function spel_assert( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
}

function spel_reset( array $episodes ): void {
	$GLOBALS['spel_episode_map'] = $episodes;
	$GLOBALS['spel_queries']     = array();
	streamit_child_reset_series_download_episode_map_cache();
	streamit_child_reset_episode_media_flags_cache();
}

echo "=== Series page episode load ===\n\n";

$episodes = array(
	101 => array(
		'title'          => 'S01E01',
		'season_number'  => '1',
		'episode_number' => 'E01',
		'sources'        => array(
			array(
				'quality'          => '1080p',
				'download_content' => 'series/Show/S01E01.mkv',
			),
		),
		'subtitles'      => array(),
	),
	102 => array(
		'title'          => 'S01E02',
		'season_number'  => '1',
		'episode_number' => 'E02',
		'sources'        => array(
			array(
				'quality'          => '1080p',
				'download_content' => 'series/Show/S01E02.mkv',
			),
		),
		'subtitles'      => array(),
	),
	201 => array(
		'title'          => 'S02E01',
		'season_number'  => '2',
		'episode_number' => 'E01',
		'sources'        => array(
			array(
				'quality'          => '720p',
				'download_content' => 'series/Show/S02E01.mkv',
			),
		),
		'subtitles'      => array(),
	),
	202 => array(
		'title'          => 'S02E02 missing',
		'season_number'  => '2',
		'episode_number' => 'E02',
		'sources'        => array(),
		'subtitles'      => array(),
	),
	301 => array(
		'title'          => 'S03E01 subs',
		'season_number'  => '3',
		'episode_number' => 'E01',
		'sources'        => array(),
		'subtitles'      => array(
			array(
				'label'   => 'FA',
				'srclang' => 'fa',
				'url'     => 'series/Show/S03E01.fa.srt',
			),
		),
	),
);

$seasons_meta = array(
	array(
		'name'          => 'فصل ۱',
		'season_number' => '1',
		'episodes'      => array( 101, 102 ),
	),
	array(
		'name'          => 'فصل ۲',
		'season_number' => '2',
		'episodes'      => array( 201, 202 ),
	),
	array(
		'name'          => 'فصل ۳',
		'season_number' => '3',
		'episodes'      => array( 301 ),
	),
);

$show = new class( $seasons_meta ) {
	private $seasons;
	public function __construct( $seasons ) {
		$this->seasons = $seasons;
	}
	public function get_id() {
		return 7001;
	}
	public function get_meta( $key ) {
		return '_seasons' === $key ? $this->seasons : null;
	}
};

echo "1. First-season objects preserve order; missing media stays out of availability\n";
spel_reset( $episodes );
$first = streamit_child_get_series_episode_objects( array( 102, 101, 0, 'x' ) );
spel_assert( 2 === count( $first ), 'two episode objects returned' );
spel_assert( 102 === (int) $first[0]->get_id() && 101 === (int) $first[1]->get_id(), 'slider order follows the season id list' );
spel_assert( array( array( 101, 102 ) ) === $GLOBALS['spel_queries'], 'one streamit_get_episodes call for the first season' );

$available = streamit_child_get_available_seasons( $show );
spel_assert( 3 === count( $available ), 'three seasons have some media' );
spel_assert( array( 201 ) === $available[1]['episodes'], 'season 2 drops the missing-media episode' );
spel_assert( array( 301 ) === $available[2]['episodes'], 'subtitle-only season stays available for tabs/download' );

echo "\n2. Download catalog reuses memoized rows and skips no-media ids\n";
$GLOBALS['spel_queries'] = array();
$catalog                 = streamit_child_build_series_download_catalog( $show );
spel_assert( 1 === count( $GLOBALS['spel_queries'] ), 'catalog queries only the ids not already memoized' );
$queried = $GLOBALS['spel_queries'][0];
sort( $queried );
spel_assert( array( 201, 301 ) === $queried, 'second query is season 2 video + season 3 subtitle only' );
spel_assert( 3 === count( $catalog['seasons'] ), 'catalog keeps every season that has media' );
spel_assert( 2 === (int) $catalog['seasons'][0]['downloadable_episode_count'], 'season 1 downloadable count' );
spel_assert( 1 === (int) $catalog['seasons'][1]['downloadable_episode_count'], 'season 2 omits missing-media episode from downloads' );
spel_assert( 1 === (int) $catalog['seasons'][2]['downloadable_episode_count'], 'subtitle-only season remains downloadable' );
spel_assert( true === $catalog['can_download'], 'download permission preserved' );

$groups = streamit_child_series_download_quality_groups( $catalog['seasons'][0]['episodes'] );
spel_assert( '1080p' === ( $groups[0]['quality'] ?? '' ), 'season 1 quality group retained' );
spel_assert( 2 === count( $groups[0]['episodes'] ), 'season 1 quality group lists both episodes' );

echo "\n3. Repeated map reads do not query again\n";
$before = count( $GLOBALS['spel_queries'] );
$again  = streamit_child_get_series_episode_objects( array( 101, 201 ) );
spel_assert( 2 === count( $again ), 'memoized objects still available' );
spel_assert( $before === count( $GLOBALS['spel_queries'] ), 'no extra streamit_get_episodes after warm memo' );

echo "\n4. Empty / locked catalog edges\n";
spel_reset(
	array(
		501 => array(
			'title'          => 'Empty',
			'season_number'  => '1',
			'episode_number' => 'E01',
			'sources'        => array(),
			'subtitles'      => array(),
		),
	)
);
$empty_show = new class() {
	public function get_id() {
		return 7002;
	}
	public function get_meta( $key ) {
		return '_seasons' === $key
			? array(
				array(
					'name'          => 'فصل ۱',
					'season_number' => '1',
					'episodes'      => array( 501 ),
				),
			)
			: null;
	}
};
$empty_catalog = streamit_child_build_series_download_catalog( $empty_show );
spel_assert( array() === $empty_catalog['seasons'], 'no seasons when every episode lacks media' );
spel_assert( array() === $GLOBALS['spel_queries'], 'no episode list query when availability is empty' );

$unlocked = streamit_child_build_series_download_catalog_from_data(
	array(
		array(
			'name'          => 'فصل ۱',
			'season_number' => '1',
			'episodes'      => array( 101 ),
		),
	),
	array(
		101 => array(
			'title'          => 'S01E01',
			'season_number'  => '1',
			'episode_number' => 'E01',
			'sources'        => array(
				array(
					'quality'          => '1080p',
					'download_content' => 'series/Show/S01E01.mkv',
				),
			),
			'subtitles'      => array(),
		),
	),
	false
);
spel_assert( false === $unlocked['can_download'], 'locked catalog keeps can_download false' );
spel_assert( '' === ( $unlocked['seasons'][0]['episodes'][0]['sources'][0]['href'] ?? 'x' ), 'locked catalog omits hrefs' );

echo "\n";
if ( 0 === $failures ) {
	echo "All series page episode load tests PASSED!\n";
	exit( 0 );
}

echo "{$failures} series page episode load test(s) FAILED.\n";
exit( 1 );

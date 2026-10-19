<?php
/**
 * CLI tests for series frontend media availability filtering.
 *
 * Verifies that the TV show season selector, episode tabs, play button,
 * banner season count, and download catalog consistently reflect actual
 * media availability on the media host.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/series-frontend-availability-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-series-frontend-availability-test/' );
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
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

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return trim( (string) $url );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'streamit_get_permalink' ) ) {
	function streamit_get_permalink( $post_type, $post_name ) {
		return 'https://example.test/' . rawurlencode( (string) $post_type ) . '/' . rawurlencode( (string) $post_name );
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

require_once dirname( __DIR__ ) . '/sources-download.php';
require_once dirname( __DIR__ ) . '/subtitles.php';
require_once dirname( __DIR__ ) . '/series-download.php';

/**
 * Serves fixture episode meta to streamit_child_episode_media_flags().
 */
class Streamit_Child_Availability_Test_Wpdb {
	public $prefix = 'wp_';
	public $streamit_episodemeta = 'wp_streamit_episodemeta';
	public $last_error = '';

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
		$map              = isset( $GLOBALS['test_episode_map'] ) && is_array( $GLOBALS['test_episode_map'] ) ? $GLOBALS['test_episode_map'] : array();
		$meta_id          = 1;

		foreach ( $map as $id => $data ) {
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

$GLOBALS['wpdb'] = new Streamit_Child_Availability_Test_Wpdb();

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

echo "=== Series Frontend Media Availability Tests ===\n\n";

// -----------------------------------------------------------------------------
// Test 1: TMDB seasons 1, 2, and 3 are stored, but only season 3 has available
// episode sources: only season 3 appears in availability-dependent frontend elements.
// -----------------------------------------------------------------------------
echo "1. Partial Season Availability (S1, S2 stored without media; S3 has media)\n";

$seasons_three = array(
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
		'episodes'      => array( 301, 302 ),
	),
);

$episodes_s3_only = array(
	// Season 1: metadata only
	101 => array(
		'title'          => 'S01E01',
		'season_number'  => '1',
		'episode_number' => 'E01',
		'sources'        => array(),
		'subtitles'      => array(),
	),
	102 => array(
		'title'          => 'S01E02',
		'season_number'  => '1',
		'episode_number' => 'E02',
		'sources'        => array(),
		'subtitles'      => array(),
	),
	// Season 2: metadata only
	201 => array(
		'title'          => 'S02E01',
		'season_number'  => '2',
		'episode_number' => 'E01',
		'sources'        => array(),
		'subtitles'      => array(),
	),
	202 => array(
		'title'          => 'S02E02',
		'season_number'  => '2',
		'episode_number' => 'E02',
		'sources'        => array(),
		'subtitles'      => array(),
	),
	// Season 3: available media
	301 => array(
		'title'          => 'S03E01',
		'season_number'  => '3',
		'episode_number' => 'E01',
		'sources'        => array(
			array(
				'quality'          => '1080p',
				'download_content' => 'series/us/2026/Show/1080p/Show.S03E01.1080p.mkv',
			),
		),
		'subtitles'      => array(),
		'permalink'      => 'https://example.test/episode/show-s03e01',
		'object'         => (object) array(
			'ID'        => 301,
			'post_type' => 'episode',
			'post_name' => 'show-s03e01',
		),
	),
	302 => array(
		'title'          => 'S03E02',
		'season_number'  => '3',
		'episode_number' => 'E02',
		'sources'        => array(
			array(
				'quality'          => '1080p',
				'download_content' => 'series/us/2026/Show/1080p/Show.S03E02.1080p.mkv',
			),
		),
		'subtitles'      => array(),
	),
);

$avail_s3 = streamit_child_filter_available_seasons_data( $seasons_three, $episodes_s3_only );

assert_eq( 1, count( $avail_s3 ), 'only 1 season returned when S1 and S2 have no media' );
assert_eq( '3', $avail_s3[0]['season_number'], 'retained season retains original season_number "3" (no renumbering)' );
assert_eq( 'فصل ۳', $avail_s3[0]['name'], 'retained season retains original name' );
assert_eq( array( 301, 302 ), $avail_s3[0]['episodes'], 'retained season contains exactly S3 episode IDs' );

// Banner season count
$banner_count = count( $avail_s3 );
assert_eq( 1, $banner_count, 'banner season count is 1 Season (not 3 Seasons)' );

// Play button target
$mock_tvshow_s3 = new class( $seasons_three, $episodes_s3_only ) {
	private $seasons;
	private $ep_map;
	public function __construct( $s, $e ) {
		$this->seasons = $s;
		$this->ep_map  = $e;
	}
	public function get_id() { return 5001; }
	public function get_meta( $key ) {
		return '_seasons' === $key ? $this->seasons : null;
	}
};

// Global mock for episode lookup
$GLOBALS['test_episode_map'] = $episodes_s3_only;
streamit_child_reset_episode_media_flags_cache();
if ( ! function_exists( 'streamit_get_episode' ) ) {
	function streamit_get_episode( $id ) {
		if ( isset( $GLOBALS['test_episode_map'][ $id ]['object'] ) ) {
			return $GLOBALS['test_episode_map'][ $id ]['object'];
		}
		return (object) array(
			'ID'        => $id,
			'post_type' => 'episode',
			'post_name' => 'episode-' . $id,
			'get_id'    => function() use ( $id ) { return $id; },
			'get_post_type' => function() { return 'episode'; },
			'get_post_name' => function() use ( $id ) { return 'episode-' . $id; },
		);
	}
}

// Global mock for streamit_get_episodes
if ( ! function_exists( 'streamit_get_episodes' ) ) {
	function streamit_get_episodes( $args ) {
		$include = isset( $args['include'] ) ? $args['include'] : array();
		$objs    = array();
		foreach ( $include as $id ) {
			if ( isset( $GLOBALS['test_episode_map'][ $id ] ) ) {
				$data   = $GLOBALS['test_episode_map'][ $id ];
				$objs[] = new class( $id, $data ) {
					private $id;
					private $data;
					public function __construct( $id, $data ) {
						$this->id   = $id;
						$this->data = $data;
					}
					public function get_id() { return $this->id; }
					public function get_post_title() { return $this->data['title'] ?? ''; }
					public function get_post_name() { return 'episode-' . $this->id; }
					public function get_post_type() { return 'episode'; }
					public function get_permalink() { return $this->data['permalink'] ?? ('https://example.test/episode-' . $this->id); }
					public function get_meta( $k ) {
						if ( '_sources' === $k ) return $this->data['sources'] ?? array();
						if ( '_subtitles' === $k ) return $this->data['subtitles'] ?? array();
						if ( '_season_number' === $k ) return $this->data['season_number'] ?? '';
						if ( '_episode_number' === $k ) return $this->data['episode_number'] ?? '';
						return null;
					}
				};
			}
		}
		return (object) array( 'results' => $objs );
	}
}

$first_ep = streamit_child_get_first_available_episode( $mock_tvshow_s3 );
assert_true( ! empty( $first_ep ), 'first available episode found' );
assert_eq( 301, $first_ep->ID, 'play button targets Season 3 Episode 1 (ID 301)' );

echo "\n";

// -----------------------------------------------------------------------------
// Test 2: A season contains 10 metadata episodes but only 4 have media:
// only those four are presented as available.
// -----------------------------------------------------------------------------
echo "2. Partial Episode Availability (10 metadata episodes, 4 have media)\n";

$ep_ids_ten = range( 1, 10 );
$seasons_ten = array(
	array(
		'name'          => 'فصل ۱',
		'season_number' => '1',
		'episodes'      => $ep_ids_ten,
	),
);

$episodes_four_active = array();
for ( $i = 1; $i <= 10; $i++ ) {
	$has_file = ( $i <= 4 ); // episodes 1-4 have media; 5-10 do not
	$episodes_four_active[ $i ] = array(
		'title'          => "Episode {$i}",
		'season_number'  => '1',
		'episode_number' => sprintf( 'E%02d', $i ),
		'sources'        => $has_file ? array(
			array(
				'quality'          => '720p',
				'download_content' => "series/Show/S01E{$i}.mkv",
			),
		) : array(),
		'subtitles'      => array(),
	);
}

$avail_four = streamit_child_filter_available_seasons_data( $seasons_ten, $episodes_four_active );

assert_eq( 1, count( $avail_four ), 'season 1 is retained' );
assert_eq( 4, count( $avail_four[0]['episodes'] ), 'season contains exactly 4 available episodes' );
assert_eq( array( 1, 2, 3, 4 ), $avail_four[0]['episodes'], 'episodes array holds only IDs 1, 2, 3, 4' );

echo "\n";

// -----------------------------------------------------------------------------
// Test 3: Season 0 / Specials handling
// - Season 0 has no media: does not appear and does not become default play target.
// - Season 0 has media AND Season 1 has media: Season 1 is preferred for play button.
// - Season 0 has media, but regular seasons have none: Season 0 becomes play target.
// -----------------------------------------------------------------------------
echo "3. Season 0 / Specials Handling\n";

$seasons_with_s0 = array(
	array(
		'name'          => 'قسمت‌های ویژه',
		'season_number' => '0',
		'episodes'      => array( 50 ),
	),
	array(
		'name'          => 'فصل ۱',
		'season_number' => '1',
		'episodes'      => array( 101 ),
	),
);

// Subcase 3a: Season 0 has no media; Season 1 has media
$episodes_s0_empty = array(
	50  => array(
		'title'          => 'Behind the Scenes',
		'season_number'  => '0',
		'episode_number' => 'E01',
		'sources'        => array(),
		'subtitles'      => array(),
	),
	101 => array(
		'title'          => 'Pilot',
		'season_number'  => '1',
		'episode_number' => 'E01',
		'sources'        => array(
			array(
				'quality'          => '1080p',
				'download_content' => 'series/Show/S01E01.mkv',
			),
		),
		'subtitles'      => array(),
		'object'         => (object) array( 'ID' => 101, 'post_type' => 'episode', 'post_name' => 'ep-101' ),
	),
);

$avail_s0_empty = streamit_child_filter_available_seasons_data( $seasons_with_s0, $episodes_s0_empty );
assert_eq( 1, count( $avail_s0_empty ), 'Season 0 omitted when it has no media' );
assert_eq( '1', $avail_s0_empty[0]['season_number'], 'Only Season 1 is returned' );

$mock_tvshow_s0_empty = new class( $seasons_with_s0, $episodes_s0_empty ) {
	private $s;
	private $e;
	public function __construct( $s, $e ) { $this->s = $s; $this->e = $e; }
	public function get_id() { return 5002; }
	public function get_meta( $k ) { return '_seasons' === $k ? $this->s : null; }
};
$GLOBALS['test_episode_map'] = $episodes_s0_empty;
streamit_child_reset_episode_media_flags_cache();
$first_ep_s0_empty = streamit_child_get_first_available_episode( $mock_tvshow_s0_empty );
assert_eq( 101, $first_ep_s0_empty->ID, 'Play button targets Season 1 Episode 1 (not Season 0)' );

// Subcase 3b: Both Season 0 and Season 1 have media → Season 1 is still preferred for play button
$episodes_both_have_media = array(
	50  => array(
		'title'          => 'Special Clip',
		'season_number'  => '0',
		'episode_number' => 'E01',
		'sources'        => array(
			array( 'quality' => '1080p', 'download_content' => 'series/Show/S00E01.mkv' ),
		),
		'subtitles'      => array(),
		'object'         => (object) array( 'ID' => 50, 'post_type' => 'episode', 'post_name' => 'ep-50' ),
	),
	101 => array(
		'title'          => 'Pilot',
		'season_number'  => '1',
		'episode_number' => 'E01',
		'sources'        => array(
			array( 'quality' => '1080p', 'download_content' => 'series/Show/S01E01.mkv' ),
		),
		'subtitles'      => array(),
		'object'         => (object) array( 'ID' => 101, 'post_type' => 'episode', 'post_name' => 'ep-101' ),
	),
);

$avail_both = streamit_child_filter_available_seasons_data( $seasons_with_s0, $episodes_both_have_media );
assert_eq( 2, count( $avail_both ), 'Both Season 0 and Season 1 returned when both have media' );

$mock_tvshow_both = new class( $seasons_with_s0, $episodes_both_have_media ) {
	private $s;
	public function __construct( $s ) { $this->s = $s; }
	public function get_id() { return 5003; }
	public function get_meta( $k ) { return '_seasons' === $k ? $this->s : null; }
};
$GLOBALS['test_episode_map'] = $episodes_both_have_media;
streamit_child_reset_episode_media_flags_cache();
$first_ep_both = streamit_child_get_first_available_episode( $mock_tvshow_both );
assert_eq( 101, $first_ep_both->ID, 'Play button prefers Season 1 Episode 1 over Season 0 Specials' );

// Subcase 3c: ONLY Season 0 has media (regular seasons empty) → Season 0 plays
$episodes_only_s0 = array(
	50  => array(
		'title'          => 'Special Clip',
		'season_number'  => '0',
		'episode_number' => 'E01',
		'sources'        => array(
			array( 'quality' => '1080p', 'download_content' => 'series/Show/S00E01.mkv' ),
		),
		'subtitles'      => array(),
		'object'         => (object) array( 'ID' => 50, 'post_type' => 'episode', 'post_name' => 'ep-50' ),
	),
	101 => array(
		'title'          => 'Pilot',
		'season_number'  => '1',
		'episode_number' => 'E01',
		'sources'        => array(),
		'subtitles'      => array(),
	),
);

$mock_tvshow_only_s0 = new class( $seasons_with_s0, $episodes_only_s0 ) {
	private $s;
	public function __construct( $s ) { $this->s = $s; }
	public function get_id() { return 5004; }
	public function get_meta( $k ) { return '_seasons' === $k ? $this->s : null; }
};
$GLOBALS['test_episode_map'] = $episodes_only_s0;
streamit_child_reset_episode_media_flags_cache();
$first_ep_only_s0 = streamit_child_get_first_available_episode( $mock_tvshow_only_s0 );
assert_eq( 50, $first_ep_only_s0->ID, 'Play button falls back to Season 0 when no regular seasons have media' );

echo "\n";

// -----------------------------------------------------------------------------
// Test 4: A season has subtitles but no video sources:
// - Appears in season tabs & download box (media exists).
// - Does NOT become the play target (playback requires video).
// -----------------------------------------------------------------------------
echo "4. Subtitles-Only Season Behavior\n";

$seasons_subs_only = array(
	array(
		'name'          => 'فصل ۱',
		'season_number' => '1',
		'episodes'      => array( 601 ),
	),
);

$episodes_subs_only = array(
	601 => array(
		'title'          => 'Episode 1',
		'season_number'  => '1',
		'episode_number' => 'E01',
		'sources'        => array(),
		'subtitles'      => array(
			array(
				'label'   => 'FA',
				'srclang' => 'fa',
				'url'     => 'series/Show/S01E01.fa.srt',
			),
		),
		'object'         => (object) array( 'ID' => 601, 'post_type' => 'episode', 'post_name' => 'ep-601' ),
	),
);

// General media check (tabs / download box)
$avail_subs = streamit_child_filter_available_seasons_data( $seasons_subs_only, $episodes_subs_only, array( 'require_video' => false ) );
assert_eq( 1, count( $avail_subs ), 'Season with subtitles is available for tabs/download catalog' );

// Video playback check (play button)
$avail_video = streamit_child_filter_available_seasons_data( $seasons_subs_only, $episodes_subs_only, array( 'require_video' => true ) );
assert_eq( 0, count( $avail_video ), 'Season with subtitles only is NOT available for video playback' );

$mock_tvshow_subs = new class( $seasons_subs_only, $episodes_subs_only ) {
	private $s;
	public function __construct( $s ) { $this->s = $s; }
	public function get_id() { return 5005; }
	public function get_meta( $k ) { return '_seasons' === $k ? $this->s : null; }
};
$GLOBALS['test_episode_map'] = $episodes_subs_only;
streamit_child_reset_episode_media_flags_cache();
$first_ep_subs = streamit_child_get_first_available_episode( $mock_tvshow_subs );
assert_true( null === $first_ep_subs, 'Play button returns null when series has only subtitles and no video' );

echo "\n";

// -----------------------------------------------------------------------------
// Test 5: A series has no available media at all:
// - Page does not produce a broken play link or misleading season count.
// -----------------------------------------------------------------------------
echo "5. Series With Zero Available Media\n";

$seasons_empty = array(
	array(
		'name'          => 'فصل ۱',
		'season_number' => '1',
		'episodes'      => array( 701 ),
	),
	array(
		'name'          => 'فصل ۲',
		'season_number' => '2',
		'episodes'      => array( 702 ),
	),
);

$episodes_empty = array(
	701 => array(
		'title'          => 'Episode 1',
		'season_number'  => '1',
		'episode_number' => 'E01',
		'sources'        => array(),
		'subtitles'      => array(),
	),
	702 => array(
		'title'          => 'Episode 2',
		'season_number'  => '2',
		'episode_number' => 'E01',
		'sources'        => array(),
		'subtitles'      => array(),
	),
);

$avail_empty = streamit_child_filter_available_seasons_data( $seasons_empty, $episodes_empty );
assert_eq( 0, count( $avail_empty ), 'Zero available seasons when all episodes lack media' );

// Banner season count behavior
$banner_count_zero = count( $avail_empty );
$banner_censor_rating = $banner_count_zero
	? $banner_count_zero . _n( " Season", " Seasons", $banner_count_zero, 'streamit' )
	: 'Arriving Soon';
assert_eq( 'Arriving Soon', $banner_censor_rating, 'Banner displays "Arriving Soon" when no seasons have media' );

// Play button behavior
$mock_tvshow_empty = new class( $seasons_empty, $episodes_empty ) {
	private $s;
	public function __construct( $s ) { $this->s = $s; }
	public function get_id() { return 5006; }
	public function get_meta( $k ) { return '_seasons' === $k ? $this->s : null; }
};
$GLOBALS['test_episode_map'] = $episodes_empty;
streamit_child_reset_episode_media_flags_cache();
$first_ep_none = streamit_child_get_first_available_episode( $mock_tvshow_empty );
assert_true( null === $first_ep_none, 'Play button returns null (no broken play link)' );

echo "\n";

// -----------------------------------------------------------------------------
// Test 6: Existing download-box behavior remains intact
// -----------------------------------------------------------------------------
echo "6. Download Box Consistency\n";

$catalog = streamit_child_build_series_download_catalog_from_data( $seasons_three, $episodes_s3_only, true );
assert_eq( 1, count( $catalog['seasons'] ), 'Download catalog has exactly 1 season (Season 3)' );
assert_eq( '3', $catalog['seasons'][0]['season_number'], 'Download catalog retains season_number "3"' );
assert_eq( 2, $catalog['seasons'][0]['downloadable_episode_count'], 'Download catalog reports 2 downloadable episodes' );
assert_true( $catalog['can_download'], 'Download catalog preserves can_download flag' );

echo "\n";

// -----------------------------------------------------------------------------
// Final Results
// -----------------------------------------------------------------------------
if ( 0 === $failures ) {
	echo "All series frontend media availability tests PASSED!\n";
	exit( 0 );
} else {
	echo "FAIL: {$failures} test(s) failed.\n";
	exit( 1 );
}

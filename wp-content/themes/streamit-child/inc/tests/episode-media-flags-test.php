<?php
/**
 * CLI tests for the episode media-flag loader.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/episode-media-flags-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-episode-media-flags-test/' );
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

$GLOBALS['emf_queries'] = 0;
$GLOBALS['emf_fail']    = false;
$GLOBALS['emf_rows']    = array();

/**
 * In-memory episodemeta for media-flag queries.
 */
class Streamit_Child_Media_Flags_Test_Wpdb {
	public $prefix = 'wp_';
	public $streamit_episodemeta = 'wp_streamit_episodemeta';
	public $last_error = '';
	public $last_query = '';

	public function prepare( $query, ...$args ) {
		if ( ! str_contains( $query, 'wp_streamit_episodemeta' ) ) {
			$this->last_error = 'bad table';
			return '';
		}
		$offset = 0;
		$sql    = preg_replace_callback(
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
		$this->last_query = $sql;
		return $sql;
	}

	public function get_results( $sql ) {
		$GLOBALS['emf_queries']++;
		$this->last_query = (string) $sql;
		if ( ! empty( $GLOBALS['emf_fail'] ) ) {
			$this->last_error = 'db down';
			return null;
		}
		$this->last_error = '';

		$ids = array();
		if ( preg_match( '/streamit_episode_id IN \(([^)]+)\)/', (string) $sql, $matches ) ) {
			$ids = array_map( 'intval', array_map( 'trim', explode( ',', $matches[1] ) ) );
		}

		$rows = array();
		foreach ( $GLOBALS['emf_rows'] as $row ) {
			if ( in_array( (int) $row->streamit_episode_id, $ids, true ) ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}

$GLOBALS['wpdb'] = new Streamit_Child_Media_Flags_Test_Wpdb();

require_once dirname( __DIR__ ) . '/sources-download.php';
require_once dirname( __DIR__ ) . '/subtitles.php';
require_once dirname( __DIR__ ) . '/series-download.php';

$failures = 0;

function emf_assert( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
}

function emf_row( int $id, int $meta_id, string $key, $value ): object {
	return (object) array(
		'streamit_episode_id' => $id,
		'meta_id'             => $meta_id,
		'meta_key'            => $key,
		'meta_value'          => is_string( $value ) ? $value : serialize( $value ),
	);
}

function emf_reset( array $rows = array(), bool $fail = false ): void {
	$GLOBALS['emf_queries'] = 0;
	$GLOBALS['emf_fail']    = $fail;
	$GLOBALS['emf_rows']    = $rows;
	streamit_child_reset_episode_media_flags_cache();
}

echo "=== Episode media flags ===\n\n";

echo "1. Video, subtitles, empty, missing\n";
emf_reset(
	array(
		emf_row( 1, 1, '_sources', array( array( 'quality' => '1080p', 'download_content' => 'series/A.mkv' ) ) ),
		emf_row( 2, 2, '_subtitles', array( array( 'url' => 'series/A.fa.srt', 'srclang' => 'fa' ) ) ),
		emf_row( 3, 3, '_sources', array() ),
		emf_row( 3, 4, '_subtitles', array() ),
	)
);
$flags = streamit_child_episode_media_flags( array( 1, 2, 3, 4 ) );
emf_assert( true === $flags[1]['video'] && true === $flags[1]['media'], 'video source sets video and media' );
emf_assert( false === $flags[2]['video'] && true === $flags[2]['media'], 'subtitle-only sets media and not video' );
emf_assert( false === $flags[3]['video'] && false === $flags[3]['media'], 'empty source and subtitle rows are not media' );
emf_assert( false === $flags[4]['video'] && false === $flags[4]['media'], 'missing meta rows are not media' );
emf_assert( array( 1, 2, 3, 4 ) === array_keys( $flags ), 'every requested id is present' );

echo "\n2. Parsing semantics\n";
emf_reset(
	array(
		emf_row( 10, 1, '_sources', 'a:1:{broken' ),
		emf_row( 11, 2, '_sources', array() ),
		emf_row( 11, 3, '_source', array( array( 'quality' => '720p', 'link' => 'series/B.mp4' ) ) ),
		emf_row( 12, 4, 'episode_url_link', 'series/C.mkv' ),
		emf_row( 13, 5, '_sources', array( array( 'quality' => '', 'download_content' => 'series/D.mkv' ) ) ),
		emf_row( 13, 6, 'episode_url_link', 'series/D-fallback.mkv' ),
		emf_row( 14, 7, '_sources', array( array( 'quality' => '480p', 'download_content' => 'series/keep.mkv' ) ) ),
		emf_row( 14, 8, '_sources', array() ),
	)
);
$parsed = streamit_child_episode_media_flags( array( 10, 11, 12, 13, 14 ) );
emf_assert( false === $parsed[10]['video'] && false === $parsed[10]['media'], 'malformed serialized _sources is not media' );
emf_assert( true === $parsed[11]['video'], 'empty _sources falls through to _source link' );
emf_assert( true === $parsed[12]['video'], 'episode_url_link becomes a video source when source meta is empty' );
emf_assert( false === $parsed[13]['video'], 'non-empty invalid _sources does not fall through to episode_url_link' );
emf_assert( true === $parsed[14]['video'], 'lowest meta_id _sources row wins' );

echo "\n3. Normalize, memoize, chunks, query errors\n";
emf_reset( array( emf_row( 4, 1, '_sources', array( array( 'quality' => '1080p', 'download_content' => 'series/E.mkv' ) ) ) ) );
$once = streamit_child_episode_media_flags( array( -4, '4', 4, 0, 'no', true ) );
emf_assert( array( 4 ) === array_keys( $once ) && true === $once[4]['video'], 'ids are absolutized, deduped, and non-numeric values dropped' );
emf_assert( 1 === $GLOBALS['emf_queries'], 'one query for one normalized id' );
$again = streamit_child_episode_media_flags( array( 4 ) );
emf_assert( $again === $once && 1 === $GLOBALS['emf_queries'], 'repeated normalized input does not query again' );

$chunk   = streamit_child_episode_media_flags_chunk_size();
$many    = range( 1, $chunk + 1 );
emf_reset( array( emf_row( $chunk + 1, 1, '_subtitles', array( array( 'url' => 'series/last.srt' ) ) ) ) );
$chunked = streamit_child_episode_media_flags( $many );
emf_assert( 2 === $GLOBALS['emf_queries'], 'chunk size plus one id issues two queries' );
emf_assert( true === $chunked[ $chunk + 1 ]['media'] && false === $chunked[1]['video'], 'the id past the chunk boundary is still read' );
$GLOBALS['emf_queries'] = 0;
streamit_child_episode_media_flags( array_reverse( $many ) );
emf_assert( 0 === $GLOBALS['emf_queries'], 'id order does not change the memo key' );

emf_reset( array(), true );
$failed = streamit_child_episode_media_flags( array( 9 ) );
emf_assert( false === $failed[9]['video'] && false === $failed[9]['media'], 'query error yields no media' );
emf_assert( 1 === $GLOBALS['emf_queries'], 'query error still attempts the read' );
emf_reset( array( emf_row( 9, 1, '_sources', array( array( 'quality' => '1080p', 'download_content' => 'series/recovered.mkv' ) ) ) ) );
$recovered = streamit_child_episode_media_flags( array( 9 ) );
emf_assert( true === $recovered[9]['video'] && 1 === $GLOBALS['emf_queries'], 'a failed read is not memoized' );

emf_reset();
emf_assert( array() === streamit_child_episode_media_flags( array( 0, 'x', false ) ) && 0 === $GLOBALS['emf_queries'], 'no valid ids skips the query' );
emf_reset();
$collapsed = streamit_child_episode_media_flags( array( -8, 8 ) );
emf_assert( array( 8 ) === array_keys( $collapsed ) && 1 === $GLOBALS['emf_queries'], 'negative ids collapse to the absolute id' );

echo "\n4. Available seasons uses flags, not the episode list query\n";
$GLOBALS['emf_episode_queries'] = 0;
if ( ! function_exists( 'streamit_get_episodes' ) ) {
	function streamit_get_episodes( $args ) {
		unset( $args );
		$GLOBALS['emf_episode_queries']++;
		return (object) array( 'results' => array() );
	}
}
emf_reset(
	array(
		emf_row( 21, 1, '_sources', array( array( 'quality' => '1080p', 'download_content' => 'series/S01E01.mkv' ) ) ),
		emf_row( 22, 2, '_subtitles', array( array( 'url' => 'series/S01E02.srt' ) ) ),
	)
);
$show = new class() {
	public function get_id() {
		return 9001;
	}
	public function get_meta( $key ) {
		if ( '_seasons' !== $key ) {
			return null;
		}
		return array(
			array(
				'name'          => 'Season 1',
				'season_number' => '1',
				'episodes'      => array( 21, 22, 23 ),
			),
		);
	}
};
$all = streamit_child_get_available_seasons( $show );
$video_only = streamit_child_get_available_seasons( $show, array( 'require_video' => true ) );
emf_assert( array( 21, 22 ) === $all[0]['episodes'], 'tabs keep video and subtitle episodes' );
emf_assert( array( 21 ) === $video_only[0]['episodes'], 'require_video drops the subtitle-only episode' );
emf_assert( 0 === $GLOBALS['emf_episode_queries'], 'availability does not call streamit_get_episodes' );

echo "\n";
if ( 0 === $failures ) {
	echo "All episode media flag tests PASSED!\n";
	exit( 0 );
}

echo "{$failures} episode media flag test(s) FAILED.\n";
exit( 1 );

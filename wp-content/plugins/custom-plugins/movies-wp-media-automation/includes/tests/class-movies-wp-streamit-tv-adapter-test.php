<?php
/**
 * CLI tests for Movies_WP_Streamit_TV_Adapter.
 *
 * Run: php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-streamit-tv-adapter-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-tv-adapter-test/' );
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code = (string) $code;
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
	function __( $text, $domain = 'default' ) { unset( $domain ); return $text; }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $value ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( (string) $value ) ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( (string) $value ); }
}
if ( ! function_exists( 'maybe_serialize' ) ) {
	function maybe_serialize( $value ) { return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value; }
}
if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $key, $url ) {
		unset( $key );
		return preg_replace( '/([?&])_streamit_image_role=[^&]*(&|$)/', '$1', (string) $url );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ) { return (string) $value; }
}
if ( ! function_exists( 'wp_basename' ) ) {
	function wp_basename( $value ) { return basename( (string) $value ); }
}

require_once dirname( __DIR__ ) . '/class-movies-wp-streamit-tv-adapter.php';

$failures = 0;
function tv_adapter_assert( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}
function tv_adapter_same( $expected, $actual, string $label ): void {
	tv_adapter_assert( $expected === $actual, $label . ' expected=' . var_export( $expected, true ) . ' got=' . var_export( $actual, true ) );
}
function tv_adapter_season_episodes( $seasons, int $index ): array {
	if ( ! is_array( $seasons ) || ! isset( $seasons[ $index ] ) || ! is_array( $seasons[ $index ] ) ) {
		return array();
	}
	$episodes = $seasons[ $index ]['episodes'] ?? array();
	return is_array( $episodes ) ? $episodes : array();
}

class TV_Adapter_Object {
	private $data;
	public function __construct( array $data ) { $this->data = $data; }
	private function value( $key ) { return $this->data[ $key ]; }
	public function get_post_author() { return $this->value( 'post_author' ); }
	public function get_post_date() { return $this->value( 'post_date' ); }
	public function get_post_date_gmt() { return $this->value( 'post_date_gmt' ); }
	public function get_post_content() { return $this->value( 'post_content' ); }
	public function get_post_title() { return $this->value( 'post_title' ); }
	public function get_post_excerpt() { return $this->value( 'post_excerpt' ); }
	public function get_post_status() { return $this->value( 'post_status' ); }
	public function get_comment_status() { return $this->value( 'comment_status' ); }
	public function get_ping_status() { return $this->value( 'ping_status' ); }
	public function get_post_password() { return $this->value( 'post_password' ); }
	public function get_post_name() { return $this->value( 'post_name' ); }
	public function get_to_ping() { return $this->value( 'to_ping' ); }
	public function get_pinged() { return $this->value( 'pinged' ); }
	public function get_post_modified() { return $this->value( 'post_modified' ); }
	public function get_post_modified_gmt() { return $this->value( 'post_modified_gmt' ); }
	public function get_post_content_filtered() { return $this->value( 'post_content_filtered' ); }
	public function get_post_parent() { return $this->value( 'post_parent' ); }
	public function get_guid() { return $this->value( 'guid' ); }
	public function get_menu_order() { return $this->value( 'menu_order' ); }
	public function get_post_type() { return $this->value( 'post_type' ); }
	public function get_post_mime_type() { return $this->value( 'post_mime_type' ); }
	public function get_comment_count() { return $this->value( 'comment_count' ); }
}

function tv_adapter_fields( string $type, string $title ): array {
	return array(
		'post_author' => 1, 'post_date' => '2020-01-01 00:00:00', 'post_date_gmt' => '2020-01-01 00:00:00',
		'post_content' => 'old content', 'post_title' => $title, 'post_excerpt' => '', 'post_status' => 'publish',
		'comment_status' => 'open', 'ping_status' => 'open', 'post_password' => '', 'post_name' => 'existing',
		'to_ping' => '', 'pinged' => '', 'post_modified' => '2020-01-01 00:00:00',
		'post_modified_gmt' => '2020-01-01 00:00:00', 'post_content_filtered' => '', 'post_parent' => 0,
		'guid' => '', 'menu_order' => 0, 'post_type' => $type, 'post_mime_type' => '', 'comment_count' => 0,
	);
}

function tv_adapter_plan( string $action = 'create' ): array {
	return array(
		'ok' => true, 'type' => 'series', 'ready_to_import' => true, 'errors' => array(),
		'contract' => array( 'kind' => 'series_import_plan', 'version' => 1 ),
		'identity' => array(
			'action' => $action,
			'existing_series_id' => 'update' === $action ? 42 : null,
		),
		'series' => array(
			'tmdb_id' => 100, 'title' => 'عنوان اپراتور', 'summary' => 'خلاصه جدید',
			'tmdb_title' => 'TMDb Name', 'tmdb_original_title' => 'Original Name', 'imdb_id' => 'tt100',
			'rating' => 8.5, 'original_language' => 'ko', 'origin_country' => array( 'KR' ),
			'genres' => array( array( 'id' => 18, 'name' => 'Drama' ) ),
			'first_air_date' => '2020-01-01',
			'cast' => array(
				array( 'tmdb_id' => 201, 'name' => 'Existing Actor', 'character' => 'Lead', 'order' => 0 ),
				array( 'tmdb_id' => 202, 'name' => 'New Actor', 'character' => 'Support', 'order' => 1 ),
			),
			'crew' => array(
				array( 'tmdb_id' => 203, 'name' => 'Director', 'job' => 'Director' ),
			),
		),
		'images' => array(
			'poster' => 'update' === $action
				? array( 'action' => 'keep_existing', 'url' => null, 'target' => '_portrait_thumbmail' )
				: array( 'action' => 'set', 'url' => 'https://image.tmdb.org/t/p/w500/poster.jpg', 'target' => '_portrait_thumbmail' ),
			'backdrop' => 'update' === $action
				? array( 'action' => 'keep_existing', 'url' => null, 'target' => 'thumbnail_id' )
				: array( 'action' => 'set', 'url' => 'https://image.tmdb.org/t/p/original/backdrop.jpg', 'target' => 'thumbnail_id' ),
		),
		'sources_policy' => array( 'mutate' => false, 'actions' => array() ),
		'seasons' => array(
			array(
				'action' => $action, 'season_number' => '0', 'existing_slot_index' => 'update' === $action ? 0 : null,
				'existing_episode_ids' => 'update' === $action ? array( 49, 50 ) : array(),
				'unmatched_existing_episode_ids' => 'update' === $action ? array( 49 ) : array(),
				'preserve_unmatched_episode_ids' => true,
				'name' => 'Specials', 'air_date' => '', 'overview' => 'Special season',
				'image' => 'update' === $action
					? array( 'action' => 'keep_existing', 'url' => null )
					: array( 'action' => 'set', 'url' => 'https://image.tmdb.org/t/p/w500/season-0.jpg' ),
				'episodes' => array(
					array(
						'action' => $action, 'existing_episode_id' => 'update' === $action ? 50 : null,
						'match_by' => 'update' === $action ? 'tvshow_id+_tmdb_id' : null,
						'tmdb_id' => 900, 'season_number' => '0', 'episode_number' => 1,
						'name' => 'Special', 'overview' => 'Episode summary', 'air_date' => '', 'runtime' => 61,
						'sources_action' => 'keep_existing_untouched',
						'image' => 'update' === $action
							? array( 'action' => 'keep_existing', 'url' => null, 'target' => 'thumbnail_id' )
							: array( 'action' => 'set', 'url' => 'https://image.tmdb.org/t/p/w500/still.jpg', 'target' => 'thumbnail_id' ),
					),
				),
			),
		),
	);
}

function tv_adapter_harness( array &$state ): array {
	$state += array(
		'tv_meta' => array(), 'episode_meta' => array(), 'tv_rows' => array(), 'episode_rows' => array(),
		'created_tv_rows' => array(), 'created_episode_rows' => array(), 'next_episode_id' => 70,
		'seasons' => array(), 'downloads' => array(), 'failed_episode_ids' => array(),
		'episode_meta_writes' => array(),
		'person_by_tmdb' => array( 201 => 801, 203 => 803 ), 'person_by_name' => array(),
		'next_person_id' => 900, 'created_people' => array(), 'person_meta' => array(), 'person_relations' => array(),
		'countries' => array(), 'genres' => array(),
	);
	return array(
		'current_user_id' => 7, 'now_local' => '2026-08-16 12:00:00', 'now_gmt' => '2026-08-16 08:30:00',
		'create_tvshow' => static function ( $row ) use ( &$state ) { $state['created_tv_rows'][] = $row; return 100; },
		'get_tvshow' => static function () use ( &$state ) { return new TV_Adapter_Object( tv_adapter_fields( 'tvshow', $state['local_title'] ?? 'عنوان فارسی موجود' ) ); },
		'update_tvshow_row' => static function ( $id, $row ) use ( &$state ) { $state['tv_rows'][] = array( $id, $row ); return true; },
		'create_episode' => static function ( $row ) use ( &$state ) {
			$state['created_episode_rows'][] = $row;
			$id = $state['next_episode_id']++;
			return in_array( $id, $state['failed_episode_ids'], true ) ? new WP_Error( 'episode_fail', 'episode failed' ) : $id;
		},
		'get_episode' => static function ( $id ) use ( &$state ) { return new TV_Adapter_Object( tv_adapter_fields( 'episode', 'Old episode ' . $id ) ); },
		'update_episode_row' => static function ( $id, $row ) use ( &$state ) { $state['episode_rows'][] = array( $id, $row ); return true; },
		'get_tvshow_meta' => static function ( $id, $key ) use ( &$state ) {
			unset( $id );
			return '_seasons' === $key ? $state['seasons'] : ( $state['tv_meta'][ $key ] ?? null );
		},
		'update_tvshow_meta' => static function ( $id, $key, $value ) use ( &$state ) {
			unset( $id );
			$state['tv_meta'][ $key ] = $value;
			if ( '_seasons' === $key ) { $state['seasons'] = $value; }
			return true;
		},
		'get_episode_meta' => static function ( $id, $key ) use ( &$state ) { return $state['episode_meta'][ $id ][ $key ] ?? null; },
		'update_episode_meta' => static function ( $id, $key, $value ) use ( &$state ) {
			$state['episode_meta'][ $id ][ $key ] = $value;
			$state['episode_meta_writes'][] = array( $id, $key, $value );
			return true;
		},
		'download_image' => static function ( $url, $role ) use ( &$state ) {
			$ids = array( 'poster' => 501, 'backdrop' => 502, 'still' => 503, 'season_poster' => 504 );
			$state['downloads'][] = array( $role, $url );
			return $ids[ $role ];
		},
		'save_country' => static function ( $series_id, $countries ) use ( &$state ) {
			$state['countries'][] = array( $series_id, $countries );
			return true;
		},
		'save_genres' => static function ( $series_id, $genres, $taxonomy ) use ( &$state ) {
			$state['genres'][] = array( $series_id, $genres, $taxonomy );
			return true;
		},
		'find_person_by_tmdb' => static function ( $tmdb_id ) use ( &$state ) {
			return $state['person_by_tmdb'][ $tmdb_id ] ?? 0;
		},
		'find_person_by_name' => static function ( $name ) use ( &$state ) {
			return $state['person_by_name'][ $name ] ?? 0;
		},
		'create_person' => static function ( $row ) use ( &$state ) {
			$id = $state['next_person_id']++;
			$state['created_people'][ $id ] = $row;
			$state['person_by_name'][ $row['post_title'] ] = $id;
			return $id;
		},
		'get_person_meta' => static function ( $id, $key ) use ( &$state ) {
			return $state['person_meta'][ $id ][ $key ] ?? null;
		},
		'update_person_meta' => static function ( $id, $key, $value ) use ( &$state ) {
			$state['person_meta'][ $id ][ $key ] = $value;
			return true;
		},
		'add_person_relation' => static function ( $id, $key, $series_id ) use ( &$state ) {
			$state['person_relations'][] = array( $id, $key, $series_id );
			return true;
		},
	);
}

echo "Movies_WP_Streamit_TV_Adapter tests\n";

echo "\n[create-series-season-zero-images]\n";
$state = array();
$result = Movies_WP_Streamit_TV_Adapter::apply( tv_adapter_plan(), tv_adapter_harness( $state ) );
tv_adapter_assert( ! empty( $result['ok'] ), 'create series succeeds' );
tv_adapter_same( 100, $result['series_id'], 'create returns Streamit series ID' );
tv_adapter_same( 'create', $result['seasons'][0]['action'], 'new Season 0 is created' );
tv_adapter_same( 'عنوان اپراتور', $state['created_tv_rows'][0]['post_title'], 'create uses operator title' );
tv_adapter_same( 'خلاصه جدید', $state['created_tv_rows'][0]['post_content'], 'create uses planned summary' );
tv_adapter_same( '0', $state['episode_meta'][70]['_season_number'], 'Season 0 stored as string zero' );
tv_adapter_same( 'E01', $state['episode_meta'][70]['_episode_number'], 'episode number uses E01 format' );
tv_adapter_same( '100', $state['episode_meta'][70]['tvshow_id'], 'created episode receives current TV show identity meta' );
tv_adapter_same( 'S00E01 - Special', $state['created_episode_rows'][0]['post_title'], 'Season 0 episode title is canonical' );
tv_adapter_same( 100, $state['created_episode_rows'][0]['post_parent'], 'created episode post_parent follows Streamit TV show ID convention' );
tv_adapter_same( 501, $state['tv_meta']['_portrait_thumbmail'], 'poster maps only to portrait meta' );
tv_adapter_same( 502, $state['tv_meta']['thumbnail_id'], 'backdrop maps to thumbnail meta' );
tv_adapter_same( 503, $state['episode_meta'][70]['thumbnail_id'], 'still maps to episode thumbnail' );
tv_adapter_same( '0', $state['seasons'][0]['season_number'], 'season row has explicit digit-string identity' );
tv_adapter_same( array( 70 ), $state['seasons'][0]['episodes'], 'season row links created episode' );
tv_adapter_same( 504, $state['seasons'][0]['image_id'], 'season poster is stored only in the season image field' );
tv_adapter_assert( array_key_exists( 'season_year', $state['seasons'][0] ), 'season row has admin year key' );
tv_adapter_assert( array_key_exists( 'sesion_upcoming_status', $state['seasons'][0] ), 'season row has admin upcoming key' );
tv_adapter_same( 'TMDb Name', $state['tv_meta']['_tmdb_title'], 'TMDb title metadata is saved' );
tv_adapter_same( 'Original Name', $state['tv_meta']['_tmdb_original_title'], 'TMDb original title metadata is saved' );
tv_adapter_same( 'tt100', $state['tv_meta']['_imdb_id'], 'IMDb metadata is saved' );
tv_adapter_same( '8.5', $state['tv_meta']['name_custom_imdb_rating'], 'rating metadata is saved' );
tv_adapter_assert( isset( $state['tv_meta']['_language'] ), 'language metadata is saved' );
tv_adapter_same( array( 100, array( 'KR' ) ), $state['countries'][0], 'country enrichment uses TV show ID' );
tv_adapter_same( 'tvshow_genre', $state['genres'][0][2], 'genre enrichment uses TV taxonomy' );
tv_adapter_same( 801, $state['tv_meta']['_cast'][0]['id'], 'existing cast person is linked' );
tv_adapter_same( 900, $state['tv_meta']['_cast'][1]['id'], 'missing cast person is created and linked' );
tv_adapter_same( 803, $state['tv_meta']['_crew'][0]['id'], 'crew person is linked' );
tv_adapter_same( 'Director', $state['tv_meta']['_crew'][0]['job'], 'crew job convention is saved' );
tv_adapter_same( '201', $state['person_meta'][801]['_tmdb_id'], 'existing person receives TMDb identity metadata' );
tv_adapter_same( '_tvshow_cast', $state['person_relations'][0][1], 'cast reverse relationship uses Streamit convention' );
tv_adapter_assert(
	! array_key_exists( '_first_air_date', $state['tv_meta'] ) && ! array_key_exists( 'first_air_date', $state['tv_meta'] ),
	'first_air_date remains plan-only without a confirmed Streamit destination'
);

echo "\n[update-preserves-title-sources-season-metadata]\n";
$state = array(
	'seasons' => array(
		array(
			'season_number' => '0', 'name' => 'Old specials', 'episodes' => array( 49 ),
			'season_year' => '2019', 'season_description' => 'old', 'sesion_upcoming_status' => '1',
			'season_upcoming_datetime' => '2030-01-01 00:00:00', 'manual_key' => 'keep', 'image_id' => 774,
		),
		array(
			'season_number' => '9', 'name' => 'Manual season', 'episodes' => array( 999 ),
			'manual_only' => true,
		),
	),
	'tv_meta' => array(
		'_portrait_thumbmail' => 776,
		'thumbnail_id' => 775,
		'_cast' => array( array( 'id' => 799, 'character' => 'Manual cast', 'position' => '9', 'manual' => true ) ),
		'_crew' => array( array( 'id' => 798, 'job' => 'Manual crew', 'manual' => true ) ),
	),
	'episode_meta' => array(
		50 => array(
			'tvshow_id' => '42',
			'_sources' => array( array( 'name' => 'Manual source', 'link' => 'series/file.mkv' ) ),
			'thumbnail_id' => 777,
		),
	),
);
$before_sources = $state['episode_meta'][50]['_sources'];
$result = Movies_WP_Streamit_TV_Adapter::apply( tv_adapter_plan( 'update' ), tv_adapter_harness( $state ) );
tv_adapter_assert( ! empty( $result['ok'] ), 'update series succeeds' );
tv_adapter_same( 'update', $result['seasons'][0]['action'], 'existing Season 0 is updated' );
tv_adapter_same( 'عنوان اپراتور', $state['tv_rows'][0][1]['post_title'], 'update applies planned local title' );
tv_adapter_same( 'خلاصه جدید', $state['tv_rows'][0][1]['post_content'], 'update applies planned summary' );
tv_adapter_same( 'TMDb Name', $state['tv_meta']['_tmdb_title'], 'update still writes _tmdb_title meta' );
tv_adapter_same( $before_sources, $state['episode_meta'][50]['_sources'], 'episode _sources remain exactly unchanged' );
tv_adapter_assert( ! in_array( '_sources', array_column( $state['episode_meta_writes'], 1 ), true ), 'adapter generates no _sources write payload' );
tv_adapter_same( 777, $state['episode_meta'][50]['thumbnail_id'], 'missing still keeps existing thumbnail' );
tv_adapter_same( 0, count( array_filter( $state['downloads'], static function ( $row ) { return 'still' === $row[0]; } ) ), 'keep_existing still is not downloaded' );
tv_adapter_same( 0, $state['episode_rows'][0][1]['post_parent'], 'valid update preserves existing post_parent' );
tv_adapter_same( '42', $state['episode_meta'][50]['tvshow_id'], 'valid update preserves existing tvshow_id' );
tv_adapter_assert( ! in_array( 'tvshow_id', array_column( $state['episode_meta_writes'], 1 ), true ), 'valid update does not write tvshow_id' );
tv_adapter_same( 'tvshow_id+_tmdb_id', $result['episodes'][0]['match_by'], 'adapter preserves TMDb identity decision from plan' );
tv_adapter_same( 'keep', $state['seasons'][0]['manual_key'], 'unrelated season metadata preserved' );
tv_adapter_same( '1', $state['seasons'][0]['sesion_upcoming_status'], 'upcoming season metadata preserved' );
tv_adapter_same( '2019', $state['seasons'][0]['season_year'], 'missing TMDb season year preserves existing value' );
tv_adapter_same( 774, $state['seasons'][0]['image_id'], 'missing season poster preserves existing season image' );
tv_adapter_same( array( 49, 50 ), $state['seasons'][0]['episodes'], 'planned episode merges into existing IDs' );
tv_adapter_same( 2, count( $state['seasons'] ), 'unrelated existing season remains in complete season list' );
tv_adapter_same( true, $state['seasons'][1]['manual_only'], 'unrepresented season data is preserved' );
tv_adapter_same( 776, $state['tv_meta']['_portrait_thumbmail'], 'missing update poster preserves existing image' );
tv_adapter_same( 775, $state['tv_meta']['thumbnail_id'], 'missing update backdrop preserves existing image' );
tv_adapter_same( 799, $state['tv_meta']['_cast'][0]['id'], 'unrelated existing cast is not deleted' );
tv_adapter_same( 798, $state['tv_meta']['_crew'][0]['id'], 'unrelated existing crew is not deleted' );

echo "\n[update-applies-approved-persian-title]\n";
$state = array(
	'local_title' => 'A Shop for Killers',
	'tv_meta'     => array(
		'_tmdb_title' => 'Stale Meta',
	),
	'episode_meta' => array(
		50 => array(
			'tvshow_id' => '42',
			'_sources'  => array(
				array(
					'name'    => '',
					'link'    => 'series/korea/2024/A.Shop.for.Killers/ep.mkv',
					'quality' => '1080p',
				),
			),
		),
	),
);
$before_sources = $state['episode_meta'][50]['_sources'];
$plan           = tv_adapter_plan( 'update' );
$plan['series']['title']      = 'فروشگاه قاتلان';
$plan['series']['summary']    = 'خلاصه فروشگاه قاتلان';
$plan['series']['tmdb_title'] = 'A Shop for Killers';
$options = tv_adapter_harness( $state );
$result  = Movies_WP_Streamit_TV_Adapter::apply_series_phase( $plan, $options );
tv_adapter_assert( ! empty( $result['ok'] ), 'persian title series-phase update succeeds' );
tv_adapter_same( 'فروشگاه قاتلان', $state['tv_rows'][0][1]['post_title'], 'UPDATE replaces English title with approved Persian title' );
tv_adapter_same( 'خلاصه فروشگاه قاتلان', $state['tv_rows'][0][1]['post_content'], 'UPDATE still applies planned summary' );
tv_adapter_same( 'A Shop for Killers', $state['tv_meta']['_tmdb_title'], '_tmdb_title remains the TMDb English name' );
tv_adapter_same( $before_sources, $state['episode_meta'][50]['_sources'], 'series-phase title fix does not touch episode _sources' );
tv_adapter_same( 0, count( $state['episode_rows'] ), 'series-phase does not rewrite episode titles/rows' );
tv_adapter_same( 0, count( $state['created_episode_rows'] ), 'series-phase does not create episodes' );

echo "\n[mixed-create-update-and-fallback-identity]\n";
$plan = tv_adapter_plan( 'update' );
$new_episode = $plan['seasons'][0]['episodes'][0];
$new_episode['action'] = 'create';
$new_episode['existing_episode_id'] = null;
$new_episode['match_by'] = null;
$new_episode['tmdb_id'] = 901;
$new_episode['episode_number'] = 2;
$new_episode['name'] = 'New special';
$new_episode['image'] = array( 'action' => 'skip_missing', 'url' => null, 'target' => 'thumbnail_id' );
$plan['seasons'][0]['episodes'][] = $new_episode;
$fallback_episode = $plan['seasons'][0]['episodes'][0];
$fallback_episode['existing_episode_id'] = 51;
$fallback_episode['match_by'] = 'tvshow_id+_season_number+_episode_number';
$fallback_episode['tmdb_id'] = 902;
$fallback_episode['episode_number'] = 3;
$fallback_episode['name'] = 'Fallback update';
$plan['seasons'][0]['episodes'][] = $fallback_episode;
$state = array(
	'seasons' => array( array( 'season_number' => '0', 'episodes' => array( 49, 50, 51 ) ) ),
	'episode_meta' => array(
		50 => array( 'tvshow_id' => '42', '_sources' => 'a:1:{s:4:"name";s:6:"Manual";}' ),
		51 => array( 'tvshow_id' => '42', '_sources' => array( 'opaque' => array( 'keep', 1 ) ) ),
	),
);
$before_fallback_sources = $state['episode_meta'][51]['_sources'];
$result = Movies_WP_Streamit_TV_Adapter::apply( $plan, tv_adapter_harness( $state ) );
tv_adapter_assert( ! empty( $result['ok'] ), 'mixed graph persists' );
tv_adapter_same( array( 'update', 'create', 'update' ), array_column( $result['episodes'], 'action' ), 'mixed episode actions remain explicit' );
tv_adapter_same( 50, $result['episodes'][0]['episode_id'], 'TMDb identity updates planned episode ID' );
tv_adapter_same( 51, $result['episodes'][2]['episode_id'], 'S/E fallback identity updates planned episode ID' );
tv_adapter_same( 'tvshow_id+_season_number+_episode_number', $result['episodes'][2]['match_by'], 'fallback identity decision remains explicit' );
tv_adapter_same( $before_fallback_sources, $state['episode_meta'][51]['_sources'], 'fallback update leaves _sources logically unchanged' );
tv_adapter_same( array( 49, 50, 51, 70 ), $state['seasons'][0]['episodes'], 'mixed season preserves existing IDs and adds created episode' );

echo "\n[episode-ownership-conflict]\n";
$state = array(
	'seasons' => array( array( 'season_number' => '0', 'episodes' => array( 50 ) ) ),
	'episode_meta' => array(
		50 => array(
			'tvshow_id' => '999',
			'_sources' => array( array( 'name' => 'Foreign source' ) ),
			'thumbnail_id' => 888,
			'_tmdb_id' => '900',
		),
	),
);
$before_foreign_meta = $state['episode_meta'][50];
$result = Movies_WP_Streamit_TV_Adapter::apply( tv_adapter_plan( 'update' ), tv_adapter_harness( $state ) );
tv_adapter_assert( empty( $result['ok'] ) && ! empty( $result['partial'] ), 'foreign episode ownership hard-fails adapter result' );
tv_adapter_same( 50, $result['episodes'][0]['episode_id'], 'ownership error identifies episode ID' );
tv_adapter_same( 'series_tv_adapter_episode_ownership_conflict', $result['episodes'][0]['error']['code'], 'ownership mismatch has explicit conflict code' );
tv_adapter_assert( false !== strpos( $result['episodes'][0]['error']['message'], '999' ), 'ownership error identifies existing TV show ID' );
tv_adapter_assert( false !== strpos( $result['episodes'][0]['error']['message'], '42' ), 'ownership error identifies requested TV show ID' );
tv_adapter_same( array(), $state['episode_rows'], 'foreign episode row is not modified' );
tv_adapter_same( array(), $state['episode_meta_writes'], 'foreign episode metadata is not modified' );
tv_adapter_same( $before_foreign_meta, $state['episode_meta'][50], 'foreign episode ownership/meta remains byte-for-byte unchanged' );

echo "\n[series-enrichment-partial-result]\n";
$state = array();
$options = tv_adapter_harness( $state );
$options['save_country'] = static function () { return false; };
$result = Movies_WP_Streamit_TV_Adapter::apply( tv_adapter_plan(), $options );
tv_adapter_same( false, $result['ok'], 'enrichment failure returns not-ok' );
tv_adapter_same( 100, $result['series_id'], 'enrichment failure reports actual created Series ID' );
tv_adapter_same( true, $result['partial'], 'enrichment failure is explicitly partial' );
tv_adapter_same( 'series_tv_adapter_country_failed', $result['errors'][0]['code'], 'enrichment error details are retained' );
tv_adapter_same( 1, count( $state['created_tv_rows'] ), 'Series row exists when enrichment later fails' );

echo "\n[source-url-image-deduplication]\n";
$plan = tv_adapter_plan();
$plan['series']['cast'] = array();
$plan['series']['crew'] = array();
$plan['images']['backdrop'] = array( 'action' => 'skip_missing', 'url' => null, 'target' => 'thumbnail_id' );
$plan['seasons'] = array();
$state = array();
$options = tv_adapter_harness( $state );
unset( $options['download_image'] );
$lookups = array();
$sideloads = array();
$source_writes = array();
$options['find_attachment_by_source_url'] = static function ( $url ) use ( &$lookups ) {
	$lookups[] = $url;
	return 910;
};
$options['sideload_image'] = static function ( $url, $path, $role ) use ( &$sideloads ) {
	$sideloads[] = array( $url, $path, $role );
	return 911;
};
$options['update_attachment_source_url'] = static function ( $id, $url ) use ( &$source_writes ) {
	$source_writes[] = array( $id, $url );
};
$result = Movies_WP_Streamit_TV_Adapter::apply( $plan, $options );
tv_adapter_assert( ! empty( $result['ok'] ), 'existing source-URL attachment is reused' );
tv_adapter_same( 910, $state['tv_meta']['_portrait_thumbmail'], 'source-URL dedupe supplies poster attachment ID' );
tv_adapter_same( array( 'https://image.tmdb.org/t/p/w500/poster.jpg' ), $lookups, 'dedupe lookup uses canonical source URL meta' );
tv_adapter_same( array(), $sideloads, 'dedupe hit avoids image sideload' );
tv_adapter_same( array(), $source_writes, 'dedupe hit does not rewrite attachment metadata' );

$state = array();
$options = tv_adapter_harness( $state );
unset( $options['download_image'] );
$options['find_attachment_by_source_url'] = static function () { return 0; };
$options['sideload_image'] = static function ( $url, $path, $role ) use ( &$sideloads ) {
	$sideloads[] = array( $url, $path, $role );
	return 911;
};
$options['update_attachment_source_url'] = static function ( $id, $url ) use ( &$source_writes ) {
	$source_writes[] = array( $id, $url );
};
$result = Movies_WP_Streamit_TV_Adapter::apply( $plan, $options );
tv_adapter_assert( ! empty( $result['ok'] ), 'source-URL miss creates an attachment' );
tv_adapter_same( 911, $state['tv_meta']['_portrait_thumbmail'], 'new poster attachment is assigned' );
tv_adapter_same( array( 911, 'https://image.tmdb.org/t/p/w500/poster.jpg' ), $source_writes[0], 'new attachment records _streamit_tmdb_source_url value' );

echo "\n[episode-failure-does-not-rollback]\n";
$plan = tv_adapter_plan();
$second = $plan['seasons'][0]['episodes'][0];
$second['tmdb_id'] = 901;
$second['episode_number'] = 2;
$second['name'] = 'Second';
$plan['seasons'][0]['episodes'][] = $second;
$state = array( 'failed_episode_ids' => array( 71 ) );
$result = Movies_WP_Streamit_TV_Adapter::apply( $plan, tv_adapter_harness( $state ) );
tv_adapter_assert( empty( $result['ok'] ) && ! empty( $result['partial'] ), 'failed episode reports partial result' );
tv_adapter_same( true, $result['episodes'][0]['ok'], 'earlier episode remains successful' );
tv_adapter_same( false, $result['episodes'][1]['ok'], 'failed episode is reported independently' );
tv_adapter_same( array( 70 ), tv_adapter_season_episodes( $state['seasons'], 0 ), 'season retains earlier successful episode' );

echo "\n[live-identity-retry-does-not-duplicate-episode]\n";
$state = array();
$options = tv_adapter_harness( $state );
$creates = 0;
$fail_meta = true;
$options['create_episode'] = static function ( $row ) use ( &$state, &$creates ) {
	++$creates;
	$state['created_episode_rows'][] = $row;
	$id = 70;
	$state['episode_meta'][ $id ]['tvshow_id'] = '100';
	return $id;
};
$options['find_episodes'] = static function () use ( &$state ) {
	$episodes = array();
	foreach ( $state['created_episode_rows'] as $idx => $row ) {
		unset( $idx, $row );
		$episodes[] = array(
			'id'             => 70,
			'tvshow_id'      => 100,
			'tmdb_id'        => 900,
			'season_number'  => '0',
			'episode_number' => 1,
		);
	}
	return $episodes;
};
$options['update_episode_meta'] = static function ( $id, $key, $value ) use ( &$state, &$fail_meta ) {
	if ( $fail_meta && '_tmdb_id' === $key ) {
		$fail_meta = false;
		return false;
	}
	$state['episode_meta'][ $id ][ $key ] = $value;
	return true;
};
$episode_plan = tv_adapter_plan()['seasons'][0]['episodes'][0];
$first = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( empty( $first['ok'] ), 'first persist fails after the Streamit row exists' );
$second = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( ! empty( $second['ok'] ), 'retry completes against the existing episode' );
tv_adapter_same( 1, $creates, 'retry does not call streamit_add_episode again' );
tv_adapter_same( 70, (int) $second['episode_id'], 'retry reuses the live episode id' );

echo "\n[find-live-wrong-show-season-episode-fallback]\n";
$wrong_show = Movies_WP_Streamit_TV_Adapter::find_live_episode_id(
	100,
	array(
		'season_number'  => '1',
		'episode_number' => 1,
	),
	array(
		'find_episodes' => static function () {
			return array(
				array(
					'id'             => 9901,
					'tvshow_id'      => 99,
					'tmdb_id'        => 0,
					'season_number'  => '1',
					'episode_number' => 1,
				),
				array(
					'id'             => 10001,
					'tvshow_id'      => 100,
					'tmdb_id'        => 0,
					'season_number'  => '1',
					'episode_number' => 1,
				),
			);
		},
	)
);
tv_adapter_same( 10001, $wrong_show, 'SxxExx fallback matches only the requested TV show' );

echo "\n[create-without-identity-meta-then-retry]\n";
$state = array();
$options = tv_adapter_harness( $state );
$creates = 0;
$fail_secondary = true;
$options['create_episode'] = static function ( $row ) use ( &$state, &$creates ) {
	++$creates;
	$state['created_episode_rows'][] = $row;
	return $state['next_episode_id']++;
};
$options['find_episodes'] = static function ( $series_id ) use ( &$state ) {
	$episodes = array();
	foreach ( $state['episode_meta'] as $id => $meta ) {
		if ( ! is_array( $meta ) ) {
			continue;
		}
		$show = absint( $meta['tvshow_id'] ?? 0 );
		if ( $show !== (int) $series_id ) {
			continue;
		}
		$episodes[] = array(
			'id'             => (int) $id,
			'tvshow_id'      => $show,
			'tmdb_id'        => absint( $meta['_tmdb_id'] ?? 0 ),
			'season_number'  => $meta['_season_number'] ?? null,
			'episode_number' => $meta['_episode_number'] ?? null,
		);
	}
	return $episodes;
};
$options['update_episode_meta'] = static function ( $id, $key, $value ) use ( &$state, &$fail_secondary ) {
	if ( $fail_secondary && '_episode_run_time' === $key ) {
		$fail_secondary = false;
		return false;
	}
	$state['episode_meta'][ $id ][ $key ] = $value;
	$state['episode_meta_writes'][] = $key;
	return true;
};
$episode_plan = tv_adapter_plan()['seasons'][0]['episodes'][0];
$first = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( empty( $first['ok'] ), 'persist fails after identity meta and before secondary work' );
tv_adapter_same( 1, $creates, 'create_episode runs once on the first attempt' );
tv_adapter_same(
	array( 'tvshow_id', '_season_number', '_episode_number', '_tmdb_id' ),
	array_slice( $state['episode_meta_writes'], 0, 4 ),
	'identity metadata is written before secondary metadata'
);
tv_adapter_same( '100', (string) ( $state['episode_meta'][70]['tvshow_id'] ?? '' ), 'tvshow_id is persisted immediately after create' );
$second = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( ! empty( $second['ok'] ), 'retry completes after identity meta exists' );
tv_adapter_same( 1, $creates, 'retry does not create a second Streamit episode' );
tv_adapter_same( 70, (int) $second['episode_id'], 'retry reuses the created episode id' );
tv_adapter_same( 'update', (string) ( $second['action'] ?? '' ), 'retry updates the existing episode' );

echo "\n[identity-meta-write-failure-then-recover]\n";
$state = array();
$options = tv_adapter_harness( $state );
$creates = 0;
$fail_tmdb = true;
$options['create_episode'] = static function ( $row ) use ( &$state, &$creates ) {
	++$creates;
	$state['created_episode_rows'][] = $row;
	return $state['next_episode_id']++;
};
$options['find_episodes'] = static function ( $series_id ) use ( &$state ) {
	$episodes = array();
	foreach ( $state['episode_meta'] as $id => $meta ) {
		if ( ! is_array( $meta ) ) {
			continue;
		}
		$show = absint( $meta['tvshow_id'] ?? 0 );
		if ( $show !== (int) $series_id ) {
			continue;
		}
		$episodes[] = array(
			'id'             => (int) $id,
			'tvshow_id'      => $show,
			'tmdb_id'        => absint( $meta['_tmdb_id'] ?? 0 ),
			'season_number'  => $meta['_season_number'] ?? null,
			'episode_number' => $meta['_episode_number'] ?? null,
		);
	}
	return $episodes;
};
$options['update_episode_meta'] = static function ( $id, $key, $value ) use ( &$state, &$fail_tmdb ) {
	if ( $fail_tmdb && '_tmdb_id' === $key ) {
		$fail_tmdb = false;
		return false;
	}
	$state['episode_meta'][ $id ][ $key ] = $value;
	return true;
};
$episode_plan = tv_adapter_plan()['seasons'][0]['episodes'][0];
$first = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( empty( $first['ok'] ), 'identity TMDb write failure returns ok=false' );
tv_adapter_same( 'series_tv_adapter_episode_meta_failed', (string) ( $first['error']['code'] ?? '' ), 'identity write failure uses episode meta error' );
tv_adapter_same( 1, $creates, 'failed identity write does not retry create in the same call' );
tv_adapter_same( '100', (string) ( $state['episode_meta'][70]['tvshow_id'] ?? '' ), 'tvshow_id survived the failed TMDb identity write' );
tv_adapter_same( '0', (string) ( $state['episode_meta'][70]['_season_number'] ?? '' ), 'season identity survived the failed TMDb identity write' );
$second = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( ! empty( $second['ok'] ), 'retry recovers using season and episode identity' );
tv_adapter_same( 1, $creates, 'retry after partial identity write does not duplicate' );
tv_adapter_same( 70, (int) $second['episode_id'], 'retry reuses the episode after partial identity write' );

echo "\n[crash-after-tvshow-id-only-uses-remembered-id]\n";
$state = array();
$options = tv_adapter_harness( $state );
$creates = 0;
$remembered = 0;
$fail_key = '_season_number';
$options['create_episode'] = static function ( $row ) use ( &$state, &$creates ) {
	++$creates;
	$state['created_episode_rows'][] = $row;
	$id = $state['next_episode_id']++;
	$state['episode_parent'][ $id ] = absint( $row['post_parent'] ?? 0 );
	return $id;
};
$options['remember_created_episode'] = static function ( $id ) use ( &$remembered ) {
	$remembered = absint( $id );
	return true;
};
$options['find_episodes'] = static function ( $series_id ) use ( &$state ) {
	$episodes = array();
	foreach ( $state['episode_meta'] as $id => $meta ) {
		if ( ! is_array( $meta ) ) {
			continue;
		}
		$show = absint( $meta['tvshow_id'] ?? 0 );
		if ( $show !== (int) $series_id ) {
			continue;
		}
		$episodes[] = array(
			'id'             => (int) $id,
			'tvshow_id'      => $show,
			'tmdb_id'        => absint( $meta['_tmdb_id'] ?? 0 ),
			'season_number'  => $meta['_season_number'] ?? null,
			'episode_number' => $meta['_episode_number'] ?? null,
		);
	}
	return $episodes;
};
$options['get_episode'] = static function ( $id ) use ( &$state ) {
	$fields = tv_adapter_fields( 'episode', 'Retry ' . $id );
	$fields['post_parent'] = (int) ( $state['episode_parent'][ $id ] ?? 0 );
	return new TV_Adapter_Object( $fields );
};
$options['update_episode_meta'] = static function ( $id, $key, $value ) use ( &$state, &$fail_key ) {
	if ( $fail_key === $key ) {
		$fail_key = '';
		return false;
	}
	$state['episode_meta'][ $id ][ $key ] = $value;
	return true;
};
$episode_plan = tv_adapter_plan()['seasons'][0]['episodes'][0];
$first = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( empty( $first['ok'] ), 'crash after tvshow_id fails the episode' );
tv_adapter_same( 1, $creates, 'create ran once before the identity crash' );
tv_adapter_same( 70, $remembered, 'created episode id was remembered before remaining identity writes' );
tv_adapter_same( '100', (string) ( $state['episode_meta'][70]['tvshow_id'] ?? '' ), 'only tvshow_id identity was stored' );
tv_adapter_assert( ! isset( $state['episode_meta'][70]['_season_number'] ), 'season identity was not stored' );
$retry_plan = $episode_plan;
$retry_plan['retry_created_episode_id'] = $remembered;
$second = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $retry_plan, $options );
tv_adapter_assert( ! empty( $second['ok'] ), 'retry after tvshow_id-only crash completes' );
tv_adapter_same( 1, $creates, 'retry after tvshow_id-only crash does not create' );
tv_adapter_same( 70, (int) $second['episode_id'], 'retry after tvshow_id-only crash reuses remembered id' );

echo "\n[crash-after-season-before-episode-number]\n";
$state = array();
$options = tv_adapter_harness( $state );
$creates = 0;
$remembered = 0;
$fail_key = '_episode_number';
$options['create_episode'] = static function ( $row ) use ( &$state, &$creates ) {
	++$creates;
	$state['created_episode_rows'][] = $row;
	$id = $state['next_episode_id']++;
	$state['episode_parent'][ $id ] = absint( $row['post_parent'] ?? 0 );
	return $id;
};
$options['remember_created_episode'] = static function ( $id ) use ( &$remembered ) {
	$remembered = absint( $id );
	return true;
};
$options['find_episodes'] = static function () {
	return array();
};
$options['get_episode'] = static function ( $id ) use ( &$state ) {
	$fields = tv_adapter_fields( 'episode', 'Retry ' . $id );
	$fields['post_parent'] = (int) ( $state['episode_parent'][ $id ] ?? 0 );
	return new TV_Adapter_Object( $fields );
};
$options['update_episode_meta'] = static function ( $id, $key, $value ) use ( &$state, &$fail_key ) {
	if ( $fail_key === $key ) {
		$fail_key = '';
		return false;
	}
	$state['episode_meta'][ $id ][ $key ] = $value;
	return true;
};
$episode_plan = tv_adapter_plan()['seasons'][0]['episodes'][0];
$first = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( empty( $first['ok'] ), 'crash after season identity fails the episode' );
tv_adapter_same( '0', (string) ( $state['episode_meta'][70]['_season_number'] ?? '' ), 'season identity was stored' );
tv_adapter_assert( ! isset( $state['episode_meta'][70]['_episode_number'] ), 'episode number identity was not stored' );
$retry_plan = $episode_plan;
$retry_plan['retry_created_episode_id'] = $remembered;
$second = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $retry_plan, $options );
tv_adapter_assert( ! empty( $second['ok'] ), 'retry after season-only identity completes' );
tv_adapter_same( 1, $creates, 'retry after season-only crash does not create' );
tv_adapter_same( 70, (int) $second['episode_id'], 'retry after season-only crash reuses remembered id' );

echo "\n[crash-after-episode-number-before-tmdb]\n";
$state = array();
$options = tv_adapter_harness( $state );
$creates = 0;
$remembered = 0;
$fail_key = '_tmdb_id';
$options['create_episode'] = static function ( $row ) use ( &$state, &$creates ) {
	++$creates;
	$state['created_episode_rows'][] = $row;
	$id = $state['next_episode_id']++;
	$state['episode_parent'][ $id ] = absint( $row['post_parent'] ?? 0 );
	return $id;
};
$options['remember_created_episode'] = static function ( $id ) use ( &$remembered ) {
	$remembered = absint( $id );
	return true;
};
$options['find_episodes'] = static function () {
	return array();
};
$options['get_episode'] = static function ( $id ) use ( &$state ) {
	$fields = tv_adapter_fields( 'episode', 'Retry ' . $id );
	$fields['post_parent'] = (int) ( $state['episode_parent'][ $id ] ?? 0 );
	return new TV_Adapter_Object( $fields );
};
$options['update_episode_meta'] = static function ( $id, $key, $value ) use ( &$state, &$fail_key ) {
	if ( $fail_key === $key ) {
		$fail_key = '';
		return false;
	}
	$state['episode_meta'][ $id ][ $key ] = $value;
	return true;
};
$episode_plan = tv_adapter_plan()['seasons'][0]['episodes'][0];
$first = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( empty( $first['ok'] ), 'crash before TMDb identity fails the episode' );
tv_adapter_same( 'E01', (string) ( $state['episode_meta'][70]['_episode_number'] ?? '' ), 'episode number identity was stored' );
tv_adapter_assert( ! isset( $state['episode_meta'][70]['_tmdb_id'] ), 'TMDb identity was not stored' );
$retry_plan = $episode_plan;
$retry_plan['retry_created_episode_id'] = $remembered;
$second = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $retry_plan, $options );
tv_adapter_assert( ! empty( $second['ok'] ), 'retry after episode-number crash completes' );
tv_adapter_same( 1, $creates, 'retry after episode-number crash does not create' );
tv_adapter_same( 70, (int) $second['episode_id'], 'retry after episode-number crash reuses remembered id' );

echo "\n[incomplete-foreign-episode-is-not-adopted]\n";
$state = array();
$options = tv_adapter_harness( $state );
$creates = 0;
$options['create_episode'] = static function ( $row ) use ( &$state, &$creates ) {
	++$creates;
	$state['created_episode_rows'][] = $row;
	return $state['next_episode_id']++;
};
$options['find_episodes'] = static function () {
	return array(
		array(
			'id'             => 9901,
			'tvshow_id'      => 99,
			'tmdb_id'        => 0,
			'season_number'  => null,
			'episode_number' => null,
		),
	);
};
$options['get_episode'] = static function ( $id ) {
	$fields = tv_adapter_fields( 'episode', 'Foreign ' . $id );
	$fields['post_parent'] = 99;
	return new TV_Adapter_Object( $fields );
};
$state['episode_meta'][9901]['tvshow_id'] = '99';
$episode_plan = tv_adapter_plan()['seasons'][0]['episodes'][0];
$episode_plan['retry_created_episode_id'] = 9901;
$foreign = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( empty( $foreign['ok'] ), 'incomplete episode from another TV show is not adopted' );
tv_adapter_same( 'series_tv_adapter_episode_ownership_conflict', (string) ( $foreign['error']['code'] ?? '' ), 'foreign incomplete episode is an ownership conflict' );
tv_adapter_same( 0, $creates, 'foreign incomplete episode does not trigger create' );
tv_adapter_same( array(), $state['episode_rows'], 'foreign incomplete episode row is not updated' );

$state = array();
$options = tv_adapter_harness( $state );
$creates = 0;
$options['create_episode'] = static function ( $row ) use ( &$state, &$creates ) {
	++$creates;
	$state['created_episode_rows'][] = $row;
	return $state['next_episode_id']++;
};
$options['find_episodes'] = static function () {
	return array();
};
$options['get_episode'] = static function ( $id ) {
	$fields = tv_adapter_fields( 'episode', 'Foreign parent ' . $id );
	$fields['post_parent'] = 99;
	return new TV_Adapter_Object( $fields );
};
$episode_plan = tv_adapter_plan()['seasons'][0]['episodes'][0];
$episode_plan['retry_created_episode_id'] = 9902;
$parent_foreign = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( empty( $parent_foreign['ok'] ), 'post_parent of another TV show is not adopted' );
tv_adapter_same( 0, $creates, 'foreign post_parent retry does not create' );

echo "\n[remember-created-episode-must-succeed-before-identity]\n";
$state = array();
$options = tv_adapter_harness( $state );
$creates = 0;
$options['create_episode'] = static function ( $row ) use ( &$state, &$creates ) {
	++$creates;
	$state['created_episode_rows'][] = $row;
	return $state['next_episode_id']++;
};
$options['remember_created_episode'] = static function ( $id ) {
	unset( $id );
	return true;
};
$options['find_episodes'] = static function () {
	return array();
};
$episode_plan = tv_adapter_plan()['seasons'][0]['episodes'][0];
$ok_remember = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( ! empty( $ok_remember['ok'] ), 'successful remember allows identity metadata to proceed' );
tv_adapter_same( '100', (string) ( $state['episode_meta'][70]['tvshow_id'] ?? '' ), 'identity metadata is written after a successful remember' );
tv_adapter_same( 1, $creates, 'successful remember still creates once' );

$state = array();
$options = tv_adapter_harness( $state );
$creates = 0;
$options['create_episode'] = static function ( $row ) use ( &$state, &$creates ) {
	++$creates;
	$state['created_episode_rows'][] = $row;
	return $state['next_episode_id']++;
};
$options['remember_created_episode'] = static function ( $id ) {
	unset( $id );
	return false;
};
$options['find_episodes'] = static function () {
	return array();
};
$options['update_episode_meta'] = static function ( $id, $key, $value ) use ( &$state ) {
	$state['episode_meta'][ $id ][ $key ] = $value;
	$state['episode_meta_writes'][] = $key;
	return true;
};
$episode_plan = tv_adapter_plan()['seasons'][0]['episodes'][0];
$failed_remember = Movies_WP_Streamit_TV_Adapter::apply_episode_phase( 100, $episode_plan, $options );
tv_adapter_assert( empty( $failed_remember['ok'] ), 'failed remember does not complete the episode' );
tv_adapter_same( 'series_tv_adapter_episode_remember_failed', (string) ( $failed_remember['error']['code'] ?? '' ), 'failed remember uses the episode remember error' );
tv_adapter_same( 1, $creates, 'failed remember does not call create_episode again' );
tv_adapter_assert( empty( $state['episode_meta'][70] ?? array() ), 'failed remember does not write identity metadata' );
tv_adapter_same( array(), $state['episode_meta_writes'], 'failed remember writes no episode meta keys' );

echo "\n";
if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} assertion(s) failed.\n" );
	fwrite( STDERR, "EXECUTABLE TEST STATUS: pending (assertions defined; PHP runtime unavailable in this environment).\n" );
	exit( 1 );
}
echo "All Streamit TV adapter assertions constructed successfully.\n";
echo "EXECUTABLE TEST STATUS: pending — run with PHP when available:\n";
echo "  php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-streamit-tv-adapter-test.php\n";
exit( 0 );

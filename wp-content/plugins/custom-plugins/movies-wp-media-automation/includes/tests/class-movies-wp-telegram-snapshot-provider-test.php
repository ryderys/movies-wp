<?php
/**
 * CLI tests for Movies_WP_Telegram_Snapshot_Provider.
 *
 * php -n wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-telegram-snapshot-provider-test.php
 *
 * Uses in-memory Streamit stubs. Does not read the database or call Telegram.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-telegram-snapshot-test/' );
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-snapshot-provider.php';

$failures = 0;
function telegram_snapshot_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

final class Telegram_Snapshot_Post {
	/** @param array<string, mixed> $meta */
	public function __construct(
		private string $post_type,
		private string $title,
		private string $content,
		private string $name,
		private string $date,
		private array $meta
	) {
	}

	public function get_post_type() {
		return $this->post_type;
	}

	public function get_post_title() {
		return $this->title;
	}

	public function get_post_content() {
		return $this->content;
	}

	public function get_post_name() {
		return $this->name;
	}

	public function get_post_date() {
		return $this->date;
	}

	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? '';
	}
}

final class Telegram_Snapshot_Term {
	public function __construct( private string $name ) {
	}

	public function get_term_name() {
		return $this->name;
	}
}

/**
 * @param array<string, mixed> $meta
 */
function telegram_snapshot_post( string $type, string $title, string $content, string $name, string $date, array $meta ): Telegram_Snapshot_Post {
	return new Telegram_Snapshot_Post( $type, $title, $content, $name, $date, $meta );
}

/**
 * @param list<int> $ids
 * @return list<Telegram_Snapshot_Term>
 */
function telegram_snapshot_terms( array $ids ): array {
	$names = array(
		10 => 'درام',
		11 => 'خانوادگی',
	);
	$terms = array();
	foreach ( $ids as $id ) {
		if ( isset( $names[ $id ] ) ) {
			$terms[] = new Telegram_Snapshot_Term( $names[ $id ] );
		}
	}
	return $terms;
}

function telegram_snapshot_permalink( string $type, string $name ): string {
	return 'https://example.test/' . $type . '/' . $name;
}

function telegram_snapshot_attachment( int $id ): string {
	if ( 501 === $id ) {
		return 'https://cdn.example.test/posters/separation.jpg';
	}
	if ( 502 === $id ) {
		return 'https://10.1.1.5/private.jpg';
	}
	return '';
}

$movie = telegram_snapshot_post(
	'movie',
	'جدایی نادر از سیمین',
	'<p>خلاصه‌ای کوتاه</p>',
	'a-separation',
	'2011-03-16 00:00:00',
	array(
		'_movie_release_date' => '2011-03-16',
		'_portrait_thumbmail' => 501,
		'_tmdb_title'          => 'A Separation',
		'_source'              => 'https://files.example.test/Movie/Korea/film.mp4',
		'_movie_url_link'      => 'https://files.example.test/play',
		'_media_directory'     => 'Movie/Korea/2011/A-Separation',
	)
);
$series = telegram_snapshot_post(
	'tvshow',
	'سریال نمونه',
	'داستان سریال',
	'sample-show',
	'2019-06-15 12:00:00',
	array(
		'_portrait_thumbmail' => 501,
		'_tmdb_title'          => 'Sample Show',
	)
);
$episode = telegram_snapshot_post(
	'episode',
	'شروع',
	'قسمت اول',
	'sample-s01e02',
	'2019-06-16 12:00:00',
	array(
		'tvshow_id'        => 70,
		'_season_number'   => '1',
		'_episode_number'  => '2',
		'thumbnail_id'     => 501,
		'_subtitles'       => '/data/subs/fa.vtt',
	)
);

$seen_meta = array();
$options   = array(
	'get_movie'   => static function ( $id ) use ( $movie ) {
		return 41 === (int) $id ? $movie : null;
	},
	'get_tvshow'  => static function ( $id ) use ( $series ) {
		return 70 === (int) $id ? $series : null;
	},
	'get_episode' => static function ( $id ) use ( $episode ) {
		return 80 === (int) $id ? $episode : null;
	},
	'get_meta'    => static function ( $object, $key ) use ( &$seen_meta ) {
		$seen_meta[] = (string) $key;
		return $object->get_meta( $key );
	},
	'get_relationships' => static function ( $post_id, $taxonomy ) {
		if ( 41 === (int) $post_id && 'movie_genre' === $taxonomy ) {
			return array( 10, 11 );
		}
		if ( 70 === (int) $post_id && 'tvshow_genre' === $taxonomy ) {
			return array( 10 );
		}
		return array();
	},
	'get_terms'      => static function ( $ids ) {
		return telegram_snapshot_terms( $ids );
	},
	'permalink'      => 'telegram_snapshot_permalink',
	'attachment_url' => 'telegram_snapshot_attachment',
);

echo "Telegram snapshot provider\n";
$built = Movies_WP_Telegram_Snapshot_Provider::build( 'movie', 41, $options );
telegram_snapshot_assert( true === $built['ok'], 'movie snapshot builds' );
$snap = $built['snapshot'];
telegram_snapshot_assert( 'movie' === $snap['type'], 'movie type is movie' );
telegram_snapshot_assert( 'جدایی نادر از سیمین' === $snap['title'], 'movie title is the Persian post title' );
telegram_snapshot_assert( 'خلاصه‌ای کوتاه' === $snap['description'], 'movie description comes from post content' );
telegram_snapshot_assert( ! str_contains( (string) $snap['title'], 'A Separation' ), 'movie title does not use the English TMDb title' );
telegram_snapshot_assert( '2011' === ( $snap['year'] ?? '' ), 'movie year comes from the release date' );
telegram_snapshot_assert( array( 'درام', 'خانوادگی' ) === $snap['genres'], 'movie genres come from movie_genre' );
telegram_snapshot_assert( 'https://cdn.example.test/posters/separation.jpg' === $snap['poster_url'], 'movie poster is the public portrait URL' );
telegram_snapshot_assert( 'https://example.test/movie/a-separation' === $snap['permalink'], 'movie permalink uses the movie route and slug' );

$sparse_movie = telegram_snapshot_post( 'movie', 'فیلم بدون جزئیات', '', 'bare-movie', '', array() );
$sparse       = Movies_WP_Telegram_Snapshot_Provider::build(
	'movie',
	42,
	array_merge(
		$options,
		array(
			'get_movie' => static function () use ( $sparse_movie ) {
				return $sparse_movie;
			},
		)
	)
);
telegram_snapshot_assert( true === $sparse['ok'], 'movie with missing optional metadata still builds' );
telegram_snapshot_assert( ! array_key_exists( 'year', $sparse['snapshot'] ), 'missing release date omits the year' );
telegram_snapshot_assert( array() === $sparse['snapshot']['genres'], 'missing genre relationships produce an empty list' );
telegram_snapshot_assert( '' === $sparse['snapshot']['poster_url'], 'missing poster is empty' );
telegram_snapshot_assert( '' === $sparse['snapshot']['description'], 'missing description is empty' );

$series_result = Movies_WP_Telegram_Snapshot_Provider::build( 'series', 70, $options );
telegram_snapshot_assert( true === $series_result['ok'], 'series snapshot builds' );
telegram_snapshot_assert( 'سریال نمونه' === $series_result['snapshot']['title'], 'series title is Persian' );
telegram_snapshot_assert( '2019' === ( $series_result['snapshot']['year'] ?? '' ), 'series year comes from post_date' );
telegram_snapshot_assert( array( 'درام' ) === $series_result['snapshot']['genres'], 'series genres come from tvshow_genre' );
telegram_snapshot_assert( 'https://example.test/tvshow/sample-show' === $series_result['snapshot']['permalink'], 'series permalink uses the tvshow route' );

$episode_result = Movies_WP_Telegram_Snapshot_Provider::build( 'episode', 80, $options );
telegram_snapshot_assert( true === $episode_result['ok'], 'episode snapshot builds' );
telegram_snapshot_assert( 'شروع' === $episode_result['snapshot']['title'], 'episode title is the episode post title' );
telegram_snapshot_assert( 'سریال نمونه' === $episode_result['snapshot']['series_title'], 'episode includes the parent series title' );
telegram_snapshot_assert( '1' === ( $episode_result['snapshot']['season'] ?? '' ), 'episode season comes from episode meta' );
telegram_snapshot_assert( '2' === ( $episode_result['snapshot']['episode'] ?? '' ), 'episode number comes from episode meta' );
telegram_snapshot_assert( 'https://example.test/episode/sample-s01e02' === $episode_result['snapshot']['permalink'], 'episode permalink uses the episode route' );
telegram_snapshot_assert( ! array_key_exists( 'genres', $episode_result['snapshot'] ) || array() === $episode_result['snapshot']['genres'], 'episode snapshot does not invent genres' );

$orphan = telegram_snapshot_post( 'episode', 'یتیم', '', 'orphan', '', array( 'tvshow_id' => 0 ) );
$orphan_result = Movies_WP_Telegram_Snapshot_Provider::build(
	'episode',
	81,
	array_merge(
		$options,
		array(
			'get_episode' => static function () use ( $orphan ) {
				return $orphan;
			},
		)
	)
);
telegram_snapshot_assert( false === $orphan_result['ok'] && 'missing_parent' === $orphan_result['code'], 'episode without a parent series is rejected' );

$wrong = Movies_WP_Telegram_Snapshot_Provider::build(
	'movie',
	70,
	array_merge(
		$options,
		array(
			'get_movie' => static function () use ( $series ) {
				return $series;
			},
		)
	)
);
telegram_snapshot_assert( false === $wrong['ok'] && 'wrong_type' === $wrong['code'], 'a series ID requested as a movie is rejected' );
telegram_snapshot_assert( false === Movies_WP_Telegram_Snapshot_Provider::build( 'movie', 0, $options )['ok'], 'a non-positive ID is rejected' );
telegram_snapshot_assert( false === Movies_WP_Telegram_Snapshot_Provider::build( 'trailer', 41, $options )['ok'], 'an unknown content type is rejected' );
telegram_snapshot_assert( false === Movies_WP_Telegram_Snapshot_Provider::build( 'movie', 999, $options )['ok'], 'a missing movie is rejected' );

$private = telegram_snapshot_post( 'movie', 'خصوصی', '', 'private-poster', '', array( '_portrait_thumbmail' => 502 ) );
$private_result = Movies_WP_Telegram_Snapshot_Provider::build(
	'movie',
	43,
	array_merge(
		$options,
		array(
			'get_movie' => static function () use ( $private ) {
				return $private;
			},
		)
	)
);
telegram_snapshot_assert( '' === $private_result['snapshot']['poster_url'], 'a private poster URL is not copied into the snapshot' );

$internal = Movies_WP_Telegram_Snapshot_Provider::build(
	'movie',
	41,
	array_merge(
		$options,
		array(
			'permalink' => static function () {
				return 'https://files.example.test/Movie/Korea/film.mp4';
			},
		)
	)
);
telegram_snapshot_assert( '' === $internal['snapshot']['permalink'], 'an internal media URL is not used as the permalink' );

$encoded = (string) json_encode( $built['snapshot'] );
telegram_snapshot_assert( ! str_contains( $encoded, 'Movie/Korea' ), 'media directory paths are not in the snapshot' );
telegram_snapshot_assert( ! str_contains( $encoded, 'files.example.test/play' ), 'playback URLs are not in the snapshot' );
telegram_snapshot_assert( ! str_contains( $encoded, 'A Separation' ), 'the English TMDb title is not in the snapshot' );
$forbidden = array( '_source', '_movie_url_link', '_media_directory', '_subtitles', '_tmdb_title' );
$leaked    = array_values( array_intersect( $seen_meta, $forbidden ) );
telegram_snapshot_assert( array() === $leaked, 'forbidden metadata keys are not read' );

$bootstrap = (string) file_get_contents( dirname( __DIR__, 2 ) . '/movies-wp-media-automation.php' );
telegram_snapshot_assert( str_contains( $bootstrap, 'class-movies-wp-telegram-publisher.php' ), 'plugin bootstrap loads the Telegram publisher' );
telegram_snapshot_assert( ! str_contains( $bootstrap, 'Movies_WP_Telegram_Client::deliver' ), 'plugin bootstrap does not send Telegram messages' );
telegram_snapshot_assert( ! str_contains( $bootstrap, 'movies_wp_telegram_enabled' ), 'plugin bootstrap does not enable publishing' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Telegram snapshot tests passed.\n";
exit( $failures ? 1 : 0 );

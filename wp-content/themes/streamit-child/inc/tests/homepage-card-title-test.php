<?php
/**
 * CLI tests for English titles on homepage movie / TV show cards.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/homepage-card-title-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-homepage-card-title-test/' );
}
if ( ! defined( 'STREAMIT_CHILD_SIZE_HERO' ) ) {
	define( 'STREAMIT_CHILD_SIZE_HERO', 'hero' );
}
if ( ! defined( 'STREAMIT_CHILD_SIZE_GENRE' ) ) {
	define( 'STREAMIT_CHILD_SIZE_GENRE', 'genre' );
}

$GLOBALS['hct_is_front_page'] = true;

function is_front_page() {
	return (bool) $GLOBALS['hct_is_front_page'];
}
function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $value ) {
	return (string) $value;
}
function esc_html__( $text, $domain = 'default' ) {
	unset( $domain );
	return esc_html( $text );
}
function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html__( $text, $domain );
}
function esc_attr_e( $text, $domain = 'default' ) {
	unset( $domain );
	echo esc_attr( $text );
}
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}
function _n( $single, $plural, $number, $domain = 'default' ) {
	unset( $domain );
	return 1 === (int) $number ? $single : $plural;
}
function wp_kses_post( $value ) {
	return (string) $value;
}
function sanitize_text_field( $value ) {
	return trim( (string) $value );
}
function sanitize_title( $value ) {
	return (string) $value;
}
function trailingslashit( $value ) {
	return rtrim( (string) $value, '/' ) . '/';
}
function is_wp_error( $value ) {
	unset( $value );
	return false;
}
function do_shortcode( $value ) {
	unset( $value );
	return '<button class="watch-list-btn"></button>';
}
function date_i18n( $format, $ts ) {
	return gmdate( (string) $format, (int) $ts );
}
function streamit_get_permalink( $type, $slug = '', $filter = '' ) {
	unset( $filter );
	return 'https://example.test/' . $type . '/' . $slug;
}
function streamit_get_term_relationships( $id, $taxonomy ) {
	unset( $id, $taxonomy );
	return array();
}
function streamit_get_imdb_logo() {
	return array();
}
function streamit_render_image( array $args ) {
	return '<img alt="' . ( $args['alt'] ?? '' ) . '">';
}
function wp_get_attachment_image( $id, $size, $icon, array $attr ) {
	unset( $id, $size, $icon );
	return '<img alt="' . ( $attr['alt'] ?? '' ) . '">';
}
function wp_get_attachment_image_url( $id, $size ) {
	unset( $id, $size );
	return 'https://example.test/thumb.jpg';
}
function streamit_placeholder_image() {
	return 'https://example.test/placeholder.jpg';
}
function st_get_icon( $name ) {
	unset( $name );
	return '';
}
function st_format_runtime( $value ) {
	return (string) $value;
}
function st_star_rating( $value ) {
	unset( $value );
	return '';
}
function streamit_child_get_available_seasons( $post ) {
	unset( $post );
	return array( array(), array() );
}

class Homepage_Card_Test_Object {
	private $type;
	private $title;
	private $meta;

	public function __construct( $type, $title, array $meta = array() ) {
		$this->type  = $type;
		$this->title = $title;
		$this->meta  = $meta;
	}
	public function get_id() {
		return 7;
	}
	public function get_post_type() {
		return $this->type;
	}
	public function get_post_title() {
		return $this->title;
	}
	public function get_post_name() {
		return 'slug-' . $this->type;
	}
	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? '';
	}
	public function get_post_content() {
		return 'Summary';
	}
	public function get_post_date() {
		return '2026-01-01 00:00:00';
	}
}

require_once dirname( __DIR__ ) . '/homepage-card-title.php';

$failures = 0;

function assert_true( bool $condition, string $label ): void {
	global $failures;
	if ( $condition ) {
		echo "  ok  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
}

function assert_eq( $expected, $actual, string $label ): void {
	assert_true( $expected === $actual, $label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' );
}

$theme = dirname( __DIR__, 2 );

$movie        = new Homepage_Card_Test_Object( 'movie', 'تصمیم به جدایی', array( '_tmdb_title' => 'Decision to Leave' ) );
$tvshow       = new Homepage_Card_Test_Object( 'tvshow', 'شوهر', array( '_tmdb_title' => ' The Husband ' ) );
$movie_no_en  = new Homepage_Card_Test_Object( 'movie', 'فیلم بدون عنوان انگلیسی' );
$tvshow_blank = new Homepage_Card_Test_Object( 'tvshow', 'سریال بی‌نام', array( '_tmdb_title' => '   ' ) );
$episode      = new Homepage_Card_Test_Object( 'episode', 'قسمت ۱', array( '_tmdb_title' => 'Episode 1' ) );
$video        = new Homepage_Card_Test_Object( 'video', 'ویدیو', array( '_tmdb_title' => 'Video' ) );
$movie_same   = new Homepage_Card_Test_Object( 'movie', 'Parasite', array( '_tmdb_title' => 'Parasite' ) );
$tvshow_no_fa = new Homepage_Card_Test_Object( 'tvshow', '  ', array( '_tmdb_title' => 'Moving' ) );

echo "streamit_child_homepage_card_title\n\n";

assert_eq( 'Decision to Leave', streamit_child_homepage_card_title( $movie ), 'front page movie card uses _tmdb_title' );
assert_eq( 'The Husband', streamit_child_homepage_card_title( $tvshow ), 'front page TV show card uses trimmed _tmdb_title' );
assert_eq( 'فیلم بدون عنوان انگلیسی', streamit_child_homepage_card_title( $movie_no_en ), 'missing English title falls back to existing title' );
assert_eq( 'سریال بی‌نام', streamit_child_homepage_card_title( $tvshow_blank ), 'blank English title falls back to existing title' );
assert_eq( 'قسمت ۱', streamit_child_homepage_card_title( $episode ), 'episode cards unchanged' );
assert_eq( 'ویدیو', streamit_child_homepage_card_title( $video ), 'video cards unchanged' );
assert_eq( '', streamit_child_homepage_card_title( null ), 'non-object returns empty without errors' );

$GLOBALS['hct_is_front_page'] = false;
assert_eq( 'تصمیم به جدایی', streamit_child_homepage_card_title( $movie ), 'off the front page the movie title is unchanged' );
assert_eq( 'شوهر', streamit_child_homepage_card_title( $tvshow ), 'off the front page the TV show title is unchanged' );
$GLOBALS['hct_is_front_page'] = true;

echo "\nstreamit_child_homepage_card_subtitle\n\n";

assert_eq( 'تصمیم به جدایی', streamit_child_homepage_card_subtitle( $movie ), 'movie card subtitle is the Persian post title' );
assert_eq( 'شوهر', streamit_child_homepage_card_subtitle( $tvshow ), 'TV show card subtitle is the Persian post title' );
assert_eq( '', streamit_child_homepage_card_subtitle( $movie_no_en ), 'no subtitle when English title is missing (Persian already primary)' );
assert_eq( '', streamit_child_homepage_card_subtitle( $tvshow_blank ), 'no subtitle when English title is blank' );
assert_eq( '', streamit_child_homepage_card_subtitle( $movie_same ), 'no duplicate subtitle when titles are identical' );
assert_eq( '', streamit_child_homepage_card_subtitle( $tvshow_no_fa ), 'no blank subtitle when Persian title is empty' );
assert_eq( 'Moving', streamit_child_homepage_card_title( $tvshow_no_fa ), 'English title still shown when Persian title is empty' );
assert_eq( '', streamit_child_homepage_card_subtitle( $episode ), 'episode cards get no subtitle' );
assert_eq( '', streamit_child_homepage_card_subtitle( $video ), 'video cards get no subtitle' );
assert_eq( '', streamit_child_homepage_card_subtitle( null ), 'non-object returns empty subtitle' );

$GLOBALS['hct_is_front_page'] = false;
assert_eq( '', streamit_child_homepage_card_subtitle( $movie ), 'no subtitle off the front page (movie)' );
assert_eq( '', streamit_child_homepage_card_subtitle( $tvshow ), 'no subtitle off the front page (TV show)' );
$GLOBALS['hct_is_front_page'] = true;

function render_template( string $file, array $vars ): string {
	extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	ob_start();
	require $file;
	return (string) ob_get_clean();
}

function render_banner( array $results ): string {
	global $theme;
	$settings = array( 'st_play_now_text' => 'تماشا' );
	return render_template(
		$theme . '/template-parts/elementor-widget/vertical-thumbnail-banner/html-vertical-thumbnail-banner-slider.php',
		array(
			'results'  => $results,
			'id_int'   => 11,
			'settings' => $settings,
			'args'     => array( 'settings' => $settings ),
		)
	);
}

function render_grid( array $post_data ): string {
	global $theme, $streamit_options;
	$streamit_options = array(
		'streamit_recommended_enable_premium_badges'  => 'no',
		'streamit_recommended_enable_upcoming_badges' => 'no',
	);
	return render_template(
		$theme . '/template-parts/elementor-widget/main-card-slider/html-main-card-grid.php',
		array(
			'post_data'    => $post_data,
			'settings'     => array(
				'show_title'             => 'yes',
				'st_select_content_type' => 'tvshow',
				'play_now_text'          => 'تماشا',
			),
			'title_tag'    => 'h3',
			'slider_title' => 'سریال های بروز شده',
			'post_filter'  => 'latest',
		)
	);
}

function render_continue( array $items ): string {
	global $theme;
	$post_content = array();
	foreach ( $items as $item ) {
		$post_content[] = array(
			'post_data'               => $item,
			'watched_time'            => 60,
			'watched_total_time'      => 600,
			'watched_time_percentage' => 10,
		);
	}
	return render_template(
		$theme . '/template-parts/elementor-widget/continue-watching/html-continue-watching.php',
		array(
			'slider_title'   => 'ادامه تماشا',
			'title_tag'      => 'h4',
			'settings'       => array( 'nav-arrow' => 'false' ),
			'slick_settings' => array(),
			'args'           => array( 'post_content' => $post_content ),
		)
	);
}

function visible_titles( string $html, string $pattern ): array {
	preg_match_all( $pattern, $html, $m );
	return array_map( 'trim', $m[1] );
}

echo "\nhomepage vertical thumbnail banner (hero + thumbnail cards)\n\n";

$banner = render_banner( array( $tvshow, $movie, $movie_no_en ) );
assert_eq( array( 'The Husband', 'Decision to Leave', 'فیلم بدون عنوان انگلیسی' ), visible_titles( $banner, '#<h2 class="m-0 title[^"]*">([^<]*)</h2>#' ), 'hero banner titles: English, English, fallback' );
assert_eq( array( 'The Husband', 'Decision to Leave', 'فیلم بدون عنوان انگلیسی' ), visible_titles( $banner, '#<h5 class="verticle-title[^"]*">([^<]*)</h5>#' ), 'thumbnail card titles: English, English, fallback' );
assert_true( str_contains( $banner, 'alt="شوهر"' ), 'image alt text keeps the stored title' );
assert_true( (bool) preg_match( '#<h2 class="m-0 title[^"]*">The Husband</h2>\s*<p class="streamit-child-card-fa-title RightAnimate-two line-count-1" dir="auto">شوهر</p>#u', $banner ), 'hero: Persian title directly under English title' );
assert_true( (bool) preg_match( '#<h5 class="verticle-title[^"]*">Decision to Leave</h5>\s*<p class="streamit-child-card-fa-title line-count-1" dir="auto">تصمیم به جدایی</p>#u', $banner ), 'thumbnail: Persian title directly under English title' );
assert_eq( 4, substr_count( $banner, 'streamit-child-card-fa-title' ), 'banner: subtitle only for the 2 English cards (hero + thumbnail each)' );
assert_true( ! preg_match( '#فیلم بدون عنوان انگلیسی</h[25]>\s*<p class="streamit-child-card-fa-title#u', $banner ), 'banner: fallback card has no duplicate subtitle' );

echo "\nhomepage main card grid (hover overlay title)\n\n";

$grid = render_grid( array( $tvshow, $movie, $tvshow_blank ) );
assert_eq( array( 'The Husband', 'Decision to Leave', 'سریال بی‌نام' ), visible_titles( $grid, '#<h5 class="css_prefix-title[^"]*">\s*<a[^>]*>([^<]*)</a>#' ), 'grid card titles: English, English, fallback' );
assert_true( str_contains( $grid, 'class="btn btn-primary w-100"' ) && str_contains( $grid, 'watch-list-btn' ), 'play and watchlist buttons still render' );
assert_eq( array( 'شوهر', 'تصمیم به جدایی' ), visible_titles( $grid, '#</h5>\s*<p class="streamit-child-card-fa-title line-count-1" dir="auto">([^<]*)</p>#u' ), 'grid: Persian subtitles directly under English titles, none for fallback' );

$grid_edge = render_grid( array( $movie_same, $tvshow_no_fa ) );
assert_eq( array( 'Parasite', 'Moving' ), visible_titles( $grid_edge, '#<h5 class="css_prefix-title[^"]*">\s*<a[^>]*>([^<]*)</a>#' ), 'grid: identical-title and empty-Persian cards keep their primary title' );
assert_true( ! str_contains( $grid_edge, 'streamit-child-card-fa-title' ), 'grid: no duplicate or blank subtitle' );

echo "\nhomepage continue watching\n\n";

$continue = render_continue( array( $movie, $episode ) );
assert_eq( array( 'Decision to Leave', 'قسمت ۱' ), visible_titles( $continue, '#<span class="line-count-1">([^<]*)</span>#' ), 'continue watching: movie English, episode unchanged' );
assert_true( (bool) preg_match( '#<span class="line-count-1">Decision to Leave</span><span class="streamit-child-card-fa-title line-count-1" dir="auto">تصمیم به جدایی</span>#u', $continue ), 'continue watching: Persian title directly after English title' );
assert_eq( 1, substr_count( $continue, 'streamit-child-card-fa-title' ), 'continue watching: no subtitle for episode' );

echo "\nsame templates off the front page (e.g. Movies / TV Shows pages)\n\n";

$GLOBALS['hct_is_front_page'] = false;
assert_eq( array( 'شوهر', 'تصمیم به جدایی', 'سریال بی‌نام' ), visible_titles( render_grid( array( $tvshow, $movie, $tvshow_blank ) ), '#<h5 class="css_prefix-title[^"]*">\s*<a[^>]*>([^<]*)</a>#' ), 'grid keeps stored titles off the front page' );
assert_eq( array( 'شوهر' ), visible_titles( render_banner( array( $tvshow ) ), '#<h2 class="m-0 title[^"]*">([^<]*)</h2>#' ), 'banner keeps stored title off the front page' );
assert_true( ! str_contains( render_grid( array( $tvshow, $movie ) ) . render_banner( array( $tvshow, $movie ) ) . render_continue( array( $movie ) ), 'streamit-child-card-fa-title' ), 'no Persian subtitle line off the front page' );
$GLOBALS['hct_is_front_page'] = true;

echo "\ndetail page titles unchanged\n\n";

foreach ( array( true, false ) as $front ) {
	$GLOBALS['hct_is_front_page'] = $front;
	$ctx                          = $front ? 'front-page context' : 'normal context';

	$movie_html = render_template( $theme . '/template-parts/movie/content/movie_single_title.php', array( 'st_data' => $movie ) );
	assert_true( (bool) preg_match( '#<h1[^>]*>\s*تصمیم به جدایی\s*</h1>#', $movie_html ), "movie detail h1 stays Persian ({$ctx})" );
	assert_true( (bool) preg_match( '#streamit-child-tmdb-title[^>]*>\s*Decision to Leave\s*<#', $movie_html ), "movie detail English subtitle unchanged ({$ctx})" );

	$tv_html = render_template( $theme . '/template-parts/tvshow/content/tvshow_single_title.php', array( 'st_data' => $tvshow ) );
	assert_true( (bool) preg_match( '#<h1[^>]*>\s*شوهر\s*</h1>#', $tv_html ), "TV detail h1 stays Persian ({$ctx})" );
	assert_true( (bool) preg_match( '#streamit-child-tmdb-title[^>]*>\s*The Husband\s*<#', $tv_html ), "TV detail English subtitle unchanged ({$ctx})" );
	assert_true( ! str_contains( $movie_html . $tv_html, 'streamit-child-card-fa-title' ), "detail pages render no homepage subtitle ({$ctx})" );
}

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}

echo "All homepage card title tests passed.\n";
exit( 0 );

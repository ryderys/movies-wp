<?php
/**
 * Regression: detail heroes must not autoplay _name_trailer_link.
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/tvshow-trailer-hero-test.php
 */

declare(strict_types=1);

$root      = dirname( __DIR__, 2 ) . '/template-parts';
$failures  = 0;
$heroes    = array(
	'tvshow/content/tvshow_single_trailer.php',
	'movie/content/movie_single_trailer.php',
);
$actions   = array(
	'tvshow/content/tvshow_single_play_btn.php',
	'movie/content/movie_single_play_btn.php',
);

function hero_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

echo "hero templates stay poster-only\n";
foreach ( $heroes as $relative ) {
	$source = (string) file_get_contents( $root . '/' . $relative );
	hero_assert( ! str_contains( $source, 'autoplay muted' ), $relative . ' has no autoplay video' );
	hero_assert( ! str_contains( $source, 'allow="autoplay"' ), $relative . ' has no autoplay iframe' );
	hero_assert( ! str_contains( $source, '_name_trailer_link' ), $relative . ' does not read the trailer URL' );
	hero_assert( ! str_contains( $source, '<video' ), $relative . ' does not render a video element' );
	hero_assert( ! str_contains( $source, '<iframe' ), $relative . ' does not render an iframe' );
	hero_assert( str_contains( $source, 'video-banner-image' ), $relative . ' keeps the poster image' );
}

echo "\nWatch Trailer stays on the action button\n";
foreach ( $actions as $relative ) {
	$source = (string) file_get_contents( $root . '/' . $relative );
	hero_assert( str_contains( $source, 'st-load-trailer' ), $relative . ' opens the trailer modal' );
	hero_assert( str_contains( $source, 'data-trailer-url' ), $relative . ' passes the trailer URL to the button' );
	hero_assert( str_contains( $source, 'streamit_child_get_movie_trailer_button' ), $relative . ' uses the playback helper' );
	hero_assert( ! str_contains( $source, 'autoplay' ), $relative . ' does not autoplay' );
}

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nAll trailer hero regression checks passed.\n";
exit( 0 );

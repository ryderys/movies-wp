<?php
/**
 * CLI tests for media_classify_series_category().
 *
 * Run: php media-server/tests/series-category-test.php
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/series-category.php';

$failures = 0;

function series_category_assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function series_category_type( string $name ): string {
	return media_classify_series_category( $name )['type'];
}

echo "video release categories\n";
series_category_assert_true( series_category_type( '480p WEB-DL' ) === 'VIDEO_RELEASE', '480p WEB-DL' );
series_category_assert_true( series_category_type( '540p WEB-DL' ) === 'VIDEO_RELEASE', '540p WEB-DL' );
series_category_assert_true( series_category_type( '540p SOFT SUB' ) === 'VIDEO_RELEASE', '540p SOFT SUB' );
series_category_assert_true( series_category_type( '720p WEB-DL' ) === 'VIDEO_RELEASE', '720p WEB-DL' );
series_category_assert_true( series_category_type( '720p x265 WEB-DL' ) === 'VIDEO_RELEASE', '720p x265 WEB-DL' );
series_category_assert_true( series_category_type( '1080p WEB-DL' ) === 'VIDEO_RELEASE', '1080p WEB-DL' );

echo "\nsubtitle categories\n";
series_category_assert_true( series_category_type( 'SUB' ) === 'SUBTITLE', 'SUB' );
series_category_assert_true( series_category_type( 'SUB.ENG' ) === 'SUBTITLE', 'SUB.ENG' );
series_category_assert_true( series_category_type( 'SUB_FA' ) === 'SUBTITLE', 'SUB_FA' );
series_category_assert_true( series_category_type( 'SUB-FA' ) === 'SUBTITLE', 'SUB-FA' );

echo "\nsupplementary and unknown\n";
series_category_assert_true( series_category_type( 'OST' ) === 'SUPPLEMENTARY', 'OST' );
series_category_assert_true( series_category_type( 'ost' ) === 'SUPPLEMENTARY', 'ost case-insensitive' );
series_category_assert_true( series_category_type( '720p' ) === 'UNKNOWN', 'bare 720p rejected' );
series_category_assert_true( series_category_type( 'WEB-DL' ) === 'UNKNOWN', 'bare WEB-DL rejected' );
series_category_assert_true( series_category_type( 'random-folder' ) === 'UNKNOWN', 'unknown category' );

$eng = media_classify_series_category( 'SUB.ENG' );
series_category_assert_true( ( $eng['language_hint'] ?? '' ) === 'ENG', 'SUB.ENG language hint' );

$quality = media_classify_series_category( '720p WEB-DL' )['quality_hint'] ?? '';
series_category_assert_true( $quality === '720p', 'quality hint from category' );

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failure(s)\n" );
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

<?php
/**
 * CLI tests for media_parse_series_episode_identity().
 *
 * Run: php media-server/tests/series-episode-identity-test.php
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/series-episode-identity.php';

$failures = 0;

function series_identity_assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function series_identity_parse( string $filename ): array {
	return media_parse_series_episode_identity( $filename );
}

echo "valid identities\n";
$one = series_identity_parse( 'Show.S01E01.720p.WEB-DL.mkv' );
series_identity_assert_true( ( $one['ok'] ?? false ) === true, 'S01E01 accepted' );
series_identity_assert_true( ( $one['season_number'] ?? '' ) === '1', 'season 1' );
series_identity_assert_true( ( $one['episode_number'] ?? '' ) === '1', 'episode 1' );

$lower = series_identity_parse( 'Show.s01e01.mkv' );
series_identity_assert_true( ( $lower['ok'] ?? false ) === true, 'lowercase s01e01' );

$multi = series_identity_parse( 'Show.S10E12.mkv' );
series_identity_assert_true( ( $multi['season_number'] ?? '' ) === '10', 'S10E12 season' );
series_identity_assert_true( ( $multi['episode_number'] ?? '' ) === '12', 'S10E12 episode' );

$zero = series_identity_parse( 'Show.S00E01.mkv' );
series_identity_assert_true( ( $zero['season_number'] ?? '' ) === '0', 'Season 0 preserved as string zero' );

$year = series_identity_parse( 'A.Love.to.Kill.2005.S01E02.540p.KCW.WEB-DL.mkv' );
series_identity_assert_true( ( $year['season_number'] ?? '' ) === '1' && ( $year['episode_number'] ?? '' ) === '2', '2005 does not interfere with S01E02' );
series_identity_assert_true(
	( $year['sanitized_filename'] ?? '' ) === 'A.Love.to.Kill.2005.540p.KCW.WEB-DL.mkv',
	'sanitize removes S/E once and preserves WEB-DL hyphen'
);

echo "\ninvalid identities\n";
series_identity_assert_true( ( series_identity_parse( 'Show.NoEpisode.mkv' )['code'] ?? '' ) === 'missing_episode_identity', 'missing identity' );
series_identity_assert_true( ( series_identity_parse( 'Show.S01E00.mkv' )['code'] ?? '' ) === 'malformed_episode_identity', 'S01E00 rejected' );
series_identity_assert_true( ( series_identity_parse( 'Show.SxE02.mkv' )['code'] ?? '' ) === 'malformed_episode_identity', 'SxE02 rejected' );
series_identity_assert_true( ( series_identity_parse( 'Show.S01E.mkv' )['code'] ?? '' ) === 'malformed_episode_identity', 'S01E malformed' );
series_identity_assert_true( ( series_identity_parse( 'Show.S01E02.S01E03.mkv' )['code'] ?? '' ) === 'conflicting_episode_identity', 'conflicting identities' );

$dup = series_identity_parse( 'Show.S01E02.Copy.S01E02.mkv' );
series_identity_assert_true( ( $dup['ok'] ?? false ) === true, 'duplicate identical identity accepted' );
series_identity_assert_true( ! empty( $dup['warnings'] ), 'duplicate identical identity warns' );

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failure(s)\n" );
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

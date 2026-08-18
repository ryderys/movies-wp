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

$second = series_identity_parse( 'Show.S01E02.mkv' );
series_identity_assert_true( ( $second['season_number'] ?? '' ) === '1' && ( $second['episode_number'] ?? '' ) === '2', 'S01E02 behavior unchanged' );

$season_two = series_identity_parse( 'Show.S02E01.mkv' );
series_identity_assert_true( ( $season_two['season_number'] ?? '' ) === '2' && ( $season_two['episode_number'] ?? '' ) === '1', 'explicit S02E01 remains season 2 episode 1' );

$ep01 = series_identity_parse( 'Spring.Burning.EP01.1080p.WEB-DL.mkv' );
series_identity_assert_true( ( $ep01['ok'] ?? false ) === true, 'EP01 accepted' );
series_identity_assert_true( ( $ep01['identity_type'] ?? '' ) === 'episode_only', 'EP01 is explicitly seasonless' );
series_identity_assert_true( array_key_exists( 'season_number', $ep01 ) && null === $ep01['season_number'], 'EP01 does not invent a season' );
series_identity_assert_true( ( $ep01['episode_number'] ?? '' ) === '1', 'EP01 means episode 1' );
series_identity_assert_true( ( $ep01['sanitized_filename'] ?? '' ) === 'Spring.Burning.1080p.WEB-DL.mkv', 'EP01 is removed before generic parsing' );

$ep02 = series_identity_parse( 'Spring.Burning.ep02.720p.WEB-DL.mkv' );
series_identity_assert_true( ( $ep02['ok'] ?? false ) === true && ( $ep02['episode_number'] ?? '' ) === '2', 'EP02 accepted case-insensitively' );
series_identity_assert_true( null === ( $ep02['season_number'] ?? null ), 'EP02 remains seasonless' );

$episode_word = series_identity_parse( 'Spring Burning Episode 1 kisskh.srt' );
series_identity_assert_true( ( $episode_word['ok'] ?? false ) === true, 'Episode 1 subtitle accepted' );
series_identity_assert_true( ( $episode_word['identity_type'] ?? '' ) === 'episode_only', 'Episode 1 is episode-only' );
series_identity_assert_true( array_key_exists( 'season_number', $episode_word ) && null === $episode_word['season_number'], 'Episode 1 does not invent a season' );
series_identity_assert_true( ( $episode_word['episode_number'] ?? '' ) === '1', 'Episode 1 means episode 1' );

$episode_24 = series_identity_parse( 'Spring Burning Episode 24 kisskh.srt' );
series_identity_assert_true( ( $episode_24['ok'] ?? false ) === true, 'Episode 24 subtitle accepted' );
series_identity_assert_true( ( $episode_24['identity_type'] ?? '' ) === 'episode_only', 'Episode 24 is episode-only' );
series_identity_assert_true( null === ( $episode_24['season_number'] ?? null ), 'Episode 24 does not invent a season' );
series_identity_assert_true( ( $episode_24['episode_number'] ?? '' ) === '24', 'Episode 24 means episode 24' );

$episode_video = series_identity_parse( 'Spring Burning Episode 1.mkv' );
series_identity_assert_true( ( $episode_video['ok'] ?? false ) === true, 'Episode 1 video accepted' );
series_identity_assert_true( ( $episode_video['identity_type'] ?? '' ) === 'episode_only', 'Episode 1 video is episode-only' );
series_identity_assert_true( null === ( $episode_video['season_number'] ?? null ), 'Episode 1 video does not invent a season' );
series_identity_assert_true( ( $episode_video['episode_number'] ?? '' ) === '1', 'Episode 1 video means episode 1' );

$episode_padded = series_identity_parse( 'Show.episode 01.WEB-DL.srt' );
series_identity_assert_true( ( $episode_padded['ok'] ?? false ) === true && ( $episode_padded['episode_number'] ?? '' ) === '1', 'Episode 01 is case-insensitive and canonicalizes to 1' );
series_identity_assert_true( null === ( $episode_padded['season_number'] ?? null ), 'Episode 01 remains seasonless' );

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
series_identity_assert_true( ( series_identity_parse( 'Show.EP00.mkv' )['code'] ?? '' ) === 'malformed_episode_identity', 'EP00 rejected' );
series_identity_assert_true( ( series_identity_parse( 'Show.EP.mkv' )['code'] ?? '' ) === 'malformed_episode_identity', 'EP without digits rejected' );
series_identity_assert_true( ( series_identity_parse( 'Show.EP01.EP02.mkv' )['code'] ?? '' ) === 'conflicting_episode_identity', 'conflicting EP identities rejected' );
series_identity_assert_true( ( series_identity_parse( 'Show.S01E01.EP01.mkv' )['code'] ?? '' ) === 'conflicting_episode_identity', 'mixed identity tokens in one filename rejected' );
series_identity_assert_true( ( series_identity_parse( 'Show.E01.mkv' )['code'] ?? '' ) === 'missing_episode_identity', 'E01 remains unsupported' );
series_identity_assert_true( ( series_identity_parse( 'Show.Episode.mkv' )['code'] ?? '' ) === 'missing_episode_identity', 'Episode alone is not a valid identity' );
series_identity_assert_true( ( series_identity_parse( 'Show.Episode.mkv' )['code'] ?? '' ) !== 'malformed_episode_identity', 'Episode alone is not a malformed EP token' );
series_identity_assert_true( ! media_series_episode_identity_has_malformed_token( 'Spring Burning Episode 1 kisskh.srt' ), 'malformed detector does not flag Episode 1' );
series_identity_assert_true( ! media_series_episode_identity_has_malformed_token( 'Show.Episode.mkv' ), 'malformed detector does not flag the word Episode' );
series_identity_assert_true( media_series_episode_identity_has_malformed_token( 'Show.EP.mkv' ), 'bare EP remains malformed' );

$dup = series_identity_parse( 'Show.S01E02.Copy.S01E02.mkv' );
series_identity_assert_true( ( $dup['ok'] ?? false ) === true, 'duplicate identical identity accepted' );
series_identity_assert_true( ! empty( $dup['warnings'] ), 'duplicate identical identity warns' );

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failure(s)\n" );
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

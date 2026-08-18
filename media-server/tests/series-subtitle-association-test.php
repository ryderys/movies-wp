<?php
/**
 * CLI tests for media_associate_series_subtitles().
 *
 * Run: php media-server/tests/series-subtitle-association-test.php
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/series-subtitle-association.php';

$failures = 0;

function series_sub_assoc_assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

$files = array(
	array(
		'kind'       => 'subtitle',
		'media_path' => 'series/korea/2024/Show/SUB.ENG/Show.S01E02.ENG.srt',
		'episode'    => array( 'season_number' => '1', 'episode_number' => '2' ),
	),
	array(
		'kind'       => 'subtitle',
		'media_path' => 'series/korea/2024/Show/SUB.ENG/Show.S01E02.alt.ENG.srt',
		'episode'    => array( 'season_number' => '1', 'episode_number' => '2' ),
	),
	array(
		'kind'       => 'subtitle',
		'media_path' => 'series/korea/2024/Show/SUB.ENG/Show.S01E03.ENG.srt',
		'episode'    => array( 'season_number' => '1', 'episode_number' => '3' ),
	),
	array(
		'kind'       => 'subtitle',
		'media_path' => 'series/korea/2024/Show/SUB.ENG/Show.EP02.ENG.srt',
		'episode'    => array( 'identity_type' => 'episode_only', 'season_number' => null, 'episode_number' => '2' ),
	),
	array(
		'kind'       => 'subtitle',
		'media_path' => 'series/korea/2024/Show/SUB/BluRay/Show.EP01.srt',
		'episode'    => array( 'identity_type' => 'episode_only', 'season_number' => null, 'episode_number' => '1' ),
	),
	array(
		'kind'       => 'subtitle',
		'media_path' => 'series/korea/2024/Show/SUB.ENG/Any-Other-Name/Show.EP01.ENG.srt',
		'episode'    => array( 'identity_type' => 'episode_only', 'season_number' => null, 'episode_number' => '1' ),
	),
);

$result = media_associate_series_subtitles( $files );
series_sub_assoc_assert_true( count( $result['subtitles_by_episode']['1:2'] ?? array() ) === 2, 'same episode keeps different subtitle paths' );
series_sub_assoc_assert_true( count( $result['subtitles_by_episode']['1:3'] ?? array() ) === 1, 'different episode grouped separately' );
series_sub_assoc_assert_true( count( $result['subtitles_by_episode']['EP:2'] ?? array() ) === 1, 'EP02 subtitle remains seasonless for authoritative resolution' );
series_sub_assoc_assert_true( count( $result['subtitles_by_episode']['EP:1'] ?? array() ) === 2, 'SUB/BluRay and SUB.ENG/Any-Other-Name EP01 tracks stay distinct' );

$dup = media_associate_series_subtitles(
	array(
		array(
			'kind'       => 'subtitle',
			'media_path' => 'series/korea/2024/Show/SUB.ENG/a.srt',
			'episode'    => array( 'season_number' => '1', 'episode_number' => '1' ),
		),
		array(
			'kind'       => 'subtitle',
			'media_path' => 'series/korea/2024/Show/SUB.ENG/a.srt',
			'episode'    => array( 'season_number' => '1', 'episode_number' => '1' ),
		),
	)
);
series_sub_assoc_assert_true( ! empty( $dup['warnings'] ), 'duplicate subtitle path warns' );

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failure(s)\n" );
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

<?php
/**
 * CLI tests for streamit_child_player_source_display_label().
 *
 * Run:
 *   php wp-content/themes/streamit-child/inc/tests/media-player-source-label-test.php
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-player-label-test/' );
}

require_once dirname( __DIR__ ) . '/media-player-rewrite.php';

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

echo "streamit_child_player_source_display_label tests\n\n";

$link = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.1080p.mkv';

assert_eq(
	'AirenTeam',
	streamit_child_player_source_display_label(
		array(
			'name'    => 'AirenTeam',
			'quality' => '1080p',
			'link'    => $link,
		)
	),
	'AirenTeam encoder label'
);

assert_eq(
	'tG1R0',
	streamit_child_player_source_display_label(
		array(
			'name'    => 'tG1R0',
			'quality' => '1080p',
			'link'    => $link,
		)
	),
	'encoder name preferred over quality'
);

assert_eq(
	'1080p',
	streamit_child_player_source_display_label(
		array(
			'name'    => '',
			'quality' => '1080p',
			'link'    => $link,
		)
	),
	'empty name falls back to 1080p'
);

assert_eq(
	'480p',
	streamit_child_player_source_display_label(
		array(
			'name'    => '',
			'quality' => '480p',
			'link'    => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.480p.mkv',
		)
	),
	'empty name falls back to 480p'
);

assert_eq(
	'720p',
	streamit_child_player_source_display_label(
		array(
			'name'    => '',
			'quality' => '720p',
			'link'    => 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.720p.mkv',
		)
	),
	'empty name falls back to 720p'
);

assert_eq(
	null,
	streamit_child_player_source_display_label(
		array(
			'name'    => '',
			'quality' => '',
			'link'    => $link,
		)
	),
	'empty name + empty quality excluded'
);

assert_eq(
	null,
	streamit_child_player_source_display_label(
		array(
			'name'    => 'tG1R0',
			'quality' => '1080p',
			'link'    => '',
		)
	),
	'empty link excluded'
);

assert_eq(
	null,
	streamit_child_player_source_display_label(
		array(
			'name'    => 'tG1R0',
			'quality' => '1080p',
		)
	),
	'missing link excluded'
);

assert_eq(
	'tG1R0',
	streamit_child_player_source_display_label(
		array(
			'name'    => 'tG1R0',
			'quality' => '',
			'link'    => $link,
		)
	),
	'encoder name still shown when quality empty'
);

$row = array(
	'name'    => '',
	'quality' => '720p',
	'link'    => $link,
);
$before = $row;
streamit_child_player_source_display_label( $row );
assert_eq( $before, $row, 'helper does not mutate source row / name' );

echo "\nstreamit_child_player_source_identity tests\n\n";

assert_eq(
	'local:Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.720p.mkv',
	streamit_child_player_source_identity(
		'\\Movie\\Korea\\2022\\Decision.to.Leave\\Decision.to.Leave.2022.720p.mkv'
	),
	'local identity normalizes separators and a leading slash'
);
assert_eq(
	streamit_child_player_source_identity( 'Movie/Korea/2022/Decision.to.Leave/movie.720p.mkv' ),
	streamit_child_player_source_identity( '/Movie/Korea/2022/Decision.to.Leave/movie.720p.mkv' ),
	'the same normalized media link has the same identity'
);
assert_true(
	streamit_child_player_source_identity( 'Movie/Korea/2022/Decision.to.Leave/movie.720p-a.mkv' )
		!== streamit_child_player_source_identity( 'Movie/Korea/2022/Decision.to.Leave/movie.720p-b.mkv' ),
	'different files at the same quality remain distinct'
);
assert_eq( null, streamit_child_player_source_identity( '' ), 'empty identity excluded' );
assert_eq(
	null,
	streamit_child_player_source_identity( 'Movie/Korea/2022/Decision.to.Leave/../other.mkv' ),
	'traversal identity excluded'
);

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}
echo "All player source-label assertions passed.\n";
exit( 0 );

<?php
/**
 * Patch Streamit TMDB TV import + episode admin select labeling/filtering.
 *
 * Usage: php patch-episode-import.php
 */
$files = array(
	__DIR__ . '/../wp-content/plugins/streamit/admin/content-import/streamit-tmdb_tvshow-function.php',
	__DIR__ . '/../wp-content/plugins/streamit/includes/functions/episode/streamit-episode-function.php',
	__DIR__ . '/../wp-content/plugins/streamit/admin/view/tvshow-meta/html-tvshow-seasons.php',
);

$server_root = '/var/www/html/wp-content/plugins/streamit';
if ( is_dir( $server_root ) ) {
	$files = array(
		$server_root . '/admin/content-import/streamit-tmdb_tvshow-function.php',
		$server_root . '/includes/functions/episode/streamit-episode-function.php',
		$server_root . '/admin/view/tvshow-meta/html-tvshow-seasons.php',
	);
}

$repo_files = array(
	__DIR__ . '/../wp-content/plugins/streamit/admin/content-import/streamit-tmdb_tvshow-function.php',
	__DIR__ . '/../wp-content/plugins/streamit/includes/functions/episode/streamit-episode-function.php',
	__DIR__ . '/../wp-content/plugins/streamit/admin/view/tvshow-meta/html-tvshow-seasons.php',
);

foreach ( $repo_files as $index => $repo_file ) {
	if ( ! is_readable( $repo_file ) ) {
		fwrite( STDERR, "Missing patched source file: {$repo_file}\n" );
		exit( 1 );
	}

	$target = $files[ $index ];
	if ( ! is_readable( dirname( $target ) ) ) {
		fwrite( STDERR, "Target directory missing: {$target}\n" );
		exit( 1 );
	}

	copy( $repo_file, $target );
	echo "Patched: {$target}\n";
}

echo "Episode import/admin patches applied.\n";

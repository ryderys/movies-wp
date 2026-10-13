<?php
/**
 * Production utility: rewrite existing Persian/encoded slugs to English (TMDb title).
 *
 * - Scope: movie + tvshow posts that have _tmdb_id meta.
 * - Source of truth for new slug: _tmdb_title (fallback: _tmdb_original_title).
 * - Safe by default: dry-run only unless --apply is provided.
 *
 * Run inside the WordPress container:
 *   php /tmp/fix-slugs-to-tmdb-title-prod.php --dry-run --sample=30
 *   php /tmp/fix-slugs-to-tmdb-title-prod.php --apply
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run via CLI.\n" );
	exit( 1 );
}

$args = $_SERVER['argv'] ?? array();
$apply = in_array( '--apply', $args, true );
$dry_run = in_array( '--dry-run', $args, true ) || ! $apply;

$sample = 30;
foreach ( $args as $arg ) {
	if ( str_starts_with( $arg, '--sample=' ) ) {
		$sample = max( 0, (int) substr( $arg, strlen( '--sample=' ) ) );
	}
}

// Boot WordPress.
define( 'WP_USE_THEMES', false );
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME']    = $_SERVER['SERVER_NAME'] ?? 'localhost';

$wp_load = '/var/www/html/wp-load.php';
if ( ! is_file( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$wp_load}\n" );
	exit( 1 );
}
require_once $wp_load;

if ( ! function_exists( 'wp_unique_post_slug' ) || ! function_exists( 'sanitize_title' ) ) {
	fwrite( STDERR, "WordPress functions are not available after boot.\n" );
	exit( 1 );
}

/**
 * Slugs we consider \"already ok\": simple ASCII kebab/underscore case.
 */
function is_simple_ascii_slug( string $slug ): bool {
	return (bool) preg_match( '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $slug );
}

function tmdb_slug_source_for_post( int $post_id ): string {
	$english = trim( (string) get_post_meta( $post_id, '_tmdb_title', true ) );
	if ( $english !== '' ) {
		return $english;
	}
	$orig = trim( (string) get_post_meta( $post_id, '_tmdb_original_title', true ) );
	return $orig;
}

$types = array( 'movie', 'tvshow' );

$page        = 1;
$per_page    = 200;
$seen        = 0;
$eligible    = 0;
$changes     = 0;
$collisions  = 0;
$skipped     = 0;
$printed     = 0;

echo $dry_run ? "DRY RUN (no DB writes)\n" : "APPLY MODE (DB writes)\n";
echo "Scope: " . implode( ', ', $types ) . "\n";
echo "Slug source: _tmdb_title (fallback _tmdb_original_title)\n\n";

while ( true ) {
	$q = new WP_Query(
		array(
			'post_type'      => $types,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => '_tmdb_id',
					'compare' => 'EXISTS',
				),
			),
		)
	);

	$ids = is_array( $q->posts ?? null ) ? $q->posts : array();
	if ( $ids === array() ) {
		break;
	}

	foreach ( $ids as $id ) {
		$post_id = (int) $id;
		++$seen;

		$post = get_post( $post_id );
		if ( ! $post || ! isset( $post->post_name, $post->post_type, $post->post_status, $post->post_parent ) ) {
			++$skipped;
			continue;
		}

		$current = (string) $post->post_name;
		if ( $current === '' ) {
			++$skipped;
			continue;
		}

		// Only change slugs that look Persian/encoded/non-simple (your current broken case).
		if ( is_simple_ascii_slug( $current ) ) {
			++$skipped;
			continue;
		}

		++$eligible;

		$source = tmdb_slug_source_for_post( $post_id );
		if ( trim( $source ) === '' ) {
			++$skipped;
			continue;
		}

		$desired = sanitize_title( $source );
		if ( ! is_string( $desired ) || trim( $desired ) === '' ) {
			++$skipped;
			continue;
		}

		$unique = wp_unique_post_slug(
			$desired,
			$post_id,
			(string) $post->post_status,
			(string) $post->post_type,
			(int) $post->post_parent
		);

		if ( $unique === $current ) {
			++$skipped;
			continue;
		}

		++$changes;
		if ( $unique !== $desired ) {
			++$collisions;
		}

		if ( $printed < $sample ) {
			printf(
				"- #%d [%s] %s  =>  %s\n",
				$post_id,
				(string) $post->post_type,
				$current,
				$unique
			);
			++$printed;
		}

		if ( ! $dry_run ) {
			$result = wp_update_post(
				array(
					'ID'        => $post_id,
					'post_name' => $unique,
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				fwrite( STDERR, "Failed to update #{$post_id}: " . $result->get_error_message() . "\n" );
				exit( 1 );
			}
		}
	}

	++$page;
}

echo "\nSummary\n";
echo "- scanned: {$seen}\n";
echo "- eligible (non-simple slug): {$eligible}\n";
echo "- would_change: {$changes}\n";
echo "- collisions (unique != desired): {$collisions}\n";
echo "- skipped: {$skipped}\n";

exit( 0 );


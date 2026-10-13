<?php
/**
 * Production utility: rewrite existing Persian/encoded slugs to English (TMDb title).
 *
 * - Scope: Streamit movie + tvshow rows that have _tmdb_id meta (automation imports).
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

global $wpdb;
if ( ! isset( $wpdb ) ) {
	fwrite( STDERR, "\$wpdb is not available after boot.\n" );
	exit( 1 );
}

/**
 * Slugs we consider \"already ok\": simple ASCII kebab/underscore case.
 */
function is_simple_ascii_slug( string $slug ): bool {
	return (bool) preg_match( '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $slug );
}

/**
 * @return array{
 *   type: 'movie'|'tvshow',
 *   table: string,
 *   meta_table: string,
 *   meta_id_col: string
 * }
 */
function streamit_table_config( string $type ): array {
	global $wpdb;

	$type = strtolower( trim( $type ) );
	if ( 'tvshow' === $type ) {
		return array(
			'type'        => 'tvshow',
			'table'       => $wpdb->prefix . 'streamit_tvshow',
			'meta_table'  => $wpdb->prefix . 'streamit_tvshowmeta',
			'meta_id_col' => 'streamit_tvshow_id',
		);
	}

	return array(
		'type'        => 'movie',
		'table'       => $wpdb->prefix . 'streamit_movie',
		'meta_table'  => $wpdb->prefix . 'streamit_moviemeta',
		'meta_id_col' => 'streamit_movie_id',
	);
}

function streamit_meta_value( string $meta_table, string $meta_id_col, int $object_id, string $key ): string {
	global $wpdb;

	$object_id = absint( $object_id );
	if ( $object_id <= 0 || '' === $key ) {
		return '';
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$raw = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT meta_value FROM {$meta_table} WHERE {$meta_id_col} = %d AND meta_key = %s ORDER BY meta_id DESC LIMIT 1",
			$object_id,
			$key
		)
	);

	return is_string( $raw ) ? trim( $raw ) : '';
}

function tmdb_slug_source_for_streamit_row( array $cfg, int $object_id ): string {
	$english = streamit_meta_value( $cfg['meta_table'], $cfg['meta_id_col'], $object_id, '_tmdb_title' );
	if ( $english !== '' ) {
		return $english;
	}
	return streamit_meta_value( $cfg['meta_table'], $cfg['meta_id_col'], $object_id, '_tmdb_original_title' );
}

/**
 * Generate a unique slug inside Streamit's custom content table.
 */
function streamit_unique_slug( string $table, string $desired, int $object_id ): string {
	global $wpdb;

	$object_id = absint( $object_id );
	$base      = trim( $desired );
	if ( $base === '' ) {
		return $desired;
	}

	$slug   = $base;
	$suffix = 2;
	while ( true ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$table} WHERE post_name = %s AND ID <> %d LIMIT 1",
				$slug,
				$object_id
			)
		);
		if ( empty( $exists ) ) {
			return $slug;
		}
		$slug = $base . '-' . $suffix;
		++$suffix;
	}
}

$types = array( 'movie', 'tvshow' );
$arg_types = '';
foreach ( $args as $arg ) {
	if ( str_starts_with( $arg, '--types=' ) ) {
		$arg_types = (string) substr( $arg, strlen( '--types=' ) );
	}
}
if ( '' !== $arg_types ) {
	$types = array_values(
		array_filter(
			array_map(
				static function ( $t ) {
					return strtolower( trim( (string) $t ) );
				},
				explode( ',', $arg_types )
			),
			static function ( $t ) {
				return in_array( $t, array( 'movie', 'tvshow' ), true );
			}
		)
	);
	if ( $types === array() ) {
		fwrite( STDERR, "No valid --types provided. Use --types=movie,tvshow\n" );
		exit( 1 );
	}
}

$seen        = 0;
$eligible    = 0;
$changes     = 0;
$collisions  = 0;
$skipped     = 0;
$printed     = 0;

echo $dry_run ? "DRY RUN (no DB writes)\n" : "APPLY MODE (DB writes)\n";
echo "Scope: " . implode( ', ', $types ) . "\n";
echo "Slug source: _tmdb_title (fallback _tmdb_original_title)\n\n";

foreach ( $types as $type ) {
	$cfg        = streamit_table_config( $type );
	$table      = $cfg['table'];
	$meta_table = $cfg['meta_table'];
	$id_col     = $cfg['meta_id_col'];

	$offset   = 0;
	$per_page = 200;

	while ( true ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT c.ID
				 FROM {$table} c
				 INNER JOIN {$meta_table} m
				   ON m.{$id_col} = c.ID
				  AND m.meta_key = %s
				 WHERE c.post_status NOT IN ('auto-draft','trash')
				 ORDER BY c.ID ASC
				 LIMIT %d OFFSET %d",
				'_tmdb_id',
				$per_page,
				$offset
			)
		);

		if ( empty( $ids ) ) {
			break;
		}

		foreach ( $ids as $id ) {
			$object_id = (int) $id;
			++$seen;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT ID, post_name, post_status, post_parent FROM {$table} WHERE ID = %d LIMIT 1",
					$object_id
				),
				ARRAY_A
			);
			if ( ! is_array( $row ) ) {
				++$skipped;
				continue;
			}

			$current = isset( $row['post_name'] ) ? (string) $row['post_name'] : '';
			if ( '' === $current ) {
				++$skipped;
				continue;
			}

			// Only change slugs that look Persian/encoded/non-simple (your current broken case).
			if ( is_simple_ascii_slug( $current ) ) {
				++$skipped;
				continue;
			}

			++$eligible;

			$source = tmdb_slug_source_for_streamit_row( $cfg, $object_id );
			if ( '' === trim( $source ) ) {
				++$skipped;
				continue;
			}

			$desired = sanitize_title( $source );
			if ( ! is_string( $desired ) || '' === trim( $desired ) ) {
				++$skipped;
				continue;
			}

			$unique = streamit_unique_slug( $table, $desired, $object_id );
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
					$object_id,
					$type,
					$current,
					$unique
				);
				++$printed;
			}

			if ( ! $dry_run ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$updated = $wpdb->update(
					$table,
					array( 'post_name' => $unique ),
					array( 'ID' => $object_id ),
					array( '%s' ),
					array( '%d' )
				);
				if ( false === $updated ) {
					fwrite( STDERR, "Failed to update {$type} #{$object_id}: DB update failed.\n" );
					exit( 1 );
				}

				// Let existing cache purgers run (child theme hooks streamit_after_update_*).
				do_action( 'streamit_after_update_' . $type, $object_id, array( 'post_name' => $unique ) );
			}
		}

		$offset += $per_page;
	}
}

echo "\nSummary\n";
echo "- scanned: {$seen}\n";
echo "- eligible (non-simple slug): {$eligible}\n";
echo "- would_change: {$changes}\n";
echo "- collisions (unique != desired): {$collisions}\n";
echo "- skipped: {$skipped}\n";

exit( 0 );


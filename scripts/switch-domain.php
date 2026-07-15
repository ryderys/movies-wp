<?php
/**
 * Switch WordPress site URL across the database (handles serialized data).
 *
 * Usage (inside WordPress container):
 *   php /var/www/html/scripts/switch-domain.php --from=https://asiastarx.com --to=https://asiastarx.ir
 *   php /var/www/html/scripts/switch-domain.php --from=https://asiastarx.com --to=https://asiastarx.ir --dry-run
 *
 * @package Movies_WP
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( 1 );
}

$from    = '';
$to      = '';
$dry_run = in_array( '--dry-run', $argv, true );

foreach ( $argv as $arg ) {
	if ( str_starts_with( $arg, '--from=' ) ) {
		$from = substr( $arg, 7 );
	}
	if ( str_starts_with( $arg, '--to=' ) ) {
		$to = substr( $arg, 5 );
	}
}

if ( '' === $from || '' === $to ) {
	fwrite( STDERR, "Usage: php switch-domain.php --from=OLD_URL --to=NEW_URL [--dry-run]\n" );
	exit( 1 );
}

require '/var/www/html/wp-load.php';

/**
 * Recursive search-replace that preserves PHP serialized string lengths.
 *
 * @param mixed  $data Data to process.
 * @param string $from Old URL.
 * @param string $to   New URL.
 * @return mixed
 */
function movies_wp_recursive_replace( $data, $from, $to ) {
	if ( is_string( $data ) ) {
		if ( is_serialized( $data ) ) {
			$unserialized = @unserialize( $data, array( 'allowed_classes' => false ) );
			if ( false !== $unserialized || 'b:0;' === $data ) {
				$replaced = movies_wp_recursive_replace( $unserialized, $from, $to );
				return serialize( $replaced );
			}
		}

		return str_replace( $from, $to, $data );
	}

	if ( is_array( $data ) ) {
		foreach ( $data as $key => $value ) {
			$data[ $key ] = movies_wp_recursive_replace( $value, $from, $to );
		}
	}

	if ( is_object( $data ) ) {
		foreach ( get_object_vars( $data ) as $key => $value ) {
			$data->$key = movies_wp_recursive_replace( $value, $from, $to );
		}
	}

	return $data;
}

global $wpdb;

$variants = array_unique(
	array(
		$from,
		rtrim( $from, '/' ),
		str_replace( 'https://', 'http://', $from ),
		str_replace( 'https://', 'http://', rtrim( $from, '/' ) ),
	)
);

$to_https = $to;
$to_http  = str_replace( 'https://', 'http://', $to );

$tables = $wpdb->get_col( 'SHOW TABLES' );
$total  = 0;

foreach ( $tables as $table ) {
	$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
	foreach ( $columns as $column ) {
		$type = strtolower( $column['Type'] );
		if ( ! preg_match( '/char|text|blob/', $type ) ) {
			continue;
		}

		$col = $column['Field'];
		$pk  = $wpdb->get_var( "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'" );
		$id_col = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT Column_name FROM information_schema.KEY_COLUMN_USAGE WHERE table_schema = %s AND table_name = %s AND constraint_name = 'PRIMARY' LIMIT 1",
				DB_NAME,
				$table
			)
		);

		if ( ! $id_col ) {
			continue;
		}

		$where_parts = array();
		foreach ( $variants as $variant ) {
			$where_parts[] = $wpdb->prepare( "`{$col}` LIKE %s", '%' . $wpdb->esc_like( $variant ) . '%' );
		}
		$where = implode( ' OR ', $where_parts );

		$rows = $wpdb->get_results( "SELECT `{$id_col}`, `{$col}` FROM `{$table}` WHERE {$where}", ARRAY_A );
		foreach ( $rows as $row ) {
			$original = $row[ $col ];
			$updated  = $original;

			foreach ( $variants as $i => $variant ) {
				$replacement = ( str_starts_with( $variant, 'http://' ) ) ? $to_http : $to_https;
				$updated     = movies_wp_recursive_replace( $updated, $variant, $replacement );
			}

			if ( $updated === $original ) {
				continue;
			}

			++$total;
			if ( $dry_run ) {
				echo "[dry-run] {$table}.{$col} id={$row[$id_col]}\n";
				continue;
			}

			$wpdb->update(
				$table,
				array( $col => $updated ),
				array( $id_col => $row[ $id_col ] ),
				array( '%s' ),
				array( '%s' )
			);
		}
	}
}

if ( ! $dry_run ) {
	update_option( 'home', $to_https );
	update_option( 'siteurl', $to_https );

	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}

echo $dry_run
	? "Dry run complete. {$total} row(s) would be updated.\n"
	: "Done. Updated {$total} row(s). home/siteurl set to {$to_https}.\n";

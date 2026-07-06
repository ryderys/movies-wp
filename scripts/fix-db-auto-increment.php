<?php
/**
 * Fix missing AUTO_INCREMENT on WordPress / Streamit tables.
 * Run inside the WordPress container: php /path/to/fix-db-auto-increment.php
 */
require '/var/www/html/wp-load.php';

global $wpdb;

$dry_run = in_array( '--dry-run', $argv ?? [], true );

function get_pk_columns( $wpdb, $table ) {
	$cols = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
	$pks  = array();
	foreach ( $cols as $col ) {
		if ( 'PRI' === $col['Key'] ) {
			$pks[] = $col;
		}
	}
	return $pks;
}

function is_auto_increment_candidate( $pk_col, $pk_count ) {
	if ( 1 !== $pk_count ) {
		return false;
	}

	$name = $pk_col['Field'];
	if ( ! preg_match( '/^(ID|id|meta_id|umeta_id|comment_ID|term_id|term_taxonomy_id|link_id|request_id|action_id|log_id|note_id|submission_id|user_id)$/', $name ) ) {
		return false;
	}

	return preg_match( '/int/i', $pk_col['Type'] );
}

function table_needs_fix( $create_sql ) {
	return preg_match( '/PRIMARY KEY/i', $create_sql ) && ! preg_match( '/AUTO_INCREMENT/i', $create_sql );
}

$tables = $wpdb->get_col( 'SHOW TABLES' );
$fixed  = array();
$skipped = array();
$errors = array();

foreach ( $tables as $table ) {
	$create_row = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
	if ( ! $create_row ) {
		continue;
	}

	$create_sql = $create_row[1];
	if ( ! table_needs_fix( $create_sql ) ) {
		$skipped[] = $table;
		continue;
	}

	$pk_cols = get_pk_columns( $wpdb, $table );
	if ( empty( $pk_cols ) ) {
		$errors[] = "{$table}: no primary key column found";
		continue;
	}

	$pk = $pk_cols[0];
	if ( ! is_auto_increment_candidate( $pk, count( $pk_cols ) ) ) {
		$skipped[] = "{$table} (non-auto-increment PK)";
		continue;
	}

	$col     = $pk['Field'];
	$type    = $pk['Type'];
	$null    = ( 'NO' === $pk['Null'] ) ? 'NOT NULL' : 'NULL';
	$default = $pk['Default'];
	$extra   = $pk['Extra'];

	$zero_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` = 0" );
	$max_id     = (int) $wpdb->get_var( "SELECT MAX(`{$col}`) FROM `{$table}`" );

	echo "FIX {$table}: pk={$col} type={$type} zero_rows={$zero_count} max_id={$max_id}\n";

	if ( $dry_run ) {
		continue;
	}

	// Remove orphan zero-ID rows (blocks AUTO_INCREMENT).
	if ( $zero_count > 0 ) {
		$deleted = $wpdb->query( "DELETE FROM `{$table}` WHERE `{$col}` = 0" );
		if ( false === $deleted ) {
			$errors[] = "{$table}: failed to delete ID=0 rows - {$wpdb->last_error}";
			continue;
		}
		echo "  deleted {$zero_count} zero-ID row(s)\n";
		if ( 0 === $max_id ) {
			$max_id = 0;
		}
	}

	$next_ai = max( $max_id, 0 ) + 1;

	// Build column definition for MODIFY.
	$default_sql = '';
	if ( null !== $default && '' !== $default ) {
		if ( is_numeric( $default ) ) {
			$default_sql = " DEFAULT {$default}";
		} else {
			$default_sql = $wpdb->prepare( ' DEFAULT %s', $default );
		}
	} elseif ( 'YES' === $pk['Null'] ) {
		$default_sql = ' DEFAULT NULL';
	}

	$alter = "ALTER TABLE `{$table}` MODIFY `{$col}` {$type} {$null}{$default_sql} AUTO_INCREMENT, AUTO_INCREMENT = {$next_ai}";
	$result = $wpdb->query( $alter );

	if ( false === $result ) {
		$errors[] = "{$table}: ALTER failed - {$wpdb->last_error}";
		echo "  ERROR: {$wpdb->last_error}\n";
		continue;
	}

	$fixed[] = "{$table} ({$col}, AI={$next_ai})";
	echo "  OK: AUTO_INCREMENT={$next_ai}\n";
}

echo "\n=== SUMMARY ===\n";
echo 'Fixed: ' . count( $fixed ) . "\n";
foreach ( $fixed as $line ) {
	echo "  {$line}\n";
}
echo 'Skipped (already OK): ' . count( $skipped ) . "\n";
echo 'Errors: ' . count( $errors ) . "\n";
foreach ( $errors as $err ) {
	echo "  {$err}\n";
}

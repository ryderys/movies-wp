<?php
/**
 * Schema install must not bump the version when dbDelta cannot run.
 *
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-series-import-schema-test.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-series-import-schema-test/' );
}

$options = array();
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $options;
		return $options[ $name ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = true ) {
		global $options;
		unset( $autoload );
		$options[ $name ] = $value;
		return true;
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-schema.php';

$failures = 0;
function schema_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

echo "Series import schema install\n";
$options[ Movies_WP_Series_Import_Schema::OPTION ] = 0;
$ok = Movies_WP_Series_Import_Schema::maybe_install();
schema_assert( false === $ok, 'install without wpdb/dbDelta fails closed' );
schema_assert(
	0 === (int) get_option( Movies_WP_Series_Import_Schema::OPTION, 0 ),
	'failed install does not bump the schema version'
);

$options[ Movies_WP_Series_Import_Schema::OPTION ] = Movies_WP_Series_Import_Schema::VERSION;
$ok = Movies_WP_Series_Import_Schema::maybe_install();
schema_assert( true === $ok, 'maybe_install is a no-op when the version is current' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Series import schema tests passed.\n";
exit( $failures ? 1 : 0 );

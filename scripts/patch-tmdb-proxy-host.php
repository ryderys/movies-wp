<?php
/**
 * Point the gitignored Streamit TMDb importers at the current proxy host.
 *
 * The importers fetch TMDb over raw cURL, so WordPress `pre_http_request`
 * rewrites never reach them and the host has to be patched in the source.
 *
 * Usage inside WordPress:
 *   php /tmp/patch-tmdb-proxy-host.php
 *   php /tmp/patch-tmdb-proxy-host.php --host=tmdb.example.com
 */

/**
 * Rewrite the TMDb proxy host in importer source.
 *
 * Kept as a pure transformation so the deployment patch can be tested without
 * loading WordPress or making network requests.
 *
 * @param string   $content Importer source.
 * @param string   $host    Target proxy host.
 * @param string[] $changes Replaced host names.
 * @return string
 */
function streamit_patch_tmdb_proxy_host_content( $content, $host, &$changes = array() ) {
	$replaced = array();

	// Absolute TMDb endpoints: https://<host>/3/... and https://<host>/t/p/...
	$content = preg_replace_callback(
		'#https://([A-Za-z0-9.-]+)(/3/|/t/p/)#',
		function ( $matches ) use ( $host, &$replaced ) {
			if ( $matches[1] === $host ) {
				return $matches[0];
			}
			$replaced[ $matches[1] ] = true;

			return 'https://' . $host . $matches[2];
		},
		$content
	);

	// Worker detection guard: strpos($url, '<host>').
	$content = preg_replace_callback(
		"#(strpos\(\\\$url, ')([A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+)('\))#",
		function ( $matches ) use ( $host, &$replaced ) {
			if ( $matches[2] === $host ) {
				return $matches[0];
			}
			$replaced[ $matches[2] ] = true;

			return $matches[1] . $host . $matches[3];
		},
		$content
	);

	$changes = array_keys( $replaced );

	return $content;
}

/**
 * Resolve the proxy host from WordPress, an explicit argument, or the default.
 *
 * @param string[] $args CLI arguments.
 * @return string
 */
function streamit_patch_tmdb_proxy_host_target( array $args ) {
	foreach ( $args as $arg ) {
		if ( 0 === strpos( $arg, '--host=' ) ) {
			return strtolower( trim( substr( $arg, 7 ) ) );
		}
	}

	if ( defined( 'STREAMIT_TMDB_PROXY_HOST' ) ) {
		return strtolower( trim( STREAMIT_TMDB_PROXY_HOST ) );
	}

	return 'tmdb.asiastars.ir';
}

/**
 * Patch a deployed importer.
 *
 * @param string $file Importer path.
 * @param string $host Target proxy host.
 * @return int Process exit code.
 */
function streamit_patch_tmdb_proxy_host_file( $file, $host ) {
	if ( ! preg_match( '#^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$#', $host ) ) {
		fwrite( STDERR, "Refusing to patch with an invalid host: {$host}\n" );
		return 1;
	}

	if ( ! is_readable( $file ) || ! is_writable( $file ) ) {
		fwrite( STDERR, "Importer is not readable/writable: {$file}\n" );
		return 1;
	}

	$original = file_get_contents( $file );
	if ( false === $original ) {
		fwrite( STDERR, "Could not read importer: {$file}\n" );
		return 1;
	}

	$changes = array();
	$patched = streamit_patch_tmdb_proxy_host_content( $original, $host, $changes );

	if ( false !== strpos( $patched, 'workers.dev' ) ) {
		fwrite( STDERR, "Patch verification failed; workers.dev still present in {$file}\n" );
		return 1;
	}

	if ( $patched === $original ) {
		echo basename( $file ) . ": already on {$host}\n";
		return 0;
	}

	if ( false === file_put_contents( $file, $patched ) ) {
		fwrite( STDERR, "Could not write importer: {$file}\n" );
		return 1;
	}

	echo basename( $file ) . ": {$host} (replaced " . implode( ', ', $changes ) . ")\n";
	return 0;
}

if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
	$args = array_slice( $argv, 1 );

	if ( ! defined( 'STREAMIT_TMDB_PROXY_HOST' ) && is_readable( '/var/www/html/wp-load.php' ) ) {
		require '/var/www/html/wp-load.php';
	}

	$host = streamit_patch_tmdb_proxy_host_target( $args );
	$dir  = getenv( 'STREAMIT_CONTENT_IMPORT_DIR' );
	if ( ! is_string( $dir ) || '' === $dir ) {
		$dir = '/var/www/html/wp-content/plugins/streamit/admin/content-import';
	}

	$status = 0;
	foreach ( array( 'streamit-tmdb_movie-function.php', 'streamit-tmdb_tvshow-function.php' ) as $name ) {
		$status = max( $status, streamit_patch_tmdb_proxy_host_file( $dir . '/' . $name, $host ) );
	}

	exit( $status );
}

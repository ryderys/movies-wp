<?php
/**
 * CLI tests for the Telegram queue schema and queue operations.
 *
 * php -n wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-telegram-queue-test.php
 *
 * Behavioral cases use the in-memory driver, the same pattern as the series
 * import job store. They do not open MySQL. A database section runs only when
 * MOVIES_WP_TEST_DB_HOST is set.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-telegram-queue-test/' );
}
if ( ! defined( 'MOVIES_WP_TELEGRAM_TEST_MEMORY' ) ) {
	define( 'MOVIES_WP_TELEGRAM_TEST_MEMORY', true );
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

class Movies_WP_Telegram_Schema_Test_Wpdb {
	public string $prefix = 'wp_';
	public bool $created  = false;

	public function get_charset_collate(): string {
		return 'DEFAULT CHARSET=utf8mb4';
	}

	public function prepare( string $sql, mixed ...$args ): string {
		unset( $args );
		return $sql;
	}

	public function get_var( string $sql ) {
		unset( $sql );
		return $this->created ? 'wp_movies_wp_telegram_queue' : null;
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $sql ) {
		global $telegram_ddl, $wpdb;
		$telegram_ddl[] = (string) $sql;
		if ( is_object( $wpdb ) && property_exists( $wpdb, 'created' ) ) {
			$wpdb->created = true;
		}
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-schema.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-queue.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-schema.php';

$failures = 0;
function telegram_queue_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function telegram_queue_insert( string $type, int $post_id, string $source ): array {
	return Movies_WP_Telegram_Queue::insert( $type, $post_id, $source );
}

function telegram_queue_claim_at( int $now ): ?array {
	$job = Movies_WP_Telegram_Queue::claim( array( 'now' => $now ) );
	return is_array( $job ) ? $job : null;
}

echo "Telegram queue schema\n";
global $options, $wpdb, $telegram_ddl;
$telegram_ddl = array();
$options[ Movies_WP_Series_Import_Schema::OPTION ] = Movies_WP_Series_Import_Schema::VERSION;
$wpdb = null;
telegram_queue_assert( false === Movies_WP_Telegram_Schema::install(), 'install without wpdb fails closed' );
telegram_queue_assert( false === Movies_WP_Telegram_Schema::install(), 'failed install is repeatable' );
telegram_queue_assert( 0 === (int) get_option( Movies_WP_Telegram_Schema::OPTION, 0 ), 'failed install does not bump the schema version' );

$wpdb = new Movies_WP_Telegram_Schema_Test_Wpdb();
telegram_queue_assert( true === Movies_WP_Telegram_Schema::install(), 'install creates the queue table' );
telegram_queue_assert( true === Movies_WP_Telegram_Schema::install(), 'install is repeatable' );
telegram_queue_assert( 2 === count( $telegram_ddl ), 'repeat install runs dbDelta again' );
$ddl = implode( "\n", $telegram_ddl );
telegram_queue_assert( str_contains( $ddl, 'wp_movies_wp_telegram_queue' ), 'table name uses the plugin prefix' );
telegram_queue_assert( str_contains( $ddl, 'UNIQUE KEY event_key' ), 'event key is unique' );
telegram_queue_assert( str_contains( $ddl, 'KEY status_next (status, next_attempt_at)' ), 'status and next attempt are indexed' );
telegram_queue_assert( str_contains( $ddl, 'KEY claim_expiry (status, claimed_until)' ), 'expired claims are indexed' );
telegram_queue_assert( ! str_contains( $ddl, 'movies_wp_series_import' ), 'queue DDL does not touch series import tables' );

telegram_queue_assert( true === Movies_WP_Telegram_Schema::maybe_install(), 'maybe_install records the schema version' );
telegram_queue_assert( 1 === (int) get_option( Movies_WP_Telegram_Schema::OPTION, 0 ), 'schema version is 1' );
$before = count( $telegram_ddl );
telegram_queue_assert( true === Movies_WP_Telegram_Schema::maybe_install(), 'maybe_install is a no-op when current' );
telegram_queue_assert( count( $telegram_ddl ) === $before, 'current version does not run dbDelta again' );
telegram_queue_assert(
	Movies_WP_Series_Import_Schema::VERSION === (int) get_option( Movies_WP_Series_Import_Schema::OPTION, 0 ),
	'series import schema version is unchanged'
);
telegram_queue_assert( 2 === Movies_WP_Series_Import_Schema::VERSION, 'series import schema constant stays at version 2' );

$bootstrap = (string) file_get_contents( dirname( __DIR__, 2 ) . '/movies-wp-media-automation.php' );
telegram_queue_assert( str_contains( $bootstrap, 'class-movies-wp-telegram-publisher.php' ), 'plugin bootstrap loads the Telegram publisher' );
telegram_queue_assert( str_contains( $bootstrap, "array( 'Movies_WP_Telegram_Schema', 'maybe_install' )" ), 'plugin bootstrap installs the queue table' );
telegram_queue_assert( ! str_contains( $bootstrap, 'Movies_WP_Telegram_Client::deliver' ), 'plugin bootstrap does not send Telegram messages' );
telegram_queue_assert( ! str_contains( $bootstrap, 'movies_wp_telegram_enabled' ), 'plugin bootstrap does not enable publishing' );

echo "Telegram queue operations\n";
Movies_WP_Telegram_Queue::reset_state();
$now    = 1700000000;
$source = 'job-token-1';
$first  = telegram_queue_insert( 'movie', 41, $source );
telegram_queue_assert( true === $first['ok'] && false === $first['duplicate'] && $first['id'] > 0, 'valid event inserts' );
telegram_queue_assert( 64 === strlen( (string) $first['event_key'] ), 'event key is a sha-256 hex digest' );
telegram_queue_assert(
	$first['event_key'] === Movies_WP_Telegram_Queue::event_key( 'movie', 41, $source ),
	'event key is deterministic'
);
telegram_queue_assert(
	$first['event_key'] !== Movies_WP_Telegram_Queue::event_key( 'movie', 41, 'job-token-2' ),
	'a new import token is a different event'
);
$again = telegram_queue_insert( 'movie', 41, $source );
telegram_queue_assert( true === $again['ok'] && true === $again['duplicate'] && $again['id'] === $first['id'], 'duplicate event key does not create a row' );
telegram_queue_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'duplicate insert leaves one row' );

telegram_queue_assert( 'invalid_type' === Movies_WP_Telegram_Queue::insert( 'trailer', 41, $source )['code'], 'unknown content type is rejected' );
telegram_queue_assert( 'invalid_post_id' === Movies_WP_Telegram_Queue::insert( 'movie', 0, $source )['code'], 'zero post ID is rejected' );
telegram_queue_assert( 'invalid_post_id' === Movies_WP_Telegram_Queue::insert( 'episode', -5, $source )['code'], 'negative post ID is rejected' );
telegram_queue_assert( 1 === Movies_WP_Telegram_Queue::count_rows(), 'rejected inserts do not write rows' );

$claimed = telegram_queue_claim_at( $now );
telegram_queue_assert( is_array( $claimed ) && (int) $claimed['id'] === (int) $first['id'], 'pending job can be claimed' );
telegram_queue_assert( null === telegram_queue_claim_at( $now ), 'a second claim does not own the same job' );

$stale_token = (string) $claimed['claim_token'];
$stolen      = telegram_queue_claim_at( $now + Movies_WP_Telegram_Queue::LEASE_SECONDS + 1 );
telegram_queue_assert( is_array( $stolen ) && (string) $stolen['claim_token'] !== $stale_token, 'expired claim can be recovered by a new worker' );
telegram_queue_assert(
	false === Movies_WP_Telegram_Queue::mark_sent( (int) $stolen['id'], $stale_token, '100', array( 'now' => $now ) ),
	'stale claim cannot mark the job sent'
);
$stale_fail = Movies_WP_Telegram_Queue::record_permanent( (int) $stolen['id'], $stale_token, 'nope', array( 'now' => $now ) );
telegram_queue_assert( false === $stale_fail['ok'], 'stale claim cannot mark the job failed' );
telegram_queue_assert(
	true === Movies_WP_Telegram_Queue::mark_sent( (int) $stolen['id'], (string) $stolen['claim_token'], '4242', array( 'now' => $now ) ),
	'current claim can mark the job sent'
);
telegram_queue_assert( null === telegram_queue_claim_at( $now + 10000 ), 'sent job is not claimable' );

$retry_job = telegram_queue_insert( 'series', 77, 'series-token-1' );
$retry_claim = telegram_queue_claim_at( $now );
telegram_queue_assert( is_array( $retry_claim ), 'series job can be claimed' );
$first_fail = Movies_WP_Telegram_Queue::record_retryable(
	(int) $retry_claim['id'],
	(string) $retry_claim['claim_token'],
	'temporary outage',
	array( 'now' => $now, 'backoff_base' => 1000, 'backoff_cap' => 1500 )
);
telegram_queue_assert( true === $first_fail['ok'] && 'retry' === $first_fail['status'] && 1000 === $first_fail['delay_seconds'], 'first retry delay uses the base' );
telegram_queue_assert( null === telegram_queue_claim_at( $now + 999 ), 'retry job waits until next_attempt_at' );
$second_claim = telegram_queue_claim_at( $now + 1000 );
telegram_queue_assert( is_array( $second_claim ), 'retry job is claimable when due' );
$second_fail = Movies_WP_Telegram_Queue::record_retryable(
	(int) $second_claim['id'],
	(string) $second_claim['claim_token'],
	'temporary outage',
	array( 'now' => $now + 1000, 'backoff_base' => 1000, 'backoff_cap' => 1500 )
);
telegram_queue_assert( true === $second_fail['ok'] && 1500 === $second_fail['delay_seconds'], 'later retry delay is capped' );

$limit_job = telegram_queue_insert( 'episode', 88, 'episode-token-1' );
$cursor    = $now;
$limited   = array( 'ok' => false, 'status' => '' );
for ( $attempt = 1; $attempt <= Movies_WP_Telegram_Queue::MAX_ATTEMPTS; $attempt++ ) {
	$owned = telegram_queue_claim_at( $cursor );
	telegram_queue_assert( is_array( $owned ), 'attempt ' . $attempt . ' can be claimed' );
	if ( ! is_array( $owned ) ) {
		break;
	}
	$limited = Movies_WP_Telegram_Queue::record_retryable(
		(int) $owned['id'],
		(string) $owned['claim_token'],
		'still failing',
		array( 'now' => $cursor, 'backoff_base' => 10, 'backoff_cap' => 10 )
	);
	$cursor += 11;
}
telegram_queue_assert( true === $limited['ok'] && 'failed' === $limited['status'], 'attempt limit becomes a permanent failure' );
telegram_queue_assert( null === telegram_queue_claim_at( $cursor + 100 ), 'permanent failure is not claimed again' );

$permanent = telegram_queue_insert( 'movie', 90, 'movie-token-permanent' );
$perm_claim = telegram_queue_claim_at( $now );
$perm = Movies_WP_Telegram_Queue::record_permanent(
	(int) $perm_claim['id'],
	(string) $perm_claim['claim_token'],
	'chat not found',
	array( 'now' => $now )
);
$ambiguous = telegram_queue_insert( 'movie', 91, 'movie-token-ambiguous' );
$amb_claim = telegram_queue_claim_at( $now );
$amb = Movies_WP_Telegram_Queue::record_ambiguous(
	(int) $amb_claim['id'],
	(string) $amb_claim['claim_token'],
	"timeout after send bot123456789:ABCDEFGHIJKLMNOP Authorization: Bearer secret-value",
	array( 'now' => $now )
);
telegram_queue_assert( 'failed' === $perm['status'] && 'ambiguous' === $amb['status'], 'permanent and ambiguous outcomes are distinct' );
$later = telegram_queue_claim_at( $now + 50000 );
telegram_queue_assert(
	! is_array( $later ) || (int) $later['id'] !== (int) $amb_claim['id'],
	'ambiguous job is not retried automatically'
);

$expired = telegram_queue_insert( 'episode', 92, 'episode-token-expired' );
$expired_claim = telegram_queue_claim_at( $now );
telegram_queue_assert( is_array( $expired_claim ), 'lease job can be claimed' );
$released = Movies_WP_Telegram_Queue::release_expired( array( 'now' => $now + Movies_WP_Telegram_Queue::LEASE_SECONDS + 5 ) );
telegram_queue_assert( 1 === $released, 'release_expired recovers one expired claim' );
$reclaimed = telegram_queue_claim_at( $now + Movies_WP_Telegram_Queue::LEASE_SECONDS + 5 );
telegram_queue_assert(
	is_array( $reclaimed ) && (int) $reclaimed['id'] === (int) $expired_claim['id'],
	'released job can be claimed again'
);

$secret = '123456789:ABCDEFGHIJKLMNOPQRST';
for ( $i = 0; $i < 3; $i++ ) {
	$row   = telegram_queue_insert( 'series', 100 + $i, 'series-fail-' . $i . '-token' );
	$owned = telegram_queue_claim_at( $now + $i );
	Movies_WP_Telegram_Queue::record_permanent(
		(int) $owned['id'],
		(string) $owned['claim_token'],
		'api failed token=' . $secret,
		array( 'now' => $now + $i )
	);
	unset( $row );
}
$one = Movies_WP_Telegram_Queue::recent_failures( 1 );
$all = Movies_WP_Telegram_Queue::recent_failures( 1000 );
telegram_queue_assert( 1 === count( $one ), 'failure reads honor the requested bound' );
telegram_queue_assert( count( $all ) <= Movies_WP_Telegram_Queue::FAILURE_MAX, 'failure reads honor the hard cap' );
telegram_queue_assert( count( $all ) >= 3, 'recent failures include the recorded jobs' );
$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $all ) : json_encode( $all );
$blob    = is_string( $encoded ) ? $encoded : '';
telegram_queue_assert( is_string( $blob ) && ! str_contains( $blob, $secret ) && ! str_contains( $blob, 'secret-value' ), 'stored errors are sanitized' );

$counts = Movies_WP_Telegram_Queue::counts();
telegram_queue_assert( ( $counts['sent'] ?? 0 ) >= 1 && ( $counts['failed'] ?? 0 ) >= 1 && ( $counts['ambiguous'] ?? 0 ) === 1, 'counts separate sent, failed, and ambiguous' );

echo "Telegram queue database\n";
if ( ! is_string( getenv( 'MOVIES_WP_TEST_DB_HOST' ) ) || '' === getenv( 'MOVIES_WP_TEST_DB_HOST' ) ) {
	echo "  skip  MySQL tests were not run (MOVIES_WP_TEST_DB_HOST is unset; Docker is not assumed)\n";
} else {
	$mysql_test = __DIR__ . '/class-movies-wp-telegram-queue-mysql-test.php';
	$php_bin    = getenv( 'MOVIES_WP_TEST_PHP' );
	$php_bin    = is_string( $php_bin ) && '' !== $php_bin ? $php_bin : PHP_BINARY;
	$command    = escapeshellarg( $php_bin ) . ' ' . escapeshellarg( $mysql_test );
	passthru( $command, $mysql_code );
	if ( 0 !== (int) $mysql_code ) {
		++$failures;
		echo "  FAIL  MySQL integration child process exited {$mysql_code}\n";
	}
}

Movies_WP_Telegram_Queue::reset_state();

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Telegram queue tests passed.\n";
exit( $failures ? 1 : 0 );

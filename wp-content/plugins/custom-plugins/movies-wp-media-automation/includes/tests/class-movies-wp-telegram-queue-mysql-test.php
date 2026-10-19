<?php
/**
 * MySQL integration tests for the Telegram queue.
 *
 * Does not use MOVIES_WP_TELEGRAM_TEST_MEMORY. Boots WordPress with SHORTINIT
 * and a real $wpdb against InnoDB.
 *
 * Example (Docker Compose):
 *   MSYS_NO_PATHCONV=1 docker exec -e MOVIES_WP_TEST_DB_HOST=db movies-wp-wordpress-1 php \
 *     //var/www/html/wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-telegram-queue-mysql-test.php
 *
 * Requires MOVIES_WP_TEST_DB_HOST to be set so this is never run by accident.
 */

declare(strict_types=1);

if ( ! is_string( getenv( 'MOVIES_WP_TEST_DB_HOST' ) ) || '' === getenv( 'MOVIES_WP_TEST_DB_HOST' ) ) {
	fwrite( STDERR, "MOVIES_WP_TEST_DB_HOST is unset; refusing to open MySQL.\n" );
	exit( 2 );
}

if ( defined( 'MOVIES_WP_TELEGRAM_TEST_MEMORY' ) && MOVIES_WP_TELEGRAM_TEST_MEMORY ) {
	fwrite( STDERR, "MOVIES_WP_TELEGRAM_TEST_MEMORY is on; MySQL path cannot run in this process.\n" );
	exit( 2 );
}

$wp_load = getenv( 'MOVIES_WP_TEST_WP_LOAD' );
if ( ! is_string( $wp_load ) || '' === $wp_load ) {
	$wp_load = '/var/www/html/wp-load.php';
}
if ( ! is_readable( $wp_load ) ) {
	fwrite( STDERR, "WordPress bootstrap not readable: {$wp_load}\n" );
	exit( 2 );
}

if ( ! defined( 'SHORTINIT' ) ) {
	define( 'SHORTINIT', true );
}
require_once $wp_load;

if ( ! defined( 'ABSPATH' ) || ! defined( 'DB_NAME' ) ) {
	fwrite( STDERR, "WordPress did not expose database constants.\n" );
	exit( 2 );
}

require_once ABSPATH . 'wp-includes/compat.php';
require_once ABSPATH . 'wp-includes/class-wpdb.php';

global $table_prefix, $wpdb, $options;
$options = array();
$wpdb    = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$wpdb->set_prefix( isset( $table_prefix ) && is_string( $table_prefix ) && '' !== $table_prefix ? $table_prefix : 'wp_' );

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $options, $wpdb;
		if ( array_key_exists( $name, $options ) ) {
			return $options[ $name ];
		}
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", (string) $name ) );
		if ( null === $value ) {
			return $default;
		}
		$options[ $name ] = maybe_unserialize( $value );
		return $options[ $name ];
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		global $options, $wpdb;
		unset( $autoload );
		$options[ $name ] = $value;
		$serialized       = maybe_serialize( $value );
		$exists           = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", (string) $name ) );
		if ( $exists ) {
			$wpdb->update( $wpdb->options, array( 'option_value' => $serialized ), array( 'option_name' => (string) $name ) );
		} else {
			$wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => (string) $name,
					'option_value' => $serialized,
					'autoload'     => 'no',
				)
			);
		}
		return true;
	}
}
if ( ! function_exists( 'maybe_serialize' ) ) {
	function maybe_serialize( $data ) {
		return ( is_array( $data ) || is_object( $data ) ) ? serialize( $data ) : $data;
	}
}
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $data ) {
		if ( ! is_string( $data ) ) {
			return $data;
		}
		$value = @unserialize( $data );
		return false === $value && 'b:0;' !== $data ? $data : $value;
	}
}
require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-schema.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-queue.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-series-import-schema.php';

$failures    = 0;
$created_ids = array();

function telegram_mysql_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

function telegram_mysql_cleanup(): void {
	global $wpdb, $created_ids;
	if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || array() === $created_ids ) {
		return;
	}
	$table = Movies_WP_Telegram_Schema::table_name();
	$ids   = array_values(
		array_filter(
			array_map( 'intval', $created_ids ),
			static function ( $id ) {
				return $id > 0;
			}
		)
	);
	if ( array() === $ids ) {
		return;
	}
	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids ) );
}

/**
 * Create the queue table with the same DDL the schema class installs.
 * Avoids loading wp-admin/upgrade.php under SHORTINIT.
 */
function telegram_mysql_ensure_table(): bool {
	global $wpdb;
	$table   = Movies_WP_Telegram_Schema::table_name();
	$charset = $wpdb->get_charset_collate();
	$sql     = "CREATE TABLE IF NOT EXISTS {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		event_key CHAR(64) NOT NULL,
		content_type VARCHAR(16) NOT NULL,
		post_id BIGINT UNSIGNED NOT NULL,
		status VARCHAR(20) NOT NULL,
		attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
		next_attempt_at DATETIME NULL,
		last_attempt_at DATETIME NULL,
		last_error VARCHAR(500) NULL,
		telegram_message_id VARCHAR(32) NULL,
		sent_at DATETIME NULL,
		claim_token VARCHAR(64) NULL,
		claimed_until DATETIME NULL,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY event_key (event_key),
		KEY status_next (status, next_attempt_at),
		KEY claim_expiry (status, claimed_until)
	) {$charset}";
	$wpdb->query( $sql );
	return Movies_WP_Telegram_Schema::tables_exist();
}

echo "Telegram queue MySQL\n";

$series_before = (int) get_option( Movies_WP_Series_Import_Schema::OPTION, 0 );

telegram_mysql_assert( true === telegram_mysql_ensure_table(), 'queue table exists on MySQL' );
update_option( Movies_WP_Telegram_Schema::OPTION, Movies_WP_Telegram_Schema::VERSION, true );
telegram_mysql_assert( true === Movies_WP_Telegram_Schema::maybe_install(), 'maybe_install is a no-op when current' );
telegram_mysql_assert( $series_before === (int) get_option( Movies_WP_Series_Import_Schema::OPTION, 0 ), 'series schema version is unchanged' );
telegram_mysql_assert( Movies_WP_Telegram_Schema::VERSION === (int) get_option( Movies_WP_Telegram_Schema::OPTION, 0 ), 'telegram schema version is recorded' );

$now    = 1760209000;
$source = 'mysql-job-token-1';
$first  = Movies_WP_Telegram_Queue::insert( 'movie', 41, $source, array( 'now' => $now ) );
telegram_mysql_assert( ! empty( $first['ok'] ) && empty( $first['duplicate'] ), 'first insert writes a row' );
$created_ids[] = (int) ( $first['id'] ?? 0 );

$dup = Movies_WP_Telegram_Queue::insert( 'movie', 41, $source, array( 'now' => $now ) );
telegram_mysql_assert( ! empty( $dup['ok'] ) && ! empty( $dup['duplicate'] ), 'duplicate event_key is a no-op success' );
telegram_mysql_assert( (int) $first['id'] === (int) $dup['id'], 'duplicate insert returns the same id' );

$second = Movies_WP_Telegram_Queue::insert( 'movie', 41, 'mysql-job-token-2', array( 'now' => $now ) );
telegram_mysql_assert( ! empty( $second['ok'] ) && empty( $second['duplicate'] ), 'a new import token creates another row' );
$created_ids[] = (int) ( $second['id'] ?? 0 );

$claim_a = Movies_WP_Telegram_Queue::claim( array( 'now' => $now ) );
$claim_b = Movies_WP_Telegram_Queue::claim( array( 'now' => $now ) );
telegram_mysql_assert( is_array( $claim_a ) && is_array( $claim_b ), 'two due rows can be claimed' );
telegram_mysql_assert( (int) $claim_a['id'] !== (int) $claim_b['id'], 'atomic claim does not hand the same row to two workers' );
telegram_mysql_assert( 'claimed' === (string) $claim_a['status'] && 'claimed' === (string) $claim_b['status'], 'claimed rows report claimed status' );

$sent = Movies_WP_Telegram_Queue::mark_sent( (int) $claim_a['id'], (string) $claim_a['claim_token'], '9001', array( 'now' => $now ) );
telegram_mysql_assert( true === $sent, 'owned claim can mark the row sent' );
$stale = Movies_WP_Telegram_Queue::mark_sent( (int) $claim_a['id'], (string) $claim_a['claim_token'], '9002', array( 'now' => $now ) );
telegram_mysql_assert( false === $stale, 'a completed claim cannot mark sent again' );

$retry = Movies_WP_Telegram_Queue::record_retryable(
	(int) $claim_b['id'],
	(string) $claim_b['claim_token'],
	'retry later',
	array(
		'now'          => $now,
		'backoff_base' => 60,
		'backoff_cap'  => 3600,
	)
);
telegram_mysql_assert( ! empty( $retry['ok'] ) && 'retry' === (string) ( $retry['status'] ?? '' ), 'owned claim can record a retry' );
$early = Movies_WP_Telegram_Queue::claim( array( 'now' => $now + 10 ) );
telegram_mysql_assert( null === $early, 'a retry is not claimable before next_attempt_at' );
$due_retry = Movies_WP_Telegram_Queue::claim( array( 'now' => $now + 60 ) );
telegram_mysql_assert( is_array( $due_retry ) && (int) $due_retry['id'] === (int) $claim_b['id'], 'a due retry can be claimed again' );

$permanent = Movies_WP_Telegram_Queue::record_permanent(
	(int) $due_retry['id'],
	(string) $due_retry['claim_token'],
	'chat gone',
	array( 'now' => $now + 60 )
);
telegram_mysql_assert( ! empty( $permanent['ok'] ), 'owned claim can record a permanent failure' );

$failed_id  = (int) $due_retry['id'];
$amb_insert = Movies_WP_Telegram_Queue::insert( 'episode', 80, 'mysql-job-token-ambiguous', array( 'now' => $now + 60 ) );
$created_ids[] = (int) ( $amb_insert['id'] ?? 0 );
$amb_claim = Movies_WP_Telegram_Queue::claim( array( 'now' => $now + 60 ) );
telegram_mysql_assert( is_array( $amb_claim ), 'another row can be claimed for ambiguous' );
$ambiguous = Movies_WP_Telegram_Queue::record_ambiguous(
	(int) $amb_claim['id'],
	(string) $amb_claim['claim_token'],
	'timed out',
	array( 'now' => $now + 60 )
);
telegram_mysql_assert( ! empty( $ambiguous['ok'] ), 'owned claim can record ambiguous' );
$no_amb = Movies_WP_Telegram_Queue::claim( array( 'now' => $now + 100000 ) );
telegram_mysql_assert(
	! is_array( $no_amb ) || (int) $no_amb['id'] !== (int) $amb_claim['id'],
	'an ambiguous row is never claimable again'
);
telegram_mysql_assert( false === Movies_WP_Telegram_Queue::requeue_failed( (int) $amb_claim['id'], array( 'now' => $now + 60 ) ), 'ambiguous rows cannot be manually requeued' );

$requeued = Movies_WP_Telegram_Queue::requeue_failed( $failed_id, array( 'now' => $now + 60 ) );
telegram_mysql_assert( true === $requeued, 'a failed row can be manually requeued' );
$reclaimed = Movies_WP_Telegram_Queue::claim( array( 'now' => $now + 60 ) );
telegram_mysql_assert( is_array( $reclaimed ) && (int) $reclaimed['id'] === $failed_id, 'a requeued failed row is claimable' );
Movies_WP_Telegram_Queue::record_permanent(
	(int) $reclaimed['id'],
	(string) $reclaimed['claim_token'],
	'still failed',
	array( 'now' => $now + 60 )
);

$held = Movies_WP_Telegram_Queue::insert( 'series', 70, 'mysql-job-token-held', array( 'now' => $now + 60 ) );
$created_ids[] = (int) ( $held['id'] ?? 0 );
$held_claim = Movies_WP_Telegram_Queue::claim( array( 'now' => $now + 60 ) );
telegram_mysql_assert( is_array( $held_claim ), 'a held claim can be taken' );
$blocked = Movies_WP_Telegram_Queue::claim( array( 'now' => $now + 60 ) );
telegram_mysql_assert(
	! is_array( $blocked ) || (int) $blocked['id'] !== (int) $held_claim['id'],
	'an active claim is not stolen by a second worker'
);
$released = Movies_WP_Telegram_Queue::release_expired( array( 'now' => $now + 60 + Movies_WP_Telegram_Queue::LEASE_SECONDS + 1 ) );
telegram_mysql_assert( $released >= 1, 'release_expired recovers the expired claim' );
$stolen = Movies_WP_Telegram_Queue::claim( array( 'now' => $now + 60 + Movies_WP_Telegram_Queue::LEASE_SECONDS + 1 ) );
telegram_mysql_assert( is_array( $stolen ) && (int) $stolen['id'] === (int) $held_claim['id'], 'an expired claim can be recovered' );
Movies_WP_Telegram_Queue::mark_sent( (int) $stolen['id'], (string) $stolen['claim_token'], '9003', array( 'now' => $now + 200 ) );

$movie_only   = Movies_WP_Telegram_Queue::insert( 'movie', 43, 'mysql-job-token-movie', array( 'now' => $now + 200 ) );
$episode_only = Movies_WP_Telegram_Queue::insert( 'episode', 81, 'mysql-job-token-episode', array( 'now' => $now + 200 ) );
$created_ids[] = (int) ( $movie_only['id'] ?? 0 );
$created_ids[] = (int) ( $episode_only['id'] ?? 0 );
$typed = Movies_WP_Telegram_Queue::claim(
	array(
		'now'           => $now + 200,
		'content_types' => array( 'episode' ),
	)
);
telegram_mysql_assert( is_array( $typed ) && 'episode' === (string) ( $typed['content_type'] ?? '' ), 'content_types filter claims only the enabled type' );
Movies_WP_Telegram_Queue::mark_sent( (int) $typed['id'], (string) $typed['claim_token'], '9004', array( 'now' => $now + 200 ) );
$movie_claim = Movies_WP_Telegram_Queue::claim( array( 'now' => $now + 200 ) );
telegram_mysql_assert( is_array( $movie_claim ) && 'movie' === (string) ( $movie_claim['content_type'] ?? '' ), 'the filtered-out movie remains claimable later' );
Movies_WP_Telegram_Queue::mark_sent( (int) $movie_claim['id'], (string) $movie_claim['claim_token'], '9005', array( 'now' => $now + 200 ) );

$ready = Movies_WP_Telegram_Queue::next_ready_at( array( 'now' => $now + 200 ) );
telegram_mysql_assert( null === $ready, 'next_ready_at is null when nothing is due' );

telegram_mysql_cleanup();

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Telegram queue MySQL tests passed.\n";
exit( $failures > 0 ? 1 : 0 );

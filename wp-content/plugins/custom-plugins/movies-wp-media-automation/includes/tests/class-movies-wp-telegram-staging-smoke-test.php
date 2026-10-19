<?php
/**
 * Staging smoke for Telegram publishing (switches stay off).
 *
 * Boots WordPress with SHORTINIT + real $wpdb. Does not send Telegram
 * messages and does not enable publishing switches.
 *
 *   MSYS_NO_PATHCONV=1 docker exec -e MOVIES_WP_TEST_DB_HOST=db movies-wp-wordpress-1 php \
 *     //var/www/html/wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-telegram-staging-smoke-test.php
 */

declare(strict_types=1);

if ( ! is_string( getenv( 'MOVIES_WP_TEST_DB_HOST' ) ) || '' === getenv( 'MOVIES_WP_TEST_DB_HOST' ) ) {
	fwrite( STDERR, "MOVIES_WP_TEST_DB_HOST is unset; refusing staging smoke against an unknown DB.\n" );
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
if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text ) {
		return esc_html( $text );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		$value = strtolower( (string) $value );
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		unset( $action );
		return 'smoke-nonce';
	}
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		unset( $referer );
		$field = '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';
		if ( $echo ) {
			echo $field;
		}
		return $field;
	}
}
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text ) {
		echo '<button type="submit">' . esc_html( $text ) . '</button>';
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		unset( $hook, $callback, $priority, $args );
	}
}
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent, $page_title, $menu_title, $capability, $slug, $callback = null ) {
		global $telegram_smoke_menus;
		$telegram_smoke_menus[] = compact( 'parent', 'capability', 'slug' );
		unset( $page_title, $menu_title, $callback );
		return 'telegram-smoke-hook';
	}
}
if ( ! class_exists( 'Movies_WP_Media_Admin' ) ) {
	class Movies_WP_Media_Admin {
		const SLUG = 'movies-wp-media-automation';
	}
}

$plugin_dir = dirname( __DIR__, 2 );
require_once $plugin_dir . '/includes/class-movies-wp-telegram-config.php';
require_once $plugin_dir . '/includes/class-movies-wp-telegram-schema.php';
require_once $plugin_dir . '/includes/class-movies-wp-telegram-queue.php';
require_once $plugin_dir . '/includes/class-movies-wp-telegram-formatter.php';
require_once $plugin_dir . '/includes/class-movies-wp-telegram-client.php';
require_once $plugin_dir . '/includes/class-movies-wp-telegram-snapshot-provider.php';
require_once $plugin_dir . '/includes/class-movies-wp-telegram-publisher.php';
require_once $plugin_dir . '/includes/class-movies-wp-telegram-worker.php';
require_once $plugin_dir . '/includes/class-movies-wp-telegram-admin.php';

$failures = 0;
$telegram_smoke_menus = array();

function telegram_smoke_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

echo "Telegram staging smoke\n";

$active_raw = get_option( 'active_plugins', array() );
$active     = is_array( $active_raw ) ? $active_raw : array();
$plugin_rel = 'custom-plugins/movies-wp-media-automation/movies-wp-media-automation.php';
$is_active  = in_array( $plugin_rel, $active, true );
foreach ( $active as $entry ) {
	if ( is_string( $entry ) && str_contains( $entry, 'movies-wp-media-automation' ) ) {
		$is_active  = true;
		$plugin_rel = $entry;
		break;
	}
}
if ( ! $is_active ) {
	$active[] = $plugin_rel;
	update_option( 'active_plugins', array_values( array_unique( $active ) ) );
	telegram_smoke_assert( true, 'activated movies-wp-media-automation for local staging' );
} else {
	telegram_smoke_assert( true, 'movies-wp-media-automation is already active' );
}

// Keep publishing off for the smoke.
update_option( Movies_WP_Telegram_Config::OPT_GLOBAL, '0', false );
update_option( Movies_WP_Telegram_Config::OPT_MOVIE, '0', false );
update_option( Movies_WP_Telegram_Config::OPT_SERIES, '0', false );
update_option( Movies_WP_Telegram_Config::OPT_EPISODE, '0', false );
update_option( Movies_WP_Telegram_Config::OPT_PAUSED, '0', false );

telegram_smoke_assert( false === Movies_WP_Telegram_Config::global_enabled(), 'global publishing stays off' );
telegram_smoke_assert( false === Movies_WP_Telegram_Config::movie_enabled(), 'movie publishing stays off' );
telegram_smoke_assert( false === Movies_WP_Telegram_Config::series_enabled(), 'series publishing stays off' );
telegram_smoke_assert( false === Movies_WP_Telegram_Config::episode_enabled(), 'episode publishing stays off' );
telegram_smoke_assert( false === Movies_WP_Telegram_Config::paused(), 'delivery is not paused by default' );
telegram_smoke_assert( false === Movies_WP_Telegram_Config::publishing_allowed( 'movie' ), 'publishing_allowed(movie) is false' );
telegram_smoke_assert( false === Movies_WP_Telegram_Config::publishing_allowed( 'series' ), 'publishing_allowed(series) is false' );
telegram_smoke_assert( false === Movies_WP_Telegram_Config::publishing_allowed( 'episode' ), 'publishing_allowed(episode) is false' );

$table = Movies_WP_Telegram_Schema::table_name();
$charset = $wpdb->get_charset_collate();
$wpdb->query(
	"CREATE TABLE IF NOT EXISTS {$table} (
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
	) {$charset}"
);
update_option( Movies_WP_Telegram_Schema::OPTION, Movies_WP_Telegram_Schema::VERSION, true );
telegram_smoke_assert( true === Movies_WP_Telegram_Schema::tables_exist(), 'queue table exists in staging DB' );
telegram_smoke_assert( true === Movies_WP_Telegram_Schema::maybe_install(), 'maybe_install is safe when current' );

Movies_WP_Telegram_Admin::register_menu();
telegram_smoke_assert(
	isset( $telegram_smoke_menus[0]['slug'] ) && Movies_WP_Telegram_Admin::SLUG === $telegram_smoke_menus[0]['slug'],
	'Telegram submenu is registered'
);
telegram_smoke_assert(
	'manage_options' === (string) ( $telegram_smoke_menus[0]['capability'] ?? '' ),
	'Telegram submenu requires manage_options'
);

$state = Movies_WP_Telegram_Admin::page_state();
telegram_smoke_assert( ! array_key_exists( 'token', $state ) && ! array_key_exists( 'chat_id', $state ), 'page state has no credential values' );
telegram_smoke_assert( is_array( $state['counts'] ?? null ), 'page state includes queue counts' );
$markup = Movies_WP_Telegram_Admin::render_markup( $state );
telegram_smoke_assert( str_contains( $markup, 'Telegram' ), 'admin markup renders' );
telegram_smoke_assert( str_contains( $markup, 'Pause delivery' ) || str_contains( $markup, 'Resume delivery' ), 'pause control is present' );
telegram_smoke_assert( str_contains( $markup, 'Send test message' ), 'test-post control is present' );
telegram_smoke_assert( ! str_contains( $markup, 'MOVIES_WP_TELEGRAM_BOT_TOKEN' ), 'markup does not name the token env key as a value' );

$http_calls = 0;
$worker = Movies_WP_Telegram_Worker::run(
	array(
		'now'     => time(),
		'deliver' => static function () use ( &$http_calls ) {
			++$http_calls;
			return array( 'outcome' => 'sent', 'message_id' => '1', 'error' => '' );
		},
	)
);
telegram_smoke_assert( 0 === (int) ( $worker['processed'] ?? -1 ) && 0 === $http_calls, 'worker is a no-op while publishing is off' );

$scheduled = Movies_WP_Telegram_Worker::ensure_scheduled(
	array(
		'now'             => time(),
		'schedule_action' => static function () {
			throw new RuntimeException( 'must not schedule while publishing is off' );
		},
	)
);
telegram_smoke_assert( false === $scheduled, 'worker is not scheduled while publishing is off' );

$bootstrap = (string) file_get_contents( $plugin_dir . '/movies-wp-media-automation.php' );
telegram_smoke_assert( str_contains( $bootstrap, 'Movies_WP_Telegram_Admin::init' ), 'bootstrap registers Telegram admin' );
telegram_smoke_assert( str_contains( $bootstrap, 'Movies_WP_Telegram_Worker::init' ), 'bootstrap registers Telegram worker' );
telegram_smoke_assert( ! str_contains( $bootstrap, 'register_activation_hook' ), 'bootstrap does not schedule from activation' );
telegram_smoke_assert( ! str_contains( $bootstrap, "update_option( 'movies_wp_telegram_enabled'" ), 'bootstrap does not enable publishing' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Telegram staging smoke checks passed.\n";
echo "Open wp-admin → Content Automation → Telegram. Switches are off; do not enable them yet.\n";
exit( $failures > 0 ? 1 : 0 );

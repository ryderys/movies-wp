<?php
/**
 * CLI tests for Movies_WP_Telegram_Config.
 *
 * php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-telegram-config-test.php
 *
 * Uses an in-memory option store and restores environment variables.
 * Does not load the plugin bootstrap and does not call Telegram.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-telegram-config-test/' );
}

$options = array();
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $options;
		return $options[ $name ] ?? $default;
	}
}

require_once dirname( __DIR__ ) . '/class-movies-wp-telegram-config.php';

$failures = 0;
function telegram_config_assert( bool $ok, string $label ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok  {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL  {$label}\n";
}

/**
 * @return array<string, string|false>
 */
function telegram_config_env_snapshot(): array {
	return array(
		Movies_WP_Telegram_Config::TOKEN_KEY => getenv( Movies_WP_Telegram_Config::TOKEN_KEY ),
		Movies_WP_Telegram_Config::CHAT_KEY  => getenv( Movies_WP_Telegram_Config::CHAT_KEY ),
	);
}

/**
 * @param array<string, string|false> $snapshot
 */
function telegram_config_env_restore( array $snapshot ): void {
	foreach ( $snapshot as $key => $value ) {
		if ( false === $value ) {
			putenv( $key );
			continue;
		}
		putenv( $key . '=' . $value );
	}
}

function telegram_config_clear_secrets(): void {
	putenv( Movies_WP_Telegram_Config::TOKEN_KEY );
	putenv( Movies_WP_Telegram_Config::CHAT_KEY );
}

$env_snapshot = telegram_config_env_snapshot();
$token_locked = defined( Movies_WP_Telegram_Config::TOKEN_KEY );
$chat_locked  = defined( Movies_WP_Telegram_Config::CHAT_KEY );

try {
	global $options;
	$options = array();
	telegram_config_clear_secrets();

	echo "Telegram config\n";

	telegram_config_assert( false === Movies_WP_Telegram_Config::global_enabled(), 'missing global switch is false' );
	telegram_config_assert( false === Movies_WP_Telegram_Config::movie_enabled(), 'missing movie switch is false' );
	telegram_config_assert( false === Movies_WP_Telegram_Config::series_enabled(), 'missing series switch is false' );
	telegram_config_assert( false === Movies_WP_Telegram_Config::episode_enabled(), 'missing episode switch is false' );

	$options[ Movies_WP_Telegram_Config::OPT_GLOBAL ] = true;
	$options[ Movies_WP_Telegram_Config::OPT_MOVIE ]  = true;
	if ( ! $token_locked ) {
		telegram_config_assert(
			false === Movies_WP_Telegram_Config::publishing_allowed( 'movie' ),
			'enabled movie switch with a missing token does not publish'
		);
		putenv( Movies_WP_Telegram_Config::TOKEN_KEY . '=test-token' );
	} else {
		echo "  skip  missing token (token constant already defined)\n";
	}

	if ( ! $chat_locked ) {
		telegram_config_assert(
			false === Movies_WP_Telegram_Config::publishing_allowed( 'movie' ),
			'missing destination does not publish'
		);
		putenv( Movies_WP_Telegram_Config::CHAT_KEY . '=-1001234567890' );
	} else {
		echo "  skip  missing destination (chat constant already defined)\n";
	}

	telegram_config_assert(
		false === Movies_WP_Telegram_Config::publishing_allowed( 'trailer' ),
		'unknown content type does not publish'
	);
	telegram_config_assert(
		false === Movies_WP_Telegram_Config::publishing_allowed( '' ),
		'empty content type does not publish'
	);
	telegram_config_assert(
		false === Movies_WP_Telegram_Config::publishing_allowed( null ),
		'non-string content type does not publish'
	);

	if ( ! $chat_locked ) {
		foreach ( array( '123', '-100', '-100abc', '@abcd', 'https://t.me/channel' ) as $invalid ) {
			putenv( Movies_WP_Telegram_Config::CHAT_KEY . '=' . $invalid );
			telegram_config_assert(
				false === Movies_WP_Telegram_Config::publishing_allowed( 'movie' ),
				'invalid destination does not publish'
			);
		}
		putenv( Movies_WP_Telegram_Config::CHAT_KEY . '=-1001234567890' );
	} else {
		echo "  skip  invalid destination formats (chat constant already defined)\n";
	}

	if ( ! $token_locked && ! $chat_locked ) {
		$options[ Movies_WP_Telegram_Config::OPT_GLOBAL ] = false;
		$options[ Movies_WP_Telegram_Config::OPT_MOVIE ]  = true;
		telegram_config_assert(
			false === Movies_WP_Telegram_Config::publishing_allowed( 'movie' ),
			'valid credentials do not override a disabled global switch'
		);
		$options[ Movies_WP_Telegram_Config::OPT_GLOBAL ] = true;
		$options[ Movies_WP_Telegram_Config::OPT_MOVIE ]  = false;
		telegram_config_assert(
			false === Movies_WP_Telegram_Config::publishing_allowed( 'movie' ),
			'valid credentials do not override a disabled movie switch'
		);
		$options[ Movies_WP_Telegram_Config::OPT_MOVIE ]   = true;
		$options[ Movies_WP_Telegram_Config::OPT_SERIES ]  = false;
		$options[ Movies_WP_Telegram_Config::OPT_EPISODE ] = false;
		telegram_config_assert(
			true === Movies_WP_Telegram_Config::publishing_allowed( 'movie' ),
			'valid token, destination, and enabled movie switch allow publishing'
		);
		telegram_config_assert(
			false === Movies_WP_Telegram_Config::publishing_allowed( 'series' ),
			'enabled movie switch does not allow series publishing'
		);
	} else {
		echo "  skip  disabled-switch override (credential constant already defined)\n";
	}

	if ( ! $token_locked ) {
		putenv( Movies_WP_Telegram_Config::TOKEN_KEY . '=env-token-value' );
		define( 'MOVIES_WP_TELEGRAM_BOT_TOKEN', 'const-token-value' );
		telegram_config_assert(
			'const-token-value' === Movies_WP_Telegram_Config::token(),
			'non-empty token constant takes precedence over the environment'
		);
	} else {
		echo "  skip  token constant precedence (already defined)\n";
	}

	if ( ! $chat_locked ) {
		putenv( Movies_WP_Telegram_Config::CHAT_KEY . '=@envname' );
		define( 'MOVIES_WP_TELEGRAM_CHAT_ID', '@constname' );
		telegram_config_assert(
			'@constname' === Movies_WP_Telegram_Config::chat_id(),
			'non-empty chat constant takes precedence over the environment'
		);
	} else {
		echo "  skip  chat constant precedence (already defined)\n";
	}
} finally {
	telegram_config_env_restore( $env_snapshot );
	$options = array();
}

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll Telegram config tests passed.\n";
exit( $failures ? 1 : 0 );

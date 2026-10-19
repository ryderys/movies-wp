<?php
/**
 * Telegram publishing configuration.
 *
 * Secrets come from a PHP constant, then the environment. Switches are
 * WordPress options and default to off. This class does not log secrets,
 * store credentials, or call Telegram.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Telegram_Config {

	const TOKEN_KEY   = 'MOVIES_WP_TELEGRAM_BOT_TOKEN';
	const CHAT_KEY    = 'MOVIES_WP_TELEGRAM_CHAT_ID';
	const OPT_GLOBAL  = 'movies_wp_telegram_enabled';
	const OPT_MOVIE   = 'movies_wp_telegram_movies';
	const OPT_SERIES  = 'movies_wp_telegram_series';
	const OPT_EPISODE = 'movies_wp_telegram_episodes';
	const OPT_PAUSED  = 'movies_wp_telegram_paused';

	/**
	 * Bot token. Empty string means unconfigured.
	 *
	 * @return string
	 */
	public static function token() {
		return self::secret( self::TOKEN_KEY );
	}

	/**
	 * Destination chat ID. Empty string means unconfigured.
	 *
	 * @return string
	 */
	public static function chat_id() {
		return self::secret( self::CHAT_KEY );
	}

	/**
	 * @return bool
	 */
	public static function global_enabled() {
		return self::flag( self::OPT_GLOBAL );
	}

	/**
	 * @return bool
	 */
	public static function movie_enabled() {
		return self::flag( self::OPT_MOVIE );
	}

	/**
	 * @return bool
	 */
	public static function series_enabled() {
		return self::flag( self::OPT_SERIES );
	}

	/**
	 * @return bool
	 */
	public static function episode_enabled() {
		return self::flag( self::OPT_EPISODE );
	}

	/**
	 * Pause stops delivery only. It does not change import behavior.
	 *
	 * @return bool
	 */
	public static function paused() {
		return self::flag( self::OPT_PAUSED );
	}

	/**
	 * Whether a token and destination are present. The values are not returned.
	 *
	 * @return bool
	 */
	public static function credentials_configured() {
		try {
			return '' !== self::token() && self::chat_is_valid( self::chat_id() );
		} catch ( \Throwable $e ) {
			unset( $e );
			return false;
		}
	}

	/**
	 * True only when global and type switches are on and credentials are usable.
	 *
	 * @param mixed $type movie, series, or episode.
	 * @return bool
	 */
	public static function publishing_allowed( $type ) {
		try {
			if ( ! is_string( $type ) || ! self::global_enabled() || ! self::type_enabled( $type ) ) {
				return false;
			}
			if ( '' === self::token() ) {
				return false;
			}
			return self::chat_is_valid( self::chat_id() );
		} catch ( \Throwable $e ) {
			unset( $e );
			return false;
		}
	}

	/**
	 * Constant, then environment. Never logs the value.
	 *
	 * @param string $key
	 * @return string
	 */
	private static function secret( $key ) {
		try {
			if ( defined( $key ) ) {
				$value = constant( $key );
				if ( is_string( $value ) && '' !== $value ) {
					return $value;
				}
			}

			$from_env = getenv( $key );
			if ( is_string( $from_env ) && '' !== $from_env ) {
				return $from_env;
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		return '';
	}

	/**
	 * Missing options are off. Only an explicit true / 1 / "1" enables a switch.
	 *
	 * @param string $option
	 * @return bool
	 */
	private static function flag( $option ) {
		try {
			if ( ! function_exists( 'get_option' ) ) {
				return false;
			}
			$value = get_option( $option, false );
			return true === $value || 1 === $value || '1' === $value;
		} catch ( \Throwable $e ) {
			unset( $e );
			return false;
		}
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	private static function type_enabled( $type ) {
		if ( 'movie' === $type ) {
			return self::movie_enabled();
		}
		if ( 'series' === $type ) {
			return self::series_enabled();
		}
		if ( 'episode' === $type ) {
			return self::episode_enabled();
		}
		return false;
	}

	/**
	 * Numeric supergroup ID, or a public @username of at least five characters.
	 *
	 * @param string $chat_id
	 * @return bool
	 */
	private static function chat_is_valid( $chat_id ) {
		if ( '' === $chat_id ) {
			return false;
		}
		$numeric = preg_match( '/^-100\d+$/', $chat_id );
		$named   = preg_match( '/^@\w{5,}$/', $chat_id );
		return 1 === $numeric || 1 === $named;
	}
}

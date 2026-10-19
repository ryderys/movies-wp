<?php
/**
 * Telegram Bot API client.
 *
 * Sends one announcement over HTTPS. Classifies the result as sent,
 * retryable, permanent, or ambiguous. A timeout after the request was
 * started is ambiguous and is not retried here. Photo URLs Telegram cannot
 * fetch fall back to a text message once. Secrets are not logged.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-movies-wp-telegram-formatter.php';

class Movies_WP_Telegram_Client {

	const API_BASE          = 'https://api.telegram.org';
	const CONNECT_TIMEOUT   = 5;
	const RESPONSE_TIMEOUT  = 15;

	/**
	 * Build and send an announcement from a content snapshot.
	 *
	 * @param array<string, mixed> $snapshot Formatter snapshot.
	 * @param array<string, mixed> $options  token, chat_id, http_post. Test hooks only.
	 * @return array{outcome:string,message_id:?string,error:string,code:string}
	 */
	public static function deliver( array $snapshot, array $options = array() ) {
		if ( ! class_exists( 'Movies_WP_Telegram_Formatter' ) ) {
			return self::result( 'permanent', null, 'Telegram formatter is not available.', 'formatter_missing' );
		}
		$message = Movies_WP_Telegram_Formatter::build( $snapshot );
		if ( empty( $message['ok'] ) ) {
			return self::result( 'permanent', null, 'Announcement had no public content.', 'empty_message' );
		}
		if ( is_string( $message['photo_url'] ?? null ) && '' !== $message['photo_url'] ) {
			$photo = self::request( 'sendPhoto', $message, $options );
			if ( 'photo_unreachable' === $photo['code'] ) {
				$message['photo_url'] = null;
				return self::request( 'sendMessage', $message, $options );
			}
			return $photo;
		}
		return self::request( 'sendMessage', $message, $options );
	}

	/**
	 * @param string               $method  sendPhoto or sendMessage.
	 * @param array<string, mixed> $message Formatter result.
	 * @param array<string, mixed> $options Test hooks.
	 * @return array{outcome:string,message_id:?string,error:string,code:string}
	 */
	private static function request( $method, array $message, array $options ) {
		$credentials = self::credentials( $options );
		if ( null === $credentials ) {
			return self::result( 'permanent', null, 'Telegram is not configured.', 'unconfigured' );
		}
		$payload = array(
			'chat_id'    => $credentials['chat_id'],
			'parse_mode' => Movies_WP_Telegram_Formatter::PARSE_MODE,
		);
		if ( 'sendPhoto' === $method ) {
			$payload['photo']   = (string) $message['photo_url'];
			$payload['caption'] = (string) $message['text'];
		} else {
			$method         = 'sendMessage';
			$payload['text'] = (string) $message['text'];
		}
		if ( is_string( $message['button_url'] ?? null ) && '' !== $message['button_url'] ) {
			$payload['reply_markup'] = array(
				'inline_keyboard' => array(
					array(
						array(
							'text' => (string) ( $message['button_label'] ?? Movies_WP_Telegram_Formatter::BUTTON_LABEL ),
							'url'  => (string) $message['button_url'],
						),
					),
				),
			);
		}
		$body = function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload );
		if ( ! is_string( $body ) ) {
			return self::result( 'permanent', null, 'Telegram request could not be encoded.', 'encode_failed' );
		}
		$url  = self::API_BASE . '/bot' . $credentials['token'] . '/' . $method;
		$args = array(
			'timeout'     => self::RESPONSE_TIMEOUT,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'        => $body,
		);
		$response = self::post( $url, $args, $options );
		return self::classify( $response, $method );
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array{token:string,chat_id:string}|null
	 */
	private static function credentials( array $options ) {
		$token = isset( $options['token'] ) ? (string) $options['token'] : '';
		$chat  = isset( $options['chat_id'] ) ? (string) $options['chat_id'] : '';
		if ( '' === $token || '' === $chat ) {
			if ( ! class_exists( 'Movies_WP_Telegram_Config' ) && is_readable( __DIR__ . '/class-movies-wp-telegram-config.php' ) ) {
				require_once __DIR__ . '/class-movies-wp-telegram-config.php';
			}
			if ( class_exists( 'Movies_WP_Telegram_Config' ) ) {
				if ( '' === $token ) {
					$token = Movies_WP_Telegram_Config::token();
				}
				if ( '' === $chat ) {
					$chat = Movies_WP_Telegram_Config::chat_id();
				}
			}
		}
		if ( '' === $token || strlen( $token ) > 200 ) {
			return null;
		}
		if ( 1 !== preg_match( '/^-100\d+$/', $chat ) && 1 !== preg_match( '/^@\w{5,}$/', $chat ) ) {
			return null;
		}
		return array(
			'token'   => $token,
			'chat_id' => $chat,
		);
	}

	/**
	 * @param string               $url
	 * @param array<string, mixed> $args
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|WP_Error
	 */
	private static function post( $url, array $args, array $options ) {
		if ( isset( $options['http_post'] ) && is_callable( $options['http_post'] ) ) {
			return call_user_func( $options['http_post'], $url, $args );
		}
		if ( ! function_exists( 'wp_remote_post' ) ) {
			return new WP_Error( 'telegram_http_missing', 'HTTP API is not available.' );
		}
		$hook = static function ( $handle ) {
			if ( is_resource( $handle ) || $handle instanceof \CurlHandle ) {
				curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, Movies_WP_Telegram_Client::CONNECT_TIMEOUT );
				curl_setopt( $handle, CURLOPT_TIMEOUT, Movies_WP_Telegram_Client::RESPONSE_TIMEOUT );
			}
		};
		if ( function_exists( 'add_action' ) ) {
			add_action( 'http_api_curl', $hook, 10, 1 );
		}
		try {
			return wp_remote_post( $url, $args );
		} finally {
			if ( function_exists( 'remove_action' ) ) {
				remove_action( 'http_api_curl', $hook, 10 );
			}
		}
	}

	/**
	 * @param array<string, mixed>|WP_Error $response
	 * @param string                        $method
	 * @return array{outcome:string,message_id:?string,error:string,code:string}
	 */
	private static function classify( $response, $method ) {
		if ( is_wp_error( $response ) ) {
			if ( self::is_timeout( $response ) ) {
				return self::result( 'ambiguous', null, 'Telegram request timed out.', 'timeout' );
			}
			return self::result( 'retryable', null, 'Telegram request failed before a response.', 'transport' );
		}
		if ( ! is_array( $response ) ) {
			return self::result( 'ambiguous', null, 'Telegram returned an unreadable response.', 'unreadable' );
		}
		$status = self::status_code( $response );
		$body   = self::body( $response );
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			if ( $status >= 200 && $status < 300 ) {
				return self::result( 'ambiguous', null, 'Telegram returned an unreadable success response.', 'invalid_json' );
			}
			if ( 429 === $status || $status >= 500 || 0 === $status ) {
				$outcome = 0 === $status ? 'ambiguous' : 'retryable';
				return self::result( $outcome, null, 'Telegram returned an unreadable error response.', 'invalid_json' );
			}
			return self::result( 'permanent', null, 'Telegram rejected the request.', 'invalid_json' );
		}
		if ( ! empty( $decoded['ok'] ) && $status >= 200 && $status < 300 ) {
			$message_id = $decoded['result']['message_id'] ?? null;
			if ( is_int( $message_id ) || ( is_string( $message_id ) && 1 === preg_match( '/^\d+$/', $message_id ) ) ) {
				return self::result( 'sent', (string) $message_id, '', 'ok' );
			}
			return self::result( 'ambiguous', null, 'Telegram accepted the request without a message ID.', 'missing_message_id' );
		}
		$description = isset( $decoded['description'] ) ? self::sanitize_error( (string) $decoded['description'] ) : '';
		if ( 'sendPhoto' === $method && self::photo_was_unreachable( $description ) ) {
			return self::result( 'retryable', null, 'Telegram could not fetch the poster.', 'photo_unreachable' );
		}
		if ( 408 === $status ) {
			return self::result( 'ambiguous', null, 'Telegram timed out.', 'timeout' );
		}
		if ( 429 === $status || $status >= 500 ) {
			return self::result( 'retryable', null, '' !== $description ? $description : 'Telegram is unavailable.', 'http_' . $status );
		}
		if ( $status >= 400 && $status < 500 ) {
			return self::result( 'permanent', null, '' !== $description ? $description : 'Telegram rejected the request.', 'http_' . $status );
		}
		return self::result( 'retryable', null, 'Telegram returned an unexpected response.', 'unexpected' );
	}

	/**
	 * @param WP_Error $error
	 * @return bool
	 */
	private static function is_timeout( $error ) {
		$message = (string) $error->get_error_message();
		return ( false !== stripos( $message, 'timed out' ) )
			|| ( false !== stripos( $message, 'timeout' ) )
			|| ( 'http_request_failed' === $error->get_error_code() && false !== stripos( $message, 'cURL error 28' ) );
	}

	/**
	 * @param string $description
	 * @return bool
	 */
	private static function photo_was_unreachable( $description ) {
		$description = strtolower( $description );
		return str_contains( $description, 'failed to get http url content' )
			|| str_contains( $description, 'wrong type of the web page content' )
			|| str_contains( $description, 'failed to get header' );
	}

	/**
	 * @param array<string, mixed> $response
	 * @return int
	 */
	private static function status_code( array $response ) {
		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			return (int) wp_remote_retrieve_response_code( $response );
		}
		return (int) ( $response['response']['code'] ?? 0 );
	}

	/**
	 * @param array<string, mixed> $response
	 * @return string
	 */
	private static function body( array $response ) {
		if ( function_exists( 'wp_remote_retrieve_body' ) ) {
			return (string) wp_remote_retrieve_body( $response );
		}
		return isset( $response['body'] ) ? (string) $response['body'] : '';
	}

	/**
	 * @param string $error
	 * @return string
	 */
	private static function sanitize_error( $error ) {
		$text = str_replace( array( "\r", "\n", "\0" ), ' ', $error );
		$text = preg_replace( '#https?://\S+#i', '[url]', $text );
		$text = is_string( $text ) ? $text : '';
		$text = preg_replace( '/\d{5,}:[A-Za-z0-9_-]{10,}/', '[redacted]', $text );
		$text = is_string( $text ) ? $text : '';
		$text = preg_replace( '/bot\d{5,}:[A-Za-z0-9_-]+/i', 'bot[redacted]', $text );
		$text = is_string( $text ) ? $text : '';
		$text = preg_replace( '/authorization\s*:\s*(?:bearer\s+)?\S+/i', 'authorization: [redacted]', $text );
		$text = is_string( $text ) ? $text : '';
		$text = trim( preg_replace( '/\s+/', ' ', $text ) ?? '' );
		if ( strlen( $text ) > 500 ) {
			$text = substr( $text, 0, 500 );
		}
		return $text;
	}

	/**
	 * @param string      $outcome
	 * @param string|null $message_id
	 * @param string      $error
	 * @param string      $code
	 * @return array{outcome:string,message_id:?string,error:string,code:string}
	 */
	private static function result( $outcome, $message_id, $error, $code ) {
		return array(
			'outcome'    => $outcome,
			'message_id' => $message_id,
			'error'      => self::sanitize_error( $error ),
			'code'       => $code,
		);
	}
}

<?php
/**
 * Persian Telegram announcement text.
 *
 * The snapshot is plain data. This class does not read WordPress, invent
 * missing facts, or call Telegram. Descriptions are omitted when empty;
 * English text is not substituted.
 *
 * Snapshot keys:
 *   type: movie, series, or episode
 *   title: Persian movie, series, or episode title
 *   series_title: parent series title for an episode
 *   year: release year, movie or series
 *   genres: list of Persian genre names
 *   description: Persian description
 *   season, episode: numbers already stored for the episode
 *   permalink: public https URL of the movie, series, or episode page
 *   poster_url: public https image URL, or empty
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Telegram_Formatter {

	const PARSE_MODE      = 'HTML';
	const BUTTON_LABEL    = 'تماشا و دانلود';
	const DESCRIPTION_MAX = 280;
	const CAPTION_MAX     = 1024;
	const TEXT_MAX        = 4096;

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	public static function build( array $snapshot ) {
		$type  = isset( $snapshot['type'] ) ? (string) $snapshot['type'] : '';
		$lines = array();
		if ( 'episode' === $type ) {
			$lines = self::episode_lines( $snapshot );
		} elseif ( 'movie' === $type || 'series' === $type ) {
			$lines = self::title_lines( $snapshot );
		}
		$lines = array_values( array_filter( $lines, static function ( $line ) {
			return is_string( $line ) && '' !== $line;
		} ) );
		if ( array() === $lines ) {
			return self::empty_message();
		}

		$photo  = self::public_image_url( isset( $snapshot['poster_url'] ) ? (string) $snapshot['poster_url'] : '' );
		$button = self::public_page_url( isset( $snapshot['permalink'] ) ? (string) $snapshot['permalink'] : '' );
		$limit  = null === $photo ? self::TEXT_MAX : self::CAPTION_MAX;
		$text   = self::fit( implode( "\n", $lines ), $limit );

		return array(
			'ok'           => '' !== $text,
			'parse_mode'   => self::PARSE_MODE,
			'text'         => $text,
			'photo_url'    => $photo,
			'button_url'   => $button,
			'button_label' => self::BUTTON_LABEL,
		);
	}

	/**
	 * Public poster URL Telegram can fetch. Empty when the URL is not usable.
	 *
	 * @param string $url
	 * @return string|null
	 */
	public static function public_image_url( $url ) {
		$parts = self::public_https_parts( $url );
		if ( null === $parts ) {
			return null;
		}
		$path = (string) ( $parts['path'] ?? '' );
		if ( ! preg_match( '/\.(jpe?g|png|webp|gif)$/i', $path ) ) {
			return null;
		}
		return self::rebuild_url( $parts );
	}

	/**
	 * Public page URL for the inline button.
	 *
	 * @param string $url
	 * @return string|null
	 */
	public static function public_page_url( $url ) {
		$parts = self::public_https_parts( $url );
		if ( null === $parts ) {
			return null;
		}
		$path = strtolower( (string) ( $parts['path'] ?? '' ) );
		if ( preg_match( '/\.(mp4|mkv|webm|m3u8|m4v|avi|mov)$/i', $path ) ) {
			return null;
		}
		return self::rebuild_url( $parts );
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return list<string>
	 */
	private static function title_lines( array $snapshot ) {
		$lines = array();
		$title = self::plain( $snapshot['title'] ?? '' );
		if ( '' !== $title ) {
			$lines[] = '<b>' . self::esc( $title ) . '</b>';
		}
		$meta = self::meta_line( $snapshot );
		if ( '' !== $meta ) {
			$lines[] = self::esc( $meta );
		}
		$description = self::description( $snapshot['description'] ?? '' );
		if ( '' !== $description ) {
			$lines[] = self::esc( $description );
		}
		return $lines;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return list<string>
	 */
	private static function episode_lines( array $snapshot ) {
		$lines = array();
		$series = self::plain( $snapshot['series_title'] ?? '' );
		if ( '' !== $series ) {
			$lines[] = '<b>' . self::esc( $series ) . '</b>';
		}
		$slot = self::episode_slot( $snapshot );
		if ( '' !== $slot ) {
			$lines[] = self::esc( $slot );
		}
		$title = self::plain( $snapshot['title'] ?? '' );
		if ( '' !== $title && $title !== $series ) {
			$lines[] = self::esc( $title );
		}
		$description = self::description( $snapshot['description'] ?? '' );
		if ( '' !== $description ) {
			$lines[] = self::esc( $description );
		}
		return $lines;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return string
	 */
	private static function meta_line( array $snapshot ) {
		$parts = array();
		$year  = self::year( $snapshot['year'] ?? null );
		if ( '' !== $year ) {
			$parts[] = $year;
		}
		$genres = self::genres( $snapshot['genres'] ?? array() );
		if ( '' !== $genres ) {
			$parts[] = $genres;
		}
		return implode( ' · ', $parts );
	}

	/**
	 * @param mixed $year
	 * @return string
	 */
	private static function year( $year ) {
		if ( is_int( $year ) ) {
			$year = (string) $year;
		}
		if ( ! is_string( $year ) ) {
			return '';
		}
		return 1 === preg_match( '/^\d{4}$/', $year ) ? $year : '';
	}

	/**
	 * @param mixed $genres
	 * @return string
	 */
	private static function genres( $genres ) {
		if ( ! is_array( $genres ) ) {
			return '';
		}
		$names = array();
		foreach ( $genres as $genre ) {
			$name = self::plain( $genre );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		return implode( '، ', $names );
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return string
	 */
	private static function episode_slot( array $snapshot ) {
		$parts  = array();
		$season = self::number_label( $snapshot['season'] ?? null );
		if ( '' !== $season ) {
			$parts[] = 'فصل ' . $season;
		}
		$episode = self::number_label( $snapshot['episode'] ?? null );
		if ( '' !== $episode ) {
			$parts[] = 'قسمت ' . $episode;
		}
		return implode( '، ', $parts );
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function number_label( $value ) {
		if ( is_int( $value ) && $value >= 0 ) {
			return (string) $value;
		}
		if ( is_string( $value ) && 1 === preg_match( '/^\d{1,4}$/', $value ) ) {
			return (string) (int) $value;
		}
		return '';
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function description( $value ) {
		$text = self::plain( $value );
		if ( '' === $text ) {
			return '';
		}
		if ( self::length( $text ) <= self::DESCRIPTION_MAX ) {
			return $text;
		}
		return self::cut( $text, self::DESCRIPTION_MAX );
	}

	/**
	 * @param string $text
	 * @param int    $limit
	 * @return string
	 */
	private static function fit( $text, $limit ) {
		if ( self::length( $text ) <= $limit ) {
			return $text;
		}
		return self::cut( $text, $limit );
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function plain( $value ) {
		$text = is_string( $value ) || is_numeric( $value ) ? (string) $value : '';
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = trim( preg_replace( '/[ \t]+/u', ' ', $text ) ?? '' );
		return $text;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private static function esc( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/**
	 * @param string $url
	 * @return array<string, mixed>|null
	 */
	private static function public_https_parts( $url ) {
		$url = trim( $url );
		if ( '' === $url || str_contains( $url, "\0" ) || str_contains( $url, ' ' ) ) {
			return null;
		}
		if ( function_exists( 'wp_parse_url' ) ) {
			$parts = wp_parse_url( $url );
		} else {
			$parts = parse_url( $url );
		}
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) ) {
			return null;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return null;
		}
		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( '' === $host || self::blocked_host( $host ) ) {
			return null;
		}
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : 443;
		if ( 443 !== $port ) {
			return null;
		}
		$path = (string) ( $parts['path'] ?? '' );
		if ( self::blocked_path( $path ) || self::blocked_query( (string) ( $parts['query'] ?? '' ) ) ) {
			return null;
		}
		return $parts;
	}

	/**
	 * @param string $host
	 * @return bool
	 */
	private static function blocked_host( $host ) {
		if ( in_array( $host, array( 'localhost', 'localhost.localdomain' ), true ) ) {
			return true;
		}
		if ( str_ends_with( $host, '.local' ) || str_ends_with( $host, '.internal' ) ) {
			return true;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		return false;
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	private static function blocked_path( $path ) {
		$normalized = str_replace( '\\', '/', $path );
		if ( str_contains( strtolower( $normalized ), '/data/' ) ) {
			return true;
		}
		// Media-library roots. The public route /movie/ is lowercase and stays allowed.
		return 1 === preg_match( '#(^|/)Movie(/|$)#', $normalized )
			|| 1 === preg_match( '#(^|/)Series(/|$)#', $normalized );
	}

	/**
	 * @param string $query
	 * @return bool
	 */
	private static function blocked_query( $query ) {
		if ( '' === $query ) {
			return false;
		}
		foreach ( explode( '&', $query ) as $pair ) {
			$name = strtolower( rawurldecode( (string) strtok( $pair, '=' ) ) );
			if ( in_array( $name, array( 'token', 'signature', 'sig', 'expires', 'x-amz-signature', 'x-amz-credential', 'x-media-signature' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $parts
	 * @return string
	 */
	private static function rebuild_url( array $parts ) {
		$url = 'https://' . (string) $parts['host'] . (string) ( $parts['path'] ?? '' );
		if ( isset( $parts['query'] ) && is_string( $parts['query'] ) && '' !== $parts['query'] ) {
			$url .= '?' . $parts['query'];
		}
		return $url;
	}

	/**
	 * @param string $text
	 * @return int
	 */
	private static function length( $text ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $text, 'UTF-8' );
		}
		$count = preg_match_all( '/./u', $text, $matches );
		return false === $count ? strlen( $text ) : (int) $count;
	}

	/**
	 * @param string $text
	 * @param int    $limit
	 * @return string
	 */
	private static function cut( $text, $limit ) {
		$keep = $limit - 1;
		if ( $keep < 1 ) {
			return '…';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return rtrim( (string) mb_substr( $text, 0, $keep, 'UTF-8' ) ) . '…';
		}
		$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $chars ) ) {
			return '…';
		}
		return rtrim( implode( '', array_slice( $chars, 0, $keep ) ) ) . '…';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function empty_message() {
		return array(
			'ok'           => false,
			'parse_mode'   => self::PARSE_MODE,
			'text'         => '',
			'photo_url'    => null,
			'button_url'   => null,
			'button_label' => self::BUTTON_LABEL,
		);
	}
}

<?php
/**
 * Read-only WordPress snapshot for a Telegram announcement.
 *
 * Reads the verified Streamit fields only. Optional metadata is omitted
 * when missing. Poster and permalink URLs are accepted only after
 * Movies_WP_Telegram_Formatter validation. This class does not enqueue
 * jobs or call Telegram.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-movies-wp-telegram-formatter.php';

class Movies_WP_Telegram_Snapshot_Provider {

	/**
	 * @param string               $type    movie, series, or episode.
	 * @param int                  $post_id Streamit post ID.
	 * @param array<string, mixed> $options Test readers.
	 * @return array<string, mixed>
	 */
	public static function build( $type, $post_id, array $options = array() ) {
		if ( ! is_string( $type ) || ! in_array( $type, array( 'movie', 'series', 'episode' ), true ) ) {
			return self::failure( 'invalid_type' );
		}
		$post_id = self::positive_int( $post_id );
		if ( $post_id <= 0 ) {
			return self::failure( 'invalid_post_id' );
		}

		$object = self::load( $type, $post_id, $options );
		if ( ! is_object( $object ) ) {
			return self::failure( 'not_found' );
		}
		if ( self::post_type( $object ) !== self::streamit_type( $type ) ) {
			return self::failure( 'wrong_type' );
		}

		$snapshot = array(
			'type'        => $type,
			'title'       => self::text( self::call_getter( $object, 'get_post_title' ) ),
			'description' => self::text( self::call_getter( $object, 'get_post_content' ) ),
			'genres'      => array(),
			'permalink'   => self::permalink( self::streamit_type( $type ), self::call_getter( $object, 'get_post_name' ), $options ),
			'poster_url'  => '',
		);

		if ( 'movie' === $type ) {
			$year = self::year_from_date( self::meta( $object, '_movie_release_date', $options ) );
			if ( '' !== $year ) {
				$snapshot['year'] = $year;
			}
			$snapshot['genres']     = self::genre_names( $post_id, 'movie_genre', $options );
			$snapshot['poster_url'] = self::poster( self::meta( $object, '_portrait_thumbmail', $options ), $options );
		} elseif ( 'series' === $type ) {
			$year = self::year_from_date( self::call_getter( $object, 'get_post_date' ) );
			if ( '' !== $year ) {
				$snapshot['year'] = $year;
			}
			$snapshot['genres']     = self::genre_names( $post_id, 'tvshow_genre', $options );
			$snapshot['poster_url'] = self::poster( self::meta( $object, '_portrait_thumbmail', $options ), $options );
		} else {
			$parent_id = self::positive_int( self::meta( $object, 'tvshow_id', $options ) );
			if ( $parent_id <= 0 ) {
				return self::failure( 'missing_parent' );
			}
			$parent = self::load( 'series', $parent_id, $options );
			if ( ! is_object( $parent ) || 'tvshow' !== self::post_type( $parent ) ) {
				return self::failure( 'missing_parent' );
			}
			$snapshot['series_title'] = self::text( self::call_getter( $parent, 'get_post_title' ) );
			$season                   = self::number( self::meta( $object, '_season_number', $options ) );
			$episode                  = self::number( self::meta( $object, '_episode_number', $options ) );
			if ( '' !== $season ) {
				$snapshot['season'] = $season;
			}
			if ( '' !== $episode ) {
				$snapshot['episode'] = $episode;
			}
			$snapshot['poster_url'] = self::poster( self::meta( $object, 'thumbnail_id', $options ), $options );
		}

		return array(
			'ok'       => true,
			'snapshot' => $snapshot,
		);
	}

	/**
	 * @param string $code
	 * @return array{ok:bool,code:string,snapshot:null}
	 */
	private static function failure( $code ) {
		return array(
			'ok'       => false,
			'code'     => $code,
			'snapshot' => null,
		);
	}

	/**
	 * @param string               $type
	 * @param int                  $post_id
	 * @param array<string, mixed> $options
	 * @return object|null
	 */
	private static function load( $type, $post_id, array $options ) {
		$readers = array(
			'movie'   => array( 'get_movie', 'streamit_get_movie' ),
			'series'  => array( 'get_tvshow', 'streamit_get_tvshow' ),
			'episode' => array( 'get_episode', 'streamit_get_episode' ),
		);
		$pair = $readers[ $type ] ?? null;
		if ( null === $pair ) {
			return null;
		}
		if ( isset( $options[ $pair[0] ] ) && is_callable( $options[ $pair[0] ] ) ) {
			$object = call_user_func( $options[ $pair[0] ], (int) $post_id );
		} elseif ( function_exists( $pair[1] ) ) {
			$object = call_user_func( $pair[1], (int) $post_id );
		} else {
			return null;
		}
		return is_object( $object ) ? $object : null;
	}

	/**
	 * @param object               $object
	 * @param string               $key
	 * @param array<string, mixed> $options
	 * @return mixed
	 */
	private static function meta( $object, $key, array $options ) {
		if ( isset( $options['get_meta'] ) && is_callable( $options['get_meta'] ) ) {
			return call_user_func( $options['get_meta'], $object, $key );
		}
		if ( method_exists( $object, 'get_meta' ) ) {
			return $object->get_meta( $key );
		}
		return '';
	}

	/**
	 * @param object $object
	 * @return string
	 */
	private static function post_type( $object ) {
		$value = self::call_getter( $object, 'get_post_type' );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * @param object $object
	 * @param string $method
	 * @return mixed
	 */
	private static function call_getter( $object, $method ) {
		if ( ! method_exists( $object, $method ) ) {
			return '';
		}
		return $object->{$method}();
	}

	/**
	 * @param string $type
	 * @return string
	 */
	private static function streamit_type( $type ) {
		return 'series' === $type ? 'tvshow' : $type;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function text( $value ) {
		$text = is_string( $value ) || is_numeric( $value ) ? (string) $value : '';
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$text = wp_strip_all_tags( $text );
		} else {
			$text = strip_tags( $text );
		}
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		return trim( $text );
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function year_from_date( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}
		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}
		if ( function_exists( 'wp_date' ) ) {
			$year = wp_date( 'Y', $timestamp );
			return is_string( $year ) && 1 === preg_match( '/^\d{4}$/', $year ) ? $year : '';
		}
		$year = date( 'Y', $timestamp );
		return 1 === preg_match( '/^\d{4}$/', $year ) ? $year : '';
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function number( $value ) {
		if ( is_int( $value ) && $value >= 0 ) {
			return (string) $value;
		}
		if ( is_string( $value ) && 1 === preg_match( '/^\d{1,4}$/', trim( $value ) ) ) {
			return (string) (int) trim( $value );
		}
		return '';
	}

	/**
	 * @param int                  $post_id
	 * @param string               $taxonomy
	 * @param array<string, mixed> $options
	 * @return list<string>
	 */
	private static function genre_names( $post_id, $taxonomy, array $options ) {
		if ( isset( $options['get_relationships'] ) && is_callable( $options['get_relationships'] ) ) {
			$ids = call_user_func( $options['get_relationships'], (int) $post_id, $taxonomy );
		} elseif ( function_exists( 'streamit_get_term_relationships' ) ) {
			$ids = streamit_get_term_relationships( (int) $post_id, $taxonomy );
		} else {
			return array();
		}
		if ( is_wp_error( $ids ) || ! is_array( $ids ) || array() === $ids ) {
			return array();
		}
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( array() === $ids ) {
			return array();
		}
		if ( isset( $options['get_terms'] ) && is_callable( $options['get_terms'] ) ) {
			$terms = call_user_func( $options['get_terms'], $ids, $taxonomy );
		} elseif ( function_exists( 'streamit_get_terms' ) ) {
			$terms = streamit_get_terms(
				array(
					'include'  => $ids,
					'per_page' => count( $ids ),
				)
			);
		} else {
			return array();
		}
		if ( is_object( $terms ) && isset( $terms->results ) && is_array( $terms->results ) ) {
			$terms = $terms->results;
		}
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$names = array();
		foreach ( $terms as $term ) {
			$name = '';
			if ( is_object( $term ) && method_exists( $term, 'get_term_name' ) ) {
				$name = self::text( $term->get_term_name() );
			} elseif ( is_array( $term ) && isset( $term['name'] ) ) {
				$name = self::text( $term['name'] );
			}
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * @param string               $post_type
	 * @param mixed                $post_name
	 * @param array<string, mixed> $options
	 * @return string
	 */
	private static function permalink( $post_type, $post_name, array $options ) {
		$post_name = is_string( $post_name ) ? $post_name : '';
		if ( '' === $post_name ) {
			return '';
		}
		if ( isset( $options['permalink'] ) && is_callable( $options['permalink'] ) ) {
			$url = call_user_func( $options['permalink'], $post_type, $post_name );
		} elseif ( function_exists( 'streamit_get_permalink' ) ) {
			$url = streamit_get_permalink( $post_type, $post_name );
		} else {
			return '';
		}
		$safe = Movies_WP_Telegram_Formatter::public_page_url( is_string( $url ) ? $url : '' );
		return is_string( $safe ) ? $safe : '';
	}

	/**
	 * @param mixed                $attachment_id
	 * @param array<string, mixed> $options
	 * @return string
	 */
	private static function poster( $attachment_id, array $options ) {
		$attachment_id = self::positive_int( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return '';
		}
		if ( isset( $options['attachment_url'] ) && is_callable( $options['attachment_url'] ) ) {
			$url = call_user_func( $options['attachment_url'], $attachment_id );
		} elseif ( function_exists( 'wp_get_attachment_image_url' ) ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
		} elseif ( function_exists( 'wp_get_attachment_url' ) ) {
			$url = wp_get_attachment_url( $attachment_id );
		} else {
			return '';
		}
		$safe = Movies_WP_Telegram_Formatter::public_image_url( is_string( $url ) ? $url : '' );
		return is_string( $safe ) ? $safe : '';
	}

	/**
	 * @param mixed $value
	 * @return int
	 */
	private static function positive_int( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : 0;
		}
		if ( is_string( $value ) && 1 === preg_match( '/^[1-9]\d*$/', $value ) ) {
			return (int) $value;
		}
		return 0;
	}
}

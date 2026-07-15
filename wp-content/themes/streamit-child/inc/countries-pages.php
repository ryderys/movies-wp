<?php
/**
 * Country archive and single pages (mirrors Streamit genre pages using _country meta).
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight card object compatible with genre archive loop templates.
 */
class Streamit_Child_Country_Card {

	/** @var string */
	private $code;

	/** @var string */
	private $label;

	/** @var string */
	private $post_type;

	/**
	 * @param string $code      ISO country code.
	 * @param string $label     Display label.
	 * @param string $post_type movie|tvshow.
	 */
	public function __construct( $code, $label, $post_type ) {
		$this->code      = strtoupper( sanitize_text_field( (string) $code ) );
		$this->label     = (string) $label;
		$this->post_type = sanitize_key( (string) $post_type );
	}

	/** @return string */
	public function get_term_name() {
		return $this->label;
	}

	/** @return string */
	public function get_term_slug() {
		return strtolower( $this->code );
	}

	/** @return string */
	public function get_thumbnail() {
		return '';
	}

	/** @return string */
	public function get_post_type() {
		return $this->post_type;
	}
}

/**
 * Default rewrite slug for country archives.
 *
 * @param string $context movie_country|tvshow_country.
 * @return string
 */
function streamit_child_country_slug( $context ) {
	$defaults = array(
		'movie_country'  => 'movie_countries',
		'tvshow_country' => 'tvshow_countries',
	);

	$option = 'streamit_' . $context . '_slug';

	return (string) get_option( $option, $defaults[ $context ] ?? $context );
}

/**
 * Build permalink for a country archive or single country page.
 *
 * @param string $context movie_country|tvshow_country.
 * @param string $code    Optional ISO code.
 * @return string
 */
function streamit_child_get_country_permalink( $context, $code = '' ) {
	$slug = trailingslashit( streamit_child_country_slug( $context ) );
	$url  = home_url( $slug );

	if ( '' !== $code ) {
		$url = trailingslashit( $url . strtoupper( sanitize_text_field( $code ) ) );
	}

	return $url;
}

/**
 * Items per page for country grid archives.
 *
 * @return int
 */
function streamit_child_countries_per_page() {
	return max( 1, (int) apply_filters( 'streamit_child_countries_per_page', 24 ) );
}

/**
 * Paginated country cards for archive pages.
 *
 * @param string $post_type movie|tvshow.
 * @param int    $page      Page number.
 * @return object
 */
function streamit_child_get_countries_archive_data( $post_type, $page = 1 ) {
	$post_type = sanitize_key( $post_type );
	$page      = max( 1, (int) $page );
	$per_page  = streamit_child_countries_per_page();

	$countries = function_exists( 'streamit_child_get_catalog_countries' )
		? streamit_child_get_catalog_countries( $post_type )
		: array();

	$total  = count( $countries );
	$offset = ( $page - 1 ) * $per_page;
	$slice  = array_slice( $countries, $offset, $per_page, true );
	$cards  = array();

	foreach ( $slice as $code => $label ) {
		$cards[] = new Streamit_Child_Country_Card( $code, $label, $post_type );
	}

	return (object) array(
		'results'     => $cards,
		'total'       => $total,
		'maxnumpages' => max( 1, (int) ceil( $total / $per_page ) ),
	);
}

/**
 * Movies or TV shows filtered by country code.
 *
 * @param string $post_type movie|tvshow.
 * @param string $code      ISO country code.
 * @param int    $page      Page number.
 * @param int    $per_page  Items per page.
 * @return object
 */
function streamit_child_get_content_by_country( $post_type, $code, $page = 1, $per_page = 12 ) {
	$post_type = sanitize_key( $post_type );
	$code      = strtoupper( sanitize_text_field( (string) $code ) );
	$page      = max( 1, (int) $page );
	$per_page  = max( 1, (int) $per_page );

	$empty = (object) array(
		'results'     => array(),
		'total'       => 0,
		'maxnumpages' => 0,
	);

	if ( ! $code || ! in_array( $post_type, array( 'movie', 'tvshow' ), true ) ) {
		return $empty;
	}

	$callback = 'streamit_get_' . $post_type . 's';
	if ( ! function_exists( $callback ) ) {
		return $empty;
	}

	$args = apply_filters(
		'streamit_' . $post_type . 's_arguments',
		array(
			'paged'       => $page,
			'per_page'    => $per_page,
			'post_status' => array( 'publish' ),
			'order'       => 'DESC',
			'filters'     => array(
				'countries' => array( $code ),
			),
		)
	);

	return call_user_func( $callback, $args );
}

/**
 * Resolve country label from catalog list.
 *
 * @param string $post_type movie|tvshow.
 * @param string $code      ISO code.
 * @return string
 */
function streamit_child_get_country_label( $post_type, $code ) {
	$code      = strtoupper( sanitize_text_field( (string) $code ) );
	$countries = function_exists( 'streamit_child_get_catalog_countries' )
		? streamit_child_get_catalog_countries( $post_type )
		: array();

	return $countries[ $code ] ?? $code;
}

/**
 * @param string $title Page title.
 * @param string $type  Permalink context key.
 */
function streamit_child_set_country_page_title( $title, $type = '' ) {
	add_filter(
		'pre_get_document_title',
		static function () use ( $title ) {
			return $title . ' | ' . get_bloginfo( 'name' );
		},
		PHP_INT_MAX
	);

	if ( $type ) {
		add_action(
			'wp_head',
			static function () use ( $title, $type ) {
				echo '<meta property="og:title" content="' . esc_attr( $title ) . '"/>' . "\n";
				echo '<meta property="og:url" content="' . esc_url( streamit_child_get_country_permalink( $type ) ) . '"/>' . "\n";
			},
			PHP_INT_MAX
		);
	}
}

/**
 * Detect active country route from the main query.
 *
 * @return array{context:string,content_type:string,view_type:string,code:string}|null
 */
function streamit_child_get_active_country_route() {
	$map = array(
		'movie_countries'  => array(
			'context'      => 'movie_country',
			'content_type' => 'movie',
			'view_type'    => 'country-archive',
		),
		'movie_country'    => array(
			'context'      => 'movie_country',
			'content_type' => 'movie',
			'view_type'    => 'country-single',
		),
		'tvshow_countries' => array(
			'context'      => 'tvshow_country',
			'content_type' => 'tvshow',
			'view_type'    => 'country-archive',
		),
		'tvshow_country'   => array(
			'context'      => 'tvshow_country',
			'content_type' => 'tvshow',
			'view_type'    => 'country-single',
		),
	);

	foreach ( $map as $query_var => $route ) {
		$value = get_query_var( $query_var );
		if ( '' === $value && ! array_key_exists( $query_var, $GLOBALS['wp_query']->query_vars ) ) {
			continue;
		}

		if ( str_ends_with( $query_var, '_countries' ) ) {
			$route['code'] = '';
			return $route;
		}

		$slug = is_string( $value ) ? $value : '';
		if ( ! $slug && isset( $GLOBALS['wp_query']->query_vars[ $query_var ] ) ) {
			$slug = (string) $GLOBALS['wp_query']->query_vars[ $query_var ];
		}

		if ( $slug ) {
			$route['code'] = strtoupper( sanitize_text_field( $slug ) );
			return $route;
		}
	}

	return null;
}

/**
 * Render country routes through Streamit's main template wrapper.
 *
 * @param string $template Default template.
 * @return string
 */
function streamit_child_country_template_include( $template ) {
	$route = streamit_child_get_active_country_route();
	if ( ! $route || ! function_exists( 'streamit_get_template' ) ) {
		return $template;
	}

	$content_type = $route['content_type'];
	$view_type    = $route['view_type'];
	$context      = $route['context'];

	if ( 'country-archive' === $view_type ) {
		$title = ( 'movie' === $content_type )
			? __( 'Movie Countries', 'streamit' )
			: __( 'TV Show Countries', 'streamit' );
		streamit_child_set_country_page_title( $title, $context );
		$content_data = streamit_child_get_countries_archive_data( $content_type, 1 );
	} else {
		$known = function_exists( 'streamit_child_get_catalog_countries' )
			? streamit_child_get_catalog_countries( $content_type )
			: array();

		if ( ! isset( $known[ $route['code'] ] ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return get_404_template() ?: $template;
		}

		$label = streamit_child_get_country_label( $content_type, $route['code'] );
		streamit_child_set_country_page_title( $label, $context );
		$content_data = streamit_child_get_content_by_country( $content_type, $route['code'], 1, 12 );
	}

	add_filter(
		'body_class',
		static function ( $classes ) use ( $content_type, $view_type ) {
			$classes[] = 'st-' . $content_type . '-' . $view_type;
			return $classes;
		}
	);

	$parts        = explode( '-', $view_type );
	$sub_template = end( $parts );

	streamit_get_template(
		'streamit-main-template.php',
		array(
			'content_type' => $content_type,
			'view_type'    => $view_type,
			'content_data' => $content_data,
			'sub_template' => $sub_template,
		)
	);

	return '';
}
add_filter( 'template_include', 'streamit_child_country_template_include', 0 );

/**
 * Register rewrite rules and query vars.
 */
function streamit_child_country_query_vars( $vars ) {
	$vars[] = 'movie_countries';
	$vars[] = 'movie_country';
	$vars[] = 'tvshow_countries';
	$vars[] = 'tvshow_country';
	return $vars;
}
add_filter( 'query_vars', 'streamit_child_country_query_vars' );

/**
 * @param array $rules Rewrite rules.
 * @return array
 */
function streamit_child_country_rewrite_rules( $rules ) {
	$movie_slug  = streamit_child_country_slug( 'movie_country' );
	$tvshow_slug = streamit_child_country_slug( 'tvshow_country' );

	$new = array(
		"{$movie_slug}/?$"            => 'index.php?movie_countries=1',
		"{$movie_slug}/([^/]+)/?$"   => 'index.php?movie_country=$matches[1]',
		"{$tvshow_slug}/?$"           => 'index.php?tvshow_countries=1',
		"{$tvshow_slug}/([^/]+)/?$"  => 'index.php?tvshow_country=$matches[1]',
	);

	return $new + $rules;
}
add_filter( 'rewrite_rules_array', 'streamit_child_country_rewrite_rules' );

/**
 * Flush rewrite rules once after deploy.
 */
function streamit_child_maybe_flush_country_rewrites() {
	if ( get_option( 'streamit_child_countries_rewrites_flushed' ) ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( 'streamit_child_countries_rewrites_flushed', 1, false );
}
add_action( 'init', 'streamit_child_maybe_flush_country_rewrites', 99 );

/**
 * Parse AJAX payload for Streamit load-more requests.
 *
 * @return array<string, mixed>
 */
function streamit_child_countries_ajax_data() {
	if ( ! empty( $_REQUEST['data'] ) && is_array( $_REQUEST['data'] ) ) {
		return $_REQUEST['data'];
	}

	$raw = file_get_contents( 'php://input' );
	if ( $raw ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) && isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) {
			return $decoded['data'];
		}
	}

	return array();
}

/**
 * Handle load-more for country archives and single-country listings.
 */
function streamit_child_countries_ajax_load() {
	if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'GET' ) {
		return;
	}

	$route_name = isset( $_REQUEST['route_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['route_name'] ) ) : '';
	if ( 'st_load_more_content' !== $route_name ) {
		return;
	}

	$data      = streamit_child_countries_ajax_data();
	$post_type = isset( $data['post_type'] ) ? sanitize_text_field( $data['post_type'] ) : '';

	$archive_types = array(
		'movie_countries'  => 'movie',
		'tvshow_countries' => 'tvshow',
	);
	$single_types = array(
		'movie_single_country'  => 'movie',
		'tvshow_single_country' => 'tvshow',
	);

	if ( ! isset( $archive_types[ $post_type ] ) && ! isset( $single_types[ $post_type ] ) ) {
		return;
	}

	$current_page = max( 1, (int) ( $data['current_page'] ?? 1 ) );
	$per_page     = max( 1, (int) ( $data['per_page'] ?? 12 ) );

	ob_start();

	if ( isset( $archive_types[ $post_type ] ) ) {
		$content_type = $archive_types[ $post_type ];
		$archive      = streamit_child_get_countries_archive_data( $content_type, $current_page );
		$template     = $content_type . '/archive/archive_country_loop.php';

		foreach ( $archive->results as $st_data ) {
			streamit_get_template( $template, array( 'st_data' => $st_data ) );
		}

		$html        = ob_get_clean();
		$total_pages = (int) $archive->maxnumpages;
		$total       = (int) $archive->total;
	} else {
		$content_type = $single_types[ $post_type ];
		$country_code = isset( $data['post_id'] ) ? strtoupper( sanitize_text_field( (string) $data['post_id'] ) ) : '';
		$listing      = streamit_child_get_content_by_country( $content_type, $country_code, $current_page, $per_page );
		$template     = $content_type . '/archive/archive_loop.php';

		foreach ( $listing->results as $st_data ) {
			streamit_get_template( $template, array( 'st_data' => $st_data ) );
		}

		$html        = ob_get_clean();
		$total_pages = (int) $listing->maxnumpages;
		$total       = (int) $listing->total;
	}

	wp_send_json(
		array(
			'status'        => '' !== trim( $html ),
			'result'        => $html,
			'total_results' => $total,
			'total_pages'   => $total_pages,
			'current_page'  => $current_page,
		)
	);
}
add_action( 'wp_ajax_st_ajax_get', 'streamit_child_countries_ajax_load', 0 );
add_action( 'wp_ajax_nopriv_st_ajax_get', 'streamit_child_countries_ajax_load', 0 );

<?php
/**
 * Theme functions and definitions.
 */
add_action( 'wp_enqueue_scripts', 'streamit_enqueue_styles', 99 );

function streamit_enqueue_styles() {
	wp_enqueue_style( 'parent-style', get_stylesheet_directory_uri() . '/style.css' );
	wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css' );
}

/**
 * Cloudflare Worker host — used for server-side requests only (server can resolve workers.dev).
 */
const STREAMIT_TMDB_PROXY_HOST = 'tmdb.youssefi-ashkan-ys.workers.dev';

/**
 * Direct TMDB image URL for server-side downloads (proxied via pre_http_request).
 *
 * @param string $image_path TMDB poster path (e.g. abc.jpg).
 * @param string $size       TMDB size (e.g. w500, original).
 * @return string
 */
function streamit_tmdb_direct_image_url( $image_path, $size = 'original' ) {
	if ( empty( $image_path ) ) {
		return '';
	}

	return 'https://image.tmdb.org/t/p/' . $size . '/' . ltrim( $image_path, '/' );
}

/**
 * Convert any TMDB image URL (proxy, worker, or direct) to a server-downloadable URL.
 *
 * @param string $url Image URL from Streamit.
 * @return string
 */
function streamit_tmdb_download_image_url( $url ) {
	if ( empty( $url ) ) {
		return $url;
	}

	if ( preg_match( '#(/t/p/[a-zA-Z0-9_./-]+)#', $url, $matches ) ) {
		return 'https://image.tmdb.org' . $matches[1];
	}

	return $url;
}

/**
 * Build a same-origin URL that proxies a TMDB image through WordPress (for browsers in Iran).
 *
 * @param string $tmdb_url_or_path Full TMDB image URL or path like /t/p/w500/foo.jpg.
 * @return string
 */
function streamit_tmdb_image_proxy_url( $tmdb_url_or_path ) {
	if ( preg_match( '#^https?://(?:image\.tmdb\.org|' . preg_quote( STREAMIT_TMDB_PROXY_HOST, '#' ) . ')(/t/p/.+)$#', $tmdb_url_or_path, $matches ) ) {
		$path = $matches[1];
	} elseif ( preg_match( '#^/t/p/.+#', $tmdb_url_or_path ) ) {
		$path = $tmdb_url_or_path;
	} else {
		return $tmdb_url_or_path;
	}

	return add_query_arg(
		array(
			'action' => 'streamit_tmdb_image',
			'path'   => $path,
		),
		admin_url( 'admin-ajax.php' )
	);
}

/**
 * Rewrite TMDB URLs for server-side HTTP (API + image download on import).
 *
 * @param string $url Request URL.
 * @return string
 */
function streamit_tmdb_server_proxy_url( $url ) {
	return str_replace(
		array(
			'https://image.tmdb.org',
			'http://image.tmdb.org',
			'https://api.themoviedb.org',
			'http://api.themoviedb.org',
		),
		array(
			'https://' . STREAMIT_TMDB_PROXY_HOST,
			'https://' . STREAMIT_TMDB_PROXY_HOST,
			'https://' . STREAMIT_TMDB_PROXY_HOST,
			'https://' . STREAMIT_TMDB_PROXY_HOST,
		),
		$url
	);
}

/**
 * Rewrite TMDB image URLs in admin/browser output to the WordPress image proxy.
 *
 * @param mixed $data String, array, or other value.
 * @return mixed
 */
function streamit_tmdb_rewrite_urls_for_browser( $data ) {
	if ( is_string( $data ) ) {
		return preg_replace_callback(
			'#https?://(?:image\.tmdb\.org|' . preg_quote( STREAMIT_TMDB_PROXY_HOST, '#' ) . ')(/t/p/[^"\'\s<>\)]+)#',
			function ( $matches ) {
				return streamit_tmdb_image_proxy_url( $matches[1] );
			},
			$data
		);
	}

	if ( is_array( $data ) ) {
		foreach ( $data as $key => $value ) {
			$data[ $key ] = streamit_tmdb_rewrite_urls_for_browser( $value );
		}
	}

	return $data;
}

/**
 * Proxy server-side HTTP requests through the Cloudflare Worker.
 */
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		// download_url() must not hit the logged-in admin proxy endpoint.
		if ( strpos( $url, 'admin-ajax.php' ) !== false && strpos( $url, 'streamit_tmdb_image' ) !== false ) {
			$query = array();
			$parts = wp_parse_url( $url );
			if ( ! empty( $parts['query'] ) ) {
				parse_str( $parts['query'], $query );
			}
			if ( ! empty( $query['path'] ) ) {
				$path = '/' . ltrim( wp_unslash( $query['path'] ), '/' );
				if ( preg_match( '#^/t/p/[a-zA-Z0-9_./-]+$#', $path ) ) {
					$url = 'https://image.tmdb.org' . $path;
				}
			}
		}

		if ( strpos( $url, 'api.themoviedb.org' ) === false && strpos( $url, 'image.tmdb.org' ) === false ) {
			return $pre;
		}

		$proxied_url     = streamit_tmdb_server_proxy_url( $url );
		$args['timeout'] = max( (int) ( $args['timeout'] ?? 5 ), 40 );

		return wp_remote_request( $proxied_url, $args );
	},
	10,
	3
);

/**
 * Streamit cache groups (list queries + per-object meta).
 *
 * @return string[]
 */
function streamit_child_cache_groups() {
	return array(
		'streamit_tvshows',
		'streamit_movies',
		'streamit_episodes',
		'streamit_person',
		'streamit_videos',
		'streamit_terms',
		'streamit_comments',
		'streamit_notifications',
		'streamit_playlists',
		'streamit_tvshow_playlists',
		'streamit_episode_playlists',
		'streamit_video_playlists',
		'tvshow',
		'movie',
		'episode',
		'person',
		'video',
		'term',
		'comment',
		'notification',
		'movie_playlist',
		'tvshow_playlist',
		'episode_playlist',
		'video_playlist',
		'playlist_relation',
	);
}

/**
 * Invalidate all Streamit caches (namespace bump + transient purge + object cache).
 *
 * flush_all_streamit_cache() alone does not bump namespace versions, which can
 * leave stale list/meta data visible after import or edit.
 */
function streamit_child_flush_streamit_cache() {
	if ( ! class_exists( 'Streamit_Advanced_Cache' ) ) {
		return;
	}

	foreach ( streamit_child_cache_groups() as $group ) {
		$cache = new Streamit_Advanced_Cache( $group );
		$cache->clear_group( true );
	}

	Streamit_Advanced_Cache::flush_all_streamit_cache();

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
}

/**
 * List-query cache group + tags used by Streamit get_* list methods.
 *
 * @param string $object_type episode|movie|tvshow|video|person.
 * @return array{group: string, list_tags: string[]}|null
 */
function streamit_child_get_list_cache_config( $object_type ) {
	$map = array(
		'episode' => array(
			'group'     => 'streamit_episodes',
			'list_tags' => array( 'episode_list' ),
		),
		'movie'   => array(
			'group'     => 'streamit_movies',
			'list_tags' => array( 'movies' ),
		),
		'tvshow'  => array(
			'group'     => 'streamit_tvshows',
			'list_tags' => array( 'tvshows', 'episode_list' ),
		),
		'video'   => array(
			'group'     => 'streamit_videos',
			'list_tags' => array( 'videos' ),
		),
		'person'  => array(
			'group'     => 'streamit_person',
			'list_tags' => array( 'person' ),
		),
	);

	$object_type = sanitize_key( (string) $object_type );

	return $map[ $object_type ] ?? null;
}

/**
 * Invalidate cached meta + single-object + list-query entries after Streamit meta writes.
 *
 * @param string $object_type episode|movie|tvshow|...
 * @param int    $object_id   Object ID.
 */
function streamit_child_invalidate_object_meta_cache( $object_type, $object_id ) {
	if ( ! class_exists( 'Streamit_Advanced_Cache' ) ) {
		return;
	}

	$object_id   = absint( $object_id );
	$object_type = sanitize_key( (string) $object_type );

	if ( ! $object_id || ! $object_type ) {
		return;
	}

	$meta_cache = new Streamit_Advanced_Cache( $object_type );
	$meta_cache->delete( $object_type . '_meta_' . $object_id );
	$meta_cache->invalidate_tags(
		array(
			$object_type . '_meta',
			$object_type . '_' . $object_id,
			'meta_data',
		)
	);

	$list_config = streamit_child_get_list_cache_config( $object_type );
	if ( ! $list_config ) {
		return;
	}

	$list_cache = new Streamit_Advanced_Cache( $list_config['group'] );
	$list_cache->delete( $object_type . '_' . $object_id );
	$list_cache->invalidate_tags(
		array_merge(
			array( 'single_' . $object_type . '_' . $object_id ),
			$list_config['list_tags']
		)
	);
}

foreach ( array( 'episode', 'movie', 'tvshow', 'video', 'person' ) as $object_type ) {
	$meta_type = 'streamit_' . $object_type;

	add_action(
		"added_{$meta_type}_meta",
		function ( $meta_id, $object_id ) use ( $object_type ) {
			streamit_child_invalidate_object_meta_cache( $object_type, $object_id );
		},
		10,
		2
	);

	add_action(
		"updated_{$meta_type}_meta",
		function ( $meta_id, $object_id ) use ( $object_type ) {
			streamit_child_invalidate_object_meta_cache( $object_type, $object_id );
		},
		10,
		2
	);

	add_action(
		"deleted_{$meta_type}_meta",
		function ( $meta_ids, $object_id ) use ( $object_type ) {
			streamit_child_invalidate_object_meta_cache( $object_type, $object_id );
		},
		10,
		2
	);
}

/**
 * Admin always reads fresh Streamit data (covers admin-ajax where get_current_screen() is null).
 */
add_filter(
	'streamit_disable_cache',
	function ( $disabled ) {
		return is_admin() ? true : $disabled;
	},
	10,
	1
);

/**
 * Country labels from _country meta for frontend templates.
 *
 * @param object $st_data Movie or TV show object.
 * @return string[]
 */
function streamit_child_get_country_labels( $st_data ) {
	if ( empty( $st_data ) || ! is_object( $st_data ) || ! method_exists( $st_data, 'get_meta' ) ) {
		return array();
	}

	$country = $st_data->get_meta( '_country' );
	if ( is_string( $country ) ) {
		$country = maybe_unserialize( $country );
	}

	if ( ! is_array( $country ) || empty( $country['labels'] ) || ! is_array( $country['labels'] ) ) {
		return array();
	}

	return array_values( array_filter( array_map( 'strval', $country['labels'] ) ) );
}

/**
 * ISO country codes used in the catalog for archive filters.
 *
 * @param string $post_type movie|tvshow
 * @return array<string, string> Code => label.
 */
function streamit_child_get_catalog_countries( $post_type = 'movie' ) {
	global $wpdb;

	$post_type = sanitize_key( (string) $post_type );
	if ( ! in_array( $post_type, array( 'movie', 'tvshow' ), true ) ) {
		return array();
	}

	$meta_table = ( 'tvshow' === $post_type )
		? $wpdb->prefix . 'streamit_tvshowmeta'
		: $wpdb->prefix . 'streamit_moviemeta';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT meta_value FROM {$meta_table} WHERE meta_key = %s AND meta_value != ''",
			'_country'
		)
	);

	if ( empty( $rows ) ) {
		return array();
	}

	$codes = array();
	foreach ( $rows as $raw ) {
		$data = maybe_unserialize( $raw );
		if ( ! is_array( $data ) || empty( $data['slugs'] ) || ! is_array( $data['slugs'] ) ) {
			continue;
		}
		foreach ( $data['slugs'] as $slug ) {
			$code = strtoupper( sanitize_text_field( (string) $slug ) );
			if ( $code ) {
				$codes[ $code ] = true;
			}
		}
	}

	if ( empty( $codes ) ) {
		return array();
	}

	$known = function_exists( 'streamit_country_list' ) ? streamit_country_list() : array();
	$out   = array();

	foreach ( array_keys( $codes ) as $code ) {
		$out[ $code ] = isset( $known[ $code ] ) ? $known[ $code ] : $code;
	}

	asort( $out, SORT_NATURAL | SORT_FLAG_CASE );

	return $out;
}

/**
 * Apply country filter to movie/TV archive queries.
 *
 * @param array $args Query args including filters from the frontend.
 * @return array
 */
function streamit_child_apply_country_archive_filter( $args ) {
	if ( empty( $args['filters']['countries'] ) || ! is_array( $args['filters']['countries'] ) ) {
		return $args;
	}

	$codes = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $code ) {
						return strtoupper( sanitize_text_field( (string) $code ) );
					},
					$args['filters']['countries']
				)
			)
		)
	);

	if ( ! $codes ) {
		return $args;
	}

	$country_or = array( 'relation' => 'OR' );

	foreach ( $codes as $code ) {
		$country_or[] = array(
			'key'     => '_country',
			'value'   => '%"' . $code . '"%',
			'compare' => 'LIKE',
		);
	}

	if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
		$args['meta_query'] = array();
	}

	$args['meta_query'][] = $country_or;

	return $args;
}
add_filter( 'streamit_movies_arguments', 'streamit_child_apply_country_archive_filter', 20 );
add_filter( 'streamit_tvshows_arguments', 'streamit_child_apply_country_archive_filter', 20 );

/**
 * Stage TV show archive filters that need SQL changes (release year on post_date).
 *
 * @param array $args Query args.
 * @return array
 */
function streamit_child_stage_tvshow_archive_query_extras( $args ) {
	$GLOBALS['streamit_child_tvshow_query_extras'] = array();

	if ( ! empty( $args['filters']['release_year'] ) ) {
		$GLOBALS['streamit_child_tvshow_query_extras']['release_year'] = sanitize_text_field(
			(string) $args['filters']['release_year']
		);
	}

	return $args;
}
add_filter( 'streamit_tvshows_arguments', 'streamit_child_stage_tvshow_archive_query_extras', 15 );

/**
 * Build a YEAR(post_date) SQL fragment for archive year filters.
 *
 * @param string $table_alias SQL table alias.
 * @param string $year_filter Year or start-end range.
 * @return string
 */
function streamit_child_build_post_date_year_sql( $table_alias, $year_filter ) {
	$table_alias = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $table_alias );

	if ( '' === $table_alias ) {
		return '';
	}

	if ( false !== strpos( $year_filter, '-' ) ) {
		$years = array_map( 'intval', explode( '-', $year_filter, 2 ) );
		if ( 2 === count( $years ) && $years[0] > 0 && $years[1] >= $years[0] ) {
			return sprintf(
				'YEAR(%s.post_date) BETWEEN %d AND %d',
				$table_alias,
				$years[0],
				$years[1]
			);
		}
	}

	$year = (int) $year_filter;
	if ( $year > 0 ) {
		return sprintf( 'YEAR(%s.post_date) = %d', $table_alias, $year );
	}

	return '';
}

/**
 * Append a WHERE clause to a prepared Streamit SQL string.
 *
 * @param string $query SQL query.
 * @param string $clause WHERE condition without leading AND/WHERE.
 * @return string
 */
function streamit_child_inject_sql_where( $query, $clause ) {
	if ( '' === $clause || ! is_string( $query ) || '' === $query ) {
		return $query;
	}

	foreach ( array( ' GROUP BY ', ' ORDER BY ', ' LIMIT ' ) as $needle ) {
		$pos = stripos( $query, $needle );
		if ( false === $pos ) {
			continue;
		}

		$before = substr( $query, 0, $pos );
		$after  = substr( $query, $pos );

		if ( false !== stripos( $before, ' WHERE ' ) ) {
			return $before . ' AND (' . $clause . ')' . $after;
		}

		return $before . ' WHERE (' . $clause . ')' . $after;
	}

	if ( false !== stripos( $query, ' WHERE ' ) ) {
		return $query . ' AND (' . $clause . ')';
	}

	return $query . ' WHERE (' . $clause . ')';
}

/**
 * Apply staged TV show release-year filter to generated SQL queries.
 *
 * @param array $queries Query bundle from Streamit.
 * @return array
 */
function streamit_child_apply_tvshow_release_year_query( $queries ) {
	$extras = $GLOBALS['streamit_child_tvshow_query_extras'] ?? array();
	if ( empty( $extras['release_year'] ) ) {
		return $queries;
	}

	$clause = streamit_child_build_post_date_year_sql( 'tv', $extras['release_year'] );
	if ( '' === $clause ) {
		return $queries;
	}

	foreach ( array( 'paginateQuery', 'countQuery' ) as $key ) {
		if ( ! empty( $queries[ $key ] ) ) {
			$queries[ $key ] = streamit_child_inject_sql_where( $queries[ $key ], $clause );
		}
	}

	return $queries;
}
add_filter( 'streamit_get_tvshows_query', 'streamit_child_apply_tvshow_release_year_query' );

/**
 * Show production/origin country after language on detail and card templates.
 */
add_action(
	'streamit_theme_after_template_part',
	function ( $template, $args ) {
		if ( false !== strpos( $template, 'movie_language.php' ) ) {
			streamit_get_template( 'movie/content/movie_country.php', $args );
		}

		if ( false !== strpos( $template, 'tvshow_language.php' ) ) {
			streamit_get_template( 'tvshow/content/tvshow_country.php', $args );
		}
	},
	10,
	2
);

/**
 * Long TMDB imports run via admin-ajax (not REST).
 */
add_action(
	'wp_ajax_streamit_ajax_post',
	function () {
		$route = isset( $_REQUEST['route_name'] ) ? sanitize_key( wp_unslash( $_REQUEST['route_name'] ) ) : '';

		if ( false !== strpos( $route, 'streamit_insert_import_content' ) ) {
			@set_time_limit( 300 );
			@ini_set( 'max_execution_time', '300' );
		}
	},
	1
);

/**
 * Reset import UI after each import (search again without full page reload).
 */
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'toplevel_page_streamit-content-import' !== $hook ) {
			return;
		}

		$js_path  = get_stylesheet_directory() . '/assets/js/import-fix.js';
		$css_path = get_stylesheet_directory() . '/assets/css/import-status.css';

		wp_enqueue_style(
			'streamit-child-import-status',
			get_stylesheet_directory_uri() . '/assets/css/import-status.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0'
		);

		wp_enqueue_style(
			'streamit-child-admin-vazirmatn',
			'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600&display=swap',
			array(),
			null
		);

		wp_enqueue_script(
			'streamit-child-import-fix',
			get_stylesheet_directory_uri() . '/assets/js/import-fix.js',
			array( 'jquery' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0',
			true
		);

		wp_localize_script(
			'streamit-child-import-fix',
			'streamitChildImport',
			array(
				'importing'        => 'در حال درون‌ریزی…',
				'importingTitle'   => 'درون‌ریزی در جریان است',
				'importingMessage' => 'لطفاً صبر کنید — سریال‌ها ممکن است چند دقیقه طول بکشد (فصل‌ها، قسمت‌ها، بازیگران). این صفحه را نبندید.',
				'successTitle'     => 'درون‌ریزی انجام شد',
				'successMessage'   => 'محتوا با موفقیت درون‌ریزی شد. می‌توانید مورد دیگری جستجو و درون‌ریزی کنید.',
				'errorTitle'       => 'درون‌ریزی ناموفق بود',
				'errorMessage'     => 'مشکلی پیش آمد. لیست را بررسی کنید یا دوباره تلاش کنید.',
				'elapsed'          => 'زمان سپری‌شده',
				'finishedIn'       => 'پایان در',
				'networkError'     => 'درخواست ناموفق یا زمان‌بر بود. ممکن است درون‌ریزی هنوز روی سرور در حال اجرا باشد.',
				'invalidResponse'  => 'پاسخ سرور خوانده نشد. اگر محتوا روی سایت شماست، درون‌ریزی موفق بوده است.',
				'uncertainTitle'   => 'احتمالاً درون‌ریزی انجام شده',
				'uncertainMessage' => 'اتصال قبل از تأیید قطع شد. اگر محتوا روی سایت شماست، درون‌ریزی موفق بوده — می‌توانید عنوان دیگری جستجو کنید.',
				'dismiss'          => 'بستن',
				'stillRunning'     => 'سرور هنوز در حال پاسخ است. اگر محتوا روی سایت شماست، درون‌ریزی موفق بوده — این پیام را ببندید و عنوان دیگری جستجو کنید.',
			)
		);
	}
);

/**
 * Serve TMDB images from the WordPress domain (browser cannot reach workers.dev in Iran).
 */
function streamit_tmdb_serve_image() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		status_header( 403 );
		exit;
	}

	$path = isset( $_GET['path'] ) ? wp_unslash( $_GET['path'] ) : '';
	$path = '/' . ltrim( $path, '/' );

	if ( ! preg_match( '#^/t/p/[a-zA-Z0-9_./-]+$#', $path ) ) {
		status_header( 400 );
		exit;
	}

	$response = wp_remote_get(
		'https://image.tmdb.org' . $path,
		array( 'timeout' => 40 )
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		status_header( 502 );
		exit;
	}

	$content_type = wp_remote_retrieve_header( $response, 'content-type' );
	if ( $content_type ) {
		header( 'Content-Type: ' . $content_type );
	}

	header( 'Cache-Control: public, max-age=86400' );
	echo wp_remote_retrieve_body( $response ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'wp_ajax_streamit_tmdb_image', 'streamit_tmdb_serve_image' );

/**
 * Rewrite TMDB image URLs in import search AJAX responses only.
 */
add_action(
	'admin_init',
	function () {
		if ( ! wp_doing_ajax() ) {
			return;
		}

		$route = isset( $_REQUEST['route_name'] ) ? sanitize_key( wp_unslash( $_REQUEST['route_name'] ) ) : '';
		if ( 'streamit_search_import_content' !== $route ) {
			return;
		}

		ob_start(
			function ( $buffer ) {
				return streamit_tmdb_rewrite_urls_for_browser( $buffer );
			}
		);
	},
	1
);

/**
 * Rewrite TMDB image URLs in Streamit REST responses (search thumbnails).
 * rest_pre_echo_response does not run when Streamit calls wp_send_json().
 */
add_filter(
	'rest_post_dispatch',
	function ( $response, $server, $request ) {
		$route = $request->get_route();

		if (
			false === strpos( $route, 'streamit_search_import_content' )
			&& false === strpos( $route, 'streamit_insert_import_content' )
		) {
			return $response;
		}

		if ( $response instanceof WP_REST_Response ) {
			$response->set_data( streamit_tmdb_rewrite_urls_for_browser( $response->get_data() ) );
		}

		return $response;
	},
	10,
	3
);

/**
 * Rewrite TMDB image URLs in REST API responses (fallback).
 */
add_filter(
	'rest_pre_echo_response',
	function ( $result ) {
		return streamit_tmdb_rewrite_urls_for_browser( $result );
	},
	10,
	1
);

/**
 * Multi-quality sources, download modal fallbacks, and admin guide.
 */
require_once get_stylesheet_directory() . '/inc/sources-download.php';

/**
 * Set up My Child Theme's textdomain.
 *
 * Declare textdomain for this child theme.
 * Translations can be added to the /languages/ directory.
 */
function streamit_child_theme_setup() {
	load_child_theme_textdomain( 'streamit', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'streamit_child_theme_setup' );

function remove_default_post_type() {
	remove_menu_page( 'edit.php' );
}
add_action( 'admin_menu', 'remove_default_post_type' );

function streamit_enqueue_custom_font() {
	wp_enqueue_style( 'vazirmatn-font', 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap', array(), null );
}
add_action( 'wp_enqueue_scripts', 'streamit_enqueue_custom_font' );

function streamit_enqueue_fontawesome() {
	wp_enqueue_style( 'fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css', array(), '6.5.2' );
}
add_action( 'wp_enqueue_scripts', 'streamit_enqueue_fontawesome' );

<?php
/**
 * Movie editor UI + AJAX for trailer uploads to the media server.
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

class Movies_WP_Trailer_Upload_Admin {

	const NONCE_ACTION = 'movies_wp_upload_trailer';
	const AJAX_ACTION  = 'movies_wp_upload_trailer';

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_footer', array( __CLASS__, 'render_streamit_field' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'handle_ajax' ) );
	}

	/**
	 * Streamit Additional Data has no render hook. Output the control in the footer
	 * so script can place it under the existing #_name_trailer_link_field.
	 */
	public static function render_streamit_field() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$kinds  = array(
			'admin_page_streamit-edit-movie'  => 'movie',
			'admin_page_streamit-edit-tvshow' => 'tvshow',
		);
		if ( ! $screen || ! isset( $kinds[ $screen->id ] ) ) {
			return;
		}
		$kind     = $kinds[ $screen->id ];
		$title_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $title_id <= 0 || ! self::user_can_edit_streamit( $kind ) || ! self::streamit_title_exists( $kind, $title_id ) ) {
			return;
		}
		self::render_box( $title_id, $kind );
	}

	/**
	 * @param int    $title_id
	 * @param string $kind movie|tvshow
	 */
	private static function render_box( $title_id, $kind ) {
		$title_id = (int) $title_id;
		$dir_ok   = ! is_wp_error( Movies_WP_Trailer_Upload_Service::resolve_directory( $kind, $title_id ) );
		$links    = Movies_WP_Trailer_Upload_Service::trailer_link_list(
			Movies_WP_Trailer_Upload_Service::get_meta( $kind, $title_id, '_name_trailer_links' )
		);
		$primary  = trim( (string) Movies_WP_Trailer_Upload_Service::get_meta( $kind, $title_id, '_name_trailer_link' ) );
		if ( '' !== $primary && ! in_array( $primary, $links, true ) ) {
			array_unshift( $links, $primary );
		}
		$max_mb = max( 1, (int) ceil( Movies_WP_Trailer_Upload_Client::max_bytes() / 1048576 ) );
		?>
		<div
			id="movies-wp-trailer-upload-root"
			class="movies-wp-trailer-upload"
			hidden
			data-post-id="<?php echo esc_attr( (string) $title_id ); ?>"
			data-content-type="<?php echo esc_attr( $kind ); ?>"
			data-max-bytes="<?php echo esc_attr( (string) Movies_WP_Trailer_Upload_Client::max_bytes() ); ?>"
		>
			<div class="movies-wp-trailer-actions">
				<input
					type="file"
					id="movies-wp-trailer-file"
					class="regular-text"
					accept=".mp4,.webm,.mkv,video/mp4,video/webm,video/x-matroska"
					multiple
					<?php disabled( ! $dir_ok ); ?>
				/>
				<button
					type="button"
					class="button"
					id="movies-wp-trailer-upload-btn"
					<?php disabled( ! $dir_ok ); ?>
				>
					<?php esc_html_e( 'Add trailers', 'movies-wp' ); ?>
				</button>
			</div>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: maximum trailer size in mebibytes */
						__( 'MP4, WebM or MKV · Maximum %d MiB · each new filename is added', 'movies-wp' ),
						$max_mb
					)
				);
				?>
			</p>
			<ul id="movies-wp-trailer-list" class="movies-wp-trailer-list">
				<?php foreach ( $links as $link ) : ?>
					<li><?php echo esc_html( $link ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p class="movies-wp-trailer-progress" hidden>
				<progress id="movies-wp-trailer-progress-bar" max="100" value="0"></progress>
				<span id="movies-wp-trailer-progress-label">0%</span>
			</p>
			<p
				id="movies-wp-trailer-status"
				class="description movies-wp-trailer-status<?php echo $dir_ok ? '' : ' is-warn'; ?>"
				role="status"
				aria-live="polite"
			>
				<?php
				if ( ! $dir_ok ) {
					esc_html_e( 'Configure this title’s media directory before uploading a trailer.', 'movies-wp' );
				}
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * @param string $hook
	 */
	public static function enqueue( $hook ) {
		$screens = array( 'admin_page_streamit-edit-movie', 'admin_page_streamit-edit-tvshow' );
		if ( ! in_array( $hook, $screens, true ) ) {
			return;
		}

		$js  = MOVIES_WP_MEDIA_AUTOMATION_DIR . '/assets/js/trailer-upload-admin.js';
		$css = MOVIES_WP_MEDIA_AUTOMATION_DIR . '/assets/css/trailer-upload-admin.css';

		wp_enqueue_style(
			'movies-wp-trailer-upload-admin',
			plugins_url( 'assets/css/trailer-upload-admin.css', MOVIES_WP_MEDIA_AUTOMATION_FILE ),
			array(),
			file_exists( $css ) ? (string) filemtime( $css ) : '1.0'
		);

		wp_enqueue_script(
			'movies-wp-trailer-upload-admin',
			plugins_url( 'assets/js/trailer-upload-admin.js', MOVIES_WP_MEDIA_AUTOMATION_FILE ),
			array(),
			file_exists( $js ) ? (string) filemtime( $js ) : '1.0',
			true
		);

		wp_localize_script(
			'movies-wp-trailer-upload-admin',
			'moviesWpTrailerUpload',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => array(
					'selectFile'       => __( 'Choose a trailer file first.', 'movies-wp' ),
					'uploading'        => __( 'Uploading…', 'movies-wp' ),
					'success'          => __( 'Trailer uploaded and link saved.', 'movies-wp' ),
					'genericError'     => __( 'Trailer upload failed.', 'movies-wp' ),
					'tooLarge'         => __( 'The selected file exceeds the maximum allowed size.', 'movies-wp' ),
				),
			)
		);
	}

	public static function handle_ajax() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'code'    => 'trailer_upload_bad_nonce',
					'message' => __( 'Security check failed. Reload the page and try again.', 'movies-wp' ),
				),
				403
			);
		}

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$kind    = isset( $_POST['content_type'] ) ? sanitize_key( wp_unslash( $_POST['content_type'] ) ) : 'movie';
		if ( 'tvshow' === $kind ) {
			if ( $post_id <= 0 || ! self::tvshow_exists( $post_id ) ) {
				wp_send_json_error(
					array(
						'code'    => 'trailer_upload_invalid_tvshow',
						'message' => __( 'Invalid TV show.', 'movies-wp' ),
					),
					400
				);
			}
			if ( ! self::user_can_edit_tvshow() ) {
				wp_send_json_error(
					array(
						'code'    => 'trailer_upload_forbidden',
						'message' => __( 'You are not allowed to upload trailers for this TV show.', 'movies-wp' ),
					),
					403
				);
			}
		} else {
			$kind = 'movie';
			if ( $post_id > 0 && 'movie' === get_post_type( $post_id ) ) {
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					wp_send_json_error(
						array(
							'code'    => 'trailer_upload_forbidden',
							'message' => __( 'You are not allowed to upload trailers for this movie.', 'movies-wp' ),
						),
						403
					);
				}
			} elseif ( self::streamit_title_exists( 'movie', $post_id ) ) {
				if ( ! self::user_can_edit_streamit( 'movie' ) ) {
					wp_send_json_error(
						array(
							'code'    => 'trailer_upload_forbidden',
							'message' => __( 'You are not allowed to upload trailers for this movie.', 'movies-wp' ),
						),
						403
					);
				}
			} else {
				wp_send_json_error(
					array(
						'code'    => 'trailer_upload_invalid_movie',
						'message' => __( 'Invalid movie.', 'movies-wp' ),
					),
					400
				);
			}
		}

		if ( empty( $_FILES['trailer'] ) || ! is_array( $_FILES['trailer'] ) ) {
			wp_send_json_error(
				array(
					'code'    => 'trailer_upload_missing_file',
					'message' => __( 'No trailer file was uploaded.', 'movies-wp' ),
				),
				400
			);
		}

		$file = $_FILES['trailer'];
		$err  = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_INI_SIZE === $err || UPLOAD_ERR_FORM_SIZE === $err ) {
			wp_send_json_error(
				array(
					'code'    => 'trailer_upload_too_large',
					'message' => __( 'The trailer file exceeds the maximum allowed size.', 'movies-wp' ),
				),
				413
			);
		}
		if ( UPLOAD_ERR_OK !== $err ) {
			wp_send_json_error(
				array(
					'code'    => 'trailer_upload_missing_file',
					'message' => __( 'No trailer file was uploaded.', 'movies-wp' ),
				),
				400
			);
		}

		$tmp  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			wp_send_json_error(
				array(
					'code'    => 'trailer_upload_missing_file',
					'message' => __( 'No trailer file was uploaded.', 'movies-wp' ),
				),
				400
			);
		}

		$result = Movies_WP_Trailer_Upload_Service::upload_for_title( $kind, $post_id, $tmp, $name );
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 400;
			if ( $status < 400 ) {
				$status = 400;
			}
			$payload = array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			);
			wp_send_json_error( $payload, $status );
		}

		wp_send_json_success(
			array(
				'path'    => (string) $result['path'],
				'links'   => isset( $result['links'] ) && is_array( $result['links'] ) ? $result['links'] : array(),
				'message' => __( 'Trailer uploaded and link saved.', 'movies-wp' ),
			)
		);
	}

	/**
	 * @param string $kind movie|tvshow
	 * @return bool
	 */
	private static function user_can_edit_streamit( $kind ) {
		$prefix = defined( 'STREAMIT_CORE_PREFIX' ) ? STREAMIT_CORE_PREFIX : 'streamit_core_';
		$cap    = $prefix . ( 'tvshow' === $kind ? 'edit_tvshow' : 'edit_movie' );
		return current_user_can( $cap );
	}

	/**
	 * @param string $kind movie|tvshow
	 * @param int    $title_id
	 * @return bool
	 */
	private static function streamit_title_exists( $kind, $title_id ) {
		$title_id = (int) $title_id;
		if ( $title_id <= 0 ) {
			return false;
		}
		if ( 'tvshow' === $kind ) {
			if ( ! function_exists( 'streamit_get_tvshow' ) ) {
				return false;
			}
			return ! empty( streamit_get_tvshow( $title_id ) );
		}
		if ( ! function_exists( 'streamit_get_movie' ) ) {
			return false;
		}
		return ! empty( streamit_get_movie( $title_id ) );
	}

	/**
	 * @param int $tvshow_id
	 * @return bool
	 */
	private static function tvshow_exists( $tvshow_id ) {
		return self::streamit_title_exists( 'tvshow', $tvshow_id );
	}

	/**
	 * @return bool
	 */
	private static function user_can_edit_tvshow() {
		return self::user_can_edit_streamit( 'tvshow' );
	}
}

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
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'handle_ajax' ) );
	}

	public static function register_meta_box() {
		add_meta_box(
			'movies-wp-trailer-upload',
			__( 'Upload Trailer', 'movies-wp' ),
			array( __CLASS__, 'render_meta_box' ),
			'movie',
			'side',
			'default'
		);
	}

	/**
	 * @param WP_Post $post
	 */
	public static function render_meta_box( $post ) {
		$post_id = isset( $post->ID ) ? (int) $post->ID : 0;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			echo '<p>' . esc_html__( 'You are not allowed to upload trailers for this movie.', 'movies-wp' ) . '</p>';
			return;
		}

		$dir      = Movies_WP_Trailer_Upload_Service::get_movie_meta( $post_id, '_media_directory' );
		$trailer  = Movies_WP_Trailer_Upload_Service::get_movie_meta( $post_id, '_name_trailer_link' );
		$dir_ok   = ! is_wp_error( Movies_WP_Trailer_Upload_Service::resolve_media_directory( $post_id ) );
		$has_link = Movies_WP_Trailer_Upload_Service::requires_replace_confirmation( $trailer );
		$max_mb   = max( 1, (int) ceil( Movies_WP_Trailer_Upload_Client::max_bytes() / 1048576 ) );

		wp_nonce_field( self::NONCE_ACTION, 'movies_wp_trailer_upload_nonce' );
		?>
		<div
			id="movies-wp-trailer-upload-root"
			class="movies-wp-trailer-upload"
			data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
			data-has-trailer="<?php echo $has_link ? '1' : '0'; ?>"
			data-max-bytes="<?php echo esc_attr( (string) Movies_WP_Trailer_Upload_Client::max_bytes() ); ?>"
		>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: max size in MiB */
						__( 'Upload an MP4, WebM, or MKV trailer (max %d MiB) to this movie’s Trailer folder on the media server.', 'movies-wp' ),
						$max_mb
					)
				);
				?>
			</p>
			<p class="description">
				<?php
				esc_html_e(
					'Replacing a trailer requires a new filename. WordPress metadata updates only after a successful upload. The previous media-server file is left on disk (not deleted or overwritten).',
					'movies-wp'
				);
				?>
			</p>

			<p>
				<strong><?php esc_html_e( 'Media directory', 'movies-wp' ); ?>:</strong>
				<code><?php echo $dir_ok ? esc_html( (string) $dir ) : esc_html__( '(missing or invalid)', 'movies-wp' ); ?></code>
			</p>

			<div id="movies-wp-trailer-replace-panel" <?php echo $has_link ? '' : 'hidden'; ?>>
				<p>
					<strong><?php esc_html_e( 'Current trailer', 'movies-wp' ); ?>:</strong>
					<code id="movies-wp-trailer-current-path"><?php echo $has_link ? esc_html( (string) $trailer ) : ''; ?></code>
				</p>
				<p>
					<label>
						<input type="checkbox" id="movies-wp-trailer-confirm-replace" value="1" />
						<?php esc_html_e( 'Replace the existing trailer link after a successful upload', 'movies-wp' ); ?>
					</label>
				</p>
			</div>
			<p>
				<input
					type="file"
					id="movies-wp-trailer-file"
					accept=".mp4,.webm,.mkv,video/mp4,video/webm,video/x-matroska"
					<?php disabled( ! $dir_ok ); ?>
				/>
			</p>

			<p>
				<button
					type="button"
					class="button button-primary"
					id="movies-wp-trailer-upload-btn"
					<?php disabled( ! $dir_ok ); ?>
				>
					<?php esc_html_e( 'Upload Trailer', 'movies-wp' ); ?>
				</button>
			</p>

			<div class="movies-wp-trailer-progress" hidden>
				<progress id="movies-wp-trailer-progress-bar" max="100" value="0"></progress>
				<span id="movies-wp-trailer-progress-label">0%</span>
			</div>

			<p id="movies-wp-trailer-status" class="movies-wp-trailer-status" role="status" aria-live="polite"></p>
		</div>
		<?php
	}

	/**
	 * @param string $hook
	 */
	public static function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'movie' !== $screen->post_type ) {
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
					'confirmRequired'  => __( 'Confirm replacement of the existing trailer before uploading.', 'movies-wp' ),
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
		if ( $post_id <= 0 || 'movie' !== get_post_type( $post_id ) ) {
			wp_send_json_error(
				array(
					'code'    => 'trailer_upload_invalid_movie',
					'message' => __( 'Invalid movie.', 'movies-wp' ),
				),
				400
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error(
				array(
					'code'    => 'trailer_upload_forbidden',
					'message' => __( 'You are not allowed to upload trailers for this movie.', 'movies-wp' ),
				),
				403
			);
		}

		$confirm = ! empty( $_POST['confirm_replace'] ) && '1' === (string) wp_unslash( $_POST['confirm_replace'] );

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

		$result = Movies_WP_Trailer_Upload_Service::upload_for_movie( $post_id, $tmp, $name, $confirm );
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
			if ( is_array( $data ) && ! empty( $data['requires_confirm'] ) ) {
				$payload['requires_confirm'] = true;
			}
			wp_send_json_error( $payload, $status );
		}

		wp_send_json_success(
			array(
				'path'     => (string) $result['path'],
				'replaced' => ! empty( $result['replaced'] ),
				'message'  => __( 'Trailer uploaded and link saved.', 'movies-wp' ),
			)
		);
	}
}

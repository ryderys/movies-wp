<?php
/**
 * Centered search modal (downloadha-style prominent search).
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the search modal markup in the footer.
 */
function streamit_child_render_search_modal() {
	if ( is_admin() ) {
		return;
	}
	?>
	<div class="modal fade" id="streamit-search-modal" tabindex="-1" aria-labelledby="streamit-search-modal-label" aria-hidden="true">
		<div class="modal-dialog modal-dialog-scrollable streamit-search-modal__dialog">
			<div class="modal-content streamit-search-modal">
				<div class="modal-header border-0 pb-0">
					<div class="streamit-search-modal__input-wrap input-group">
						<span class="input-group-text border-0 bg-transparent text-white" aria-hidden="true">
							<svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></circle>
								<path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
							</svg>
						</span>
						<label class="visually-hidden" for="streamit-search-modal-input" id="streamit-search-modal-label">جستجو</label>
						<input
							type="search"
							id="streamit-search-modal-input"
							class="form-control form-control-lg border-0 shadow-none"
							placeholder="<?php echo esc_attr__( 'جستجو در فیلم، سریال، بازیگر...', 'streamit' ); ?>"
							autocomplete="off"
						/>
					</div>
					<button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal" aria-label="بستن"></button>
				</div>
				<div class="modal-body streamit-search-modal__body">
					<div id="streamit-search-modal-results" class="streamit-search-modal__results" aria-live="polite"></div>
				</div>
			</div>
		</div>
	</div>
	<button
		type="button"
		id="streamit-search-modal-open"
		class="visually-hidden"
		data-bs-toggle="modal"
		data-bs-target="#streamit-search-modal"
		tabindex="-1"
		aria-hidden="true"
	></button>
	<?php
}
add_action( 'wp_footer', 'streamit_child_render_search_modal', 5 );

/**
 * AJAX: return full tabbed search results for the modal.
 */
function streamit_child_ajax_modal_search() {
	check_ajax_referer( 'streamit_child_search', 'nonce' );

	$query = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

	if ( strlen( $query ) < 2 ) {
		wp_send_json_success(
			'<div class="streamit-search-modal__hint">' . esc_html__( 'حداقل ۲ حرف وارد کنید.', 'streamit' ) . '</div>'
		);
	}

	ob_start();
	streamit_get_template( 'search/ajax_search.php', array( 's' => $query ) );
	$html = ob_get_clean();

	wp_send_json_success( $html );
}
add_action( 'wp_ajax_streamit_child_modal_search', 'streamit_child_ajax_modal_search' );
add_action( 'wp_ajax_nopriv_streamit_child_modal_search', 'streamit_child_ajax_modal_search' );

/**
 * Enqueue search modal assets.
 */
function streamit_child_enqueue_search_modal_assets() {
	if ( is_admin() ) {
		return;
	}

	$theme_dir = get_stylesheet_directory();
	$theme_uri = get_stylesheet_directory_uri();

	$css_path = $theme_dir . '/assets/css/search-modal.css';
	$js_path  = $theme_dir . '/assets/js/search-modal.js';

	wp_enqueue_style(
		'streamit-child-search-modal',
		$theme_uri . '/assets/css/search-modal.css',
		array(),
		file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0'
	);

	wp_enqueue_script(
		'streamit-child-search-modal',
		$theme_uri . '/assets/js/search-modal.js',
		array( 'jquery', 'streamit-main' ),
		file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0',
		true
	);

	wp_localize_script(
		'streamit-child-search-modal',
		'streamitChildSearch',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'streamit_child_search' ),
			'hint'    => 'نام فیلم، سریال یا بازیگر را جستجو کنید...',
			'empty'   => 'نتیجه‌ای یافت نشد.',
			'error'   => 'خطا در جستجو. لطفاً دوباره تلاش کنید.',
			'loading' => 'در حال جستجو...',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'streamit_child_enqueue_search_modal_assets', 110 );
